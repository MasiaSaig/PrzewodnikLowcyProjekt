<?php
/**
 * @file index.php
 * @brief Starting view for hunter, mostly HTML file, that redirects to sections where user can see data, edit them and login or register.
 *
 * This file includes necessary files which user may want to access, most of the stuff can be accessed from here.
 */
 
session_start(); 
require "database.php";

$loggedIn = false;
try{
  $loggedIn = loggedIn();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
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

	<link rel="Stylesheet" href="styles.css">
	<link rel="Stylesheet" href="styles_tablet.css">
	<link rel="Stylesheet" href="styles_mobile.css">
	<title>Projekt</title>
</head>
<body class="grain-background">

<header>
	<a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php">
		<img id="headerTitle" src="assets/przewodnik_lowcy_header.png" alt="Przewodnik Łowcy"> 
	</a>
</header>

<section id="mainWindowWrapper">
<section id="mainWindow">
	<nav id="navbar">
		<a class="logo" href="index.php?page=home">Ł</a>
		<a href="index.php?page=guilds">Gildie</a>
		<a href="index.php?page=bestiary">Bestiariusz</a>
		<a href="index.php?page=classes">Klasy</a>
    <a href="index.php?page=races">Rasy</a>
	  <?php 
			if($loggedIn){
        echo "<a href=\"index.php?page=quests\">Zlecenia</a>";
      }      
    ?>
   
		<div id="navbar-right">
			<?php 
			if($loggedIn){
        echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/profile.php\">Profil Łowcy</a>";
				echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/loging/logout.php\">Wyloguj</a>";
			} else {
				echo "<a href=\"loging/login.php\">Logowanie</a>";
				echo "<a href=\"loging/registration.php\">Rejestracja</a>";
			} ?>
		</div>
	</nav>

	<div id="content">

		<?php 
		if(isset($_GET["page"])){ 
			if((include "sites/" . $_GET["page"].".php") == FALSE)
				include "home.php";
		} else if(isset($_GET["id_guild"])){
      include "sites/guild.php";
    } else if(isset($_GET["id_beast"])){
      include "sites/beast.php";
    }else if(isset($_GET['id_quest'])){
      include "sites/quest.php";
    } else{
			include "home.php"; 
		}
		?>

    <p><?php echo $error; echo $sqlError; ?></p>

	</div>
</section>
</section>

</body>
</html>

