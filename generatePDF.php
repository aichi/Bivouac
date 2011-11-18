<?php
$projectID = (int)$_GET['project'];

if (empty($projectID) || $projectID == 0) {
    die("Project ID is wrong");
}

require('./lib/basecamp/Basecamp.class.php');
require('./credentials.php');
//$basecamp = new Basecamp(URL, USER, PASSWD);    //$projectID = 7321770;
//$entries = $basecamp->getCalendarEntriesForProject($projectID);

//file_put_contents('entries.txt', serialize($entries));
//exit;
$entries = unserialize(file_get_contents('entries.txt'));

//print_r($entries);

$xml = new SimpleXMLElement($entries["body"]);
$data = array();
$ces = 'calendar-entries';
$ce = 'calendar-entry';
$sa = 'start-at';
$da = 'due-at';
$resp = 'responsible-party-name';
date_default_timezone_set('Europe/Prague');
$today = mktime(0, 0, 0, date('n'), date('j'), date('Y'));

$children = ($xml->children());
foreach ($children as $entry) {
    $a = array();
    $a['type'] = (string)$entry->type[0];
    $a['title'] = (string)$entry->title[0];
    $a['responsive'] = (string)$entry->$resp;

    $start = strtotime((string)$entry->$sa);
    $end = strtotime((string)$entry->$da);
    $deadline = strtotime((string)$entry->deadline[0]);

    $a['start_at'] = (!empty($start) ? $start : (  $a['type'] === 'Milestone' ? $deadline : $end ));
    $a['due_at'] = $a['type'] === 'Milestone' ? $deadline : $end;
    $a['start'] = date('Y-m-d H:i:s', $a['start_at']);
    $a['due'] = date('Y-m-d H:i:s', $a['due_at']);

    //if ($a['due_at'] >= $today) {
        $data[] = $a;
    //}
}

$a = array(
    'type' => 'Milestone',
    'title' => 'Super dlouhej milestone',
    'start' => '2011-11-21',
    'due' => '2011-11-29',
    'responsive' => 'Michal Aichinger'
);
$a['start_at'] = strtotime($a['start']);
$a['due_at'] = strtotime($a['due']);
$data[] = $a;

//print_r($data);

$startTime = $data[0]['start_at'];
$offset = date('N', $data[0]['start_at']) -1;
//pondeli
$start = strtotime("-$offset day", $startTime);
//echo 'start date: ' . date('Y-m-d H:i:s N', $start);
//echo "\n<br>";

$dayCells = array();
for ($i = 0; $i < 63; $i++) {
    $day = array();
    $actualTime = strtotime('+'.$i.' day', $start);   //start v 00:00:00
    $midnight = strtotime('-1 second',strtotime('+1 day', $actualTime)); //23:59:59

    $day['actualTime'] = $actualTime;
    $day['midnight'] = $midnight;
    $day['dayLabel'] = (date('j', $actualTime) == 1 ? date('M', $actualTime) : '') . ' ' .date('j', $actualTime);
    $day['fistDayInMonth'] = date('j', $actualTime) == 1;
    $day['dayInWeek'] = date('N', $actualTime); //1..7
    $day['oneDayEvents'] = array();
    $day['multiDayEvents'] = array();
    $day['multiDaySpacers'] = array();
    $dayCells[] = $day;
}

