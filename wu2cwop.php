<?php
#
# WU2CWOP - WU to CWOP Packet Injector
#
# This utility is designed to read weather station dashboard data from
# WeatherUnderground, massage it into a format understandable by CWOP,
# and upload it. 
# 
# Usage: ./wu2cwop [options]
#
# options:
#  -c | --cwop-id | --cwop_id | --cwopid station-id
#  -p | --pws-id | --pws_id | --pwsid station-id
# [-d | --no-dups]
# [-e | --errors who@email.addy]
# [-f | --force]
# [-h | --help]
# [-l | --latlong | --lat_long latlong-string]
# [     --lat latitude]
# [     --lng longitude]
# [-m | --comment comment-string]
# [-n | --no-wind]
# [-o | --offset baro_offset_in_millibars]
# [-w | --work | --workdir work-directory]
# [-r | --no-rain]
# [-s | --save]
# [-t | --packet]
# [-v | --verbose]
# [-x | --xml]
#
# See README.md for detailed description of options and usage.
#

#
# For users uploading from a single site, these values can be hard-coded in 
# the script below
#
# Run this script from CRON periodically, depending on how often your PWS
# uploads data to WU.
#

#
# Defaults... to be used when you run the script with no command-line
# parameters. If you use this script to inject packets for a single site
# only, you can set them here.
#
$email      = '';               /* SEND ERRORS TO THIS EMAIL ADDY */
$cwop_id    = 'KH6HZ-W1';	/* CWOP ID */
$pws_id     = 'KMAREHOB22';	/* WEATHER UNDERGROUND ID */
$offset     = 0;		/* BARO OFFSET IN MILLIBARS */
$lat_long   = ''; 		/* LAT/LONG STRING */
$lat        = 999;              /* LATITUDE */
$lng        = 999;              /* LONGITUDE */
$verbose    = false;		/* BE VERBOSE TO STDOUT */
$log_data   = true;	       	/* APPEND OBSERVATION TO LOG FILE */
$log_packet = true;	       	/* APPEND OBSERVATION TO LOG FILE */
$force      = false;            /* FORCE PACKET (IGNORE TIMESTAMP) */
$workdir    = '/tmp';          	/* WORK DIRECTORY FOR LOG/TIMESTAMP FILES */
$comment    = '';		/* COMMENT STRING */
$xml        = false;            /* USE XML FILE */
$wind       = true;             /* SEND WIND DATA */
$rain       = true;             /* SEND RAIN DATA */
$dups       = true;             /* SEND DUPLICATE DATA */

###############################################################################
#
# Functions
#
###############################################################################

function getOS() {
   switch (strtoupper(PHP_OS)) {
      case 'DARWIN':
         return 'm';
      case 'FREEBSD':
      case 'HP-UX':
      case 'IRIX64':
      case 'LINUX':
      case 'NETBSD':
      case 'OPENBSD':
      case 'SUNOS':
      case 'UNIX':
         return 'x';
      case 'CYGWIN_NT-5.1':
      case 'WIN32':
      case 'WINNT':
      case 'WINDOWS':
         return 'w';
      default:
         return '?';
  }
    }

function usage() {
}

function out($msg) {
   global $verbose;
   if ( $verbose ) {
      echo $msg;
   }
}

function MailAndDie($msg) {
   global $email;
   if ( $email != '' ) {
      mail($email, "WU2CWOP Error", $msg);
   }
   die($msg);
}

function build_latlong($lat, $lng) {
   $ns     = 'N';
   $ew     = 'E';
   if ( $lat < 0 ) {
      $ns  = 'S';
      $lat = abs($lat);
   }
   $latdd  = (int)$lat;
   $latmm  = 60*($lat-$latdd);
   if ( $lng < 0 ) {
      $ew  = 'W';
      $lng = abs($lng);
   }
   $londd  = (int)$lng;
   $lng    = 60*($lng-$londd);
   $lonmm  = $lng;
   return sprintf('%02d%02.2f%s/%03d%02.2f%s',
                  $latdd,
                  floor($latmm*100)/100,
                  $ns,
                  $londd,
                  floor($lonmm*100)/100,
                  $ew);
}


