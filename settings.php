<!DOCTYPE html>
<html lang="de">
<head>
    <title>Einstellungen</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="apple-touch-icon-precomposed" sizes="57x57" href="Favicon/apple-touch-icon-57x57.png" />
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="Favicon/apple-touch-icon-72x72.png" />
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="Favicon/apple-touch-icon-144x144.png" />
    <link rel="apple-touch-icon-precomposed" sizes="120x120" href="Favicon/apple-touch-icon-120x120.png" />
    <link rel="apple-touch-icon-precomposed" sizes="152x152" href="Favicon/apple-touch-icon-152x152.png" />
    <link rel="icon" type="image/png" href="Favicon/favicon-196x196.png" sizes="196x196" />
    <link rel="icon" type="image/png" href="Favicon/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="Favicon/favicon-16x16.png" sizes="16x16" />
    <meta name="application-name" content="Untis Notify"/>
    <meta name="msapplication-TileColor" content="#" />
    <meta name="msapplication-TileImage" content="Favicon/mstile-144x144.png" />

    <link rel="stylesheet" href="style.css">
</head>
<body>


<?php

use Exceptions\DatabaseException;
use Exceptions\UserException;

session_start();


require_once "functions.php";

$username = $_SESSION['username'] ?? null;
$password = $_SESSION['password'] ?? null;

if (!$username || !$password) {
    logOut();
}


$conn = null;
$emailAdress = "";
$dictionary = "";
$notificationForDaysInAdvance = 0;
$receiveNotificationsFor = [];
$checkboxAusfall = "";
$checkboxVertretung = "";
$checkboxRaumänderung = "";
$checkboxSonstiges = "";


try {
    $conn = connectToDatabase();
    $emailAdress = getValueFromDatabase($conn, "users", "email_adress", ["username" => $username], $username);
    $dictionary = getValueFromDatabase($conn, "users", "dictionary", ["username" => $username], $username);
    $notificationForDaysInAdvance = getValueFromDatabase($conn, "users", "notification_for_days_in_advance", ["username" => $username], $username);
    $receiveNotificationsFor = getValueFromDatabase($conn, "users", "receive_notifications_for", ["username" => $username], $username);
    $receiveNotificationsFor = explode(", ", $receiveNotificationsFor); // Convert comma-separated string to array
} catch (DatabaseException $e) {
    redirectToSettingsPage("dbError");
}

echo "<pre>";
echo "Conn: \n";
print_r($conn);
initiateCheck($conn, $username, $password);




$messageToUser = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $emailAdress = $_POST['emailAdress'] ?? $emailAdress;
    $dictionary = $_POST['dictionary'] ?? $dictionary;
    $notificationForDaysInAdvance = $_POST['notificationDays'] ?? $notificationForDaysInAdvance;
    $receiveNotificationsFor = $_POST['notifications'] ?? [];

    $emailAdress = trim($emailAdress);
    $dictionary = trim($dictionary);


    try {
        if ($receiveNotificationsFor == []) {
            throw new UserException("No notification type selected", 1);
        }

        if (!empty($dictionary) && !str_contains($dictionary, '=')) {
            throw new UserException("Dictionary format incorrect", 2);
        }

        $receiveNotificationsForString = implode(', ', $receiveNotificationsFor); // Convert array to comma-separated string
        deleteFromDatabase($conn, "timetables", ["user = ?"], [$username], $username); // Delete timetable data with the old dictionary
        updateDatabase($conn, "users", ["email_adress", "dictionary", "notification_for_days_in_advance", "receive_notifications_for"], ["username = ?"], [$emailAdress, $dictionary, $notificationForDaysInAdvance, $receiveNotificationsForString, $username], $username);
        initiateCheck($conn, $username, $password);
        $previousSetupComplete = getValueFromDatabase($conn, "users", "setup_complete", ["username" => $username], $username);
        if (!$previousSetupComplete && $emailAdress) {
            $URLParameterMessage = "settingsSavedSuccessfullyAndHowToContinue";
        } elseif(!$previousSetupComplete && empty($emailAdress)) {
            $URLParameterMessage = "settingsSavedSuccessfullyAndReferToEmail";
        } else {
            $URLParameterMessage = "settingsSavedSuccessfully";
        }
        redirectToSettingsPage($URLParameterMessage);
    } catch (DatabaseException $e) {
        redirectToSettingsPage("settingsNotSaved");
    } catch (UserException $e) {
        Logger::log($e->getMessage(), $username);
        redirectToSettingsPage("notificationOrDictionaryError");
    }

    $conn->close();
}



