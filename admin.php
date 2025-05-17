<!DOCTYPE html>
<html lang="de">
<head>
    <title>Admin Panel</title>
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();

use Exceptions\DatabaseException;
require_once "functions.php";

$username = $_SESSION['username'] ?? null;

global $config;
$adminUsername = $config['adminUsername'];

if($username != $adminUsername) {
    logOut();
}


$firstMessageToUser = '';
$secondMessageToUser = '';

try {
    $conn = connectToDatabase();
    $users = getRowsFromDatabase($conn, "users", ["setup_complete" => 1], "Cronjob");
    $pwLoggingMode = getValueFromDatabase($conn, "settings", "pw_logging_mode", ["id" => 1], "Admin");
} catch (DatabaseException $e) {
    $firstMessageToUser = getMessageText("dbError");
    $secondMessageToUser = getMessageText("dbError");
    exit();
}



if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'sendCustomMessage') {
        $username = $_POST['user'] ?? null;
        $forDate = $_POST['forDate'] ?? null;
        $affectedLessons = $_POST['affectedLessons'] ?? null;
        $message = $_POST['message'] ?? null;
        $oldValue = $_POST['oldValue'] ?? null;


        $affectedLessons = trim($affectedLessons);
        $message = trim($message);
        $oldValue = trim($oldValue);



        try {
            if ($username && $affectedLessons && $message) {
                if ($username === "all") {
                    foreach ($users as $user) {
                        $username = $user['username'];
                        sendEmail($username, $affectedLessons, $message, $oldValue, "", $forDate, $conn);
                        $firstMessageToUser = getMessageText("messageSentSuccessfully");
                    }
                } else {
                    sendEmail($username, $affectedLessons, $message, $oldValue, "", $forDate, $conn);
                    $firstMessageToUser = getMessageText("messageSentSuccessfully");
                }
            } else {
                $firstMessageToUser = getMessageText("emptyFields");
            }
        } catch (DatabaseException|Exception $e) {
            $firstMessageToUser = getMessageText("messageNotSent");
        }
    } elseif ($formType === 'pwLoggingMode') {
        $pwLoggingMode = $_POST['pwLoggingMode'] ?? null;
        if ($pwLoggingMode) {
            $pwLoggingMode = 1;
        } else {
            $pwLoggingMode = 0;
        }

        try {
            updateDatabase($conn, "settings", ["pw_logging_mode"], ["id = ?"], [$pwLoggingMode, 1], "Admin");
            $secondMessageToUser = getMessageText("settingsSavedSuccessfully");
        } catch (DatabaseException $e) {
            $secondMessageToUser = getMessageText("settingsNotSaved");
        }

    }
}




?>


<div class="parent">

    <span class="loader" id="loading-animation" ></span>

    <form action="admin" method="post">
        <input type="hidden" name="form_type" value="sendCustomMessage">

        <button id="navigate-back-btn" class="navigate-back-btn" type="button" onclick="showLoadingAnimation()">
            <img src="https://img.icons8.com/?size=100&id=26194&format=png&color=0000009C" alt="navigate-back-icon" class="navigate-back-icon">
        </button>

        <button id="toggle-theme" class="dark-mode-switch-btn" type="button">
            <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
        </button>
        <h2>Admin Panel</h2>
        <br>
        <h3>Send Custom Message</h3>

        <label for="user">User:</label>
        <div class="label-container">
            <select id="user" class="custom-message-select" name="user" required>
                <option disabled selected>User</option>
                <option value="all">All</option>
                <?php
                foreach ($users as $user) {
                    echo "<option value='" . $user['username'] . "'>" . $user['username'] . "</option>";
                }
                ?>
            </select>
        </div>
        <br>


        <label for="forDate">For Date:</label>
        <div class="label-container">
            <input type="date" id="forDateInput" class="date-input" name="forDate"">
        </div>
        <br>

        <label for="affectedLessons">Affected Lessons (part of title):</label>
        <div class="label-container">
            <input type="text" id="affectedLessons" name="affectedLessons" placeholder="Affected Lessons" required>
        </div>
        <br>

        <label for="message">Message:</label>
        <div class="label-container">
            <textarea id="message" class="message-textarea" name="message" placeholder="Message" required></textarea>
        </div>
        <br>

        <label for="oldValue">Old Value:</label>
        <div class="label-container">
            <input type="text" id="oldValue" name="oldValue" placeholder="OldValue">
        </div>


        <br><br>
        <button class="btn-save-settings btn" type="submit">Benachrichtigung senden</button>
        <br>
        <?php echo $firstMessageToUser; ?>
    </form>


    <form action="admin" method="post">
        <input type="hidden" name="form_type" value="pwLoggingMode">
        <div class="pwLoggingMode-div">
            <input class="pwLoggingMode-input" type="checkbox" id="pwLoggingMode" name="pwLoggingMode" <?php echo $pwLoggingMode ? "checked" : "";?>
            >
            <label for="pwLoggingMode">Login Password Logging</label>
        </div>
        <br>
        <button class="btn-save-settings btn" type="submit">Speichern</button>
        <br>
        <?php echo $secondMessageToUser; ?>
    </form>
</div>
</body>
<script src="script.js"></script>
</html>

