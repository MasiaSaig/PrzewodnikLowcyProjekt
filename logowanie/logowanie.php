<?php 
session_start(); 

$username = $password = $error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$username = validateInput($_POST["username"]);
	$password = $_POST["password"];

	$_SESSION["username"] = $username;
	$_SESSION["password"] = $password;

	require "../database.php";
	$sqlError = "";
// check cookie if user is logged in
	try{
		$loggedInCheck = $pdo->prepare("SELECT (token_autoryzacji = :authLoginCookie) as match_login_token FROM prj.łowca");
		$loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginToken']]);
		if($loggedInCheck->fetchColumn() == 1){
			header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
			die();
		}
	}catch(PDOException $e){
		$sqlError = $e->getMessage();
	}

	$hashed_password = "";
// check username and password in database
	try{
		// echo "<br>" . $username . " " . $password . "<br>";
		$stmt = $pdo->prepare("SELECT id, (hasło_hash=crypt(:password, hasło_hash)) AS password_match FROM prj.łowca WHERE imię=:username;");	
		$stmt->execute(['password' => $password, 'username' => $username]);
		
		$getPassHash = $pdo->prepare("SELECT crypt(:password, gen_salt('md5'));");
		$getPassHash->execute(['password' => $password]);
		$hashed_password = $getPassHash->fetchColumn();

		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if($result){
			foreach($result as $row){
				$id = $row['id'];
				$password_match = $row['password_match'];
				print_r($row);
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
			// logged in cookie, that lasts 30 days, keeps user logged in
			setcookie("authLoginToken", $id . $hashed_password, time()+(86400*30), "/");

			$updateAuthToken = $pdo->prepare("UPDATE prj.łowca SET token_autoryzacji=:authLoginCookie WHERE id=:id");
			$updateAuthToken->execute(['authLoginCookie' => $_COOKIE['authLoginToken'], 'id'=>$id]);
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
            <p>Logowanie</p>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="text" name="username">
                <br>
                <input type="password" name="password"> 
                <br>
                <input type="submit" value="login">
            </form>
            <?php echo "<p class=\"error-text\">".$error."</p>" ?>
			<?php echo "<p class=\"error-text\">".$sqlError."</p>" ?>
            <a href="rejestracja.php">Rejestracja</a>
        </div>

	</div>
</section>
</section>

<!-- <footer>

</footer> -->

</body>
</html>

