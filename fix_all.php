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
            if (($MAX_BUILDING_LVL[$capital][$t] + ($capital == 0 ? $PLANETS_DATA[$planet['planet_type']][14][$t] : 0)) < $planet['building_'.($t+1)])
            {
                if (($MAX_BUILDING_LVL[$capital][$t] + ($capital == 0 ? $PLANETS_DATA[$planet['planet_type']][14][$t] : 0))>=5) {
                    $db->query('UPDATE planets SET building_'.($t+1).'='.($MAX_BUILDING_LVL[$capital][$t] + ($capital == 0 ? $PLANETS_DATA[$planet['planet_type']][14][$t] : 0)).' WHERE planet_id='.$planet['planet_id']);
                    $sdl->log('On planet '.$planet['planet_id'].' adjusted building_'.($t+1).' to lvl '.($MAX_BUILDING_LVL[$capital][$t] + ($capital == 0 ? $PLANETS_DATA[$planet['planet_type']][14][$t] : 0)));
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


$sdl->start_job('Rioters planets return inhabitated');

$sql = 'SELECT * FROM planets WHERE ( planet_surrender < '.$ACTUAL_TICK.' AND planet_surrender > 0 )';

if(($query_s_p = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> Could not query surrending planets');
}
  
while($surrender = $db->fetchrow($query_s_p)) {

    if($surrender['min_security_troops'] == 0) {
        $sql = 'UPDATE user SET user_capital = 0, pending_capital_choice = 1, active_planet = 0
               WHERE user_id = '.$surrender['planet_owner'];

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not update user_capital status on surrending user '.$surrender['planet_owner']);
        }
    }

    $sql = 'UPDATE planets
            SET planet_owner = 0,
                planet_owned_date = '.time().',
                resource_1 = 10000,
                resource_2 = 10000,
                resource_3 = 10000,
                resource_4 = '.mt_rand(0, 5000).',
                recompute_static = 1,
                research_1 = '.mt_rand(0,5).',
                research_2 = 0,
                research_3 = 0,
                research_4 = 0,
                research_5 = 0,
                building_1 = '.mt_rand(0, 9).',
                building_2 = '.mt_rand(0, 9).',
                building_3 = '.mt_rand(0, 9).',
                building_4 = '.mt_rand(0, 9).',
                building_5 = '.mt_rand(0, 9).',
                building_6 = '.mt_rand(0, 9).',
                building_7 = '.mt_rand(0, 9).',
                building_8 = '.mt_rand(0, 9).',
                building_10 = '.mt_rand(5, 15).',
                building_11 = '.mt_rand(0, 9).',
                building_13 = '.mt_rand(0, 10).',
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
             WHERE planet_id = '.$surrender['planet_id'];

    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b> Could not delete switch user');
    }

    // DC ---- History record in planet_details, with label '30'
    $sql = 'SELECT user_race, user_alliance FROM user WHERE user_id = '.$surrender['planet_owner'];

    $_temp = $db->queryrow($sql);

    $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
            VALUES ('.$surrender['planet_id'].',
                    '.$surrender['planet_owner'].',
                    '.$_temp['user_alliance'].',
                    '.$surrender['planet_owner'].',
                    '.$_temp['user_alliance'].', '.time().', 30)';

    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b> Could not insert new planet details 30 for <b>'.$surrender['planet_id'].'</b>! CONTINUED');
    }

// DC ---- Maybe we have to check shipyard building queue? ...
}

$sdl->finish_job('Rioters planets return inhabitated');

