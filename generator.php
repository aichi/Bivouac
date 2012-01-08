<?php

class CalendarGenerator {

    /*
     * Project id obtained from Basecamp
     * @var int
     */
    protected $projectID;

    /**
     * Project name obtained from Basecamp for current projectID
     * @var string
     */
    protected $projectName = '';

    /**
     * Page number during events paging
     * @var int
     */
    protected $page = 1;

    /**
     * Instance of Basecamp class
     * @var Basecamp
     */
    protected $basecamp = null;

    /**
     * File with table template which is parsed and filled with content
     * @var string
     */
    private $empty_table_filename = 'table_empty.html';

    /**
     * Todays time (int)
     * @var int
     */
    private $today = null;

    /**
     * Time of day in week when rendering of calendar ended (int)
     * @var int
     */
    private $endTime = null;

    /**
     * Monday when calendar rendering starts
     * @var int
     */
    private $start = null;

    /**
     * Sunday when calendar rendering ends
     * @var int
     */
    private $end = null;

    /**
     * User defined input when he wants to start calendar rendering
     * @var null
     */
    protected $from  = null;

    /**
     * User defined input when he wants to end calendar rendering
     * @var null
     */
    protected $to = null;

    /**
     * Array of entries parsed from Bascamp response
     * @var array
     */
    private $data = array();

    /**
     * Array of generated day cells for calendar rendering
     * @var array
     */
    private $dayCells = array();

    /**
     * If true than application/pdf header is not sent
     * @var bool
     */
    protected $showPDF = false;

    /**
     * If true than HTML is returned instead of PDF, this could be true only if $showPDF is true
     * @var bool
     */
    protected $showHTML = false;

    /**
     * Page size.
     * @see CPDF_Adapter::$PAPER_SIZES
     * @var string
     */
    protected $pageSize = 'a4';

    /**
     * Paper orientation, possible values are landscape and portrait
     * @var string
     */
    protected $orientation = 'landscape';


    /**
     * Constructor, argument is project id
     * @param int $projectID
     * @param Basecamp $basecamp
     * @param bool $showPDF if true than don't send application/pdf header
     * @param bool $showHTML if true than show generated HTML instead of PDF
     */
    public function __construct($projectID, Basecamp $basecamp, $showPDF, $showHTML, $pageSize, $orientation) {
        $this->projectID = $projectID;
        $this->basecamp = $basecamp;

        //showHTML could be true only if showPDF is true
        if ($showHTML && !$showPDF)
            $showHTML = false;

        $this->showPDF = $showPDF;
        $this->showHTML = $showHTML;
        $this->pageSize = $pageSize;
        $this->orientation = $orientation;
    }

    /**
     * Main function which you call when you would like to render calendar for one project
     * @param $from
     * @param $to
     * @return void
     */
    public function renderCalendar($from, $to) {
        $this->page = 1;
        $this->data = array();
        $this->from = $from;
        $this->to = $to;

        $this->setupTime();

        $this->data = $this->getCalendarEntries();
        if (count($this->data) == 0) {
            echo "<script>alert('No events in calendar!')</script>";
            exit;
        }
        $this->projectName = $this->getProjectName();
        $this->computeStartingAndEndingDate();
        $this->prepareDayCells();
        $this->fillDateCells();
        $html = $this->renderHTML();

        if ($this->showPDF && $this->showHTML)
            echo $html;
        else
            $this->renderToPDF($html);
}

    /**
     * setup timezone from config file and reset today/endTime properties
     */
    protected function setupTime() {
        date_default_timezone_set(TIME_ZONE);
        $this->today = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
        $this->endTime = $this->today;
    }

