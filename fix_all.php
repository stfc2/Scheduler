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
set_time_limit(120); // 2 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'The scheduler can only be called by CLI!'; exit;
}

define('TICK_LOG_FILE', $game_path . 'logs/fixall/tick_'.date('d-m-Y', time()).'.log');
define('IN_SCHEDULER', true); // we are in the scheduler...

// include commons classes and functions
include('commons.php');


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

$sdl->log('<br><br><br><b>-------------------------------------------------------------</b><br>'.
          '<b>Starting FixAll-Script at '.date('d.m.y H:i:s', time()).'</b>');

if(($cfg_data = $db->queryrow('SELECT * FROM config')) === false) {
    $sdl->log('- Fatal: Could not query tick data! ABORTED');
    exit;
}

$ACTUAL_TICK = $cfg_data['tick_id'];
$NEXT_TICK = ($cfg_data['tick_time'] - time());
$LAST_TICK_TIME = ($cfg_data['tick_time']-5*60);
$STARDATE = $cfg_data['stardate'];

if($cfg_data['tick_stopped']) {
    $sdl->log('Finished FixAll-Script in '.round((microtime()+time())-$starttime, 4).' secs<br>Tick has been stopped (Unlock in table "config")');
    exit;
}

if(empty($ACTUAL_TICK)) {
    $sdl->log('Finished FixAll-Script in '.round((microtime()+time())-$starttime, 4).' secs<br>- Fatal: empty($ACTUAL_TICK) == true');
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


$sdl->start_job('Recalculate resources');
$db->query('UPDATE planets SET recompute_static=1');
$sdl->finish_job('Recalculate resources');









$sdl->start_job('Recalculate security forces');
$sql = 'SELECT u.user_id FROM user u WHERE u.user_active=1';
$count = 0;

if(!$q_user = $db->query($sql)) {

    $sdl->log('<b>Error:</b> could not query user data');
}

while($user = $db->fetchrow($q_user)) {


    $sql = 'SELECT planet_id, planet_owner_enum FROM planets WHERE planet_owner='.$user['user_id'].' ORDER BY  planet_owned_date ASC, planet_id ASC';
    if(!$q_planet = $db->query($sql)) {
        $sdl->log('<b>Error:</b> could not query user data');
    }

    $i=0;

    while($planet = $db->fetchrow($q_planet)) {
        if ($planet['planet_owner_enum']!=$i) {$count++;}
        $i++;
        $count2++;
    }

    if(!$db->query('SET @i=0')) {
        $sdl->log('<b>Error:</b> could not set sql iterator variable for planet owner enum! SKIP');
    }

    $sql = 'UPDATE planets
            SET planet_owner_enum = (@i := (@i + 1))-1
            WHERE planet_owner = '.$user['user_id'].'
            ORDER BY planet_owned_date ASC, planet_id ASC';

    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b>: could not update planet owner enum data! SKIP');
    }
}
$sdl->log($count.' of '.$count2.' planets now have adjusted values');

$sdl->finish_job('Recalculate security forces');

$sdl->start_job('User SlowStats Update');

$sql = 'SELECT user_id FROM user WHERE user_active = 1 AND user_auth_level = 1';
$user_set = $db->queryrowset($sql);

foreach($user_set as $s_user){
    $charted=$db->queryrow('SELECT COUNT(*) AS num FROM starsystems_details sd INNER JOIN starsystems ss USING (system_id) WHERE system_closed <> 1 AND log_code = 0 AND alliance_id is null AND user_id = '.$s_user['user_id']);
    $first_contact=$db->queryrow('SELECT COUNT(*) AS num FROM settlers_relations WHERE user_id = '.$s_user['user_id'].' AND log_code = 1');
    $settler_made=$db->queryrow('SELECT COUNT(*) AS num FROM settlers_relations WHERE user_id = '.$s_user['user_id'].' AND log_code = 30');
    $settler_best=$db->queryrow('SELECT COUNT(*) AS num FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' AND best_mood_user = '.$s_user['user_id']);

    $sql = 'UPDATE user SET user_charted = '.$charted['num'].', user_first_contact = '.$first_contact['num'].', user_settler_made = '.$settler_made['num'].', user_settler_best = '.$settler_best['num'].' WHERE user_id = '.$s_user['user_id'];
    if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b>: could not update user #'.$s_user['user_id'].' slow stats data! CONTINUE.');
    }    
}

