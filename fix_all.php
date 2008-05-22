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

error_reporting(E_ERROR);
ini_set('memory_limit', '200M');
set_time_limit(120); // 2 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'Der Scheduler kann nur per CLI aufgerufen werden!'; exit;
}

define('TICK_LOG_FILE', '|script_dir|/game/logs/fixall/tick_'.date('d-m-Y', time()).'.log');
define('IN_SCHEDULER', true); // wir sind im scheduler...


// ########################################################################################
// ########################################################################################
// SQL-Abstraktions-Klasse


class sql {
    var $login = array();
    var $error = array();

    var $link_id = 0;
    var $query_id = 0;

    var $i_query = 0;
    
    var $already_reconnected = -1; // So ist bei erster Verbindung 0


    function sql($server, $database, $user, $password = '') {
        $this->login = array(
            'server' => $server,
            'database' => $database,
            'user' => $user,
            'password' => $password
        );
    }

    function raise_error($message = false, $number = false, $sql = '') {
        if($message === false) $message = mysql_error($this->link_id);
        if($number === false) $number = mysql_errno($this->link_id);

        $this->error = array(
            'message' => $message,
            'number' => $number,
            'sql' => $sql
        );

        return false;
    }

    function connect() {
        if(!is_resource($this->link_id)) {
            if($this->already_reconnected == 5) {
                global $sdl;
                
                $sdl->log('ILLEGAL: Mysql->connect(): Trying to reconnect 6th time! DIE');
                exit;
            }

            if(!$this->link_id = @mysql_connect($this->login['server'], $this->login['user'], $this->login['password'])) {
                global $sdl;

                $sdl->log('CRITICAL: Mysql->connect(): Could not connect to mysql server! DIE');
                exit;
            }

            if(!@mysql_select_db($this->login['database'], $this->link_id)) {
                global $sdl;

                $sdl->log('CRITICAL: Mysql->connect(): Could not select database! DIE');
                exit;
            }
            
            $this->already_reconnected++;
        }
        


        return true;
    }

    function close() {
        if(is_resource($this->link_id)) {
            if(!@mysql_close($this->link_id)) {
                return $this->raise_error();
            }
        }

        return true;
    }

    function query($query, $unbuffered = false) {
        if(!$this->connect()) {
            return false;
        }

        $query_function = ($unbuffered) ? 'mysql_unbuffered_query' : 'mysql_query';

        if(!$this->query_id = @$query_function($query, $this->link_id)) {
            return $this->raise_error(false, false, $query);
        }

        ++$this->i_query;

        return $this->query_id;
    }

    function fetchrow($query_id = 0, $result_type = MYSQL_ASSOC) {
        if(!is_resource($query_id)) $query_id = $this->query_id;

        if(!$_row = @mysql_fetch_array($query_id, $result_type)) {
            if(($_error = mysql_error()) !== '') {
                return $this->raise_error($_error);
            }
            else {
                return array();
            }
        }

        return $_row;
    }

    function fetchrowset($query_id = 0, $result_type = MYSQL_ASSOC) {
        if(!is_resource($query_id)) $query_id = $this->query_id;

        $_row = $_rowset = array();

        while($_row = @mysql_fetch_array($query_id, $result_type)) {
            $_rowset[] = $_row;
        }

        if(!$_rowset) {
            if(($_error = mysql_error()) !== '') {
                return $this->raise_error();
            }
            else {
                return array();
            }
        }

        return $_rowset;
    }

    function queryrow($query, $result_type = MYSQL_ASSOC) {
        if(!$_qid = $this->query($query)) {
            return false;
        }

        return $this->fetchrow($_qid, $result_type);
    }

    function queryrowset($query, $result_type = MYSQL_ASSOC) {
        if(!$_qid = $this->query($query, true)) {
            return false;
        }

        return $this->fetchrowset($_qid, $result_type);
    }

    function free_result($query_id = 0) {
        if(!is_resource($query_id)) $query_id = $this->query_id;

        if(!@mysql_free_result($query_id)) {
            return $this->raise_error();
        }

        return true;
    }

