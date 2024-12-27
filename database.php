<?php
$dbHost = "pascal.fis.agh.edu.pl";
$dbPort = "5432";
$dbName = "u2mueller";
$dbUser = "u2mueller";
$dbPassword = "2mueller";
$dbDsn = "pgsql:host=$dbHost;dbname=$dbName;user=$dbUser;port=$dbPort;password=$dbPassword;";

try{
	if(!session_id()) session_start(); 
	$pdo = new PDO($dbDsn);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//echo "Connected";
}catch(PDOException $e){
	echo "<p>Connection failed: " . $e->getMessage() . "</p>";
}

function loggedIn(){
	// check cookie if user is logged in
	global $pdo;
	$loggedInCheck = $pdo->prepare("SELECT (token_autoryzacji = :authLoginCookie) FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
	$loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginToken']]);
	if($loggedInCheck->fetchColumn() == 1){
		// user already logged in
		return true;
	}// else could not redirect, possibly wrong cookie loginToken, or user is just not logged in.
	return false;
}

?>
