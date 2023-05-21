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


// #######################################################################################
// #######################################################################################
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


// #######################################################################################
// #######################################################################################
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

if($cfg_data['tick_stopped']) {
    $sdl->log('Finished SixHours-Script in '.round((microtime()+time())-$starttime, 4).' secs<br>Tick has been stopped (Unlock in table "config")');
    exit;
}

if(empty($ACTUAL_TICK)) {
    $sdl->log('Finished SixHours-Script in '.round((microtime()+time())-$starttime, 4).' secs<br>- Fatal: empty($ACTUAL_TICK) == true');
    exit;
}



/*
Example Job:

$sdl->start_job('Mine Job');

do something ... during error / message:
  $sdl->log('...');
best also - before, so it's apart from the other messages, also: $sdl->log('- this was not true');

$sdl->finish_job('Mine Job'); // terminates the timer

*/
// #######################################################################################
// #######################################################################################
// Ok, now we try to update the user_max_colo
$sdl->start_job('Recalculate colony ship limits');

// Activate colony limit only if there are at least 30 players
$sql = 'SELECT count(user_id) as num_users FROM user 
        WHERE user_active = 1 AND user_auth_level = '.STGC_PLAYER;
if(!$players = $db->queryrow($sql)) {
    $sdl->log('<b>Error:</b> Could not query users number! SKIP');
}
elseif($players['num_users'] > 30) {
    $sql = 'SELECT user_points FROM user ORDER BY user_points DESC LIMIT 30,1';
    if(!$limit = $db->queryrow($sql)) {
        $sdl->log('<b>Error:</b> Could not query user points data! CONTINUED');
        $limit['user_points'] = 2000;
    }
    // Are there "big" players present?
    elseif($limit['user_points'] <= 2000) {
        $limit['user_points'] = 2000;
    }

    // Who is ABOVE the threshold can have only five colony ship at a time!!!
    $sql = 'UPDATE user SET user_max_colo = 5 WHERE user_points > '.$limit['user_points'];
    if(!$db->query($sql))
        $sdl->log('<b>Error:</b> Cannot set user_max_colo to 5! CONTINUED');

    //Who is equal to or smaller than the threshold, can do as many colony ship as he want!!!
    $sql = 'UPDATE user SET user_max_colo = 0 WHERE user_points <= '.$limit['user_points'];
    if(!$db->query($sql))
        $sdl->log('<b>Error:</b> Cannot set user_max_colo to 0! CONTINUED');
}
$sdl->finish_job('Recalculate colony ship limits');

// #######################################################################################
// #######################################################################################
// Protect point maintenance
$sdl->start_job('Protect Point System Maintenance');

$sql = 'SELECT user_id, user_race, user_protect_cap, user_protect_level FROM user WHERE user_active = 1 AND user_auth_level <= '.STGC_DEVELOPER;

$user_lst = $db->queryrowset($sql);

foreach ($user_lst AS $user_to_proc) {
    $over_flag = 0;
    
    $q_res = $db->queryrow('SELECT SUM(building_1) AS value_1 FROM planets WHERE planet_owner = '.$user_to_proc['user_id']);
    
    $value_1 = $q_res['value_1'];
    
    $q_res = $db->queryrow('SELECT SUM(building_1) AS value_2 FROM planets INNER JOIN starsystems USING (system_id) WHERE system_owner = '.$user_to_proc['user_id'].' AND system_closed = 2 AND planet_owner = '.$user_to_proc['user_id']);
    
    $value_2 = $q_res['value_2'];
    
    $q_res = $db->queryrow('SELECT SUM(building_1) AS value_3 FROM planets INNER JOIN starsystems USING (system_id) WHERE system_owner = '.$user_to_proc['user_id'].' AND system_closed = 1 AND planet_owner = '.$user_to_proc['user_id']);
    
    $value_3 = $q_res['value_3'];
    
    $build_ratio = round((($value_2 + $value_3)*100/$value_1) * 0.01, 2);
    
    $build_ratio = 1.0 - $build_ratio;
    
    if($build_ratio < 0) {$build_ratio = 0;}
    
    if($build_ratio > 1.0) {$build_ratio = 1.0;}
    
    if($user_to_proc['user_protect_cap'] < $user_to_proc['user_protect_level']) {$over_flag = 1;}
    
    $new_ratio = round((((0.25/2) * $build_ratio) + ((0.25/2) * $RACE_DATA[$user_to_proc['user_race']][20]) - (0.5 * $over_flag) ), 2);
    
    $db->query('UPDATE user SET user_protect_ratio = '.$new_ratio.', recompute_protect_ratio = 0 WHERE user_id = '.$user_to_proc['user_id']);
}