if (isset($_POST['action'])) {
    try {
        $conn = connectToDatabase();
    } catch (DatabaseException $e) {
        redirectToSettingsPage("dbConnError");
    }
    switch ($_POST['action']) {
        case 'logout':
            logOut();
            break;
        case 'deleteAccount':
            try {
                deleteFromDatabase($conn, "users", ["username = ?"], [$username], $username);
                Logger::log("Account successfully deleted", $username);
                sleep(2);
                $conn->close();
                logOut("accountDeletedSuccessfully");
            } catch (DatabaseException $e) {
                redirectToSettingsPage("accountNotDeleted");
            }
            break;
        case 'testNotification':
            if(!$emailAdress){
                redirectToSettingsPage("noEmailAdress");
                break;
            } else {
                try {
                    sendEmail($username, "Testbenachrichtigung", "Testbenachrichtigung", "", "", "", $conn);
                    if (updateDatabase($conn, "users", ["setup_complete"], ["username = ?"], [true, $username], $username)) {
                        Logger::log("User $username completed the setup; Testnotification send successfully");
                        redirectToSettingsPage("testNotificationSent");
                        break;
                    }
                } catch (DatabaseException|UserException $e) {
                    redirectToSettingsPage("testNotificationNotSent");
                    if (str_contains($e, 'Invalid address')) {
                        redirectToSettingsPage("testNotificationNotSentInvalidEmail");
                    }
                    break;
                }
            }
            break;
        case 'adminPanel':
            $conn->close();
            header("Location: admin");
            exit();
        case 'notionFormula':
            $conn->close();
            header("Location: notionFormula");
            exit();
        default:
            break;
    }
    $conn->close();
}

try {
    // Vars for checkboxes need to be set either to "" or "checked" to work properly
    $checkboxAusfall = in_array("ausfall", $receiveNotificationsFor) ? "checked" : "";
    $checkboxVertretung = in_array("vertretung", $receiveNotificationsFor) ? "checked" : "";
    $checkboxRaumänderung = in_array("raumänderung", $receiveNotificationsFor) ? "checked" : "";
    $checkboxSonstiges = in_array("sonstiges", $receiveNotificationsFor) ? "checked" : "";
} catch (Exception $e) {
    Logger::log($e->getMessage(), $username);
    redirectToSettingsPage("receiveNotificationsForError");
}


?>




