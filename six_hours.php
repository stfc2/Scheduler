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

$sdl->start_job('Unimatrix Zero Maintenance');

$bot = $db->queryrow('SELECT * FROM borg_bot LIMIT 0,1');

if($bot['shutdown'] == 0) {

    $sql = 'SELECT COUNT(*) AS counter FROM planets WHERE planet_owner = '.BORG_USERID;
    $borg_planets = $db->queryrow($sql);

    $sql = 'SELECT ship_template3 AS tact_cube, ship_template2 AS standard_cube, ship_template1 As sphere from borg_bot WHERE user_id = '.BORG_USERID;
    $borg_tp = $db->queryrow($sql);

    $sql = 'SELECT fleet_id FROM ship_fleets WHERE fleet_name LIKE "Unimatrix Zero" AND user_id = '.BORG_USERID;
    $borg_fleet = $db->queryrow($sql);

    // Tactical Cubes Check
    $sql = 'SELECT COUNT(*) AS counter FROM ships
            INNER JOIN ship_templates ON template_id = id
            WHERE user_id = '.BORG_USERID.' AND fleet_id = '.$borg_fleet['fleet_id'].'
            AND ship_torso = 11 AND ship_class = 3';            
    $borg_tact_num = $db->queryrow($sql);
    $tactical_counter = round((($borg_planets['counter'] - 1) / 5), 0) + 3;
    $sdl->log('Unimatrix Zero Tact Cube count is: '.$tactical_counter);
    if($tactical_counter > $borg_tact_num['counter'])
    {
        // We add ONE Tactical Cube
        $sql = 'SELECT value_4, value_9, max_torp, rof, rof2, max_unit_1, max_unit_2, max_unit_3, max_unit_4 FROM ship_templates WHERE id = '.$borg_tp['tact_cube'];
        $borg_tact_tp = $db->queryrow($sql);
        $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time, unit_1, unit_2, unit_3, unit_4)
                VALUES ('.$borg_fleet['fleet_id'].', '.BORG_USERID.', '.$borg_tp['tact_cube'].', '.$borg_tact_tp['value_9'].', '.$borg_tact_tp['value_4'].', '.time().', '.$borg_tact_tp['max_torp'].', '.$borg_tact_tp['rof'].', '.$borg_tact_tp['rof2'].', '.time().', 
                        '.$borg_tact_tp['max_unit_1'].', '.$borg_tact_tp['max_unit_2'].', '.$borg_tact_tp['max_unit_3'].', '.$borg_tact_tp['max_unit_4'].')';
        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b>: could not insert new tactical cube in ships! CONTINUE');
        }
    }

    //Standard Cubes Check
    $sql = 'SELECT COUNT(*) AS counter FROM ships
            INNER JOIN ship_templates ON template_id = id
            WHERE user_id = '.BORG_USERID.' AND fleet_id = '.$borg_fleet['fleet_id'].' 
            AND ship_torso = 9 AND ship_class = 3'; 
    $borg_std_num = $db->queryrow($sql);
    $standard_counter = $tactical_counter*4;
    $sdl->log('Unimatrix Zero Standard Cube count is: '.$standard_counter);
    if($standard_counter > $borg_std_num['counter'])
    {
        // We add FOUR Cubes for every one TACT
        $to_add_counter = $standard_counter - $borg_std_num['counter'];
        if($to_add_counter > 5) $to_add_counter = 5;
        $sql = 'SELECT value_4, value_9, max_torp, rof, rof2, max_unit_1, max_unit_2, max_unit_3, max_unit_4 FROM ship_templates WHERE id = '.$borg_tp['standard_cube'];
        $borg_std_tp = $db->queryrow($sql);
        for($i = 0; $i < $to_add_counter; $i++)
        {
            $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time, unit_1, unit_2, unit_3, unit_4)
                    VALUES ('.$borg_fleet['fleet_id'].', '.BORG_USERID.', '.$borg_tp['standard_cube'].', '.$borg_std_tp['value_9'].', '.$borg_std_tp['value_4'].', '.time().', '.$borg_std_tp['max_torp'].', '.$borg_std_tp['rof'].', '.$borg_std_tp['rof2'].', '.time().',
                    '.$borg_std_tp['max_unit_1'].', '.$borg_std_tp['max_unit_2'].', '.$borg_std_tp['max_unit_3'].', '.$borg_std_tp['max_unit_4'].')';
            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b>: could not insert new cube in ships! CONTINUE');
            }
        }
    }

    //Spheres Check
    $sql = 'SELECT COUNT(*) AS counter FROM ships 
            INNER JOIN ship_templates ON template_id = id 
            WHERE ships.user_id = '.BORG_USERID.' AND 
                  ships.fleet_id = '.$borg_fleet['fleet_id'].' AND 
                  ship_torso = 6 AND ship_class = 2'; 
    $borg_std_num = $db->queryrow($sql);
    $sphere_counter = $standard_counter*3;
    $sdl->log('Unimatrix Zero Sphere count is: '.$sphere_counter);
    if($sphere_counter > $borg_std_num['counter'])
    {
        // We add THREE spheres for every one CUBE
        $to_add_counter = $sphere_counter - $borg_std_num['counter'];
        if($to_add_counter > 5) $to_add_counter = 5;
        $sql = 'SELECT value_4, value_9, max_torp, rof, rof2, max_unit_1, max_unit_2, max_unit_3, max_unit_4 FROM ship_templates WHERE id = '.$borg_tp['sphere'];
        $borg_std_tp = $db->queryrow($sql);
        for($i = 0; $i < $to_add_counter; $i++)
        {
            $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time)
                    VALUES ('.$borg_fleet['fleet_id'].', '.BORG_USERID.', '.$borg_tp['sphere'].', '.$borg_std_tp['value_9'].', '.$borg_std_tp['value_4'].', '.time().', '.$borg_std_tp['max_torp'].', '.$borg_std_tp['rof'].', '.$borg_std_tp['rof2'].', '.time().'
                    '.$borg_std_tp['max_unit_1'].', '.$borg_std_tp['max_unit_2'].', '.$borg_std_tp['max_unit_3'].', '.$borg_std_tp['max_unit_4'].')';
            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b>: could not insert new sphere in ships! CONTINUE');
            }
        }
    }
}
else {
    $sdl->log('<b>Notice:</b>: Bot is shutdown');
}