$sdl->finish_job('Protect Point System Maintenance');

// #######################################################################################
// #######################################################################################
$sdl->start_job('Orion Cartel Operations');

$sql = 'SELECT p.planet_id, ss.system_orion_alert
        FROM planets p
        INNER JOIN starsystems ss USING (system_id)
        LEFT JOIN settlers_events se ON (p.planet_id = se.planet_id AND se.event_code = 107 AND se.user_id = '.ORION_USERID.')
        WHERE p.planet_owner = '.INDEPENDENT_USERID.' AND ss.system_orion_alert > 0 AND se.event_code IS NULL';

$op_list = $db->queryrowset($sql);

$tally = 0;

foreach ($op_list AS $op_item) {
        $tally++;
        $sql = 'INSERT INTO settlers_events
                (planet_id, user_id, event_code, timestamp, tick, awayteamship_id, awayteam_startlevel, unit_1, unit_2, unit_3, unit_4, awayteam_alive, event_status)
                VALUES  ('.$op_item['planet_id'].', '.ORION_USERID.', 107, '.time().', '.$ACTUAL_TICK.', 0, 20, 40, 25, 20, 3, 1, 1)';
        if(!$db->query($sql)) {
            $sdl->log(MV_M_DATABASE, 'Could not insert settlers event data!'.$sql);
            $tally--;
        }
        if($op_item['system_orion_alert'] >= 3) {
            $sql = 'INSERT INTO settlers_relations
                    (planet_id, user_id, race_id, timestamp, log_code, mood_modifier)
                    VALUES ('.$op_item['planet_id'].', '.ORION_USERID.', 7, '.time().', 1, 10)';
            if(!$db->query($sql)) {
                $sdl->log(MV_M_DATABASE, 'Could not insert Orion First Contact data!'.$sql);
            }            
        }
}

if($tally > 0) {$sdl->log('Operation started: '.$tally);}

$sdl->finish_job('Orion Cartel Operations');

// #######################################################################################
// #######################################################################################
$sdl->start_job('Settlers Events');

// Prima fase, muoviamo il mood Founder
$sql = 'SELECT se.planet_id, event_code, mood_modifier FROM settlers_events se INNER JOIN settlers_relations sr USING (planet_id) WHERE event_status = 1 AND log_code = 30 ORDER BY planet_id, event_code';
$events_query = $db->query($sql);
$counter = $db->num_rows($events_query);
if($counter > 0)
{
    $events_list = $db->fetchrowset($events_query);
    $pre_item['planet_id'] = $events_list[0]['planet_id'];
    $pre_item['mood_modifier'] = $events_list[0]['mood_modifier'];
    $cache_mood = 0;
    foreach($events_list as $events_item)
    {
        if($pre_item['planet_id'] != $events_item['planet_id'])
        {
            $new_mood = $pre_item['mood_modifier'] + $cache_mood;
            $new_mood = max( 0, $new_mood);
            $new_mood = min(80, $new_mood);
            if($new_mood != $pre_item['mood_modifier'])
            {
                $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = 30';
                if(!$db->query($sql)) {
                    $sdl->log('<b>Error:</b> could not update settlers relations!!! E0: '.$sql);
                }
                $sdl->log('DEBUG: sql E0:'.$sql);
            }
            $cache_mood = 0;            
        }
        switch($events_item['event_code'])
        {
            case '100':
            case '101':
            case '102':
            case '103':
            case '104':
            case '105':
                $cache_mood -= 15;
            break;
            case '120':
            case '121':
            case '122':
            case '123':
            case '124':
                $cache_mood += 10;
            break;
        }
        $pre_item['planet_id'] = $events_item['planet_id'];
        $pre_item['mood_modifier'] = $events_item['mood_modifier'];
    }
    $new_mood = $pre_item['mood_modifier'] + $cache_mood;
    $new_mood = max( 0, $new_mood);
    $new_mood = min(80, $new_mood);
    $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = 30';
    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b> could not update settlers relations!!! E1: '.$sql);
    }
    $sdl->log('DEBUG: sql E1:'.$sql);
}

