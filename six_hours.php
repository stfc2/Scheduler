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
// Startup Config

// include game definitions, path url and so on
include('config.script.php');

error_reporting(E_ERROR);
ini_set('memory_limit', '200M');
set_time_limit(300); // 5 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'The scheduler can only be called by CLI!'; exit;
}

define('TICK_LOG_FILE', $game_path . 'logs/sixhours/tick_'.date('d-m-Y', time()).'.log');
define('TICK_LOG_FILE_NPC', TICK_LOG_FILE);
define('IN_SCHEDULER', true); // we are in the scheduler...

// include commons classes and functions
include('commons.php');

// include BOT class
include('NPC_BOT.php');


// ########################################################################################
// ########################################################################################
// Init

$starttime = ( microtime() + time() );

include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/text_races.php');
include($game_path . 'include/race_data.php');
include($game_path . 'include/ship_data.php');
include($game_path . 'include/libs/moves.php');

$sdl = new scheduler();
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

$game = new game();

$sdl->log('<br><br><br><b>-------------------------------------------------------------</b><br>'.
          '<b>Starting SixHours-Script at '.date('d.m.y H:i:s', time()).'</b>');

if(($cfg_data = $db->queryrow('SELECT * FROM config')) === false) {
    $sdl->log('- Fatal: Could not query tick data! ABORTED');
  exit;
}

$ACTUAL_TICK = $cfg_data['tick_id'];
$NEXT_TICK = ($cfg_data['tick_time'] - time());
$LAST_TICK_TIME = ($cfg_data['tick_time']-5*60);
$STARDATE = $cfg_data['stardate'];



/*
Example Job:

$sdl->start_job('Mine Job');

do something ... during error / message:
  $sdl->log('...');
best also - before, so it's apart from the other messages, also: $sdl->log('- this was not true');

$sdl->finish_job('Mine Job'); // terminates the timer

*/



// Ok, now we try to update the user_max_colo
$sdl->start_job('Recalculate colony ship limits');

$sql = 'SELECT user_points FROM user ORDER BY user_points DESC LIMIT 30,1';
if(!$limit = $db->queryrow($sql)) {
	$sdl->log('<b>Error:</b> Could not query user points data! CONTINUED');
	$limit['user_points'] = 2000;
}

// Who is ABOVE the threshold can have only one colony ship at a time!!!
$sql = 'UPDATE user SET user_max_colo = 1 WHERE user_points > '.$limit['user_points'];
if(!$db->query($sql))
	$sdl->log('<b>Error:</b> Cannot set user_max_colo to 1! CONTINUED');

//Who is equal to or smaller than the threshold, can do as many colony ship as he want!!!
$sql = 'UPDATE user SET user_max_colo = 0 WHERE user_points <= '.$limit['user_points'];
if(!$db->query($sql))
	$sdl->log('<b>Error:</b> Cannot set user_max_colo to 0! CONTINUED');

$sdl->finish_job('Recalculate colony ship limits');



// 31/10/08 - AC After 3 months owned by Settlers, planets returns inhabitated and becomes of D class.
$sdl->start_job('Exausted Colony planets');

$today = time();
$oneWeek = (7 * 24 * 60 * 60);
$threeMonths = $oneWeek * 12;
$planet_type = 'd';

// Select ALL exausted planets
$sql = 'SELECT planet_id FROM planets
        WHERE planet_owner = '.INDEPENDENT_USERID.' AND
              '.$today.'  - planet_owned_date > '.$threeMonths;

if(($exausted_planets = $db->queryrowset($sql)) === false) {
	$sdl->log('<b>Error:</b> Could not query exausted planets!');
}

