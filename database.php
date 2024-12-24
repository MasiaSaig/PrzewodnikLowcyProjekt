<?php
$dbHost = "pascal.fis.agh.edu.pl";
$dbPort = "5432";
$dbName = "u2mueller";
$dbUser = "u2mueller";
$dbPassword = "2mueller";
$dbDsn = "pgsql:host=$dbHost;dbname=$dbName;user=$dbUser;port=$dbPort;password=$dbPassword;";


try{
	if(!session_id()) session_start(); 
	$pdo = new PDO($dsn);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//echo "Connected";
}catch(PDOException $e){
	echo "<p>Connection failed: " . $e->getMessage() . "</p>";
}

?>
