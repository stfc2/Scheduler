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
        'is_orbital_move' => true,
        'is_friendly_move' => false,

        'free_dest_planet' => false,

        'skip_action' => false,
        'keep_move_alive' => false,

        'combat_happened' => false,
        
        'recall_action' => false,
    );

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
        global $CURRENT_TICK;

        $message = addslashes($reason.NL.NL.
                              'move_id: <b>'.$this->mid.'</b>'.NL.
                              'user_id: <b>'.$this->move['user_id'].'</b>'.NL.
                              'action_code: <b>'.$this->move['action_code'].'</b>'.NL.
                              'start: <b>'.$this->move['start'].'</b>'.NL.
                              'dest: <b>'.$this->move['dest'].'</b>'.NL.
                              'move_begin: <b>'.$this->move['move_begin'].'</b>'.NL.
                              'move_finish: <b>'.$this->move['move_finish'].'</b>'.NL.
                              'processing tick: <b>'.$CURRENT_TICK.'</b>');

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
        return 'f.fleet_id, f.fleet_name, f.n_ships,
                u.user_name AS owner_name';
    }

    function get_combat_query_ship_columns() {
        return 's.ship_id, s.hitpoints, s.unit_1, s.unit_2, s.unit_3, s.unit_4, s.experience, s.user_id, s.fleet_id,
                st.value_1, st.value_2, st.value_3, st.value_4, st.value_5, st.value_6, st.value_7, st.value_8, st.value_10, st.value_11, st.value_12, st.name, st.ship_torso, st.ship_class, st.race';
    }

    function do_ship_combat(&$atk_fleet_ids_str, &$dfd_fleet_ids_str, $combat_level) {
        global $config;

        if(empty($atk_fleet_ids_str)) $atk_fleet_ids_str = '-1';
        if(empty($dfd_fleet_ids_str)) $dfd_fleet_ids_str = '-1';

        // When $combat_level == MV_COMBAT_LEVEL_PLANETARY the owner is always attacked
        //			--> fight with all orbitals

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

        $bin_output = array();

        $cmd_line = MV_COMBAT_BIN_PATH.' '.$atk_fleet_ids_str.' '.$dfd_fleet_ids_str.' '.$this->move['dest'].' '.$n_large_orbital_defense.' '.$n_small_orbital_defense;
        $auth_line = ' '.$config['game_database'].' '.$config['user'].' '.$config['password'];
        exec($cmd_line.$auth_line, $bin_output);

        if($bin_output[0][0] == '0') {
            return $this->log(MV_M_ERROR, 'Combat Binary exited with an error ('.substr($bin_output[0], 1).' - '.$cmd_line.')');
        }

        $this->cmb[MV_CMB_WINNER] = $bin_output[1];
        $this->cmb[MV_CMB_KILLS_PLANETARY] = (int)$bin_output[2];
        $this->cmb[MV_CMB_KILLS_SPLANETARY] = (int)$bin_output[3];
        $this->cmb[MV_CMB_KILLS_EXT] = &$bin_output[4];

        if($this->cmb[MV_CMB_KILLS_PLANETARY] != 0) {
            $this->dest['building_10'] -= $this->cmb[MV_CMB_KILLS_PLANETARY];
        }

        if($this->cmb[MV_CMB_KILLS_SPLANETARY] != 0) {
            $this->dest['building_13'] -= $this->cmb[MV_CMB_KILLS_SPLANETARY];
        }

        return MV_EXEC_OK;
    }

    function _main() {
        $start_processing_time = time() + microtime();

        // #############################################################################
        // On...

        $this->flags['is_orbital_move'] = ($this->move['start'] == $this->move['dest']);
        $this->flags['is_friendly_action'] = in_array($this->move['action_code'], array(23, 31, 33));

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

        $sql = 'SELECT fleet_id, fleet_name, n_ships
                FROM ship_fleets
                WHERE move_id = '.$this->mid;

        if(!$q_fleets = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not query moves fleets data! SKIP');
        }

        while($_fl = $this->db->fetchrow($q_fleets)) {
            $this->fleet_ids[] = $_fl['fleet_id'];
            $this->fleet_names[] = $_fl['fleet_name'];
            $this->n_ships[] = $_fl['n_ships'];
        }

        $this->n_fleets = count($this->fleet_ids);

        if($this->n_fleets == 0) {
            $this->deactivate(34);
            $this->report('Deactivated due to missing fleets');

            return MV_EXEC_ERROR;
        }

        $this->fleet_ids_str = implode(',', $this->fleet_ids);

        // #############################################################################
        // action_data decode, if available

        if(!empty($this->move['action_data'])) {
            $this->action_data = (array)unserialize($this->move['action_data']);
        }

        // #############################################################################
        // Data of start planet

        $sql = 'SELECT p.*,
                       u.user_id, u.user_active, u.user_name, u.user_race, u.user_planets, u.user_alliance, u.user_capital
                FROM (planets p)
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
                           u.user_id, u.user_active, u.user_name, u.user_race, u.user_planets, u.user_alliance, u.user_capital
                    FROM (planets p)
                    LEFT JOIN user u ON u.user_id = p.planet_owner
                    WHERE p.planet_id = '.$this->move['dest'];

            if(($this->dest = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query dest planets! SKIP');
            }

            settype($this->dest['user_id'], 'int');


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
            else
                $this->dest['language'] = 'ENG';


            // #############################################################################
            // Look for stationed fleets in AR

            $sql = 'SELECT DISTINCT f.user_id,
                           u.user_alliance,
                           ud.ud_id, ud.accepted,
                           ad.ad_id, ad.type, ad.status
                    FROM (ship_fleets f)
                    INNER JOIN user u ON u.user_id = f.user_id
                    LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
                    LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$this->move['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$this->move['user_alliance'].' ) )
                    WHERE f.planet_id = '.$this->move['dest'].' AND
                          f.user_id <> '.$this->move['user_id'].' AND
                          f.alert_phase = '.ALERT_PHASE_RED;

//$this->log(MV_M_NOTICE,'AR-Fleet:<br>"'.$sql.'"<br>');

            if(!$q_ar_uid = $this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not query alert phase red user! SKIP');
            }

            $ar_user = array();

            while($ar_uid = $this->db->fetchrow($q_ar_uid)) {
                //if( ($ar_uid['user_id'] == $this->dest['user_id']) && $is_friendly_action ) continue;

                if($ar_uid['user_alliance'] == $this->move['user_alliance']) continue;

                if(!empty($ar_uid['ud_id'])) {
                    if($ar_uid['accepted'] == 1) continue;
                }

                if(!empty($ar_uid['ad_id'])) {
                    if( ($ar_uid['type'] == ALLIANCE_DIPLOMACY_PACT) && ($ar_uid['status'] == 0) ) continue;
                }

                $ar_user[] = $ar_uid['user_id'];

                echo 'AR-User ID war '.$ar_uid['user_id'];
            }

            $this->db->free_result($q_ar_uid);
$this->log(MV_M_NOTICE,'AR-user(s): <b>'.count($ar_user).'</b>');

            // 160408 DC ---- Scouts does not trigger AR Fleets attack
            $scout_counter = 0;
            foreach($this->n_ships as $n_ships) {
                $scout_counter += $n_ships;
            }
            foreach($this->fleet_ids as $fleet_id) {
                $sql = 'SELECT count(st.ship_torso) AS num_scout FROM (ships s, ship_templates st)
                        WHERE s.fleet_id = '.$fleet_id.' AND st.id = s.template_id AND st.ship_torso = '.SHIP_TYPE_SCOUT;
                $check_scout = $this->db->queryrow($sql);
                $scout_counter -= $check_scout['num_scout'];
            }
            if ($scout_counter != 0) {
            //160408 DC -----
                for($i = 0; $i < count($ar_user); ++$i) {
                    $this->log(MV_M_NOTICE, 'Entering AR-loop #'.$i);

                    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
                            FROM (ship_fleets f)
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
                            INNER JOIN user u ON u.user_id = f.user_id
                            WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

                    if(($dfd_fleets = $this->db->queryrowset($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not query defender fleets in AR! SKIP');
                    }

                    $this->log(MV_M_NOTICE, 'Doing combat in AR-loop #'.$i);

                    if($this->do_ship_combat(implode(',', $atk_fleet_ids), $this->fleet_ids_str, MV_COMBAT_LEVEL_OUTER) == MV_EXEC_ERROR) {
                        $this->log(MV_M_CRITICAL, 'Move Direct: Something went wrong with this fight!');
                        return MV_EXEC_ERROR;
                    }

                    $this->log(MV_M_NOTICE, 'Combat done in AR-loop #'.$i);

                    // If the attacker has won (the fleet AR)
                    // the move can then be terminated immediately

                    if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
                        $this->flags['skip_action'] = true;
                    }

                    $log1_data = array(40, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_ATTACKER, ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER), 0,0, $atk_fleets, $dfd_fleets, null, $ar_user[$i]);
                    $log2_data = array(40, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_DEFENDER, ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER), 0,0, $atk_fleets, $dfd_fleets, null, $ar_user[$i]);

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

                    add_logbook_entry($ar_user[$i], LOGBOOK_TACTICAL_2, $log_title1, $log1_data);
                    add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $log_title2, $log2_data);

                    $this->flags['combat_happened'] = true;

                    $this->log(MV_M_NOTICE, 'Leaving AR-loop #'.$i);
                }
            }
            // 170408 DC ---- Report AR fleets evaded
            else
            {
                if (count($ar_user) > 0) {
                    $this->log(MV_M_NOTICE,'Scouts evaded AR-Fleet, attack avoided');
                    $log1_data = array($this->move['action_code'], $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id']);
                    switch($this->move['language'])
                    {
                        case 'ITA':
                            $log_title1 = 'Scout in arrivo presso '.$this->dest['planet_name'].' obbligati a tornare indietro da forze ostili in allarme rosso.';
                        break;
                        default:
                            $log_title1 = 'Scout aproaching at '.$this->dest['planet_name'].' forced to get back by hostile forces';
                        break;
                    }
                    add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $log_title1, $log1_data);

                    // DC --- Scouts evades AR Fleet and come back to home
                    $this->flags['skip_action'] = true;
                    
                    $this->flags['recall_action'] = true;

                   
                   // ----
                }
            }
            //170408 DC ----
        }

        // #############################################################################
        // Come through without mistakes?

        $this->flags['free_dest_planet'] = (empty($this->dest['user_id'])) ? true : false;

        if($this->flags['skip_action']) {
            $this->log(MV_M_NOTICE, 'Skipped_action_main()');
        }
        else {
            if($this->_action_main() != MV_EXEC_OK) {
                return $this->log(MV_M_ERROR, 'Could not exec successfully _action_main()! SKIP');
            }
            else {

                // DC ---- Here we go, first steps into "Fog of War"
                // DC  We never have been here before?
                // DC  500 = I was already here 
                $sql = 'SELECT COUNT(*) AS been_there FROM planet_details
                        WHERE planet_id = '.$this->dest['planet_id'].'
                        AND log_code = 500 AND user_id = '.$this->move['user_id'];
                if(($_flag = $this->db->queryrow($sql)) === false)
                    $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');

                if($_flag['been_there'] < 1) {
                    // DC ---- Ok, we never have been here before
                    // DC First, let's mark this planet as a know one
                    $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                            VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 500)';
                    if(!$this->db->query($sql))
                        $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');

                    // DC Now, if the planet is unsettled...
                    if(empty($this->dest['user_id'])) {
                        // DC Maybe we did boldly go where nobody has boldly gone before?
                        $sql = 'SELECT COUNT(*) AS first_here FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND log_code = 1';
                        if(!$_flag = $this->db->queryrow($sql))
                            $this->log(MV_M_DATABASE, 'Could not query planet details data! CONTINUE!');

                        if($_flag['first_here'] == 0) {
                            // DC Yeah, we made it!
                            $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                    VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 1)';
                        }
                        else {
                            // DC Bad luck, we are seconds...
                           $sql = 'INSERT INTO planet_details (planet_id, system_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                                   VALUES ('.$this->dest['planet_id'].', '.$this->dest['system_id'].', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.$this->move['user_id'].', '.( (!empty($this->move['user_alliance'])) ? $this->move['user_alliance'] : 0 ).', '.time().', 2)';
                        }
                        if(!$this->db->query($sql))
                            $this->log(MV_M_DATABASE, 'Could not insert new planet details data! CONTINUE!');
                    }
                }
                // DC ----
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
        elseif($this->flags['recall_action']) {
            $sql = 'UPDATE scheduler_shipmovement
                    SET start = '.$this->move['dest'].',
                        dest  = '.$this->move['start'].',
                        move_exec_started = 0,
                        remaining_distance = total_distance - remaining_distance,
                        move_begin = '.$this->CURRENT_TICK.',
                        move_finish = '.($this->CURRENT_TICK + ( ($this->CURRENT_TICK + 1) - $this->move['move_begin'] ) ).',
                        action_code = 13,
                        action_data = "'.addslashes(serialize(array('callback_15', (int)$this->move['action_code'], (int)$this->move['move_begin'], (int)$this->move['move_finish'], $this->move['action_data']))).'"
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update ship movement for scout return! SKIP');
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

