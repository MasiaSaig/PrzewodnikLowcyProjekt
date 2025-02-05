<?php
/**
 * @file guilds.php
 * @brief File where user can see data of a all guilds. Here hunters can also create their own guilds.
 */

$errorGuild = "";

if($loggedIn == true){
  try{
    $userIdQuery = $pdo->prepare("SELECT id FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
    $userIdQuery->execute(['authLoginCookie'=>$_COOKIE['authLoginToken']]);
    if($userIdQuery == false)
      $error = $error . "<br> Nie można otrzymać id łowcy.";
    $id_hunter = $userIdQuery->fetchColumn();
    $userGuildQuery = $pdo->prepare("SELECT id_gildia, status FROM prj.członkowie_gildii WHERE id_łowca=:id_hunter");
    $userGuildQuery->execute(['id_hunter'=>$id_hunter]);
    if($userGuildQuery == false)
      $error = $error . "<br> Nie można otrzymać danych gildii łowcy.";
    $data = $userGuildQuery->fetch();
    $id_guild = $data['id_gildia'];
    $guild_status = $data['status'];
  }catch(PDOException $e){
    $sqlError = $sqlError . " | " . $e->getMessage();
  }

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // create guild
    $guildName = validateInput($_POST['guildName']);
    
    $errorGuild = createGuild($id_hunter, $guildName);
    
  }

  if($guild_status != 'założyciel' && $id_guild == ''){
?>
<h2>Tworzenie Nowej Gildii</h2>
<form class="centered-form" method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=guilds">
  <p>Nazwa Gildii:</p>
  <input type="text" name="guildName"><br>
  <input id="guildCreateButton" class="form-button" type="submit" value="Stwórz Gildię">
</form>
<br>
<?php 
  }
} 

if($errorGuild != ""){
  echo "<p>".$errorGuild."</p>";
}
?>

<h1>Lista Gildii</h1>
<table>
<tr>
  <th>ID</th>
  <th>Nazwa</th>
  <th>Pula nagród</th>
</tr>
<?php
try{
  $guildsQuery = $pdo->query("SELECT g.id, g.nazwa, (SELECT SUM((SELECT SUM(pieniądze) FROM prj.łowca WHERE id=cg.id_łowca AND cg.status<>'oczekuje')) FROM prj.członkowie_gildii cg WHERE id_gildia=g.id) AS prize_pool FROM prj.gildia g ORDER BY prize_pool");
  if($guildsQuery == false)
    $error = $error . "<br> Nie można otrzymać danych gildii.";
  foreach($guildsQuery as $guild){
    echo "<td>".$guild['id']."</td>";
    echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_guild=".$guild['id']."\">".$guild['nazwa']."</a></td>";
    echo "<td>".$guild['prize_pool']."</td>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}


/**
 * Function that creates new guild and assigns hunter as guilds creator.
 * 
 * This function checks if hunter is already in guild. If he is, he cannot create new guild.
 * If also, checks if given name is unique for guild, next creates guild, assigns new id, 
 * finally assign hunter as creator of a guild, with special privilages.
 * 
 * @param[in] $id_hunter ID of a hunter, which wants to create a guild.
 * @param[in] $guildName Name of a new guild.
 * @return Returns empty string on success, else return error message.
 */
function createGuild($id_hunter, $guildName){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $hunterAlreadyInGuild = $pdo->prepare("SELECT (id_łowca=?) AS already_in_guild FROM prj.członkowie_gildii WHERE id_łowca=?");
      $hunterAlreadyInGuild->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $hunterAlreadyInGuild->bindParam(2, $id_hunter, PDO::PARAM_INT);
      $hunterAlreadyInGuild->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić czy łowca należy do gildii.");
    }
    if($hunterAlreadyInGuild->fetchColumn() == 1){
      throw new Exception("Łowca należy już do gildii.");
    }
    
    try{
      $checkGuildName = $pdo->prepare("SELECT (nazwa = :guild_name) FROM prj.gildia");
      $checkGuildName->execute(['guild_name'=>$guildName]);
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić nazwy gildii.");
    }
    
    if($checkGuildName->fetchColumn() == 1)
      throw new Exception("Istnieje już gildia o podanej nazwie.");
    
    try{
      $newGuildQuery = $pdo->prepare("INSERT INTO prj.gildia (id, nazwa) VALUES (DEFAULT, :guild_name)");
      $newGuildQuery->execute(['guild_name'=>$guildName]);
    }catch(PDOException $e){
      throw new Exception("Nie można stworzyć gildii.");
    }
    
    try{
      $getGuildID = $pdo->prepare("SELECT id FROM prj.gildia WHERE nazwa=:guild_name");
      $getGuildID->execute(['guild_name'=>$guildName]);
      $new_id_guild = $getGuildID->fetchColumn();
    }catch(PDOException $e){
      throw new Exception("Nie można otrzymać id nowej gildii.");
    }
      
    try{  
      $id_guild = $new_id_guild;
      $newGuildQuery = $pdo->prepare("INSERT INTO prj.członkowie_gildii (id_łowca, id_gildia, status) VALUES (:id_hunter, :id_guild, 'założyciel')");
      $newGuildQuery->execute(['id_hunter'=>$id_hunter, 'id_guild'=>$id_guild]);
    }catch(PDOException $e){
      throw new Exception("Nie można przypisać łowcy jako założyciela gildii.");
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}
?>
</table>