    /**
     * Recursively fill array of entries and returns it.
     * Basecamp offer maximally 50 events per page and there is no possibility to get page count, we have to check if there
     * is 50 entries and try next page.
     * @return array
     */
    protected function getCalendarEntries() {
        $entries = $this->basecamp->getCalendarEntriesForProject($this->projectID, $this->page);

        /**
         * Parsing XML response from server and prepare array of events.
         */
        try {
            $xml = new SimpleXMLElement($entries["body"]);
        } catch (Exception $e) {
            echo "<script>alert('Response from Basecamp is not well formed!')</script>";
            exit;
        }

        $ces = 'calendar-entries';
        $ce = 'calendar-entry';
        $sa = 'start-at';
        $da = 'due-at';
        $resp = 'responsible-party-name';

        $children = ($xml->children());
        foreach ($children as $entry) {
            $a = array();
            $a['type'] = (string)$entry->type[0];
            $a['title'] = (string)$entry->title[0];
            $a['responsive'] = (string)$entry->$resp;

            $start = strtotime((string)$entry->$sa);
            $end = strtotime((string)$entry->$da);
            $deadline = strtotime((string)$entry->deadline[0]);
            //some events has deadline instead of due date and some hasn't any due date, so it is needed to normalize it.
            $a['start_at'] = (!empty($start) ? $start : (  $a['type'] === 'Milestone' ? $deadline : $end ));
            $a['due_at'] = $a['type'] === 'Milestone' ? $deadline : $end;
            $a['start'] = date('Y-m-d H:i:s', $a['start_at']);
            $a['due'] = date('Y-m-d H:i:s', $a['due_at']);

            $data[] = $a;
            //Getting date when latest event ends up.
            if ($this->endTime < $a['due_at']) $this->endTime = $a['due_at'];
        }

        if (count($data) == 50) {
            $this->page++;
            $data = array_merge($data,$this->getCalendarEntries());
        }

        return $data;
    }

    /**
     * Getting name of the project
     * @return string
     */
    protected function getProjectName() {
        $project = $this->basecamp->getProject($this->projectID);
        /**
         * Parsing XML response from server and prepare project name.
         */
        try {
            $projectXml = new SimpleXMLElement(($project["body"]));
            $projectName = $projectXml->name;
        } catch (Exception $e) {
            echo "<script>alert('Response from Basecamp is not well formed!')</script>";
            exit;
        }

        return $projectName;
    }

    /**
     * Pre-computing starting and ending date to always start on Monday and end up at Sunday.
     */
    protected function computeStartingAndEndingDate() {
        $from = $this->from;
        $to = $this->to;
        $endTime = $this->endTime;

        //getting monday before official start date to print table nicely
        $startTime = $from ? $from : $this->data[0]['start_at'];
        $offset = date('N', $startTime) -1;
        $start = strtotime("-$offset day", $startTime);
        //getting sunday after official end date to print table nicely
        $endTime = $to ? $to : ($endTime > $startTime ? $endTime : $startTime);
        $offset = 7 - date('N', $endTime);
        $end = strtotime("+$offset day", $endTime);

        //echo 'start date: ' . date('Y-m-d H:i:s N', $start);
        //echo "\n<br>";
        //echo 'end date: ' . date('Y-m-d H:i:s N', $end);
        //exit;

        $this->start = $start;
        $this->end = $end;
    }

    /**
     * preparation of array of dates, we call every day as dayCell, because every day is rendered as cell in table later on.
     */
    protected function prepareDayCells() {
        $this->dayCells = array();
        $actualTime = null; //defined during FOR cycle, starts at 00:00:00
        for (   $i = 0, $actualTime = $this->start;
                $actualTime <= $this->end;
                $i++, $actualTime = strtotime('+'.$i.' day', $this->start))
        {
            $day = array();
            //$actualTime = strtotime('+'.$i.' day', $start);   //start v 00:00:00
            $midnight = strtotime('-1 second',strtotime('+1 day', $actualTime)); //23:59:59

            $day['actualTime'] = $actualTime;
            $day['midnight'] = $midnight;
            $day['dayLabel'] = (date('j', $actualTime) == 1 ? date('M', $actualTime) : '') . ' ' .date('j', $actualTime);
            $day['firstDayInMonth'] = date('j', $actualTime) == 1;
            $day['dayInWeek'] = date('N', $actualTime); //1..7
            $day['oneDayEvents'] = array();
            $day['multiDayEvents'] = array();
            $day['multiDaySpacers'] = array();
            $this->dayCells[] = $day;
        }
    }

    /**
     * Returns how many days overlaps end of current week to next weeks or 0 if event stays in current week
     * @param int $dayInWeek day in week, 1 for Monday, 7 for Sunday
     * @param int $days how many days we have
     * @return int rest days
     */
    protected function countRestDaysInNextWeeks($dayInWeek, $days){
        $restDays = 0;
        $sunday = 8;
        if($days + $dayInWeek > $sunday) {
            $part = $sunday - $dayInWeek;
            $restDays = $days -  $part;
        }
        return $restDays;
    }

