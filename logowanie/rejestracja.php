<!DOCTYPE html>
<html lang="pl-PL">
<head>
	<!-- get rid of favicon.ico request -->
	<link rel="icon" href="data:image/png;base64,iVBORw0KGgo="> 
	<meta http-equiv="Content-Language" content="pl">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="author" content="Maciej Muller">

	<link rel="Stylesheet" href="styles.css">
	<link rel="Stylesheet" href="styles_tablet.css">
	<link rel="Stylesheet" href="styles_mobile.css">
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
        include "database.php";
        if(!$_POST[username]){
        ?>

        <div class="center-middle">
            <p>Rejestracja</p>
            <form method="post" action="./script/registerValidation.php" enctype="multipart/form-data">
                <input type="text" name="username">
                <input type="password" name="password">
                <input type="submit" value="login">
            </form>
            <a href="#">Logowanie</a>
        </div>

        <?php
        } else{
            header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
            die();
        }
        ?>

	</div>
</section>
</section>

<!-- <footer>

</footer> -->

</body>
</html>

