<?php
/**
 * @file classes.php
 * @brief File where user can see data of all classes, that hunter can choose while registering. 
 */

$loggedInAsQuestMaker = 0;
try{
  $loggedInAsQuestMaker = loggedInQuestMaker();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}

$createClassError = "";
if($loggedInAsQuestMaker > 0){ 
  if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST["createClass"])){
      $class_name = validateInput($_POST['className']);
      $class_description = validateInput($_POST['classDescription']);
      $createClassError = createClass($class_name, $class_description);
    }
  }
}

/**
 * Function that creates new class.
 *
 * This function check if provided name for class is unique and if it is, it then creates new class with description.
 *
 * @param[in] $class_name Name of a new class.
 * @param[in] $description Description of a new class.
 * @return Returns empty string on success, else return error message.
 */
function createClass($class_name, $description){
  global $pdo;
  try{
    try{
      $checkUniqueName = $pdo->prepare("SELECT (nazwa=:name) FROM prj.klasa WHERE nazwa=:name");
      $checkUniqueName->execute(['name'=>$class_name]);
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić nazwy klasy.");
    }
    if($checkUniqueName->fetchColumn() == 1){
      throw new Exception("Klasa z podaną nazwą już istnieje.");
    }
  
    try{
      $createClassQuery = $pdo->prepare("INSERT INTO prj.klasa (id, nazwa, opis) VALUES (DEFAULT, :name, :description)");
      $createClassQuery->execute(['name'=>$class_name, 'description'=>$description]);
    }catch(PDOException $e){
      throw new Exception("Nie udało się stworzyć klasy.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

if($loggedInAsQuestMaker > 0){
?>
<h2>Stworz nową klasę</h2>
<form class="centered-form" method="POST" action="<?php htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
  <p>Nazwa klasy:</p>
  <input type="text" name="className">
  <p>Opis:</p>
  <textarea name="classDescription"></textarea>
  <input id="createClassButton" class="form-button" type="submit" name="createClass" value="Stworz klasę">
</form>

<?php 
if(!empty($createClassError)) echo "<p class=\"error-text\">".$createClassError."</p>";
echo "<br>";
} 
?>
<h1>Klasy Postaci</h1>
<table>
<tr>
  <th>ID</th>
  <th>Nazwa</th>
  <th>Opis</th>
</tr>

<?php

try{
  $classesQuery = $pdo->query("SELECT id, nazwa, opis FROM prj.klasa");
  $classesQuery->execute();
  if($classesQuery == false)
    $error = $error . "<br> Nie można otrzymać danych, klas.";
  foreach($classesQuery as $class){
    echo "<tr><td>".$class['id']."</td><td>".$class['nazwa']."</td><td>".$class['opis']."</td></tr>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}
?>

</table>