$sdl->finish_job('Unimatrix Zero Maintenance');

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
            case '130':
            case '131':
            case '132':
            case '133':
            case '134':
                $cache_mood -= 10;
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

// Seconda fase, muoviamo i vari mood negativi! IMPORTANTE. Mantenete gli event code di mosse anti controllore sotto il 120
$sql = 'SELECT se.planet_id, p.best_mood_user AS controller, event_code FROM settlers_events se INNER JOIN planets p USING (planet_id) WHERE event_status = 1 AND se.user_id <> p.best_mood_user AND event_code < 120 ORDER BY se.planet_id, event_code';
$events_query = $db->query($sql);
$counter = $db->num_rows($events_query);
if($counter > 0)
{
    $events_list = $db->fetchrowset($events_query);
    $pre_item['planet_id'] = $events_list[0]['planet_id'];
    $pre_item['controller'] = $events_list[0]['controller'];
    $pre_item['event_code'] = $events_list[0]['event_code'];
    $cache_mood = array(28 => 0, 29 => 0, 35 => 0);
    foreach($events_list as $events_item)
    {
        if($pre_item['planet_id'] != $events_item['planet_id'])
        {
            foreach($cache_mood as $key => $mood_item)
            {
                if($mood_item == 0) continue;
                $sql = 'SELECT mood_modifier FROM settlers_relations WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = '.$key;
                $lq_query = $db->queryrow($sql);
                if(isset($lq_query['mood_modifier']))
                {
                    $new_mood = $lq_query['mood_modifier'] + $mood_item;
                    $new_mood = max(-85, $new_mood);
                    $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = '.$key;
                    if(!$db->query($sql)) {
                        $sdl->log('<b>Error:</b> could not update settlers relations!!! E2: '.$sql);
                    }                    
                }
                else
                {
                    $new_mood = max(-85, $mood_item);
                    $sql = 'INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$pre_item['planet_id'].', '.$pre_item['controller'].', '.time().', '.$key.', '.$new_mood.')';
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
                $cache_mood[35] -= 10;
            break;
        }
        $pre_item['planet_id'] = $events_item['planet_id'];
        $pre_item['controller'] = $events_item['controller'];
        $pre_item['event_code'] = $events_item['event_code'];
    }
    foreach($cache_mood as $key => $mood_item)
    {
        if($mood_item == 0) continue;
        $sql = 'SELECT mood_modifier FROM settlers_relations WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = '.$key;
        $lq_query = $db->queryrow($sql);
        if(isset($lq_query['mood_modifier']))
        {
            $new_mood = $lq_query['mood_modifier'] + $mood_item;
            $new_mood = max(-85, $new_mood);
            $sql = 'UPDATE settlers_relations SET mood_modifier = '.$new_mood.' WHERE planet_id = '.$pre_item['planet_id'].' AND log_code = '.$key;
            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b> could not update settlers relations!!! E4: '.$sql);
            }                    
        }
        else
        {
            $new_mood = max(-85, $mood_item);
            $sql = 'INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$pre_item['planet_id'].', '.$pre_item['controller'].', '.time().', '.$key.', '.$new_mood.')';
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
        $sql = 'INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$predator_item['planet_id'].', '.$predator_item['user_id'].', '.time().', 23, 5) ON DUPLICATE KEY UPDATE mood_modifier = mood_modifier + 5';
        $db->query($sql);
    }
    
    $db->query('UPDATE settlers_relations SET mood_modifier = 80 WHERE log_code = 23 AND mood_modifier > 80');
    
// Quarta fase: Borg Fighters Reward
    
    $db->query('UPDATE settlers_relations SET mood_modifier = 10 WHERE log_code = 22');
    
    $fighter_list = $db->queryrowset('SELECT user_id, threat_level FROM borg_target');
    
    foreach ($fighter_list AS $fighter) {
        $value = 0;
        if($fighter['threat_level'] > 1400.0)
            $value = 80;
        elseif($fighter['threat_level'] > 950.0)
            $value = 50;
        elseif($fighter['threat_level'] > 450.0)
            $value = 30;
        elseif($fighter['threat_level'] > 200.0)
            $value = 20;
        
        $db->query('UPDATE settlers_relations SET mood_modifier = mood_modifier + '.$value.' WHERE user_id = '.$fighter['user_id'].' AND log_code = 22');
        
    }
     
$sdl->finish_job('Settlers Events'); 

// #######################################################################################
// #######################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished SixHours-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
