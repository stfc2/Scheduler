<?php
/*    
    This file is part of STFC.it.
    Copyright 2008-2013 by Andrea Carolfi (info@stfc.it) and Cristiano Delogu

    STFC.it is based on STGC,
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


// #####################################################################
// #####################################################################
// Startup Config

// include game definitions, path url and so on
include('config.script.php');

// include functions and classes needed by the upgrade utility
include($game_path . 'include/global.php');
include($game_path . 'include/race_data.php');

// include commons classes and functions
include('commons.php');

error_reporting(E_ERROR);
ini_set('memory_limit', '200M');
set_time_limit(240); // 4 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'The scheduler can only be called by CLI!'; exit;
}


// #####################################################################
// #####################################################################
// Init

$sdl = new scheduler();

// create sql-object for db-connection
$db = new sql($config['server'].":".$config['port'],
              $config['game_database'], $config['user'],
              $config['password']);

// First of all stop tick and game in order to avoid further addition
// by the players.
$sql = 'UPDATE config SET tick_stopped = 1,game_stopped = 1';

if($db->query($sql) === false) {
    echo "Error: could not stop the game. Upgrade aborted!\n";
}
else {
    // Now select all the queued buildings in the system
    $sql = 'SELECT s.*,u.user_race,
                   p.planet_type,p.research_4,p.building_queue,
                   p.building_1,p.building_2,p.building_3,p.building_4,
                   p.building_5,p.building_6,p.building_7,p.building_8,
                   p.building_9,p.building_10,p.building_11,p.building_12
            FROM (`scheduler_instbuild` s)
            LEFT JOIN (planets p) ON s.planet_id = p.planet_id
            LEFT JOIN (user u) ON u.user_id=p.planet_owner
            WHERE p.building_queue <> 0';

    //echo "First query:\n".$sql."\n";

    if(($q_inst = $db->query($sql)) === false) {
        echo "Error: could not query scheduler instbuild data!\n";
    }
    else if($db->num_rows() > 0)
    {
        $n_instbuild = 0;
        while($build = $db->fetchrow($q_inst)) {
            $building_name = 'building_'.($build['installation_type']+1);

            echo "Building being built  : ".$build['installation_type']." (name: ".$building_name.") Level: ".$build[$building_name]."\n";
            echo "Building will be built: ".($build['building_queue']-1)."\n";

            // In order to calculate times correctly, I need to consider also the building
            // being built at the moment
            if ($build['installation_type'] == ($build['building_queue']-1)) {
                $build[$building_name]++;
                echo "New level: ".$build[$building_name]."\n";
            }

            $time = GetBuildingTimeTicks($build['building_queue']-1,$build,$build['user_race']);

            // Insert the queued building using the new method
            $sql = 'INSERT INTO scheduler_instbuild (installation_type,planet_id,build_start,build_finish)
                    VALUES ("'.($build['building_queue']-1).'",
                            "'.$build['planet_id'].'",
                            "'.$build['build_finish'].'",
                            "'.($build['build_finish']+$time).'")';

            //echo "Second query:\n".$sql."\n";

            if(!$db->query($sql)) {
                echo "Error: coudl not insert new row in scheduler_instbuild table!\n";
                break;
            }

            echo "Queue new building: ".($build['building_queue']-1)." start: ".$build['build_finish']." finish: ".($build['build_finish']+$time)."\n";

            // Remove the queued building from the planet
            $sql = 'UPDATE planets
                    SET building_queue = 0
                    WHERE planet_id = '.$build['planet_id'];

            //echo "Third query:\n".$sql."\n";

            if(!$db->query($sql)) {
                echo "Error: coudl not update building_queue in planets!\n";
                break;
            }

            $n_instbuild++;
        }
        echo "Updated buildings: ".$n_instbuild."\n";

        // Structure updated, deactivate the old code and activate the
        // new one.
        $okToGo = true;
        $oldnames = array(
            $game_path.'modules/building.php',
            $game_path.'modules/building_new.php',
            $script_path.'stfc-scheduler/main.php',
            $script_path.'stfc-scheduler/main_new.php'
        );
        $newnames = array(
            $game_path.'modules/building.php.safe',
            $game_path.'modules/building.php',
            $script_path.'stfc-scheduler/main.php.safe',
            $script_path.'stfc-scheduler/main.php'
        );

        foreach ($oldnames as $i => $oldname) {
            $newname = $newnames[$i];
            if (!rename ($oldname, $newname)) {
                echo "Error: cannot rename ".$oldname." into ".$newname."\n";
                $okToGo = false;
                break;
            }
        }

        if ($okToGo) {
            // We can reactivate tick and game now.
            $sql = 'UPDATE config SET tick_stopped = 0,game_stopped = 0';

            if($db->query($sql) === false)
                echo "Error: could not restart the game!\n";
        }
    }
    else
        echo "No queued building to update.\n";
}

// #####################################################################
// #####################################################################
// Quit and close log

$db->close();

?>

