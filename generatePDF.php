<?php
require("./config.php");
require('./lib/basecamp/Basecamp.class.php');
require('./lib/dompdf/dompdf_config.inc.php');
require('./generator.php');

;

// Getting variables from request
$projectID = (int)$_GET['project'];
$from = strtotime($_GET['from']);
$to = strtotime($_GET['to']);
$showPDF = (bool)$_GET['showPDF'];
$showHTML = (bool)$_GET['showHTML'];
$pageSize = array_key_exists($_GET['pageSize'], CPDF_Adapter::$PAPER_SIZES) ? $_GET['pageSize'] : 'a4';
$orientation = $_GET['orientation'] == 'landscape' ? 'landscape' : 'portrait';


if (empty($projectID) || $projectID == 0) {
    die("Project ID is wrong");
}

$basecamp = new Basecamp(URL, USER, PASSWD);

$generator = new CalendarGenerator($projectID, $basecamp, $showPDF, $showHTML, $pageSize, $orientation);
$generator->renderCalendar($from, $to);