    function num_rows($query_id = 0) {
        if(!is_resource($query_id)) $query_id = $this->query_id;

        $_num = @mysql_num_rows($query_id);

        if($_num === false) {
            return $this->raise_error();
        }

        return $_num;
    }

    function affected_rows() {
        $_num = @mysql_affected_rows($this->link_id);

        if($_num === false) {
            return $this->raise_error();
        }

        return $_num;
    }

    function insert_id() {
        $_id = @mysql_insert_id($this->link_id);

        if($_id === false) {
            return $this->raise_error();
        }

        return $_id;
    }
}


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
$res=$db->queryrow('SELECT SUM(p.add_1/100*'.$percent.') AS r1,SUM(p.add_2/100*'.$percent.') AS r2,SUM(p.add_3/100*'.$percent.') AS r3 FROM (user u) LEFT join (planets p) ON p.planet_owner=u.user_id WHERE (u.user_alliance="'.$alliance_id.'") AND (u.user_active=1)');
$res[0]=round($res['r1']);
$res[1]=round($res['r2']);
$res[2]=round($res['r3']);
return $res;
}








// ########################################################################################
// ########################################################################################
// Init

$starttime = ( microtime() + time() );

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
          '<b>Starting FixAll-Script at '.date('d.m.y H:i:s', time()).'</b>');

if(($cfg_data = $db->queryrow('SELECT * FROM config')) === false) {
    $sdl->log('- Fatal: Could not query tick data! ABORTED');
  exit;
}

$ACTUAL_TICK = $cfg_data['tick_id'];
$NEXT_TICK = ($cfg_data['tick_time'] - time());
$LAST_TICK_TIME = ($cfg_data['tick_time']-5*60);
$STARDATE = $cfg_data['stardate'];



/*
Beispiel Job:

$sdl->start_job('Mein Job');

mach irgendwas...bei Fehlern/Meldung:
  $sdl->log('...');
am besten mit - davor, damit es sich von den anderen nachrichten abhebt, also: $sdl->log('- hier stimmt was nicht');

$sdl->finish_job('Mein Job'); // beendet den timer

 */


$sdl->start_job('Ressourcen neu berechnen');
$db->query('UPDATE planets SET recompute_static=1');
$sdl->finish_job('Ressourcen neu berechnen');









$sdl->start_job('Sicherheitstruppen neu berechnen');
$sql = 'SELECT u.user_id FROM user u WHERE u.user_active=1';	

if(!$q_user = $db->query($sql)) {

	$game->message(DATABASE_ERROR, 'Could not query user data');
}

while($user = $db->fetchrow($q_user)) {


$sql = 'SELECT planet_id, planet_owner_enum FROM planets WHERE planet_owner='.$user['user_id'].' ORDER BY  planet_owned_date ASC, planet_id ASC';	
if(!$q_planet = $db->query($sql)) {

	$game->message(DATABASE_ERROR, 'Could not query user data');
}

$i=0;

while($planet = $db->fetchrow($q_planet)) {
if ($planet['planet_owner_enum']!=$i) {$count++;}
$i++;
$count2++;
}


		    if(!$db->query('SET @i=0')) {
                       $game->message(DATABASE_ERROR, 'Could not set sql iterator variable for planet owner enum! SKIP');
                    }



                    $sql = 'UPDATE planets

                            SET planet_owner_enum = (@i := (@i + 1))-1

                            WHERE planet_owner = '.$user['user_id'].'

                            ORDER BY planet_owned_date ASC, planet_id ASC';



                    if(!$db->query($sql)) {

                        $game->message(DATABASE_ERROR, 'Could not update planet owner enum data! SKIP');

                    }               

}
$sdl->log($count.' von '.$count2.' Planeten haben nun angepasste Werte');

$sdl->finish_job('Sicherheitstruppen neu berechnen');




















$sdl->start_job('Gebï¿½de/Forschungslevel fixen');

