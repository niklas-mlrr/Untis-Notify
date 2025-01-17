<?php
require "functions.php";

$conn = connectToDatabase();
$users = getRowsFromDatabase($conn, [1], "SELECT * FROM users WHERE setup_complete = ?");

foreach ($users as $user) {
    $username = $user['username'];
    $password = $user['password'];
    initiateCheck($conn, $username, $password);
}
$conn->close();
