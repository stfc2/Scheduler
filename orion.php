<?php
/*
    This file is part of STFC.it
    Copyright 2008-2017 by Andrea Carolfi (carolfi@stfc.it) and
    Cristiano Delogu (delogu@stfc.it).

    STFC.it is based on STFC,
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


//########################################################################################
//########################################################################################
// Startconfig of Orion Syndicate
class Orion extends NPC
{
    public function Install($log = INSTALL_LOG_FILE_NPC)
    {
        $this->sdl->start_job('Orion basic system', $log);

        // We give the bot some data so that it is also Registered
        $this->bot = $this->db->queryrow('SELECT * FROM user WHERE user_id = '.ORION_USERID);

        //Check whether the bot already lives
        if(empty($this->bot['user_id'])) {
            $this->sdl->log('We need to create Orion Syndicate!', $log);

            $sql = 'INSERT INTO user (user_id, user_auth_level, user_name, user_loginname, user_password,
                                      user_email, user_active, user_race, user_gfxpath, user_skinpath,
                                    user_registration_time, user_registration_ip,
                                          user_birthday, user_gender, plz, country, user_enable_sig,
                                          user_message_sig, user_signature, user_notepad, user_options, message_basement)
                         VALUES ('.ORION_USERID.', '.STGC_BOT.', "Orion Syndicate(NPC)", "OrionBot", "'.md5("orion42").'",
                                 "pirates@stfc.it", 1, 7, "'.DEFAULT_GFX_PATH.'", "skin1/", '.time().', "127.0.0.1",
                                 "28.10.2016", "", 16162 , "IT", 1,
                                 "",  "", "", "", "")';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not create Orion Syndicate - ABORTED', $log);
            }
        }
        
        $orion_ships_id = $this->db->queryrow('SELECT orion_tmp_1, orion_tmp_2, orion_tmp_3, orion_tmp_4, orion_tmp_5 FROM config WHERE config_set_id = 0');
        
        for ($key = 0; $key < 5; $key++) {
            if(empty($orion_ships_id['orion_tmp_'.($key+1)])){
                $this->sdl->log('Writing Orion Syndicate template orion_tmp_'.$key+1, $log);
                switch ($key) {
                    case 0:
                        $sql = 'INSERT INTO `ship_templates` (`owner`, `timestamp`, `name`, `description`, `race`, `ship_torso`, `ship_class`, 
                                                              `component_1`, `component_2`, `component_3`, `component_4`, `component_5`, 
                                                              `component_6`, `component_7`, `component_8`, `component_9`, `component_10`, 
                                                              `value_1`, `value_2`, `value_3`, `value_4`, `value_5`, `value_6`, 
                                                              `value_7`, `value_8`, `value_9`, `value_10`, `value_11`, `value_12`, 
                                                              `value_13`, `value_14`, `value_15`, `rof`, `rof2`, `max_torp`, 
                                                              `resource_1`, `resource_2`, `resource_3`, `resource_4`, `unit_5`, `unit_6`, 
                                                              `min_unit_1`, `min_unit_2`, `min_unit_3`, `min_unit_4`, 
                                                              `max_unit_1`, `max_unit_2`, `max_unit_3`, `max_unit_4`, `buildtime`) 
                                                              VALUES ("'.ORION_USERID.'","'.time().'", "Lookout", "Lookout pirate ship", 7, 3, 1, 
                                                              -1, -1, -1, -1, -1, 
                                                              -1, -1, -1, -1, -1, 
                                                              100, 0, 0, 185, 200, 6,
                                                              5, 40, 20, 6.5, 30, 0, 
                                                              100, 80, 0, 8, 0, 0, 
                                                              15856, 15856, 12636, 324, 2, 1, 
                                                              30, 0, 0, 1, 
                                                              30, 0, 0, 1, 0)';                        
                        break;
                    case 1:
                        $sql = 'INSERT INTO `ship_templates` (`owner`, `timestamp`, `name`, `description`, `race`, `ship_torso`, `ship_class`, 
                                                              `component_1`, `component_2`, `component_3`, `component_4`, `component_5`, 
                                                              `component_6`, `component_7`, `component_8`, `component_9`, `component_10`, 
                                                              `value_1`, `value_2`, `value_3`, `value_4`, `value_5`, `value_6`, 
                                                              `value_7`, `value_8`, `value_9`, `value_10`, `value_11`, `value_12`, 
                                                              `value_13`, `value_14`, `value_15`, `rof`, `rof2`, `max_torp`, 
                                                              `resource_1`, `resource_2`, `resource_3`, `resource_4`, `unit_5`, `unit_6`, 
                                                              `min_unit_1`, `min_unit_2`, `min_unit_3`, `min_unit_4`, 
                                                              `max_unit_1`, `max_unit_2`, `max_unit_3`, `max_unit_4`, `buildtime`) 
                                                              VALUES ("'.ORION_USERID.'","'.time().'", "Corsair", "Frigate Pirate Ship", 7, 4, 1, 
                                                              -1, -1, -1, -1, -1, 
                                                              -1, -1, -1, -1, -1, 
                                                              100, 0, 0, 220, 240, 6, 
                                                              5, 40, 20, 6.5, 30, 0, 
                                                              100, 80, 0, 8, 0, 0, 
                                                              23520, 23520, 18720, 480, 2, 1, 
                                                              30, 0, 0, 1, 
                                                              30, 0, 0, 1, 0)';
                        break;
                    case 2:
                        $sql = 'INSERT INTO `ship_templates` (`owner`, `timestamp`, `name`, `description`, `race`, `ship_torso`, `ship_class`, 
                                                              `component_1`, `component_2`, `component_3`, `component_4`, `component_5`, 
                                                              `component_6`, `component_7`, `component_8`, `component_9`, `component_10`, 
                                                              `value_1`, `value_2`, `value_3`, `value_4`, `value_5`, `value_6`, 
                                                              `value_7`, `value_8`, `value_9`, `value_10`, `value_11`, `value_12`, 
                                                              `value_13`, `value_14`, `value_15`, `rof`, `rof2`, `max_torp`, 
                                                              `resource_1`, `resource_2`, `resource_3`, `resource_4`, `unit_5`, `unit_6`, 
                                                              `min_unit_1`, `min_unit_2`, `min_unit_3`, `min_unit_4`, 
                                                              `max_unit_1`, `max_unit_2`, `max_unit_3`, `max_unit_4`, `buildtime`) 
                                                              VALUES ("'.ORION_USERID.'","'.time().'", "Predator", "Pirate Light-Cruiser Ships", 7, 6, 2, 
                                                              -1, -1, -1, -1, -1, 
                                                              -1, -1, -1, -1, -1, 
                                                              103, 0, 0, 476, 665, 6, 
                                                              12, 26, 40, 6.5, 34, 0, 
                                                              160, 140, 0, 14, 0, 0, 
                                                              54929, 54929, 43719, 1121, 8, 4, 
                                                              100, 0, 0, 2, 
                                                              100, 0, 0, 2, 0)';
                        break;
                    case 3:
                        $sql = 'INSERT INTO `ship_templates` (`owner`, `timestamp`, `name`, `description`, `race`, `ship_torso`, `ship_class`, 
                                                              `component_1`, `component_2`, `component_3`, `component_4`, `component_5`, 
                                                              `component_6`, `component_7`, `component_8`, `component_9`, `component_10`, 
                                                              `value_1`, `value_2`, `value_3`, `value_4`, `value_5`, `value_6`, 
                                                              `value_7`, `value_8`, `value_9`, `value_10`, `value_11`, `value_12`, 
                                                              `value_13`, `value_14`, `value_15`, `rof`, `rof2`, `max_torp`, 
                                                              `resource_1`, `resource_2`, `resource_3`, `resource_4`, `unit_5`, `unit_6`, 
                                                              `min_unit_1`, `min_unit_2`, `min_unit_3`, `min_unit_4`, 
                                                              `max_unit_1`, `max_unit_2`, `max_unit_3`, `max_unit_4`, `buildtime`) 
                                                              VALUES ("'.ORION_USERID.'","'.time().'", "Marauder", "Pirate Cruiser ship", 7, 8, 2, 
                                                              -1, -1, -1, -1, -1, 
                                                              -1, -1, -1, -1, -1, 
                                                              100, 210, 16, 1146, 2151, 6, 
                                                              11, 22, 80, 6.5, 35, 0, 
                                                              250, 200, 0, 8, 6, 100, 
                                                              109809, 109809, 87399, 2241, 12, 6, 
                                                              150, 0, 0, 3, 
                                                              150, 0, 0, 3, 0)';
                        break;
                    case 4:
                        $sql = 'INSERT INTO `ship_templates` (`owner`, `timestamp`, `name`, `description`, `race`, `ship_torso`, `ship_class`, 
                                                              `component_1`, `component_2`, `component_3`, `component_4`, `component_5`, 
                                                              `component_6`, `component_7`, `component_8`, `component_9`, `component_10`, 
                                                              `value_1`, `value_2`, `value_3`, `value_4`, `value_5`, `value_6`, 
                                                              `value_7`, `value_8`, `value_9`, `value_10`, `value_11`, `value_12`, 
                                                              `value_13`, `value_14`, `value_15`, `rof`, `rof2`, `max_torp`, 
                                                              `resource_1`, `resource_2`, `resource_3`, `resource_4`, `unit_5`, `unit_6`, 
                                                              `min_unit_1`, `min_unit_2`, `min_unit_3`, `min_unit_4`, 
                                                              `max_unit_1`, `max_unit_2`, `max_unit_3`, `max_unit_4`, `buildtime`) 
                                                              VALUES ("'.ORION_USERID.'","'.time().'", "Slavemaster", "Pirate Battleship", 7, 10, 3, 
                                                              -1, -1, -1, -1, -1, 
                                                              -1, -1, -1, -1, -1, 
                                                              100, 210, 100, 2600, 4100, 22, 
                                                              30, 16, 120, 6.5, 50, 0, 
                                                              280, 250, 0, 18, 6, 300, 
                                                              154840, 154840, 123240, 3160, 30, 8, 
                                                              165, 0, 0, 3, 
                                                              165, 0, 0, 3, 0)';
                        break;
                }

                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create Orion Syndicate template orion_tmp_'.($key+1).' - ABORTED', $log);
                }
               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET orion_tmp_'.($key+1).' = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);                
            }
        }
        $this->sdl->finish_job('Orion basic system', $log);
    }

    public function Execute($debug=0)
    {
        global $ACTUAL_TICK, $INTER_SYSTEM_TIME, $cfg_data, $RACE_DATA, $QUADRANT_NAME;
        $starttime = ( microtime() + time() );

        $this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
            '<b>Starting Orion Bot at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

        // Update BOT user ID
        $this->bot['user_id'] = ORION_USERID;

        // ########################################################################################
        // ########################################################################################
        // Messages answer
        $messages=array('Bot system.','Bot system.','Consegnate il vostro carico e non vi sar&agrave; fatto alcun male.');
        $titles=array('--','--','Contatto rifiutato.');

        $this->ReplyToUser($titles,$messages);
        // ########################################################################################
        // ########################################################################################
        // Read Logbook

        $this->ReadLogbook();
        // ########################################################################################
        // ########################################################################################

        $this->sdl->start_job('Orion Fleets Control', TICK_LOG_FILE_NPC);

        $sql = 'SELECT orion_tmp_1, orion_tmp_2, orion_tmp_3, orion_tmp_4, orion_tmp_5 FROM config WHERE config_set_id = 0';
        if(!($orion_temp_ids = $this->db->queryrow($sql))) {
            return $this->sdl->log('Could not read config data! SKIP'.$sql,TICK_LOG_FILE_NPC);
        }

        $sql='SELECT sf.*, p.system_id FROM ship_fleets sf LEFT JOIN planets p USING (planet_id) WHERE sf.user_id = '.ORION_USERID.' AND sf.planet_id <> 0 AND sf.npc_last_action < '.$ACTUAL_TICK.' ORDER BY sf.npc_last_action ASC';

        if(($setpoint = $this->db->query($sql)) === false)
        {
            $this->sdl->log('<b>Error:</b> Bot: Could not read fleets DB', TICK_LOG_FILE_NPC);
        }
        else
        {
            while($fleet_to_serve = $this->db->fetchrow($setpoint))
            {
                //$this->sdl->log('DEBUG:ORION_1: Is now acting fleet '.$fleet_to_serve['fleet_id'].': '.$fleet_to_serve['fleet_name'].' over planet '.$fleet_to_serve['planet_id'], TICK_LOG_FILE_NPC );
                $sql = 'SELECT COUNT(*) as slavers FROM ships WHERE fleet_id = '.$fleet_to_serve['fleet_id'].' AND template_id = '.$orion_temp_ids['orion_tmp_5'];
                $major_fleet = $this->db->queryrow($sql);                
                
                // Check timeout
                if($fleet_to_serve['npc_timeout'] > 0 && $fleet_to_serve['npc_timeout'] < $ACTUAL_TICK) {
                    $this->sdl->log('Fleet '.$fleet_to_serve['fleet_id'].': is despawning!!!', TICK_LOG_FILE_NPC );
                    $this->DespawnOrionFleet($fleet_to_serve['fleet_id']);
                    continue;
                }
                                
                // Check altre flotte per merge
                $check_fleet = $this->db->queryrowset('SELECT fleet_id, npc_timeout FROM ship_fleets WHERE fleet_id <> '.$fleet_to_serve['fleet_id'].' AND user_id = '.ORION_USERID.' AND planet_id = '.$fleet_to_serve['planet_id']);
                
                if(count($check_fleet) > 0) {
                    foreach ($check_fleet as $merge_fleet) {
                        $this->sdl->log('Merging '.$merge_fleet['fleet_id'].' with '.$fleet_to_serve['fleet_id'].' for greater glory!', TICK_LOG_FILE_NPC );
                        $sql = 'UPDATE ships SET fleet_id = '.$fleet_to_serve['fleet_id'].' WHERE fleet_id = '.$merge_fleet['fleet_id'];
                        if(!$this->db->query($sql)) {
                                $this->sdl->log('Could not update moving ships.',TICK_LOG_FILE_NPC);
                        }
                        $sql = 'UPDATE ship_fleets SET npc_timeout = '.($merge_fleet['npc_timeout'] >= $fleet_to_serve['npc_timeout'] ? $merge_fleet['npc_timeout'] : $fleet_to_serve['npc_timeout']).' WHERE fleet_id = '.$fleet_to_serve['fleet_id'];
                        if(!$this->db->query($sql)) {
                                $this->sdl->log('Could not update new fleet.',TICK_LOG_FILE_NPC);
                        }                        
                        $sql = 'DELETE FROM ship_fleets WHERE fleet_id = '.$merge_fleet['fleet_id'];
                        if(!$this->db->query($sql)) {
                                $this->sdl->log('Could not delete old fleet',TICK_LOG_FILE_NPC);
                        }                    
                    }
                    break;
                }

                // Check dell'orbita per eventuali bersagli
                $dfd_user = $this->db->queryrow('SELECT DISTINCT user_id FROM ship_fleets WHERE planet_id = '.$fleet_to_serve['planet_id'].' AND user_id > 10 LIMIT 0,1');

                if(isset($dfd_user['user_id']) && !empty($dfd_user['user_id'])) {
                    // Attacchiamo l'utente vicino
                    $this->sdl->log('Fleet '.$fleet_to_serve['fleet_id'].': is attacking '.$dfd_user['user_id'], TICK_LOG_FILE_NPC );                    
                    $this->OrionAttackNearFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $fleet_to_serve['planet_id'], $fleet_to_serve['n_ships'], $dfd_user['user_id']);
                    continue;
                }
            
                // Check delle altre orbite
                $check_id = $this->db->queryrow('SELECT system_id FROM planets WHERE planet_id = '.$fleet_to_serve['planet_id']);
                
                $sql = 'SELECT sf.planet_id FROM ship_fleets sf INNER JOIN planets p USING(planet_id) WHERE sf.user_id > 10 AND sf.planet_id <> '.$fleet_to_serve['planet_id'].' AND p.system_id = '.$check_id['system_id'];
                
                $other_orbit = $this->db->queryrow($sql);

                if(isset($other_orbit['planet_id']) && !empty($other_orbit['planet_id'])) {
                    // Spostiamo la flotta per intercettare!!!! 
                    $this->sdl->log('Fleet '.$fleet_to_serve['fleet_id'].': is moving to a new orbit', TICK_LOG_FILE_NPC );                                        
                    $this->SendOrionFleet($ACTUAL_TICK, $INTER_SYSTEM_TIME, $fleet_to_serve['fleet_id'], $other_orbit['planet_id'], 11);
                    continue;
                }
                
                // Check Flotte in Avvicinamento!!!
                $sql = 'SELECT dest AS planet_id, move_finish  
                        FROM scheduler_shipmovement
                        INNER JOIN planets ON dest = planet_id
                        WHERE move_status = 0 AND
                              scheduler_shipmovement.user_id > 10 AND
                              system_id = '.$fleet_to_serve['system_id'].' AND
                              move_finish < '.($ACTUAL_TICK + 60).' AND                              
                              move_finish > '.($ACTUAL_TICK + 4).' AND
                              dest <> '.$fleet_to_serve['planet_id'].'
                        ORDER BY move_finish ASC
                        LIMIT 0,1';

                $inc_fleet = $this->db->queryrow($sql);

                if(isset($inc_fleet['planet_id']) && !empty($inc_fleet['planet_id'])) {
                    // Spostiamo la flotta per intercettare!!!! 
                    $this->sdl->log('Fleet '.$fleet_to_serve['fleet_id'].': is moving to a new orbit', TICK_LOG_FILE_NPC );                                                            
                    $this->SendOrionFleet($ACTUAL_TICK, $INTER_SYSTEM_TIME, $fleet_to_serve['fleet_id'], $inc_fleet['planet_id'], 11);
                    continue;
                }
                /*
                // Check Pianeta Conquistabile per Flotte Maggiori
                if(isset($major_fleet['slavers']) && $major_fleet['slavers'] > 0 && ($fleet_to_serve['npc_timeout']-480 < $ACTUAL_TICK)) {
                    //Possiamo creare un pianeta pirata se la flotta ci sta orbiando intorno. Funziona solo su E/F/G/K/L/M/O/P
                    $sql = 'SELECT planet_type 
                            FROM planets 
                            INNER JOIN starsystems USING (system_id)
                            WHERE planet_id = '.$fleet_to_serve['planet_id'].' AND 
                                  planet_type IN ("e", "f", "g", "k", "l", "m", "o", "p") AND
                                  (system_n_planets > 3 AND system_n_planets < 7) AND
                                  system_closed = 0 AND
                                  planet_owner = 0 LIMIT 0,1';
                    
                    $res = $this->db->queryrow($sql);
                    
                    if(isset($res['planet_type']) && !empty($res['planet_type'])) {
                        $this->sdl->log('Fleet '.$fleet_to_serve['fleet_id'].': is colonizing planet '.$fleet_to_serve['planet_id'], TICK_LOG_FILE_NPC );
                        $this->SpawnOrionPlanet($fleet_to_serve['planet_id'], $fleet_to_serve['system_id');
                        continue;
                    }
                }
                */
                /*
                //Check Pianeti Conquistabili per Flotte Maggiori nel sistema.
                if(isset($major_fleet['slavers']) && $major_fleet['slavers'] > 0) {
                    $sql = 'SELECT SUM(*) as settled
                            FROM planets 
                            WHERE system_id = '.$fleet_to_serve['system_id'].' AND
                                  planet_owner <> 0';
                    $res = $this->db->queryrow($sql);
                    if(isset($res['settled']) && $res['settled'] <= 2) {
                        $sql = 'SELECT planet_id 
                                FROM planets
                                INNER JOIN starsystems USING (system_id)
                                WHERE system_id = '.$fleet_to_serve['system_id'].' AND
                                      planet_owner = 0 AND
                                      planet_type IN ("e", "f", "g", "k", "l", "m", "o", "p") AND
                                      system_closed = 0 AND
                                      (system_n_planets > 3 AND system_n_planets < 7)
                                LIMIT 0,1';
                        $res2 = $this->db->queryrow($sql);
                        if(isset($res2['planet_id']) && !empty($res2['planet_id'])) {
                            $this->sdl->log('Fleet '.$fleet_to_serve['fleet_id'].': is moving to a new orbit', TICK_LOG_FILE_NPC );
                            $this->SendOrionFleet($ACTUAL_TICK, $INTER_SYSTEM_TIME, $fleet_to_serve['fleet_id'], $res2['planet_id'], 11);
                        }                    
                    }                    
                }
                */
                $sql = 'DELETE FROM starsystems_details WHERE system_id = '.$fleet_to_serve['system_id'].' AND user_id = 0 AND log_code = 1';
                if(!$this->db->query($sql)) {
                        $this->sdl->log('Could not update system patrol data.',TICK_LOG_FILE_NPC);
                }                
                $this->db->query('UPDATE ship_fleets SET npc_last_action = '.($ACTUAL_TICK + 1*20).' WHERE fleet_id = '.$fleet_to_serve['fleet_id']);                    
                
            }
        }

        $this->sdl->finish_job('Orion Fleets Control', TICK_LOG_FILE_NPC);
        
