<?php session_start(); ?>

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
	<img id="headerTitle" src="assets/przewodnik_lowcy_header.png" alt="Przewodnik Łowcy">
</header>

<section id="mainWindowWrapper">
<section id="mainWindow">

	<div id="content">
        <?php 
        $username = $password = $error = "";
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $name = validateInput($_POST["username"]);
            $password = $_POST["password"];
            
            $_SESSION["username"] = $username;
            $_SESSION["password"] = $password;

            require "../database.php";
            
            // check username and password in database
            try{
                $stmt = $pdo->prepare("SELECT id, hasło_hash FROM prj.łowca WHERE imię = :username AND hasło_hash = crypt(:password, gen_salt('md5'))");
                $stmt->execute(['username' => $username, 'password' => $password]);
                $id = $stmt->fetchColumn(0);
                $hashed_password = $stmt->fetchColumn(1);
                
                // logged in cookie, that lasts 30 days, keeps user logged in
                setcookie(loggedInCookie, $id . $hashed_password, time()+(86400*30), "/");
                //header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
                //die();
            }catch(PDOException $e){
                echo "<p>" . $e->getMessage() . "</p>";
            }
        }
          
        function validateInput($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }
        
        ?>

        <div class="center-middle">
            <p>Logowanie</p>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <input type="text" name="username">
                <br>
                <input type="password" name="password"> 
                <br>
                <input type="submit" value="login">
            </form>
            <?php echo "<p class=\"error-text\">".$error."</p>" ?>
            <a href="rejestracja.php">Rejestracja</a>
        </div>

	</div>
</section>
</section>

<!-- <footer>

</footer> -->

</body>
</html>

