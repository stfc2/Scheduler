<?php
/*    
	This file is part of STFC.
	Copyright 2006-2007 by Michael Krauss (info@stfc2.de) and Tobias Gafner
		
	STFC is based on STGC,
	Copyright 2003-2007 by Florian Brede (florian_brede@hotmail.com) and Philipp Schmidt
	
    STFC is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    STFC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// ########################################################################################
// ########################################################################################
// Startup Konfig

error_reporting(E_ALL);
ini_set('memory_limit', '600M');

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'Der Scheduler kann nur per CLI aufgerufen werden!'; exit;
}

define('TICK_LOG_FILE', '|script_dir|/game/logs/moves_tick_'.date('d-m-Y', time()).'.log');
define('IN_SCHEDULER', true); // wir sind im scheduler...


// ########################################################################################
// ########################################################################################
// App-Klasse (im Moment nur Logging/Timing)

class scheduler {
    var $start_values = array();

    function log($message) {
        $fp = fopen(TICK_LOG_FILE, 'a');
        fwrite($fp, $message."\n");
        echo str_replace('\n','<br>',$message.'\n');
        fclose($fp);
    }

    function start_job($name) {
        global $db;

        $this->log('<font color=#0000ff>Starting <b>'.$name.'</b>...</font>');

        $this->start_values[$name] = array( time() + microtime() , $db->i_query );
    }

    function finish_job($name) {
        global $db;

        $this->log('<font color=#0000ff>Executed <b>'.$name.'</b> (</font><font color=#ff0000>queries: '.($db->i_query - $this->start_values[$name][1]).'</font><font color=#0000ff>) in </font><font color=#009900>'.round( (time() + microtime()) - $this->start_values[$name][0] , 4).' secs</font><br>');

    }
}


// ########################################################################################
// ########################################################################################
// andere Funktionen

function RetrieveRealCatId($id,$user_id)
{
global $db,$sdl;
$player=$db->queryrow('SELECT user_race FROM user WHERE user_id="'.$user_id.'"');
$catquery=$db->query('SELECT id FROM ship_ccategory WHERE race="'.$player['user_race'].'"');
$cat_id=1;
while (($cat=$db->fetchrow($catquery))==true)
{
if ($cat['id']==$id) return $cat_id;
$cat_id++;
}
return -1;
}






function GetBuildingTimeTicks($building,$planet,$user_race)
{
global $db;
global $RACE_DATA, $BUILDING_NAME, $BUILDING_DATA, $MAX_BUILDING_LVL,$NEXT_TICK,$ACTUAL_TICK,$PLANETS_DATA;

$time=($BUILDING_DATA[$building][3] + 3*pow($planet['building_'.($building+1)],$BUILDING_DATA[$building][4]));
if ($building==9)
	$time=$BUILDING_DATA[$building][3];
$time*=$RACE_DATA[$user_race][1];
$time/=100;
$time*=(100-2*($planet['research_4']*$RACE_DATA[$user_race][20]));
$time*=$PLANETS_DATA[$planet['planet_type']][5];
$time=round($time,0);

return $time;
}



function UnitTimeTicksScheduler($unit,$planet_research,$user_race)
{
global $RACE_DATA, $UNIT_NAME, $UNIT_DATA, $MAX_BUILDING_LVL,$NEXT_TICK,$ACTUAL_TICK;
if ($unit<0 || $unit>5) return 0;

$time=$UNIT_DATA[$unit][4];
$time*=$RACE_DATA[$user_race][2];
$time/=100;
$time*=(100-2*($planet_research*$RACE_DATA[$user_race][20]));

if ($time<1) $time=1;
$time=round($time,0);
return $time;
}



function ResourcesPerTickMetal($planet) {
	$rid=0;
    global $RACE_DATA,$PLANETS_DATA, $addres;
    $result = 0.25*(3*((pow(((3*$PLANETS_DATA[$planet['planet_type']][$rid])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
	if($result < 10) $round = 1;
    else $round = 0;
    return round($result, $round);
}

function ResourcesPerTickMineral($planet) {
	$rid=1;
    global $RACE_DATA,$PLANETS_DATA, $addres;
    $result = 0.25*(3*((pow(((3*$PLANETS_DATA[$planet['planet_type']][$rid])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
    if($result < 10) $round = 1;
    else $round = 0;
    return round($result, $round);
}

function ResourcesPerTickLatinum($planet) {
	$rid=2;
    global $RACE_DATA,$PLANETS_DATA, $addres;
    $result = 0.2*(3*((pow(((3*$PLANETS_DATA[$planet['planet_type']][$rid])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
    if($result < 10) $round = 1;
    else $round = 0;
    return round($result, $round);
}



function UnitPrice($unit,$resource, $race)
{
global $db;
global $RACE_DATA, $UNIT_NAME, $UNIT_DATA, $MAX_BUILDING_LVL,$NEXT_TICK,$ACTUAL_TICK;
if ($unit<0 || $unit>5) return 0;

$price = $UNIT_DATA[$unit][$resource];
$price*= $RACE_DATA[$race][6];
return round($price,0);
}


function GetTaxes($percent,$alliance_id)
{
global $db;

if ($percent<=0) return (array(0,0,0));
$res=$db->queryrow('SELECT SUM(p.add_1/100*'.$percent.') AS r1,SUM(p.add_2/100*'.$percent.') AS r2,SUM(p.add_3/100*'.$percent.') AS r3 FROM user u LEFT join planets p ON p.planet_owner=u.user_id WHERE (u.user_alliance="'.$alliance_id.'") AND (u.user_active=1)');
$res[0]=round($res['r1']);
$res[1]=round($res['r2']);
$res[2]=round($res['r3']);
return $res;
}


// ########################################################################################
// ########################################################################################
// Init

$starttime = ( microtime() + time() );

include('|script_dir|/game/include/sql.php');
include('|script_dir|/game/include/global.php');
include('|script_dir|/game/include/functions.php');
include('|script_dir|/game/include/text_races.php');
include('|script_dir|/game/include/race_data.php');
include('|script_dir|/game/include/ship_data.php');
include('|script_dir|/game/include/libs/moves.php');

$sdl = new scheduler();
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

$game = new game();

$sdl->log("\n\n\n".'<b>-------------------------------------------------------------</b>'."\n".
          '<b>Starting Scheduler at '.date('d.m.y H:i:s', time()).'</b>');
if(($cfg_data = $db->queryrow('SELECT * FROM config')) === false) {
    $sdl->log('- Fatal: Could not query tick data! ABORTED');
  exit;
}
$ACTUAL_TICK = $cfg_data['tick_id'];
$NEXT_TICK = ($cfg_data['tick_time'] - time());
$LAST_TICK_TIME = ($cfg_data['tick_time']-TICK_DURATION*60);
$STARDATE = $cfg_data['stardate'];

if($cfg_data['tick_stopped']) {
    $sdl->log('Finished Scheduler in '.round((microtime()+time())-$starttime, 4).' secs\nTick has been stopped (Unlock in table "config")');
    exit;
}

if(empty($ACTUAL_TICK)) {
    $sdl->log('Finished Scheduler in '.round((microtime()+time())-$starttime, 4).' secs\n- Fatal: empty($ACTUAL_TICK) == true');
    exit;
}

/*
Beispiel Job:

$sdl->start_job('Mein Job');

mach irgendwas...bei Fehlern/Meldung:
  $sdl->log('...');
am besten mit - davor, damit es sich von den anderen nachrichten abhebt, also: $sdl->log('- hier stimmt was nicht');

$sdl->finish_job('Mein Job'); // beendet den timer

 */


// ########################################################################################
// ########################################################################################
// Moves Scheduler
$sdl->start_job('Moves Scheduler');
include('moves_main.php');

$sdl->finish_job('Moves Scheduler');


// ########################################################################################
// ########################################################################################
// Beenden und Log schließen

$sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font>'."\n".'Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>