<div class="parent" id="parent">
    <span class="loader" id="loading-animation" ></span>

    <form action="settings" method="post">

        <button id="toggle-theme" class="dark-mode-switch-btn" type="button">
            <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
        </button>
        <h2>Einstellungen</h2>
        <br>

        <label for="emailAdress">Email-Adresse:</label>
        <div class="label-container">
            <input type="text" id="emailAdress" name="emailAdress" value="<?php echo $emailAdress; ?>" placeholder="deine.email@gmail.com">
            <span class="info-icon" onclick="openExternInfoSite('EmailAdress')" onKeyDown="openExternInfoSite('EmailAdress')">?</span>
        </div>
        <br><br>

        <label for="dictionary">Dictionary (optional):</label>
        <div class="label-container">
            <input type="text" id="dictionary" name="dictionary" value="<?php echo $dictionary; ?>" placeholder="ph1E=Physik; ch2E=Chemie; la1E=Latein">
            <span class="info-icon" onclick="openExternInfoSite('dictionary')" onKeyDown="openExternInfoSite('dictionary')">?</span>
        </div>
        <br><br>






    <fieldset class="checkbox-group">
        <legend class="checkbox-group-legend">Wofür möchtest du <br> benachrichtigt werden?</legend>
        <div class="checkbox">
            <label class="checkbox-wrapper">
                <input type="checkbox" class="checkbox-input" name="notifications[]" value="ausfall" <?php echo $checkboxAusfall; ?>/>
                <span class="checkbox-tile">
                    <span class="checkbox-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-slash-2"><circle cx="12" cy="12" r="10"/><path d="M22 2 2 22"/></svg>
                    </span>
                    <span class="checkbox-label">Ausfall</span>
                </span>
            </label>
        </div>
        <div class="checkbox">
            <label class="checkbox-wrapper">
                <input type="checkbox" class="checkbox-input" name="notifications[]" value="vertretung" <?php echo $checkboxVertretung; ?>/>
                <span class="checkbox-tile">
                    <span class="checkbox-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                    </span>
                    <span class="checkbox-label">Vertretung</span>
                </span>
            </label>
        </div>
        <div class="checkbox">
            <label class="checkbox-wrapper">
                <input type="checkbox" class="checkbox-input" name="notifications[]" value="raumänderung" <?php echo $checkboxRaumänderung; ?>/>
                <span class="checkbox-tile">
                    <span class="checkbox-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left-right"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg>
                    </span>
                    <span class="checkbox-label">Raum-<br>änderung</span>
                </span>
            </label>
        </div>
        <div class="checkbox">
            <label class="checkbox-wrapper">
                <input type="checkbox" class="checkbox-input" name="notifications[]" value="sonstiges" <?php echo $checkboxSonstiges; ?>/>
                <span class="checkbox-tile">
                    <span class="checkbox-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square-warning"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v2"/><path d="M12 13h.01"/></svg>
                    </span>
                    <span class="checkbox-label">Sonstiges*</span>
                </span>
            </label>
        </div>
        <p class="info-text">*Hierzu zählen Dinge wie Veranstaltungen, Raumaustragungen und Lehreraustragungen <br> (= oft eine Vorstufe von Ausfall / Vertretung)</p>
    </fieldset>
    <br><br>





        <label for="notificationDays">Wie viele Tage im Voraus sollen auf Änderungen geprüft werden?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="0" max="20" value="<?php echo $notificationForDaysInAdvance; ?>" oninput="this.nextElementSibling.value = this.value">
            <output><?php echo $notificationForDaysInAdvance; ?></output>
            <span class="info-icon info-icon-slider" onclick="openExternInfoSite('TageInVoraus')"
                  onKeyDown="openExternInfoSite('TageInVoraus')">?</span>
        </div>
        <br><br>
        <button class="btn-save-settings btn" type="submit" onclick="showLoadingAnimation()">Einstellungen speichern</button>
        <br><br>
        <?php
        $messageToUser = $_GET['messageToUser'] ?? $messageToUser;
        echo getMessageText($messageToUser);
        ?>
    </form>
    <form action="settings" method="post">
        <button class="btn-testbenachrichtigung btn" type="submit" name="action" value="testNotification" onclick="showLoadingAnimation()">Testbenachrichtigungen senden</button><br>
        <nobr>
            <button class="btn-log-out btn" type="submit" name="action" value="logout" onclick="showLoadingAnimation()">Abmelden</button>
            <button class="btn-delete-acc btn" type="submit" name="action" value="deleteAccount" onclick="showLoadingAnimation()">Konto löschen</button>
        </nobr>

        <?php

        global $config;
        $adminUsername = $config['adminUsername'];

        if ($username === $adminUsername) {
            echo '<br><button class="admin-panel-btn btn" type="submit" name="action" value="adminPanel" onclick="showLoadingAnimation()">Admin Panel</button>';

        }

        $usersToShowNotionFormulaBtn = $config['usersToShowNotionFormulaBtn'];
        if (in_array($username, $usersToShowNotionFormulaBtn)) {
            echo '<br><button class="notion-formula-btn btn" type="submit" name="action" value="notionFormula" onclick="showLoadingAnimation()">Notion Hausaufgaben Formel</button>';
        }
        ?>

    </form>
</div>
</body>
<script src="script.js"></script>
</html>