$sql = 'UPDATE memory_alpha_triggers SET trigger_1 = 1 WHERE id = 1';
if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b>: could not update memory alpha triggers!.');
}

$sdl->finish_job('User SlowStats Update');






$sdl->start_job('Buildings / Research level fix');

if (isset($MAX_BUILDING_LVL))
{
    $count = 0;

    $qry=$db->query('SELECT p.*,u.user_capital,u.pending_capital_choice FROM (planets p) LEFT JOIN (user u) ON u.user_id=p.planet_owner WHERE p.planet_owner<>0 ORDER BY planet_id ASC');

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
                if ($MAX_BUILDING_LVL[$capital][$t]>=9) {
                    $db->query('UPDATE planets SET building_'.($t+1).'='.$MAX_BUILDING_LVL[$capital][$t].' WHERE planet_id='.$planet['planet_id']);
                    $count++;
                }
            }

            if ($planet['building_'.($t+1)]<0)
            {
                $db->query('UPDATE planets SET building_'.($t+1).'=0 WHERE planet_id='.$planet['planet_id']);
                $count++;
            }
        }

        for ($t=0;$t<5;$t++)
            if ($MAX_RESEARCH_LVL[$capital][$t]<$planet['research_'.($t+1)])
            {
                if ($MAX_RESEARCH_LVL[$capital][$t]>=9) {
                    $db->query('UPDATE planets SET research_'.($t+1).'='.$MAX_RESEARCH_LVL[$capital][$t].' WHERE planet_id='.$planet['planet_id']);
                    $count++;
                }
            }
    }
    if ($count) $sdl->log($count.' buildings/researches has been fixed');
}
$sdl->finish_job('Buildings / Research level fix');


$sdl->start_job('Rioters planets take over by the settlers');

$sql = 'SELECT p.planet_id, p.system_id, p.planet_owner, u.user_capital, ss.system_closed, ss.system_owner, ss.system_orion_alert, ss.system_n_planets
        FROM (planets p)
        INNER JOIN (starsystems ss) USING (system_id)
        INNER JOIN (user u) ON (p.planet_owner = u.user_id)
        WHERE ( planet_surrender < '.$ACTUAL_TICK.' AND planet_surrender > 0 )';