if (isset($MAX_BUILDING_LVL))
{

$qry=$db->query('SELECT p.*,u.user_capital,u.pending_capital_choice FROM (planets p) LEFT JOIN (user u) ON u.user_id=p.planet_owner ORDER BY planet_id ASC');

while (($planet = $db->fetchrow($qry)) != false)
{
$capital=(($planet['user_capital']==$planet['planet_id']) ? 1 : 0);
if ($planet['pending_capital_choice']) $capital=0;

$MAX_BUILDING_LVL[0][9] = 15 + $planet['research_3'];
$MAX_BUILDING_LVL[1][9] = 20 + $planet['research_3'];
$MAX_BUILDING_LVL[0][12] = 15 + $planet['research_3'];
$MAX_BUILDING_LVL[1][12] = 20 + $planet['research_3'];


for ($t=0;$t<13;$t++)
{

if ($MAX_BUILDING_LVL[$capital][$t]<$planet['building_'.($t+1)])
{
if ($MAX_BUILDING_LVL[$capital][$t]>=9) $db->query('UPDATE planets SET building_'.($t+1).'='.$MAX_BUILDING_LVL[$capital][$t].' WHERE planet_id='.$planet['planet_id']);
}



if ($planet['building_'.($t+1)]<0)
{
$db->query('UPDATE planets SET building_'.($t+1).'=0 WHERE planet_id='.$planet['planet_id']);
}


}


for ($t=0;$t<5;$t++)
if ($MAX_RESEARCH_LVL[$capital][$t]<$planet['research_'.($t+1)])
{
if ($MAX_RESEARCH_LVL[$capital][$t]>=9) $db->query('UPDATE planets SET research_'.($t+1).'='.$MAX_RESEARCH_LVL[$capital][$t].' WHERE planet_id='.$planet['planet_id']);
}


}
}
$sdl->finish_job('Gebï¿½de/Forschungslevel fixen');