    /**
     * Iteration through all data and filling $dayCells array with events which durate one day or more days or spacers for
     * long events for next days
     */
    protected function fillDateCells() {
        $data = $this->data;
        $dayCells = &$this->dayCells;

        foreach ($data as $entry) {
            for($i = 0; $i < count($dayCells); $i++) {
                $dayCell = $dayCells[$i];
                //found some event started at particular date (cell)
                if ($entry['start_at'] >= $dayCell['actualTime'] && $entry['start_at'] <= $dayCell['midnight']) {
                    $event = array();
                    $event['name'] = $entry['title'] . ($entry['type'] == 'Milestone' ? ' - '.$entry['responsive'] : '');
                    $event['entry'] = $entry;

                    //oneday
                    if ($entry['start_at'] == $entry['due_at']) {
                        $dayCells[$i]['oneDayEvents'][] = $event;
                    //multiday
                    } else {
                        //how much is the event shifted from top of cell
                        $linesAbove = count($dayCell['multiDaySpacers']) + count($dayCell['multiDayEvents']);

                        //measuring how long is the event, this way we correctly pass over dates where time is changed
                        //from summer to winter time or otherwise around
                        $tmpEndTime = $entry['start_at'];
                        $days = 0;
                        while ($tmpEndTime <= $entry['due_at']) {
                            $days++;
                            $tmpEndTime = strtotime('+1 day', $tmpEndTime);
                        }

                        //setup event length to MIN(length of rest of the week, length of event)
                        $restDays = $this->countRestDaysInNextWeeks($dayCell['dayInWeek'], $days);
                        $event['days'] = $days - $restDays;
                        $event['start'] = true;
                        $event ['end'] = true;
                        $dayCells[$i]['multiDayEvents'][] = $event;
                        $index = $i;

                        /**
                         * adding spacers for next days, or adding repetitive event to Monday if event is long and touch more
                         * than one week. We are skipping days which are out of rendered dayCells
                         */
                        for ($j = 1; $j < $days && isset($dayCells[$i+$j]); $j++) {
                            $nextDayCell = $dayCells[$i+$j];

                            //we have to render long event again on "next" monday
                            if ($nextDayCell['dayInWeek'] == 1) {
                                //revert previous week 'end' flag for event
                                $dayCells[$index]['multiDayEvents'][count($dayCells[$index]['multiDayEvents']) -1]['end'] = false;
                                $index = $i+$j;

                                $restDaysTmp = $this->countRestDaysInNextWeeks(1, $restDays);
                                $event['cont'] = true;
                                $event['start'] = false;
                                $event['days'] = $restDays - $restDaysTmp;
                                $restDays = $restDaysTmp;
                                $dayCells[$i+$j]['multiDayEvents'][] = $event;
                            //spacer
                            } else {
                                /**
                                 * if event above is 2 days long and curent event is three days long and boths are starting at
                                 * some date, it is needed to add one more spacer to the last day to shift all content down
                                 * under the event line
                                 */
                                $nextDayLinesAbove = count($nextDayCell['multiDaySpacers']) + count($nextDayCell['multiDayEvents']);
                                $missingSpace = $linesAbove - $nextDayLinesAbove;
                                if ($missingSpace > 0) {
                                    while ($missingSpace--) {
                                        $dayCells[$i+$j]['multiDaySpacers'][] = array('original_entry' => null);
                                    }
                                }

                                //adding spacer which hold space for current event
                                $spacer = array();
                                $spacer['original_entry'] = $entry;
                                $dayCells[$i+$j]['multiDaySpacers'][] = $spacer;
                            }
                        }
                    }
                }
            }
        }
    }

    //TODO: fix when event is begining before our render plan but ends up in the plan - valid for multiday events only
    //TODO: improve rendering long events to start/end in days which are get by user and not on Monday/Sunday
    //TODO: both is possible to done by modyfing start/due date of events during parsing XML (isn't it possible to pass this dates to server?)