// Remove them to the settlers
$sql = 'UPDATE planets SET planet_name = "Inesplorato",
                           planet_type = "'.$planet_type.'",
                           planet_owner = 0,
                           planet_points = 0,
                           research_1 = 0,
                           research_2 = 0,
                           research_3 = 0,
                           research_4 = 0,
                           research_5 = 0,
                           resource_1 = 0,
                           resource_2 = 0,
                           resource_3 = 0,
                           resource_4 = 0,
                           add_1 = 0,
                           add_2 = 0,
                           add_3 = 0,
                           add_4 = 0,
                           max_resources = 0,
                           max_worker = 0,
                           max_units = 0,
                           min_security_troops = 0,
                           planet_insurrection_time = 0,
                           building_1 = 0,
                           building_2 = 0,
                           building_3 = 0,
                           building_4 = 0,
                           building_5 = 0,
                           building_6 = 0,
                           building_7 = 0,
                           building_8 = 0,
                           building_9 = 0,
                           building_10 = 0,
                           building_11 = 0,
                           building_12 = 0,
                           building_13 = 0,
                           unit_1 = 0,
                           unit_2 = 0,
                           unit_3 = 0,
                           unit_4 = 0,
                           unit_5 = 0,
                           unit_6 = 0,
                           workermine_1 = 0,
                           workermine_2 = 0,
                           workermine_3 = 0,
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
                           unittrainid_1 = 0,
                           unittrainid_2 = 0,
                           unittrainid_3 = 0,
                           unittrainid_4 = 0,
                           unittrainid_5 = 0,
                           unittrainid_6 = 0,
                           unittrainid_7 = 0,
                           unittrainid_8 = 0,
                           unittrainid_9 = 0,
                           unittrainid_10 = 0,
                           unittrainnumber_1 = 0,
                           unittrainnumber_2 = 0,
                           unittrainnumber_3 = 0,
                           unittrainnumber_4 = 0,
                           unittrainnumber_5 = 0,
                           unittrainnumber_6 = 0,
                           unittrainnumber_7 = 0,
                           unittrainnumber_8 = 0,
                           unittrainnumber_9 = 0,
                           unittrainnumber_10 = 0,
                           unittrainnumberleft_1 = 0,
                           unittrainnumberleft_2 = 0,
                           unittrainnumberleft_3 = 0,
                           unittrainnumberleft_4 = 0,
                           unittrainnumberleft_5 = 0,
                           unittrainnumberleft_6 = 0,
                           unittrainnumberleft_7 = 0,
                           unittrainnumberleft_8 = 0,
                           unittrainnumberleft_9 = 0,
                           unittrainnumberleft_10 = 0,
                           unittrainendless_1 = 0,
                           unittrainendless_2 = 0,
                           unittrainendless_3 = 0,
                           unittrainendless_4 = 0,
                           unittrainendless_5 = 0,
                           unittrainendless_6 = 0,
                           unittrainendless_7 = 0,
                           unittrainendless_8 = 0,
                           unittrainendless_9 = 0,
                           unittrainendless_10 = 0,
                           unittrain_actual = 0,
                           unittrainid_nexttime = 0,
                           unittrain_error = 0,
                           building_queue = 0,
                           planet_altname = "",
                           planet_surrender = 0
        WHERE planet_owner = '.INDEPENDENT_USERID.' AND
              '.$today.'  - planet_owned_date > '.$threeMonths;

if(!$db->query($sql))
{
	$sdl->log('<b>Error:</b> Could not remove exausted planet to the settlers!');
}

$planets_ids = array();
$planets_num = 0;

foreach($exausted_planets as $i => $cur_planet) {
	$planets_ids[] = $cur_planet['planet_id'];
	$planets_num++;

	// Recalculate rateo for each planet
	$rateo_1 = round(($PLANETS_DATA[$planet_type][0] + ((400 - mt_rand(0, 800))*0.001)), 2);
	if($rateo_1 < 0.1) $rateo_1 = 0.1;
	$rateo_2 = round(($PLANETS_DATA[$planet_type][1] + ((350 - mt_rand(0, 700))*0.001)), 2);
	if($rateo_2 < 0.1) $rateo_2 = 0.1;
	$rateo_3 = round(($PLANETS_DATA[$planet_type][2] + ((300 - mt_rand(0, 600))*0.001)), 2);
	if($rateo_3 < 0.1) $rateo_3 = 0.1;
	$rateo_4 = $PLANETS_DATA[$planet_type][3];

	$sql = 'UPDATE planets SET rateo_1 = '.$rateo_1.',
	                           rateo_2 = '.$rateo_2.',
	                           rateo_3 = '.$rateo_3.',
	                           rateo_4 = '.$rateo_4.'
	        WHERE planet_id = '.$cur_planet['planet_id'];

	if(!$db->query($sql))
	{
		$sdl->log('<b>Error:</b> Could set new rateo values to planet <b>#'.$cur_planet['planet_id'].'</b>');
	}
}

if($planets_num != 0) {
	$sql = 'DELETE FROM planet_details WHERE planet_id IN ('.implode(',', $planets_ids).') AND log_code = 300';
	if(!$db->query($sql)) {
		$sdl->log('<b>Error:</b> Could not delete settlers moods!');
	}

	$sdl->log('Exausted Colony planet: <b>'.$planets_num.'</b> returned uninhabited');
}
$sdl->finish_job('Exausted Colony planets');