/*
$sdl->start_job('Fog of War Maintenance'); // Facciamo manutenzione alla Nebbia di Guerra

	$sql = 'SELECT * from planet_details WHERE fixed IN (0, 1) AND log_code = 500 AND validity >= '.$ACTUAL_TICK;
	
	$sdl->log($sql);
	
	if(($fowquery = $db->query($sql)) === false) {
		message(DATABASE_ERROR, '(fix_all:FoW Maint) Could not query planet details');
	}
		
	while($fow_item = $db->fetchrow($fowquery)) {
		// Abbiamo in canna un 500 instabile. 
		// Se c'è un nostro pianeta nel sistema, diventa stabile
		$sql = 'SELECT COUNT(*) AS result FROM planets pl WHERE system_id = '.$fow_item['system_id'].' AND planet_owner = '.$fow_item['user_id'];
		$_temp = $db->queryrow($sql);
		if($_temp['result'] > 0) {
			$db->query('UPDATE planet_details SET fixed = 2, validity = 0 WHERE id = '.$fow_item['id']);
			continue;
		}
		// Se c'è una nostra flotta nel sistema, rimane instabile ma rinnoviamo la validità
		$sql = 'SELECT COUNT(*) AS result FROM ship_fleets sf INNER JOIN planets pl ON sf.planet_id = pl.planet_id INNER JOIN user u ON sf.user_id = u.user_id WHERE sf.user_id = '.$fow_item['user_id'].' AND pl.system_id = '.$fow_item['system_id'];
		$_temp = $db->queryrow($sql);
		if($_temp['result'] > 0) {
			$db->query('UPDATE planet_details SET fixed = 1, validity = '.($ACTUAL_TICK+960).' WHERE id = '.$fow_item['id']);
			continue;
		}
		
		// Il 500 potrebbe essere un pod lasciato da una flotta. Se fixed = 1 lo portiamo a zero
		if($fow_item['fixed'] == 1) {
			$db->query('UPDATE planet_details SET fixed = 0 WHERE id = '.$fow_item['id']);
		}
	}
		
	
	
	$timestamp = time();
	
	$sql = 'SELECT * FROM planet_details WHERE log_code = 500 AND fixed = 0 AND validity < '.$ACTUAL_TICK;
	
	if(($fixquery = $db->query($sql)) === false) {
		message(DATABASE_ERROR, '(fix_all:FoW Maint) Could not query planet details');
	}
	while($fix_item = $db->fetchrow($fixquery)) {
		$sql = "SELECT p.planet_id, p.system_id, p.planet_name, p.planet_owner, p.planet_points, u.user_alliance FROM planets p LEFT JOIN user u ON p.planet_owner = u.user_id WHERE p.system_id = ".$fix_item['system_id'];
		if(($detquery = $db->query($sql)) === false) {
			message(DATABASE_ERROR, '(fix_all:FoW Maint) Could not query planet details');
		}
		while($det_item = $db->fetchrow($detquery)) {
			$sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code, planet_name, planet_owner, owner_alliance, planet_points) VALUES ('
			       .$det_item['planet_id'].', '.$det_item['system_id'].', '.$fix_item['user_id'].', '.$fix_item['alliance_id'].', '.$fix_item['user_id'].', '.$fix_item['alliance_id'].', '.$timestamp.', 102, "'.$det_item['planet_name'].'", '
			       .$det_item['planet_owner'].', '.(isset($det_item['user_alliance']) ? $det_item['user_alliance'] : 0 ).', '.$det_item['planet_points'].')'; 
			$db->query($sql);
		}
	}
	
	$db->query('DELETE FROM planet_details WHERE log_code = 500 AND fixed = 0 AND validity < '.$ACTUAL_TICK);

$sdl->finish_job('Fog of War Maintenance'); // terminates the timer
*/

$sdl->start_job('New Fog of War Maintenance'); // Facciamo manutenzione alla Nebbia di Guerra

// Player Section
$sql = 'SELECT system_id, user_id, fixed
        FROM starsystems_details
        WHERE alliance_id is null AND
              fixed IN (0, 1) AND
              log_code = 500 AND
              validity >= '.$ACTUAL_TICK;

$sdl->log($sql);

