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

define('LC_FIRST_CONTACT', 1);
define('LC_DIPLO_SPEECH', 2);
define('LC_TECH_SUPPORT', 3);
define('LC_MIL_SUPPORT', 4);
define('LC_DEF_SUPPORT', 5);
define('LC_COLO_FOUNDER', 30);
define('LC_COLO_GIVER', 31);
define('STL_MAX_ORBITAL', 120);



class moves_action_27 extends moves_common {

    function _action_main() {

        global $PLANETS_DATA, $RACE_DATA, $TECH_DATA, $TECH_NAME, $MAX_RESEARCH_LVL, $ACTUAL_TICK, $cfg_data;

        // #############################################################################
        // Away Team Missions!!!
        //
        // Beam us down! Energy!
        //
        //#############################################################################

        $tech_reward = array(5,7,7,7,10,10,10,12,12);

        $sql = 'SELECT s.ship_name, s.experience, s.ship_id, t.name FROM ship_fleets f
                       LEFT JOIN ships s ON s.fleet_id = f.fleet_id
                       LEFT JOIN ship_templates t ON t.id = s.template_id
                WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

        if(($ship_details = $this->db->queryrow($sql)) == false) {
            $this->log(MV_M_DATABASE, 'Could not read ship name! CONTINUE');
            $name_of_ship = '<i>Sconosciuto</i>';
        }
        else {
            if(!empty($ship_details['ship_name'])) {
                $name_of_ship = '<b>'.$ship_details['ship_name'].'</b>';
            }
            else {
                $name_of_ship = '<b><i>&#171;'.$ship_details['name'].'&#187;</i></b>';
            }
        }

        // Check Action Code
        if(!isset($this->action_data[0])) {
            // first item tell us the kind of mission we are going to do
            $this->log(MV_M_DATABASE, 'action_27: Could not find required action_data entry [0]! FORCED TO ZERO');
            $this->action_data[0] = 0;
        }

        if($this->action_data[0] == 3 && !isset($this->action_data[1])) {
            // second item tell us more on the mission target
            // for tech mission:
            // - 0 = research_1 (higher rooms for ppl on planet, higher labourer rate)
            // - 1 = research_2 (higher labourer rate, will not be implemented)
            // - 2 = research_3 (better defense protection)
            // - 3 = research_4 (otimization of accademy, shipyards)
            // - 4 = research_5 (higher mines output)
            $this->log(MV_M_NOTICE, 'action_27: Could not find mission parameters [1]! FORCED TO -1');
            $this->action_data[1] = -1;
        }

        /**
         * Check planet's mood.
         */

        // The mood of the planet is calculated by adding racial modifiers, alliance
        // modifiers (if any) and player's one

        // For now, let's forget about racial and alliance modifiers.
        /*
        // Racial mood
        $sql = 'SELECT SUM(mood_modifier) AS mood_race
                FROM settlers_relations
                WHERE planet_id = '.$this->move['dest'].' AND
                      user_id != '.$this->move['user_id'].' AND
                      race_id = '.$this->move['user_race'];
        if(($mood_race = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
        }

        $mood['race'] = 0.20 * $mood_race['mood_race'];

        // Alliance mood
        if(isset($this->move['user_alliance']) && !empty($this->move['user_alliance'])) {
            $sql = 'SELECT SUM(mood_modifier) AS mood_alliance
                    FROM settlers_relations
                    WHERE planet_id = '.$this->move['dest'].' AND
                          user_id != '.$this->move['user_id'].' AND
                          alliance_id = '.$this->move['user_alliance'];
            if(($mood_alliance = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
            }
        }
        else $mood_alliance['mood_alliance'] = 0;

        $mood['alliance'] = 0.50 * $mood_alliance['mood_alliance'];
        */

        // Player mood
        $sql = 'SELECT SUM(mood_modifier) AS mood_user
                FROM settlers_relations
                WHERE planet_id = '.$this->move['dest'].' AND
                      user_id = '.$this->move['user_id'];
        if(($mood_user = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
        }

        $mood['user'] = $mood_user['mood_user'];


        $mood['value'] = (!empty($mood['race']) ? $mood['race'] : 0) +
                         (!empty($mood['alliance']) ? $mood['alliance'] : 0) +
                         (!empty($mood['user']) ? $mood['user'] : 0);


        switch($this->action_data[0]){
            /**
             * First contact mission.
             */
            case 0:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'First contact on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Primo contatto su ';
                        $f_c_fail = ' fallito';
                        $f_c_success = ' avvenuto';
                    break;
                    default:
                        $f_c_title = 'First contact on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array(
                    27,
                    $this->move['user_id'],
                    $this->move['start'],
                    $this->start['planet_name'],
                    $this->start['user_id'],
                    $this->move['dest'],
                    $this->dest['planet_name'],
                    $this->dest['user_id'],
                    0
                );

                $log_data[8] = array(
                    'mission_type'   => 0,
                    'mission_result' => 0,
                );

                if($mood['value'] != 0) {
                    // Invalid move. 
                    $log_data[8]['mission_result'] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
                }
                else
                {
                    if($mood['value'] >= 0)  {
                        // Mission successfully
                        // Insert First Contact record, log_code = 1
                        // If the player is President or Diplomatic, add alliance mood value
                        if(isset($this->move['user_alliance']) && !empty($this->move['user_alliance']) && $this->move['user_alliance_status'] > 2) {
                            $sql = 'INSERT INTO settlers_relations
                                    SET planet_id = '.$this->move['dest'].',
                                        race_id = '.$this->move['user_race'].',
                                        user_id = '.$this->move['user_id'].',
                                        alliance_id = '.$this->move['user_alliance'].',
                                        timestamp = '.time().',
                                        log_code = '.LC_FIRST_CONTACT.',
                                        mood_modifier = 20';
                            
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                        }
                        else {
                            $sql = 'INSERT INTO settlers_relations
                                    SET planet_id = '.$this->move['dest'].',
                                        race_id = '.$this->move['user_race'].',
                                        user_id = '.$this->move['user_id'].',
                                        timestamp = '.time().',
                                        log_code = '.LC_FIRST_CONTACT.',
                                        mood_modifier = 10';

                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                        }

                        // Calculate Exp of the mission
                        if($ship_details['experience'] < 75) {
                            $actual_exp = $ship_details['experience'];
                            $exp = (2.7/((float)$actual_exp*0.0635))+1.5;
                            $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                            }
                        }
                        $log_data[8]['mission_result'] = 1;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);
                    }
                    else
                    {
                        // Mission failed!
                        $log_data[8]['mission_result'] = -2;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
                    }
                }
            break;

