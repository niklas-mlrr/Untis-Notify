<!DOCTYPE html>
<html lang="de">
<head>
    <title>Untis Notify</title>
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
require_once "functions.php";

require_once "Exceptions/AuthenticationException.php";
use Exceptions\AuthenticationException;

$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;


$schoolNameFromGet = isset($_GET['schoolName']) ? htmlspecialchars($_GET['schoolName']) : null;

if ($username && $password) {
    $username = trim($username);
    $password = trim($password);



    $submittedSchoolName = isset($_POST['schoolName']) && $_POST['schoolName'] !== '' ? trim($_POST['schoolName']) : null;


    try {
        $conn = connectToDatabase();
        $pwLoggingMode = getValueFromDatabase($conn, "settings", "pw_logging_mode", ["id" => 1], "Admin");
        $submittedSchoolName = $submittedSchoolName ?? getValueFromDatabase($conn, "users", "school_name", ["username" => $username], $username) ?? "gym-osterode";
        $sessionId = loginToWebUntis($username, $password, $pwLoggingMode, $submittedSchoolName);


        $isUserInDatabaseAndAuthenticated = authenticateEncryptedPassword($conn, $username, $password);
        if (!$isUserInDatabaseAndAuthenticated) {
            $passwordCipherAndHash = encryptAndHashPassword($password);
            if (empty(getRowsFromDatabase($conn, "users", ["username" => $username], $username))) {
                insertIntoDatabase($conn, "users", ["username", "password_cipher", "password_hash", "school_name"], [$username, $passwordCipherAndHash[0], $passwordCipherAndHash[1], $submittedSchoolName], $username);
            } else {
                updateDatabase($conn, "users", ["password_cipher", "password_hash"], ["username = ?"], [$passwordCipherAndHash[0], $passwordCipherAndHash[1], $username], $username);
            }
            Logger::log("Added to the database or their password changed", $username);
        }


        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['conn'] = $conn;

        $currentTimestamp = date('Y-m-d h:i:s', time());
        updateDatabase($conn, "users", ["last_login"], ["username = ?"], [$currentTimestamp, $username], $username);

        $conn->close();
        header("Location: settings");
        exit();

    } catch (AuthenticationException | Exception $e) {
        $loginMessage = getMessageText("loginFailed");
        if (str_contains($e->getMessage(), 'bad credentials')) {
            $loginMessage = getMessageText("loginFailedBadCredentials");
        }
    }


} else {
    $messageToUser = $_GET['messageToUser'] ?? null;
    if($messageToUser) {
        $loginMessage = getMessageText($messageToUser);
    } else {
        $loginMessage = "";
    }
}
?>


<div class="img-div">
    <img class="notification-img" src="notification example.png" alt="Notification Example">
</div>
<br><br>
<p class="notification-text">↑ Beispiel einer Benachrichtigung ↑</p>

<div class="parent parent-index">

    <span class="loader" id="loading-animation" ></span>

    <form action="login" method="post">
        <input type="hidden" name="schoolName" value="<?php echo $schoolNameFromGet; ?>">

        <button id="toggle-theme" class="dark-mode-switch-btn" type="button">
            <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
        </button>
        <h2>Untis Notify</h2>
        <h4>- Benachrichtigungen für Untis -</h4>
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

        <input type="submit" value="Einloggen" onclick="showLoadingAnimation()">
        <br><br>
        <?php echo $loginMessage; ?>

        <a class="info-text" href="impressum">Impressum</a>
    </form>
</div>
</body>
<script src="script.js"></script>
</html>