/*


$sdl->start_job('Geisterflotten beheben');



$sql = 'SELECT f.*,

               COUNT(s.ship_id) AS real_n_ships,

               u.user_id AS real_user_id, u.user_capital,

               p.planet_id,

               ss.move_id, ss.move_status, ss.start, ss.dest, ss.action_code

        FROM (ship_fleets f)

        LEFT JOIN (ships s) ON s.fleet_id = f.fleet_id

        LEFT JOIN (user u) ON u.user_id = f.user_id

        LEFT JOIN (planets p) ON p.planet_id = f.planet_id

        LEFT JOIN (scheduler_shipmovement ss) ON ss.move_id = f.move_id

        GROUP BY f.fleet_id';

        

if(!$q_fleets = $db->query($sql)) {

    message(DATABASE_ERROR, 'Could not query fleets main data');

}



$sdl->log('<b>'.$db->num_rows($q_fleets).'</b> Flotten mit Schiffen gefunden.');



while($fleet = $db->fetchrow($q_fleets)) {

    $fleet_id = (int)$fleet['fleet_id'];

    

    if($fleet['real_n_ships'] == 0) {

        $sql = 'DELETE FROM ship_fleets

                WHERE fleet_id = '.$fleet_id;

                

        if(!$db->query($sql)) {

            message(DATABASE_ERROR, 'Could not delete empty fleets data');

        }

        

        $sdl->log('Flotte: <b>'.$fleet_id.'</b>; (Nicht-sichere MoveID: '.$fleet['move_id'].' [Typ: '.$fleet['action_code'].']) Flotte ist leer. Gelï¿½cht');

    }

    

    if(empty($fleet['real_user_id'])) {

        $sql = 'DELETE FROM ships

                WHERE fleet_id = '.$fleet_id;

                

        if(!$db->query($sql)) {

            message(DATABASE_ERROR, 'Could not delete ships data');

        }

        

        $sql = 'DELETE FROM ship_fleets
                WHERE fleet_id = '.$fleet_id;

                

        if(!$db->query($sql)) {

            message(DATABASE_ERROR, 'Could not delete fleets data');

        }

        

        $sdl->log('Flotte: <b>'.$fleet_id.'</b>; Spieler existiert nicht mehr. Schiffe und Flotten gelï¿½cht');

    }

        

    

    if($fleet['real_n_ships'] != $fleet['n_ships']) {

        $sql = 'UPDATE ship_fleets

                SET n_ships = '.(int)$fleet['real_n_ships'].'

                WHERE fleet_id = '.$fleet_id;

                

        if(!$db->query($sql)) {

            message(DATABASE_ERROR, 'Could not update fleet n_ships data');

        }

        

        $sdl->log('Flotte: <b>'.$fleet_id.' (Nicht-sichere MoveID: '.$fleet['move_id'].' [Typ: '.$fleet['action_code'].'])</b>; Falsche Schiffszahlen. Behoben');

    }

    

    $RESET_FLEET = 0;

    

    if( (empty($fleet['planet_id'])) && (empty($fleet['move_id'])) ) {

        $sdl->log('Geisterflotte: <b>'.$fleet_id.'</b>; Keine Positionsdaten. Versuche Repositionierung');

        

        $RESET_FLEET = $fleet['user_capital'];

    }

    elseif( (!empty($fleet['planet_id'])) && (!empty($fleet['move_id'])) ) {

        $sdl->log('Geisterflotte: <b>'.$fleet_id.'</b>; Korrupte Positionsdaten; Versuche Repositionierung');
        $RESET_FLEET = $fleet['planet_id'];

    }

    elseif(!empty($fleet['move_id'])) {

        $move_status = (int)$fleet['move_status'];

        

        if( ($move_status > 10) && ($move_status < 40) ) {

            $sdl->log('Geisterflotte: <b>'.$fleet_id.'</b>; Vollstï¿½diger Move: <b>[Typ: '.$fleet['action_code'].'] [Typ: '.$fleet['action_code'].']</b>. Versuche Repositionierung');

            

            $RESET_FLEET = $fleet['dest'];

        }

        elseif( ($move_status > 30) && ($move_status < 40) ) {

            $sdl->log('Geisterflotte: <b>'.$fleet_id.'</b>; Unvollstï¿½diger Move: <b>'.$fleet['move_id'].' [Typ: '.$fleet['action_code'].']</b>. Versuche Repositionierung');

            

            $RESET_FLEET = $fleet['start'];

        }
        elseif( ($move_status == 4) ) { // Rueckruf einer Koloflotte oder Rueckruf im ersten Tick

            $sdl->log('Geisterflotte: <b>'.$fleet_id.'</b>; Unvollstï¿½diger Move -> status 4: <b>'.$fleet['move_id'].' [Typ: '.$fleet['action_code'].']</b>. Versuche Repositionierung');
            $RESET_FLEET = $fleet['start'];

        }
        

    }

    

    if($RESET_FLEET > 0) {

        $sql = 'UPDATE ship_fleets

                SET planet_id = '.$RESET_FLEET.',

                    move_id = 0

                WHERE fleet_id = '.$fleet_id;



        if(!$db->query($sql)) {

            message(DATABASE_ERROR, 'Could not reset fleets location data');

        }

        $sdl->log('Flotte <b>'.$fleet_id.'</b> wird zu Planet <b>'.$RESET_FLEET.'</b> zurckgesetzt');



    }

}

$sql = 'DELETE FROM ship_fleets
        WHERE n_ships = 0';

if(!$db->query($sql)) {
    message(DATABASE_ERROR, 'Could not delete empty fleets');
}



$sdl->finish_job('Geisterflotten beheben');

*/