            /**
             * Reconnaissance mission.
             */
            case 1:
                switch($this->move['language'])	{
                    case 'GER':
                        $f_c_title = 'Recon mission on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Ricognizione su ';
                        $f_c_fail = ' fallita';
                        $f_c_success = ' terminata';
                    break;
                    default:
                        $f_c_title = 'Recon mission on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 1, 0);                

                // Mood Section
                $index = 0;
                $sql = 'SELECT sr.user_id, u.user_name, u.user_alliance, u.user_race, SUM(mood_modifier) as mood_value
                        FROM (settlers_relations sr)
                        LEFT JOIN (user u) ON sr.user_id = u.user_id
                        WHERE sr.user_id != '.$this->move['user_id'].' AND
                              sr.planet_id = '.$this->move['dest'].'
                              GROUP BY sr.user_id ORDER BY mood_value
                        LIMIT 0,10';
                $user_mood_query = $this->db->query($sql);
                $user_mood_data = array();
                while($user_mood_item = $this->db->fetchrow($user_mood_query)) {
                    $user_mood_data[$index] = $user_mood_item;
                    $index++;
                }

                $log_data[5] = array();
                $log_data[6] = array();
                $log_data[5]['user_mood'] = $mood['user'];
                $log_data[5]['toptenlist'] = $user_mood_data;

                // Tech section
                // We will display info on the tech we can teach to the planet, accordingly with RACE_DATA table, element 29

                $sql='SELECT MAX(research_1) as rescap1, MAX(research_2) as rescap2, MAX(research_3) as rescap3,
                             MAX(research_4) as rescap4, MAX(research_5) as rescap5
                      FROM planets WHERE planet_owner = '.$this->move['user_id'];
                $rc_q = $this->db->queryrow($sql);

                if($this->dest['research_1'] < 10 && $RACE_DATA[$this->move['user_race']][29][0] && $this->dest['research_1'] < $rc_q['rescap1'])
                {
                    $log_data[6]['research_1'] = true;
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 0';
                    if($q_time = $this->db->queryrow($sql))
                    {
                        $log_data[6]['time_1'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }

                if($this->dest['research_2'] < 10 && $RACE_DATA[$this->move['user_race']][29][1] && $this->dest['research_2'] < $rc_q['rescap2'])
                {
                    $log_data[6]['research_2'] = true;
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 1';
                    if($q_time = $this->db->queryrow($sql))
                    {
                        $log_data[6]['time_2'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }

                if($this->dest['research_3'] < 10 && $RACE_DATA[$this->move['user_race']][29][2] && $this->dest['research_3'] < $rc_q['rescap3'])
                {
                    $log_data[6]['research_3'] = true;
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 2';
                    if($q_time = $this->db->queryrow($sql))
                    {
                        $log_data[6]['time_3'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }

                if($this->dest['research_4'] < 10 && $RACE_DATA[$this->move['user_race']][29][3] && $this->dest['research_4'] < $rc_q['rescap4'])
                {
                    $log_data[6]['research_4'] = true;
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 3';
                    if($q_time = $this->db->queryrow($sql))
                    {
                        $log_data[6]['time_4'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }

                if($this->dest['research_5'] < 10 && $RACE_DATA[$this->move['user_race']][29][4] && $this->dest['research_5'] < $rc_q['rescap5'])
                {
                    $log_data[6]['research_5'] = true;
                    $sql = 'SELECT research_start, research_finish
                            FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                            AND player_id = '.INDEPENDENT_USERID.' AND research_id = 4';
                    if($q_time = $this->db->queryrow($sql))
                    {
                        $log_data[6]['time_5'] = format_time(($q_time['research_finish'] - $ACTUAL_TICK)*TICK_DURATION);
                    }
                }

                add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);

            break;

            /**
             * Diplomatic Agreement.
             */
            case 2:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Diplo Speech on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Trattative Diplomatiche su ';
                        $f_c_fail = ' fallite';
                        $f_c_success = ' concluse';
                    break;
                    default:
                        $f_c_title = 'Diplo Speech on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 2, 0);

                if($mood['value'] < 1) {
                    // Invalid move. 
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                else
                {
                    // Check whether an agreement is still valid
                
                    $sql = 'SELECT log_code, mood_modifier
                            FROM settlers_relations
                            WHERE planet_id = '.$this->move['dest'].' AND
                                  user_id = '.$this->move['user_id'].' AND
                                  log_code = '.LC_DIPLO_SPEECH;
                    if(($_qd = $this->db->queryrow($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
                    }

                    if(!empty($_qd['log_code']) && $_qd['log_code'] == LC_DIPLO_SPEECH && $_qd['mood_modifier'] > 0) {
                        // There's already a valid agreement, invalid move
                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                        $log_data[5] = -2;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    }
                    else
                    {
                        $speech_value = 10;

                        // Alliance mood
                        if(isset($this->move['user_alliance']) && !empty($this->move['user_alliance'])) {
                            $sql = 'SELECT SUM(mood_modifier) AS mood_alliance
                                    FROM settlers_relations
                                    WHERE planet_id = '.$this->move['dest'].' AND
                                    log_code = 1 AND
                                    user_id != '.$this->move['user_id'].' AND
                                    alliance_id = '.$this->move['user_alliance'];
                            if(($mood_alliance = $this->db->queryrow($sql)) === false) {
                                return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
                            }
                        }
                        else $mood_alliance['mood_alliance'] = 0;

                        $mood['alliance'] = 0.50 * $mood_alliance['mood_alliance'];

                        $speech_value += $mood['alliance'];

                        $sql = 'SELECT threat_level FROM borg_target WHERE user_id = '.$this->move['user_id'];
                        $bot_target_data = $this->db->query($sql);
                        $already_acquired = $this->db->num_rows($bot_target_data);
                        if($already_acquired > 0) {
                            $honor_bonus_data = $this->db->fetchrow($bot_target_data);
                            // REMEMBER to keep this aligned with those present in borg.php!!
                            if($honor_bonus_data['threat_level'] > 1400.0)
                                $speech_value += 50;
                            elseif($honor_bonus_data['threat_level'] > 950.0)
                                $speech_value += 35;
                            elseif($honor_bonus_data['threat_level'] > 450.0)
                                $speech_value += 25;
                            elseif($honor_bonus_data['threat_level'] > 200.0)
                                $speech_value += 15;
                            else
                                $speech_value += 10;
                        }

                        $sql = 'SELECT ud_id FROM user_diplomacy
                                WHERE user1_id = '.INDEPENDENT_USERID.' AND user2_id = '.$this->move['user_id'];
                        $diplo_data = $this->db->queryrow($sql);
                        if(isset($diplo_data['ud_id']) && !empty($diplo_data['ud_id'])) {
                            $speech_value += 30;
                        }
                        else {
                            $speech_value += 30;
                            $sql = 'INSERT INTO user_diplomacy 
                                    SET user1_id = '.INDEPENDENT_USERID.',
                                        user2_id = '.$this->move['user_id'].',
                                        date = '.time().',
                                        accepted = 1';
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create user alliance with the settlers! SKIP!!!');
                            }
                        }

                        $sql = 'INSERT INTO settlers_relations
                                SET planet_id = '.$this->move['dest'].',
                                    race_id = '.$this->move['user_race'].',
                                    user_id = '.$this->move['user_id'].',
                                    timestamp = '.time().',
                                    log_code = '.LC_DIPLO_SPEECH.',
                                    mood_modifier = '.$speech_value;

                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                        }

                        // Calculate Exp of the mission
                        if($ship_details['experience'] < 99) {
                            $actual_exp = $ship_details['experience'];
                            $exp = (2.9/((float)$actual_exp*0.0635))+2.5;
                            $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                            }
                        }

                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                        $log_data[6] = $speech_value;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    }
                }
            break;
            /**
             * Tech Help
             */
            case 3:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Tech Support on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' succesful';
                    break;
                    case 'ITA':
                        $f_c_title = 'Supporto tecnologico su ';
                        $f_c_fail = ' non fornito';
                        $f_c_success = ' fornito con successo';
                    break;
                    default:
                        $f_c_title = 'Tech Support on ';
                        $f_c_fail = ' failed';
                        $f_c_success = ' succesful';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 3, 0);

                if($this->action_data[1] < 0 || $this->action_data[1] == 1) {
                    // Mission parameter invalid. Exit.
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }

                $tech_data = $this->action_data[1];

                switch($tech_data)
                {
                    case 0:
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Environmental modification</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Modifica ambientale</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Environmental modification</b>';
                            break;
                        }
                    break;
                    case 2:
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Defenses upgrade</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Aggiornamento difese</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Defenses upgrade</b>';
                            break;
                        }
                    break;
                    case 3:
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Automation</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Automazione</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Automation</b>';
                            break;
                        }
                    break;
                    case 4:
                        switch($this->move['language'])
                        {
                            case 'GER':
                                $log_data[7] = '<b>Mining</b>';
                            break;
                            case 'ITA':
                                $log_data[7] = '<b>Estrazione</b>';
                            break;
                            default:
                                $log_data[7] = '<b>Mining</b>';
                            break;
                        }
                    break;
                }


                // Let's check if planet reached maximum research

                if($this->dest['research_'.($tech_data+1)] >= 9) {
                    //Research cannot go tooo far!!! Exit.
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -2;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }
                else
                {
                    // Let's check if we can afford to supply this tech

                    $sql='SELECT MAX(research_'.($tech_data+1).') as rescap FROM planets WHERE planet_owner = '.$this->move['user_id'];
                    $rc_q = $this->db->queryrow($sql);

                    if(!isset($rc_q['rescap']) || $rc_q['rescap'] <= ($this->dest['research_'.($tech_data+1)])) {
                        // We can't do this!!!
                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                        $log_data[5] = -3;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    }
                }

                // Let's check if someone else is already doing this mission.

                $sql = 'SELECT COUNT(*) as counter 
                        FROM scheduler_research WHERE planet_id = '.$this->move['dest'].'
                        AND research_id = '.$tech_data; 
                $c_q = $this->db->queryrow($sql);

                if(isset($c_q['counter']) && $c_q['counter'] > 0) {
                    // The research is already undergoing!!!
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -4;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                    // refound the resources!
                }

                if($log_data[5] == 0) {
                    // ALL GREEN: LET'S DO THIS
                    // Code taken from modules/researchlabs.php
                    $time=0;

                    $time=$TECH_DATA[$tech_data][3]+ pow($this->dest['research_'.($tech_data+1)],$TECH_DATA[$tech_data][4]);
                    $time*=$RACE_DATA[13][4]; // Settlers Race is 13

                    $time/=100;
                    $time*=(100-2*($this->dest['research_4']*$RACE_DATA[13][20])); // Settlers Race is 13
                    if ($time<1) $time=1;
                    $time=round($time,0);

                    $sql = 'INSERT INTO scheduler_research (research_id,planet_id,player_id,research_finish,research_start)
                            VALUES ("'.$tech_data.'","'.$this->move['dest'].'","'.INDEPENDENT_USERID.'","'.($ACTUAL_TICK+$time).'","'.$ACTUAL_TICK.'")';

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not write research record for settlers research! SKIP!!!');
                    }

                    $log_data[8] = format_time($time*TICK_DURATION);

                    // Tech Rewards. Create a LC_TECH_SUPPORT/LC_DEF_SUPPORT entry on settlers_relations or upgrade it

                    $tech_value = $tech_reward[$this->dest['research_'.($tech_data+1)]];

                    $sql = 'UPDATE settlers_relations SET mood_modifier = mood_modifier + '.$tech_value.', timestamp = '.time().'
                            WHERE planet_id = '.$this->move['dest'].'
                            AND user_id = '.$this->move['user_id'].'
                            AND log_code = '.($tech_data== 2 ? LC_DEF_SUPPORT : LC_TECH_SUPPORT);

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update settlers relations! SKIP!!!');
                    }

                    $s_upd = $this->db->affected_rows();

                    if($s_upd == 0) {
                            $sql = 'INSERT INTO settlers_relations
                                    SET planet_id = '.$this->move['dest'].',
                                        race_id = '.$this->move['user_race'].',
                                        user_id = '.$this->move['user_id'].',
                                        timestamp = '.time().',
                                        log_code = '.($tech_data== 2 ? LC_DEF_SUPPORT : LC_TECH_SUPPORT).',
                                        mood_modifier = '.$tech_value;

                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                    }

                    // Calculate Exp of the mission
                    if($ship_details['experience'] < 99) {
                        $actual_exp = $ship_details['experience'];
                        $exp = (2.9/((float)$actual_exp*0.0635))+2.5;
                        $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                        }
                    }

                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    $log_data[6] = $tech_value;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                }

            break;
            /**
             * Orbital Defense Help
             */
            case 4:
                switch($this->move['language'])
                {
                    case 'GER':
                        $f_c_title = 'Orbital defense building on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                    case 'ITA':
                        $f_c_title = 'Costruzione difese orbitali su ';
                        $f_c_fail = ' non ultimata';
                        $f_c_success = ' ultimata';
                    break;
                    default:
                        $f_c_title = 'Orbital defense building on ';
                        $f_c_fail = ' not done';
                        $f_c_success = ' done';
                    break;
                }

                $log_data = array($this->move['dest'],$this->dest['planet_name'], $ship_details['ship_id'], $name_of_ship, 4, 0);

                // The player need to be ally of Settlers Faction
                $sql = 'SELECT COUNT(*) as conto FROM user_diplomacy
                        WHERE user1_id = '.INDEPENDENT_USERID.' AND user2_id = '.$this->move['user_id'];
                $diplo_data = $this->db->queryrow($sql);

                if(!isset($diplo_data['conto']) || $diplo_data['conto'] != 1)
                {
                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                    $log_data[5] = -2;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                }
                else {

                    $sql = 'SELECT COUNT(*) as conto
                            FROM settlers_relations WHERE planet_id = '.$this->move['dest'].'
                            AND user_id = '.$this->move['user_id'].' AND log_code = '.LC_DIPLO_SPEECH;
                    $diplo_data = $this->db->queryrow($sql);

                    if(!isset($diplo_data['conto']) || $diplo_data['conto'] != 1)
                    {
                        // A Treaty must exists on planet for this move to work
                        $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                        $log_data[5] = -2;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                    }
                }

                $sql='SELECT fleet_id FROM ship_fleets WHERE fleet_name = "Orbital'.$this->move['dest'].'"';
                $od_q = $this->db->queryrow($sql);
                if(isset($od_q['fleet_id']) && !empty($od_q['fleet_id']))
                {
                        $sql='SELECT COUNT(*) AS counter FROM ships WHERE fleet_id = '.$od_q['fleet_id'];
                        $orbital_check=$this->db->queryrow($sql);
                        $orbital_counter = $orbital_check['counter'];
                        if($orbital_counter >= STL_MAX_ORBITAL)
                        {
                            // A Treaty must exists on planet for this move to work
                            $log_title = $f_c_title.$this->dest['planet_name'].$f_c_fail;
                            $log_data[5] = -1;
                            add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                    
                        }
                }
                else
                {
                    $orbital_counter = 0;
                }


                if($log_data[5] == 0)
                {
                    $sql = 'SELECT value_5, value_9, rof, max_torp FROM `ship_templates`
                            WHERE `id` = '.$cfg_data['settler_tmp_4'];
                    if(($stpl = $this->db->queryrow($sql)) === false)
                        return $this->log(MV_M_DATABASE, '<b>Error:</b> Could not query settlers ship template data');

                    if($orbital_counter > 1 && $orbital_counter < STL_MAX_ORBITAL)
                    {
                        $fleet_id = $od_q['fleet_id'];
                        $orbital_to_made = min((STL_MAX_ORBITAL - $orbital_counter), 10);
                    }
                    else
                    {
                        // Orbital defence fleet not exists. Let's create this.
                        $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id,
                                                         alert_phase, move_id, n_ships)
                                VALUES ("Orbital'.$this->move['dest'].'", '.INDEPENDENT_USERID.', '.$this->move['dest'].',
                                        '.ALERT_PHASE_GREEN.', 0, 10)';
                        $this->db->query($sql);
                        $fleet_id = $this->db->insert_id();
                        $orbital_to_made = 10;
                    }

                    $sql = 'INSERT INTO ships (fleet_id, user_id, template_id,
                                               experience, hitpoints, construction_time,
                                               torp, rof, last_refit_time)
                            VALUES ('.$fleet_id.', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].',
                                    '.$stpl['value_9'].', '.$stpl['value_5'].', '.time().',
                                    '.$stpl['max_torp'].', '.$stpl['rof'].', '.time().')';
                    $tally = 0;
                    while($tally < $orbital_to_made)
                    {
                        if(!$this->db->query($sql)) 
                        {
                            return $this->log(MV_M_DATABASE, 'Error while adding an orbital cannon for Settlers ---> '.$sql);
                        }
                        $tally++;
                    }

                    $orbital_reward = 1*$tally;

                    $sql='UPDATE settlers_relations SET mood_modifier = mood_modifier + '.$orbital_reward.', timestamp = '.time().'
                          WHERE planet_id = '.$this->move['dest'].' AND user_id = '.$this->move['user_id'].' AND log_code = '.LC_MIL_SUPPORT;

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update settlers relations! SKIP!!!');
                    }

                    $s_upd = $this->db->affected_rows();

                    if($s_upd == 0) {
                            $sql = 'INSERT INTO settlers_relations
                                    SET planet_id = '.$this->move['dest'].',
                                        race_id = '.$this->move['user_race'].',
                                        user_id = '.$this->move['user_id'].',
                                        timestamp = '.time().',
                                        log_code = '.LC_MIL_SUPPORT.',
                                        mood_modifier = '.$orbital_reward;

                            if(!$this->db->query($sql)) {
                                return $this->log(MV_M_DATABASE, 'Could not create new settlers relations! SKIP!!!');
                            }
                    }

                    $log_data[6] = $tally;

                    $log_title = $f_c_title.$this->dest['planet_name'].$f_c_success;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);

                    // Calculate Exp of the mission
                    if($ship_details['experience'] < 99) {
                        $actual_exp = $ship_details['experience'];
                        $exp = (2.9/((float)$actual_exp*0.0635))+2.5;
                        $sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
                        if(!$this->db->query($sql)) {
                            return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
                        }
                    }
                }
            break;
        }

        $old_besty = $this->check_best_mood($this->move['dest'], true);

        if($old_besty > 0)
        {
            $log_data = array($this->move['dest'],$this->dest['planet_name'], 0, '', 100);

            switch($this->move['language'])
            {
                case 'GER':
                    $log_title = 'Priority message from ';
                break;
                case 'ITA':
                    $log_title = 'Comunicazione prioritaria dalla colonia ';
                break;
                default:
                    $log_title = 'Priority message from ';
                break;
            }

            add_logbook_entry($old_besty, LOGBOOK_SETTLERS, $log_title.$this->dest['planet_name'], $log_data);
        }

        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
        }

        return MV_EXEC_OK;
    }
}



?>