if(($query_s_p = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query surrending planets');
}

$orion_marbles = [0, 14, 20, 32, 48];

while($surrender = $db->fetchrow($query_s_p)) {
    
    //Safeguard
    if(!isset($surrender['system_orion_alert'])) {
        $surrender['system_orion_alert'] = 0;
    }

    /*Introducing the "Big Happy Surrending Ballot System"!!!
     *In the ballot box we put:
     * 40 white marbles
     * Settlers <= 100? 30 blue marbles
     * starsystem is OPEN and Orion alert 1? 14 red marbles
     * starsystem is OPEN and Orion alert 2? 20 red marbles
     * starsystem is OPEN and Orion alert 3? 32 red marbles
     * starsystem is OPEN and Orion alert 4? 48 red marbles
     */

    for($i = 0; $i < 40; $i++) {
        $ballot_box[] = 'white';
    }

    if($surrender['system_closed'] == 0) {
        $num = $orion_marbles[$surrender['system_orion_alert']];
        for($i = 0; $i < $num; $i++) {
            $ballot_box[] = 'red';
        }        
    }
    
    if($cfg_data['settler_n_planets'] < 100) {
        for($i = 0; $i < 30; $i++) {
            $ballot_box[] = 'blu';
        }        
    }
    
    $vote = $ballot_box[array_rand($ballot_box)];
    
    if ($vote == 'white' ) {
        // Planet go desert
        $sql = 'UPDATE planets
                SET planet_owner= 0,
                    planet_owner_enum = 0,
                    planet_name = "Lost World",
                    best_mood = 0,
                    best_mood_user = 0,
                    best_mood_planet = 0,
                    best_mood_alert = 0,
                    npc_last_action = 0,
                    planet_owned_date = 0,
                    resource_1 = '.mt_rand(10000, 30000).',
                    resource_2 = '.mt_rand(10000, 30000).',
                    resource_3 = '.mt_rand(10000, 30000).',
                    resource_4 = 0,
                    recompute_static = 1,
                    building_1 = '.mt_rand(3, 9).',
                    building_2 = '.mt_rand(1, 6).',
                    building_3 = '.mt_rand(1, 6).',
                    building_4 = '.mt_rand(1, 6).',
                    building_5 = '.mt_rand(1, 3).',
                    building_6 = 0,
                    building_7 = 0,
                    building_8 = 0,
                    building_9 = '.mt_rand(1, 3).',
                    building_10 = '.mt_rand(5, 10).',
                    building_11 = '.mt_rand(1, 4).',
                    building_12 = '.mt_rand(1, 4).',                        
                    building_13 = '.mt_rand(9, 15).',
                    unit_1 = 0,
                    unit_2 = 0,
                    unit_3 = 0,
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
                    unittrainid_nexttime = 0,
                    planet_insurrection_time = 0,
                    planet_surrender = 0
                 WHERE planet_id = '.$surrender['planet_id'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not desertificate planet');
        }
        
        // DC ---- History record in planet_details, with label '33'
        $sql = 'SELECT user_race, user_alliance FROM user WHERE user_id = '.$surrender['planet_owner'];

        $_temp = $db->queryrow($sql);

        $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                VALUES ('.$surrender['planet_id'].',
                        '.$surrender['planet_owner'].',
                        '.$_temp['user_alliance'].',
                        '.$surrender['planet_owner'].',
                        '.$_temp['user_alliance'].', '.time().', 33)';

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not insert new planet details 33 for <b>'.$surrender['planet_id'].'</b>! CONTINUED');
        }

        $sql = 'DELETE FROM settlers_relations WHERE planet_id = '.$surrender['planet_id'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not remove settlers_relations entry for <b>'.$surrender['planet_id'].'</b>! CONTINUED');
        }        
    
    }
    else {
        
        $riot_race_id = ($vote == 'blu' ? INDEPENDENT_USERID : ORION_USERID);
        $sql = 'UPDATE planets
                SET planet_owner='.$riot_race_id.',
                    planet_name = "'.($riot_race_id == INDEPENDENT_USERID ? 'Colony' : 'Orion Cove #').$surrender['planet_id'].'",
                    best_mood = 0,
                    best_mood_user = 0,
                    best_mood_planet = 0,
                    best_mood_alert = 0,
                    npc_last_action = 0,
                    planet_owned_date = '.time().',
                    resource_1 = '.mt_rand(10000, 25000).',
                    resource_2 = '.mt_rand(10000, 20000).',
                    resource_3 = '.mt_rand(8000,  15000).',
                    resource_4 = '.mt_rand(0, 5000).',
                    techpoints = 0,
                    recompute_static = 1,
                    building_1 = '.mt_rand(5, 9).',
                    building_2 = '.mt_rand(5, 9).',
                    building_3 = '.mt_rand(5, 9).',
                    building_4 = '.mt_rand(5, 9).',
                    building_6 = '.mt_rand(5, 9).',
                    building_7 = '.mt_rand(3, 9).',
                    building_8 = '.mt_rand(3, 9).',
                    building_9 = 0,    
                    building_10 = '.mt_rand(3, 10).',
                    building_11 = '.mt_rand(3, 9).',
                    building_12 = '.mt_rand(5, 9).',                        
                    building_13 = '.mt_rand(8, 15).',
                    research_3 = '.($riot_race_id == INDEPENDENT_USERID ? 3 : 7).',
                    unit_1 = '.mt_rand(500, 1500).',
                    unit_2 = '.mt_rand(500, 1000).',
                    unit_3 = '.mt_rand(0, 500).',
                    unit_4 = 0,
                    unit_5 = 0,
                    unit_6 = 0,
                    workermine_1 = 500,
                    workermine_2 = 500,
                    workermine_3 = 500,
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
                    planet_surrender=0
                 WHERE planet_id = '.$surrender['planet_id'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not delete switch user');
        }

        // DC ---- History record in planet_details, with label '30' for Settlers, 34 for Orion's pirates
        $sql = 'SELECT user_race, user_alliance FROM user WHERE user_id = '.$surrender['planet_owner'];

        $_temp = $db->queryrow($sql);

        $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                VALUES ('.$surrender['planet_id'].',
                        '.$surrender['planet_owner'].',
                        '.$_temp['user_alliance'].',
                        '.$surrender['planet_owner'].',
                        '.$_temp['user_alliance'].', '.time().', '.($riot_race_id == INDEPENDENT_USERID ? 30 : 34).')';

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not insert new planet details 30/34 for <b>'.$surrender['planet_id'].'</b>! CONTINUED');
        }

        $sql = 'DELETE FROM settlers_relations WHERE planet_id = '.$surrender['planet_id'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not remove settlers_relations entry for <b>'.$surrender['planet_id'].'</b>! CONTINUED');
        }

        if($riot_race_id == INDEPENDENT_USERID) {
            $sql = 'INSERT INTO settlers_relations (planet_id, race_id, user_id, timestamp, log_code, mood_modifier)
                                            VALUES ('.$surrender['planet_id'].', 13, '.INDEPENDENT_USERID.', '.time().', 30, 80)';

            if(!$db->query($sql)) {
                $sdl->log('<b>Error:</b> Could not create Settlers Founder data for <b>'.$surrender['planet_id'].'</b>! CONTINUED');
            }
            
            $sql = 'UPDATE config SET settler_n_planets = settler_n_planets + 1';
            
            $db->query($sql);

        }
    }

    if($surrender['planet_id'] == $surrender['user_capital']) {
        $sql = 'UPDATE user SET user_capital = 0, pending_capital_choice = 1, recompute_protect_ratio = 1 WHERE user_id = '.$surrender['planet_owner'];

        $db->query($sql);
        
        $sql = 'UPDATE starsystems SET system_closed = 2, system_close_time = '.time().' WHERE system_id = '.$surrender['system_id'];
            
        $db->query($sql);
    }

    if($surrender['system_n_planets'] >= 3 && $surrender['system_owner'] != 0) {
        // Surrending a planet could lead to losing a lock on a system
        $sql = 'SELECT planet_points FROM planets WHERE planet_owner = '.$surrender['system_owner'].' AND system_id = '.$surrender['system_id'];
        
        if(($p_info =  $db->queryrowset($sql)) === FALSE) {
            $sdl->log('error reading planets info. Offending query: '.$sql);
        }        
        
        $planet_owned_cnt = $planet_owned_pts = 0;
    
        foreach ($p_info as $planet) {
            $planet_owned_cnt++;
            $planet_owned_pts += $planet['planet_points'];
        }        
        
        if(($planet_owned_pts < 3*320) || ($planet_owned_cnt < round($ss_info['system_n_planets']/2))) {
            
            $sql = 'UPDATE starsystems SET system_owner = 0, system_closed = 0, system_close_time = 0 WHERE system_id = '.$surrender['system_id'];
            
            $db->query($sql);
            
            $sql = 'DELETE FROM starsystems_details WHERE system_id = '.$surrender['system_id'].' AND log_code = 100';
            
            $db->query($sql);
            
        }        
    }
    
    unset($ballot_box);
}

