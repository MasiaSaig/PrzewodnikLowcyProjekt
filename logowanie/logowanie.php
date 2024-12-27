<?php 
session_start(); 

$username = $password = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$username = validateInput($_POST["username"]);
	$password = $_POST["password"];

	// $_SESSION["username"] = $username;
	// $_SESSION["password"] = $password;

	require "../database.php";
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

	$hashed_password = $id = "";
// check username and password in database
	try{
		$stmt = $pdo->prepare("SELECT id, (hasło_hash=crypt(:password, hasło_hash)) AS password_match FROM prj.łowca WHERE imię=:username;");
		$stmt->execute(['password' => $password, 'username' => $username]);

		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if($result){
			foreach($result as $row){
				$id = $row['id'];
				$password_match = $row['password_match'];
			}
		}
	}catch(PDOException $e){
		$sqlError = $sqlError . "<br>" . $e->getMessage();
	};

	if($password_match == 0){
		$error = "Błędna nazwa lub hasło.";
	}else{
		// set cookie
		try{
			// generate hashToken
			$getPassHash = $pdo->prepare("SELECT crypt(:password, gen_salt('md5'));");
			$getPassHash->execute(['password' => $password]);
			$hashed_loginToken = $id . $getPassHash->fetchColumn();

			// logged in cookie, that lasts 30 days, keeps user logged in
			setcookie("authLoginToken", $hashed_loginToken, time()+(86400*30), "/");
			$updateAuthToken = $pdo->prepare("UPDATE prj.łowca SET token_autoryzacji=:authLoginCookie WHERE id=:id");
			$updateAuthToken->execute(['authLoginCookie' => $hashed_loginToken, 'id'=>$id]);
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
            <h1>Logowanie</h1>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
				<p>Nazwa/Imię</p>
				<input type="text" name="username"><br>
				<p>Hasło</p>
                <input type="password" name="password"><br>
                <input id="loginButton" class="button" type="submit" value="login">
            </form>
            <?php echo "<p class=\"error-text\">".$error."</p>" ?>
			<?php echo "<p class=\"error-text\">".$sqlError."</p>" ?>
            <a id="registrationButton" class="button" href="rejestracja.php">Przejdz do rejestracji</a>
        </div>

	</div>
</section>
</section>

<!-- <footer>

</footer> -->

</body>
</html>

