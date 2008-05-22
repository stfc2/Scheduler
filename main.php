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
set_time_limit(240); // 4 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'Der Scheduler kann nur per CLI aufgerufen werden!'; exit;
}

define('TICK_LOG_FILE', '|script_dir|/game/logs/tick_'.date('d-m-Y', time()).'.log');
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



function ResourcesPerTickMetal(&$planet) {
	$rid=0;
    global $RACE_DATA,$PLANETS_DATA, $addres;
    $result = 0.25*(3*((pow(((3*$PLANETS_DATA[$planet['planet_type']][$rid])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
	if($result < 10) $round = 1;
    else $round = 0;
    return round($result, $round);
}

function ResourcesPerTickMineral(&$planet) {
	$rid=1;
    global $RACE_DATA,$PLANETS_DATA, $addres;
    $result = 0.25*(3*((pow(((3*$PLANETS_DATA[$planet['planet_type']][$rid])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
    if($result < 10) $round = 1;
    else $round = 0;
    return round($result, $round);
}

function ResourcesPerTickLatinum(&$planet) {
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

if(!$db->query('UPDATE config SET tick_time = '.(time() + 60 * TICK_DURATION))) {
    $sdl->log('- Notice: Could not update tick_time! CONTINUED');
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
// Building Scheduler
						
$sdl->start_job('Building Scheduler');

$sql = 'SELECT si.*, u.user_race, p.planet_type,p.research_4,p.building_queue,p.building_1,p.building_2,p.building_3,p.building_4,p.building_5,p.building_6,p.building_7,p.building_8,p.building_9,p.building_10,p.building_11,p.building_12
        FROM (scheduler_instbuild si) LEFT JOIN (planets p) ON p.planet_id=si.planet_id LEFT JOIN (user u) ON u.user_id=p.planet_owner
		WHERE si.build_finish <= '.$ACTUAL_TICK;

if(($q_inst = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query scheduler instbuild data! CONTINUED');
}
else
{
$n_instbuild = 0;
while($build = $db->fetchrow($q_inst)) {
   	    $recompute_static = (in_array($build['installation_type'], array(1, 2, 3, 11))) ? 1 : 0;

        $sql = 'UPDATE planets
                SET building_'.($build['installation_type'] + 1).' = building_'.($build['installation_type'] + 1).' + 1,
                    recompute_static = '.$recompute_static.', building_queue=0
                WHERE planet_id = '.$build['planet_id'];
		
        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Query sched_instbuild @ planets failed!  TICK EXECUTION CONTINUED');
        }
	
	// Queue processing:
	if ($build['building_queue']>0)
	{
	$build['building_'.($build['installation_type']+1)]++;
	if ($db->query('INSERT INTO scheduler_instbuild (installation_type,planet_id,build_finish) VALUES ("'.($build['building_queue']-1).'","'.$build['planet_id'].'","'.($ACTUAL_TICK+GetBuildingTimeTicks($build['building_queue']-1,$build,$build['user_race'])).'")')==false)  {$sdl->log('<b>Error:</b> building_query: Could not call INSERT INTO in scheduler_instbuild TICK EXECTUION CONTINUED'); }	
	}


		++$n_instbuild;
}

$sql = 'DELETE FROM scheduler_instbuild
        WHERE build_finish <= '.$ACTUAL_TICK.'
        LIMIT '.$n_instbuild;

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not delete instbuild data - TICK EXECUTION CONTINUED');
}

unset($build);
}

$sdl->finish_job('Building Scheduler');


// ########################################################################################
// ########################################################################################
// Academy Scheduler

$sdl->start_job('Academy Scheduler v3-blackeye');

$db->query('UPDATE planets SET
            unittrain_error = 0');

if(!($academyquery=$db->query('SELECT p.*, u.user_race FROM (planets p) LEFT JOIN (user u) ON u.user_id=p.planet_owner WHERE (p.unittrainid_nexttime<="'.$ACTUAL_TICK.'") AND (p.unittrainid_nexttime>0)'))) {
    $sdl->log(' - Warning: Could not query unittrain data! CONTINUED');
}
else
{
while (($planet=$db->fetchrow($academyquery))==true)
{



	// Gucken ob die Baunummer innerhalb normaler Parameter liegt, sollte aber niemals auï¿½rhalb liegen:
	if ($planet['unittrain_actual']<1 || $planet['unittrain_actual']>10)
	{
		$db->query('UPDATE planets SET unittrain_actual="1" WHERE planet_id="'.$planet['planet_id'].'" LIMIT 1');
	}
	else // Falls innerhalb normaler Parameter
	{
		
		$t=($planet['unittrainid_'.($planet['unittrain_actual'])])-1;
		if ($t>5 || $t<0 || (UnitPrice($t,0,$planet['user_race'])<=$planet['resource_1'] && UnitPrice($t,1,$planet['user_race'])<=$planet['resource_2'] && UnitPrice($t,2,$planet['user_race'])<=$planet['resource_3'] && UnitPrice($t,3,$planet['user_race'])<=$planet['resource_4']))
		{
			$sql=array();
 			if ($t<=5 && $t>=0)
			{
			$sql[]='resource_1=resource_1-'.(UnitPrice($t,0,$planet['user_race'])).', resource_2=resource_2-'.(UnitPrice($t,1,$planet['user_race'])).', resource_3=resource_3-'.(UnitPrice($t,2,$planet['user_race'])).', resource_4=resource_4-'.(UnitPrice($t,3,$planet['user_race']));
			}	
				
			
			// 2pre1: Das SQL Query fr 2. vorbereiten, weil sich die Daten unter 1. ï¿½dern kï¿½nen:
			if ($planet['unittrainid_'.($planet['unittrain_actual'])]<7 && $planet['unittrainid_'.($planet['unittrain_actual'])]>0)
			$sql[]='unit_'.($planet['unittrainid_'.($planet['unittrain_actual'])]).'=unit_'.($planet['unittrainid_'.($planet['unittrain_actual'])]).'+1';
			
			// 1. Zur nï¿½hsten Einheit springen + neue Zeit setzen:
			// Wenn left<=0
        		$planet['unittrainnumberleft_'.($planet['unittrain_actual'])]--;
			
			// Wir bauen am gleichen Slot weiter:
		        if ($planet['unittrainnumberleft_'.($planet['unittrain_actual'])]>0)
			{
			$sql[]='unittrainnumberleft_'.($planet['unittrain_actual']).'=unittrainnumberleft_'.($planet['unittrain_actual']).'-1';
		        	
				// Nur die neue Zeit setzen:
				// If Unit
				if ($planet['unittrainid_'.($planet['unittrain_actual'])]<7) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+UnitTimeTicksScheduler($planet['unittrainid_'.($planet['unittrain_actual'])]-1,$planet['research_4'],$planet['user_race'])).'"';}
				else // If Break
				{
					if ($planet['unittrainid_'.($planet['unittrain_actual'])]==10) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+1).'"';}
					if ($planet['unittrainid_'.($planet['unittrain_actual'])]==11) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+9).'"';}
					if ($planet['unittrainid_'.($planet['unittrain_actual'])]==12) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+18).'"';}
				}
			}
			else // Wir bauen nicht am gleichen Slot weiter:
			{
	    			// Wenn endlos gebaut wird, die zahl wieder zurcksetzen...:
	    			if ($planet['unittrainendless_'.($planet['unittrain_actual'])]==1)
				{
					$planet['unittrainnumberleft_'.($planet['unittrain_actual'])]=$planet['unittrainnumber_'.($planet['unittrain_actual'])];
					$sql[]='unittrainnumberleft_'.($planet['unittrain_actual']).'=unittrainnumber_'.($planet['unittrain_actual']);
				}
				else $sql[]='unittrainnumberleft_'.($planet['unittrain_actual']).'=0';
				
				// Jetzt nehmen wir den Bau der nï¿½hsten Einheit in der Liste auf:
				$started=0;
				$tries=0;
				while ($started==0 && $tries<=10)
				{
					$planet['unittrain_actual']++;
					if ($planet['unittrain_actual']>10) $planet['unittrain_actual']=1;
					if ($planet['unittrainid_'.($planet['unittrain_actual'])]<13 && $planet['unittrainid_'.($planet['unittrain_actual'])]>=0 && $planet['unittrainnumberleft_'.($planet['unittrain_actual'])]>0)
					{
						// If Unit
						if ($planet['unittrainid_'.($planet['unittrain_actual'])]<7) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+UnitTimeTicksScheduler($planet['unittrainid_'.($planet['unittrain_actual'])]-1,$planet['research_4'],$planet['user_race'])).'"';}
						else // If Break
						{
							if ($planet['unittrainid_'.($planet['unittrain_actual'])]==10) 
{$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+1).'"';}
							if ($planet['unittrainid_'.($planet['unittrain_actual'])]==11) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+9).'"';}
							if ($planet['unittrainid_'.($planet['unittrain_actual'])]==12) {$sql[]='unittrain_actual="'.($planet['unittrain_actual']).'",unittrainid_nexttime="'.($ACTUAL_TICK+18).'"';}
						}
						$started=1;
					}
					$tries++;
				}
			
				if (!$started)
				{
					$sql[]='unittrainid_nexttime="-1"';
				}


			}

			// 2. Die letzte Einheit dem Planeten hinzufügen (Wenn Planet am Limit, bleibt Einheit als Fertig in der Schleife):
			// unittrain_error=2 wenn Planet voll 

                    $damn_units = ($planet['unit_1']*2+$planet['unit_2']*3+$planet['unit_3']*4+$planet['unit_4']*4+$planet['unit_5']*4+$planet['unit_6']*4);

                    if($planet['max_units']<=$damn_units){
 
                       $db->query('UPDATE planets SET unittrain_error="2" WHERE planet_id="'.$planet['planet_id'].'" LIMIT 1');
                       //$sdl->log('Keine Einheit bei ID #'.$planet['planet_id'].' wegen Platzmangel hinzugefügt');
                        
                    }

                    else {

			 if (isset($sql) && count($sql)>0)
			 {
			 	$db->query('UPDATE planets SET '.implode(",", $sql).' WHERE planet_id='.$planet['planet_id'].'');
			 }

			}
		}
		else // Wenn wir nicht genug Ressourcen hatten
		{                  
                $db->query('UPDATE planets SET unittrain_error="1" WHERE planet_id="'.$planet['planet_id'].'" LIMIT 1');
              }

	} // Ende von "Innerhalb normaler Parameter"

      // Ende Abbrechen

} // End while

} // End of: Successfull Planet Query

