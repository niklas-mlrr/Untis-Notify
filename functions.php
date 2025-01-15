<?php






function initiateCheck() {
    global $username, $password, $conn;

    $conn = connectToDatabase();
    $setupComplete = getColumnFromDatabase($conn, [$username], "setup_complete", "SELECT setup_complete FROM users where username  = ?");

    $schoolUrl = getColumnFromDatabase($conn, [$username], "school_url", "SELECT school_url FROM users where username  = ?");
    $login = loginToWebUntis($username, $password, $schoolUrl);

    if (!$setupComplete || !$login) {
        return;
    }

    $students = getStudents($login);

    $currentDate = date("Ymd");
    writeDataToDatabase($conn, [$currentDate], "DELETE FROM timetables WHERE for_Date < ?");    // Delete old timetables


    // for each user:


    $userId = getStudentIdByName($students, $username);
    $notificationForDaysInAdvance = getColumnFromDatabase($conn, [$username], "notification_for_days_in_advance", "SELECT notification_for_days_in_advance FROM users where username  = ?");

    for ($i = 0; $i < $notificationForDaysInAdvance; $i++) {
        $date = date("Ymd", strtotime("+$i days"));

        $timetable = getTimetable($login, $userId, $date);
        $formatedTimetable = getFormatedTimetable($timetable);


        $lastRetrieval = getColumnFromDatabase($conn, [$date, $username], "timetableData", "SELECT timetableData FROM timetables where for_Date  = ? AND user = ?");
        if($lastRetrieval){
            $lastRetrieval = json_decode($lastRetrieval, true);
        }


        if (!$lastRetrieval && $formatedTimetable != NULL) {
            writeDataToDatabase($conn, [$formatedTimetable, $date, $username], "INSERT INTO timetables (timetableData, for_Date, user) VALUES (?, ?, ?)");
            continue;
        } else if (!$lastRetrieval && $formatedTimetable == NULL) {
            continue;
        }


        $compResult = compareArrays($lastRetrieval, $formatedTimetable, $date);



        if ($compResult) {  // Update the database with the new timetable
            writeDataToDatabase($conn, [$formatedTimetable, $date, $username], "UPDATE timetables SET timetableData = ?, for_Date = ?, user = ? WHERE for_Date = $date");
        }
    }
    $conn->close();
}






/**
 * Sends a message to a slack channel using the slack Web API
 * @param string $channel The channel to send the message to (e.g. "#general")
 * @param string $message The message to send
 * @param string $channel The channel to send the message to (e.g. "#general")
 * @param string $bot_token Your slack bot user OAuth token
 */
function sendslackMessage($channel, $title, $message, $date) {
    global $username, $conn;
    $url = 'https://slack.com/api/chat.postMessage';


    $botToken = getColumnFromDatabase($conn, [$username], "slack_bot_token", "SELECT slack_bot_token FROM users where username  = ?");



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
        'channel' => $channel,
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
            "text" => "$message"
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
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $response_data = json_decode($response, true);
    //print_r($response_data);

    if ($http_code !== 200 || !$response_data['ok']) {
        return false;
    }

    return true;
}









/**
 * Logs into the WebUntis API and returns the session ID
 *
 * @param string $username The username to log in with
 * @param string $password The password to log in with
 * @param string $schoolUrl The URL of the school
 * @return string The session ID
 */
function loginToWebUntis(string $username, string $password, string $schoolUrl): string {

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
        return $result['result']['sessionId'];
    }
    return "";
}

/**
 * @param $sessionId
 * @param $payload
 * @return mixed
 */
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


/**
 * @param $sessionId
 * @param $userId
 * @param $date
 * @return mixed
 */
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


/**
 * @param $sessionId
 * @return mixed
 */
function getStudents($sessionId) {
    $payload = [
        "id" => "getStudents",
        "method" => "getStudents",
        "params" => [],
        "jsonrpc" => "2.0"
    ];
    return sendApiRequest($sessionId, $payload);
}


/**
 * @param $studentArray
 * @param $name
 * @return mixed|null
 */
