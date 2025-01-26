<!DOCTYPE html>
<html lang="de">
<head>
    <title>Untis Notify</title>
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
require_once __DIR__ . "/Exceptions/AuthenticationException.php";
use Exceptions\AuthenticationException;

$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$schoolUrl = $_POST['schoolUrl'] ?? null;



if ($username && $password && $schoolUrl) {
    require_once "functions.php";

    $username = trim($username);
    $password = trim($password);
    $schoolUrl = trim($schoolUrl);

    try {
        $sessionId = loginToWebUntis($username, $password, $schoolUrl);
        echo "Session ID: " . $sessionId . "<br>";

        $conn = connectToDatabase();


        $isUserInDatabaseAndAuthenticated = authenticateEncryptedPassword($conn, $username, $password);
        echo "Is user in database and authenticated: " . ($isUserInDatabaseAndAuthenticated ? "true" : "false") . "<br>";

        if (!$isUserInDatabaseAndAuthenticated) {
            $passwordCipherAndHash = encryptAndHashPassword($password);
            if (empty(getRowsFromDatabase($conn, "users", ["username" => $username], $username))) {
                echo "Inserting new user into database...<br>";
                insertIntoDatabase($conn, "users", ["username", "password_cipher", "password_hash", "school_url"], [$username, $passwordCipherAndHash[0], $passwordCipherAndHash[1], $schoolUrl], $username);
            } else {
                echo "Updating user in database...<br>";
                updateDatabase($conn, "users", ["password_cipher", "password_hash"], ["username = ?"], [$passwordCipherAndHash[0], $passwordCipherAndHash[1], $username], $username);
            }
        }

        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['conn'] = $conn;
        $_SESSION['schoolUrl'] = $schoolUrl;

        $currentTimestamp = date('Y-m-d h:i:s', time());
        updateDatabase($conn, "users", ["last_login"], ["username = ?"], [$currentTimestamp, $username], $username);

        $conn->close();
        header("Location: settings.php");
        exit();

    } catch (AuthenticationException | Exception $e) {
        $loginMessage = getMessageText("loginFailed");
    }


} else {
    $loginMessage = '';
}
?>

<div class="img-div">
    <img class="notification-img" src="notification example.png" alt="Notification Example">
</div>
<br><br>
<p class="notification-text">↑ Beispiel einer Benachrichtigung ↑</p>

<div class="parent">
    <form action="index.php" method="post">
        <button id="toggle-theme" class="dark-mode-switch-btn">
            <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
        </button>
        <h2>Untis Notify</h2>
        <h4>- Benachrichtigungen für Untis -</h4>
        <p class="info-text">Die Einrichtung dauert einmalig ca. 15 Min. und benötigt ein Handy & Pc / Laptop</p>
        <br>

        <label for="schoolUrl">Schul-URL:</label>
        <div class="label-container">
            <input type="text" id="schoolUrl" name="schoolUrl" placeholder="https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode">
            <span class="info-icon" onclick="toggleInfo('info-schoolUrl')" onKeyDown="toggleInfo('info-schoolUrl')">?</span>
        </div>
        <div class="info-field" id="info-schoolUrl">
            <p>Dies ist eine schulspezifische URL. <br> Wenn du auf dem TRG bist, musst du hier nichts eingeben.</p>
        </div>
        <br>

        <label for="username">Untis Benutzername:</label>
        <div class="label-container">
            <input type="text" id="username" name="username" required>
            <span class="info-icon" onclick="openExternInfoSite('username')" onKeyDown="openExternInfoSite('username')">?</span>
        </div>
        <br>

        <label for="password">Untis Passwort:</label>
        <div class="label-container">
            <input type="password" id="password" name="password" required>
            <span class="info-icon" onclick="openExternInfoSite('password')" onKeyDown="openExternInfoSite('password')">?</span>
            <span class="password-toggle" onclick="togglePasswordVisibility()" onKeyDown="togglePasswordVisibility()">
                <i id="toggleIcon" class="far fa-eye-slash"></i>
            </span>
        </div>
        <br>

        <input type="submit" value="Einloggen">
        <br><br>
        <?php echo $loginMessage; ?>

        <a class="info-text" href="impressum.php">Impressum</a>
    </form>
</div>
</body>
<script src="script.js"></script>
</html>