unset($sql);
$sdl->finish_job('Academy Scheduler v3-blackeye');



// ########################################################################################
// ########################################################################################
// Shiprepair Scheduler

$sdl->start_job('Shiprepair Scheduler');

$sql = 'SELECT s.*,t.value_5 FROM (ships s) LEFT JOIN (ship_templates t) ON s.template_id=t.id
			WHERE s.ship_repair>0 AND s.ship_repair<= '.$ACTUAL_TICK;

if(($q_ship = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query shiprepair data! CONTINUED');
}
else
{
while($ship = $db->fetchrow($q_ship)) {
$sql = 'UPDATE ships SET hitpoints='.$ship['value_5'].', ship_repair=0, ship_untouchable=0 WHERE ship_id='.$ship['ship_id'];

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not update processed ships data: <b>'.$sql.'</b>');
}
}

}
$sdl->finish_job('Shiprepair Scheduler');


// ########################################################################################
// ########################################################################################
// Shipscrap Scheduler

$sdl->start_job('Shipscrap Scheduler');

$sql = 'SELECT s.*,t.value_5,t.buildtime,t.resource_1,t.resource_2,t.resource_3,t.unit_5,t.unit_6 FROM (ships s) LEFT JOIN (ship_templates t) ON s.template_id=t.id
			WHERE s.ship_scrap>0 AND s.ship_scrap<= '.$ACTUAL_TICK;


if(($q_ship = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query shiprepair data! CONTINUED');
}
else
{

while($ship = $db->fetchrow($q_ship)) {

$res[0]=round(0.7*($ship['resource_1']-$ship['resource_1']/$ship['value_5']*($ship['value_5']-$ship['hitpoints'])),0);
$res[1]=round(0.7*($ship['resource_2']-$ship['resource_2']/$ship['value_5']*($ship['value_5']-$ship['hitpoints'])),0);
$res[2]=round(0.7*($ship['resource_3']-$ship['resource_3']/$ship['value_5']*($ship['value_5']-$ship['hitpoints'])),0);

$unit[0]=$ship['unit_1'];
$unit[1]=$ship['unit_2'];
$unit[2]=$ship['unit_3'];
$unit[3]=$ship['unit_4'];
$unit[4]=$ship['unit_5'];
$unit[5]=$ship['unit_6'];


$sql = 'DELETE FROM ships WHERE ship_id='.$ship['ship_id'].' LIMIT 1';
if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not delete ship: <b>'.$sql.'</b>');
}
else
{
$sql = 'UPDATE planets SET resource_1=resource_1+'.$res[0].', resource_2=resource_2+'.$res[1].', resource_3=resource_3+'.$res[2].',
					unit_1=unit_1+'.$unit[0].',unit_2=unit_2+'.$unit[1].',unit_3=unit_3+'.$unit[2].',unit_4=unit_4+'.$unit[3].',unit_5=unit_5+'.$unit[4].',unit_6=unit_6+'.$unit[5].'
			WHERE planet_id='.((-1)*$ship['fleet_id']).' LIMIT 1';
if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not update planets data: <b>'.$sql.'</b>');
}
}

}

}
$sdl->finish_job('Shipscrap Scheduler');


// ########################################################################################
// ########################################################################################
// Shipyard Scheduler

$sdl->start_job('Shipyard Scheduler');

$sql = 'SELECT ssb.*,
               st.id AS template_id, st.value_5 AS template_value_5, st.value_9 AS template_value_9,
               p.planet_owner as user_id, p.building_7
        FROM (scheduler_shipbuild ssb)
        INNER JOIN (planets p) ON p.planet_id = ssb.planet_id
        LEFT JOIN (ship_templates st) ON st.id = ssb.ship_type
        WHERE ssb.finish_build <= '.$ACTUAL_TICK;

if(!$q_shipyard = $db->query($sql)) {
    $sdl->log(' - <b>Warning:</b> Could not query shipbuild data! CONTINUED');
}
else {

	$bFirstTime = true;
	$bShipyardFull = false;
    while($shipbuild = $db->fetchrow($q_shipyard)) {
	    
		if ($bFirstTime) {
		   $sql = '
		      SELECT COUNT(*) AS no_ships 
		      FROM ships 
			  WHERE fleet_id = -'.$shipbuild['planet_id'];
           
		   if(!$q_spacedock = $db->query($sql)) {
             $sdl->log(' - <b>Warning:</b> Could not query spacedock number of ships! CONTINUED AND JUMP NEXT');
			 continue;
           }
		   
		   $spacedock = $db->fetchrow($q_spacedock);
		   if ($spacedock['no_ships'] >= $MAX_SPACEDOCK_SHIPS[$shipbuild['building_7']]) {
		      $bShipyardFull = false;
           }
		   $bFirstTime = false;
		}

        if ($bShipyardFull) {
		   $sql = '
		      UPDATE scheduler_shipbuild
			  SET start_build = start_build + 1,
                  finish_build = finish_build + 1
              WHERE planet_id = '.$shipbuild['planet_id'].'
			    AND finish_build > '.$ACTUAL_TICK;
				
		   if(!$db->query($sql)) {
              $sdl->log(' - <b>Warning:</b> Could not update start and finish scheduler! CONTINUED!');
			  continue;
           }
			
		} else {
			
           if(empty($shipbuild['template_id'])) {
               $sdl->log(' - <b>Warning:</b> Could not find template '.$shipbuild['template_id'].'! CONTINUED AND JUMP TO NEXT');
               continue;
           }

           $sql = 'DELETE FROM scheduler_shipbuild
                   WHERE planet_id = '.$shipbuild['planet_id'].' AND
                         finish_build = '.$shipbuild['finish_build'].'
                   LIMIT 1';

           if(!$db->query($sql)) {
               $sdl->log(' - <b>Warning:</b> Could not delete shipbuild data on planet '.$shipbuild['planet_id'].' ending in tick '.$shipbuild['finish_build'].'! CONTINUED AND JUMP TO NEXT');
               continue;
           }

           $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
                   VALUES (-'.$shipbuild['planet_id'].', '.$shipbuild['user_id'].', '.$shipbuild['ship_type'].', '.$shipbuild['template_value_9'].', '.$shipbuild['template_value_5'].', '.$game->TIME.', '.$shipbuild['unit_1'].', '.$shipbuild['unit_2'].', '.$shipbuild['unit_3'].', '.$shipbuild['unit_4'].')';

           if(!$db->query($sql)) {
            $sdl->log(' - <b>Warning:</b> Could not insert new ship data! CONTINUED AND JUMP TO NEXT');
            continue;
           }
           $sdl->log('<b>Added Ship from Yard to Dock:</b> Planet #'.$shipbuild['planet_id'].' for User #'.$shipbuild['user_id'].' with Template #'.$shipbuild['ship_type'].' - <b>SUCCESS!</b>');
       }
	}
}

