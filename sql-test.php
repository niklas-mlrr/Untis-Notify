<?php

function connectToDatabase() {
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $database = "untis";

// Create connection
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

// Select database
    $conn->select_db($database);
    return $conn;
}

$conn = connectToDatabase();

function getDataFromDatabase($query) {
    global $conn;
    $testArr = [];

    $sql = $query;
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Output data of each row
        while ($row = $result->fetch_assoc()) {
            //echo "id: " . $row["id"] . "; test: " . $row["test"] . "<br>";
            array_push($testArr, $row["test"]);
        }
        return $testArr;
    } else {
        return "0 results";
    }
}








echo "<pre>";
print_r(getDataFromDatabase("SELECT * FROM test_table WHERE id=(SELECT max(id) FROM test_table);"));

function writeDataToDatabase($input) {
    global $conn;

    $sql = "INSERT INTO test_table (test) VALUES ('$input')";
    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

//writeDataToDatabase("input");

$conn->close();


