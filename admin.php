<!DOCTYPE html>
<html lang="de">
<head>
    <title>Admin Panel</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" href="logo.svg">
    <link rel="icon" href="logo.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();

use Exceptions\DatabaseException;
require_once "functions.php";

$btnResponse = '';

try {
    $conn = connectToDatabase();
    $users = getRowsFromDatabase($conn, "users", ["setup_complete" => 1], "Cronjob");
} catch (DatabaseException $e) {
    $btnResponse = getMessageText("dbError");
    exit();
}



if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['user'] ?? null;
    $channel = $_POST['channel'] ?? null;
    $forDate = $_POST['forDate'] ?? null;
    $title = $_POST['title'] ?? null;
    $message = $_POST['message'] ?? null;

    $title = trim($title);
    $message = trim($message);
    

    $title = $title . "; ";

    try {
    if($username && $channel && $title && $message) {
        if($username === "all") {
            foreach ($users as $user) {
                $username = $user['username'];
                sendSlackMessage($username, $channel, $title, $message, $forDate, $conn);
                $btnResponse = getMessageText("messageSentSuccessfully");
            }
        } else {
            sendSlackMessage($username, $channel, $title, $message, $forDate, $conn);
            $btnResponse = getMessageText("messageSentSuccessfully");
        }
    } else {
        $btnResponse = getMessageText("emptyFields");
    }
    } catch (DatabaseException|Exception $e) {
        $btnResponse = getMessageText("messageNotSent");
    }
}







?>


<div class="parent">
    <form action="admin.php" method="post">
        <button id="navigate-back-btn" class="navigate-back-btn" type="button">
            <img src="https://img.icons8.com/?size=100&id=26194&format=png&color=0000009C" alt="navigate-back-icon" class="navigate-back-icon">
        </button>

        <button id="toggle-theme" class="dark-mode-switch-btn dark-mode-switch-btn-potential-small" type="button">
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

        <label for="channel">Channel:</label>
        <div class="label-container">
            <select id="channel" class="custom-message-select" name="channel" required>
                <option disabled selected>Channel</option>
                <option value="sonstiges">Sonstiges</option>
                <option value="ausfall">Ausfall</option>
                <option value="vertretung">Vertretung</option>
                <option value="raumänderung">Raumänderung</option>
            </select>
        </div>
        <br>


        <label for="forDate">For Date:</label>
        <div class="label-container">
            <input type="date" id="forDateInput" class="for-date-input" name="forDate"">
        </div>
        <br>

        <label for="title">Title:</label>
        <div class="label-container">
            <input type="text" id="title" name="title" placeholder="Title" required>
        </div>
        <br>

        <label for="message">Message:</label>
        <div class="label-container">
            <textarea id="message" class="message-textarea" name="message" placeholder="Message" required></textarea>
        </div>


        <br><br>
        <button class="btn-save-settings btn" type="submit">Benachrichtigung senden</button>
        <br>
        <?php echo $btnResponse; ?>
    </form>
</div>

</body>
<script src="script.js"></script>
</html>