$sdl->finish_job('Shipyard Scheduler');


// ########################################################################################
// ########################################################################################
// Research Scheduler

$sdl->start_job('Research Scheduler');

$n_techs = 0;

$sql = 'SELECT sr.*,
               p.research_1, p.research_2, p.research_3, p.research_4, p.research_5,
               p.catresearch_1, p.catresearch_2, p.catresearch_3, p.catresearch_4, p.catresearch_5,
               p.catresearch_6, p.catresearch_7, p.catresearch_8, p.catresearch_9, p.catresearch_10
        FROM (scheduler_research sr)
        LEFT JOIN (planets p) ON p.planet_id = sr.planet_id
        WHERE sr.research_finish <= '.$ACTUAL_TICK;

if(($q_research = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query research data');
}

while($research = $db->fetchrow($q_research)) {
    if($research['research_id'] < 5) {
        $sql = 'UPDATE planets
                 SET research_'.($research['research_id']+1).' = research_'.($research['research_id']+1).' +1,
                 recompute_static = 1
                    WHERE planet_id = '.$research['planet_id'];

            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b> Query sched_research @ user failed!  TICK EXECUTION CONTINUED');
            }
        }
    else {

           $sql = 'UPDATE planets
                 SET catresearch_'.($research['research_id']-4).' = catresearch_'.($research['research_id']-4).' + 1
                    WHERE planet_id = '.$research['planet_id'];

            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b> Query sched_research @ user failed:\n '.$sql.' \nTICK EXECUTION CONTINUED');
            }

       	}


    $n_techs++;
}

$sql = 'DELETE FROM scheduler_research
        WHERE research_finish <= '.$ACTUAL_TICK.'
        LIMIT '.$n_techs;

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not delete processed research data');
}

$sdl->finish_job('Research Scheduler');

// ########################################################################################
// ########################################################################################
// Resourcetrade Scheduler

$sdl->start_job('Resourcetrade Scheduler');

$sql = 'SELECT *
        FROM (scheduler_resourcetrade s)
		WHERE s.arrival_time <= '.$ACTUAL_TICK;

if(($q_rtrade = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query scheduler resourcetrade data! CONTINUED');
}
else
{
$n_resourcetrades = 0;
while($trade = $db->fetchrow($q_rtrade)) {

        $sql = 'UPDATE planets
                SET resource_1=resource_1+'.$trade['resource_1'].',resource_2=resource_2+'.$trade['resource_2'].',resource_3=resource_3+'.$trade['resource_3'].',resource_4=resource_4+'.$trade['resource_4'].',
                unit_1=unit_1+'.$trade['unit_1'].',unit_2=unit_2+'.$trade['unit_2'].',unit_3=unit_3+'.$trade['unit_3'].',unit_4=unit_4+'.$trade['unit_4'].',unit_5=unit_5+'.$trade['unit_5'].',unit_6=unit_6+'.$trade['unit_6'].'
                WHERE planet_id = '.$trade['planet'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Query sched_resourcetrade @ planets failed!  TICK EXECUTION CONTINUED');
        }
        else { $sdl->log('<b>Transport ausgeliefert</b> Transport ID: '.$trade['id'].' bei Planet: '.$trade['planet'].' <b>WARES</b> - Metall: '.$trade['resource_1'].' Mineral: '.$trade['resource_2'].' Latinum: '.$trade['resource_3'].' Arbeiter: '.$trade['resource_4'].' lvl1: '.$trade['unit_1'].' lvl2: '.$trade['unit_2'].' lvl3: '.$trade['unit_3'].' lvl4: '.$trade['unit_4'].' lvl5: '.$trade['unit_5'].' lvl6: '.$trade['unit_6'].''); }

		++$n_resourcetrades;
}

$sql = 'DELETE FROM scheduler_resourcetrade
        WHERE arrival_time <= '.$ACTUAL_TICK.'
        LIMIT '.$n_resourcetrades;

if(!$db->query($sql)) {
    $sdl->log('<b>Error: (Critical)</b> Could not delete scheduler_resourcetrade data - TICK EXECUTION CONTINUED');
}
unset($trade);
}
$sdl->finish_job('Resourcetrade Scheduler');



// ########################################################################################
// ########################################################################################
//BOT
ini_set('memory_limit', '500M');
define('FILE_PATH_hg','|script_dir|/game-stfc/');
define('FILE_PATH_s','/home/stfc-os/stfc-scheduler/'); 
define('TICK_LOG_FILE_NPC', FILE_PATH_hg.'logs/NPC_BOT_tick_'.date('d-m-Y', time()).'.log');
define('TICK_LOG_FILE_ERROR', FILE_PATH_hg.'logs/NPC_BOT_tick_'.date('d-m-Y', time()).'.log');
define('TODO', FILE_PATH_hg.'logs/TODO_'.date('d-m-Y', time()).'.log');
include('NPC_BOT.php');
$sdl->start_job('Ramona kommt vorbei - ach Frauen sind so wunderbar');
$test01 = new NPC(1,"Normaler Betrieb in der Testrunde",0,"#DEEB24");
$sdl->finish_job('Ramona kommt vorbei - ach Frauen sind so wunderbar');
// ########################################################################################
// ########################################################################################
// Update Tick-ID
// (hier ist alles abgeschlossen, was auf der Tick-ID basiert)

if(substr($STARDATE, -1, 1) == '9') {
    $new_stardate = (string)( ((float)$STARDATE) + 0.1 ).'.0';
}
else {
    $new_stardate = (string)( ((float)$STARDATE) + 0.1 );
}

if(!$db->query('UPDATE config SET tick_id = tick_id + 1, shipwreck_id=shipwreck_id+1, tick_securehash = "'.md5($ACTUAL_TICK).'", stardate = "'.$new_stardate.'"')) {
    $sdl->log('- Could not update tick ID, Tick stopped, sent mail to admin@stgc.de');
    mail('admin@stgc.de','STGC3: Tickstop','Tick '.$ACTUAL_TICK.' wurde angehalten.\nFehlermeldung:\n'.$db->raise_error().'\n\nGrï¿½, der STGC Scheduler');
    $db->raise_error();
    $sdl->log('Tick '.$ACTUAL_TICK.' wurde (vermutlich) angehalten.\nFehlermeldung:\n'.$db->error['message'].'\n\nFehlerquelle: " UPDATE config SET tick_id = tick_id + 1, tick_securehash = "'.md5($ACTUAL_TICK).'" "\n\nGrï¿½, der STGC Scheduler');
    $db->query('UPDATE config SET tick_stopped = 1');
    exit;
}


// ########################################################################################
// ########################################################################################
// Shipdestruction (Wrecking) // Ehemals ROST

/*
if ($cfg_data['shipwreck_id'] > 700)
{
$sdl->start_job('Shipdestruction (Wrecking)');
$sql = 'UPDATE ships s LEFT JOIN ship_templates t ON t.id=s.template_id SET s.hitpoints=s.hitpoints-t.value_5/100 WHERE s.hitpoints>t.value_5/2 AND s.fleet_id>0';
if(($db->query($sql)) === false)
    $sdl->log('<b>Error:</b> Could not execute shipwrecking query! CONTINUED');

if(!$db->query('UPDATE config SET shipwreck_id='.rand(0,100))) {
    $sdl->log('- Could not update shipwreck_id! CONTINUED');

}
$sdl->finish_job('Shipdestruction (Wrecking)');
}
else
$sdl->log('<font color=#0000ff>Shipdestruction (Wrecking) [Skipped '.$cfg_data['shipwreck_id'].'/'.(700).']</font><br>');

*/




// ########################################################################################
// ########################################################################################
// Destruction based on troop strength

$sdl->start_job('Destruction based on troop strength');

