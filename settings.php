<!DOCTYPE html>
<html lang="de">
<head>
    <title>Einstellungen</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();

require_once "functions.php";

$username = $_SESSION['username'] ?? null;
$password = $_SESSION['password'] ?? null;
$conn = connectToDatabase();

$slackBotToken = getValueFromDatabase($conn, "users", "slack_bot_token", ["username" => $username]);
$dictionary = getValueFromDatabase($conn, "users", "dictionary", ["username" => $username]);
$notificationForDaysInAdvance = getValueFromDatabase($conn, "users", "notification_for_days_in_advance", ["username" => $username]);



initiateCheck($conn, $username, $password);




$btnResponse = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slackBotToken = $_POST['slackBotToken'] ?? $slackBotToken;
    $dictionary = $_POST['dictionary'] ?? $dictionary;
    $notificationForDaysInAdvance = $_POST['notificationDays'] ?? $notificationForDaysInAdvance;


    if(updateDatabase($conn, "users", ["slack_bot_token", "dictionary", "notification_for_days_in_advance"], ["username = ?"], [$slackBotToken, $dictionary, $notificationForDaysInAdvance, $username])){
        $btnResponse = getMessageText("settingsSavedSuccessfully");
        initiateCheck($conn, $username, $password);
    } else {
        $btnResponse = getMessageText("settingsNotSaved");
    }
    $conn->close();
}



if (isset($_POST['action'])) {
    $conn = connectToDatabase();
    switch ($_POST['action']) {
        case 'logout':
            logOut();
            break;
        case 'deleteAccount':
            if (deleteFromDatabase($conn, "users", ["username = ?"], [$username])) {
                $btnResponse = getMessageText("accountDeletedSuccessfully");
                sleep(2);
                $conn->close();
                logOut();
            } else {
                $btnResponse = getMessageText("accountNotDeleted");
            }
            break;
        case 'testNotification':

            $testNotificationAusfall = sendslackMessage($username, "ausfall", "Testbenachrichtigung für den Channel ausfall", " ", "");
            $testNotificationRaumänderung = sendslackMessage($username, "raumänderung", "Testbenachrichtigung für den Channel raumänderung", " ", "");
            $testNotificationVertretung = sendslackMessage($username, "vertretung", "Testbenachrichtigung für den Channel vertretung", " ", "");
            $testNotificationSonstiges = sendslackMessage($username, "sonstiges", "Testbenachrichtigung für den Channel sonstiges", "Wenn du das hier liest, hast du alles richtig gemacht! (Solange auf der Website \"Alle 4 Testbenachrichtigungen erfolgreich gesendet\" stand.) Ab sofort erhältst du Benachrichtigungen, wenn es Änderungen in deinem Stundenplan gibt. Alle 10 Min. wird überprüft, ob Änderungen vorhanden sind. Nun kannst du die Slack App überall dort installieren und dich anmelden, wo du benachrichtigt werden möchtest (Handy, iPad, usw.). In Einzelfällen (z.B. wenn bei Untis durch eine spezielle Veranstalltung aufeinmal 2 \"Fächer\" für eine Stunde eingetragen sind und die Stunde somit vertikal in der Mitte geteilt ist) kann es sein, dass nicht alles richtig verarbeitet werden kann. Bei Fehlern oder Fragen mir gerne schreiben.", "");

            if ($testNotificationAusfall && $testNotificationRaumänderung && $testNotificationVertretung && $testNotificationSonstiges) {
                if (updateDatabase($conn, "users", ["setup_complete"], ["username = ?"], [true, $username])){
                    $btnResponse = getMessageText("testNotificationAllSent");
                }
            } elseif(!$testNotificationSonstiges && !$testNotificationVertretung && !$testNotificationRaumänderung && !$testNotificationAusfall) {
                $btnResponse = getMessageText("testNotificationAllNotSent");
            } elseif (!$testNotificationAusfall) {
                $btnResponse = getMessageText("testNotificationAusfallNotSent");
            } elseif (!$testNotificationRaumänderung) {
                $btnResponse = getMessageText("testNotificationRaumänderungNotSent");
            } elseif (!$testNotificationVertretung) {
                $btnResponse = getMessageText("testNotificationVertretungNotSent");
            } elseif (!$testNotificationSonstiges) {
                $btnResponse = getMessageText("testNotificationSonstigesNotSent");
            }
            break;
    }
    $conn->close();
}
?>

<div class="parent parent-settings">


    <form action="settings.php" method="post">
        <button id="toggle-theme" class="dark-mode-switch-btn">
            <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
        </button>
        <h2>Einstellungen</h2>
        <br>

        <label for="slackBotToken">Slack Bot Token:</label>
        <div class="label-container">
            <input type="text" id="slackBotToken" name="slackBotToken" value="<?php echo $slackBotToken; ?>" placeholder="xoxb-...">
            <span class="info-icon" onclick="openExternInfoSite('BotToken')" onKeyDown="openExternInfoSite('BotToken')">?</span>
        </div>
        <br><br>

        <label for="dictionary">Dictionary (optional):</label>
        <div class="label-container">
            <input type="text" id="dictionary" name="dictionary" value="<?php echo $dictionary; ?>" placeholder="ph1E=Physik; ch2E=Chemie; la1E=Latein">
            <span class="info-icon" onclick="openExternInfoSite('dictionary')" onKeyDown="openExternInfoSite('dictionary')">?</span>
        </div>
        <br><br>

        <label for="notificationDays">Wie viele Tage im Voraus sollen auf Änderungen geprüft werden?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="0" max="30" value="<?php echo $notificationForDaysInAdvance; ?>" oninput="this.nextElementSibling.value = this.value">
            <output><?php echo $notificationForDaysInAdvance; ?></output>
            <span class="info-icon" onclick="openExternInfoSite('TageInVoraus')" onKeyDown="openExternInfoSite('TageInVoraus')">?</span>
        </div>
        <br><br>
        <button class="btn-save-settings btn" type="submit">Einstellungen speichern</button>
        <br><br>
        <?php echo $btnResponse; ?>
    </form>
    <form action="settings.php" method="post">
        <button class="btn-testbenachrichtigung btn" type="submit" name="action" value="testNotification">Testbenachrichtigungen senden</button><br>
        <nobr>
            <button class="btn-log-out btn" type="submit" name="action" value="logout">Abmelden</button>
            <button class="btn-delete-acc btn" type="submit" name="action" value="deleteAccount">Konto löschen</button>
        </nobr>
    </form>
</div>
</body>
<script src="script.js"></script>
</html>