###############################################################################
#
# Main Program Execution Begins Here
#
###############################################################################
#
# Process any command-line options, overriding default values
#
$options = getopt('c:de:fhm:no:p:rstvw:x', 
                  array('comment:', 
                        'cwop_id:', 
                        'cwop-id:', 
                        'cwopid:', 
                        'errors:',
                        'email:',
                        'e-mail:',
                        'pws_id:', 
                        'pws-id:', 
                        'pwsid:', 
                        'offset:', 
                        'lat:',
                        'latitude:',
                        'lng:',
                        'lon:',
                        'long:',
                        'longitude:',
                        'latlong:', 
                        'lat_long:', 
                        'work:', 
                        'workdir:', 
                        'verbose', 
                        'force', 
                        'help',
                        'nowind',
                        'no-wind',
                        'no_wind',
                        'norain',
                        'no-rain',
                        'no_rain',
                        'no-dups',
                        'no_dups',
                        'nodups',
                        'save',
                        'packet',
                        'xml'));

foreach ($options as $k => $v) {
   switch($k) {
      case 'c':
      case 'cwop-id':
      case 'cwop_id':
      case 'cwopid':
         $cwop_id = $v;
         break;
      case 'd':
      case 'no-dups':
      case 'no_dups':
      case 'nodups':
         $dups = false;
         break;
      case 'e':
      case 'errors':
      case 'email':
      case 'e-mail':
         $email = $v;
         break;
      case 'f':
      case 'force':
         $force = true;
         break;
      case 'h':
      case 'help':
         usage();
         die();
      case 'lat':
      case 'latitude':
         if ( is_numeric($v) ) {
            $lat = (float)$v;
            if (abs($lat) > 90 ) {
               $lat = 999;
               echo 'WARN: Invalid latitude specified.';
            }
         }
         break;
      case 'lng':
      case 'lon':
      case 'long':
      case 'longitude':
         if ( is_numeric($v) ) {
            $lng = (float)$v;
            if ( abs($lng) > 180 ) {
               $lng = 999;
               echo 'WARN: Invalid longitude specified.';
            }
         }
         break;
      case 'l':
      case 'latlong':
         $lat_long = $v;
         break;
      case 'm':
      case 'comment':
         $comment = $v;
         break;
      case 'n':
      case 'nowind':
      case 'no-wind':
      case 'no_wind':
         $wind = false;
         break;
      case 'o':
      case 'offset':
         $offset = (int)$v;
         break;
      case 'p':
      case 'pws_id':
      case 'pws-id':
      case 'pwsid':
         $pws_id = $v;
         break;
      case 'r':
      case 'norain':
      case 'no-rain':
      case 'no_rain':
         $rain = false;
         break;
      case 's':
      case 'save':
         $log_data = true;
         break;
      case 't':
      case 'packet':
         $log_packet = true;
         break;
      case 'v':
      case 'verbose':
         $verbose = true;
         break;
      case 'w':
      case 'workdir':
      case 'work':
         $workdir = $v;
      case 'x':
      case 'xml':
         $xml = true;
   }
}

#
# Consistency checks... make sure we have a cwop and wu id. all other
# parameters are optional
#
if ( $cwop_id == '' ) {
   $msg = 'ERROR: CWOP station id not set. Use -c stationid !' . PHP_EOL;
   out($msg);
   MailAndDie($msg);
}
if ( $pws_id == '' ) {
   $msg = 'ERROR: WU station id not set. Use -p stationid !' . PHP_EOL;
   out($msg);
   MailAndDie($msg);
}

