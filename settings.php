<!DOCTYPE html>
<html lang="de">
<head>
    <title>Einstellungen</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();
require_once "functions.php";

$username = $_SESSION['username'] ?? null;
$conn = connectToDatabase();

$slackBotToken = getColumnFromDatabase($conn, [$username], "slack_bot_token", "SELECT slack_bot_token FROM users WHERE username = ?");
$notificationForDaysInAdvance = getColumnFromDatabase($conn, [$username], "notification_for_days_in_advance", "SELECT notification_for_days_in_advance FROM users WHERE username = ?");


$btnResponse = '';
$newSlackBotToken = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!empty($_POST['slackBotToken'])) {
        $slackBotToken = $_POST['slackBotToken'];
        $newSlackBotToken = true;
    }

    $notificationForDaysInAdvance = $_POST['notificationDays'] ?? $notificationForDaysInAdvance;


    if($newSlackBotToken){
        if(writeDataToDatabase($conn, [$slackBotToken, $notificationForDaysInAdvance, $username], "UPDATE users SET slack_bot_token = ?, notification_for_days_in_advance = ? WHERE username = ?")){
            $btnResponse = getMessageText("settingsSavedSuccessfully");
        } else {
            $btnResponse = getMessageText("settingsNotSaved");
        }
        $newSlackBotToken = false;
    } elseif ($notificationForDaysInAdvance){
        if(writeDataToDatabase($conn, [$notificationForDaysInAdvance, $username], "UPDATE users SET notification_for_days_in_advance = ? WHERE username = ?")){
            $btnResponse = getMessageText("settingsSavedSuccessfully");
        } else {
            $btnResponse = getMessageText("settingsNotSaved");
        }
    }
    $conn->close();
}

initiateCheck();

if (isset($_POST['action'])) {
    $conn = connectToDatabase();
    switch ($_POST['action']) {
        case 'logout':
            logOut();
            break;
        case 'deleteAccount':
            if (writeDataToDatabase($conn, [$username], "DELETE FROM users WHERE username = ?")) {
                $btnResponse = getMessageText("accountDeletedSuccessfully");
                sleep(2);
                $conn->close();
                logOut();
            } else {
                $btnResponse = getMessageText("accountNotDeleted");
            }
            break;
        case 'testNotification':
            if (sendslackMessage("Testbenachrichtigung", "Wenn du das hier liest, hast du alles richtig gemacht! Ab sofort erhältst du Benachrichtigungen, wenn es Änderungen in deinem Stundenplan gibt. Alle 10 Min. wird überprüft, ob Änderungen vorhanden sind.", "")) {
                if (writeDataToDatabase($conn, [$username], "UPDATE users SET setup_complete = true WHERE username = ?")) {
                    $btnResponse = getMessageText("testNotificationSent");
                }
            } else {
                $btnResponse = getMessageText("testNotificationNotSent");
            }
            break;
    }
    $conn->close();
}
?>

<div class="parent">
    <form action="settings.php" method="post">
        <h2>Einstellungen</h2>
        <br>
        <label for="slackBotToken">Slack Bot Token:</label>
        <div class="label-container">
            <input type="text" id="slackBotToken" name="slackBotToken" placeholder="<?php echo $slackBotToken; ?>">
            <span class="info-icon" onclick="openExternInfoSite('BotToken')">?</span>
        </div>
        <br><br>
        <label for="notificationDays">Wie viele Tage im Voraus sollen auf Änderungen geprüft werden?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="1" max="30" value="<?php echo $notificationForDaysInAdvance; ?>" oninput="this.nextElementSibling.value = this.value">
            <output><?php echo $notificationForDaysInAdvance; ?></output>
            <span class="info-icon" onclick="openExternInfoSite('TageInVorraus')">?</span>
        </div>
        <br><br>
        <button class="btn-save-settings" type="submit">Einstellungen speichern</button>
        <br><br>
        <?php echo $btnResponse; ?>
    </form>
    <form action="settings.php" method="post">
        <button class="btn-testbenachrichtigung" type="submit" name="action" value="testNotification">Testbenachrichtigung senden</button><br>
        <nobr>
            <button class="btn-log-out" type="submit" name="action" value="logout">Abmelden</button>
            <button class="btn-delete-acc" type="submit" name="action" value="deleteAccount">Konto löschen</button>
        </nobr>
    </form>
</div>
</body>
<script src="script.js"></script>
</html>
