<?php 
/**
 * @file registration.php
 * @brief This file is registrating new user, either Hunter or Quest Maker.
 *
 * This file consists of form that can create new users. It validates all fields and can redirect to login file. 
 */

session_start(); 
require "../database.php";
$sqlError = $error = "";

$loggedIn = false;
try{
	$loggedIn = loggedIn();
}catch(PDOException $e){
	$sqlError = $sqlError . "<br>" . $e->getMessage();
}

if(!$loggedIn){
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_GET['userType']) && $_GET['userType']=='questMaker'){
      $username = validateInput($_POST["username"]);
      $password = $_POST["password"];
      $password_repeat = $_POST["password_repeat"];

      if(empty($username)){
        $error = $error . "Nazwa nie może być pusta.<br>";
      }else if(empty($password) || empty($password_repeat)){
        $error = $error . "Hasło nie może być puste.<br>";
      }else{
        $sqlError = $error = "";
        try{
          // check if name already exists
          $checkNameQuery = $pdo->prepare("SELECT (imię=:username) FROM prj.zleceniodawca WHERE imię=:username");
          $checkNameQuery->execute(['username'=>$username]);
          if(($checkNameQuery == true) && ($checkNameQuery->fetchColumn() == 0)){
            // validating password
            if(strcmp($password, $password_repeat) === 0){
              // creating new quest maker
              $createQuestMakerQuery = $pdo->prepare("INSERT INTO prj.zleceniodawca (id, imię, hasło_hash) VALUES (DEFAULT, :username, crypt(:password, gen_salt('md5')))");
              $createQuestMakerQuery->execute(['username'=>$username, 'password'=>$password]);
              if(($createQuestMakerQuery == true) && ($createQuestMakerQuery->rowCount() == 1)){
                header("Location: http://pascal.fis.agh.edu.pl/~2mueller/loging/login.php?userType=questMaker");
                die();
              }else{
                $error = $error . "Wystąpił błąd, podczas tworzenia zleceniodawcy.<br>";    
              }
            }else{
              $error = $error . "Podane hasło się nie zgadzają.<br>";  
            }
          }else{
            $error = $error . "Podane imię, już istnieje.<br>";
          }
        }catch(PDOException $e){
          $sqlError = $sqlError . "<br>" . $e->getMessage();
        }
      }
    }else{
      $username = $password = $password_repeat = $id_race = $id_class = "";
      $username = validateInput($_POST["username"]);
      $password = $_POST["password"];
      $password_repeat = $_POST["password_repeat"];
      $id_race = $_POST["id_race"];
      $id_class = $_POST["id_class"];

      $_SESSION["username"] = $username;
      $_SESSION["password"] = $password;
      
      $registerError = registerHunter($username, $password, $password_repeat, $id_race, $id_class);
      if($registerError == ""){
        header("Location: http://pascal.fis.agh.edu.pl/~2mueller/loging/login.php?userType=hunter");
        die();
      }
      $error = $error . $registerError;
    }
  }
}else{
  header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
	die();
}

/**
 * Function that creates new hunter
 * 
 * If username or password are empty, hunter cannot be created. Next username must be unique for a hunter. 
 * After that id_race and id_class must exist in database. Only after all the checks hunter can be created.
 * 
 * @param[in] $username Name of hunter.
 * @param[in] $password Password of hunter.
 * @param[in] $password_repeat Repeated password, must be the same as $password.
 * @param[in] $id_race Race of a hunter.
 * @param[in] $id_class Class of a hunter.
 * @return Returns empty string on successs, else return error message.
 */