if ( $xml )
{
   # This link seems to be inconsistent with the data it returns...
   # it is here as an option but the default is to scrape data off the
   # dashboard instead. Use the -x|--xml option to retrieve data from this
   # source instead.
   #
   $url = 'http://api.wunderground.com/weatherstation/WXCurrentObXML.asp?format=XML&ID=';
   $data       = simplexml_load_file($url . $pws_id); 
   $pretty     = $data->observation_time_rfc822;
   $odate      = date('U', strtotime($pretty));
   $wind_dir   = (int)$data->wind_degrees;
   $wind_speed = (int)$data->wind_mph;
   $wind_gust  = (int)$data->wind_gust_mph;
   $temp       = (int)$data->temp_f;
   $relH       = (int)$data->relative_humidity;
   $baro       = (float)$data->pressure_mb - $offset;
   $rainh      = (float)$data->precip_1hr_in;
   $rainp      = (float)$data->precip_today_in;
   if ( $lat > 90 ) {
      $lat     = (float)$data->location->latitude;
   }
   if ( $lng > 180 ) {
      $lng     = (float)$data->location->longitude;
   }
}
else {
   #
   # Retrieve the "dashboard" for a WU station, and extract the json object
   # which contains the station's current observation. This requires a bit of
   # string manipulation to find the data...
   #
   $url = 'https://www.wunderground.com/personal-weather-station/dashboard?ID=';
   $buffer = file_get_contents($url . $pws_id );

   $i = strpos($buffer, '"current_observation":');
   if ($i < 1) {
     $msg = 'ERROR: Cannot find start string!' . PHP_EOL . $buffer;
     out($msg);
     MailAndDie($msg);
   }
   $buffer=substr($buffer, $i + 23);

   $i = strpos($buffer, '"astronomy"');
   if ($i < 1 ) {
     $msg = 'ERROR: cannot find end string!' . PHP_EOL . $buffer;
     out($msg);
     MailAndDie($msg);
   }
   $buffer     = substr($buffer, 0, $i);
   $buffer     = substr($buffer, 0, strrpos($buffer, '}')+1);
   $data       = json_decode($buffer);
   $odate      = $data->{'date'}->{'epoch'};
   $pretty     = $data->{'date'}->{'pretty'};
   $wind_dir   = (int)$data->{'wind_dir_degrees'};
   $wind_speed = (int)$data->{'wind_speed'};
   $wind_gust  = (int)$data->{'wind_gust_speed'};
   $temp       = (int)$data->{'temperature'};
   $relH       = (int)$data->{'humidity'};
   $baro       = ((float)$data->{'pressure'} * 33.86389) - $offset;
   $rainh      = (float)$data->{'precip_1hr'};
   $rainp      = (float)$data->{'precip_today'};
   if ( $lat > 90 ) {
      $lat     = (float)$data->{'station'}->{'latitude'};
   }
   if ( $lng > 180 ) {
      $lng     = (float)$data->{'station'}->{'longitude'};
   }
}

# check to see if the timestamp is greater on the weatherunderground data
# than the timestamp we have stored locally. If it is, then we can proceed
# (unless --force is specified). If not, exit so we are not injecting 
# packets with the same WU timestamp into the cwop network
#
$ts = $workdir . '/' . $pws_id . '.ts';
if (!$force) {
   if (file_exists($ts)) {
      $fdate = file_get_contents($ts, 255);
      if ((int)$odate <= (int)$fdate) {
         out('No new observation data recorded on WU since ' . $pretty .
             PHP_EOL . '(ts: ' . $odate . '<=' . $fdate . ')' . PHP_EOL);
         die();
      }
   }
}


#
# save new observation time as we have a new packet to send to CWOP
#
out('New observation: ' . $pretty . ' (' . $odate . ')' . PHP_EOL);
file_put_contents($ts, $odate);


#
# build lat/long of weather station from the weatherunderground data if 
# the value is not specifically supplied in defaults and/or command-line 
# options
#
if ( $lat_long == '' ) {
   $lat_long = build_latlong($lat, $lng);
}

