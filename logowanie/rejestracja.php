<?php 
session_start(); 

require "../database.php";
$username = $surname = $password = $password_repeat = $id_race = $id_class = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$username = validateInput($_POST["username"]);
    $surname = validateInput($_POST["surname"]);
	$password = $_POST["password"];
    $password_repeat = $_POST["password_repeat"];
    $id_race = $_POST["id_race"];
    $id_class = $_POST["id_class"];

	$_SESSION["username"] = $username;
	$_SESSION["password"] = $password;

	$sqlError = $error = "";
// check cookie if user is logged in
	try{
		$loggedInCheck = $pdo->prepare("SELECT (token_autoryzacji = :authLoginCookie) FROM prj.łowca WHERE imię=:username");
		$loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginToken'], 'username' => $username]);
		if($loggedInCheck->fetchColumn() == 1){
			// user already logged in
			header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
			die();
		}// else could not redirect, possibly wrong cookie loginToken, or user is just not logged in.
	}catch(PDOException $e){
		$sqlError = $sqlError . "<br>" . $e->getMessage();
	}
// check if password equals repeated password
	if($password == $password_repeat){
		$error = $error . " Podane hasła nie są takie same.";
	}
// validate unique username
	try{
		$checkUniqueName = $pdo->prepare("SELECT (imię=:username) FROM prj.łowca WHERE imię=:username;");
		$checkUniqueName->execute(['username'=>$username]);
		$res = $checkUniqueName->fetchColumn();
		if(($res == true) && ($res == 1)){
			$error = $error . " Podane imię, już istnieje.";
		}
	}catch(PDOEXception $e){
		$sqlError = $sqlError . "<br>" . $e->getMessage();
	}
// validate id_race
	try{
		$validateRaceQuery = $pdo->prepare("SELECT (id=:id_rasa) FROM prj.rasa WHERE id=:id_rasa;");
		$validateRaceQuery->execute(['id_rasa'=>$id_race]);
		$res = $validateRaceQuery->fetchColumn();
		if(($res == true) && ($res == 1)){
			$error = $error . " Podana rasa, nie istnieje.";
		}
	}catch(PDOEXception $e){
		$sqlError = $sqlError . "<br>" . $e->getMessage();
	}
// validate id_class
	try{
		$validateClassQuery = $pdo->prepare("SELECT (id=:id_class) FROM prj.klasa WHERE id=:id_class;");
		$validateClassQuery->execute(['id_class'=>$id_class]);
		$res = $validateClassQuery->fetchColumn();
		if(($res == true) && ($res == 1)){
			$error = $error . " Podana klasa, nie istnieje.";
		}
	}catch(PDOEXception $e){
		$sqlError = $sqlError . "<br>" . $e->getMessage();
	}

// create new hunter in database
	if(empty($error) && empty($sqlError)){
		try{
			$createHunterQuery = $pdo->prepare("INSERT INTO prj.łowca (imię, hasło_hash, id_rasa, id_klasa) VALUES 
											(:username, crypt(:password, gen_salt('md5')), :id_rasa, :id_klasa);");
			$createHunterQuery->execute(['username'=>$username, 'password'=>$password, 'id_rasa'=>$id_race, 'id_klasa'=>$id_class]);
		}catch(PDOException $e){
			$sqlError = $sqlError . "<br>" . $e->getMessage();
		}
	}
}

function validateInput($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
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
	<link rel="Stylesheet" href="styles_logowanie.css">
	<title>Projekt</title>
</head>
<body class="grain-background">

<header>
	<img id="headerTitle" src="../assets/przewodnik_lowcy_header.png" alt="Przewodnik Łowcy">
</header>

<section id="mainWindowWrapper">
<section id="mainWindow">

	<div id="content">

        <div class="center-middle">
            <h1>Rejestracja</h1>
            <form method="post" action="./script/registerValidation.php" enctype="multipart/form-data">
                <p>Imię</p>
                <input type="text" name="username"><br>
                <p>Hasło</p>
				<input type="password" name="password"> <br>
				<p>Powtórz hasło</p>
				<input type="password" name="password_repeat"> <br>
				<p>Rasa</p>
				<select id="race-select" name="id_race">
					<?php 
					echo "<option value=\"aa\">aa</option>";
                    // get all values from prj.rasa 
					try{
						$racesQuery = $pdo->query("SELECT id, nazwa FROM prj.rasa;");
						if($racesQuery){
							foreach ($racesQuery->fetchAll() as $race){
								echo "<option value='" . $race["id"] . "'>" . $race["nazwa"] . "</option>";
							}
						}else{
							echo "No Data??!";
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
						foreach ($classesQuery as $class){
							echo "<option value='" . $class["id"] . "'>" . $class["nazwa"] . "</option>";
						}
					}catch(PDOException $e){
						$sqlError = $sqlError . "<br>" . $e->getMessage();
					}
                    ?>
				</select><br>
                <input id="registrationButton" class="button"  type="submit" value="zarejestruj">
			</form>
			<?php echo "<p class=\"error-text\">".$error."</p>" ?>
			<?php echo "<p class=\"error-text\">".$sqlError."</p>" ?>
			<a id="loginButton" class="button" href="logowanie.php">Przejdz do logowania</a>
        </div>

	</div>
</section>
</section>

<!-- <footer>

</footer> -->

</body>
</html>