// Prima fase punto cinque, muoviamo il mood Benefactor
$sql = 'SELECT se.planet_id, event_code, mood_modifier FROM settlers_events se INNER JOIN settlers_relations sr USING (planet_id) WHERE se.user_id = sr.user_id AND event_status = 1 AND log_code = 36 ORDER BY planet_id, event_code';
$events_query = $db->query($sql);
$counter = $db->num_rows($events_query);
if($counter > 0)
{
    $events_list = $db->fetchrowset($events_query);
    $pre_item['planet_id'] = $events_list[0]['planet_id'];
    $pre_item['mood_modifier'] = $events_list[0]['mood_modifier'];
    $cache_mood = 0;
    foreach($events_list as $events_item)
    {
        if($pre_item['planet_id'] != $events_item['planet_id'])
        {
            $new_mood = $pre_item['mood_modifier'] + $cache_mood;
            $new_mood = max(10, $new_mood);
            $new_mood = min(85, $new_mood);
            if($new_mood != $pre_item['mood_modifier'])
            {
                $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = 36';
                if(!$db->query($sql)) {
                    $sdl->log('<b>Error:</b> could not update settlers relations!!! E2: '.$sql);
                }
                $sdl->log('DEBUG: sql E2:'.$sql);
            }
            $cache_mood = 0;            
        }
        switch($events_item['event_code'])
        {
            case '130':
            case '131':
            case '132':
            case '133':
            case '134':
            case '135':
                $cache_mood += 15;
            break;
        }
        $pre_item['planet_id'] = $events_item['planet_id'];
        $pre_item['mood_modifier'] = $events_item['mood_modifier'];
    }
    $new_mood = $pre_item['mood_modifier'] + $cache_mood;
    $new_mood = max(10, $new_mood);
    $new_mood = min(85, $new_mood);
    $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = 36';
    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b> could not update settlers relations!!! E3: '.$sql);
    }
    $sdl->log('DEBUG: sql E3:'.$sql);
}

// Seconda fase, muoviamo i vari mood negativi! IMPORTANTE. Mantenete gli event code di mosse anti controllore sotto il 120
$crime_strenght = [0,10,10,15,20];
$crime_cap      = [0,-20,-35,-60,-85];
$sql = 'SELECT se.planet_id, p.best_mood_user AS controller, event_code, ss.system_orion_alert AS orion_alert 
        FROM settlers_events se 
        INNER JOIN planets p USING (planet_id) 
        INNER JOIN starsystems ss USING (system_id) 
        WHERE event_status = 1 AND se.user_id <> p.best_mood_user AND event_code < 120 
        ORDER BY se.planet_id, event_code';