function registerHunter($username, $password, $password_repeat, $id_race, $id_class){
  global $pdo;
  try{
    $pdo->beginTransaction();
    // check if provided name or password are empty
    if(empty($username)) { throw new Exception("Nazwa nie może być pusta."); }
    if(empty($password)){ throw new Exception("Hasło nie może być puste."); }
    // check if password equals repeated password
    if(strcmp($password, $password_repeat) !== 0){ throw new Exception("Podane hasła nie są takie same."); }
        
    // validate unique username
    if(validateHunterName($username) == false) { throw new Exception("Podane imię, już istnieje."); }

    // validate id_race
    try{
      $validateRaceQuery = $pdo->prepare("SELECT (id=:id_rasa) as match_id, rasa_łowcy FROM prj.rasa WHERE id=:id_rasa;");
      $validateRaceQuery->execute(['id_rasa'=>$id_race]);
      $raceData = $validateRaceQuery->fetch(); 
    }catch(PDOEXception $e){
      throw new Exception("Nie można sprawdzić rasy.");
    }
    if($raceData['match_id'] == 0) { throw new Exception("Podana rasa, nie istnieje."); }
    if($raceData['rasa_łowcy'] == 0) { throw new Exception("Podana rasa, nie jest rasą rozumną."); }

    // validate id_class
    try{
      $validateClassQuery = $pdo->prepare("SELECT (id=:id_class) FROM prj.klasa WHERE id=:id_class;");
      $validateClassQuery->execute(['id_class'=>$id_class]);  
    }catch(PDOEXception $e){
      throw new Exception("Nie można sprawdzić klasy.");
    }
    if($validateClassQuery->fetchColumn() == 0) { throw new Exception("Podana klasa, nie istnieje."); }

    // create new hunter in database
    try{
      $createHunterQuery = $pdo->prepare("INSERT INTO prj.łowca (id, imię, hasło_hash, id_rasa, id_klasa) VALUES 
                      (DEFAULT, :username, crypt(:password, gen_salt('md5')), :id_rasa, :id_klasa);");
      $createHunterQuery->execute(['username'=>$username, 'password'=>$password, 'id_rasa'=>$id_race, 'id_klasa'=>$id_class]);
    }catch(PDOException $e){
      throw new Exception("Nie można zarejestrować łowcy.");
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function checking if given username is unique and valid, for hunter.
 *
 * @return Returns true if username is unique and valid, else return false.
 */
function validateHunterName($username){
  global $pdo;
  try{
    $checkUniqueName = $pdo->prepare("SELECT (imię=:username) FROM prj.łowca WHERE imię=:username;");
    $checkUniqueName->execute(['username'=>$username]);
    if($checkUniqueName->fetchColumn() == true) { return false; }
  }catch(PDOException $e){
    return false;
  }
  return true;
}
?>

<!DOCTYPE html>
<html lang="pl-PL">
<head>
	<!-- get rid of favicon.ico request -->
	<link rel="icon" href="data:image/png;base64,iVBORw0KGgo="> 
	<meta http-equiv="Content-Language" content="pl">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="author" content="Maciej Muller">

	<link rel="Stylesheet" href="../styles.css">
	<link rel="Stylesheet" href="../styles_tablet.css">
	<link rel="Stylesheet" href="../styles_mobile.css">
	<link rel="Stylesheet" href="styles_loging.css">
	<title>Projekt</title>
</head>
<body class="grain-background">

<header>
	<a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php">
		<img id="headerTitle" src="../assets/przewodnik_lowcy_header.png" alt="Przewodnik Łowcy"> 
	</a>
</header>

<section id="mainWindowWrapper">
<section id="mainWindow">

	<div id="content">

      <div class="center-middle">
        <h1>Rejestracja</h1>
        <form method="GET" name="userSelectionForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
          <select id="userType" name="userType" onchange="document.userSelectionForm.submit()">
            <option value="hunter" <?php if($_GET['userType'] == 'hunter') echo "selected=\"selected\""; ?> >Łowca</option>
            <option value="questMaker" <?php if($_GET['userType'] == 'questMaker') echo "selected=\"selected\""; ?> >Zleceniodawca</option>
          </select>
        </form>


      <?php if(isset($_GET['userType']) && $_GET['userType']=='questMaker'){ ?>
        <form method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/loging/registration.php?userType=questMaker" enctype="multipart/form-data">
          <p>Imię/Nazwa<sup>*</sup></p>
          <input type="text" name="username"><br>
          <p>Hasło<sup>*</sup></p>
          <input type="password" name="password"> <br>
          <p>Powtórz hasło<sup>*</sup></p>
          <input type="password" name="password_repeat"> <br>
          <input style="width: 350px; margin-left: -50px;" id="registrationButton" class="button"  type="submit" value="Zarejestruj się jako zleceniodawca">
        </form>

      <?php }else{ ?>  
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
          <p>Imię<sup>*</sup></p>
          <input type="text" name="username"><br>
          <p>Hasło<sup>*</sup></p>
          <input type="password" name="password"> <br>
          <p>Powtórz hasło<sup>*</sup></p>
          <input type="password" name="password_repeat"> <br>
          <p>Rasa</p>
          <select id="race-select" name="id_race">
            <?php 
                      // get all values from prj.rasa 
            try{
              $racesQuery = $pdo->query("SELECT id, nazwa FROM prj.rasa WHERE rasa_łowcy='t'");
              if($racesQuery){
                foreach ($racesQuery->fetchAll() as $race){
                  echo "<option value='" . $race["id"] . "'>" . $race["nazwa"] . "</option>";
                }
              }else{
                echo $sqlError . " Nie można pobrać ras.";
              }
              echo $racesQuery->fetchColumn();
            }catch(PDOException $e){
              $sqlError = $sqlError . "<br>" . $e->getMessage();
            }
                      ?>
          </select><br>
          <p>Klasa</p>
          <select id="class-select" name="id_class">
            <?php 
            // get all values from prj.klasa
            try{
              $classesQuery = $pdo->query("SELECT id, nazwa FROM prj.klasa;");
              if($classesQuery){
                foreach ($classesQuery as $class){
                  echo "<option value='" . $class["id"] . "'>" . $class["nazwa"] . "</option>";
                }
              }else{
                $sqlError = $sqlError . " Nie można pobrać ras.";
              }
            }catch(PDOException $e){
              $sqlError = $sqlError . "<br>" . $e->getMessage();
            }
                      ?>
          </select><br>
          <input style="width: 350px; margin-left: -50px;" id="registrationButton" class="button"  type="submit" value="Zarejestruj się jako łowca">
        </form>
        <?php } ?>  
        <?php echo "<p class=\"error-text\">".$error."</p>" ?>
        <?php echo "<p class=\"error-text\">".$sqlError."</p>" ?>
        <a id="loginButton" class="button" href="login.php">Przejdz do logowania</a>
        </div>
      
	</div>
</section>
</section>

<!-- <footer>

</footer> -->

</body>
</html>

