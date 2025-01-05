<?php


// klasseId:329


function sendPushoverNotification($title, $message) {
    $token = "a8o45hxo6bxq2fgu9hpc2spmhxyzr6";
    $user = "usavqqeyudkf9sueqkhsiwdtgosu44";
    
    $data = array(
        "token" => $token,
        "user" => $user,
        "title" => $title,
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
    
    return $status == 200;
}





function loginToWebUntis() {
    $school = "gym-osterode";
    $username = "MüllerNik";
    $password = "RFftJz1n9neBpn,";
    $baseUrl = "https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=$school";

    $loginPayload = [
        "id" => "login",
        "method" => "authenticate",
        "params" => [
            "user" => $username,
            "password" => $password,
        ],
        "jsonrpc" => "2.0"
    ];

    $ch = curl_init($baseUrl);
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
        throw new Exception("Login fehlgeschlagen: " . $response);
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




















function getTimetable($userId, $date) {
    $sessionId = loginToWebUntis();
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

                "showStudentgroup" => true,
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





function getFormatedTimetable($timetable) {
    global $startTimes;
    $numOfLessons = count($timetable);
    $formatedTimetable = [];


    for($i = 0; $i < $numOfLessons; $i++){

        // Set canceled boolean var
        if(isset($timetable[$i]["code"])) {
            $canceled = 1;
        } else {
            $canceled = 0;
        }

        $lesson = [
            "lessonNum" => $startTimes[$timetable[$i]["startTime"]],
            "subject" => $timetable[$i]["su"][0]["longname"],
            "teacher" => $timetable[$i]["te"][0]["name"],
            "room" => $timetable[$i]["ro"][0]["name"],
            "canceled" => $canceled
        ];
        array_push($formatedTimetable, $lesson);
    }


    // Sort by lessonNum
    function cmp($a, $b) {
        return $a['lessonNum'] - $b['lessonNum'];
    }
    usort($formatedTimetable,"cmp");


    return $formatedTimetable;
}






function saveToFile($data, $fileName) {
    $jsonData = json_encode($data);
    file_put_contents($fileName, $jsonData);
}


function retrieveFromFile($fileName) {
    $jsonData = file_get_contents($fileName);
    return json_decode($jsonData, true);    // Zu Array umwandeln
}







$userId = 2995;
$date = date("Ymd", strtotime("-19 days"));
$timetable = getTimetable($userId, $date);
    // echo '<pre>'; 
    // print_r($timetable);
    // echo "--------------------------------------------------------------------------";


$formatedTimetable = getFormatedTimetable($timetable);
    echo '<pre>'; 
    print_r($formatedTimetable);


    // saveToFile($formatedTimetable, "lastRetrieval.txt");



$lastRetrieval = retrieveFromFile("lastRetrieval.txt");
    echo '<pre>'; 
    print_r($lastRetrieval);














$meaningOfChange = [
    "lessonNum" => "Verlegt (Änderung bei lessonNum)",
    "subject" => "Fachwechsel",
    "teacher" => "Vertretung",
    "room" => "Raumänderung",
    "canceled" => "Ausfall",
];
    





function compareArrays($array1, $array2) {
    global $meaningOfChange;
    $differencesTitle = [];     // Stores the tiles for the push notifications
    $differencesMessage = [];   // Stores the body for the push notifications
    
    // Vergleiche alle Elemente des ersten Arrays
    foreach ($array1 as $key => $item) {
        // Wenn der Index im zweiten Array nicht existiert, markiere dies
        if (!isset($array2[$key])) {
            $differencesTitle[] = "{$array1[$key]["subject"]} fehlt nun komplett"; 	//(...  im zweiten Array)
            $differencesMessage[] = "";
            continue;
        }
        
        // Vergleiche die einzelnen Werte
        foreach ($item as $subKey => $value) {
            if (!isset($array2[$key][$subKey])) {
                $differencesTitle[] = "Schlüssel '$subKey'" . $array1[$subKey] . " fehlt in Array 2 bei Index $key";
                $differencesMessage[] = "";
            } 
            elseif ($array2[$key][$subKey] !== $value) {
                $differencesTitle[] = "{$array1[$key]["lessonNum"]}. Stunde {$array1[$key]["subject"]} $meaningOfChange[$subKey]";
                if ($meaningOfChange[$subKey] == "Ausfall") {
                    $differencesMessage[] = "";
                } else {
                    $differencesMessage[] = "Vorher: $value; Jetzt: {$array2[$key][$subKey]}";
                }
            }
        }
    }
    
    // Prüfe auch das zweite Array auf zusätzliche Indizes
    foreach ($array2 as $key => $item) {
        if (!isset($array1[$key])) {
            $differencesTitle[] = "Index $key fehlt im ersten Array";
            $differencesMessage[] = "";
        }
    }
    

    $result = array_merge($differencesTitle, $differencesMessage);
    return empty($result) ? "Arrays sind identisch" : $result;
    
}



// Ausgabe der Unterschiede
$compResult = compareArrays($lastRetrieval, $formatedTimetable);

$comResultLen = count($compResult);
$compResultTitle = array_slice($compResult, 0, intval($comResultLen / 2));
$comResultMessage = array_slice($compResult, intval($comResultLen / 2));



for ($i = 0; $i < intval($comResultLen / 2); $i++) {
    $title = $compResultTitle[$i];
    $message = $comResultMessage[$i];
    // sendPushoverNotification($title, $message);
    echo '<pre>'; 
    echo $title;
    echo $message;
}











// $resulut = sendPushoverNotification(
//     "Test Titel", 
//     "Test Message"
// );
    









?>