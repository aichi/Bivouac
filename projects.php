<?php
header('Content-type: application/json');
require('./lib/basecamp/Basecamp.class.php');
require('./config.php');


$basecamp = new Basecamp(URL, USER, PASSWD);
$resp = $basecamp->getProjects();
try {
    $xml = new SimpleXMLElement($resp["body"]);


    $data = array();
    for ($i = 0; $i < count($xml->project); $i++) {
        $project = $xml->project[$i];
        $data[(string)$project->id[0]] = (string)$project->name[0];
    }
} catch (Exception $e) {
    $data = array();
    $data["0"] = "Connection to server was not successful.";
}
echo json_encode($data);
?>