$events_query = $db->query($sql);
$counter = $db->num_rows($events_query);
if($counter > 0)
{
    $events_list = $db->fetchrowset($events_query);
    $pre_item['planet_id'] = $events_list[0]['planet_id'];
    $pre_item['controller'] = $events_list[0]['controller'];
    $pre_item['event_code'] = $events_list[0]['event_code'];
    $pre_item['orion_alert'] = $events_list[0]['orion_alert'];
    $cache_mood = array(28 => 0, 29 => 0, 35 => 0);
    foreach($events_list as $events_item)
    {
        if($pre_item['planet_id'] != $events_item['planet_id'])
        {
            foreach($cache_mood as $key => $mood_item)
            {
                if($mood_item == 0) continue;
                $sql = 'SELECT mood_modifier FROM settlers_relations WHERE planet_id = '.$pre_item['planet_id'].' AND user_id = '.$pre_item['controller'].' AND log_code = '.$key;
                $lq_query = $db->queryrow($sql);
                if(isset($lq_query['mood_modifier']))
                {
                    $new_mood = $lq_query['mood_modifier'] + $mood_item;
                    //$new_mood = max(-85, $new_mood);
                    $new_mood = max(($key == 35 ? $crime_cap[$pre_item['orion_alert']] : -85), $new_mood);
                    $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].'  AND user_id = '.$pre_item['controller'].' AND log_code = '.$key;
                    $sdl->log('DEBUG: SQL E4A:'.$sql);
                    if(!$db->query($sql)) {
                        $sdl->log('<b>Error:</b> could not update settlers relations!!! E2: '.$sql);
                    }                    
                }
                else
                {
                    //$new_mood = max(-85, $mood_item);
                    $new_mood = max(($key == 35 ? $crime_cap[$pre_item['orion_alert']] : -85), $mood_item);                    
                    $sql = 'INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ("'.$pre_item['planet_id'].'", "'.$pre_item['controller'].'", '.time().', '.$key.', '.$new_mood.')';
                    $sdl->log('DEBUG: SQL E4B:'.$sql);
                    if(!$db->query($sql)) {
                        $sdl->log('<b>Error:</b> could not update settlers relations!!! E3: '.$sql);
                    }                    
                }
            }
            $cache_mood[28] = 0;
            $cache_mood[29] = 0;
            $cache_mood[35] = 0;            
        }
        switch($events_item['event_code'])
        {
            case '100':
            case '101':
            case '102':
            case '103':
                $cache_mood[28] -= 20;
            break;
            case '104':
            case '105':
                $cache_mood[29] -= 20;
            break;
            case '107':
                $cache_mood[35] -= $crime_strenght[$events_item['orion_alert']];
            break;
        }
        $pre_item['planet_id'] = $events_item['planet_id'];
        $pre_item['controller'] = $events_item['controller'];
        $pre_item['event_code'] = $events_item['event_code'];
        $pre_item['orion_alert'] = $events_item['orion_alert'];
    }
    foreach($cache_mood as $key => $mood_item)
    {
        if($mood_item == 0) continue;
        $sql = 'SELECT mood_modifier FROM settlers_relations WHERE planet_id = '.$pre_item['planet_id'].' AND user_id = '.$pre_item['controller'].' AND log_code = '.$key;
        $lq_query = $db->queryrow($sql);
        if(isset($lq_query['mood_modifier']))
        {
            $new_mood = $lq_query['mood_modifier'] + $mood_item;
            //$new_mood = max(-85, $new_mood);
            $new_mood = max(($key == 35 ? $crime_cap[$pre_item['orion_alert']] : -85), $new_mood);
            $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].'  AND user_id = '.$pre_item['controller'].' AND log_code = '.$key;
            $sdl->log('DEBUG: SQL E4C:'.$sql);
            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b> could not update settlers relations!!! E4: '.$sql);
            }                    
        }
        else
        {
            //$new_mood = max(-85, $mood_item);
            $new_mood = max(($key == 35 ? $crime_cap[$pre_item['orion_alert']] : -85), $new_mood);            
            $sql = 'INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$pre_item['planet_id'].', '.$pre_item['controller'].', '.time().', '.$key.', '.$new_mood.')';
            $sdl->log('DEBUG: SQL E4D:'.$sql);
            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b> could not update settlers relations!!! E5: '.$sql);
            }                    
        }        
    }    
}