$sdl->finish_job('Rioters planets take over by the settlers');

$sdl->start_job('Claim Verify');

$sql = 'SELECT user_id, language FROM user WHERE user_active = 1 AND user_id > 10';

$user_list = $db->queryrowset($sql);

foreach ($user_list AS $user) {

    $sql = 'SELECT system_id, system_global_x, system_global_y FROM starsystems_details INNER JOIN starsystems USING (system_id) WHERE user_id = '.$user['user_id'].' AND log_code = 100';

    $res = $db->queryrowset($sql);

    $sdl->log('user '.$user['user_id'].' still have #'.count($res).' claim(s)');

    if(count($res)> 0) {
        $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_closed > 0 AND system_owner = '.$user['user_id'];
        $list = $db->queryrowset($sql);
        $sdl->log('user '.$user['user_id'].' still have #'.count($list).' private system(s)');
        foreach ($res AS $claim) {
            $min_range = 100000;
            for($i = 0; $i < count($list); $i++) {
                $range = get_distance(array($claim['system_global_x'], $claim['system_global_y']), array($list[$i]['system_global_x'], $list[$i]['system_global_y']));
                $min_range = ($range < $min_range ? $range : $min_range);
            }
            if(count($list) == 0 || $min_range > CLAIM_SYSTEM_RANGE) {
                $db->query('DELETE FROM starsystems_details WHERE system_id = '.$claim['system_id'].' AND user_id = '.$user['user_id'].' AND log_code = 100');
                $sdl->log('<b>claim</b> of user '.$user['user_id'].' over system '.$claim['system_id'].' has been revoked!!!');
            }
        }
    }    
}

