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

// include game definitions, path url and so on
include('config.script.php');

// status codes for _action_main() and _main()
define('MV_EXEC_OK', 1);
define('MV_EXEC_ERROR', -1);

// used for moves_common::log()
define('MV_M_NOTICE', 1);
define('MV_M_ERROR', 2);
define('MV_M_DATABASE', 3);
define('MV_M_CRITICAL', 4);

// macros for the elements of the numerical arrays returned by combat system
define('MV_CMB_WINNER', 0);
define('MV_CMB_KILLS_PLANETARY', 1);
define('MV_CMB_KILLS_SPLANETARY', 2);
define('MV_CMB_KILLS_EXT', 3);

define('MV_CMB_ATTACKER', 0);
define('MV_CMB_DEFENDER', 1);

define('MV_COMBAT_LEVEL_PLANETARY', 1); // Attack of the planet (= Colonization)
define('MV_COMBAT_LEVEL_ORBITAL', 2); // Attack on owned planet, but only ships
define('MV_COMBAT_LEVEL_OUTER', 3); // Fight beyond the planet between foreign players (or attack by the owner)

define('MV_COMBAT_BIN_PATH', $script_path . 'stfc-moves-combat/bin/moves_combat');

// defines form action27

define('LC_FIRST_CONTACT', 1);
define('LC_DIPLO_SPEECH', 2);

define('LC_SUP_TECH', 3);
define('LC_SUP_MEDI', 4);
define('LC_SUP_DFNS', 5);
define('LC_SUP_AUTO', 6);
define('LC_SUP_MINE',7);
define('LC_MIL_ORBITAL', 8);

define('LC_REL_MULTIC', 10);
define('LC_REL_TSUPER', 11);
define('LC_REL_CHAMPN', 12);
define('LC_REL_INNVTR', 13);
define('LC_REL_OPPSTR', 14);
define('LC_REL_PLURLS', 15);
define('LC_REL_PRESTG', 16);
define('LC_REL_DEFNDR', 17);
define('LC_REL_CMPTNT', 18);
define('LC_REL_LIBERT', 19);
define('LC_REL_LEADER', 20);
define('LC_REL_MECENA', 21);
define('LC_REL_WORSHP', 22);
define('LC_REL_PREDAT', 23);
define('LC_REL_STRNGR', 24);
define('LC_REL_UNABLE', 25);
define('LC_REL_EXPLOI', 26);
define('LC_REL_PREY',   27);
define('LC_REL_BENEF',  36);

define('LC_COLO_FOUNDER', 30);
define('LC_COLO_GIVER', 31);

define('STL_MAX_ORBITAL', 130);


function commonlog($message,$message2,$move_id=0)
{
    $fp = fopen(TICK_LOG_FILE, 'a');
        fwrite($fp, $message." (move_id: <b>".$move_id."</b>) ".$message2."<br>\n");
        echo str_replace('\n','<br>',$message." ".$message2."\n");
        fclose($fp);
}

class moves_common {
    var $db;

    var $move = array();
    var $mid = 0;

    var $CURRENT_TICK = 0;

    var $start = array();
    var $dest = array();

    var $n_fleets = 0;
    var $fleet_ids = array();
    var $fleet_names = array();
    var $n_ships = array();
    var $fleet_ids_str = '';

    var $flags = array(
        'is_orbital_move' => false,
        'is_inter_system_move' => false,
        'is_friendly_move' => false,
        'is_borg_assimilation' => false,
        'is_civilian' => false,

        'free_dest_planet' => false,

        'in_private_system' => false,
        'has_claim_over_system' => false,

        'skip_action' => false,
        'keep_move_alive' => false,

        'lookout_is_present' => false,

        'combat_happened' => false
    );
    
    var $star_trail = [
        'b' => 320,
        'a' => 480,
        'g' => 960,
        'm' => 1200,
        'l' => 1440
    ];
    
    var $action_data = array();

    var $cmb = array();


    function moves_common(&$db, &$cur_move, $CURRENT_TICK) {
        $this->db = $db;

        $this->move = $cur_move;
        $this->mid = $this->move['move_id'];

        $this->CURRENT_TICK = $CURRENT_TICK;

        /* 02/04/08 - AC: Load user language */
        $sql = 'SELECT language FROM user WHERE user_id = '.$this->move['user_id'];
        if(!($lang = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Could not retrieve player '.$this->move['user_id'].' (move) language! SET to ENG');
            $this->move['language'] = 'ENG';
        }
        else
            $this->move['language'] = $lang['language'];
        /* */
        $sql = 'SELECT user_name, user_race FROM user WHERE user_id = '.$this->move['user_id'];
        if(!($name = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Could not retrieve player '.$this->move['user_id'].' (move) name! SET to Unknown');
            $this->move['user_name'] = 'Unknown';
            $this->move['user_race'] = 0;
        }
        else {
            $this->move['user_name'] = $name['user_name'];
            $this->move['user_race'] = $name['user_race'];            
        }
        if($this->move['user_id'] != $this->move['owner_id']) {
            $name = $this->db->queryrow('SELECT user_name, user_race FROM user WHERE user_id = '.$this->move['owner_id']);
            $this->move['alt_id'] = $this->move['owner_id'];
            $this->move['alt_name'] = $name['user_name'];
            $this->move['alt_race'] = $name['user_race'];            
        }
        else {
            $this->move['alt_id'] = $this->move['user_id'];            
            $this->move['alt_name'] = $this->move['user_name'];
            $this->move['alt_race'] = $this->move['user_race'];
        }        
    }

    function log($level, $message) {
        global $sdl;

        switch($level) {
            case MV_M_NOTICE:
                $sdl->log('- Moves Notice: (move_id: '.$this->mid.') '.$message);
            break;

            case MV_M_ERROR:
                $sdl->log('- Moves Error: (move_id: '.$this->mid.') '.$message);
            break;

            case MV_M_DATABASE:
                $sdl->log('- Moves Database Error: (move_id: '.$this->mid.') '.$message.' - '.$this->db->error['message'].' - '.$this->db->error['sql']);
            break;
            case MV_M_CRITICAL:
                $sdl->log('- Moves CRITICAL: (move_id: '.$this->mid.') '.$message);
            break;

            default:
                $sdl->log($level.': (move_id: '.$this->mid.') '.$message);
            break;

        }

        return MV_EXEC_ERROR;
    }

    function report($reason) {

        $message = addslashes($reason.NL.NL.
                              'move_id: <b>'.$this->mid.'</b>'.NL.
                              'user_id: <b>'.$this->move['user_id'].'</b>'.NL.
                              'action_code: <b>'.$this->move['action_code'].'</b>'.NL.
                              'start: <b>'.$this->move['start'].'</b>'.NL.
                              'dest: <b>'.$this->move['dest'].'</b>'.NL.
                              'move_begin: <b>'.$this->move['move_begin'].'</b>'.NL.
                              'move_finish: <b>'.$this->move['move_finish'].'</b>'.NL.
                              'processing tick: <b>'.$this->CURRENT_TICK.'</b>');

        SystemMessage(DATA_UID, 'Move ('.$reason.')', $message);
    }

    function deactivate($to_status) {
        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['start'].',
                    move_id = 0
                WHERE move_id = '.$this->mid;

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update fleets location data before move deativication! CONTINUE');
        }

        $sql = 'UPDATE scheduler_shipmovement
                SET move_status = '.$to_status.'
                WHERE move_id = '.$this->mid;

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update move status data to deactivation status '.$to_status.'! CONTINUE');
        }

        $this->log(MV_M_NOTICE, 'Deactivate move to status '.$to_status);
    }

    function get_combat_query_fleet_columns() {
        return 'f.fleet_id, f.fleet_name, f.n_ships, u.user_name AS owner_name, o.officer_name';
    }

    function get_combat_query_ship_columns() {
        return 's.ship_id, s.hitpoints, s.unit_1, s.unit_2, s.unit_3, s.unit_4, s.experience, s.user_id, s.fleet_id,
                st.value_1, st.value_2, st.value_3, st.value_4, st.value_5, st.value_6, st.value_7, st.value_8, st.value_10, st.value_11, st.value_12, st.name, st.ship_torso, st.ship_class, st.race';
    }