// Terza parte, easy

    $sql = 'UPDATE settlers_relations SET mood_modifier = mood_modifier + 5 WHERE log_code IN (28, 29, 35)';

    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b> could not update settlers relations!!! E3: '.$sql);
    }                        

    $sql = 'UPDATE settlers_relations SET mood_modifier = mood_modifier - 5 WHERE log_code = 36 AND mood_modifier > 10';
    
    $db->query($sql);    
    
    $sql = 'DELETE FROM settlers_relations WHERE log_code IN (28, 29, 35) AND mood_modifier = 0';

    $db->query($sql);

    $sql = 'UPDATE settlers_events SET count_ok = count_ok +1, event_result = 1 WHERE event_code = 150 AND event_status = 1 AND count_crit_ok = 0 AND ('.$ACTUAL_TICK.' - tick) > 80';

    $db->query($sql);

    $sql = 'UPDATE settlers_events SET count_ok = 0, count_crit_ok = 1 WHERE event_code = 150 AND event_status = 1 AND count_crit_ok = 0 AND count_ok = 4 AND ('.$ACTUAL_TICK.' - tick) > 80';

    $db->query($sql);
    
    $sql = 'UPDATE settlers_events SET count_ok = count_ok +1 WHERE awayteam_alive = 1 AND event_status = 1 AND event_code IN (100, 101, 102, 103) AND planet_id IN (SELECT planet_id FROM settlers_relations WHERE log_code = 28 AND mood_modifier = -80)';
    
    $db->query($sql);
    
    $sql = 'SELECT settlers_events.user_id, settlers_events.planet_id
            FROM settlers_events
            INNER JOIN settlers_relations USING (planet_id)
            WHERE settlers_relations.log_code = 28 AND
                  settlers_relations.mood_modifier = -80 AND
                  settlers_events.event_code IN (100, 101, 102, 103) AND
                  settlers_events.awayteam_alive = 1 AND
                  settlers_events.event_status = 1';
    
    $predator_list = $db->queryrowset($sql);
    
    foreach ($predator_list as $predator_item) {
        // Use do_simple_relation() code in action_27 here
        //$sql = 'INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$predator_item['planet_id'].', '.$predator_item['user_id'].', '.time().', 23, 5) ON DUPLICATE KEY UPDATE mood_modifier = mood_modifier + 5';
        //$db->query($sql);
        $sql='SELECT * FROM settlers_relations WHERE planet_id = '.$predator_item['planet_id'].' AND user_id = '.$predator_item['user_id'].' AND log_code = 23';

        $pre_data = $db->queryrow($sql);

        if(!isset($pre_data['mood_modifier']))
        {
            $sql='INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$predator_item['planet_id'].', '.$predator_item['user_id'].', '.time().', 23, 5';
        }
        else
        {
            $new_mood = min(80, ($pre_data['mood_modifier'] + 5));

            $sql='UPDATE settlers_relations SET mood_modifier = '.$new_mood.', timestamp = '.time().'
                    WHERE planet_id = '.$predator_item['planet_id'].' AND user_id = '.$predator_item['user_id'].' AND log_code = 23';
        }

        $sdl->log('DEBUG: SQL E5A:'.$sql);

        $db->query($sql);        
    }
    
    // Abbiamo ancora bisogno di questa?
    $db->query('UPDATE settlers_relations SET mood_modifier = 80 WHERE log_code = 23 AND mood_modifier > 80');
    
    $diplo_list = $db->queryrowset('SELECT user_id, user_settler_best FROM user WHERE user_auth_level = 1 AND user_active = 1 AND user_settler_best > 0');
    
    foreach ($diplo_list AS $diplo_item) {
        $add_diplo_points = 0.25*$diplo_item['user_settler_best'];
        $sql = 'UPDATE user SET user_diplo_points = user_diplo_points + '.$add_diplo_points.' WHERE user_id = '.$diplo_item['user_id'];
        $db->query($sql);
        // $sdl->log('DEBUG: SQL E5B:'.$sql);
    }
    
$sdl->finish_job('Settlers Events');

$sdl->start_job('User SlowStats Update');

    $xform_list = $db->queryrowset('SELECT user_id, COUNT(log_code) AS xform_count FROM user LEFT JOIN planet_details USING (user_id) WHERE user_active = 1 AND user_auth_level = 1 AND log_code = 31 GROUP BY user_id ORDER BY COUNT(log_code)');

    foreach ($xform_list AS $xform_item) {
        $db->query('UPDATE user SET user_tform_planets = '.$xform_item['xform_count'].' WHERE user_id = '.$xform_item['user_id']);
    }
    
    $sql = 'UPDATE memory_alpha_triggers SET trigger_2 = 1 WHERE id = 1';
    if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b>: could not update memory alpha triggers!.');
    }    
$sdl->finish_job('User SlowStats Update');

// #######################################################################################
// #######################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished SixHours-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
