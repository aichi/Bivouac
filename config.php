<?php
//Basecamp project
define( 'URL', 'https://YOUR_PROJECT.basecamphq.com');
define( 'USER', 'API_KEY');
define( 'PASSWD', 'x');

//PDF styles
define("GENERATE_HEADER", true);
define("HEADER_LOGO", './logo.png');
define("HEADER_SHOW_PROJECT_NAME", true);
define("HEADER_FONT_SIZE", 24);
define("LOGO_HEIGHT", 50);//px
define("LOGO_TITLE_DISTANCE", 30); //px
define("HEADER_FONT", '"Lucida Grande",verdana,arial,helvetica,sans-serif');

define("GENERATE_FOOTER", true);
define("FOOTER_FONT_SIZE", 6);
define("FOOTER_FONT", '"Lucida Grande",verdana,arial,helvetica,sans-serif');

define("FONT", '"Lucida Grande",verdana,arial,helvetica,sans-serif');

//rendering options
define("SHOW_ENGLISH_TIME", true); //English: 1pm, otherwise: 13:00
define("SHORTEN_LAST_NAME", true);  //if true than John Doe become to John D

//timezone in which we render inputed data
define("TIME_ZONE", "Europe/Prague");
?>