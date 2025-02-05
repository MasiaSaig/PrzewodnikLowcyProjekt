<?php 
/**
 * @file questMaker.php
 * @brief Starting view for Quest Maker, mostly HTML file, that redirects to sections where user can see data, after being logged in as quest maker.
 *
 * This file includes necessary files which user may want to access, most of the stuff can be accessed from here.
 */

session_start(); 
require "database.php";

$loggedIn = false;
try{
  $loggedIn = loggedInQuestMaker();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}

if($loggedIn == false){
  header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
  die();
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
	<a href="http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php">
		<img id="headerTitle" src="assets/przewodnik_lowcy_header.png" alt="Przewodnik Łowcy"> 
	</a>
</header>

<section id="mainWindowWrapper">
<section id="mainWindow">
	<nav id="navbar">
		<a class="logo" href="questMaker.php">Ł</a>
		<a href="questMaker.php?page=bestiary">Bestiariusz</a>
    <a href="questMaker.php?page=classes">Klasy</a>
    <a href="questMaker.php?page=races">Rasy</a>
   
		<div id="navbar-right">
      <a href="http://pascal.fis.agh.edu.pl/~2mueller/profileQuestMaker.php">Profil Zleceniodawcy</a>
      <a href="http://pascal.fis.agh.edu.pl/~2mueller/loging/logoutQuestMaker.php">Wyloguj</a>
		</div>
	</nav>

	<div id="content">
		<?php 
		if(isset($_GET["page"])){ 
			if((include "sites/" . $_GET["page"].".php") == FALSE)
				include "home.php";
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

