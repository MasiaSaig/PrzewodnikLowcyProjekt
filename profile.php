<?php 
/**
 * @file profile.php
 * @brief This file a profile view of logged in hunter.
 *
 * Here hunter can see his data, quests, group, and guild, mostly everything he needs. Here he can also add new group members.
 */

session_start(); 
require "database.php";

$loggedIn = false;
try{
  $loggedIn = loggedIn();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}

if(!$loggedIn){
  header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
  die();
}

$id_hunter = $username = $id_race = $id_group = $money = $id_class = "";
// get data from łowca 
try{
  $userData = $pdo->prepare("SELECT id, imię, id_rasa, id_grupa, pieniądze, id_klasa, grupa_status FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
  $userData->execute(['authLoginCookie' => $_COOKIE['authLoginToken']]);
  if($userData == false){
    $error = $error . " Nie można otrzymać, danych łowcy.";
  }
  $data = $userData->fetch();
  $id_hunter = $data['id'];
  $username = $data['imię'];
  $id_race = $data['id_rasa'];
  $id_group = $data['id_grupa'];
  $money = $data['pieniądze'];
  $id_class = $data['id_klasa'];
  $group_status = $data['grupa_status'];
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

// get race data
try{
  $race = $race_desc = "";
  $raceData = $pdo->prepare("SELECT nazwa FROM prj.rasa WHERE id=?");
  $raceData->bindParam(1, $id_race, PDO::PARAM_INT);
  $raceData->execute();
  if($raceData == false)
    $error = $error . " Nie można otrzymać danych rasy.";
  $data = $raceData->fetch();
  $race = $data['nazwa'];
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}
// get class data
try{
  $class = $class_desc = "";
  $classData = $pdo->prepare("SELECT nazwa FROM prj.klasa WHERE id=?");
$classData->bindParam(1, $id_class, PDO::PARAM_INT);
  $classData->execute();
  if($classData == false)
    $error = $error . " Nie można otrzymać danych klasy.";
  $data = $classData->fetch();
  $class = $data['nazwa'];
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

$sqlError = $error = $removeGroupMemberError = "";
$addGroupMemberGroupError = $leaveGroupError = $acceptGroupError = $leaveGuildError = $acceptGuildInviteError = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($_POST['removeGroupMember'])){
  // removing group member
    $removeGroupMemberError = removeGroupMember($_POST['removeGroupMember']);
  }else if(isset($_POST['leaveGuild'])){
    $leaveGuildError = leaveGuild($id_hunter);
  }else if(isset($_POST["addGroupMember"])){
  // adding new group member
    $addGroupMemberGroupError = addNewGroupMember($id_hunter, $id_group, $_POST['NewGroupMemberName']);
  }else if (isset($_POST['createGroup'])){
  // creating new group
    $createGroupError = createHunterGroup($id_hunter, $id_group);
  }else if(isset($_POST['leaveGroup'])){
  // leaving group
    $leaveGroupError = leaveHunterGroup($id_hunter, $id_group);
  }else if(isset($_POST['acceptGroupInvitation'])){
    $acceptGroupError = acceptGroupInvitation($id_hunter);
  }else if(isset($_POST['acceptGuildInvitation'])){
    $acceptGuildInviteError = acceptGuildInvitation($id_hunter);
  }
}

/**
 * Function, that accepts invitation from guild.
 * 
 * Function checks if hunter's status is correct and then changes hunters guild status in prj.członkowie_gildii to member of a guild.
 *
 * @param[in] $id_hunter ID of a currently logged in hunter, that wants to accept invitation to guild.
 * @return Returns empty string on success, else return error message.
 */
function acceptGuildInvitation($id_hunter){
  global $pdo;
  try{
    try{
      $checkHunterGuildStatus = $pdo->prepare("SELECT (status='oczekuje') FROM prj.członkowie_gildii WHERE id_łowca=?");
      $checkHunterGuildStatus->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $checkHunterGuildStatus->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić statusu łowcy.");
    } 
    if($checkHunterGuildStatus->fetchColumn() == 0){
      throw new Exception("Łowca nie został zaproszony do żadnej gildii.");
    }

    try{
      $acceptInvitation = $pdo->prepare("UPDATE prj.członkowie_gildii SET status='członek' WHERE id_łowca=?");
      $acceptInvitation->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $acceptInvitation->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można zaakceptować zaproszenia do gildii.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function, that allows a hunter to leave guild.
 * 
 * Function checks if hunter is a creator of a guild. If he is, leaving guild would remove all other members and delete guild.
 *
 * @param[in] $id_hunter ID of a currently logged in hunter, that wants to leave group.
 *
 * @return Returns empty string on success, else return error message.
 */
function leaveGuild($id_hunter){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $getGuildStatus = $pdo->prepare("SELECT g.id, cg.status FROM prj.gildia g JOIN prj.członkowie_gildii cg ON g.id=cg.id_gildia WHERE cg.id_łowca=?");
      $getGuildStatus->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $getGuildStatus->execute();
      $guildData = $getGuildStatus->fetch();
    }catch(PDOException $e){
      throw new Exception("Nie można otrzymać danych gildii, łowcy.");
    }
    if($guildData == ''){
      throw new Exception("Nie można otrzymać danych gildii, łowcy.");
    }else{
      if($guildData['status'] == "założyciel"){
        try{
          $removeAllGuildMembers = $pdo->prepare("DELETE FROM prj.członkowie_gildii WHERE id_gildia=?");
          $removeAllGuildMembers->bindParam(1, $guildData['id'], PDO::PARAM_INT);
          $removeAllGuildMembers->execute();
        }catch(PDOException $e){
          throw new Exception("Nie można usunąć członków gildii.");
        }
        
        try{
          $deleteGuild = $pdo->prepare("DELETE FROM prj.gildia WHERE id=?");
          $deleteGuild->bindParam(1, $guildData['id'], PDO::PARAM_INT);
          $deleteGuild->execute();
        }catch(PDOException $e){
          throw new Exception("Nie można usunąć gildii.");
        }
      }else{
        try{
          $leaveGuild = $pdo->prepare("DELETE FROM prj.członkowie_gildii WHERE id_łowca=?");
          $leaveGuild->bindParam(1, $id_hunter, PDO::PARAM_INT);
          $leaveGuild->execute();
        }catch(PDOException $e){
          throw new Exception("Nie można opuścić gildii.");
        }
        
        // check if any member left
        /*$checkMembersLeft = $pdo->prepare("SELECT COUNT(id_łowca) FROM prj.członkowie_gildii WHERE id_gildia=?");
        $checkMembersLeft->bindParam(1, $guildData['id'], PDO::PARAM_INT);
        $checkMembersLeft->execute();
        if($checkMembersLeft->fetchColumn() < 0)
          $deleteGuild = $pdo->prepare("DELETE FROM prj.gildia WHERE id=?");
          $deleteGuild->bindParam(1, $guildData['id'], PDO::PARAM_INT);
          $deleteGuild->execute();
          if($deleteGuild == false)
            $leaveGuildError = $leaveGuildError . "Nie można usunąć gildii. <br>";
        }*/
      }
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function that removes hunter with given id, from group.
 *
 * @param[in] $id_hunter_to_remove ID of a hunter, to be removed from a group.
 * @return Return empty string on success, else return error message.
 */
function removeGroupMember($id_hunter_to_remove){
  global $pdo;
  try{
    try{
      $removeGroupMemberQuery = $pdo->prepare("UPDATE prj.łowca SET id_grupa=NULL, grupa_status=NULL WHERE id=?");
      $removeGroupMemberQuery->bindParam(1, $id_hunter_to_remove, PDO::PARAM_INT);
      $removeGroupMemberQuery->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można usunąć członka z grupy.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function that, accepts hunter's group invitation.
 *
 * This function first, checks if hunter's group status is 'oczekuje', meaning the hunter was invited and is waiting for acceptation.
 * If hunter's group status, meets predicted status, his status changes to 'członek'. Also group quest is assigned to this hunter.
 *
 * @param[in] $id_hunter Currently logged in hunter's ID, to accept his invitation.
 * @return Return empty string on success, else return error message.
 */
function acceptGroupInvitation($id_hunter){
  global $pdo;
  try{
    try{
      $checkGroupStatus = $pdo->prepare("SELECT (grupa_status='oczekuje') AS match_group_status, id_grupa FROM prj.łowca WHERE id=?");
      $checkGroupStatus->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $checkGroupStatus->execute();
      $hunterGroupData = $checkGroupStatus->fetch();
    }catch(PDOException $e){
      throw new Exception("Nie można spawdzić statusu grupy, łowcy.");
    }
    if($hunterGroupData['match_group_status'] == 0){
      throw new Exception("Łowca nie oczekuje na zaakceptowanie do grupy.");
    }
    
    try{
      $acceptGroup = $pdo->prepare("UPDATE prj.łowca SET grupa_status='członek' WHERE id=?");
      $acceptGroup->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $acceptGroup->execute();
    }catch(PDOException $e){  
      throw new Exception("Akceptowanie dołączenia do grupy, nie powiodło się.");
    } 

    try{
      $getGroupQuest = $pdo->prepare("SELECT id_zlecenie, COUNT(id_zlecenie) as quest_exist FROM prj.przypisane_zlecenie pz JOIN prj.zlecenie z ON pz.id_zlecenie=z.id JOIN prj.łowca l ON l.id=pz.id_łowca GROUP BY id_zlecenie, z.status, l.id_grupa HAVING z.status='w trakcie' AND l.id_grupa=?");
      $getGroupQuest->bindParam(1, $hunterGroupData['id_grupa'], PDO::PARAM_INT);
      $getGroupQuest->execute();
      $groupQuestData = $getGroupQuest->fetch();
    }catch(PDOException $e){  
      throw new Exception("Nie udało się otrzymać zadania grupy.");
    }
    
    if($groupQuestData['quest_exist'] > 0){
      try{
        $assignNewMemberToGroupQuest = $pdo->prepare("INSERT INTO prj.przypisane_zlecenie (id_zlecenie, id_łowca) VALUES (?, ?)");
        $assignNewMemberToGroupQuest->bindParam(1, $groupQuestData['id_zlecenie'], PDO::PARAM_INT);
        $assignNewMemberToGroupQuest->bindParam(2, $id_hunter, PDO::PARAM_INT);
        $assignNewMemberToGroupQuest->execute();
      }catch(PDOException $e){  
        throw new Exception("Nie udało się otrzymać zadania grupy.");
      }
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function, that adds new hunter to group, by providing hunter's name.
 *
 * Function first checks if hunter with provided id exists. 
 * Then checks if invited hunter has an active quest, if he does, he cannot be invited to group.
 * Ofcourse, hunter thats already in a group also cannot be added. Next if group has already 3 members, you cannot add more.
 *
 * @param[in] $id_hunter Currently logged in hunter's ID.
 * @param[in] $id_group  Currently logged in hunter's groud ID.
 * @param[in] $id_group  New group member's name.
 *
 * @return Return empty string on success, else return error message.
 */
function addNewGroupMember($id_hunter, $id_group, $new_hunter_name){
  global $pdo;
  try{
    $pdo->beginTransaction();
    // try{
    //   $res = checkForActiveQuest($id_hunter);
    // }catch(PDOException $e){
    //   throw new Exception("Nie można sprawdzić czy łowca jest w trakcie wykonywania zadania.");
    // } 
    // if($res > 0){
    //   throw new Exception("Nie można zapraszać innych łowców do grupy, gdy jest się w trakcie wykonywania zlecenia.");
    // }
      
    try{
      $countGroupMembers = $pdo->prepare("SELECT COUNT(id) FROM prj.łowca WHERE id_grupa=?");
      $countGroupMembers->bindParam(1, $id_group, PDO::PARAM_INT);
      $countGroupMembers->execute();
      $groupMembersCount = $countGroupMembers->fetchColumn();
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić, czy łowca należy do grupy.");
    }
    if($groupMembersCount >= 3){
      throw new Exception("Grupa jest przepełniona, nie może do niej należeć, więcej niż 3 łowców.");
    }

    try{
      $checkMemberName = $pdo->prepare("SELECT id, (imię=:name) AS match_name FROM prj.łowca WHERE imię=:name");
      $checkMemberName->execute(['name'=>$new_hunter_name]);
      $hunterData = $checkMemberName->fetch();
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić, czy łowca z podanym imieniem, istnieje.");
    }
    if($hunterData['match_name'] == 0){
      throw new Exception("Łowca o podanej nazwie nie istnieje.");
    }
    
    try{
      $res_member = checkForActiveQuest($hunterData['id']);
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić, czy łowca którego chcesz dodać, jest w trakcie wykonywania zadania.");
    }
    if($res_member > 0){
      throw new Exception("Łowca którego chcesz dodać, jest w trakcie wykonywania zadania.");
    }
    
    try{
      $chechGroupStatus = $pdo->prepare("SELECT grupa_status FROM prj.łowca WHERE id=?");
      $chechGroupStatus->bindParam(1, $hunterData['id'], PDO::PARAM_INT);
      $chechGroupStatus->execute();
      $dataStatus = $chechGroupStatus->fetchColumn();
    }catch(PDOException $e){
      throw new Exception("Nie można sprawdzić statusu grupy, łowcy.");
    }
    if($dataStatus != ''){
      throw new Exception("Łowca należy już do grupy, lub został już zaproszony.");
    }
    try{
      $changeGroup = $pdo->prepare("UPDATE prj.łowca SET grupa_status='oczekuje', id_grupa=? WHERE id=?");
      $changeGroup->bindParam(1, $id_group, PDO::PARAM_INT);
      $changeGroup->bindParam(2, $hunterData['id'], PDO::PARAM_INT);
      $changeGroup->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można zmienić grupy i statusu, nowego członka.");
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function, that creates new group for hunters.
 *
 * @param[in]  $id_hunter Hunter's ID.
 * @param[out] $id_group  If group creation was a success, this variable will hold new group's ID.
 *
 * @return Return empty string on success, else return error message.
 */
function createHunterGroup($id_hunter, &$id_group){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $createGroup = $pdo->prepare("INSERT INTO prj.grupa (id) VALUES (?)");
      $createGroup->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $createGroup->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się stworzyć grupy.");
    }
    
    try{
      $setGroupStatus = $pdo->prepare("UPDATE prj.łowca SET grupa_status='członek', id_grupa=? WHERE id=?");
      $setGroupStatus->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $setGroupStatus->bindParam(2, $id_hunter, PDO::PARAM_INT);
      $setGroupStatus->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się dodać łowcy do grupy.");
    }
    $id_group = $id_hunter;
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Function that removes hunter from group.
 *
 * It sets hunters group ID and status to NULLs and if there are some other members left in group,
 * this function creates new group with other member's ID and assigns to it, all the other members except the one, leaving 
 * this group ofcourse.
 * Also only if there are other members of group, remove hunter from this group's quest.
 * 
 * @param[in]  $id_hunter Hunter's ID.
 * @param[out] $id_group  If leaving group was a success, change this local variable to 0.
 *
 * @return If there was any error, return its error message, else return empty string.
 */
function leaveHunterGroup($id_hunter, &$id_group){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      // remove hunter from group, by setting his group fields to null
      $leaveGroup = $pdo->prepare("UPDATE prj.łowca SET id_grupa=NULL, grupa_status=NULL WHERE id=?");
      $leaveGroup->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $leaveGroup->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało sie opuścić grupy.");
    }
    
    if($id_group == $id_hunter){
      // if group id equals to current hunters id, change group id to one of its member's id
      $getNewGroupID = $pdo->prepare("SELECT id FROM prj.łowca WHERE id_grupa=? LIMIT 1");
      $getNewGroupID->bindParam(1, $id_group, PDO::PARAM_INT);
      $getNewGroupID->execute();
      $new_id_group = $getNewGroupID->fetchColumn();
      if(($getNewGroupID == true) && $new_id_group){
        try{
          // if there are other members in group left, remove all quests that are assigned to group, from this hunter
          $removeGroupQuests = $pdo->prepare("DELETE FROM prj.przypisane_zlecenie pz WHERE pz.id_łowca=? AND (SELECT (status='w trakcie') FROM prj.zlecenie z WHERE id=pz.id_zlecenie AND status='w trakcie')");
          $removeGroupQuests->bindParam(1, $id_hunter, PDO::PARAM_INT);
          $removeGroupQuests->execute();
        }catch(PDOException $e){
          throw new Exception("Nie udało sie wypisać łowcy z zadania grupy." . $e->getMessage());
        }
        
        try{
          // create new group, with new ID
          $createGroup = $pdo->prepare("INSERT INTO prj.grupa (id) VALUES (?)");
          $createGroup->bindParam(1, $new_id_group, PDO::PARAM_INT);
          $createGroup->execute();
        }catch(PDOException $e){
          throw new Exception("Nie udało sie stworzyć grupy o nowym ID.");
        }
        
        try{
          // update group ID, for other group members 
          $changeMembersGroupID = $pdo->prepare("UPDATE prj.łowca SET id_grupa=? WHERE id_grupa=?");
          $changeMembersGroupID->bindParam(1, $new_id_group, PDO::PARAM_INT);
          $changeMembersGroupID->bindParam(2, $id_group, PDO::PARAM_INT);
          $changeMembersGroupID->execute();
        }catch(PDOException $e){
          throw new Exception("Nie udało sie zmienić grupy pozostałym członkom.");
        }
      }
    }
    
    try{
      // delete group
      $deleteGroup = $pdo->prepare("DELETE FROM prj.grupa WHERE id=?");
      $deleteGroup->bindParam(1, $id_hunter, PDO::PARAM_INT);
      $deleteGroup->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się usunąć grupy.");
    }
    $id_group = 0;
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

?>

<!DOCTYPE html>
<html lang="pl-PL">
<head>
	<!-- get rid of favicon.ico request -->
	<link rel="icon" href="data:image/png;base64,iVBORw0KGgo="> 
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
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
		<a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=guilds">Gildie</a>
		<a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=bestiary">Bestiariusz</a>
		<a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=classes">Klasy</a>
    <a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=races">Rasy</a>
    <a href="http://pascal.fis.agh.edu.pl/~2mueller/index.php?page=quests">Zlecenia</a>
    
		<div id="navbar-right">
      <a href="http://pascal.fis.agh.edu.pl/~2mueller/loging/logout.php">Wyloguj</a>
		</div>
	</nav>

	<div id="content">
    <h1><?php echo $username; ?></h1>
    <p>Rasa: <?php echo $race; ?></p>
    <p>Pieniądze: <?php echo $money; ?></p>
    <p>Klasa: <?php echo $class; ?></p>
    
	<?php 
	// get guild data
	try{
		$guildData = $pdo->prepare("SELECT id_gildia, (SELECT nazwa FROM prj.gildia WHERE id=cg.id_gildia LIMIT 1) AS nazwa, status FROM prj.członkowie_gildii cg WHERE id_łowca=?");
		$guildData->bindParam(1, $id_hunter, PDO::PARAM_INT);
		$guildData->execute();
		if($guildData == false){
			$error = $error . " Nie można otrzymać danych gildii.";
		}
		$data = $guildData->fetch();
    if(($guildData == true) && ($data != '')){
?>
  <div>
  <p style="display: inline-block;">Gildia: <?php echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_guild=".$data['id_gildia']."\">".$data['nazwa']."</a> ".$data['status']; ?> </p>
  <?php if($data['status'] == 'oczekuje'){ ?>
    <form method="POST" style="display: inline-block" name="acceptGuildInvite" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <input class="accept-button" type="submit" name="acceptGuildInvitation" value="Dołącz do gildii">
    </form>

  <?php 
    if(!empty($acceptGuildInviteError)) echo "<p class=\"error-text\">".$acceptGuildInviteError."</p>";
  } 
  ?>  
    <form style="display: inline-block; float: right;" method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/profile.php">
      <input id="leaveGuildButton" class="decline-button" type="submit" name="leaveGuild" value="Opuść gildię">
    </form>
  </div>
<?php
      echo "<p style=\"color: red;\">".$leaveGuildError."</p>";
    }
	}catch(PDOException $e){
		$sqlError = $sqlError . " | " . $e->getMessage();
	}
	?>

    
    <?php
	// get members of group
    if($id_group && ($id_group > 0)){
      echo "<h2>Członkowie grupy</h2>";
      $needConfirmationToJoinGroup = false;
      try{
       $groupMembersData = $pdo->prepare("SELECT l.id, l.imię, (SELECT nazwa FROM prj.klasa WHERE id=l.id_klasa LIMIT 1) AS nazwa_klasy, grupa_status FROM prj.łowca l WHERE id_grupa=?");
	     $groupMembersData->bindParam(1, $id_group, PDO::PARAM_INT);
       $groupMembersData->execute();
       if($groupMembersData == false)
         $error = $error . " Nie można otrzymać danych członków grupy.";
       else{
         echo "<table><tr><th>Imię</th><th>Klasa</th><th>Status</th></tr>";
         foreach($groupMembersData as $data){
           if(($data['id'] == $id_hunter) && ($data['grupa_status'] == "oczekuje"))
             $needConfirmationToJoinGroup = true;
           echo "<tr><td>" . $data['imię'] . "</td><td>" . $data['nazwa_klasy'] . "</td>";
           echo "<td>" . $data['grupa_status'];
           if(($data['grupa_status'] == 'oczekuje') && ($group_status == 'członek')){
       ?>
       <form style="display: inline-block; float: right;" method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/profile.php">
         <input type="hidden" name="removeGroupMember" value="<?php echo $data['id']; ?>">
         <input type="submit" name="removeGroupMemberSubmit" value="Wyrzuć członka grupy">
       </form>
       <?php
           }
           echo "</td></td>";
         }
         echo "</table>";
       }
     }catch(PDOException $e){
       $sqlError = $sqlError . " | " . $e->getMessage();
     }
     echo "<p class=\"error-text\">".$leaveGuildError."</p>";
     
     if($needConfirmationToJoinGroup == false){
      ?>
      <h2>Dodawanie członka do grupy</h2>
      <div>
      <form class="centered-form" method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/profile.php">
        <p>Podaj nazwę łowcy, którego chcesz dodać do grupy</p>
        <input type="text" name="NewGroupMemberName"><br>
        <input class="form-button" id="addHunterToGroupButton" type="submit" name="addGroupMember" value="Dodaj łowce do grupy">
      </form>
      <?php 
        if($addGroupMemberGroupError != "") echo "<p style=\"color: red;\">".$addGroupMemberGroupError."</p>"; 
      }else{
      ?>
      <br>
      <div>
      <form style="display: inline-block; width: 50%; margin-left: 25%; text-align: center;" method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/profile.php">
        <input class="accept-button" id="acceptGroupInvitationButton" type="submit" name="acceptGroupInvitation" value="Dołącz do grupy">
      </form>
      <?php    
        if(!empty($acceptGroupError))
          echo "<p style=\"color: red;\">".$acceptGroupError."</p>";
      }
      ?>
      <form class="form-to-right" style="float: right;" method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/profile.php">
        <input id="leaveGroupButton" class="decline-button" type="submit" name="leaveGroup" value="Opuść grupę">
      </form>
      </div>
      <?php
      if($leaveGroupError != "") echo "<p style=\"color: red;\">".$leaveGroupError."</p>"; 
    }else{  
    ?>
    <div class="inline-box">
      <p>Nie należysz do żadnej grupy&nbsp</p>
      <form method="POST" action="http://pascal.fis.agh.edu.pl/~2mueller/profile.php">
        <input id="createGroupButton" class="accept-button" type="submit" name="createGroup" value="Stwórz nową grupę">
      </form>
    </div>
    <?php 
    if($createGroupError != "") echo "<p style=\"color: red;\"".$createGroupError."</p>";
    } 
    echo "</div>";
    ?>
    
    
    <br>
    <h2>Zlecenia łowcy</h2>
    <form method="GET" name="hunterQuestsForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <select id="typ-zlecenia" name="typ_zlecenia" onchange="document.hunterQuestsForm.submit()">
        <option value="w trakcie" <?php if($_GET['typ_zlecenia'] == 'w trakcie') echo "selected=\"selected\""; ?> >w trakcie wykonywania</option>
        <option value="zakończone" <?php if($_GET['typ_zlecenia'] == 'zakończone') echo "selected=\"selected\""; ?> >zakończone</option>
      </select>
    </form>
	<div>
  <table>
    <tr>
      <th>id</th>
      <th>Imię</th>
      <th>Nagroda</th>
      <th>Opis</th>
      <th>Status</th>
    </tr>
	<?php
	// get hunter's quests
    try{
      if(isset($_GET['typ_zlecenia'])){
        $questsData = $pdo->prepare("SELECT z.id, z.status, z.nagroda, z.opis, zl.imię 
	  									FROM prj.zlecenie z 
										JOIN prj.zleceniodawca zl ON z.id_zleceniodawca=zl.id 
										JOIN prj.przypisane_zlecenie pz ON z.id=pz.id_zlecenie 
										WHERE pz.id_łowca=? AND z.status=?");
        $questsData->bindParam(1, $id_hunter, PDO::PARAM_INT);
        $questsData->bindParam(2, $_GET['typ_zlecenia']);
      }else{
        $questsData = $pdo->prepare("SELECT z.id, z.status, z.nagroda, z.opis, zl.imię 
	  									FROM prj.zlecenie z 
										JOIN prj.zleceniodawca zl ON z.id_zleceniodawca=zl.id 
										JOIN prj.przypisane_zlecenie pz ON z.id=pz.id_zlecenie 
										WHERE pz.id_łowca=? AND z.status='w trakcie'");
        $questsData->bindParam(1, $id_hunter, PDO::PARAM_INT);
      }
      $questsData->execute();
      if($questsData == false)
        $error = $error . " Nie można otrzymać id zleceń.";
		  foreach($questsData as $data){
  			echo "<tr>";
        echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_quest=".$data['id']."\">".$data['id']."</td>";
        echo "<td>".$data['imię']."</td><td>".$data['nagroda']."</td><td>".$data['opis']."</td><td>".$data['status']."</td></tr>";
      }
     }catch(PDOException $e){
       $sqlError = $sqlError . " | " . $e->getMessage();
     }
    ?>
  </table>
	</div>
 
   <p clas="error"><?php echo $error; ?></p>
   <p clas="error"><?php echo $sqlError; ?></p>
	</div>
</section>
</section>

</body>
</html>