/*
        $this->sdl->start_job('Orion Crime Control', TICK_LOG_FILE_NPC);
        
        $sql='SELECT planet_id FROM planets WHERE planet_owner = '.ORION_USERID.' AND npc_last_action < '.$ACTUAL_TICK.' ORDER BY npc_last_action ASC LIMIT 0, 1';
        
        if(($orion_q = $this->db->queryrow($sql)) === false)
        {
            $this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
        }
        elseif(!empty($orion_q['planet_id']))
        {        
            $sql = 'SELECT p.planet_id, SUM(sr.mood_modifier) as mood_value 
                    FROM (planets p) 
                    LEFT JOIN (settlers_relations sr) ON (p.planet_id = sr.planet_id AND log_code IN (100, 101, 102, 103, 104, 105) AND sr.user_id = '.INDEPENDENT_USERID.')
                    WHERE p.planet_owner = '.INDEPENDENT_USERID.' AND
                          p.sector_id >= '.$sector_id_min.' AND
                          p.sector_id <= '.$sector_id_max.' AND   
                          sr.planet_id IS NOT NULL AND 
                          p.planet_id NOT IN (SELECT planet_id FROM settlers_events WHERE user_id = '.ORION_USERID.' AND event_code = 107)
                    GROUP BY p.planet_id
                    ORDER BY mood_value DESC
                    LIMIT 0,1';
            
            $crime_q = $this->db->queryrow($sql);
            
            if(!empty($crime_q['mood_value']) && $crime_q['mood_value'] >= 80) {
                $sql = 'INSERT INTO settlers_events
                                    (planet_id, user_id, event_code, timestamp, tick, awayteamship_id, awayteam_startlevel, unit_1, unit_2, unit_3, unit_4, awayteam_alive, event_status)
                            VALUES  ('.$crime_q['planet_id'].', '.ORION_USERID.', 107, '.time().', '.$ACTUAL_TICK.', 0, 20, 40, 25, 20, 3, 1, 1)';
                if(!$this->db->query($sql)) {
                    return $this->log(MV_M_DATABASE, 'Could not insert settlers event data! SKIP '.$sql);
                }                    
            }
            
            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20*48).' WHERE planet_id = '.$orion_q['planet_id'];
            $this->sdl->log('SQL E '.$sql, TICK_LOG_FILE_NPC);
            $this->db->query($sql);            
        }
        $this->sdl->finish_job('Orion Crime Control', TICK_LOG_FILE_NPC);        
*/
        
        $this->sdl->start_job('Orion Spawn Control', TICK_LOG_FILE_NPC);
        
        $sql='SELECT planet_id, sector_id, planet_type FROM planets WHERE planet_owner = '.ORION_USERID.' AND npc_last_action < '.$ACTUAL_TICK.' ORDER BY npc_last_action ASC LIMIT 0, 1';
        
        if(($orion_q = $this->db->queryrow($sql)) === false)
        {
            $this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
        }
        elseif(!empty($orion_q['planet_id'])) {
            
            $sql='SELECT ship_id FROM FHB_warteschlange WHERE user_id = '.ORION_USERID.' LIMIT 0,1';
            $notpayed=$this->db->queryrow($sql);
            if(isset($notpayed['ship_id']) && !empty($notpayed['ship_id']))
            {
                $sql='DELETE FROM FHB_warteschlange WHERE ship_id = '.$notpayed['ship_id'];
                $this->sdl->log('SQL A '.$sql, TICK_LOG_FILE_NPC);
                $this->db->query($sql);
                $sql='UPDATE ships SET user_id = '.ORION_USERID.', fleet_id = -'.$orion_q['planet_id'].' WHERE ship_id = '.$notpayed['ship_id'];
                $this->sdl->log('SQL A '.$sql, TICK_LOG_FILE_NPC);
                $this->db->query($sql);
                $this->CreatePirateFleet($orion_q['planet_id'], $notpayed['ship_id']);                
            }            
            
            $sql = 'SELECT * FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id
                    WHERE fleet_id = -'.$orion_q['planet_id'].' AND ship_untouchable = 0
                    ORDER BY construction_time ASC';
            $t_q=$this->db->queryrowset($sql);
            foreach ($t_q AS $orionsell_item) {
                if(rand(0,4) > 2) {
                    $_ress_1 = round($orionsell_item['resource_1']*0.50);
                    $_ress_2 = round($orionsell_item['resource_2']*0.50);
                    $_ress_3 = round($orionsell_item['resource_3']*0.50);
                    $_unit_1 = round($orionsell_item['min_unit_1']*0.20);
                    $_unit_1_step = 4;
                    $_unit_2_step = 2;
                    $_unit_3_step = 1;
                    $sql = 'INSERT INTO ship_trade (user,planet,start_time,end_time,ship_id,resource_1,resource_2,resource_3,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,add_resource_1,add_resource_2,add_resource_3,add_unit_1,add_unit_2,add_unit_3,add_unit_4,add_unit_5,add_unit_6,header,description,show_data,font_bold,font_colored,unowed_only)
                            VALUES   ('.ORION_USERID.','.$orion_q['planet_id'].','.$ACTUAL_TICK.','.($ACTUAL_TICK + 480).','.$orionsell_item['ship_id'].','.$_ress_1.','.$_ress_2.','.$_ress_3.','.$_unit_1.', 0, 0, 0,'.$orionsell_item['unit_5'].','.$orionsell_item['unit_6'].', 0, 0, 0, '.$_unit_1_step.', '.$_unit_2_step.', '.$_unit_3_step.',0,0,0,"'.$orionsell_item['name'].'","This is an automatic generated auction for a ship held by Orion Syndicate!!!",1,1,0,0)';
                    if(!$this->db->query($sql)) {
                        $this->sdl->log('<b>Error:</b> Bot: Could not write auction into DB '.$sql, TICK_LOG_FILE_NPC);
                    }
                    $sql = 'UPDATE ships SET ship_untouchable=1 WHERE ship_id='.$orionsell_item['ship_id'];
                    $this->db->query($sql);
                    $this->sdl->log('Ship '.$orionsell_item['ship_id'].' just sent to auction', TICK_LOG_FILE_NPC );
                }
                else{
                    $this->CreatePirateFleet($orion_q['planet_id'], $orionsell_item['ship_id']);
                }
            }
                
            $quadrant_id = ceil( ( $orion_q['sector_id'] / 81) );
            $sector_id_min = ( ($quadrant_id - 1) * 81) + 1;
            $sector_id_max = $quadrant_id * 81;
            
            $sql = 'SELECT system_id, sector_id
                    FROM starsystems
                    WHERE system_closed = 0
                    AND sector_id >= '.$sector_id_min.'
                    AND sector_id <= '.$sector_id_max.' 
                    AND system_n_planets > 4
                    AND system_id NOT IN (SELECT system_id FROM starsystems_details WHERE log_code = 1)';
            
            $spawn_q = $this->db->queryrowset($sql);
            
            if(count($spawn_q) > 0) {
                $spawn_item = $spawn_q[array_rand($spawn_q)];
                $spawn_id = $spawn_item['system_id'];
                $this->SpawnMajorPirateFleet($ACTUAL_TICK, $spawn_id, $orion_temp_ids);
                
                $sql = 'SELECT user_id, language FROM user WHERE user_id > 10 AND user_active = 1 AND user_auth_level = 1 AND user_vacation_end = 0';
                
                $warn_list = $this->db->queryrowset($sql);
                
                foreach ($warn_list AS $warn_item){
                    switch ($warn_item['language']) {
                        case 'ITA':
                            $header = 'Nuove operazioni Pirati d&rsquo;Orione';
                            $message = 'I servizi investigativi della Galassia sono concordi nell&rsquo;indicare un aumento anomalo delle attivit&agrave; di pirati nel settore <b>'.$spawn_item['sector_id'].'</b>, '.$QUADRANT_NAME[$quadrant_id].'.<br><br>Esercitare la massima cautela nell&rsquo;operare in tale settore.';
                            break;
                        default :
                            $header = 'Orion Syndicate Activity Report';
                            $message = 'The investigation service of the Galaxy agree in an abnormal increase in pirate activity in the sector <b>'.$spawn_item['sector_id'].'</b>, '.$QUADRANT_NAME[$quadrant_id].'.';                            
                            break;
                    }
                    $this->MessageUser(0, $warn_item['user_id'], $header, $message);                    
                }
            }
            
            $this->RefillOrionSpacedock($orion_q['planet_id'], $orion_q['planet_type'],$orion_temp_ids);
            
            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20*24*7).' WHERE planet_id = '.$orion_q['planet_id'];
            $this->sdl->log('SQL E '.$sql, TICK_LOG_FILE_NPC);
            $this->db->query($sql);                        
        }        
        
        $this->sdl->finish_job('Orion Spawn Control', TICK_LOG_FILE_NPC);
        
        $this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
    }
    
    function CreatePirateFleet($planet_id, $ship_id) {
        $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, alert_phase, move_id, n_ships, npc_timeout)
            VALUES ("Pirate Fleet", '.ORION_USERID.', '.$planet_id.', '.ALERT_PHASE_RED.', 0, 1, 0)';
        
        if(!$this->db->query($sql))
            return $this->sdl->log('<b>Error:</b> Could not insert new fleet data',TICK_LOG_FILE_NPC);
        
        $fleet_id = $this->db->insert_id();
        
        if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty',TICK_LOG_FILE_NPC);
        
        $sql = 'UPDATE ships SET fleet_id = '.$fleet_id.', last_refit_time = '.time().' WHERE ship_id = '.$ship_id;
        
        if(!$this->db->query($sql))
            return $this->sdl->log('<b>Error:</b> Could not insert new fleet data',TICK_LOG_FILE_NPC);

        $this->sdl->log('Ship '.$ship_id.' from the planet '.$planet_id.' has been moved in the fleet '.$fleet_id,TICK_LOG_FILE_NPC);
    }
    
    function DespawnOrionFleet($fleet_id) {
        
        $sql = 'DELETE FROM ships WHERE fleet_id = '.$fleet_id.' AND user_id = '.ORIOND_USERID;        
        
        $this->db->query($sql);
        
        $sql = 'DELETE FROM ship_fleets WHERE fleet_id = '.$fleet_id.' AND user_id = '.ORION_USERID;
        
        $this->db->query($sql);
    }
    
    function RefillOrionSpacedock($planet_id, $planet_type, $orion_temp_ids) {
        
        for($i = 1; $i < 6; $i++) {
            $sql = 'SELECT max_unit_1, max_unit_2, max_unit_3, max_unit_4, rof, rof2, max_torp,
                           value_5, value_9
                    FROM `ship_templates` WHERE `id` = '.$orion_temp_ids['orion_tmp_'.$i];
            if(($orion[$i] = $this->db->queryrow($sql)) === false)
                return $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql,TICK_LOG_FILE_NPC);
        }
        
        $num = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
        
        switch ($planet_type) {
            case 'e':
            case 'f':
            case 'g':
                $num[2]=rand(1,3); // Corsair
                $num[4]=rand(1,2); // Marauder
                $num[5]=rand(0,1); // Slavemaster
                $this->sdl->log('Class E - F - G planet refilling spacedock',TICK_LOG_FILE_NPC);
                break;
            case 'k':
            case 'l':
                $num[1]=rand(1,4); // Lookout
                $num[3]=rand(1,3); // Predator
                $num[5]=rand(0,1); // Slavemaster
                $this->sdl->log('Class K - L planet refilling spacedock',TICK_LOG_FILE_NPC);
                break;
            case 'm':
            case 'o':
            case 'p':
                $num[2]=rand(1,4); // Corsair
                $num[4]=rand(1,2); // Marauder
                $num[5]=rand(0,1); // Slavemaster                
                $this->sdl->log('Class M - O - P planet refilling spacedock',TICK_LOG_FILE_NPC);
                break;
            default :
                $num[1]=rand(1,4); // Lookout                                
                $num[3]=rand(1,3); // Predator
                break;
        }
        
        for($i = 1; $i < 6; $i++) {
            $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
                                       rof, rof2, torp, unit_1, unit_2, unit_3, unit_4)
                    VALUES (-'.$planet_id.', '.ORION_USERID.', '.$orion_temp_ids['orion_tmp_'.$i].', 10,
                            '.$orion[$i]['value_5'].', '.time().', '.$orion[$i]['rof'].', '.$orion[$i]['rof2'].', '.$orion[$i]['max_torp'].',
                            '.$orion[$i]['max_unit_1'].', '.$orion[$i]['max_unit_2'].',
                            '.$orion[$i]['max_unit_3'].', '.$orion[$i]['max_unit_4'].')';
            
            for ($ii = 0; $ii < $num[$i]; $ii++) {
                if(!$this->db->query($sql)) {
                    return $this->sdl->log('<b>Error:</b> Could not insert new ships data',TICK_LOG_FILE_NPC);
                }
            }            
        }

        $this->sdl->log('Planet Spacedock '.$planet_id.': refilled with '.$num[1].', '.$num[2].', '.$num[3].', '.$num[4].', '.$num[5].' ship(s)',TICK_LOG_FILE_NPC);        
    }
    
    function SendOrionFleet($ACTUAL_TICK,$INTER_SYSTEM_TIME,$fleet_id,$dest,$action) {

        if($action == 0) $action = 11;

        $sql = 'SELECT f.fleet_id, f.user_id, f.n_ships, f.planet_id AS start,
                   s1.system_id AS start_system_id, s1.system_global_x AS start_x, s1.system_global_y AS start_y,
                   s2.system_id AS dest_system_id, s2.system_global_x AS dest_x, s2.system_global_y AS dest_y
            FROM (ship_fleets f)
            INNER JOIN (planets p1) ON p1.planet_id = f.planet_id
            INNER JOIN (starsystems s1) ON s1.system_id = p1.system_id
            INNER JOIN (planets p2) ON p2.planet_id = '.$dest.'
            INNER JOIN (starsystems s2) ON s2.system_id = p2.system_id
            WHERE f.fleet_id = '.$fleet_id;

        if(($fleet = $this->db->queryrow($sql)) === false) {
                $this->sdl->log('Could not query fleet '.$fleet_id.' data',TICK_LOG_FILE_NPC);
                return false;
        }

        if(empty($fleet['fleet_id'])) {
                $this->sdl->log('Orion fleet for mission does not exist, already moving?'.$sql,TICK_LOG_FILE_NPC);
                return false;
        }

        if($fleet['start_system_id'] == $fleet['dest_system_id']) {
                $distance = $velocity = 0;
                $min_time = $INTER_SYSTEM_TIME + 1;
        }
        else {
                $distance = get_distance(array($fleet['start_x'], $fleet['start_y']), array($fleet['dest_x'], $fleet['dest_y']));
                $velocity = warpf(10);
                $min_time = ceil( ( ($distance / $velocity) / TICK_DURATION ) ) + 1;
        }

        if($min_time < 1) $min_time = 1;

        $sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
                 VALUES ('.$fleet['user_id'].', 0, 0, '.$fleet['start'].', '.$dest.', '.$distance.', '.$distance.', '.($velocity * TICK_DURATION).', '.$ACTUAL_TICK.', '.($ACTUAL_TICK + $min_time).', '.$fleet['n_ships'].', '.$action.', "")';

        if(!$this->db->query($sql)) {
                $this->sdl->log('Could not insert new movement data',TICK_LOG_FILE_NPC);
                return false;
        }

        $new_move_id = $this->db->insert_id();

        if(empty($new_move_id)) {
                $this->sdl->log('Could not send Borg fleet $new_move_id = empty',TICK_LOG_FILE_NPC);
                return false;
        }

        $sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$new_move_id.' WHERE fleet_id = '.$fleet['fleet_id'];

        if(!$this->db->query($sql)) {
                $this->sdl->log('Could not update Orion fleet data',TICK_LOG_FILE_NPC);
                return false;
        }

        return true;
    }    
        
    function SpawnMajorPirateFleet($ACTUAL_TICK, $system_id, $orion_temp_ids)
    {
        
        /*
        $sql = 'SELECT orion_tmp_1, orion_tmp_2, orion_tmp_3, orion_tmp_4, orion_tmp_5 FROM config WHERE config_set_id = 0';
        if(!($q_tmp = $this->db->queryrow($sql))) {
            return $this->sdl->log('Could not read config data! SKIP'.$sql,TICK_LOG_FILE_NPC);
        }
        */

        for($i = 1; $i < 6; $i++) {
            $sql = 'SELECT max_unit_1, max_unit_2, max_unit_3, max_unit_4, rof, rof2, max_torp,
                           value_5, value_9
                    FROM `ship_templates` WHERE `id` = '.$orion_temp_ids['orion_tmp_'.$i];
            if(($orion[$i] = $this->db->queryrow($sql)) === false)
                return $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql,TICK_LOG_FILE_NPC);
        }
        
        $sql = 'SELECT planet_id FROM planets WHERE system_id = '.$system_id;
        if(!($q_planets = $this->db->queryrowset($sql))) {
            return $this->sdl->log('Could not read planet ids! SKIP'.$sql,TICK_LOG_FILE_NPC);
        }        
        
        $planet = $q_planets[array_rand($q_planets)];

        $num[1]=rand(80,100); // Lookout
        $num[2]=rand(60,75);  // Corsair
        $num[3]=rand(60,100); // Predator
        $num[4]=rand(65,75);  // Marauder
        $num[5]=rand(20,30);  // Slavemaster
        
        $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, alert_phase, move_id, n_ships, npc_timeout)
            VALUES ("Pirate Fleet", '.ORION_USERID.', '.$planet['planet_id'].', '.ALERT_PHASE_RED.', 0, '.($num[1]+$num[2]+$num[3]+$num[4]+$num[5]).', '.($ACTUAL_TICK + 20*24*1.5).')';
        if(!$this->db->query($sql))
            return $this->sdl->log('<b>Error:</b> Could not insert new fleet data',TICK_LOG_FILE_NPC);
        
        $fleet_id = $this->db->insert_id();

        if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty',TICK_LOG_FILE_NPC);
        
        for($i = 1; $i < 6; $i++) {
            $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
                                       rof, rof2, torp, unit_1, unit_2, unit_3, unit_4)
                    VALUES ('.$fleet_id.', '.ORION_USERID.', '.$orion_temp_ids['orion_tmp_'.$i].', 120,
                            '.$orion[$i]['value_5'].', '.time().', '.$orion[$i]['rof'].', '.$orion[$i]['rof2'].', '.$orion[$i]['max_torp'].',
                            '.$orion[$i]['max_unit_1'].', '.$orion[$i]['max_unit_2'].',
                            '.$orion[$i]['max_unit_3'].', '.$orion[$i]['max_unit_4'].')';
            
            for ($ii = 0; $ii < $num[$i]; $ii++) {
                if(!$this->db->query($sql)) {
                    return $this->sdl->log('<b>Error:</b> Could not insert new ships data',TICK_LOG_FILE_NPC);
                }
            }            
        }

        $sql = 'INSERT INTO scheduler_shipmovement (user_id, start, dest, move_begin, move_finish, n_ships, action_code, action_data)
                VALUES ('.ORION_USERID.', 0, '.$planet['planet_id'].', '.$ACTUAL_TICK.', '.($ACTUAL_TICK + 60).', '.($num[1]+$num[2]+$num[3]+$num[4]+$num[5]).', 11, "")';

        if(!$this->db->query($sql))
            return $this->sdl->log('<b>Error:</b> Could not insert new fleet movement data'.$sql,TICK_LOG_FILE_NPC);

        $move_id = $this->db->insert_id();            

        $sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$move_id.' WHERE fleet_id = '.$fleet_id;

        if(!$this->db->query($sql))
            return $this->sdl->log('<b>Error:</b> Could not update fleet with movement data'.$sql,TICK_LOG_FILE_NPC);        
    }
    
    function SpawnOrionPlanet($planet_id,$system_id) {
                        
        $sql = 'UPDATE planets
                        SET npc_last_action = '.($ACTUAL_TICK + rand(60,120)).',
                            planet_owner = '.ORION_USERID.',
                            planet_name = "Orion Cove #'.$planet_id.'",
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
                            research_3 = '.(rand(0,5)+2).',
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
                        WHERE planet_id = '.$planet_id;

        if(!$this->db->query($sql)) {
            $this->sdl->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
        }

        $sql = 'UPDATE starsystems SET system_orion_alert = 4 WHERE system_id = '.$system_id;

        if(!$this->db->query($sql)) {
            $this->sdl->log(MV_M_DATABASE, 'Could not update starsystem data!');
        }
        
        $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                VALUES ('.$planet_id.', '.ORION_USERID.', 0, '.ORION_USERID.', 0, '.time().', 25)';

        if(!$this->db->query($sql)) {
            $this->sdl->log(MV_M_DATABASE, 'Could not update planet details data!');
        }                
    }
    
    function OrionAttackNearFleet($ACTUAL_TICK, $fleet_id, $planet_id, $n_ships, $user_id) {
        $action_data = array((int)$user_id);

        $sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
                VALUES ('.ORION_USERID.', 0, 0, '.$planet_id.', '.$planet_id.', 0, 0, 0, '.$ACTUAL_TICK.', '.($ACTUAL_TICK + 2).', '.$n_ships.', 51, "'.serialize($action_data).'")';

        if(!$this->db->query($sql)) {
            $this->sdl->log('Orion fleet'.$fleet_id.' cannot attack!!!: '.$sql,TICK_LOG_FILE_NPC);
            return false;
        }

        $new_move_id = $this->db->insert_id();

        $sql = 'UPDATE ship_fleets
                SET planet_id = 0,
                    npc_idles = 0,
                    move_id = '.$new_move_id.'
                WHERE fleet_id IN ('.$fleet_id.')';

        if(!$this->db->query($sql)) {
            $this->sdl->log('Orion fleet'.$fleet_id.' cannot update move data!!!: '.$sql,TICK_LOG_FILE_NPC);
            return false;
        }

        return true;
    }    
}


?>
