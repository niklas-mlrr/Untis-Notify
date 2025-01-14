<?php


function initiateCheck() {
    global $username, $password, $conn;

    $conn = connectToDatabase();
    $setupComplete = getDataWithOneArgFromDatabase($username, "setup_complete", "SELECT setup_complete FROM users where username  = ?");
    if (!$setupComplete) {
        return;
    }
    $schoolUrl = getDataWithOneArgFromDatabase($username, "school_url", "SELECT school_url FROM users where username  = ?");
    $login = loginToWebUntis($username, $password, $schoolUrl);
    if ($login) {


        $students = getStudents($login);

        $currentDate = date("Ymd");
        deleteDataWithOneArgFromDatabase($currentDate, "DELETE FROM timetables WHERE for_Date < ?");


        // for each user:


        $userId = getStudentIdByName($students, $username);
        $notificationForDaysInAdvance = getDataWithOneArgFromDatabase($username, "notification_for_days_in_advance", "SELECT notification_for_days_in_advance FROM users where username  = ?");


        for ($i = 0; $i < $notificationForDaysInAdvance; $i++) {
            $date = date("Ymd", strtotime("+$i days"));

            $timetable = getTimetable($login, $userId, $date);
            $formatedTimetable = getFormatedTimetable($timetable);

            $lastRetrieval = getDataWithTwoArgFromDatabase($date, $username, "timetableData", "SELECT timetableData FROM timetables where for_Date  = ? AND user = ?");

            if($lastRetrieval){
                $lastRetrieval = json_decode($lastRetrieval, true);
            }



            if (!$lastRetrieval && $formatedTimetable != NULL) {
                writeThreeArgToDatabase($formatedTimetable, $date, $username, "INSERT INTO timetables (timetableData, for_Date, user) VALUES (?, ?, ?)");
                continue;
            } else if (!$lastRetrieval && $formatedTimetable == NULL) {
                continue;
            }


            $compResult = compareArrays($lastRetrieval, $formatedTimetable);
            $result = interpreteResultDataAndSendNotification($compResult, $date);


            if ($result) {
                writeThreeArgToDatabase($formatedTimetable, $date, $username, "UPDATE timetables SET timetableData = ?, for_Date = ?, user = ? WHERE for_Date = $date");
            }
        }

        $conn->close();
    }

}






/**
 * Sends a message to a slack channel using the slack Web API
 *
 * @param string $message The message to send
 * @param string $channel The channel to send the message to (e.g. "#general")
 * @param string $bot_token Your slack bot user OAuth token
 * @return array Response from slack API
 * @throws Exception If the API request fails
 */
function sendslackMessage($title, $message, $date) {
    global $username;
    $url = 'https://slack.com/api/chat.postMessage';


    $botToken = getDataWithOneArgFromDatabase($username, "slack_bot_token", "SELECT slack_bot_token FROM users where username  = ?");


    switch ($date) {
        case date("Ymd"):
            $date = "Heute: ";
            break;
        case date("Ymd", strtotime("+1 days")):
            $date = "Morgen: ";
            break;
        case date("Ymd", strtotime("+2 days")):
            $date = "Übermorgen: ";
            break;
        default:
            $date = $date ? date("d.m", strtotime($date)) . ": " : " ";
    }


    // Prepare the payload
    $payload = array(
        'channel' => "untis-notify",
        "blocks" => [
    [
        "type" => "section",
        "text" => [
            "type" => "mrkdwn",
            "text" => "$date"
        ]
    ],

    [
        "type" => "section",
        "text" => [
            "type" => "mrkdwn",
            "text" => "$title"
        ]
    ],
    [
        "type" => "section",
        "text" => [
            "type" => "mrkdwn",
            "text" => "*$message*"
        ]
    ],
    [
        "type" => "divider"
    ]
]

    );

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $botToken,
            'Content-Type: application/json; charset=utf-8'
        ),
        CURLOPT_POSTFIELDS => json_encode($payload)
    ));

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        curl_close($ch);
        throw new Exception("cURL Error: $error");
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if ($http_code !== 200) {
        throw new Exception("HTTP Error: $http_code - " .
            ($response_data['error'] ?? 'Unknown error'));
    }

    if (!$response_data['ok']) {
        throw new Exception("slack API Error: " .
            ($response_data['error'] ?? 'Unknown error'));
    }

    return $response_data;
}