$sql = 'SELECT * FROM planets WHERE min_security_troops > (100+unit_1*2+unit_2*3+unit_3*4+unit_4*4) AND planet_owner>10';
if(($q_planets = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query planets data to destroy buildings based on troop strength! CONTINUED');
}

$n_destruction=0;
$n_caused_destruction=0;

$rand=rand(0,200);
while($planet = $db->fetchrow($q_planets)) {
    if(empty($planet['planet_id'])) {
        continue;
    }


   $chance=5 - 5/$planet['min_security_troops']*($planet['unit_1']*2+$planet['unit_2']*3+$planet['unit_3']*4+$planet['unit_4']*4);
   //$chance=5;$rand=5;
   if ($rand<=$chance && $planet['min_security_troops']>($planet['unit_1']*2+$planet['unit_2']*3+$planet['unit_3']*4+$planet['unit_4']*4))
   {
        $victim=array(4,6,7,8,10,11,9);
 		$chance*=20;

		// Liste der Gebï¿½de erstellen, die zerstï¿½t werden kï¿½nten:

        if ($chance>50) array_push($victim,5);
        if ($chance>60) array_push($victim,1);
        if ($chance>70) array_push($victim,2);
        if ($chance>80) array_push($victim,3);
        if ($chance>90) array_push($victim,0);

        $rand_building=rand(0,count($victim)-1);
        echo'\nGebäude:'.count($victim).' von 12, zufällig gewählt:'.$rand_building;
		if ($planet['building_'.$rand_building]>1)
			{
                $log_data=array(
				'planet_name' => $planet['planet_name'],
				'building_id' => $rand_building,
				'prev_level' => $planet['building_'.$rand_building],
				'troops_percent' => (100/$planet['min_security_troops']*($planet['unit_1']*2+$planet['unit_2']*3+$planet['unit_3']*4+$planet['unit_4']*4)),
				);
				add_logbook_entry($planet['planet_owner'], LOGBOOK_GOVERNMENT, 'Aufstände auf Planet '.$planet['planet_name'].'',$log_data);
                //SystemMessage($planet['planet_owner'],'Zerstörung (temp. msg., rmv)','Gebäude '.$rand_building.' wird von '.$planet['building_'.$rand_building].' auf '.($planet['building_'.$rand_building]-1).' gesetzt!');

    			$sql = 'UPDATE planets
            			SET building_'.$rand_building.'=building_'.$rand_building.'-1 WHERE planet_id = '.$planet['planet_id'];

			    if(!$db->query($sql)) {
        			$sdl->log('<b>Error:</b> Could not destroy building '.$rand_building.' of planet <b>'.$planet['planet_id'].'</b>! CONTINUED');
    				}

			}

        }


    ++$n_destruction;
}
$sdl->log('(Affected '.$n_destruction.' planets)');
$sdl->finish_job('Destruction based on troop strength');





// ########################################################################################
// ########################################################################################
// Planet revolution based on troop strength
$sdl->start_job('Planet insurrection set');

$sql = 'UPDATE planets SET planet_insurrection_time=0 WHERE min_security_troops <= (100+unit_1*2+unit_2*3+unit_3*4+unit_4*4)';
if(($db->query($sql)) === false) $sdl->log('<b>Error:</b> Could not update (unset) planets insurrection data! CONTINUED');

$sql = 'UPDATE planets SET planet_insurrection_time=UNIX_TIMESTAMP() WHERE min_security_troops > (100+unit_1*2+unit_2*3+unit_3*4+unit_4*4) AND planet_insurrection_time=0';
if(($db->query($sql)) === false) $sdl->log('<b>Error:</b> Could not update (set) planets insurrection data! CONTINUED');

$sdl->finish_job('Planet insurrection set');



// ########################################################################################
// ########################################################################################
// Planet revolution based on troop strength

$sdl->start_job('Planet revolution based on troop strength');

$sql = 'SELECT * FROM planets WHERE
			(	(min_security_troops > (100+unit_1*2+unit_2*3+unit_3*4+unit_4*4) AND planet_points<30)
			OR	( (unit_1*2+unit_2*3+unit_3*4+unit_4*4) / min_security_troops < 0.3)
			) AND planet_owner>10 AND planet_owner_enum>3 AND planet_insurrection_time>0 AND (UNIX_TIMESTAMP()-planet_insurrection_time)>3600*48';

if(($q_planets = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query planets data to start revolutions based on troop strength! CONTINUED');
}

$n_revolution=0;
$n_revolution_done=0;

while($planet = $db->fetchrow($q_planets)) {
    if(empty($planet['planet_id'])) {
        continue;
    }
	
	$rand=rand(0,100);
	if ($rand==2)
	{
        $sdl->log('Planet '.$planet['planet_name'].' ('.$planet['planet_id'].') von NPC bernommen');

		$sql = 'UPDATE planets
            			SET planet_owner='.INDEPENDENT_USERID.',
						                        planet_owned_date = '.time().',

                        resource_1 = 10000,
                        resource_2 = 10000,
                        resource_3 = 10000,
                        resource_4 = 2000,
                        recompute_static = 1,
                        building_1 = 4,
                        building_2 = 0,
                        building_3 = 0,
                        building_4 = 0,
                        building_5 = 0,
                        building_6 = 0,
                        building_7 = 0,
                        building_8 = 0,

                        building_9 = '.rand(5,15).',

                        building_10 = 0,

                        building_11 = 0,

                        building_12 = '.rand(0,10).',

                        unit_1 = '.rand(500,1500).',

                        unit_2 = '.rand(500,1000).',

                        unit_3 = '.rand(0,500).',

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

				    	building_queue=0
						
						WHERE planet_id = '.$planet['planet_id'];
						
						
				$sdl->log('<b>Overtake:</b> Planet '.$planet['planet_name'].' ('.$planet['planet_id'].') | Troops: '.($planet['unit_1']*2+$planet['unit_2']*3+$planet['unit_3']*4+$planet['unit_4']*4).'    Needed: '.$planet['min_security_troops'].'   Factor: '.(($planet['unit_1']*2+$planet['unit_2']*3+$planet['unit_3']*4+$planet['unit_4']*4)/$planet['min_security_troops']).'   Points: '.$planet['planet_points']);	
						
				    if(!$db->query($sql)) {
        			$sdl->log('<b>Error:</b> Could not perform revolution on planet <b>'.$planet['planet_id'].'</b>! CONTINUED');
    				}

	                $log_data=array(
					'planet_name' => $planet['planet_name'],
					'planet_id' => $planet['planet_id'],
					);    				
    				add_logbook_entry($planet['planet_owner'], LOGBOOK_REVOLUTION, 'Revolution auf Planet '.$planet['planet_name'].'',$log_data);
    				
    	

						
						
								
			    if(!$db->query('SET @i=0'))
					{

                        $sdl->log('<b>Error:</b> Could not set sql iterator variable for planet owner enum! SKIP');

                    }
                    else
                    {



                    $sql = 'UPDATE planets

                            SET planet_owner_enum = (@i := (@i + 1))-1

                            WHERE planet_owner = '.$planet['planet_owner'].'

                            ORDER BY planet_owned_date ASC, planet_id ASC';



                    if(!$db->query($sql)) {

                        $sdl->log('<b>Error:</b> Could not update planet owner enum data! SKIP');

                    }
					}



	

        }
}

$sdl->finish_job('Planet revolution based on troop strength');




// ########################################################################################
// ########################################################################################
// Recompute Static Planet Values

$sdl->start_job('Recompute Static Planet Values');

$sql = 'SELECT p.*,
               u.user_id, u.user_race, u.user_vacation_start, u.user_vacation_end
        FROM (planets p)
		LEFT join (user u) ON u.user_id = p.planet_owner
        WHERE p.recompute_static = 1';




if(($q_planets = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query planets data to recompute static planet values! CONTINUED');
}
else {
	$n_recomputed = 0;

	while($planet = $db->fetchrow($q_planets)) {
		if(empty($planet['user_id'])) continue;

		$add_1 = ResourcesPerTickMetal($planet);
		$add_2 = ResourcesPerTickMineral($planet);
		$add_3 = ResourcesPerTickLatinum($planet);

		$add_4 = ($PLANETS_DATA[$planet['planet_type']][3]*$RACE_DATA[$planet['user_race']][12]
		         +($planet['research_1']*$RACE_DATA[$planet['user_race']][20])*0.1
				 +($planet['research_2']*$RACE_DATA[$planet['user_race']][20])*0.2);

				
		if($ACTUAL_TICK >= $planet['user_vacation_start'] && $ACTUAL_TICK <= $planet['user_vacation_end']) {
		    $add_1 *= 0.2;
			$add_2 *= 0.2;
			$add_3 *= 0.2;
			$add_4 *= 0.2;
		}

		$sql = 'UPDATE planets
		        SET add_1 = '.$add_1.',
				    add_2 = '.$add_2.',
					add_3 = '.$add_3.',
					add_4 = '.$add_4.',
					recompute_static = 0,
					max_resources = '.($PLANETS_DATA[$planet['planet_type']][6]+($planet['building_12']*50000*$RACE_DATA[$planet['user_race']][20])).',
					max_worker = '.($PLANETS_DATA[$planet['planet_type']][7]+($planet['research_1']*$RACE_DATA[$planet['user_race']][20]*500)).',
					max_units = '.($PLANETS_DATA[$planet['planet_type']][7]+($planet['research_1']*$RACE_DATA[$planet['user_race']][20]*500)).'
				WHERE planet_id = '.$planet['planet_id'];
				
		if(!$db->query($sql)) {
		    $sdl->log('<b>Error:</b> Could not update recomputed static values of planet <b>'.$planet['planet_id'].'</b>! CONTINUED');
		}

		++$n_recomputed;
	}
}

$sdl->finish_job('Recompute Static Planet Values');


// ########################################################################################
// ########################################################################################
// Update Planets
$sdl->start_job('Update Planet Security Troops');
$sql='UPDATE planets SET min_security_troops=POW(planet_owner_enum*'.MIN_TROOPS_PLANET.',1+planet_owner_enum*0.01)';
if(!$db->query($sql)) {$sdl->log(' - Warning: Could not execute query '.$sql);}
foreach ($PLANETS_DATA as $key => $planet) {
$sql='UPDATE planets SET min_security_troops='.$planet[7].' WHERE planet_type="'.$key.'" AND min_security_troops>'.$planet[7];
if(!$db->query($sql)) {$sdl->log(' - Warning: Could not execute query '.$sql);}
}
$sdl->finish_job('Update Planet Security Troops');





$sdl->start_job('Update Planets');

$POINTS_EXP = 1.5;

$sql = 'UPDATE planets
        SET planet_points = 10 +
                            POW(building_1, '.$POINTS_EXP.') +
                            POW(building_2, '.$POINTS_EXP.') +
                            POW(building_3, '.$POINTS_EXP.') +
                            POW(building_4, '.$POINTS_EXP.') +
                            POW(building_5, '.$POINTS_EXP.') +
                            POW(building_6, '.$POINTS_EXP.') +
                            POW(building_7, '.$POINTS_EXP.') +
                            POW(building_8, '.$POINTS_EXP.') +
                            POW(building_9, '.$POINTS_EXP.') +
                            POW(building_10, '.$POINTS_EXP.') +
                            POW(building_11, '.$POINTS_EXP.') +
                            POW(building_12, '.$POINTS_EXP.') +
                            POW(building_13, '.$POINTS_EXP.') +
                            POW(research_1, '.$POINTS_EXP.') +
                            POW(research_2, '.$POINTS_EXP.') +
                            POW(research_3, '.$POINTS_EXP.') +
                            POW(research_4, '.$POINTS_EXP.') +
                            POW(research_5, '.$POINTS_EXP.'),
            resource_1 = resource_1 + add_1,
            resource_2 = resource_2 + add_2,
            resource_3 = resource_3 + add_3,
            resource_4 = resource_4 + add_4
		WHERE planet_owner <> 0';

if(!$db->query($sql)) {
    $sdl->log(' - Warning: Could not update planet points and resources! CONTINUED');
}

$n_planets = $db->affected_rows();
// Another great optimization by Data ^^
$sql = 'UPDATE planets p,user u, alliance a
        SET p.resource_1 = p.resource_1 - p.add_1 / 100 * a.taxes,
            p.resource_2 = p.resource_2 - p.add_2 / 100 * a.taxes,
            p.resource_3 = p.resource_3 - p.add_3 / 100 * a.taxes
        WHERE p.planet_owner <> 0 AND
              u.user_id = p.planet_owner AND
              a.alliance_id = u.user_alliance AND
              a.taxes > 0';

if(!$db->query($sql)) {
    $sdl->log(' - Warning: Could not update planets tax data! CONTINUED');
}


// Another great optimization by Daywalker ^^
$sql = 'UPDATE planets
        SET resource_1 = resource_1 - add_1 + add_1 * (1 / min_security_troops * (unit_1*2+unit_2*3+unit_3*4+unit_4*4)),
            resource_2 = resource_2 - add_2 + add_2 * (1 / min_security_troops * (unit_1*2+unit_2*3+unit_3*4+unit_4*4)),
            resource_3 = resource_3 - add_3 + add_3 * (1 / min_security_troops * (unit_1*2+unit_2*3+unit_3*4+unit_4*4))
	WHERE min_security_troops > unit_1*2+unit_2*3+unit_3*4+unit_4*4';

if(!$db->query($sql)) {
    $sdl->log(' - Warning: Could not update planets resource-diff-troops data! CONTINUED');
}





$sql = 'UPDATE planets
        SET resource_1 = max_resources
        WHERE planet_owner <> 0 AND
              resource_1 > max_resources';

if(!$db->query($sql)) {
    $sdl->log(' - Warning: Could not update planet max resources 1! CONTINUED');
}

$sql = 'UPDATE planets
        SET resource_2 = max_resources
        WHERE planet_owner <> 0 AND
              resource_2 > max_resources';

if(!$db->query($sql)) {
    $sdl->log(' - Warning: Could not update planet max resources 2! CONTINUED');
}

$sql = 'UPDATE planets
        SET resource_3 = max_resources
        WHERE planet_owner <> 0 AND
              resource_3 > max_resources';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max resources 3! CONTINUED');
}

$sql = 'UPDATE planets
        SET resource_4 = max_worker
        WHERE planet_owner <> 0 AND
              resource_4 > max_worker';

if(!$db->query($sql)) {
    $sdl->log(' - Warning: Could not update planet max resources 4! CONTINUED');
}


//
// Konzept:
//
// Wenn ein Planet berllt ist, zieht er der Reihe nach von den Einheitenwerten
// das ab, was benï¿½igt wird, damit der Planet nicht mehr berfllt ist.
// Da die unit-Felder UNSIGNED sind (das ist SEHR wichtig), wird es max. 0
// Wenn man bei der nï¿½hsten ï¿½erprfung, wenn man die vorherige Einheit weglï¿½st,
// der Planet noch immer berfllt ist, wird so weitergemacht.

// Unit-1
$sql = 'UPDATE planets
        SET unit_1 = unit_1 - ( ( (unit_1 * 2 + unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 2 )
        WHERE planet_owner <> 0 AND
              (unit_1 * 2 + unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max units data by unit 1! CONTINUED');
}

// Unit-2
$sql = 'UPDATE planets
        SET unit_2 = unit_2 - ( ( (unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 3 )
        WHERE planet_owner <> 0 AND
              (unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max units data by unit 1! CONTINUED');
}

// Unit-3
$sql = 'UPDATE planets
        SET unit_3 = unit_3 - ( ( (unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 4 )
        WHERE planet_owner <> 0 AND
              (unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max units data by unit 1! CONTINUED');
}

// Unit-4
$sql = 'UPDATE planets
        SET unit_4 = unit_4 - ( ( (unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 4 )
        WHERE planet_owner <> 0 AND
              (unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max units data by unit 1! CONTINUED');
}

// Unit-5
$sql = 'UPDATE planets
        SET unit_5 = unit_5 - ( ( (unit_5 * 4 + unit_6 * 4) - max_units) / 4 )
        WHERE planet_owner <> 0 AND
              (unit_5 * 4 + unit_6 * 4) > max_units';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max units data by unit 1! CONTINUED');
}

// Unit-6
$sql = 'UPDATE planets
        SET unit_6 = unit_6 - ( ( (unit_6 * 4) - max_units) / 4 )
        WHERE planet_owner <> 0 AND
              (unit_6 * 4) > max_units';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet max units data by unit 1! CONTINUED');
}



// In der Final nicht mehr benutzt!
/*
// Planet angegriffen
$sql = 'UPDATE planets
        SET planet_attacked = 0';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not zero planet attacked data! CONTINUED');
}

$sql = 'UPDATE planets p, scheduler_shipmovement ss
        SET p.planet_attacked = 1
        WHERE ss.dest = p.planet_id AND
              p.planet_owner <> ss.user_id AND
              ss.action_code >= 40 AND
              ss.move_status = 0';

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not update planet attacked data! CONTINUED');
}
*/

$sdl->finish_job('Update Planets');





// ########################################################################################
// ########################################################################################
// Update Planet Attacked Data

$sdl->start_job('Update Planet Attacked Data');

$sql = 'UPDATE planets
        SET planet_next_attack = 0';


if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not zero planet attacked data! CONTINUED');
}

$already_processed = array();

$sql = 'SELECT ss.*,
               p.building_7 AS dest_building_7,
               u1.user_id AS dest_user_id, u1.user_alliance AS dest_user_alliance,
               u2.user_alliance AS move_user_alliance
        FROM (scheduler_shipmovement ss, planets p)
        INNER JOIN (user u1 )ON u1.user_id = p.planet_owner
        INNER JOIN (user u2) ON u2.user_id = ss.user_id
        WHERE p.planet_id = ss.dest AND
              ss.action_code IN (40, 41, 42, 43, 44, 45, 51, 54, 55) AND
              ss.move_status = 0
        ORDER BY ss.move_finish ASC';

if(!$q_moves = $db->query($sql)) {
    $sdl->log('- Error: Could not select moves data for planet attacked! SKIP');
}
else {
    while($move = $db->fetchrow($q_moves)) {
        if(isset($already_processed[$move['dest']])) continue;

        $already_processed[$move['dest']] = true;

        // entnommen aus get_move_ship_details() und angepasst

        $sql = 'SELECT SUM(st.value_11) AS sum_sensors, SUM(st.value_12) AS sum_cloak
                FROM (scheduler_shipmovement ss)
                INNER JOIN (ship_fleets f) ON f.move_id = ss.move_id
                INNER JOIN (ships s) ON s.fleet_id = f.fleet_id
                INNER JOIN (ship_templates st) ON st.id = s.template_id
                WHERE ss.move_id = '.$move['move_id'].'
                GROUP BY ss.move_id';

        if(($move_ships = $db->queryrow($sql)) === false) {
            $sdl->log('- Error: Could not select moves fleet detail data! SKIP');

            break;
        }

        $move_sum_sensors = (!empty($move_ships['sum_sensors'])) ? (int)$move_ships['sum_sensors'] : 0;
        $move_sum_cloak = (!empty($move_ships['sum_cloak'])) ? (int)$move_ships['sum_cloak'] : 0;

        // entnommen aus get_friendly_orbit_fleets()

        $sql = 'SELECT DISTINCT f.user_id,
                       u.user_alliance,
                       ud.ud_id, ud.accepted,
                       ad.ad_id, ad.type, ad.status
                FROM (ship_fleets f)
                INNER JOIN (user u) ON u.user_id = f.user_id
                LEFT JOIN (user_diplomacy ud) ON ( ( ud.user1_id = '.$move['dest_user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$move['dest_user_id'].' ) )
                LEFT JOIN (alliance_diplomacy ad) ON ( ( ad.alliance1_id = '.$move['dest_user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$move['dest_user_alliance'].' ) )
                WHERE f.planet_id = '.$move['dest'].' AND
                      f.user_id <> '.$move['dest_user_id'];

        if(!$q_user = $db->query($sql)) {
            $sdl->log('- Error: Could not select friendly user data! SKIP');

            break;
        }

        $allied_user = array($move['dest_user_id']);

        while($_user = $db->fetchrow($q_user)) {
            $allied = false;

            if($_user['user_alliance'] == $move['dest_user_alliance']) $allied = true;;

            if(!empty($_user['ud_id'])) {
                if($_user['accepted'] == 1) $allied = true;;
            }

            if(!empty($_user['ad_id'])) {
                if( ($_user['type'] == ALLIANCE_DIPLOMACY_PACT) && ($_user['status'] == 0) ) $allied = true;
            }

            if($allied) $allied_user[] = $_user['user_id'];
        }

        $sql = 'SELECT SUM(st.value_11) AS sum_sensors, SUM(st.value_12) AS sum_cloak
                FROM (ships s, ship_fleets f)
                INNER JOIN (ship_templates st) ON st.id = s.template_id
                WHERE s.user_id IN ('.implode(',', $allied_user).') AND
                      s.fleet_id = f.fleet_id AND
                      f.planet_id = '.$move['dest'];

        if(($friendly_ships = $db->queryrow($sql)) === false) {
            $sdl->log('- Error: Could not select friendly fleets data! SKIP');

            break;
        }

        $dest_sum_sensors = (!empty($friendly_ships['sum_sensors'])) ? (int)$friendly_ships['sum_sensors'] : 0;
        $dest_sum_cloak = (!empty($friendly_ships['sum_cloak'])) ? (int)$friendly_ships['sum_cloak'] : 0;

        $visiblity = GetVisibility($move_sum_sensors, $move_sum_cloak, $move['n_ships'], ($dest_sum_sensors + ($move['dest_building_7'] + 1) * 200), $dest_sum_cloak);
        $travelled = 100 / ($move['move_finish'] - $move['move_begin']) * ($ACTUAL_TICK - $move['move_begin']);

        if($travelled < ($visibility +     ( (100 - $visibility) / 4) ) ) $move['n_ships'] = 0;
        if($travelled < ($visibility + 2 * ( (100 - $visibility) / 4) ) ) $move['action_code'] = 0;

        $sql = 'UPDATE planets
                SET planet_next_attack = '.(time() + ($move['move_finish'] - $ACTUAL_TICK) * 300).',
                    planet_attack_ships = '.$move['n_ships'].',
                    planet_attack_type= '.$move['action_code'].'
                WHERE planet_id= '.$move['dest'];

        if(!$db->query($sql)) {
            $sdl->log('- Warning: Could not update planet attacked data! CONTINUED');
        }
    }
}

/*

$sql='
SELECT p.planet_id,p.planet_name,p.building_7,u.user_alliance AS user1_alliance,u2.user_alliance AS user2_alliance,u.user_id as user1_id, u2.user_id AS user2_id
FROM planets p
LEFT JOIN scheduler_shipmovement ss ON ss.action_code IN(24,41,42)
LEFT JOIN user u ON u.user_id=p.planet_owner
LEFT JOIN user u2 ON u2.user_id=ss.user_id
WHERE p.planet_id=ss.dest AND u.user_alliance<>u2.user_alliance GROUP BY planet_id
AND
(SELECT COUNT(ud_id) FROM user_diplomacy WHERE accepted=1 AND ((user1_id=u.user_id AND user2_id=u2.user_id) OR (user1_id=u2.user_id AND user2_id=u.user_id)))=0

AND
(SELECT ad_id FROM alliance_diplomacy WHERE status<>-1 AND type IN('.ALLIANCE_DIPLOMACY_NAP.','.ALLIANCE_DIPLOMACY_PACT.') AND ((alliance1_id=u.user_alliance AND alliance2_id=u2.user_alliance) OR (alliance1_id=u2.user_alliance AND alliance2_id=u.user_alliance)))=0
';

$sdl->log($sql);


$qry=$db->query($sql);


while (($planet=$db->fetchrow($qry))==true)
{
$sql='SELECT  * FROM scheduler_shipmovement WHERE dest='.$planet['planet_id'].' AND action_code IN(21,24,41,42) AND move_finish>='.$ACTUAL_TICK.' ORDER BY move_finish ASC LIMIT 1';
if (($move=$db->queryrow($sql))==true)
{
$sensor2=get_friendly_orbit_fleets($planet['planet_id']);
$sensor1=get_move_ship_details($move['move_id']);
$visibility=GetVisibility($sensor1['sum_sensors']+($planet['building_7']+1)*200,$sensor1['sum_cloak'],$sensor1['n_ships'],$sensor2['sum_sensors'],$sensor2['sum_cloak']);
$travelled=100/ ($move['move_finish']-$move['move_begin']) * ($ACTUAL_TICK-$move['move_begin']);
	
if ($travelled<$visibility+((100-$visibility)/4)) {$move['n_ships']=0;}
if ($travelled<$visibility+2*((100-$visibility)/4)) {$move['action_code']=0;}


$sql = 'UPDATE planets
        SET planet_next_attack = '.(time()+($move['move_finish']-$ACTUAL_TICK)*300).', planet_attack_ships = '.$move['n_ships'].', planet_attack_type= '.$move['action_code'].' WHERE planet_id= '.$planet['planet_id'];

if(!$db->query($sql)) {
    $sdl->log('- Warning: Could not set planet attacked data! CONTINUED');
}
}

}; */

$sdl->finish_job('Update Planet Attacked Data');



// ########################################################################################
// ########################################################################################
// Update Players

$sdl->start_job('Update Players');

$sql = 'SELECT u.user_id, u.user_alliance,
               COUNT(p.planet_id) AS num_planets, SUM(p.planet_points) AS points
        FROM (user u)
        LEFT JOIN (planets p) ON p.planet_owner = u.user_id
        WHERE u.user_active = 1
        GROUP BY u.user_id
        ORDER BY u.user_id ASC';

if(($q_players = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Query users failed! CONTINUED');
}
else {
    $n_players = 0;

    while($user = $db->fetchrow($q_players)) {
        $sql = 'UPDATE user
                SET user_points = '.(int)$user['points'].',
                    user_planets = '.(int)$user['num_planets'].'
                WHERE user_id = '.$user['user_id'];

        if(!$db->query($sql)) {
            $sdl->log('- Warning: Could not update player data #'.$user['user_id'].'! CONTINUED');

            continue;
        }

        ++$n_players;
    }
}

$sql = 'UPDATE user
        SET user_points = 9,
            user_planets = 0,
			user_honor = 0
        WHERE user_auth_level = '.STGC_DEVELOPER.' OR
              user_auth_level = '.STGC_BOT;

if(!$db->query($sql)) {
    continue;
}

$sdl->finish_job('Update Players');


// ########################################################################################
// ########################################################################################
// Update Alliance Points

$update_problem=0;

$sdl->start_job('Update Alliance Points');

if(!$db->query('UPDATE alliance SET alliance_points = 1')) {
    $sdl->log('<b>Error:</b> Set alliance points to 1 failed. CONTINUE');
}

$sql = 'SELECT a.alliance_id,
               COUNT(u.user_id) AS member,
               SUM(u.user_points) AS points,
               SUM(u.user_planets) AS planets,
               SUM(u.user_honor) AS uhonor
        FROM (alliance a)
        LEFT JOIN (user u) ON u.user_alliance = a.alliance_id AND u.user_active=1
		GROUP BY a.alliance_id';

if(($q_alliance = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Query alliances failed! CONTINUE');
	 $update_problem=1;
}
else {
    $n_alliances = 0;

    while($alliance = $db->fetchrow($q_alliance)) {
        if ($alliance['member'] > 0) {
            $sql = 'UPDATE alliance
                    SET alliance_points = '.(int)$alliance['points'].',
                        alliance_planets = '.(int)$alliance['planets'].',
			alliance_member = '.(int)$alliance['member'].',
                        alliance_honor = '.(int)$alliance['uhonor'].'
                    WHERE alliance_id = '.$alliance['alliance_id'];

            if(!$db->query($sql)) {
                $sdl->log('- Warning: Could not update alliance data #'.$alliance['alliance_id'].'! CONTINUE');
					 $update_problem=1;
                continue;
            }

            ++$n_alliances;
        }
    }
}

$sdl->finish_job('Update Alliance Points');


// ########################################################################################
// ########################################################################################
// Update Alliance Points


$sdl->start_job('Remove non-existing alliances');
if (isset($update_problem) && $update_problem==0)
{
$sql = 'DELETE FROM alliance
        WHERE alliance_points = 1';

if(!$db->query($sql)) {
   $sdl->log('- Warning: Could not remove alliances! CONTINUE');
}
}
$sdl->finish_job('Remove non-existing alliances');



// ########################################################################################
// ########################################################################################
// Update Ranking

$sdl->start_job('Update Ranking');

// Punkte der Spieler
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for user points ranking! CONTINUE');
}
else {
    $sql = 'UPDATE user
            SET user_rank_points = (@i := (@i + 1))
            WHERE user_active=1 ORDER BY user_points DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update user points ranking! CONTINUE');
    }
}

// Planeten der Spieler
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for user planets ranking! CONTINUE');
}
else {
    $sql = 'UPDATE user
            SET user_rank_planets = (@i := (@i + 1))
            WHERE user_active=1 ORDER BY user_planets DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update user planets ranking! CONTINUE');
    }
}

// Verdienst der Spieler
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for user honor ranking! CONTINUE');
}
else {
    $sql = 'UPDATE user
            SET user_rank_honor = (@i := (@i + 1))
            WHERE user_active=1 ORDER BY user_honor DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update user honor ranking! CONTINUE');
    }
}

// Punkte der Allianzen
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for alliance points ranking! CONTINUE');
}
else {
    $sql = 'UPDATE alliance
            SET alliance_rank_points = (@i := (@i + 1))
            ORDER BY alliance_points DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update alliance points ranking! CONTINUE');
    }
}

// Avg Punkte der Allianzen
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for alliance points avg ranking! CONTINUE');
}
else {
    $sql = 'UPDATE alliance
            SET alliance_rank_points_avg = (@i := (@i + 1))
            ORDER BY alliance_points/alliance_member DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update alliance points avg ranking! CONTINUE');
    }
}