$sdl->finish_job('Claim Verify');

$sdl->start_job('Logbook cleaning');

$sql = 'DELETE FROM logbook WHERE log_read=1 AND log_date<'.(time()-3600*24*14);

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> could not delete 14-day old logs');
}
$sql = 'DELETE FROM logbook WHERE log_type='.LOGBOOK_GOVERNMENT.' AND log_date<'.(time()-3600*24);

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> could not delete 1-day old government logs');
}

// Update unread logbook entries
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
            $sdl->log('<b>Error:</b> could not update user unread log entries data');
        }
    }
}

$sdl->finish_job('Logbook cleaning');

$sdl->start_job('Various messages cleaning');

$sql = 'UPDATE message SET rread = 1 WHERE sender = 0 AND time < '.(time()-3600*24*7);

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> could not set as read system messages older than 7 days');
}

$sql = 'DELETE FROM message WHERE sender = 0 AND rread = 1 AND time < '.(time()-3600*24*10);

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> could not delete system messages older than 10 days');
}

$sql = 'DELETE FROM shoutbox WHERE timestamp < '.(time()-3600*24*7);

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> could not delete shoutbox messages older than 7 days');
}

$sdl->finish_job('Various messages cleaning');

$sdl->start_job('Clearing invalid war declarations');

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
            $sdl->log('<b>Error:</b> could not delete illegal pacts');
        }
    }
}

$sdl->finish_job('Clearing invalid war declarations');

$sdl->start_job('scheduler_shipmovement table maintenance');

$sql = 'DELETE FROM scheduler_shipmovement WHERE move_status IN (2, 11)';

$db->query($sql);

$sql = 'DELETE FROM scheduler_shipmovement WHERE move_status = 4 AND move_finish < '.$ACTUAL_TICK;

$db->query($sql);
        
$sql = 'OPTIMIZE TABLE `scheduler_shipmovement` ';

$db->query($sql);

$sdl->finish_job('scheduler_shipmovement table maintenance');
// ########################################################################################
// ########################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished FixAll-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