function sendPushoverNotification($title, $message, $date) {
    global $username;

    $conn = connectToDatabase();
    $token = getDataWithOneArgFromDatabase($username, "pushover_api_key", "SELECT pushover_api_key FROM users where username  = ?");
    $user = getDataWithOneArgFromDatabase($username, "pushover_user_key", "SELECT pushover_user_key FROM users where username  = ?");
    $conn->close();


    switch ($date) {
        case date("Ymd"):
            $date = "Heute: ";
            break;
        case date("Ymd", strtotime("+1 days")):
            $date = "Morgen: ";
            break;
        case date("Ymd", strtotime("+2 days")):
            $date = "Übermorgen: ";
            break;
        default:
            $date = $date ? date("d.m", strtotime($date)) . ": " : "";
    }



    $data = array(
        "token" => $token,
        "user" => $user,
        "title" => $date . $title,
        "message" => $message,
        "url" => "shortcuts://run-shortcut?name=untis",
        "url_title" => "Untis öffnen"
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.pushover.net/1/messages.json");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);



return $status == 200 ? true : false;

}






function loginToWebUntis($username, $password, $schoolUrl) {

    $loginPayload = [
        "id" => "login",
        "method" => "authenticate",
        "params" => [
            "user" => $username,
            "password" => $password,
        ],
        "jsonrpc" => "2.0"
    ];

    $ch = curl_init($schoolUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['result']['sessionId'])) {
        // Session-ID speichern und zurückgeben
        return $result['result']['sessionId'];
    } else {
        //throw new Exception("Login fehlgeschlagen: " . $response);
    }
}






function sendApiRequest($sessionId, $payload) {
    $url = "https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: JSESSIONID=' . $sessionId
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['result'])) {
        return $result['result'];
    } else {
        if (isset($result['error'])) {
            echo "<pre>Fehler: " . print_r($result['error'], true) . "</pre>";
            throw new Exception("Fehler in der API-Antwort.");
        }
        throw new Exception("Fehler in der API-Antwort: " . json_encode($result));
    }
}








function getTimetable($sessionId, $userId, $date) {
    $payload = [
        "id" => "getTimetable",
        "method" => "getTimetable",
        "params" => [
            "options" => [
                "element" => [
                    "id" => $userId,
                    "type" => 5 // Für Schüler, 2 für Lehrer
                ],
                "startDate" => $date,
                "endDate" => $date,

                "showLsText" => true,
                "showStudentgroup" => true,
                "showLsNumber" => true,
                "showSubstText" => true,
                "showInfo" => true,
                "showBooking" => true,
                "klasseFields" => ['id', 'name', 'longname', 'externalkey'],
                "roomFields" => ['id', 'name', 'longname', 'externalkey'],
                "subjectFields" => ['id', 'name', 'longname', 'externalkey'],
                "teacherFields" => ['id', 'name', 'longname', 'externalkey'],
            ]
        ],
        "jsonrpc" => "2.0"
    ];
    return sendApiRequest($sessionId, $payload);
}



function getStudents($sessionId) {
    $payload = [
        "id" => "getStudents",
        "method" => "getStudents",
        "params" => [],
        "jsonrpc" => "2.0"
    ];
    return sendApiRequest($sessionId, $payload);
}



function getStudentIdByName($studentArray, $name) {
    foreach ($studentArray as $student) {
        if ($student['name'] === $name) {
            return $student['id'];
        }
    }
    return null;
}






function getCurrentSchoolYear($sessionId) {
    $payload = [
        "id" => "getCurrentSchoolyear",
        "method" => "getCurrentSchoolyear",
        "params" => [],
        "jsonrpc" => "2.0"
    ];
    return sendApiRequest($sessionId, $payload);
}



$startTimes = [
    "745" => 1,
    "835" => 2,
    "940" => 3,
    "1025" => 4,
    "1130" => 5,
    "1215" => 6,
    "1330" => 7,
    "1415" => 8,
    "1510" => 9,
    "1555" => 10
];



function cmp($a, $b) {
    return $a['lessonNum'] - $b['lessonNum'];
}
function getFormatedTimetable($timetable) {
    global $startTimes;
    $numOfLessons = count($timetable);
    $formatedTimetable = [];


    for($i = 0; $i < $numOfLessons; $i++){

        $canceled = isset($timetable[$i]["code"]) ? 1 : 0;

        $lesson = [
            "lessonNum" => $startTimes[$timetable[$i]["startTime"]],
            "subject" => $timetable[$i]["su"][0]["longname"],
            "teacher" => $timetable[$i]["te"][0]["name"],
            "room" => $timetable[$i]["ro"][0]["name"],
            "canceled" => $canceled,
        ];
        array_push($formatedTimetable, $lesson);
    }


    // Sort by lessonNum


    usort($formatedTimetable,"cmp");

    return $formatedTimetable;
}








$meaningOfChange = [
    "lessonNum" => "Verlegt (Änderung bei lessonNum)",
    "subject" => "Fachwechsel",
    "teacher" => "Vertretung",
    "room" => "Raumänderung",
    "canceled" => ""
];






