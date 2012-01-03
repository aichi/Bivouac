<?php
// Getting variables from request
$projectID = (int)$_GET['project'];
$from = strtotime($_GET['from']);
$to = strtotime($_GET['to']);
$showPDF = (bool)$_GET['showPDF'];
$showHTML = (bool)$_GET['showHTML'];


if (empty($projectID) || $projectID == 0) {
    die("Project ID is wrong");
}

require("./config.php");
require('./lib/basecamp/Basecamp.class.php');
require('./generator.php');
$basecamp = new Basecamp(URL, USER, PASSWD);

$generator = new CalendarGenerator($projectID, $basecamp, $showPDF, $showHTML);
$generator->renderCalendar($from, $to);

