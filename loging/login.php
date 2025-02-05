<?php 
/**
 * @file login.php
 * @brief A file where user can logged in as Hunter or  a Quest Maker.
 *
 * Here is validated all thats necessary, password and username, before being able to login. It also redirects to registration.
 */


session_start(); 

require "../database.php";
$sqlError = $loginError = "";

$loggedIn = false;
try{
	$loggedIn = loggedIn();
}catch(PDOException $e){
	$sqlError = $sqlError . "<br>" . $e->getMessage();
}

if(!$loggedIn){
	$username = $password = "";
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST['loginUser'])){
  		$username = validateInput($_POST["username"]);
  		$password = $_POST["password"];
  
  		$hashed_password = $id = "";
      if(isset($_GET['userType']) && $_GET['userType'] == 'questMaker'){
        // login as quest maker
        $loginError = loginAsQuestMaker($username, $password);
        if($loginError == "")
          include "logout.php";
      }else{
        // login as hunter
        $loginError = loginAsHunter($username, $password);
        if($loginError == "")
          include "logoutQuestMaker.php";
      }
  	}
  }
}else{
	header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
	die();
}

/**
 * Function that checks if given username and password, matches any hunter in database.
 * 
 * Function that checks if given username and password, matches any hunter in database. 
 * It also sets-up a cookie so user dont need to login, everytime they open this aplication.
 * 
 * @param[in] $username Username/imię of a hunter.
 * @param[in] $password Password of a hunter.
 * 
 * @return Returns empty string on success, else returns error message.
 */
function loginAsHunter($username, $password){
  global $pdo;
  try{
    $checkPasswordName = $pdo->prepare("SELECT id, (hasło_hash=crypt(:password, hasło_hash)) AS password_match FROM prj.łowca WHERE imię=:username;");
    $checkPasswordName->execute(['password' => $password, 'username' => $username]);
    $result = $checkPasswordName->fetch();
    if(($checkPasswordName == true) && $result){
        $id = $result['id'];
        $password_match = $result['password_match'];
        if($password_match == 1){
          // set cookie
          try{
            // generate hashToken
            $getPassHash = $pdo->prepare("SELECT crypt(:password, gen_salt('md5'));");
            $getPassHash->execute(['password' => $password]);
            $hashed_loginToken = $id . $getPassHash->fetchColumn();
    
            // logged in cookie, that lasts 30 days, keeps user logged in
            setcookie("authLoginToken", $hashed_loginToken, time()+(86400*30), "/", NULL);
            $updateAuthToken = $pdo->prepare("UPDATE prj.łowca SET token_autoryzacji=:authLoginCookie WHERE id=:id");
            $updateAuthToken->execute(['authLoginCookie' => $hashed_loginToken, 'id'=>$id]);
            header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
            die();
          }catch(PDOException $e){
            throw new Exception("Coś poszło nie tak. Nie udało się zalogować.");
          }
        }else{
          throw new Exception("Nie udało się zalogować.");
        }
    }else{
      throw new Exception("Błędna nazwa lub hasło.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function that checks if given username and password, matches any quest maker in database. 
 * 
 * Function that checks if given username and password, matches any quest maker in database. 
 * It also sets-up a cookie so user dont need to login, everytime they open this aplication.
 * 
 * @param[in] $username Username/imię of a quest maker.
 * @param[in] $password Password of a quest maker.
 * 
 * @return Returns empty string on success, else returns error message.
 */
function loginAsQuestMaker($username, $password){
  global $pdo;
  try{
    $checkPasswordName = $pdo->prepare("SELECT id, (hasło_hash=crypt(:password, hasło_hash)) AS password_match FROM prj.zleceniodawca WHERE imię=:username;");
    $checkPasswordName->execute(['password' => $password, 'username' => $username]);
    $result = $checkPasswordName->fetch();
    if(($checkPasswordName == true) && $result){
      $id = $result['id'];
      $password_match = $result['password_match'];
      if($password_match == 1){
        // set cookie
        try{
          // generate hashToken
          $getPassHash = $pdo->prepare("SELECT crypt(:password, gen_salt('md5'));");
          $getPassHash->execute(['password' => $password]);
          $hashed_loginToken = $id . $getPassHash->fetchColumn();

          // logged in cookie, that lasts 30 days, keeps user logged in
          setcookie("authLoginQuestMakerToken", $hashed_loginToken, time()+(86400*30), "/", NULL);
          $updateAuthToken = $pdo->prepare("UPDATE prj.zleceniodawca SET token_autoryzacji=:authLoginCookie WHERE id=:id");
          $updateAuthToken->execute(['authLoginCookie' => $hashed_loginToken, 'id'=>$id]);
          header("Location: http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php");
          die();
        }catch(PDOException $e){
          throw new Exception("Coś poszło nie tak. Nie udało się zalogować.");
        }
      }else{
        throw new Exception("Nie udało się zalogować.");
      }
    }else{
      throw new Exception("Błędna nazwa lub hasło.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  };
  return "";
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
            <h1>Logowanie</h1>
            <form method="GET" name="userSelectionForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
              <select id="userType" name="userType" onchange="document.userSelectionForm.submit()">
                <option value="hunter" <?php if($_GET['userType'] == 'hunter') echo "selected=\"selected\""; ?> >Łowca</option>
                <option value="questMaker" <?php if($_GET['userType'] == 'questMaker') echo "selected=\"selected\""; ?> >Zleceniodawca</option>
              </select>
            </form>

            <form method="POST" action="<?php if($_GET['userType']=='questMaker') echo htmlspecialchars($_SERVER["PHP_SELF"])."?userType=questMaker"; else echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
              <p>Nazwa/Imię</p>
              <input type="text" name="username"><br>
              <p>Hasło</p>
              <input type="password" name="password"><br>
              <input style="width: 350px; margin-left: -50px;" id="loginButton" class="button" type="submit" name="loginUser" value="Zalooguje się">
            </form>
            <?php echo "<p class=\"error-text\">".$loginError."</p>" ?>
			      <?php echo "<p class=\"error-text\">".$sqlError."</p>" ?>
            <a id="registrationButton" class="button" href="registration.php">Przejdz do rejestracji</a>
        </div>
	</div>
</section>
</section>

</body>
</html>

