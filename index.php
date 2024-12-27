<?php 
session_start(); 

require "../database.php";
$loggedIn = false;
// check cookie if user is logged in
try{
	$loggedInCheck = $pdo->prepare("SELECT (token_autoryzacji = :authLoginCookie) FROM prj.łowca WHERE imię=:username");
	$loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginToken'], 'username' => $username]);
	if($loggedInCheck->fetchColumn() == 1){
		// user already logged in
		$loggedIn = true;
	}// else could not redirect, possibly wrong cookie loginToken, or user is just not logged in.
}catch(PDOException $e){
	$sqlError = $sqlError . "<br>" . $e->getMessage();
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
		<a href="index.php?page=gildie">Gildie</a>
		<a href="index.php?page=bestiariusz">Bestiariusz</a>
		<a href="index.php?page=klasy">Klasy</a>
		
		<div id="navbar-right">
			<?php 
			if($loggedIn){
				echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/logowanie/wyloguj.php\">Wyloguj</a>";
			} else {
				echo "<a href=\"logowanie/logowanie.php\">Logowanie</a>";
				echo "<a href=\"logowanie/rejestracja.php\">Rejestracja</a>";
			} ?>
		</div>
	</nav>

	<div id="content">
		<!-- <div> <?php echo phpversion(); ?> asad</div>
		<div> <?php include "database.php"; ?> asda</div> -->

		<?php 
		if(isset($_GET["page"])){ 
			if((include $_GET["page"] . ".php") == FALSE)
				include "home.php";
		} else{
			include "home.php"; 
		}
		?>

		<?php //if($_SESSION[]) ?>
		<aside>

		</aside> 
	</div>
</section>
</section>

<!-- <footer>

</footer> -->


<script>
	// $(document).ready(function(){
	// 	$(".navbar-link").on("click", function(e){
	// 		e.preventDefault();
	// 		loadContent($(this).data("page"));
	// 	});
		
	// 	// by default
	// 	loadContent("home")

	// 	function loadContent(page){
	// 		$.ajax({
	// 			url: "sites/"+page+".php",
	// 			method: "GET",
	// 			success: function(data) { $("#content").html(data); },
	// 			error: function() { $("#content").html("<h2 class=\"error\">Wystąpił Błąd podczas ładowania strony.</h2>"); }
	// 		});
	// 	}
	// });

</script>
</body>
</html>