// Planeten der Allianzen
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for alliance planets ranking! CONTINUE');
}
else {
    $sql = 'UPDATE alliance
            SET alliance_rank_planets = (@i := (@i + 1))
            ORDER BY alliance_planets DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update alliance planets ranking! CONTINUE');
    }
}

// Verdienst der Allianzen
if(!$db->query('SET @i=0')) {
    $sdl->log('- Error: Could not initialize @i for alliance honor ranking! CONTINUE');
}
else {
    $sql = 'UPDATE alliance
            SET alliance_rank_honor = (@i := (@i + 1))
            ORDER BY alliance_honor DESC';

    if(!$db->query($sql)) {
        $sdl->log('- Error: Could not update alliance honor ranking! CONTINUE');
    }
}

$sdl->finish_job('Update Ranking');


// ########################################################################################
// ########################################################################################
// Alliance Taxes

$sdl->start_job('Alliance Taxes');
$sql = 'SELECT alliance_id,taxes FROM alliance WHERE taxes>0';
if(($q_alliances = $db->query($sql)) === false) {$sdl->log('<b>Error:</b> Query alliances failed! CONTINUED');} else
while($alliance = $db->fetchrow($q_alliances)) {
$tax=GetTaxes($alliance['taxes'],$alliance['alliance_id']);
$sql = 'UPDATE alliance SET taxes_1=taxes_1+'.$tax[0].',taxes_2=taxes_2+'.$tax[1].',taxes_3=taxes_3+'.$tax[2].' WHERE alliance_id = '.$alliance['alliance_id'];
    if(!$db->query($sql)) {$sdl->log('<b>Error:</b> Update alliances failed : "<i>'.$sql.'</i>" CONTINUED');}

}

