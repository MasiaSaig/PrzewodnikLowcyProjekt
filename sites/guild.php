<?php
/**
 * @file guild.php
 * @brief File where user can see data of a certain guild. Its members and collected gold. If hunter is creator of that guild, here he can also add new members.
 */

if(!isset($_GET['id_guild']) || !is_numeric($_GET['id_guild'])){
  $guildErrorID = true;
}

try{
  $checkGuild = $pdo->prepare("SELECT (id=?) FROM prj.gildia WHERE id=?");
  $checkGuild->bindParam(1, $_GET['id_guild'], PDO::PARAM_INT);
  $checkGuild->bindParam(2, $_GET['id_guild'], PDO::PARAM_INT);
  $checkGuild->execute();
  if($checkGuild->fetchColumn() == 0){
    $guildErrorID = true;
  }
}catch(PDOException $e){
  $guildErrorID = true;
}

if($guildErrorID){
  echo "<p style=\"color: red;\">Gildia z podanym ID nie istnieje</p>";
  return;
}

$errorGildii = "";
if($loggedIn == true){
  try{
    $userIdQuery = $pdo->prepare("SELECT id FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
    $userIdQuery->execute(['authLoginCookie'=>$_COOKIE['authLoginToken']]);
    if($userIdQuery == false)
      $errorGildii = $errorGildii . "<br> Nie można otrzymać id łowcy.";
    $id_hunter = $userIdQuery->fetchColumn();
    $userGuildQuery = $pdo->prepare("SELECT id_gildia, status FROM prj.członkowie_gildii WHERE id_łowca=:id_hunter");
    $userGuildQuery->execute(['id_hunter'=>$id_hunter]);
    if($userGuildQuery == false)
      $errorGildii = $errorGildii . "<br> Nie można otrzymać danych gildii łowcy.";
    $data = $userGuildQuery->fetch();
    $id_guild = $data['id_gildia'];
    $guild_status = $data['status'];
  }catch(PDOException $e){
    $sqlError = $sqlError . " | " . $e->getMessage();
  }

  if(($_SERVER["REQUEST_METHOD"] == "POST")){
    if(isset($_POST["deleteGuild"]) && isset($_GET["id_guild"])){
      // deleting guild
      $errorGildii = deleteGuild($id_hunter, $_GET["id_guild"]);
    }else if(isset($_POST['deletingMemberFromGuild'])){
      // removing member from guild
      $errorGuildMemberRemove = removeGuildMember($id_hunter, $_GET["id_guild"], $_POST['deleteMember']);
    }else if(isset($_POST['addMemberToGuild'])){
      // adding new member to guild
      $newMemberName = validateInput($_POST['newMemberName']);
      $errorGuildMemberAdd = addNewGuildMember($id_guild, $newMemberName);
    }
  }
}

/**
 * Function that checks if provided hunter, is creator of a given guild.
 * 
 * @param[in] $id_hunter ID of a hunter to check.
 * @param[in] $id_guild ID of a guild.
 * @return Returns true if hunter is a creator of a guild, else returns false.
 */
function isGuildCreator($id_hunter, $id_guild){
  global $pdo;
  try{
    $checkIfGuildCreator = $pdo->prepare("SELECT (status='założyciel') FROM prj.członkowie_gildii WHERE id_łowca=? AND id_gildia=?");
    $checkIfGuildCreator->bindParam(1, $id_hunter, PDO::PARAM_INT);
    $checkIfGuildCreator->bindParam(2, $id_guild, PDO::PARAM_INT);
    $checkIfGuildCreator->execute();
    if($checkIfGuildCreator->fetchColumn() == 1) { return true; }
  }catch(PDOException $e){
    return false;
  }
  return true;
}

/**
 * Function that adds new hunter to a guild.
 * 
 * It first checks if provided hunter's name exists, then it checks if this hunter isnt already a member of another guild. 
 * If his name is correct and he isnt a member of any other guild, an invitation is send to him, to join a guild.
 * 
 * @param[in] $id_guild ID of a guild.
 * @param[in] $newMemberName Name of a new hunter, to add, to a guild.
 * @return Returns empty string on success, else return error message.
 */
function addNewGuildMember($id_guild, $newMemberName){
  global $pdo;
  try{
    try{
      $checkNewMemberName = $pdo->prepare("SELECT (imię=:member_name) AS match_name, id FROM prj.łowca WHERE imię=:member_name");
      $checkNewMemberName->execute(['member_name'=>$newMemberName]);
      $memberData = $checkNewMemberName->fetch();
      if($memberData["match_name"] == 0) {throw new PDOException(); }
    }catch(PDOException $e){
      throw new Exception("Nie istnieje łowca o podanej nazwie.");
    }

    try{
      $checkGuildMembership = $pdo->prepare("SELECT (id_łowca=:id_member) FROM prj.członkowie_gildii");
      $checkGuildMembership->execute(['id_member'=>$memberData['id']]);
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić czy nowy członek należy do jakiejś gildii.");
    }
    if($checkGuildMembership->fetchColumn() == 1){
      throw new Exception("Łowca jest członkiem innej gildii, lub został już zaproszony.");
    }
    
    try{
      $addNewGuildMember = $pdo->prepare("INSERT INTO prj.członkowie_gildii (id_łowca, id_gildia, status) VALUES(:id_member, :id_guild, 'oczekuje')");
      $addNewGuildMember->execute(['id_member'=>$memberData['id'], 'id_guild'=>$id_guild]);
    }catch(PDOException $e){
      throw new Exception("Nie można dodać członka do gildii.");
    }
    
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function that removed member from a guild
 * 
 * It first checks if provided hunter is creator of a guild, only then it can delete member, if that member exists.
 * Also if that member is a creator of a Guild, he cannot be removed.
 * 
 * @param[in] $id_hunter ID of a hunter, should be creator of a given guild.
 * @param[in] $id_guild ID of a guild.
 * @param[in] $id_member ID of a guild member that is to be removed.
 * @return Returns empty string on success, else return error message.
 */
function removeGuildMember($id_hunter, $id_guild, $id_member){
  global $pdo;
  try{
    if(!isGuildCreator($id_hunter, $id_guild)) {throw new Exception("Łowca nie jest założycielem gildii."); }
    $memberData = "";
    try{
      $checkHunterToDelete = $pdo->prepare("SELECT status, (id_łowca=?) AS hunter_exists FROM prj.członkowie_gildii WHERE id_łowca=? AND id_gildia=?");
      $checkHunterToDelete->bindParam(1, $id_member, PDO::PARAM_INT);
      $checkHunterToDelete->bindParam(2, $id_member, PDO::PARAM_INT);
      $checkHunterToDelete->bindParam(3, $id_guild, PDO::PARAM_INT);
      $checkHunterToDelete->execute();
      $memberData = $checkHunterToDelete->fetch();
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić danych członka gildii.");
    }
    if($memberData["hunter_exists"] == 0) { 
      throw new Exception("Członek gildii nie istnieje."); 
    }
    if($memberData["status"] == "założyciel"){
      throw new Exception("Nie można usunąć założyciela z gildii.");
    }

    try{
      $deleteGuildMember = $pdo->prepare("DELETE FROM prj.członkowie_gildii WHERE id_łowca=?");
      $deleteGuildMember->bindParam(1, $id_member, PDO::PARAM_INT);
      $deleteGuildMember->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można usunąć członka z gildii.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function that deletes guild.
 * 
 * It first checks if provided hunter is creator of a guild, only then it can be deleted with all of its members.
 * 
 * @param[in] $id_hunter ID of a hunter, should be creator of a given guild.
 * @param[in] $id_guild ID of a guild to delete.
 * @return Returns empty string on success, else return error message.
 */
function deleteGuild($id_hunter, $id_guild){
  global $pdo;
  try{
    $pdo->beginTransaction();
    if(!isGuildCreator($id_hunter, $id_guild)) {throw new Exception("Łowca nie jest założycielem gildii."); }

    try{
      $deleteGuildMembers = $pdo->prepare("DELETE FROM prj.członkowie_gildii WHERE id_gildia=?");
      $deleteGuildMembers->bindParam(1, $id_guild, PDO::PARAM_INT);
      $deleteGuildMembers->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można usunąć członków gildii.");
    }

    try{
      $deleteGuild = $pdo->prepare("DELETE FROM prj.gidlia WHERE id=?");
      $deleteGuild->bindParam(1, $id_guild, PDO::PARAM_INT);
      $deleteGuild->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można usunąc gildii.");
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

try{
  $guildQuery = $pdo->prepare("SELECT id, nazwa FROM prj.gildia WHERE id=:id_guild");
  $guildQuery->bindParam(1, $_GET['id_guild'], PDO::PARAM_INT);
  $guildQuery->execute();
  if($guildQuery == false){
    $error = $error . " Błąd podczas pobierania danych gildii.";
  }
  $data = $guildQuery->fetch(PDO::FETCH_ASSOC);
  echo "<h1>".$data['nazwa']."</h1><br>";
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

echo "<p style=\"color: red;\">".$errorGildii."</p>";



  if($loggedIn == true && $guild_status == "założyciel"){
?>
<h2>Dodaj nowego członka</h2>
<form class="centered-form" method="POST" action="<?php echo "http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_guild=".$id_guild; ?>">
  <p>Nazwa łowcy, którego chcesz dodać:</p>
  <input type="text" name="newMemberName">
  <input id="addMemberToGuildButton" name="addMemberToGuild" class="form-button" type="submit" value="Dodaj do gildii">
</form>
<br>
<?php 
} 
if($errorGuildMemberAdd != "") echo "<p style=\"color: red;\">".$errorGuildMemberAdd."<p>";
?>

<h2>Lista Członków Gildii</h2>

<table>
<tr>
  <th>Nazwa</th>
  <th>Status</th>
  <th>Pula pieniężna</th>
</tr>
<?php
try{
  $guildMembersQuery = $pdo->prepare("SELECT imię, status, pieniądze, cg.id_łowca as id FROM prj.łowca l JOIN prj.członkowie_gildii cg ON l.id=cg.id_łowca WHERE cg.id_gildia=?");
  $guildMembersQuery->bindParam(1, $_GET['id_guild'], PDO::PARAM_INT);
  $guildMembersQuery->execute();
  if($guildMembersQuery == false){
    $error = $error . " Błąd podczas pobierania członków gildii.";
  }
  foreach($guildMembersQuery as $member){
    echo "<tr>";
    echo "<td>".$member['imię']."</td>";
    echo "<td>".$member['status']."</td>";
    echo "<td>".$member['pieniądze']."</td>";
    if(($guild_status == "założyciel") && ($member['status'] != "założyciel")){
      echo "<td><form method=\"POST\" action=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_guild=".$id_guild."\">";
      echo "<input type=\"hidden\" name=\"deleteMember\" value=\"".$member['id']."\">";
      echo "<input name=\"deletingMemberFromGuild\" class=\"form-button-fill\" type=\"submit\" value=\"Usuń członka gildii\">";
      echo "</form></td>";
    }
    echo "</tr>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}
if($errorGuildMemberRemove != "") 
  echo "<p class\"error-text\">".$errorGuildMemberRemove."</p>";
?>
</table>


<?php

  if(isset($_POST['finishedGuildQuests'])){
    $getFinishedQuests = $pdo->prepare("SELECT DISTINCT(z.id), zl.imię, z.nagroda FROM prj.członkowie_gildii cg JOIN prj.łowca l ON l.id=cg.id_łowca JOIN prj.przypisane_zlecenie pz ON pz.id_łowca=l.id JOIN prj.zlecenie z ON z.id=pz.id_zlecenie JOIN prj.zleceniodawca zl ON zl.id=z.id_zleceniodawca GROUP BY z.id, zl.imię, cg.id_gildia, cg.status HAVING cg.id_gildia=? AND z.status='zakończone' AND cg.status <> 'oczekuje'");
    $getFinishedQuests->bindParam(1, $_GET['id_guild'], PDO::PARAM_INT);
    $getFinishedQuests->execute();
    if($getFinishedQuests == true){
      echo "<br><h2>Lista Ukończonych Zleceń Przez Członków Gildii</h2><br>";
      echo "<table>";
      echo "<tr><th>ID</th><th>Imię zleceniodawcy</th><th>Nagroda</th></tr>";
      foreach($getFinishedQuests as $quest){
        echo "<tr>";
        echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_quest=".$quest['id']."\">".$quest['id']."</a></td>";
        echo "<td>".$quest['imię']."</td>";
        echo "<td>".$quest['nagroda']."</td>";
        echo "</tr>";
      }
      echo "</table>";
    }else{
      $error = $error . "Nie można otrzymać zleceń gildii.<br>";
    }
  }
else{
?>
<br>
<form method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_guild=<?php echo $_GET['id_guild']; ?>">
  <input id="finishedGuildQuestsButton" class="button" type="submit" name="finishedGuildQuests" value="Wyświetl ukończone zlecenia przez członków gildii">
</form>

<?php } if($guild_status == "założyciel"){ ?>

<form class="form-to-right" method="POST" action="<?php echo "http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_guild=".$id_guild; ?>">
  <input id="deleteGuildButton" name="deleteGuild" class="button" type="submit" value="Usuń gildię">
</form>

<?php } ?>
