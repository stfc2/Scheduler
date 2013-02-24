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
// SQL-Abstractions-Class

class sql {

	var $login = array();
	var $error = array();

	var $link_id = 0;
	var $query_id = 0;

	var $i_query = 0;

	var $already_reconnected = -1; // With first connection become 0


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

	function lock() {
		$tables = func_get_args();
		$tables[] = 'planets';
		$tables[] = 'starsystems';
		$tables[] = 'user';
		$n_tables = count($tables);
		$table_str = array();
		if($n_tables == 0) return;
		for($i = 0; $i < $n_tables; ++$i) {
			$table_str[] = $tables[$i].' WRITE';
		}
		$sql = 'LOCK TABLES '.implode(',', $table_str);
		if(!mysql_query($sql, $this->link_id)) {
			$this->raise_error(false, false, $sql);
		}
		return true;
	}

	function unlock() {
		if(!mysql_query('UNLOCK TABLES', $this->link_id)) {
		  $this->raise_error(false, false, 'UNLOCK TABLES');
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
// App-Class (for the moment only Logging/Timing)

class scheduler {
	var $start_values = array();

	function log($message,$file = '') {
		if($file=='')
			$fp = fopen(TICK_LOG_FILE, 'a');
		else
			$fp = fopen($file, 'a');
		fwrite($fp, $message."<br>\n");
		fclose($fp);
	}

	function start_job($name,$file = '') {
		global $db;

		$this->log('<font color=#0000ff>Starting <b>'.$name.'</b>...</font>',$file);

		$this->start_values[$name] = array( time() + microtime() , $db->i_query );
	}

	function finish_job($name,$file = '') {
		global $db;

		$this->log('<font color=#0000ff>Executed <b>'.$name.'</b> (</font><font color=#ff0000>queries: '.($db->i_query - $this->start_values[$name][1]).'</font><font color=#0000ff>) in </font><font color=#009900>'.round( (time() + microtime()) - $this->start_values[$name][0] , 4).' secs</font><br>',$file);

	}
}


// ########################################################################################
// ########################################################################################
// other functions

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
	$result = 0.25*(3*((pow(((3*$planet['rateo_1'])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
	if($result < 10) $round = 1;
	else $round = 0;
	return round($result, $round);
}

function ResourcesPerTickMineral(&$planet) {
	$rid=1;
	global $RACE_DATA,$PLANETS_DATA, $addres;
	$result = 0.25*(3*((pow(((3*$planet['rateo_2'])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
	if($result < 10) $round = 1;
	else $round = 0;
	return round($result, $round);
}

function ResourcesPerTickLatinum(&$planet) {
	$rid=2;
	global $RACE_DATA,$PLANETS_DATA, $addres;
	$result = 0.2*(3*((pow(((3*$planet['rateo_3'])*(1+$planet['building_'.($rid+2)])),1.35))/100*(50+ (50*(1/($planet['building_'.($rid+2)]*100+100))*($planet['workermine_'.($rid+1)]+100))))*($RACE_DATA[$planet['user_race']][9+$rid]*($addres[$planet['research_5']]*$RACE_DATA[$planet['user_race']][20])));
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

function GetBuildingPrice($building,$resource,$planet,$user_race)
{
	global $RACE_DATA, $BUILDING_DATA, $PLANETS_DATA;
	$pow_factor=2;

	$price=round(pow($BUILDING_DATA[$building][$resource]*($planet['building_'.($building+1)]+1),$pow_factor),0);

	// Orbital guns costs reduction
	if ($building==9 || $building==12)
		$price=$BUILDING_DATA[$building][$resource]/100*(100-2.5*$planet['research_3']);

	$price*=$RACE_DATA[$user_race][5];
	$price*=$PLANETS_DATA[$planet['planet_type']][4];

	// Cost in metal
	if($resource==0) {
		$price*=$RACE_DATA[$user_race][23];
	}
	// Cost in minerals
	elseif($resource==1) {
		$price*=$RACE_DATA[$user_race][24];
	}
	// Cost in latinum
	elseif($resource==2) {
		$price*=$RACE_DATA[$user_race][25];
	}

	return round($price,0);
}

?>