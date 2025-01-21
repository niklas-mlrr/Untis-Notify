<?php
require_once "functions.php";




try {
    $conn = connectToDatabase();
    $users = getRowsFromDatabase($conn, "users", ["setup_complete" => 1]);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}


foreach ($users as $user) {
    $username = $user['username'];
    $passwordCipher = $user['password_cipher'];
    $password = decryptCipher($passwordCipher);
    $passwordHash = getValueFromDatabase($conn, "users", "password_hash", ["username" => $username]);

    if (!password_verify($password, $passwordHash)) {
        continue;
    }

    initiateCheck($conn, $username, $password);
}
$conn->close();