if(($fowquery = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> (fix_all:FoW Maint) could not query starsystems details');
}

while($fow_item = $db->fetchrow($fowquery)) {
    // We have an unstable 500 log code. 
    // If we own a planet in the system, it'll become stable
    $sql = 'SELECT COUNT(*) AS result
            FROM planets
            WHERE system_id = '.$fow_item['system_id'].' AND
                  planet_owner = '.$fow_item['user_id'];
    $_temp = $db->queryrow($sql);
    if($_temp['result'] > 0) {
        $sql = 'UPDATE starsystems_details
                SET fixed = 2, validity = 0
                WHERE system_id = '.$fow_item['system_id'].' AND
                      user_id = '.$fow_item['user_id'].' AND
                      log_code = 500';
        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not update startsystems details for system <b>'.$fow_item['system_id'].'</b> user <b>'.$fow_item['user_id'].'</b>! CONTINUED');
        }
        continue;
    }

    // If we have a fleet in the system, it'll remain unstable but with renewed validity
    $sql = 'SELECT COUNT(*) AS result
            FROM (ship_fleets sf)
            INNER JOIN (planets pl) ON sf.planet_id = pl.planet_id
            INNER JOIN user u ON sf.user_id = u.user_id
            WHERE sf.user_id = '.$fow_item['user_id'].' AND
                  pl.system_id = '.$fow_item['system_id'];
    $_temp = $db->queryrow($sql);
    if($_temp['result'] > 0) {
        $sql = 'UPDATE starsystems_details
                SET fixed = 1, validity = '.($ACTUAL_TICK+960).'
                WHERE system_id = '.$fow_item['system_id'].' AND
                      user_id = '.$fow_item['user_id'].' AND
                      log_code = 500';
        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not update startsystems details for system <b>'.$fow_item['system_id'].'</b> user <b>'.$fow_item['user_id'].'</b>! CONTINUED');
        }
        continue;
    }

    // The 500 log code could be a pod left by a fleet. If fixed = 1 we set it to zero
    if($fow_item['fixed'] == 1) {
        $sql = 'UPDATE starsystems_details
                SET fixed = 0
                WHERE system_id = '.$fow_item['system_id'].' AND
                      user_id = '.$fow_item['user_id'].' AND
                      log_code = 500';
        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> Could not update startsystems details for system <b>'.$fow_item['system_id'].'</b> user <b>'.$fow_item['user_id'].'</b>! CONTINUED');
        }
    }
}

$sql = 'DELETE FROM starsystems_details WHERE alliance_id is null AND log_code = 500 AND fixed = 0 AND validity < '.$ACTUAL_TICK;
if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not delete startsystems details with fixed = 0 AND validity < <b>'.$ACTUAL_TICK.'</b>! CONTINUED');
}

// Alliance Section
$sql = 'SELECT system_id,alliance_id FROM starsystems_details WHERE user_id is null AND log_code = 500';

$sdl->log($sql);

if(($fowquery = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> (fix_all:FoW Maint) could not query starsystems details');
}

while($fow_item = $db->fetchrow($fowquery)) {
    $sql = 'SELECT sd.fixed,sd.validity FROM (starsystems_details sd)
            INNER JOIN (user u) ON u.user_id = sd.user_id
            WHERE sd.alliance_id IS NULL AND
                  log_code = 500 AND
                  sd.system_id = '.$fow_item['system_id'].' AND
                  u.user_alliance = '.$fow_item['alliance_id'].'
            ORDER BY fixed DESC, timestamp DESC LIMIT 0,1';
    if($_temp = $db->queryrow($sql))
        $sql = 'UPDATE starsystems_details
                SET fixed = '.$_temp['fixed'].', validity = '.$_temp['validity'].'
                WHERE system_id = '.$fow_item['system_id'].' AND
                      log_code = 500 AND
                      alliance_id = '.$fow_item['alliance_id'];		
    else
        $sql = 'DELETE FROM starsystems_details
                WHERE system_id = '.$fow_item['system_id'].' AND
                      alliance_id = '.$fow_item['alliance_id'].' AND
                      log_code = 500';
    if(!$db->query($sql)) {
        $sdl->log('<b>Error:</b> Could not update/delete startsystems details for system <b>'.$fow_item['system_id'].'</b> alliance <b>'.$fow_item['alliance_id'].'</b>! CONTINUED');
    }
}

$sql = 'DELETE FROM starsystems_details WHERE user_id is null AND log_code = 500 AND fixed = 0 AND validity < '.$ACTUAL_TICK;
if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> Could not delete startsystems details with user_id = null AND log_code = 500 AND fixed = 0 AND validity < <b>'.$ACTUAL_TICK.'</b>! CONTINUED');
}

$sdl->finish_job('New Fog of War Maintenance'); // terminates the timer




$sdl->start_job('Logbook cleaning');

$sql = 'DELETE FROM logbook WHERE log_read=1 AND log_date<'.(time()-3600*24*14);
$sdl->log($sql);
if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> could not delete 14-day old logs');
}
$sql = 'DELETE FROM logbook WHERE log_type='.LOGBOOK_GOVERNMENT.' AND log_date<'.(time()-3600*24);
$sdl->log($sql);
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
            message(DATABASE_ERROR, 'Could not update user unread log entries data');
        }
	 }
}




$sdl->finish_job('Logbook cleaning');


/*$sdl->start_job('Clearing invalid war declarations');

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

$sdl->finish_job('Clearing invalid war declarations');*/

// ########################################################################################
// ########################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished FixAll-Script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');

?>