$sdl->finish_job('Alliance Taxes');


// ########################################################################################
// ########################################################################################
// Ferengi Taxes

$sdl->start_job('Ferengi Taxes');

if (time()-$cfg_data['last_paytime']>3600*24)
{
$sdl->log('Start Payment');
$ferengi_players=$db->queryrow('SELECT COUNT(user_id) AS num FROM user WHERE user_active=1 AND user_auth_level=1 AND user_race=5');
if ($ferengi_players['num']>0 && $cfg_data['ferengitax_1']/$ferengi_players['num']+$cfg_data['ferengitax_2']/$ferengi_players['num']+$cfg_data['ferengitax_3']/$ferengi_players['num']>300)
{
$sdl->log('Start Payment2');
$db->query('UPDATE config SET ferengitax_1=0, ferengitax_2=0, ferengitax_3=0, last_paytime='.time());

$resource[0]=$cfg_data['ferengitax_1']/$ferengi_players['num']*5;
$resource[1]=$cfg_data['ferengitax_2']/$ferengi_players['num']*5;
$resource[2]=$cfg_data['ferengitax_3']/$ferengi_players['num']*5;

$player_qry=$db->query('SELECT u.user_id,u.user_name,u.user_capital, p.planet_owner, p.system_id AS planet_system,s.system_global_x,s.system_global_y
		FROM (user u)
		LEFT JOIN (planets p) ON p.planet_id = u.user_capital
        LEFT JOIN (starsystems s) ON s.system_id = p.system_id
        WHERE u.user_active=1 AND u.user_id=p.planet_owner AND u.user_race=5
		');

$sdl->log('Pay: '.$ferengi_players['num'].' ('.$db->num_rows().') Players with '.$cfg_data['ferengitax_1'].'/'.$cfg_data['ferengitax_2'].'/'.$cfg_data['ferengitax_3'].'!');


while(($user = $db->fetchrow($player_qry))==true)
{
$sdl->log('1');
   $log_data=array(
	'resource_1' => round($resource[0],0),
	'resource_2' => round($resource[1],0),
	'resource_3' => round($resource[2],0),
	);
$sdl->log('2');
   	add_logbook_entry($user['user_id'], LOGBOOK_FERENGITAX, 'Ferengisteuer',$log_data);
$sdl->log('3');
	$res=$resource[0]+$resource[1]+$resource[2];
	$ships=ceil($res/MAX_TRANSPORT_RESOURCES);
	if (1==$user['planet_system']) $distance=6;
	else
	{
	$distance = get_distance(array(5,7), array($user['system_global_x'], $user['system_global_y']));
    $velocity = warpf(6);
    $distance= ceil( ( ($distance / $velocity) / TICK_DURATION ) );
	}


    $db->query('INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,arrival_time) VALUES ("'.$user['user_capital'].'","'.$resource[0].'","'.$resource[1].'","'.$resource[2].'","'.($ACTUAL_TICK+$distance).'")');
	//send_fake_transporter(array(FERENGI_TRADESHIP_ID=>$ships), FERENGI_USERID,5 ,$user['user_capital']);
$sdl->log('4: '.$distance);

$sdl->log('5');
$sdl->log('Paid '.$user['user_name'].' ('.$user['user_id'].')!');
}

}
}
$sdl->finish_job('Ferengi Taxes');



// ########################################################################################
// ########################################################################################
// Remove old Resourcetrade
$sdl->start_job('Remove old Resourcetrade');
$sql = 'DELETE FROM resource_trade WHERE timestamp<'.(time()-3600*24*8);
if(($d_rtrade = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not remove old resourcetrade data! CONTINUED');
}
$sdl->log('Affected '.$db->affected_rows().' resourcetrades');
$sdl->finish_job('Remove old Resourcetrade');




// ########################################################################################
// ########################################################################################
// Remove non-existing pacts

// Ist das noch nï¿½ig? -Daywalker: keine ahnung, wenn das pactsystem keine leaks hat nicht (allianz gelï¿½cht ohne pakte etc). -Data: also theoretisch nicht... -Daywalker: da haste recht...


// ########################################################################################
// ########################################################################################
// World Scheduler

$sdl->start_job('World Scheduler');

include('world.php');

$sdl->finish_job('World Scheduler');


// ########################################################################################
// ########################################################################################
// Remove inactive player

$sdl->start_job('Remove inactive Player');



// Lï¿½chen noch nicht aktivierter Spieler
$sql = 'DELETE FROM user
        WHERE user_active = 2 AND
              user_registration_time < '.($game->TIME - (49 * 60 * 60));

if(!$db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not delete unactivated users! CONTINUED');
}



// Neusetzen von last_active/last_ip nach Rckkehr aus Urlaubsmodus
$sql = 'UPDATE user
        SET last_active = '.$game->TIME.',
            last_ip = "0.0.0.0"
        WHERE user_vacation_end = '.$ACTUAL_TICK;

if(!$db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not update back from vacation user_active data! CONTINUED');
}


// Suchen nach inaktiven Spielern
$sql = 'SELECT user_id, user_name, user_auth_level, user_points, user_planets
        FROM user
        WHERE last_active < '.($game->TIME - (30 * 24 * 60 * 60)).' AND
              user_vacation_end < '.$ACTUAL_TICK.' AND
              user_active=1 AND user_id>11';

if(!$q_user = $db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not query inactive user! CONTINUED');
}
else {
    while($user = $db->fetchrow($q_user)) {
        if($user['user_auth_level'] < STGC_DEVELOPER) {
            $sql = 'UPDATE user
                    SET user_active = 4
                    WHERE user_id = '.$user['user_id'];

            if(!$db->query($sql)) {
                $sdl->log('<b>Notice:</b> Could not set inactive user to user_active = 4! CONTINUED');
            }
            else {
                $sdl->log('- Going to delete inactive user (UID: '.$user['user_id'].', Name: '.$user['user_name'].', Auth: '.$user['user_auth_level'].', Points: '.$user['user_points'].', Planets: '.$user['user_planets'].')');
            }
        }
    }
}



// Suchen nach inaktiven Spielern #2
$sql = 'SELECT user_id, user_name, user_auth_level, user_points, user_planets
			FROM user
			WHERE user_active = 1
			AND user_registration_time < '.($game->TIME - (14 * 24 * 60 * 60)).'
			AND last_active < '.($game->TIME - (14 * 24 * 60 * 60)).'
			AND user_points <11
			AND user_vacation_end < '.$ACTUAL_TICK.' -20 *48
			AND user_id>11';

if(!$q_user = $db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not query inactive user #2! CONTINUED');
}
else {
    while($user = $db->fetchrow($q_user)) {
        if($user['user_auth_level'] < STGC_DEVELOPER) {
            $sql = 'UPDATE user
                    SET user_active = 4
                    WHERE user_id = '.$user['user_id'];

            if(!$db->query($sql)) {
                $sdl->log('<b>Notice:</b> Could not set inactive user #2 to user_active = 4! CONTINUED');
            }
            else {
                $sdl->log('- Going to delete inactive user #2 (UID: '.$user['user_id'].', Name: '.$user['user_name'].', Auth: '.$user['user_auth_level'].', Points: '.$user['user_points'].', Planets: '.$user['user_planets'].')');
            }
        }
    }
}





// Spieler löschen, die user_active = 4 haben
$sql = 'SELECT u.user_id, u.user_active, u.user_auth_level, u.user_points, u.user_planets, u.user_honor, u.user_alliance_status,
               a.alliance_id
        FROM (user u)
        LEFT JOIN (alliance a) ON a.alliance_id = u.user_alliance
        WHERE u.user_active = 4';

if(!$q_user = $db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not query deleteable user data! CONTINUED');
}
else {
    while($user = $db->fetchrow($q_user)) {
        if($user['user_auth_level'] != STGC_PLAYER || $user['user_id']<12) continue;

        if(!empty($user['alliance_id'])) {
            $sql = 'UPDATE alliance
                    SET alliance_points = alliance_points - '.$user['user_points'].',
                        alliance_planets = alliance_planets - '.$user['user_planets'].',
                        alliance_honor = alliance_honor - '.$user['user_honor'].'
                    WHERE alliance_id = '.$user['alliance_id'];

            $db->query($sql);
        }
        
        /*if($user['user_alliance_status'] == ALLIANCE_STATUS_OWNER) {
            // Ist er Präsident einer Allianz, machen wir einen anderen Admin der Allianz zum Prï¿½identen
            
            $sql = 'SELECT u.user_id
                    FROM (user u)
                    INNER JOIN (alliance a) ON a.alliance_id = u.user_alliance
                    WHERE u.user_alliance_status = '.ALLIANCE_STATUS_ADMIN.'
                    LIMIT 1';
                    
            if(($other_admin = $db->queryrow($sql)) === false) {
                $sdl->log('<b>Notice:</b> Could not query another alliance admin - possible alliance without president! CONTINUED!');
            }
            else {
                if(empty($other_admin['user_id'])) {
                    $sdl->log('<b>Notice:</b> Could not find another alliance admin - possible alliance without president! CONTINUED');
                }
                else {
                    $sql = 'UPDATE user
                            SET user_alliance_status = '.ALLIANCE_STATUS_OWNER.'
                            WHERE user_id = '.$other_admin['user_id'];
                            
                    $db->query($sql);
                }
            } 
        }*/

        $sql = 'UPDATE ship_templates
                SET removed = 1
                WHERE owner = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM ship_fleets
                WHERE user_id = '.$user['user_id'];

        $db->query($sql);

        $sql = 'DELETE FROM ships
                WHERE user_id = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM user_diplomacy
                WHERE user1_id = '.$user['user_id'].' OR
                      user2_id = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM tc_coords_memo
                WHERE user_id = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM user_templates
                WHERE user_id = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM bidding
                WHERE user = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM bidding_owed
                WHERE user = '.$user['user_id'].' OR
                      receiver = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM resource_trade
                WHERE player = '.$user['user_id'];

        $db->query($sql);


        $sql = 'DELETE FROM ship_trade
                WHERE user = '.$user['user_id'];

        $db->query($sql);

        $sql = 'DELETE FROM alliance_application
				WHERE application_user = '.$user['user_id'];
				
		$db->query($sql);

        $sql = 'DELETE FROM user
                WHERE user_id = '.$user['user_id'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not delete final user data! CONTIUED');
        }
        else {
            $sdl->log('- Deleted user #'.$user['user_id'].' because user_active = 4');
        }
    }
}