$sdl->start_job('Aufgegebene Planeten an den Siedler übergeben');

  $sql = 'UPDATE planets
            		SET planet_owner='.INDEPENDENT_USERID.',
			planet_owned_date = '.time().',
                        resource_1 = 10000,
                        resource_2 = 10000,
                        resource_3 = 10000,
                        resource_4 = '.mt_rand(0, 5000).',
                        recompute_static = 1, 
                        building_1 = '.mt_rand(0, 9).',
                        building_2 = '.mt_rand(0, 9).',
                        building_3 = '.mt_rand(0, 9).',
                        building_4 = '.mt_rand(0, 9).',
                        building_5 = '.mt_rand(0, 9).',
                        building_6 = '.mt_rand(0, 9).',
                        building_7 = '.mt_rand(0, 9).',
                        building_8 = '.mt_rand(0, 9).',
                        building_10 = '.mt_rand(0, 9).',
                        building_11 = '.mt_rand(0, 9).',
                        unit_1 = '.mt_rand(500, 1500).',
                        unit_2 = '.mt_rand(500, 1000).',
                        unit_3 = '.mt_rand(0, 500).',
                        unit_4 = 0,
                        unit_5 = 0,
                        unit_6 = 0,
        	            workermine_1 = 100,
    	                workermine_2 = 100,
	                    workermine_3 = 100,
                        catresearch_1 = 0,
                        catresearch_2 = 0,
                        catresearch_3 = 0,
                        catresearch_4 = 0,
                        catresearch_5 = 0,
                        catresearch_6 = 0,
                        catresearch_7 = 0,
                        catresearch_8 = 0,
                        catresearch_9 = 0,
                        catresearch_10 = 0,
                    	unittrain_actual = 0,
		    	        unittrainid_nexttime=0,
			            building_queue=0,
                        planet_surrender=0
			WHERE ( planet_surrender < '.$ACTUAL_TICK.' AND planet_surrender > 0 )';

  $sdl->log($sql);

  if(!$db->query($sql)) {
	message(DATABASE_ERROR, 'Could not delete switch user');
  }

$sdl->finish_job('Alle Planeten wurden abgegeben.');


$sdl->start_job('Logbuch säubern');

$sql = 'DELETE FROM logbook WHERE log_read=1 AND log_date<'.(time()-3600*24*14);
$sdl->log($sql);
if(!$db->query($sql)) {
		message(DATABASE_ERROR, 'Could not delete 14-day old logs');
}
$sql = 'DELETE FROM logbook WHERE log_type='.LOGBOOK_GOVERNMENT.' AND log_date<'.(time()-3600*24);
$sdl->log($sql);
if(!$db->query($sql)) {
		message(DATABASE_ERROR, 'Could not delete 1-day old government logs');
}


// Ungelesene Logbucheinträge updaten
$sql = 'SELECT u.user_id, COUNT(l.log_id) AS n_logs  FROM (user u)
			 LEFT JOIN (logbook l) ON l.user_id=u.user_id AND l.log_read=0
			GROUP BY u.user_id';
if(!$q_user = $db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not query user! CONTINUED');
}
else {
    while($user = $db->fetchrow($q_user)) {

        $sql = 'UPDATE user 
                SET unread_log_entries = '.$user['n_logs'].' WHERE user_id = '.$user['user_id'];
                
        if(!$db->query($sql)) {
            message(DATABASE_ERROR, 'Could not update user unread log entries data');
        }
	 }
}




$sdl->finish_job('Logbuch säubern');


$sdl->start_job('Ungültige Kriegserklärungen löschen');

$min_points = 500;
$min_members = 5;

$sql = 'SELECT a.alliance_id, ap.ad_id FROM (alliance a) INNER JOIN (alliance_diplomacy ap) ON (ap.alliance1_id = a.alliance_id) OR (ap.alliance2_id = a.alliance_id) WHERE a.alliance_points <= '.$min_points.' AND a.alliance_member <= '.$min_members.' AND ap.type = 1 AND (ap.status = 0 OR ap.status = 2)'; 
		
if(!$q_diplomacy = $db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not query diplomacy! CONTINUED');
}
else {
	while($ap = $db->fetchrow($q_diplomacy)) {
	
		$sql = 'DELETE FROM alliance_diplomacy WHERE ad_id = '.$ap['ad_id'];
		
		if(!$db->query($sql)) {
            message(DATABASE_ERROR, 'Could not delete illegal pacts');
        }
	}
}

$sdl->finish_job('Diplomatie gesäubert');

// ########################################################################################
// ########################################################################################
// Beenden und Log schlieï¿½n

$sdl->log('<b>Finished FixAll-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font>'."\n".'Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