function compareArrays($array1, $array2) {


    global $meaningOfChange;
    $differencesTitle = [];     // Stores the tiles for the push notifications
    $differencesMessage = [];   // Stores the body for the push notifications

    // Vergleiche alle Elemente des ersten Arrays
    foreach ($array1 as $key => $item) {
        // Wenn der Index im zweiten Array nicht existiert, markiere dies
        if (!isset($array2[$key])) {
            $differencesTitle[] = "{$item["lessonNum"]}. Stunde {$item["subject"]} fehlt nun komplett"; 	//(...  im zweiten Array)
            $differencesMessage[] = "";
            continue;
        }

        // Vergleiche die einzelnen Werte
        foreach ($item as $subKey => $value) {
            if (!isset($array2[$key][$subKey])) {
                $differencesTitle[] = "Schlüssel '$subKey'" . " fehlt in Array 2 bei Index $key";
                $differencesMessage[] = ".";
            }
            elseif ($array2[$key][$subKey] !== $value) {
                $differencesTitle[] = "{$item["lessonNum"]}. Stunde {$item["subject"]} $meaningOfChange[$subKey]";
                if ($subKey == "canceled" && $item[$subKey] == 0) {
                    $differencesMessage[] = "Ausfall";
                } elseif($item[$subKey] == 1) {
                    $differencesMessage[] = "Jetzt kein Ausfall mehr";
                }elseif($subKey == "teacher" && $array2[$key][$subKey] == "---") {
                    $differencesMessage[] = "Lehrer Ausgetragen (Vorher: $value)";
                } else {
                    $differencesMessage[] = "Vorher: $value; Jetzt: {$array2[$key][$subKey]}";
                }
            }
        }
    }

    // Prüfe auch das zweite Array auf zusätzliche Indizes
    foreach ($array2 as $key => $item) {
        if (!isset($array1[$key])) {
            $differencesTitle[] = "{$item["lessonNum"]}. Stunde: Neues Fach";
            $differencesMessage[] = "{$item["subject"]} bei {$item["teacher"]} in Raum {$item["room"]} ist nun mit dazugekommen";
        }
    }


    $result = array_merge($differencesTitle, $differencesMessage);
    return empty($result) ? "Arrays sind identisch" : $result;

}


function interpreteResultDataAndSendNotification($compResult, $date) {
    if ($compResult != "Arrays sind identisch") {
        $comResultLen = count($compResult);
        $compResultTitle = array_slice($compResult, 0, intval($comResultLen / 2));
        $comResultMessage = array_slice($compResult, intval($comResultLen / 2));


        for ($i = 0; $i < count($compResultTitle); $i++) {
            $title = $compResultTitle[$i];
            $message = $comResultMessage[$i];

            sendslackMessage($title, $message, $date);
        }
        return "Änderungen vorhanden";
    } else {
        return null;
    }
}



function getBtnText($isSuccess): string {
    if($isSuccess){
        return '<p class="sucessful">Einstellungen erfolgreich gespeichert</p>';
    } else {
        return '<p class="failed">Fehler beim Speichern der Einstellungen</p>';
    }
}

function logOut(){
    setcookie(session_name(), '', 100);
    session_unset();
    session_destroy();
    $_SESSION = array();
    header("Location: index.php");
}















function connectToDatabase() {
    $config = include 'config.php';

    $servername = $config['servername'];
    $username = $config['username'];
    $password = $config['password'];
    $database = $config['database'];

        // Create connection
        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        //$conn->select_db($database);
        return $conn;
}





function getDataWithOneArgFromDatabase($input, $dataFromRow, $query) {
    global $conn;

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $input);
    
    $stmt->execute();
    $result = $stmt->get_result();

     if ($result->num_rows > 0) {
         while ($row = $result->fetch_assoc()) {
             return $row[$dataFromRow];
         }
     }
}


function getDataWithTwoArgFromDatabase($inputOne, $inputTwo, $dataFromRow, $query) {
    global $conn;

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $inputOne, $inputTwo);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            return $row[$dataFromRow];
        }
    }
}



function writeOneArgToDatabase($input, $query) {
    global $conn;
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $input);

    $stmt->execute();
    $result = $stmt->get_result();
    if(!$result){
        return false;
    } else {
        if ($result->num_rows > 0) {
            return true;
        } else {
            return false;
        }
    }
}


function writeTwoArgToDatabase($returnResponse, $inputOne, $inputTwo, $query) {
    /* @var $conn mysqli */
    global $conn;
    if (is_array($inputOne)) {
        $inputOne = json_encode($inputOne);
    }
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $inputOne, $inputTwo);

    if($returnResponse){
        $stmt->execute();
        $result = $stmt->get_result();
        if(!$result){
            return false;
        } else {
            if ($result->num_rows > 0) {
                return true;
            } else {
                return false;
            }
        }
    } else {
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    }
}


function writeThreeArgToDatabase($inputOne, $inputTwo, $inputThree, $query) {
    /* @var $conn mysqli */
    global $conn;

    if (is_array($inputOne)) {
        $inputOne = json_encode($inputOne);
    }
    if (is_array($inputTwo)) {
        $inputTwo = json_encode($inputTwo);
    }
    if (is_array($inputThree)) {
        $inputThree = json_encode($inputThree);
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $inputOne, $inputTwo, $inputThree);

    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
    }

function writeFourArgToDatabase($inputOne, $inputTwo, $inputThree, $inputFour, $query) {
    /* @var $conn mysqli */
    global $conn;

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $inputOne, $inputTwo, $inputThree, $inputFour);


    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}


function deleteDataWithOneArgFromDatabase($input, $query) {
    global $conn;

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $input);

    if (!$stmt->execute()) {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
