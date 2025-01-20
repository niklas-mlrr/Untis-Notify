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
    $password = $user['password'];
    initiateCheck($conn, $username, $password);
}
$conn->close();