// Check of Settler Planets OMG!!! LOT OF TIME USED!!!
$sdl->start_job('Colony DB checkup');
$sql = 'SELECT planet_id FROM planets WHERE planet_owner = '.INDEPENDENT_USERID;
$planets_restored = 0;
$settlers_planets = $db->query($sql);
while($fetch_planet=$db->fetchrow($settlers_planets)) {
	$sql='SELECT * FROM planet_details WHERE planet_id = '.$fetch_planet['planet_id'].' AND log_code = 300';
	if(!$db->queryrow($sql)) {
		$sdl->log('Colony Exception: planet '.$fetch_planet['planet_id'].' with missing moods information! Restoring with default data...');
		$sql='INSERT INTO planet_details SET planet_id  = '.$fetch_planet['planet_id'].', 
		                  user_id = '.INDEPENDENT_USERID.',
		                  log_code   = 300, 
		                  timestamp  = '.time();
		//$sdl->log('Colony SQL: '.$sql);
		if(!$db->query($sql))
		{
			$sdl->log('<b>Error:</b> Bot: Could not insert default colony moods data!');
		}
		$planets_restored++;
	}
}
if($planets_restored != 0) $sdl->log('Colony Report: Restored '.$planets_restored.' default planets mood data');
$sdl->finish_job('Colony DB checkup');



// Check of miners available on Borg planets
$sdl->start_job('Check miners on Borg planets');
$mines_upgraded = 0;
$miners = array();
$mines_level = array();
$borg = new NPC($db,$sdl);
$borg -> LoadNPCUserData(BORG_USERID);

// We need many infos here, for StartBuild() function
$sql = 'SELECT * FROM planets WHERE planet_owner = '.BORG_USERID;
$borg_planets = $db->query($sql);
while($planet=$db->fetchrow($borg_planets)) {
	// If we have at least a workers slot
	if($planet['resource_4'] > 100) {
		$mine = 0;
		$max_reached = 0;
		$miners_updated = false;
		$miners[0] = $planet['workermine_1'];
		$miners[1] = $planet['workermine_2'];
		$miners[2] = $planet['workermine_3'];
		$workers = $planet['resource_4'];
		$mines_level[0] = $planet['building_2'];
		$mines_level[1] = $planet['building_3'];
		$mines_level[2] = $planet['building_4'];

		while($workers > 100 && $max_reached < 3) {
			// If there is space for new workers
			if($miners[$mine] < (($mines_level[$mine]*100)+100)) {
				$miners[$mine]+=100;
				$workers-=100;
				$miners_updated = true;
			}
			// There is no space available, perhaps we can increase the mine level?
			else {
				$max_reached++;
			}
			$mine++;
			if($mine > 2)
				$mine = 0;
		}

		// If we exit from the while because there isn't space available on the mines
		if($max_reached) {
			// Search the mine with lowest level
			$min = $mines_level[0];
			$mine = 1;
			for($i = 1;$i < 3;$i++)
				if($mines_level[$i] < $min)
				{
					$min = $mines_level[$i];
					$mine = $i + 1;
				}

			// Start to build a new mine level
			$res = $borg->StartBuild($ACTUAL_TICK,$mine,$planet);
			if($res == BUILD_ERR_ENERGY)
				$res = $borg->StartBuild($ACTUAL_TICK,4,$planet);
		}

		if($miners_updated) {
			$sql='UPDATE planets SET
			             workermine_1 = '.$miners[0].',
			             workermine_2 = '.$miners[1].',
			             workermine_3 = '.$miners[2].',
			             resource_4 = '.$workers.'
			      WHERE planet_id = '.$planet['planet_id'];

			$sdl->log('Mines workers increased to: '.$miners[0].'/'.$miners[1].'/'.$miners[2].' on Borg planet: <b>#'.$planet['planet_id'].'</b>');

			$mines_upgraded++;

			if(!$db->query($sql)) {
				$sdl->log('<b>Error:</b> could not update mines workers!');
			}
		}
	}
}
if($mines_upgraded != 0) $sdl->log('Upgraded <b>'.$mines_upgraded.'</b> mines on Borg planets');
$sdl->finish_job('Check miners on Borg planets');



// ########################################################################################
// ########################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished SixHours-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
