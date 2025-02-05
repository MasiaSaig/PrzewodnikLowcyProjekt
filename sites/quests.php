<?php
/**
 * @file quests.php
 * @brief File where user can see data of a all available quests, that a hunter can assign to itself. Only hunters can see this section.
 */

if($loggedIn == false){
  header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
  die();
}

$id_group = $id_hunter = "";  
try{
  $hunterIdQuery = $pdo->prepare("SELECT id, id_grupa FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
  $hunterIdQuery->execute(['authLoginCookie'=>$_COOKIE['authLoginToken']]);
  if($hunterIdQuery == false)
    $questError = $questError . "Nie można otrzymać id łowcy.<br>";
  $hunterData = $hunterIdQuery->fetch();
  $id_hunter = $hunterData['id'];
  $id_group = $hunterData['id_grupa'];
}catch(PDOException $e){
  $sqlError = $sqlError . "<br>" . $e->getMessage();
};

$questError = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($_POST['assignQuest'])){  
    $questError = assignQuest($id_hunter, $id_group, $_POST['assignQuest']);
  }
}

/**
 * Function that allows hunter to assign quest to him or his group.
 * 
 * Function before assigning checks if there are some hunters waiting to join his group, if there are he cannot assign quest.
 * 
 * @param[in] $id_hunter ID of a hunter that tries to assign quest.
 * @param[in] $id_group ID of hunters group.
 * @param[in] $id_quest ID of a quest, a hunter tries to assign.
 * 
 * @return Returns empty string on success, else return error message.
 */
function assignQuest($id_hunter, $id_group, $id_quest){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $res = checkForActiveQuest($id_hunter);
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić aktualnych zadań łowcy.");
    }
    
    if($res > 0)
      throw new Exception("Łowca ma już przypisane jedno zlecenie.");
    
    if(!empty($id_group)){
      try{
        $checkHuntersInGroup = $pdo->prepare("SELECT grupa_status FROM prj.łowca WHERE id_grupa=?");
        $checkHuntersInGroup->bindParam(1, $id_group, PDO::PARAM_INT);
        $checkHuntersInGroup->execute();
        $waitingForGroupMembers = false;
        foreach($checkHuntersInGroup as $hunter){
          if($hunter['grupa_status'] == 'oczekuje'){
            $waitingForGroupMembers = true;
          }
        }
      }catch(PDOException $e){
        throw new Exception("Nie można sprawdzić statusów łowców w grupie.");
      }
      if($waitingForGroupMembers == true){
        throw new Exception("Należy poczekać, aż wszyscy członkowie grupy zaakceptują zaproszenie.");
      }

      try{
        $getHuntersFromOfGroup = $pdo->prepare("SELECT id FROM prj.łowca WHERE id_grupa=?");
        $getHuntersFromOfGroup->bindParam(1, $id_group, PDO::PARAM_INT);
        $getHuntersFromOfGroup->execute();
        foreach($getHuntersFromOfGroup as $hunter){
          $assignQuest = $pdo->prepare("INSERT INTO prj.przypisane_zlecenie (id_zlecenie, id_łowca) VALUES(?, ?)");
          $assignQuest->bindParam(1, $id_quest, PDO::PARAM_INT);
          $assignQuest->bindParam(2, $hunter['id'], PDO::PARAM_INT);
          $assignQuest->execute();
        }
      }catch(PDOException $e){
        throw new Exception("Nie można przypisać zlecenia łowcy.");
      }
      try{
        $changeQuestStatus = $pdo->prepare("UPDATE prj.zlecenie SET status='w trakcie' WHERE id=?");
        $changeQuestStatus->bindParam(1, $id_quest, PDO::PARAM_INT);
        $changeQuestStatus->execute();
      }catch(PDOException $e){
        throw new Exception("Nie można zmienić statusu zlecenia.");
      }
    }else{
      try{
        // hunter doesnt belong to any group  
        $assignQuest = $pdo->prepare("INSERT INTO prj.przypisane_zlecenie (id_zlecenie, id_łowca) VALUES(?, ?)");
        $assignQuest->bindParam(1, $id_quest, PDO::PARAM_INT);
        $assignQuest->bindParam(2, $id_hunter, PDO::PARAM_INT);
        $assignQuest->execute();
      }catch(PDOException $e){
        throw new Exception("Nie można przypisać zlecenia łowcy.");
      }
      
      try{
        $changeQuestStatus = $pdo->prepare("UPDATE prj.zlecenie SET status='w trakcie' WHERE id=?");
        $changeQuestStatus->bindParam(1, $id_quest, PDO::PARAM_INT);
        $changeQuestStatus->execute();
      }catch(PDOException $e){
        throw new Exception("Nie można zmienić statusu zlecenia.");
      }
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

?>
<h1>Zlecenia do Przypisania</h1>
<table>
<tr>
  <th>ID</th>
  <th>Zleceniodawca</th>
  <th>Nagroda</th>
  <th>Zwierzęta do upolowania</th>
</tr>

<?php
try{
  $questsQuery = $pdo->query("SELECT id, imię, nagroda FROM aktualne_zadania");
  $questsQuery->execute();
  if($questsQuery == false)
    $error = $error . "<br> Nie można otrzymać danych aktualnych zadań.";
  foreach($questsQuery as $quest){
    echo "<tr><td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_quest=".$quest['id']."\">".$quest['id']."</a></td>";
    echo "<td>".$quest['imię']."</td><td>".$quest['nagroda']."</td>";
    $beastsQuestQuery = $pdo->prepare("SELECT zw.nazwa, COUNT(zw.id) as ilosc, zw.id FROM prj.zlecenie z JOIN prj.zlecenie_zwierzę zz ON z.id=zz.id_zlecenie JOIN prj.zwierzę zw ON zz.id_zwierzę=zw.id group by zw.nazwa, z.id, zw.id having z.id=?");
    $beastsQuestQuery->bindParam(1, $quest['id'], PDO::PARAM_INT);
    $beastsQuestQuery->execute();
    if($beastsQuestQuery == false)
      $error = $error . "<br> Nie można otrzymać danych zwierząt do zadania.";
    else{
      echo "<td>";
      foreach($beastsQuestQuery as $beast){
        echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_beast=".$beast['id']."\">".$beast['nazwa']."(".$beast['ilosc'].")</a>, ";
      }
      echo "</td>";
    }
?>
  <td>
  <form method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=quests">
    <input type="hidden" name="assignQuest" value="<?php echo $quest['id']; ?>">
    <input class="form-button-fill" id="assignQuestButton" class="button" type="submit" value="Przypisz zadanie">
  </form>
  </td>
<?php
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}
?>

</table>

<div class="centered-box">
  <p class="error-text"> <?php echo $questError; ?> </p>
  <p class="error-text"> <?php echo $sqlError;   ?> </p>
</div>