// Wenn Lï¿½ch-Bestï¿½igung nach 5 Stunden nicht erfolgt ist, zurcksetzen

$sql = 'SELECT user_id, user_name FROM user WHERE user_active = 3 AND last_active < '.($game->TIME - (3 * 60 * 60));

if(!$undel_user = $db->query($sql)) {
    $sdl->log('<b>Notice:</b> Could not select user_active3 data! CONTINUED');
}
else {
  while($user = $db->fetchrow($undel_user)) {
  
    $sdl->log('- User <b>#'.$user['user_id'].'</b> ( '.$user['user_name'].'  ) hat Löschung beantragt, aber nicht vollzogen! <Fakeversuch?>');

    $sql = 'UPDATE user
            SET user_active = 1
            WHERE user_active = 3 AND
            last_active < '.($game->TIME - (3 * 60 * 60));
    
    if(!$db->query($sql)) {
      $sdl->log('<b>Notice:</b> Could not select user_active3 data! CONTINUED');
    }
  
  }
}


$sdl->finish_job('Remove inactive Player');

// ########################################################################################
// ########################################################################################
// Ghostfleets beheben (aus Fix_all rübergenommen)

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




// ########################################################################################
// ########################################################################################
// Beenden und Log schlieï¿½n

$sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font>'."\n".'Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>