    function check_academy_status() {
        // Checks if the training queues of a planet are consistent with the structures of the planet itself.
        // It's poorly written and with abuse of the continue, but that day I was so that ...

        $no_academy = false;
        $no_unit_3 = false;
        $no_unit_2 = false;
        $academy_is_working = false;
        $academy_next_tick = 0;

        $sql = 'SELECT unittrainid_1, unittrainid_2, unittrainid_3, unittrainid_4, unittrainid_5,
                       unittrainid_6, unittrainid_7, unittrainid_8, unittrainid_9, unittrainid_10,
                       unittrain_actual, unittrainid_nexttime, building_6, research_4
                FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Error reading academy queues info of planet <b>'.$this->move['dest'].'</b>');
        }
        else
        {
            $academy_level = $planet_info['building_6'];
            if ($academy_level == 0) $no_academy = true;
            if ($academy_level < 5) $no_unit_2 = true;
            if ($academy_level < 9) $no_unit_3 = true;
            if ($planet_info['unittrain_actual'] != 0) $academy_is_working = true;
            if ($planet_info['unittrainid_nexttime'] != 0)
                $academy_next_tick = $planet_info['unittrainid_nexttime']; // Can't remember the meaning of this...

            if ($no_academy)
            {
                // No academy at all, so i reset all academy fields. Big overhead but little risks.
                $sql = 'UPDATE planets
                        SET unittrainid_1 = 0, unittrainnumber_1 = 0, unittrainnumberleft_1 = 0, unittrainendless_1 = 0,
                            unittrainid_2 = 0, unittrainnumber_2 = 0, unittrainnumberleft_2 = 0, unittrainendless_2 = 0,
                            unittrainid_3 = 0, unittrainnumber_3 = 0, unittrainnumberleft_3 = 0, unittrainendless_3 = 0,
                            unittrainid_4 = 0, unittrainnumber_4 = 0, unittrainnumberleft_4 = 0, unittrainendless_4 = 0,
                            unittrainid_5 = 0, unittrainnumber_5 = 0, unittrainnumberleft_5 = 0, unittrainendless_5 = 0,
                            unittrainid_6 = 0, unittrainnumber_6 = 0, unittrainnumberleft_6 = 0, unittrainendless_6 = 0,
                            unittrainid_7 = 0, unittrainnumber_7 = 0, unittrainnumberleft_7 = 0, unittrainendless_7 = 0,
                            unittrainid_8 = 0, unittrainnumber_8 = 0, unittrainnumberleft_8 = 0, unittrainendless_8 = 0,
                            unittrainid_9 = 0, unittrainnumber_9 = 0, unittrainnumberleft_9 = 0, unittrainendless_9 = 0,
                            unittrainid_10 = 0, unittrainnumber_10 = 0, unittrainnumberleft_10 = 0, unittrainendless_10 = 0,
                            unittrain_actual = 0, unittrainid_nexttime = 0, unittrain_error = 0
                        WHERE planet_id = '.$this->move['dest'];
                if (!$this->db->query($sql))
                {
                    $this->log(MV_M_DATABASE, 'Error emptying academy working queues of planet <b>'.$this->move['dest'].'</b>');
                }
            }
            else
            {
                $sqlpart = '';

                // Ok, we have to check all the academy slots
                for ($index = 1; $index < 11; $index++)
                {
                    if ($planet_info['unittrainid_'.$index] == 0) continue;

                    if ($planet_info['unittrainid_'.$index] == 2 && $no_unit_2)
                    {
                        // Gotcha! Get out from the queue!
                        $planet_info['unittrainid_'.$index] = 0;
                        $sqlpart .= 'unittrainid_'.$index.' = 0,
                            unittrainnumber_'.$index.' = 0,
                            unittrainnumberleft_'.$index.' = 0,
                            unittrainendless_'.$index.' = 0,';
                    }

                    if ($planet_info['unittrainid_'.$index] == 3 && $no_unit_3)
                    {
                        // Gotcha! Get out from the queue!
                        $planet_info['unittrainid_'.$index] = 0;
                        $sqlpart .= 'unittrainid_'.$index.' = 0,
                            unittrainnumber_'.$index.' = 0,
                            unittrainnumberleft_'.$index.' = 0,
                            unittrainendless_'.$index.' = 0,';
                    }
                }

                // There is something to remove from the queue?
                if($sqlpart != '')
                {
                    $sql = 'UPDATE planets
                            SET '.rtrim($sqlpart,',').'
                            WHERE planet_id = '.$this->move['dest'];
                    if (!$this->db->query($sql))
                    {
                        $this->log(MV_M_DATABASE, 'Error updating planet <b>'.$this->move['dest'].'</b> academy queues info');
                    }
                }

                // Check the active slot. If the active slot is now empty, go check for the next working slot and make it active.
                $active_slot = $planet_info['unittrain_actual'];
                $tries = 0;

                while ($academy_is_working && ($planet_info['unittrainid_'.$active_slot] == 0))
                {
                    $tries++;

                    if ($tries > 9)
                    {
                        $academy_is_working = false;
                        $sql = 'UPDATE planets
                                SET unittrain_actual = 0, unittrainid_nexttime = 0,
                                    unittrain_error = 0
                                WHERE planet_id = '.$this->move['dest'];
                        if (!$this->db->query($sql))
                        {
                            $this->log(MV_M_DATABASE, 'Error clearing OLD academy active slot of planet <b>'.$this->move['dest'].'</b>');
                        }
                        continue;
                    }

                    $active_slot++;
                    if($active_slot > 10) $active_slot = 1;

                    if($planet_info['unittrainid_'.$active_slot] == 0) continue;

                    $academy_next_tick = $this->CURRENT_TICK+UnitTimeTicksScheduler($planet_info['unittrainid_'.$active_slot],
                        $planet_info['research_4'],$this->dest['user_race']);

                    $sql = 'UPDATE planets
                            SET unittrain_actual = '.$active_slot.', unittrainid_nexttime = '.$academy_next_tick.',
                                unittrain_error = 0
                            WHERE planet_id = '.$this->move['dest'];
                    if (!$this->db->query($sql))
                    {
                        $this->log(MV_M_DATABASE, 'Error updating NEW academy active slot of planet <b>'.$this->move['dest'].'</b>');
                    }
                }
            }
        }
    }

    function check_spacedock_status() {
        // Checks if the number of ships that may be present in a spacedock is consistent with the level of the structure

        global $MAX_SPACEDOCK_SHIPS;

        $sql = 'SELECT building_7 FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql)))
        {
            $this->log(MV_M_DATABASE, 'moves_common.check_spacedock_status: error reading planet info');
        }
        else
        {
            $sql = 'SELECT COUNT(*) AS num_ships FROM ships WHERE fleet_id = -'.$this->move['dest'];
            if(!($ship_docked = $this->db->queryrow($sql)))
            {
                $this->log(MV_M_DATABASE, 'moves_common.check_spacedock_status: error reading docked ships info');
            }
            else
            {
                if ($ship_docked['num_ships'] > $MAX_SPACEDOCK_SHIPS[$planet_info['building_7']])
                {
                    $ships_to_delete = $ship_docked['num_ships'] - $MAX_SPACEDOCK_SHIPS[$planet_info['building_7']];
                    $sql = 'DELETE FROM ships WHERE fleet_id = -'.$this->move['dest'].' LIMIT '.$ships_to_delete;
                    $this->log(MV_M_NOTICE, 'moves_common.check_spacedock_status: delete query = '.$sql);
                    $this->db->query($sql);
                    $ships_deleted = $this->db->affected_rows();
                    if($ships_to_delete != $ships_deleted)
                    {
                        $this->log(MV_M_DATABASE, 'moves_common.check_spacedock_status: error deleting ships. Expected '.$ships_to_delete.', MySQL reported '.$ships_deleted);
                    }
                }
            }
        }
    }

    function check_shipyard_status() {
        //If the level of the shipyard reaches zero, delete all the ships (if any) in the construction queue

        $sql = 'SELECT building_8 FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql)))
        {
            $this->log(MV_M_DATABASE, 'moves_common.check_shipyard_status: error reading planet info');
        }
        else
        {
            if ($planet_info['building_8'] == 0)
            {
                $sql = 'DELETE FROM scheduler_shipbuild WHERE planet_id = '.$this->move['dest'];
                $this->db->query($sql);
            }
        }
    }

    function check_sytem_ownership($system_id) {
        // After a planet takeover, check if the system ownership is changed.
        // Rules for system ownership:
        // - System with a number of planets >= 3
        // - Player must control half the planets in the system
        // - Player controlled planets score must be at least = 3*320

        $sql = 'SELECT starsystems.*, user.language FROM starsystems LEFT JOIN user ON (system_owner = user_id) WHERE system_id = '.$system_id;

        if(($ss_info =  $this->db->queryrow($sql)) === FALSE) {
            $this->log(MV_M_DATABASE, 'check_sytem_ownership.moves_common: error reading ss info. Offending query: '.$sql);
        }

        if($ss_info['system_owner'] == 0) {
            $this->log(MV_M_NOTICE, 'check_sytem_ownership.moves_common: starsystem with no owner; nothing to do.');
            return;
        }

        if($ss_info['system_n_planets'] < 3) {
            $this->log(MV_M_NOTICE, 'check_sytem_ownership.moves_common: starsystem with less than 3 planets; nothing to do.');
            return;
        }

        $sql = 'SELECT planet_points FROM planets WHERE planet_owner = '.$ss_info['system_owner'].' AND system_id = '.$system_id;

        if(($p_info =  $this->db->queryrowset($sql)) === FALSE) {
            $this->log(MV_M_DATABASE, 'check_sytem_ownership.moves_common: error reading planets info. Offending query: '.$sql);
        }

        $planet_owned_cnt = $planet_owned_pts = 0;

        foreach ($p_info as $planet) {
            $planet_owned_cnt++;
            $planet_owned_pts += $planet['planet_points'];
        }

        if(($planet_owned_pts >= 3*320) && ($planet_owned_cnt >= round($ss_info['system_n_planets']/2))) {
            $this->log(MV_M_NOTICE, 'check_sytem_ownership.moves_common: starsystem '.$system_id.' onwership confirmed!');
            return;
        }

        $sql = 'UPDATE starsystems SET system_owner = 0, system_closed = 0 WHERE system_id = '.$system_id;

        if($this->db->query($sql) === FALSE) {
            $this->log(MV_M_DATABASE, 'check_sytem_ownership.moves_common: cannot update starsystem data. Offending query: '.$sql);
        }

        $sql = 'DELETE FROM starsystems_details WHERE system_id = '.$system_id.' AND log_code = 100';

        if($this->db->query($sql) === FALSE) {
            $this->log(MV_M_DATABASE, 'check_sytem_ownership.moves_common: cannot update system lock challenges data. Offending query: '.$sql);
        }

        $this->log(MV_M_NOTICE, 'check_sytem_ownership.moves_common: starsystem '.$system_id.' onwership has been reset!');

        $sql = 'SELECT system_id, system_global_x, system_global_y FROM starsystems_details INNER JOIN starsystems USING (system_id) WHERE user_id = '.$ss_info['system_owner'].' AND log_code = 100';

        $res = $this->db->queryrowset($sql);

        $this->log(MV_M_NOTICE,'user '.$ss_info['system_owner'].' still have #'.count($res).' claim(s)');

        if(count($res)> 0) {
            // Verifica che i claim originati da questo sistema siano ancora validi
            $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_closed > 0 AND system_owner = '.$ss_info['system_owner'];
            $list = $this->db->queryrowset($sql);
            $this->log(MV_M_NOTICE,'user '.$ss_info['system_owner'].' still have #'.count($list).' private system(s)');
            foreach ($res AS $claim) {
                $min_range = 100000;
                for($i = 0; $i < count($list); $i++) {
                    $range = $this->get_distance(array($claim['system_global_x'], $claim['system_global_y']), array($list[$i]['system_global_x'], $list[$i]['system_global_y']));
                    $min_range = ($range < $min_range ? $range : $min_range);
                }
                if(count($list) == 0 || $min_range > CLAIM_SYSTEM_RANGE) {
                    $this->db->query('DELETE FROM starsystems_details WHERE system_id = '.$claim['system_id'].' AND user_id = '.$ss_info['system_owner'].' AND log_code = 100');
                    $this->log(MV_M_NOTICE, 'check_system_ownership.moves_common: claim of user '.$ss_info['system_owner'].' over system '.$claim['system_id'].' has been revoked!!!');
                    switch ($ss_info['language']) {
                        case 'ITA':
                            $header = 'Rivendicazione revocata';
                            $message = 'Si comunica che, in data odierna, la sua rivendicazione sul sistema '.$claim['system_name'].' &eacute; stata revocata!';
                            break;
                        default :
                            $header = 'Claim revoked';
                            $message = 'We inform you that your claim over '.$claim['system_name'].' has been revoked!';
                            break;
                    }
                    SystemMessage($ss_info['system_owner'], $header, $message);
                }
            }
        }
    }

    function check_rc_status() {
        //If the research centre level reaches zero, delete all techpoints and set add_t to 0
        $sql = 'SELECT building_9 FROM planets WHERE planet_id = '.$this->move['dest'];
        if(!($planet_info = $this->db->queryrow($sql)))
        {
            $this->log(MV_M_DATABASE, 'moves_common.check_RC_status: error reading planet info');
        }
        else
        {
            if ($planet_info['building_9'] == 0)
            {
                $sql = 'UPDATE planets SET techpoints = 0, add_t = 0 WHERE planet_id = '.$this->move['dest'];
                $this->db->query($sql);
            }
        }
    }

    function check_best_mood($planet_id, $notify) {
        /* This function will check all moods value on an indipedent planet and will set
        * the fields best_mood and best_mood_user
        */

        /* AC: Domanda: questa query non si poteva scrivere nel modo seguente?
         *
         *  $sql = 'SELECT best_mood, best_mood_user FROM planets
         *          WHERE planet_id = '.$planet_id.' AND planet_owner = '.INDIPENDENT_USERID;
         *
         *  if(!($q_p = $this->db->queryrow($sql));
         *      return -1;
         */
        $sql = 'SELECT planet_name, planet_owner, best_mood, best_mood_user FROM planets
                WHERE planet_id = '.$planet_id;

        $q_p = $this->db->queryrow($sql);

        if(empty($q_p['planet_owner']) || $q_p['planet_owner'] != INDEPENDENT_USERID) {
            return -1;
        }

        /* AC: Questa parte potrebbe essere riscritta nel modo seguente:
         *
         *  $sql = 'SELECT user_id, SUM(mood_modifier) AS mood FROM settlers_relations
         *           WHERE planet_id = '.$planet_id.' GROUP BY user_id ORDER BY timestamp ASC';
         *  $q_p_m = $this->db->queryrowset($sql);
         *
         *  foreach($q_p_m as $q_m) {
         *      if($q_m['mood'] > $best) {
         *           $newbest = true;
         *           $best = $q_m['mood'];
         *           $best_id = $q_m['user_id'];
         *       }
         *   }
         *   if($newbest) {
         *       $sql = 'UPDATE planets
         *               SET best_mood = '.$best.',
         *                   best_mood_user = '.$best_id.'
         *               WHERE planet_id = '.$planet_id;
         *       $this->db->query($sql);
         *   }
         */
        $sql = 'SELECT user_id, SUM(mood_modifier) AS mood FROM settlers_relations
                WHERE planet_id = '.$planet_id.' GROUP BY user_id ORDER BY timestamp ASC';

        $q_p_m = $this->db->query($sql);

        $rows = $this->db->num_rows($q_p_m);

        $best = $best_id = 0;

        if($rows > 0) {
            /* Db popolato.
            * Scansione dei risultati della query e ricerca del valore più alto.
            * Viene aggiornato il db
            */
            $q_m = $this->db->fetchrowset($q_p_m);
            // Non che mi piaccia molto ma vabbè...
            $best = $q_m[0]['mood'];
            $best_id = $q_m[0]['user_id'];
            for($i=0; $i < $rows; $i++) {
                if($q_m[$i]['mood'] > $best) {
                    $best = $q_m[$i]['mood'];
                    $best_id = $q_m[$i]['user_id'];
                }
            }
            $sql = 'UPDATE planets SET best_mood = '.$best.', best_mood_user = '.$best_id.', npc_last_action = '.($this->CURRENT_TICK+1).' WHERE planet_id = '.$planet_id;
            $this->db->query($sql);
        }

        if(!empty($best_id) && $q_p['best_mood_user'] != $best_id && $notify) {
            return $q_p['best_mood_user'];
        }

        return 0;
    }

    function get_distance($s_system, $d_system) {
        global $SYSTEM_WIDTH;

        /*
        $s_system[0] -> global X-Coordinate
        $s_system[1] -> global Y-Coordinate
        */

        if($s_system[0] == $d_system[0]) {
            $distance = abs( ( ($s_system[1] - $d_system[1]) * $SYSTEM_WIDTH) );
        }
        elseif($s_system[1] == $d_system[1]) {
            $distance = abs( ( ($s_system[0] - $d_system[0]) * $SYSTEM_WIDTH) );
        }
        else {
            $triangle_a = ($s_system[1] - $d_system[1]);
            $triangle_b = ($s_system[0] - $d_system[0]);

            $distance = ( sqrt( ( ($triangle_a * $triangle_a) + ($triangle_b * $triangle_b) ) ) * $SYSTEM_WIDTH );
        }

        return $distance;
    }

    function get_structure_points($player_id, $dest) {
        // Calculate the points structure for the planet

        global $MAX_POINTS;

        $points = $MAX_POINTS[0];

        // Mother system of the player
        $sql = 'SELECT system_id FROM planets WHERE planet_owner = '.$player_id.' AND planet_owner_enum = 0';
        if(!($mosystem = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read capital planet data! SKIP');
        }

        $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_id = '.$mosystem['system_id'];
        if(!($mocoord = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read capital starsystem data! SKIP');
        }

        // Destination system
        $sql = 'SELECT system_id FROM planets WHERE planet_id = '.$dest;
        if(!($destsystem = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read destination planet data! SKIP');
        }

        $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_id = '.$destsystem['system_id'];
        if(!($destcoord = $this->db->queryrow($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read destination starsystem data! SKIP');
        }

        $distance = $this->get_distance(array($mocoord['system_global_x'], $mocoord['system_global_y']),
                                        array($destcoord['system_global_x'], $destcoord['system_global_y']));
        $distance = round($distance, 2);

        if($distance > MAX_BOUND_RANGE) $points = $MAX_POINTS[2];

        return $points;
    }

    function do_ship_combat(&$atk_fleet_ids_str, &$dfd_fleet_ids_str, $combat_level) {
        global $config;

        if(empty($atk_fleet_ids_str)) $atk_fleet_ids_str = '-1';
        if(empty($dfd_fleet_ids_str)) $dfd_fleet_ids_str = '-1';

        // When $combat_level == MV_COMBAT_LEVEL_PLANETARY the owner is always attacked
        //                        --> fight with all orbitals

        /*
        if($combat_level == MV_COMBAT_LEVEL_OUTER) {
            $n_large_orbital_defense = $n_small_orbital_defense = 0;
        }
        else {
            $n_large_orbital_defense = (int)$this->dest['building_10'];
            $n_small_orbital_defense = (int)$this->dest['building_13'];

            if($combat_level == MV_COMBAT_LEVEL_ORBITAL) {
                if ($n_large_orbital_defense < 20) $n_large_orbital_defense = (($n_large_orbital_defense < 10) ? $n_large_orbital_defense : 10);
                else $n_large_orbital_defense /= 2;

                if ($n_small_orbital_defense < 20) $n_small_orbital_defense = (($n_small_orbital_defense < 10) ? $n_small_orbital_defense : 10);
                else $n_small_orbital_defense /= 2;

                settype($n_large_orbital_defense, 'int');
                settype($n_small_orbital_defense, 'int');
            }
        }
        */

        $n_large_orbital_defense = $n_small_orbital_defense = 0;

        $bin_output = array();

        $cmd_line = MV_COMBAT_BIN_PATH.' '.$atk_fleet_ids_str.' '.$dfd_fleet_ids_str.' '.$this->move['dest'].' '.$n_large_orbital_defense.' '.$n_small_orbital_defense;
        $auth_line = ' '.$config['game_database'].' '.$config['user'].' '.$config['password'];
        exec($cmd_line.$auth_line, $bin_output);
        $this->log(MV_M_NOTICE, 'combat executable params:'.$cmd_line.$auth_line.' '.substr($bin_output[0], 1));

        if($bin_output[0][0] == '0' || is_null($bin_output[0][0])) {
            return $this->log(MV_M_ERROR, 'Combat Binary exited with an error ('.substr($bin_output[0], 1).' - '.$cmd_line.')');
        }

        $this->cmb[MV_CMB_WINNER] = $bin_output[1];
        $this->cmb[MV_CMB_KILLS_PLANETARY] = (int)$bin_output[2];
        $this->cmb[MV_CMB_KILLS_SPLANETARY] = (int)$bin_output[3];
        $this->cmb[MV_CMB_KILLS_EXT] = &$bin_output[4];

        /*
        if($this->cmb[MV_CMB_KILLS_PLANETARY] != 0) {
            $this->dest['building_10'] -= $this->cmb[MV_CMB_KILLS_PLANETARY];
        }

        if($this->cmb[MV_CMB_KILLS_SPLANETARY] != 0) {
            $this->dest['building_13'] -= $this->cmb[MV_CMB_KILLS_SPLANETARY];
        }
        */

        return MV_EXEC_OK;
    }

    function make_felon($user1_id, $user1_name, $user2_id) {

        if($user1_id < 12) {return;}

        $sql = 'DELETE FROM user_diplomacy WHERE (user1_id = '.$user1_id.' AND user2_id = '.$user2_id.') OR (user2_id = '.$user1_id.' AND user1_id = '.$user2_id.')';

        $this->db->query($sql);

        $sql = 'INSERT INTO user_felony (user1_id, user2_id, date)
        VALUES ('.$user1_id.', '.$user2_id.', '.time().')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'make_felon.moves_common: Could not insert new user felon data! Offending query:'.$sql);
        }

        SystemMessage($user2_id, 'Dichiarazione proveniente da '.$user1_name, 'Il giocatore in oggetto ti ha appena dichiarato unilateralmente <i>rivale</i> e non accetter&agrave; alcun accordo commerciale con te in futuro.');
    }

    function spam_takeover($atk_id, $atk_name, $def_id, $def_name, $planet_name, $system_name) {

        $sql = 'SELECT user_id, language FROM user WHERE user_id > 10 AND user_id NOT IN ("'.$atk_id.'", "'.$def_id.'") AND user_active = 1 AND user_auth_level = 1 AND user_vacation_end < '.$this->CURRENT_TICK;

        $warn_list = $this->db->queryrowset($sql);

        foreach ($warn_list AS $warn_item){
            switch ($warn_item['language']) {
                case 'ITA':
                    $header = 'Conquista di un pianeta';
                    $message = 'I servizi informativi comunicano la conquista da parte di <b>'.$atk_name.'</b> del pianeta <b>'.$planet_name.', '.$system_name.'</b>  appartenuto a <b>'.$def_name.'</b>.';
                    break;
                default :
                    $header = 'Planet Takeover';
                    $message = 'Intelligence service report the takeover by <b>'.$atk_name.'</b> of the planet <b>'.$planet_name.', '.$system_name.'</b>  held before by <b>'.$def_name.'</b>.';
                    break;
            }

            SystemMessage($warn_item['user_id'], $header, $message);
        }

    }

    function spawn_pirate($system_id,$orion_level){

        $num = array(0, 0, 0, 0, 0);
        $orion_seed = false;

        if(rand(0,99) > (43+($orion_level*8))) {
            $this->log(MV_M_NOTICE,'Nessuna flotta Orioniana creata questa volta, soglia: '.(43+($orion_level*8)));    
            return;
        }
 
        if($this->flags['in_private_system'] > 0) {return;}

        /*
         * LVL 4: 180 ships; 60 - 30 - 10
         * LVL 3: 90 ships; 60 - 40
         * LVL 2: 45 ships; 75 - 25
         * LVL 1: 4 ships; 100 - 0
         */ 

        if($orion_level >= 4) {
            // Big Fleet
            $num[5]=rand(18,20);
            $num[4]=rand(54,60);
            $num[2]=rand(104,112);
        }elseif($orion_level >= 3){
            // Medium Fleet
            $num[4]=rand(34,38);
            $num[2]=rand(52,58);
        }elseif($orion_level >= 2){
            // Small Fleet
            $num[3]=rand(10,14);
            $num[2]=rand(33,39);
        }else {
            // Very Small Fleet
            $num[1]=rand(2,5);
        }


        // DC 170618 - Bubble System
        /*
        switch ($this->move['user_race'])
        {

            case 0: // Fed
            case 1: // Rom
                if($orion_level >= 4) {
                    // Big Fleet
                    $num[5]=rand(18,20);
                    $num[4]=rand(54,60);
                    $num[2]=rand(108,112);
                }elseif($orion_level >= 3){
                    // Medium Fleet
                    $num[4]=rand(36,38);
                    $num[2]=rand(54,56);
                }elseif($orion_level >= 2){
                    // Small Fleet
                    $num[4]=rand(12,14);
                    $num[2]=rand(36,38);
                }else {
                    // Very Small Fleet
                    $num[2]=rand(3,5);
                }
                break;
            case 2: // Klingon
            case 3: // Cardassiani
                if($orion_level >= 4) {
                    // Big Fleet
                    $num[5]=18;
                    $num[4]=54;
                    $num[2]=108;
                }elseif($orion_level >= 3){
                    // Medium Fleet
                    $num[4]=36;
                    $num[2]=54;
                }elseif($orion_level >= 2){
                    // Small Fleet
                    $num[4]=12;
                    $num[2]=36;
                }else {
                    // Very Small Fleet
                    $num[2]=rand(1,4);
                }
                break;
            case 4: // Dominio
            case 8: // Breen
            case 9: // Hirogeni
                if($orion_level >= 4) {
                    // Big Fleet
                    $num[5]=rand(18,22);
                    $num[4]=rand(54,64);
                    $num[2]=rand(108,118);
                }elseif($orion_level >= 3){
                    // Medium Fleet
                    $num[4]=rand(36,40);
                    $num[2]=rand(54,60);
                }elseif($orion_level >= 2){
                    // Small Fleet
                    $num[4]=rand(12,16);
                    $num[2]=rand(36,40);
                }else {
                    // Very Small Fleet
                    $num[2]=rand(4,6);
                }
                break;
            case 5: // Ferengi
            case 11: // Kazon
                if($orion_level >= 4) {
                    // Big Fleet
                    $num[5]=rand(18,20);
                    $num[3]=rand(54,60);
                    $num[1]=rand(108,112);
                }elseif($orion_level >= 3){
                    // Medium Fleet
                    $num[3]=rand(36,38);
                    $num[1]=rand(54,56);
                }elseif($orion_level >= 2){
                    // Small Fleet
                    $num[3]=rand(12,14);
                    $num[1]=rand(36,38);
                }else {
                    // Very Small Fleet
                    $num[1]=rand(3,5);
                }
                break;
            default :
                if($orion_level >= 4) {
                    // Big Fleet
                    $num[4]=rand(4,9);
                    $num[3]=rand(7,21);
                    $num[2]=rand(24,52);
                    $num[1]=rand(40,100);
                }elseif($orion_level >= 3){
                    // Medium Fleet
                    $num[3]=rand(1,3);
                    $num[2]=rand(4,9);
                    $num[1]=rand(12,38);
                }elseif($orion_level >= 2){
                    // Small Fleet
                    $num[2]=rand(1,3);
                    $num[1]=rand(4,9);
                }else {
                    // Very Small Fleet
                    $num[1]=rand(1,3);
                }
                break;
        }
        // ---
         * 
         */

        for($i = 1;$i < 6;$i++) {
            if($num[$i] == 0 ) {continue;}
            $sql = 'SELECT orion_tmp_'.$i.' FROM config WHERE config_set_id = 0';
            if(!($q_tmp = $this->db->queryrow($sql))) {
                return $this->log(MV_M_DATABASE, 'Could not read config data! SKIP');
            }
            $orion_ids['orion_tmp_'.$i] = $q_tmp['orion_tmp_'.$i];

            $sql = 'SELECT max_unit_1, max_unit_2, max_unit_3, max_unit_4, rof, rof2, max_torp,
                           value_5, value_9
                    FROM `ship_templates` WHERE `id` = '.$orion_ids['orion_tmp_'.$i];
            if(($stpl = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not query ship template data - '.$sql);
            }
            $orion_tpl['orion_tpl_'.$i] = $stpl;
        }

        $sql = 'SELECT planet_id, planet_type FROM planets WHERE system_id = '.$system_id;
        if(!($q_planets = $this->db->queryrowset($sql))) {
            return $this->log(MV_M_DATABASE, 'Could not read planet ids! SKIP');
        }

        $planet = $q_planets[array_rand($q_planets)];

        $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, owner_id, planet_id, alert_phase, is_civilian, move_id, n_ships)
            VALUES ("Pirate Fleet", '.ORION_USERID.', '.ORION_USERID.', '.$planet['planet_id'].', '.ALERT_PHASE_RED.', 0, 0, '.(array_sum($num)).')';
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not insert new fleet data');
        }

        $fleet_id = $this->db->insert_id();

        if(!$fleet_id) {$this->log(MV_M_DATABASE, 'Error - '.$fleet_id.' = empty');}


        for($i = 1; $i < 6; $i++) {
            if($num[$i] == 0 ) {continue;}
            $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, last_refit_time,
                                       rof, rof2, torp, unit_1, unit_2, unit_3, unit_4)
                    VALUES ('.$fleet_id.', '.ORION_USERID.', '.$orion_ids['orion_tmp_'.$i].', '.$orion_tpl['orion_tpl_'.$i]['value_9'].',
                            '.$orion_tpl['orion_tpl_'.$i]['value_5'].', '.time().', '.time().',
                            '.$orion_tpl['orion_tpl_'.$i]['rof'].', '.$orion_tpl['orion_tpl_'.$i]['rof2'].', '.$orion_tpl['orion_tpl_'.$i]['max_torp'].',
                            '.$orion_tpl['orion_tpl_'.$i]['max_unit_1'].', '.$orion_tpl['orion_tpl_'.$i]['max_unit_2'].',
                            '.$orion_tpl['orion_tpl_'.$i]['max_unit_3'].', '.$orion_tpl['orion_tpl_'.$i]['max_unit_4'].')';

            for ($i2 = 0; $i2 < $num[$i]; $i2++) {
                if(!$this->db->query($sql)) {
                    return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not insert new ships data');
                }
            }
        }

        $this->log(MV_M_NOTICE,'Orion Fleet ID '.$fleet_id.' with '.(array_sum($num)).' spawned; Player ID '.$this->move['user_id'].', System_id '.$this->dest['system_id']);

        //$test = $this->db->queryrow('SELECT COUNT(*) as num FROM starsystems_details WHERE log_code = 0 AND system_id = '.$system_id);
        $res = $this->db->queryrow('SELECT orion_spawn_counter FROM config WHERE config_set_id = 0');

        if($res['orion_spawn_counter'] > 0 && $orion_level >= 4) {
            $sql = 'SELECT planet_id FROM planets WHERE system_id = '.$system_id.' AND planet_owner = 0 AND planet_type IN ("m", "o", "p", "e", "f", "g")';
            if(!($q_planets = $this->db->queryrowset($sql))) {
                return $this->log(MV_M_DATABASE, 'Could not read planet id! SKIP');
            }
            if($this->db->num_rows($q_planets) > 0){
                $planet = $q_planets[array_rand($q_planets)];

                $sql = 'UPDATE planets
                        SET npc_last_action = 0,
                            planet_owner = '.ORION_USERID.',
                            planet_name = "Orion Cove #'.$planet['planet_id'].'",
                            best_mood = 0,
                            best_mood_user = 0,
                            planet_available_points = 320,
                            planet_owned_date = '.time().',
                            resource_4 = 1000,
                            planet_next_attack = 0,
                            planet_attack_ships = 0,
                            planet_attack_type = 0,
                            research_1 = '.(rand(0,5)+1).',
                            research_2 = '.(rand(0,5)+1).',
                            research_3 = '.(rand(0,5)+4).',
                            research_4 = '.(rand(0,5)+2).',
                            research_5 = 0,
                            recompute_static = 1,
                            building_1 = 9,
                            building_2 = 4,
                            building_3 = 4,
                            building_4 = 4,
                            building_5 = 9,
                            building_6 = 9,
                            building_7 = 5,
                            building_8 = 5,
                            building_9 = 5,
                            building_10 = '.(rand(0,10)+2).',
                            building_11 = 5,
                            building_12 = 5,
                            building_13 = '.(rand(0,10)+2).',
                            unit_1 = 1500,
                            unit_2 = 1000,
                            unit_3 = 500,
                            unit_4 = 25,
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
                            unittrainid_1 = 0, unittrainid_2 = 0, unittrainid_3 = 0, unittrainid_4 = 0, unittrainid_5 = 0, unittrainid_6 = 0, unittrainid_7 = 0, unittrainid_8 = 0, unittrainid_9 = 0, unittrainid_10 = 0,
                            unittrainnumber_1 = 0, unittrainnumber_2 = 0, unittrainnumber_3 = 0, unittrainnumber_4 = 0, unittrainnumber_5 = 0, unittrainnumber_6 = 0, unittrainnumber_7 = 0, unittrainnumber_8 = 0, unittrainnumber_9 = 0, unittrainnumber_10 = 0,
                            unittrainnumberleft_1 = 0, unittrainnumberleft_2 = 0, unittrainnumberleft_3 = 0, unittrainnumberleft_4 = 0, unittrainnumberleft_5 = 0, unittrainnumberleft_6 = 0, unittrainnumberleft_7 = 0, unittrainnumberleft_8 = 0, unittrainnumberleft_9 = 0, unittrainnumberleft_10 = 0,
                            unittrainendless_1 = 0, unittrainendless_2 = 0, unittrainendless_3 = 0, unittrainendless_4 = 0, unittrainendless_5 = 0, unittrainendless_6 = 0, unittrainendless_7 = 0, unittrainendless_8 = 0, unittrainendless_9 = 0, unittrainendless_10 = 0,
                            unittrain_actual = 0,
                            unittrainid_nexttime=0,
                            planet_surrender = 0,
                            planet_insurrection_time=0
                        WHERE planet_id = '.$planet['planet_id'];

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
                }

                $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                        VALUES ('.$planet['planet_id'].', '.ORION_USERID.', 0, '.ORION_USERID.', 0, '.time().', 25)';

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update planet details data!');
                }

                $this->db->query('UPDATE config SET orion_spawn_counter = orion_spawn_counter -1 WHERE config_set_id = 0');
            }
        }

        $test = $this->db->queryrow('SELECT COUNT(*) as num FROM planets WHERE planet_owner > 10 AND system_id = '.$system_id);

        if ($test['num'] > 0) {
            $finish_tick = $this->CURRENT_TICK + 30;
            $distance = 30 * 61;
        }
        else {
            $finish_tick = $this->CURRENT_TICK + 6;
            $distance = 6 * 61;
        }

        $sql = 'INSERT INTO scheduler_shipmovement (user_id, owner_id, move_is_civilian, race_trail, start, dest, total_distance, remaining_distance, tick_speed, warp_speed, move_begin, move_finish, n_ships, action_code, action_data)
                VALUES ('.ORION_USERID.', '.ORION_USERID.', 0, 7, 0, '.$planet['planet_id'].', '.$distance.', '.$distance.', 61, 7.5, '.$this->CURRENT_TICK.', '.$finish_tick.', '.(array_sum($num)).', 11, "")';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not insert new fleet movement data');
        }

        $move_id = $this->db->insert_id();

        $sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$move_id.' WHERE fleet_id = '.$fleet_id;

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not update fleet with movement data');
        }

    }

    function _main() {
        $start_processing_time = time() + microtime();

        // #############################################################################
        // On...

        $this->flags['is_orbital_move'] = ($this->move['start'] == $this->move['dest']);
        $this->flags['is_friendly_action'] = in_array($this->move['action_code'], array(23, 31, 33));
        $this->flags['is_borg_assimilation'] = ($this->move['action_code'] == 46);
        $this->flags['is_simple_move'] = in_array($this->move['action_code'], array(11, 12, 21));
        $this->flags['is_civilian'] = ($this->move['move_is_civilian'] == -1 ? false :  (bool)$this->move['move_is_civilian']);

        // #############################################################################
        // Security checks

        if(empty($this->mid)) {
            $this->deactivate(31);
            $this->report('Deactivated due to empty move data');

            return MV_EXEC_ERROR;
        }

        if($this->move['move_begin'] == $this->move['move_finish']) {
            $this->deactivate(32);
            $this->report('Deactivated due to illegal arrival times');

            return MV_EXEC_ERROR;
        }

        // #############################################################################
        // If the move was already tried 3 times, cancel

        if($this->move['move_exec_started'] >= 3) {
            $this->deactivate(33);
            $this->report('Deactivated due to execution limit');

            return MV_EXEC_ERROR;
        }

        // #############################################################################
        // Exec-Counter setup

        $sql = 'UPDATE scheduler_shipmovement
                SET move_exec_started = move_exec_started + 1
                WHERE move_id = '.$this->mid;

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update move exec started data! SKIP');;
        }

        // #############################################################################
        // Data from the participating fleets

        $sql = 'SELECT sf.fleet_id, sf.fleet_name, sf.n_ships, sf.alert_phase, sf.user_id, sf.owner_id, u.user_name, u.user_race
                FROM ship_fleets sf
                INNER JOIN user u ON (sf.user_id = u.user_id)
                WHERE move_id = '.$this->mid;

        if(!$q_fleets = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not query moves fleets data! SKIP');
        }

        while($_fl = $this->db->fetchrow($q_fleets)) {
            $this->fleet_ids[] = $_fl['fleet_id'];
            $this->fleet_names[] = $_fl['fleet_name'];
            $this->n_ships[] = $_fl['n_ships'];
            if($_fl['alert_phase'] == ALERT_PHASE_RED) {
                $this->ar_fleet_ids[] = $_fl['fleet_id'];
            }
        }

        $this->n_fleets = count($this->fleet_ids);

        if($this->n_fleets == 0) {
            $this->deactivate(34);
            $this->report('Deactivated due to missing fleets');

            return MV_EXEC_ERROR;
        }

        $this->fleet_ids_str = implode(',', $this->fleet_ids);

        $this->ar_n_fleets = count($this->ar_fleet_ids);

        if($this->ar_n_fleets > 0) {$this->ar_fleet_ids_str = implode(',', $this->ar_fleet_ids);}

        // #############################################################################
        // action_data decode, if available

        if(!empty($this->move['action_data'])) {
            $this->action_data = (array)unserialize($this->move['action_data']);
        }

        // #############################################################################
        // Data of start planet

        $sql = 'SELECT p.*,
                       u.user_id, u.user_active, u.user_name, u.user_race, u.user_planets, u.user_alliance, u.user_capital,
                       ss.system_closed, ss.system_owner, ss.system_name, ss.system_startype, ss.system_orion_alert, ss.system_global_x, ss.system_global_y
                FROM (planets p)
                INNER JOIN (starsystems ss) USING (system_id)
                LEFT JOIN user u ON u.user_id = p.planet_owner
                WHERE p.planet_id = '.$this->move['start'];

        if(($this->start = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query start planets data! SKIP');
        }
        settype($this->start['user_id'], 'int');

        // #############################################################################
        // Data of target planet

        if($this->flags['is_orbital_move']) {
            $this->dest = &$this->start;
            /* 03/04/08 - AC: Hmmm, we need language settings here?
             * However set it to english.
             */
            $this->dest['language'] = 'ENG';
        }
        else {
            $sql = 'SELECT p.*,
                           u.user_id, u.user_active, u.user_name, u.user_race, u.user_planets, u.user_alliance, u.user_capital,
                           ss.system_closed, ss.system_owner, ss.system_name, ss.system_startype, ss.system_orion_alert, ss.system_global_x, ss.system_global_y
                    FROM (planets p)
                    INNER JOIN (starsystems ss) USING (system_id)
                    LEFT JOIN (user u) ON u.user_id = p.planet_owner
                    WHERE p.planet_id = '.$this->move['dest'];

            if(($this->dest = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query dest planets! SKIP');
            }

            settype($this->dest['user_id'], 'int');

            $this->flags['in_private_system'] = $this->dest['system_closed'];

            // #############################################################################
            /* 17/06/08 - AC: Load user language only if a player is present*/
            if($this->dest['user_id'] != 0)
            {
                $sql = 'SELECT language FROM user WHERE user_id = '.$this->dest['user_id'];
                if(!($lang = $this->db->queryrow($sql))) {
                    $this->log(MV_M_DATABASE, 'Could not retrieve player '.$this->dest['user_id'].' (dest) language! SET to ENG');
                    $this->dest['language'] = 'ENG';
                }
                else
                    $this->dest['language'] = $lang['language'];
            }
            else {
                $this->dest['language'] = 'ENG';
            }
        }

        // Fleet with no ID must always fail this test
        $sql = 'SELECT timestamp FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND user_id = '.$this->move['user_id'].' AND log_code = 100';

        if(($res = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query claimed system data! SKIP');
        }

        $this->flags['has_claim_over_system'] = !empty($res['timestamp']);

        $this->flags['is_inter_system_move'] = ($this->start['system_id'] == $this->dest['system_id']);        

        // #############################################################################
        // System Surveillance

        /*
        $sql = 'SELECT p.planet_owner, SUM(p.building_7) AS sensor_level, u.language, ss.system_id, ss.system_name
                FROM (planets p)
                INNER JOIN (user u) ON p.planet_owner = u.user_id
                INNER JOIN (starsystems ss) ON p.system_id = ss.system_id
                WHERE p.system_id = '.$this->dest['system_id'].' AND
                    p.planet_owner > 10 AND
                    p.planet_owner <> '.($this->move['user_id'] == $this->move['alt_id'] ? $this->move['user_id'] : $this->move['alt_id']).'
                GROUP BY p.planet_owner';
        */

        $sql = 'SELECT p.planet_owner, SUM(p.building_7) AS sensor_level, u.language, ss.system_id, ss.system_name
                FROM (planets p)
                INNER JOIN (user u) ON p.planet_owner = u.user_id
                INNER JOIN (starsystems ss) ON p.system_id = ss.system_id
                WHERE p.planet_id = '.$this->move['dest'].' AND
                    p.planet_owner > 10 AND
                    p.planet_owner <> '.$this->move['alt_id'].'
                GROUP BY p.planet_owner';                

        if(($surv_list = $this->db->queryrowset($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query surveillance planets data! SKIP');
        }

        // Raccogliamo info sulla flotta IN ARRIVO (ossia quella legata alla mossa in elaborazione)
        // per averle già pronte per la scrittura dei log da mandare ai giocatori.
        // Utilizzo queryrowset perché, salvo cataclismi, la flotta in movimento HA una composizione coerente e leggibile.

        $sql = 'SELECT st.name, st.race, st.ship_torso, st.ship_class, COUNT(st.name) as n_ships
                FROM ship_templates st
                INNER JOIN ships s ON s.template_id = st.id
                INNER JOIN ship_fleets f ON f.fleet_id = s.fleet_id
                WHERE f.fleet_id IN ('.$this->fleet_ids_str.')
                GROUP BY st.name, st.race, st.ship_torso, st.ship_class
                ORDER BY st.ship_class DESC, st.ship_torso DESC';

        $ss_ship_list = $this->db->queryrowset($sql);

        foreach ($surv_list as $surv_user) {
            $log_data[0] = 101; // Codice mossa fasullo per far funzionare il logbook
            $log_data[1] = $this->move['user_id'];
            $log_data[2] = ($this->move['move_hide_start'] ? 0  : $this->move['start']);
            $log_data[3] = ($this->move['move_hide_start'] ? '' : $this->start['planet_name']);
            $log_data[4] = ($this->move['move_hide_start'] ? 0  : $this->start['user_id']);
            $log_data[5] = $this->move['dest'];
            $log_data[6] = $this->dest['planet_name'];
            $log_data[7] = $this->dest['user_id'];
            $log_data[8] = $surv_user['system_id'];
            $log_data[9] = $surv_user['system_name'];
            $log_data[10] = array_sum($this->n_ships);
            $log_data[11] = $ss_ship_list;
            $log_data[12] = $this->move['user_name'];

            switch($surv_user['language'])
            {
                case 'GER':
                    $log_title = '&Uuml;berwachungssystem kommuniziert Ankunft von Schiffen auf dem Planeten '.$this->dest['planet_name'];
                    break;
                case 'ITA':
                    $log_title = 'Sorveglianza comunica arrivo di navi sul pianeta '.$this->dest['planet_name'];
                    break;
                default:
                    $log_title = 'Surveillance system reporting the arrival of ships on planet '.$this->dest['planet_name'];
                    break;
            }

            if($surv_user['sensor_level'] > 0) {
                add_logbook_entry($surv_user['planet_owner'], LOGBOOK_TACTICAL_2, $log_title, $log_data);
            }
        }

        // #############################################################################
        // Look for stationed fleets in AR

        $sql = 'SELECT DISTINCT f.user_id, f.owner_id,
                        ud.ud_id, ud.accepted
                FROM (ship_fleets f)
                INNER JOIN user u ON u.user_id = f.user_id
                LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
                WHERE f.planet_id = '.$this->move['dest'].' AND
                        (f.user_id <> '.$this->move['user_id'].' OR f.user_id = '.UNDISCLOSED_USERID.') AND
                        f.alert_phase = '.ALERT_PHASE_RED;

//$this->log(MV_M_NOTICE,'AR-Fleet:<br>"'.$sql.'"<br>');

        if(!$q_ar_uid = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not query alert phase red user! SKIP');
        }

        $ar_user = array();
        $ar_real_user = array();

        while($ar_uid = $this->db->fetchrow($q_ar_uid)) {
            if(!empty($ar_uid['ud_id'])) {
                if($ar_uid['accepted'] == 1) continue;
            }

            $ar_user[] = $ar_uid['user_id'];

            $ar_real_user[] = $ar_uid['owner_id'];

            $this->log(MV_M_NOTICE,'AR-User ID is '.$ar_uid['user_id'].' Owner ID is '.$ar_real_user['owner_id']);
        }

        $this->db->free_result($q_ar_uid);
        
        $this->log(MV_M_NOTICE,'AR-user(s): <b>'.count($ar_user).'</b>');

        for($i = 0; $i < count($ar_user); ++$i) {
            $this->log(MV_M_NOTICE, 'Entering AR-loop #'.$i);

            $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                    FROM (ship_fleets f)
                    LEFT JOIN officers o ON o.fleet_id = f.fleet_id
                    INNER JOIN user u ON u.user_id = f.user_id
                    WHERE f.planet_id = '.$this->move['dest'].' AND
                            f.user_id = '.$ar_user[$i].' AND
                            f.alert_phase = '.ALERT_PHASE_RED;

            $this->log(MV_M_NOTICE,'AR-query:<br>"'.$sql.'"<br>');

            if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query attacker fleets in AR! SKIP');
            }

            $atk_fleet_ids = array();

            foreach($atk_fleets as $ihh => $cur_fleet) {
                $atk_fleet_ids[] = $cur_fleet['fleet_id'];
            }

            $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                    FROM (ship_fleets f)
                    LEFT JOIN officers o ON o.fleet_id = f.fleet_id
                    INNER JOIN user u ON u.user_id = f.user_id
                    WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

            if(($dfd_fleets = $this->db->queryrowset($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query defender fleets in AR! SKIP');
            }

            $this->log(MV_M_NOTICE, 'Doing combat in AR-loop #'.$i);

            $atk_fleet_ids_str = implode(',', $atk_fleet_ids);
            if($this->do_ship_combat($atk_fleet_ids_str, $this->fleet_ids_str, MV_COMBAT_LEVEL_OUTER) == MV_EXEC_ERROR) {
                $this->log(MV_M_CRITICAL, 'Move Direct: Something went wrong with this fight!');
                return MV_EXEC_ERROR;
            }

            $this->log(MV_M_NOTICE, 'Combat done in AR-loop #'.$i);

            // If the attacker has won (the fleet AR)
            // the move can then be terminated immediately
            // Now ships can run from combat. We have to check if any ships fled the fight
            // before calling the move a "skipped action"

            if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {

                $this->flags['skip_action'] = true;

                $sql = 'SELECT COUNT(*) as ship_escaped FROM ships WHERE fleet_id IN ('.$this->fleet_ids_str.')';
            //  $this->log(MV_M_NOTICE, 'Active fleet lost the fight, we look for survivors with '.$sql);
                $e_s_c = $this->db->queryrow($sql);

                if(isset($e_s_c['ship_escaped']) && $e_s_c['ship_escaped'] > 0) {
            //      $this->log(MV_M_NOTICE, 'Active fleet survived the fight, will bounce back');
                    // Update move data, setting up the bouncing back
                    // start -> dest, dest -> start
                    $sql = 'UPDATE scheduler_shipmovement SET start = '.$this->move['dest'].', dest = '.$this->move['start'].',
                                    move_begin = '.$this->CURRENT_TICK.', move_finish = '.($this->CURRENT_TICK + ($this->move['total_distance'] > 0 ? (int)($this->move['total_distance'] / $this->move['tick_speed']) : $INTER_SYSTEM_TIME)).',
                                    action_code = 28, move_rerouted = 1, action_data = 0, n_ships = '.$e_s_c['ship_escaped'].' WHERE move_id = '.$this->move['move_id'];
                    if(!$this->db->query($sql)) $this->log(MV_M_DATABASE, 'Could not update move data with retreat order! '.$sql);
                    $this->flags['keep_move_alive'] = true;

                    // Create the WARP_OUT trail if not intra-system
                    if(!$this->flags['is_inter_system_move'] && $this->move['start'] != 0) {
                        $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick, info_1, info_2, info_3)
                        VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 2, '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                '.$this->move['race_trail'].', '.WARP_OUT.', '.$this->move['start'].')
                        ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                                timestamp = '.time().', 
                                                info_1 = '.$this->move['race_trail'].', 
                                                info_2 = '.WARP_OUT.', 
                                                info_3 = '.$this->move['start'];

                        if(!$this->db->query($sql)) {
                            $this->log(MV_M_DATABASE, 'Could not update system activity data.');
                        }
                    }
                }
            }

            $log1_data = array(40,
                                $this->move['user_id'],
                                ($this->move['move_hide_start'] ? 0 : $this->move['start']),
                                ($this->move['move_hide_start'] ? '' : $this->start['planet_name']),
                                ($this->move['move_hide_start'] ? 0 : $this->start['user_id']),
                                $this->move['dest'],
                                $this->dest['planet_name'],
                                $this->dest['user_id'],
                                MV_CMB_ATTACKER,
                                ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER),
                                0,
                                0,
                                $atk_fleets,
                                $dfd_fleets,
                                null,
                                $ar_user[$i]);
            $log2_data = array(40,
                                $this->move['user_id'],
                                $this->move['start'],
                                $this->start['planet_name'],
                                $this->start['user_id'],
                                $this->move['dest'],
                                $this->dest['planet_name'],
                                $this->dest['user_id'],
                                MV_CMB_DEFENDER,
                                ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER),
                                0,
                                0,
                                $atk_fleets,
                                $dfd_fleets,
                                null,
                                $ar_user[$i]);

            $log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
            $log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];

            // #############################################################################
            // 03/04/08 - AC: Retrieve player language
            //  !! ACTUALLY WE ARE USING THE SAME LANGUAGE ALSO FOR ALLIES LOGBOOK ENTRY !!
            switch($this->move['language'])
            {
                case 'GER':
                    $log_title1 = 'AR-Flottenverband hat Schiffe bei '.$this->dest['planet_name'].' angegriffen';
                    $log_title2 = 'Flottenverband wurde bei '.$this->dest['planet_name'].' angegriffen';
                break;
                case 'ITA':
                    $log_title1 = 'Flotta in AR ha attaccato navi presso '.$this->dest['planet_name'];
                    $log_title2 = 'Associazione flotta attaccata presso '.$this->dest['planet_name'];
                break;
                default:
                    $log_title1 = 'AR fleet has attacked ships at '.$this->dest['planet_name'];
                    $log_title2 = 'Fleet association was attacked at '.$this->dest['planet_name'];
                break;
            }

            //add_logbook_entry($ar_user[$i], LOGBOOK_TACTICAL_2, $log_title1, $log1_data);
            add_logbook_entry($ar_real_user[$i], LOGBOOK_TACTICAL_2, $log_title1, $log1_data);
            add_logbook_entry($this->move['alt_id'], LOGBOOK_TACTICAL_2, $log_title2, $log2_data);

            $this->flags['combat_happened'] = true;

            $this->log(MV_M_NOTICE, 'Leaving AR-loop #'.$i);
        }

        // #############################################################################
        // If the fleet landed on a unexplored private system, let's bounce
        //
        if(HOME_SYSTEM_PRIVATE == 1 && 
           $this->flags['in_private_system'] > 0 && 
           $this->dest['system_owner'] != $this->move['alt_id'] && 
           !$this->flags['has_claim_over_system']) {
            switch($this->move['action_code']) {
                case 28:
                    // We are ALREADY retreating, going to home system (and hope there is one...)
                    // Mother system of the player
                    $sql = 'SELECT system_id FROM planets WHERE planet_id = '.$this->move['user_capital'];
                    if(!($mosystem = $this->db->queryrow($sql))) {
                        return $this->log(MV_M_DATABASE, 'Could not read capital planet data! SKIP');
                    }

                    $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_id = '.$mosystem['system_id'];
                    if(!($mocoord = $this->db->queryrow($sql))) {
                        return $this->log(MV_M_DATABASE, 'Could not read capital starsystem data! SKIP');
                    }

                    $distance = $this->get_distance(array($mocoord['system_global_x'], $mocoord['system_global_y']),
                                                    array($this->dest['system_global_x'], $this->dest['system_global_y']));
                    $distance = round($distance, 2);
                    $sql = 'UPDATE scheduler_shipmovement SET start = '.$this->move['dest'].', dest = '.$this->move['user_capital'].',
                            move_begin = '.$this->CURRENT_TICK.', move_finish = '.($this->CURRENT_TICK + ($this->move['total_distance'] > 0 ? (int)($this->move['total_distance'] / $this->move['tick_speed']) : $INTER_SYSTEM_TIME)).',
                            action_code = 28, move_rerouted = 1, action_data = 0 WHERE move_id = '.$this->move['move_id'];
                break;
                default:
                    $sql = 'UPDATE scheduler_shipmovement SET start = '.$this->move['dest'].', dest = '.$this->move['start'].',
                            move_begin = '.$this->CURRENT_TICK.', move_finish = '.($this->CURRENT_TICK + ($this->move['total_distance'] > 0 ? (int)($this->move['total_distance'] / $this->move['tick_speed']) : $INTER_SYSTEM_TIME)).',
                            action_code = 28, move_rerouted = 1, action_data = 0 WHERE move_id = '.$this->move['move_id'];
                break;
            }
            if(!$this->db->query($sql)) $this->log(MV_M_DATABASE, 'Could not update move data with retreat order! '.$sql);

            // Create the WARP_OUT trail if not intra-system
            if(!$this->flags['is_inter_system_move'] && $this->move['start'] != 0) {
                $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick, info_1, info_2, info_3)
                VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 2, '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                        '.$this->move['race_trail'].', '.WARP_OUT.', '.$this->move['start'].')
                ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                        timestamp = '.time().', 
                                        info_1 = '.$this->move['race_trail'].', 
                                        info_2 = '.WARP_OUT.', 
                                        info_3 = '.$this->move['start'];

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update system activity data.');
                }
            }            

            // DC ---- FoW 2.0 Beta -- User Section
            $sql = 'SELECT user_id FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND log_code = 0 AND user_id = '.$this->move['user_id'];
            $_res = $this->db->queryrow($sql);
            if(!isset($_res['user_id']))
            {
                $sql = 'INSERT INTO starsystems_details (system_id, user_id, log_code, timestamp)
                        VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', 0, '.time().')';
                $this->db->query($sql); // No error check plz.
            }            

            $log_data[0] = 103; // Codice mossa fasullo per far funzionare il logbook
            $log_data[1] = $this->move['alt_id'];
            $log_data[2] = $this->move['start'];
            $log_data[3] = $this->start['planet_name'];
            $log_data[4] = $this->start['user_id'];
            $log_data[5] = $this->move['dest'];
            $log_data[6] = $this->dest['planet_name'];
            $log_data[7] = $this->dest['user_id'];

            switch($this->move['language'])
            {
                case 'GER':
                    $log_title = 'Neues privates System bei '.$this->dest['system_name'].' gefunden!';
                break;
                case 'ITA':
                    $log_title = 'Nuovo sistema privato presso '.$this->dest['system_name'].' individuato!';
                break;
                default:
                    $log_title = 'A new private sytem at '.$this->dest['system_name']. ' has been discovered!';
                break;
            }            

            add_logbook_entry($this->move['alt_id'], LOGBOOK_TACTICAL_2, $log_title, $log_data);
                        
            $this->flags['skip_action'] = true;
            $this->flags['keep_move_alive'] = true;
        }

        // #############################################################################
        // If the moving fleet survive, she can attack any non-local fleets if AR is on
        //

        if(!$this->flags['skip_action'] && $this->ar_n_fleets > 0) {
            // Auto Attack loop
            $sql = 'SELECT DISTINCT f.user_id, f.owner_id,
                            u.user_name,
                            ud.ud_id, ud.accepted, u.language
                    FROM (ship_fleets f)
                    INNER JOIN user u ON u.user_id = f.user_id
                    LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
                    WHERE f.planet_id = '.$this->move['dest'].' AND
                            '.($this->dest['planet_owner'] != 0 ? 'f.user_id <> '.$this->dest['planet_owner'].' AND' : '').'
                            (f.user_id <> '.$this->move['user_id'].' OR f.user_id = '.UNDISCLOSED_USERID.') AND
                            f.alert_phase <> '.ALERT_PHASE_RED;
            $ar_move_query = $this->db->query($sql);

            $ar_move_n_user = $this->db->num_rows($ar_move_query);

            if($ar_move_n_user > 0) {

                $ar_move_user = array();
                $ar_move_real_user = array();

                $ar_move_rows = $this->db->fetchrowset($ar_move_query);

                foreach($ar_move_rows as $ar_move_uid) {
                    if(isset($ar_move_uid['ud_id']) && !empty($ar_move_uid['ud_id'])) {
                        if($ar_move_uid['accepted'] == 1) continue;
                    }

                    $ar_move_user[] = array($ar_move_uid['user_id'], $ar_move_uid['language']);
                    $ar_move_real_user[] = $ar_move_uid['owner_id'];

                    $this->log(MV_M_NOTICE,'AR_MOVE-User ID is '.$ar_move_uid['user_id'].' AR_MOVE-Owner ID is '.$ar_move_uid['owner_id']);
                }

                $this->log(MV_M_NOTICE,'AR_MOVE-user(s): <b>'.count($ar_move_user).'</b>');

                for($i = 0; $i < count($ar_move_user); ++$i) {
                    $this->log(MV_M_NOTICE, 'Entering AR_MOVE-loop #'.$i);

                    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                            FROM (ship_fleets f)
                            LEFT JOIN officers o ON o.fleet_id = f.fleet_id
                            INNER JOIN user u ON u.user_id = f.user_id
                            WHERE f.planet_id = '.$this->move['dest'].' AND
                                    f.user_id = '.$ar_move_user[$i][0].' AND
                                    f.alert_phase <> '.ALERT_PHASE_RED;

                    $this->log(MV_M_NOTICE,'AR_MOVE-query:<br>"'.$sql.'"<br>');

                    if(($def_m_fleets = $this->db->queryrowset($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not query parked orbital fleet!!! SKIP');
                    }

                    $def_fleet_ids = array();

                    foreach($def_m_fleets as $ihh => $cur_fleet) {
                        $def_fleet_ids[] = $cur_fleet['fleet_id'];
                    }

                    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                            FROM (ship_fleets f)
                            LEFT JOIN officers o ON o.fleet_id = f.fleet_id
                            INNER JOIN user u ON u.user_id = f.user_id
                            WHERE f.fleet_id IN ('.$this->ar_fleet_ids_str.')';

                    if(($atk_m_fleets = $this->db->queryrowset($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not query moving AR fleet !!! SKIP');
                    }

                    $this->log(MV_M_NOTICE, 'Doing combat in AR_MOVE-loop #'.$i);

                    $def_fleet_ids_str = implode(',', $def_fleet_ids);
                    if($this->do_ship_combat($this->ar_fleet_ids_str, $def_fleet_ids_str,  MV_COMBAT_LEVEL_OUTER) == MV_EXEC_ERROR) {
                        $this->log(MV_M_CRITICAL, 'Move Direct: Something went wrong with this fight!');
                        return MV_EXEC_ERROR;
                    }

                    $this->log(MV_M_NOTICE, 'Combat done in AR_MOVE-loop #'.$i);

                    // If the defender has won (the fleet attacked by AR)
                    // the move can then be terminated immediately
                    // Now ships can run from combat. We have to check if any ships fled the fight
                    // before calling the move a "skipped action"

                    if($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) {
                        $this->flags['skip_action'] = true;

                        $sql = 'SELECT COUNT(*) as ship_escaped FROM ships WHERE fleet_id IN ('.$this->ar_fleet_ids_str.')';
                        //  $this->log(MV_M_NOTICE, 'Active fleet lost the fight, we look for survivors with '.$sql);
                        $e_s_c = $this->db->queryrow($sql);

                        if(isset($e_s_c['ship_escaped']) && $e_s_c['ship_escaped'] > 0) {
                    //      $this->log(MV_M_NOTICE, 'Active fleet survived the fight, will bounce back');
                            // Update move data, setting up the bouncing back
                            // start -> dest, dest -> start
                            $sql = 'UPDATE scheduler_shipmovement SET start = '.$this->move['dest'].', dest = '.$this->move['start'].',
                                            move_begin = '.$this->CURRENT_TICK.', move_finish = '.($this->CURRENT_TICK + ($this->move['total_distance'] > 0 ? (int)($this->move['total_distance'] / $this->move['tick_speed']) : $INTER_SYSTEM_TIME)).',
                                            action_code = 28, move_rerouted = 1, action_data = 0, n_ships = '.$e_s_c['ship_escaped'].' WHERE move_id = '.$this->move['move_id'];
                            if(!$this->db->query($sql)) $this->log(MV_M_DATABASE, 'Could not update move data with retreat order! '.$sql);
                            $this->flags['keep_move_alive'] = true;
                        }

                        // Create the WARP_OUT trail if not intra-system
                        if(!$this->flags['is_inter_system_move']  && $this->move['start'] != 0) {
                            $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick, info_1, info_2, info_3)
                            VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 2, '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                    '.$this->move['race_trail'].', '.WARP_OUT.', '.$this->move['start'].')
                            ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                                    timestamp = '.time().', 
                                                    info_1 = '.$this->move['race_trail'].', 
                                                    info_2 = '.WARP_OUT.', 
                                                    info_3 = '.$this->move['start'];

                            if(!$this->db->query($sql)) {
                                $this->log(MV_M_DATABASE, 'Could not update system activity data.');
                            }
                        }                        
                    }

                    $log1_data = [40,
                                    $ar_move_user[$i][0],
                                    $this->move['start'],
                                    $this->start['planet_name'],
                                    $this->start['user_id'],
                                    $this->move['dest'],
                                    $this->dest['planet_name'],
                                    $this->dest['user_id'],
                                    MV_CMB_ATTACKER,
                                    ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER),
                                    0,
                                    0,
                                    $atk_m_fleets,
                                    $def_m_fleets,
                                    null,
                                    $ar_move_user[$i][0]];
                    $log2_data = [40,
                                    $ar_move_user[$i][0],
                                    ($this->move['move_hide_start'] ? 0 : $this->move['start']),
                                    ($this->move['move_hide_start'] ? '' : $this->start['planet_name']),
                                    ($this->move['move_hide_start'] ? 0 : $this->start['user_id']),
                                    $this->move['dest'],
                                    $this->dest['planet_name'],
                                    $this->dest['user_id'],
                                    MV_CMB_DEFENDER,
                                    ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER),
                                    0,
                                    0,
                                    $atk_m_fleets,
                                    $def_m_fleets,
                                    null,
                                    $this->move['user_id']];

                    $log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
                    $log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];

                    // #############################################################################
                    // 03/04/08 - AC: Retrieve player language
                    //  !! ACTUALLY WE ARE USING THE SAME LANGUAGE ALSO FOR ALLIES LOGBOOK ENTRY !!
                    switch($this->move['language'])
                    {
                        case 'GER':
                            $log_title1 = 'AR-Flottenverband hat Schiffe bei '.$this->dest['planet_name'].' angegriffen';
                            $log_title2 = 'Flottenverband wurde bei '.$this->dest['planet_name'].' angegriffen';
                        break;
                        case 'ITA':
                            $log_title1 = 'Flotta in AR ha attaccato navi presso '.$this->dest['planet_name'];
                            $log_title2 = 'Forze navali attaccate presso '.$this->dest['planet_name'];
                        break;
                        default:
                            $log_title1 = 'AR fleet has attacked ships at '.$this->dest['planet_name'];
                            $log_title2 = 'Fleet association was attacked at '.$this->dest['planet_name'];
                        break;
                    }

                    add_logbook_entry($this->move['alt_id'], LOGBOOK_TACTICAL_2, $log_title1, $log1_data);
                    add_logbook_entry($ar_move_real_user[$i], LOGBOOK_TACTICAL_2, $log_title2, $log2_data);

                    $this->flags['combat_happened'] = true;

                    $this->log(MV_M_NOTICE, 'Leaving AR_MOVE-loop #'.$i);
                }
            }
        }

        // #############################################################################
        // Funzione avvistamento degli ufficiali
        // Non è la migliore delle scelte perché aggiungiamo un secondo loop

        if(!$this->flags['skip_action']) {
            $sql = 'SELECT DISTINCT f.user_id,
                            u.user_alliance, u.user_name,
                            ud.ud_id, ud.accepted, u.language
                    FROM (ship_fleets f)
                    LEFT JOIN (officers o) ON o.fleet_id = f.fleet_id
                    INNER JOIN (user u) ON u.user_id = f.user_id
                    LEFT JOIN (user_diplomacy ud) ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
                    WHERE f.system_id = '.$this->dest['system_id'].' AND
                            f.user_id <> '.$this->move['user_id'].' AND
                            o.officer_lookout_report = 1';

            $ay_query = $this->db->query($sql);

            $ay_n_user = $this->db->num_rows($ay_query);

            if($ay_n_user > 0) {

                $ay_user = array();

                $ay_rows = $this->db->fetchrowset($ay_query);

                // Raccogliamo info sulla flotta IN ARRIVO (ossia quella legata alla mossa in elaborazione)
                // per averle già pronte per la scrittura dei log da mandare ai giocatori.
                // Utilizzo queryrowset perché, salvo cataclismi, la flotta in movimento HA una composizione coerente e leggibile.

                $sql = 'SELECT st.name, st.race, st.ship_torso, st.ship_class, COUNT(st.name) as n_ships
                        FROM ship_templates st
                        INNER JOIN ships s ON s.template_id = st.id
                        INNER JOIN ship_fleets f ON f.fleet_id = s.fleet_id
                        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')
                        GROUP BY st.name, st.race, st.ship_torso, st.ship_class
                        ORDER BY st.ship_class DESC, st.ship_torso DESC';

                $ay_ship_list = $this->db->queryrowset($sql);

                foreach ($ay_rows as $ay_uid) {
                    if(isset($ay_uid['ud_id']) && !empty($ay_uid['ud_id'])) {
                        if($ay_uid['accepted'] == 1) continue;
                    }

                    $ay_user[] = array($ay_uid['user_id'], $ay_uid['language']);

                    $this->log(MV_M_NOTICE,'AY-User ID is '.$ay_uid['user_id']);
                }

                $this->log(MV_M_NOTICE,'AY-user(s): <b>'.count($ay_user).'</b>');

                for($i = 0; $i < count($ay_user); ++$i) {
                    $this->log(MV_M_NOTICE, 'Entering AY-loop #'.$i);

                    $sql = 'SELECT f.fleet_id, f.fleet_name
                            FROM (ship_fleets f)
                            LEFT JOIN (officers o) ON o.fleet_id = f.fleet_id
                            INNER JOIN (user u) ON u.user_id = f.user_id
                                WHERE f.system_id = '.$this->dest['system_id'].' AND
                                f.user_id = '.$ay_user[$i][0].' AND
                                o.officer_lookout_report = 1 
                                GROUP BY f.owner_id, f.fleet_id';

                    $this->log(MV_M_NOTICE,'AY-query:<br>"'.$sql.'"<br>');

                    if(($sgnl_fleets = $this->db->queryrowset($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not query signaling fleets in AY! SKIP');
                    }

                    foreach($sgnl_fleets as $ihh => $cur_fleet) {
                        $log_data[0] = 100; // Codice mossa fasullo per far funzionare il logbook
                        $log_data[1] = $this->move['user_id'];
                        $log_data[2] = ($this->move['move_hide_start'] ? 0 : $this->move['start']);
                        $log_data[3] = ($this->move['move_hide_start'] ? '' : $this->start['planet_name']);
                        $log_data[4] = ($this->move['move_hide_start'] ? 0 : $this->start['user_id']);
                        $log_data[5] = $this->move['dest'];
                        $log_data[6] = $this->dest['planet_name'];
                        $log_data[7] = $this->dest['user_id'];
                        $log_data[8] = $cur_fleet['fleet_id'];
                        $log_data[9] = $cur_fleet['fleet_name'];
                        $log_data[10] = array_sum($this->n_ships);
                        $log_data[11] = $ay_ship_list;
                        $log_data[12] = $this->move['user_name'];

                        switch($ay_user[$i][1])
                        {
                            case 'GER':
                                $log_title = 'Flotte meldet Ankunft von Schiffen auf dem Planeten '.$this->dest['planet_name'];
                                break;
                            case 'ITA':
                                $log_title = 'Flotta comunica arrivo di navi sul pianeta '.$this->dest['planet_name'];
                                break;
                            default:
                                $log_title = 'Fleet reports arrival of ships on the planet '.$this->dest['planet_name'];
                                break;
                        }

                        add_logbook_entry($ay_user[$i][0], LOGBOOK_TACTICAL_2, $log_title, $log_data);
                    }
                }
            }
        }

        // #############################################################################
        // Come through without mistakes?

        $this->flags['free_dest_planet'] = (empty($this->dest['user_id'])) ? true : false;

        if($this->flags['skip_action']) {
            // Nel caso di mossa intersistema aggiorniamo i dati sulla scia anche se la mossa non si è conclusa per colpa di un combattimento in AR
            if($this->flags['is_inter_system_move']) {
                $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick, info_1)
                        VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 2, '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', '.$this->move['race_trail'].')
                        ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]);

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update system activity data.');
                }                
            }
            $this->log(MV_M_NOTICE, 'Skipped_action_main()');
        }
        else {
            if($this->_action_main() != MV_EXEC_OK)
            {
                return $this->log(MV_M_ERROR, 'Could not exec successfully _action_main()! SKIP');
            }
            else
            {
                // DC ---- Here we go, first steps into "Fog of War"
                // DC ---- FoW 2.0 Beta - Ally Section
                /*
                if(!empty($this->move['user_alliance']))
                {
                    $sql = 'SELECT alliance_id FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND alliance_id = '.$this->move['user_alliance'];
                    $_res = $this->db->queryrow($sql);
                    if(!isset($_res['alliance_id']))
                    {
                        $sql = 'INSERT INTO starsystems_details (system_id, alliance_id, timestamp)
                                VALUES ('.$this->dest['system_id'].', '.$this->move['user_alliance'].', '.time().')';
                        $this->db->query($sql); // No error check plz.
                    }
                }
                 *
                 */
                // DC ---- Orion Syndicate 0.9c
                if($this->move['user_id'] > 11 && $this->dest['system_orion_alert'] > 0)
                {
                    $res_spawn = $this->db->queryrow('SELECT COUNT(*) AS n_count FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND user_id = '.$this->move['user_id'].' AND log_code = 1 ORDER BY timestamp DESC LIMIT 0,1');
                    if($res_spawn['n_count'] == 0) {
                        $this->spawn_pirate($this->dest['system_id'],$this->dest['system_orion_alert']);
                    }
                    
                    if(!$this->flags['is_civilian']) {
                        $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick)
                                VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 1, '.($this->CURRENT_TICK+1+(20*24*(5-$this->dest['system_orion_alert']))).')
                                ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+(20*24*(5-$this->dest['system_orion_alert'])));
                        $this->db->query($sql); // No error check plz.
                    }

                    $sql = 'UPDATE ship_fleets SET npc_last_action = 0 WHERE user_id = '.ORION_USERID.' AND system_id = '.$this->dest['system_id'];
                    $this->db->query($sql); // No error check plz.                    
                }
                elseif($this->move['user_id'] == ORION_USERID && $this->dest['system_orion_alert'] > 0){
                    $sql = 'DELETE FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND log_code = 1';
                    if(!$this->db->query($sql)) {
                        $this->sdl->log('Could not update system patrol data.',TICK_LOG_FILE_NPC);
                    }
                }

                // DC ---- FoW 2.0 Beta -- User Section
                if($this->move['user_id'] > 11) {
                    $sql = 'SELECT user_id FROM starsystems_details WHERE system_id = '.$this->dest['system_id'].' AND log_code = 0 AND user_id = '.$this->move['user_id'];
                    $_res = $this->db->queryrow($sql);
                    if(!isset($_res['user_id']))
                    {
                        $sql = 'INSERT INTO starsystems_details (system_id, user_id, log_code, timestamp)
                                VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', 0, '.time().')';
                        $this->db->query($sql); // No error check plz.
                    }
                }

                if($this->flags['is_inter_system_move']) {
                    $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick, info_1)
                    VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 2, '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', '.$this->move['race_trail'].')
                    ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]);

                    if(!$this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not update system activity data.');
                    }                    
                }
                else {
                    $sql = 'INSERT INTO starsystems_details (system_id, user_id, timestamp, log_code, log_code_tick, info_1, info_2, info_3)
                            VALUES ('.$this->dest['system_id'].', '.$this->move['user_id'].', '.time().', 2, '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                    '.$this->move['race_trail'].', '.WARP_IN.', '.($this->move['move_hide_start'] ? "NULL" : $this->move['start']).')
                            ON DUPLICATE KEY UPDATE log_code_tick = '.($this->CURRENT_TICK+1+$this->star_trail[$this->dest['system_startype']]).', 
                                                    timestamp = '.time().', 
                                                    info_1 = '.$this->move['race_trail'].', 
                                                    info_2 = '.WARP_IN.', 
                                                    info_3 = '.($this->move['move_hide_start'] ? "NULL" : $this->move['start']);

                    if(!$this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not update system activity data.');
                    }
                }

                // DC Now, if the planet is unsettled...
                if($this->move['user_id'] > 11 && empty($this->dest['user_id']))
                {
                    /*
                    // DC Maybe we did boldly go where nobody has boldly gone before?
                    $sql = 'SELECT COUNT(*) AS been_here FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND user_id = '.$this->move['user_id'].' AND log_code IN (1,2)';
                    if(!$_flag = $this->db->queryrow($sql))
                        $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');
                    if($_flag['been_here'] == 0) {
                        // DC Yeah, we made it!
                        $sql = 'SELECT COUNT(*) AS first_here FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND log_code = 1';
                        if(!$_flag = $this->db->queryrow($sql))
                            $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');
                        if($_flag['first_here'] == 0)
                        {
                            $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                    VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 1)';
                            if(!$this->db->query($sql))
                                $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');
                        }
                        else
                        {
                            // DC Bad luck, we are seconds...
                            // DC AND NOW we need to check if we already placed our flag here...
                            $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                    VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 2)';
                            if(!$this->db->query($sql))
                                $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');
                        }
                    }
                     * 
                     */
                    $sql = 'SELECT COUNT(*) AS first_here FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND user_id = '.$this->move['user_id'].' AND log_code = 1';
                    if(!$_flag = $this->db->queryrow($sql))
                        $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');
                    if($_flag['first_here'] == 0)
                    {
                        $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 1)';
                        if(!$this->db->query($sql))
                            $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');
                    }                    
                }
                              
                if($this->flags['is_simple_move']) {
                    $bell_list = $this->db->queryrowset('SELECT fleet_id, fleet_name FROM ship_fleets WHERE trip_bell = 1 AND fleet_id IN ('.$this->fleet_ids_str.')');
                    foreach ($bell_list as $bell_item) {
                        $log_data[0] = 102; // Codice mossa fasullo per far funzionare il logbook
                        $log_data[1] = $this->move['user_id'];
                        $log_data[2] = $this->move['start'];
                        $log_data[3] = $this->start['planet_name'];
                        $log_data[4] = $this->start['user_id'];
                        $log_data[5] = $this->move['dest'];
                        $log_data[6] = $this->dest['planet_name'];
                        $log_data[7] = $this->dest['user_id'];
                        $log_data[8] = $bell_item['fleet_name'];
                        $log_data[9] = $bell_item['fleet_id'];

                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_title = 'Fleet '.$bell_item['fleet_name'].' just reached '.$this->dest['system_name'];
                                break;
                            case 'ITA':
                                $log_title = 'Flotta '.$bell_item['fleet_name'].' giunta presso '.$this->dest['system_name'];
                                break;
                            default:
                                $log_title = 'Fleet '.$bell_item['fleet_name'].' just reached '.$this->dest['system_name'];
                                break;
                        }

                        add_logbook_entry(($this->move['user_id'] == $this->move['alt_id'] ? $this->move['user_id'] : $this->move['alt_id']), LOGBOOK_TACTICAL_2, $log_title, $log_data);
                    }
                }
            }
        }

        // #############################################################################
        // Clean-Up

        $total_processing_time = round( (time() + microtime()) - $start_processing_time, 4);

        if($this->flags['keep_move_alive']) {
            $sql = 'UPDATE scheduler_shipmovement
                    SET move_exec_started = 0
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update move exec started data to keep move alive! CONTINUE');
            }

            $this->log(MV_M_NOTICE, 'Action '.$this->move['action_code'].' executed in '.$total_processing_time.'s, but kept alive');
        }
        else {
            $sql = 'UPDATE scheduler_shipmovement
                    SET move_status = '.( ($this->flags['skip_action']) ? '12' : '11' ).'
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update move status data! CONTINUE');
            }

            $this->log(MV_M_NOTICE, 'Action '.$this->move['action_code'].' executed in '.$total_processing_time.'s');
        }

        return MV_EXEC_OK;
    }

    function _action_main() {
        $this->log(MV_M_ERROR, 'Action class defined no _action_main()-method! DEACTIVATE AND REPORT');

        $this->deactivate(36);
        $this->report('Action class defined no _action_main()-method');

        return MV_EXEC_ERROR;
    }
}

?>