function getStudentIdByName($studentArray, $name) {
    foreach ($studentArray as $student) {
        if ($student['name'] === $name) {
            return $student['id'];
        }
    }
    return null;
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


/**
 * @param $timetable
 * @return array
 */
function getFormatedTimetable($timetable) {
    global $startTimes;
    $numOfLessons = count($timetable);
    $formatedTimetable = [];


    for($i = 0; $i < $numOfLessons; $i++){

        $canceled = isset($timetable[$i]["code"]) ? 1 : 0;

        $lesson = [
            "canceled" => $canceled,
            "lessonNum" => $startTimes[$timetable[$i]["startTime"]],
            "subject" => $timetable[$i]["su"][0]["longname"],
            "teacher" => $timetable[$i]["te"][0]["name"],
            "room" => $timetable[$i]["ro"][0]["name"],
        ];
        array_push($formatedTimetable, $lesson);
    }


    // Sort by lessonNum
    usort($formatedTimetable,"cmp");

    return $formatedTimetable;
}










/**
 * @param $array1
 * @param $array2
 * @return array|string
 */
function compareArrays($array1, $array2, $date) {

    $differencesChannel = [];
    $differencesTitle = [];
    $differencesMessage = [];

    // Vergleiche alle Elemente des ersten Arrays
    foreach ($array1 as $key => $item) {
        // Wenn der Index im zweiten Array nicht existiert, markiere dies
        if (!isset($array2[$key])) {
            $differencesChannel[] = "sonstiges";
            $differencesTitle[] = "{$item["lessonNum"]}. Stunde {$item["subject"]} fehlt nun komplett";    //(...  im zweiten Array)
            continue;
        }

        // Vergleiche die einzelnen Werte
        foreach ($item as $subKey => $value) {
            if (!isset($array2[$key][$subKey])) {
                $differencesChannel[] = "sonstiges";
                $differencesTitle[] = "Schlüssel '$subKey'" . " fehlt in Array 2 bei Index $key";
            }

            if ($array2[$key][$subKey] !== $value) {        // Wird ausgeführt, wenn ein Wert unterschiedlich ist
                $differencesTitle[] = "{$item["lessonNum"]}. Stunde {$item["subject"]}";   // z.B. 1. Stunde Mathe
                if ($subKey == "canceled" && $value == 1) {
                    $differencesChannel[] = "sonstiges";
                    $differencesMessage[] = "Jetzt kein Ausfall mehr";
                    continue;
                }
                if ($subKey == "canceled" && $value == 0) {
                    $differencesChannel[] = "ausfall";
                    $differencesMessage[] = " ";
                    continue 2;
                }
                if ($subKey == "teacher" && $array2[$key][$subKey] == "---") {
                    $differencesChannel[] = "sonstiges";
                    $differencesMessage[] = "Lehrer ausgetragen (Vorher: $value)";
                    continue;
                } elseif ($subKey == "teacher") {
                    $differencesChannel[] = "vertretung";
                    $differencesMessage[] = "Vorher: $value; Jetzt: {$array2[$key][$subKey]}";
                    continue;
                }
                if ($subKey == "room" && $array2[$key][$subKey] == "---") {
                    $differencesChannel[] = "sonstiges";
                    $differencesMessage[] = "Raum ausgetragen (Vorher: $value)";
                    continue;
                } elseif ($subKey == "room") {
                    $differencesChannel[] = "raumänderung";
                    $differencesMessage[] = "Vorher: $value; Jetzt: {$array2[$key][$subKey]}";
                    continue;
                }
                if ($subKey == "subject" && $array2[$key][$subKey] == "---") {
                    $differencesChannel[] = "sonstiges";
                    $differencesMessage[] = "Fach ausgetragen (Vorher: $value)";
                    continue;
                } elseif ($subKey == "subject") {
                    $differencesChannel[] = "sonstiges";
                    $differencesMessage[] = "Fachwechsel; Vorher: $value; Jetzt: {$array2[$key][$subKey]}";
                    continue;
                }
                }
            }
        }


    // Prüfe auch das zweite Array auf zusätzliche Indizes
    foreach ($array2 as $key => $item) {
        if (!isset($array1[$key])) {
            $differencesChannel[] = "{$item["lessonNum"]}. Stunde: Neues Fach";
            $differencesMessage[] = "{$item["subject"]} bei {$item["teacher"]} in Raum {$item["room"]} ist nun mit dazugekommen";
        }
    }



    for ($i = 0; $i < count($differencesChannel); $i++) {
        $chanel = $differencesChannel[$i];
        $title = $differencesTitle[$i];
        $message = $differencesMessage[$i];

        sendslackMessage($chanel, $title, $message, $date);
    }

    if (empty($differencesChannel)) {
        return false;
    }
    return "Änderungen vorhanden";


}















/**
 * @param $case
 * @return string
 */
function getMessageText($case) {
    switch ($case) {
        case "loginFailed":
            return '<p class="failed">Fehler beim Einloggen</p>';

        case "settingsSavedSuccessfully":
            return '<p class="successful">Einstellungen erfolgreich gespeichert</p>';
        case "settingsNotSaved":
            return '<p class="failed">Fehler beim Speichern der Einstellungen</p>';

        case "accountDeletedSuccessfully":
            return '<p class="successful">Konto erfolgreich gelöscht</p>';
        case "accountNotDeleted":
            return '<p class="failed">Fehler beim Löschen des Kontos</p>';

        case "testNotificationSent":
            return '<p class="successful">Testbenachrichtigung erfolgreich gesendet</p>';
        case "testNotificationNotSent":
            return '<p class="failed">Fehler beim Senden der Testbenachrichtigung</p>';

        default:
            return "";
    }
}


/**
 * @return void
 */
function logOut(){
    setcookie(session_name(), '', 100);
    session_unset();
    session_destroy();
    $_SESSION = array();
    header("Location: index.php");
}


/**
 * @return mysqli
 */
function connectToDatabase(): mysqli {
    $config = include 'config.php';

    $servername = $config['servername'];
    $username = $config['username'];
    $password = $config['password'];
    $database = $config['database'];

    // Create connection
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}


/**
 * @param mysqli $conn
 * @param array $inputs
 * @param string $query
 * @return bool
 */
function getRowsFromDatabase(mysqli $conn, array $inputs, string $query): bool {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $types = str_repeat('s', count($inputs));
    $stmt->bind_param($types, ...$inputs);

    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}


/**
 * @param mysqli $conn
 * @param array $inputs
 * @param string $dataFromColumn
 * @param string $query
 * @return string|null
 */
function getColumnFromDatabase(mysqli $conn, array $inputs, string $dataFromColumn, string $query): ?string {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $types = str_repeat('s', count($inputs));
    $stmt->bind_param($types, ...$inputs);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row[$dataFromColumn] ?? null;
    }
    return null;
}


/**
 * @param mysqli $conn
 * @param array $inputs
 * @param string $query
 * @return bool
 */
function writeDataToDatabase(mysqli $conn, array $inputs, string $query): bool {
    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $types = str_repeat('s', count($inputs));
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$inputs);

    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}