#
# First 4 parameters sent to CWOP are required, or they must contain '...'
# These include wind direction, speed, gust, and temperature.
#
if (!$wind ) {
   $wind_dir   = '_...';
   $wind_speed = '/...';
   $wind_gust  = 'g...';
}
else {
   #
   # Wind direction in degrees
   #
   if ( $wind_dir < 0 ) {
      $wind_dir = 0;
   }
   out('wind: dir: ' . $wind_dir . '° ');
   $wind_dir = sprintf('_%03d', $wind_dir);
   #
   # Wind speed in MPH
   #
   if ( $wind_speed < 0 ) {
      $wind_speed = 0;
   }
   out('speed: ' . $wind_speed . 'mph ');
   $wind_speed = sprintf('/%03d', $wind_speed);
   #
   # Wind gust speed in MPH
   #
   if ( $wind_gust < 0 ) {
      $wind_gust = 0;
   }
   out('gust: ' . $wind_gust . 'mph' . PHP_EOL);
   $wind_gust = sprintf('g%03d', $wind_gust);
}

#
# Temperature in degrees F
#
out('temp: ' . $temp . '°f ');
$temp = sprintf('t%03d', $temp);

#
# Relative humidity. 
# Note: change humidity field to 0 if 100%
#
out('hum: ' . $relH . '% ');
if ($relH == 100) {
   $relH = 'h00';
}
else {
   $relH = sprintf("h%02d", $relH);
}

#
# Barometric pressure in millibars.
# Adjust for elevation and shift decimal for upload
#
out('baro: ' . $baro . 'mb' . PHP_EOL);
$baro = sprintf("b%05d", $baro*10);

if (!$rain) {
   $rainh = '';
   $rainp = '';
}
else {
   #
   # get hourly precip, shift 2 decimal places to upload
   #
   out('rain: 1hr: ' . $rainh . 'in ');
   if ($rainh < 0) {
      $rainh = 'r000';
   }
   else {
      $rainh = sprintf("r%03d", $rainh*100);
   }
   #
   # get daily precip since midnight
   #
   out('today: ' . $rainp . 'in' . PHP_EOL);
   if ($rainp < 0) {
      $rainp = 'P000';
   }
   else {
      $rainp = sprintf("P%03d", $rainp*100);
   }
}

#
#
#
$obs = $wind_dir . $wind_speed . $wind_gust . $temp . $rainh . $rainp . $relH . $baro;
if (! $dups ) {
   $of = $workdir . '/' . $cwop_id . '.obs';
   if (file_exists($of)) {
      $obsf = file_get_contents($of, 255);
      if ($obs == $obsf ) {
         out('Observation data unchanged on WU since ' . $pretty .
             PHP_EOL . '(ts: ' . $odate . '<=' . $fdate . ')' . PHP_EOL);
         die();
      }
   }
   file_put_contents($of, $obs);
}

#
# build cwop packet string
#
if ( $comment == '') {
   $comment = 'wu2cwop WeatherUnderground (' . 
              $pws_id . 
              ') to CWOP Packet Injector';
}

$packet = $cwop_id . '>APRS,TCPIP*:@' . gmdate("dHi", $odate) . 'z' . $lat_long . $obs . getOS() . 'W2C ' . $comment;

out($packet . PHP_EOL);

#
# Send packet
#
$socket = fsockopen('cwop.aprs.net',  14580, $sk_err, $sk_msg, 30);
if ($socket) {
   $packet = 'user ' . $cwop_id . ' pass -1 vers wu2cwop' . "\r" . $packet . "\r";
   $i = fwrite($socket, $packet);
   fclose($socket);
   if ($i != strlen($packet)) {
      echo 'ERROR: ' . $i . ' of ' . strlen($packet) . ' bytes written.';
   }
}
else {
   echo 'ERROR: ' . $sk_msg . ' (' . $sk_err . ') on socket open' . PHP_EOL;
}

#
# save the packet if requested
#
if ( $log_packet ) {
   file_put_contents($workdir . '/' . $cwop_id . '.pkt', 
                     $packet . PHP_EOL, 
                     FILE_APPEND);
}

#
# save the observation data if requested
#
if ( $log_data ) {
   file_put_contents($workdir . '/' . $cwop_id . '.log', 
            sprintf('%s %s %s %s %s %s %s %s %s', 
                    gmdate("dHi",time()) . 'z',
                    $wind_dir,
                    $wind_speed,
                    $wind_gust, 
                    $temp,
                    $rainh,
                    $rainp,
                    $relH, 
                    $baro ) . PHP_EOL, FILE_APPEND);
}
?>
