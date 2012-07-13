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




class moves_action_27 extends moves_common {

    function _action_main() {

        global $PLANETS_DATA;

        // #############################################################################
        // Away Team Missions!!!
        //
        // Beam us down! Energy!
        //
        //#############################################################################

        define('LC_FIRST_CONTACT', 1);
        define('LC_DIPLO_SPEECH', 2);
        define('LC_COLO_FOUNDER', 30);

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
            $this->log(MV_M_DATABASE, 'action_27: Could not find required action_data entry [0]! FORCED TO ZERO');
            $this->action_data[0] = 0;
        }
        
        /**
         * Check planet's mood.
         */

        // The mood of the planet is calculated by adding racial modifiers, alliance
        // modifiers (if any) and player's one

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
                                        mood_modifier = 10';
                            
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
                    'mission_type'   => 1,
                    'mission_result' => 1,
                    'user_mood'      => $mood
                );

                $index = 0;
                $sql = 'SELECT sr.user_id, u.user_name, u.user_alliance, u.user_race, SUM(mood_modifier) as mood_value
                        FROM (settlers_relations sr)
                        LEFT JOIN (user u) ON sr.user_id = u.user_id
                        WHERE sr.user_id != '.$this->move['user_id'].' AND
                              sr.planet_id = '.$this->move['dest'].'
                              GROUP BY sr.user_id ORDER BY mood_value
                        LIMIT 0,10';
                $user_mood_query = $this->db->query($sql);
                while($user_mood_item = $this->db->fetchrow($user_mood_query)) {
                    $user_mood_data[$index] = $user_mood_item;
                    $index++;
                }

                $log_data[8]['toptenlist'] = $user_mood_data;

                add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);
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
                        $f_c_title = 'Trarrative Diplomatiche su ';
                        $f_c_fail = ' fallite';
                        $f_c_success = ' concluse';
                    break;
                    default:
                        $f_c_title = 'Diplo Speech on ';
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
                    'mission_type'   => 2,
                    'mission_result' => 0,
                );

                if($mood['value'] < 1) {
                    // Invalid move. 
                    $log_data[8]['mission_result'] = -1;
                    add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
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
                        $log_data[8]['mission_result'] = -2;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
                    }
                    else
                    {
                        $speech_value = 10;

                        if($_qd['log_code'] == LC_DIPLO_SPEECH && $_qd['mood_modifier'] == 0) $speech_value += 5;

                        $sql = 'SELECT * FROM borg_target WHERE user_id = '.$this->move['user_id'];
                        $bot_target_data = $this->db->query($sql);
                        $already_acquired = $this->db->num_rows($bot_target_data);
                        if($already_acquired > 0) {
                            $honor_bonus_data = $this->db->fetchrow($bot_target_data);
                            // Tenere aggiornati i valori qui indicati con quelli presenti in borg.php
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

                        $sql = 'SELECT * FROM user_diplomacy
                                WHERE user1_id = '.INDEPENDENT_USERID.' AND user2_id = '.$this->move['user_id'];
                        $diplo_data = $this->db->queryrow($sql);
                        if(isset($diplo_data['ud_id']) && !empty($diplo_data['ud_id'])) {
                            $speech_value += 10;
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

                        $log_data[8]['mission_result'] = 1;
                        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);
                    }
                }
            break;
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