foreach ($data as $entry) {
    for($i = 0; $i < count($dayCells); $i++) {
        $dayCell = $dayCells[$i];
        //nasli jsme zaznam zacinajici na konkretni bunce
        if ($entry['start_at'] >= $dayCell['actualTime'] && $entry['start_at'] <= $dayCell['midnight']) {
            $event = array();
            $event['name'] = $entry['title'] . ($entry['type'] == 'Milestone' ? ' - '.$entry['responsive'] : '');
            $event['entry'] = $entry;

            //oneday
            if ($entry['start_at'] == $entry['due_at']) {
                $dayCells[$i]['oneDayEvents'][] = $event;
            //multiday
            } else {
                $tmpEndTime = $entry['start_at'];
                $days = 0;
                while ($tmpEndTime <= $entry['due_at']) {
                    $days++;
                    $tmpEndTime = strtotime('+1 day', $tmpEndTime);
                }
                $event['days'] = $days;
                $dayCells[$i]['multiDayEvents'][] = $event;

                //pridani spaceru do nasledujicich dnu
                for ($j = 1; $j < $days; $j++) {
                    //v pondeli musim tu eventu znovu vyrenderovat
                    if ($dayCells[$i+$j]['dayInWeek'] == 1) {                      //nutno zkradit days u predchozi a teto dat zbytek
                        $event['cont'] = true;
                        $dayCells[$i+$j]['multiDayEvents'][] = $event;
                    //spacer
                    } else {                                                        //stale vylepsit pokud horni event ma dva dny a spodni tri a zacinaji stejne, tak v posledni den delsiho eventu potrebujeme 2 spacery, ne jeden, mozna staci kontrolovat pocet spaceru aby jich bylo tolik jako v pocatecni den i po vsechny nasledujici.
                        $spacer = array();
                        $spacer['original_entry'] = $entry;
                        $dayCells[$i+$j]['multiDaySpacers'][] = $spacer;
                    }
                }
            }
        }
    }
}


$table = file_get_contents('table_empty.html');
$tbody = '';

$now = time();
for ($i = 0; $i < count($dayCells); $i++) {
    if ($dayCells[$i]['dayInWeek'] == 1) {
        $tbody .= '<tr>';
    }

    $tbody .= '<td class="'. ($now > $dayCells[$i]['actualTime'] && $now < $dayCells[$i]['midnight'] ?
                              'today' : (in_array($dayCells[$i]['dayInWeek'], array(6,7)) ? 'weekend' : '')) .'">';


    $tbody .= '<div class="dateLabel '.($dayCells[$i]['fistDayInMonth'] == 1 ? 'bold' : '').'">'.$dayCells[$i]['dayLabel'].'</div>';

    for ($j = 0; $j < count($dayCells[$i]['multiDaySpacers']); $j++) {
        $tbody .= '<div class="multiDayEventSpacer"></div>';
    }

    for ($k = 0; $k < count($dayCells[$i]['multiDayEvents']); $k++) {
        $event = $dayCells[$i]['multiDayEvents'][$k];
        $width = $event['days'] * 100;
        $tbody .= '<div class="multiDayEvent eventStart eventEnd" style="width: '.$width.'%;"><div class="event">'.$event['name'].'</div></div>';
    }

    for ($m = 0; $m < count($dayCells[$i]['oneDayEvents']); $m++) {
        $event = $dayCells[$i]['oneDayEvents'][$m];
        $tbody .= '<div class="event oneDayEvent"><ul><li>'.$event['name'].'</li></ul></div>';
    }

    $tbody .= '<div class="'.($j + $k + $m == 0 ? 'empty' : 'tinySpacer').'"></div>';

    $tbody .= "</td>\n";

    if ($dayCells[$i]['dayInWeek'] == 7) {
        $tbody .= "</tr>\n";
    }
}

$table = str_replace('$$$REPLACE$$$', $tbody, $table);

//echo $table;
//exit;


//header("Content-type: application/pdf");
//require('./lib/mpdf/mpdf.php');
//$mpdf = new mPDF("s", "A4-L");
//$mpdf->SetDisplayMode('fullpage');
//$mpdf->WriteHTML($table);
////$mpdf->WriteHTML(file_get_contents('table2.html'));
//$mpdf->Output();



require('./lib/dompdf/dompdf_config.inc.php');
$dompdf = new DOMPDF();
//$dompdf->load_html(file_get_contents('table2.html'));
$dompdf->load_html($table);
$dompdf->set_paper('a4', 'landscape');//portrait - landscape
$dompdf->render();
$dompdf->stream("calendar.pdf", array("Attachment" => false)); //true means download

?>