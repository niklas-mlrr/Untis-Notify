<?php


$serverName = $_SERVER['SERVER_NAME'] ?? '';
$agentIsNotCronjob = $_SERVER['HTTP_HOST'] ?? false;

if ($serverName === 'localhost' || $agentIsNotCronjob) {
    $baseDir = __DIR__;
} else {
    $baseDir = __DIR__ . "/domains/untis-notify.de/public_html";
}

require_once $baseDir . "/functions.php";

use Exceptions\DatabaseException;


if(!checkIfURLExists("https://niklas.craft.me/kill-switch")) {
    ErrorLogger::log("The Kill-switch prevented the cronjob", "Cronjob");
} else {

    try {
        $conn = connectToDatabase();
        $users = getRowsFromDatabase($conn, "users", ["setup_complete" => 1], "Cronjob");
    } catch (DatabaseException $e) {
        exit();
    }

    foreach ($users as $user) {
        $username = $user['username'];
        $passwordCipher = $user['password_cipher'];
        $password = decryptCipher($passwordCipher);
        try {
            $passwordHash = getValueFromDatabase($conn, "users", "password_hash", ["username" => $username], $username);
        } catch (DatabaseException $e) {
            continue;
        }

        if (!password_verify($password, $passwordHash)) {
            ErrorLogger::log("Password verification failed", $username);
            continue;
        }

        initiateCheck($conn, $username, $password);
    }
    $conn->close();
}