    /**
     * Generating HTML table from data
     */
    protected function renderHTML() {
        $dayCells = $this->dayCells;
        $table = file_get_contents($this->empty_table_filename);
        $tbody = '';

        $now = time();
        for ($i = 0; $i < count($dayCells); $i++) {
            if ($dayCells[$i]['dayInWeek'] == 1) {
                $tbody .= '<tr>';
            }

            $tbody .= '<td class="'. ($now > $dayCells[$i]['actualTime'] && $now < $dayCells[$i]['midnight'] ?
                                      'today' : (in_array($dayCells[$i]['dayInWeek'], array(6,7)) ? 'weekend' : '')) .'">';


            $tbody .= '<div class="dateLabel '.($dayCells[$i]['firstDayInMonth'] == 1 ? 'bold' : '').'">'.$dayCells[$i]['dayLabel'].'</div>';

            for ($j = 0; $j < count($dayCells[$i]['multiDaySpacers']); $j++) {
                $tbody .= '<div class="multiDayEventSpacer"></div>';
            }

            for ($k = 0; $k < count($dayCells[$i]['multiDayEvents']); $k++) {
                $event = $dayCells[$i]['multiDayEvents'][$k];
                $width = $event['days'] * 100;
                $tbody .= '<div class="multiDayEvent '.
                            ($event['start'] ? "eventStart" : '').' '.
                            ($event['end'] ? "eventEnd" : '').
                            '" style="width: '.$width.'%;"><div class="event">'.$event['name'].'</div></div>';
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

        $table = $this->prepareHeaderStyle($table, $tbody);
        return $table;
    }



    /**
     * Generating Header and stylesheet for header
     */
    protected function prepareHeaderStyle($table, $tbody) {
        $style = "";
        if (GENERATE_HEADER) {
            $font = defined('HEADER_FONT') ? HEADER_FONT : "verdana";
            $color = "black";
            $size = defined('HEADER_FONT_SIZE') ? (int)HEADER_FONT_SIZE : 24;

            $imgy = 0;//default value if there is no logo
            $max_image_size = LOGO_HEIGHT;
            if (defined('HEADER_LOGO') && is_file(HEADER_LOGO)) {
                $imginfo = getimagesize(HEADER_LOGO);   // 0=x, 1=y
                if ($imginfo[0] > 0 && $imginfo[1] > 0) {
                    $ratio = $imginfo[0] / $imginfo[1];
                    if ($ratio > 0) {
                        $imgx = $max_image_size;
                        $imgy = $max_image_size / $ratio;
                    } else {
                        $imgy = $max_image_size;
                        $imgx = $max_image_size / $ratio;
                    }

                    $style .= "#header_image {width: ".$imgx."px; height: ".$imgy."px;}";
                    $table = str_replace('$$$IMG_SRC$$$', HEADER_LOGO, $table);
                }
            } else {
                $style .= "#header_image {display: none}";
            }
            if (HEADER_SHOW_PROJECT_NAME) {
                $style .= "#header_text {font-family: ".$font."; font-size:".$size."px; color:".$color."; margin-left: ".LOGO_TITLE_DISTANCE."px;}";
                $style .= "#mainTable {margin-top: ".(($size > $imgy ? $size : $imgy)+10)."px;}";
                $table = str_replace('$$$HEADER_TEXT$$$', $this->projectName, $table);

            } else {
                $style .= "#header_text {display: none}";
            }
        } else {
            $style .= "#header {display: none}";
        }

        $table = str_replace('$$$TABLE_BODY$$$', $tbody, $table);
        $table = str_replace('$$$GENERATED_STYLE$$$', $style, $table);
        $table = str_replace('$$$GLOBAL_FONT$$$', FONT, $table);

        return $table;
    }

    /**
     * Method which return pdf to output
     */
    public function renderToPDF($htmlInput) {
        /**
         * Technically it is possible to output also using mpdf, but it doesn't support position absolute/ but support CSS3 :)
         */
        //header("Content-type: application/pdf");
        //require('./lib/mpdf/mpdf.php');
        //$mpdf = new mPDF("s", "A4-L");
        //$mpdf->SetDisplayMode('fullpage');
        //$mpdf->WriteHTML($htmlInput);
        ////$mpdf->WriteHTML(file_get_contents('table2.html'));
        //$mpdf->Output();

        /**
         * PDF Renderer.
         */
        $dompdf = new DOMPDF();
        //$dompdf->load_html(file_get_contents('table2.html'));
        $dompdf->load_html($htmlInput);
        $dompdf->set_paper($this->pageSize, $this->orientation);
        $dompdf->render();
        $dompdf->stream("calendar.pdf", array("Attachment" => !$this->showPDF)); //true means download
    }

}