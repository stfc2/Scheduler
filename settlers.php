<?php
/*
    This file is part of STFC.it
    Copyright 2008-2013 by Andrea Carolfi (carolfi@stfc.it) and
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

/* 25. June 2008
  @Author: Delogu - Carolfi
  @Action: Thanks to Delogu they're definitely more active now! ^^
*/

// ########################################################################################
// ########################################################################################
// Startconfig of Settlers
class Settlers extends NPC
{
    function refresh_planet_to_serve(&$planet)
    {
        $planet = $this->db->queryrow('SELECT * FROM planets WHERE planet_id = '.$planet['planet_id']);
    }
    
    function delay_activity($planet,$actual)
    {
        $sql = 'UPDATE planets SET npc_last_action = '.($actual + 20).' WHERE planet_id = '.$planet['planet_id'];
        $this->sdl->log('SQL E '.$sql, TICK_LOG_FILE_NPC);
        $this->db->query($sql);        
    }
    
    function besty_capital($user_id){
        $sql = 'SELECT user_capital from user WHERE user_id = '.$user_id;
        $res = $this->db->queryrow($sql);

        return $res['user_capital'];
    }

    function raise_defense($planet,$mood,$actual)
    {
        global $TECH_DATA, $RACE_DATA;
        
        if($planet['research_3'] >= 9) {
            $this->sdl->log('SETL - RAISE_DEFENSE: Defences are already at top!',TICK_LOG_FILE_NPC);
            return;
        }
                
        if($mood < 4) {
            $this->sdl->log('SETL - RAISE_DEFENSE: mood '.$mood.' is too low for raising defences!',TICK_LOG_FILE_NPC);
            return;
        }

        $tech_data = 2;        
        
        $sql = 'SELECT COUNT(*) as counter 
                FROM scheduler_research WHERE planet_id = '.$planet['planet_id'].'
                AND research_id = '.$tech_data;        
        $queue_check=$this->db->queryrow($sql);
        
        if(isset($queue_check['counter']) && $queue_check['counter'] > 0) {
            $this->sdl->log('SETL - RAISE_DEFENSE: Defences are already being raised by someone else!',TICK_LOG_FILE_NPC);
            return;
        }
        
        $tech_reward = array(5,7,7,7,10,10,10,12,12);                
        $time=0;

        $time=$TECH_DATA[$tech_data][3]+ pow($planet['research_'.($tech_data+1)],$TECH_DATA[$tech_data][4]);
        $time*=$RACE_DATA[13][4]; // Settlers Race is 13

        $time/=100;
        $time*=(100-2*($planet['research_4']*$RACE_DATA[13][20])); // Settlers Race is 13
        if ($time<1) $time=1;
        $time=round($time,0);

        $sql = 'INSERT INTO scheduler_research (research_id,planet_id,player_id,research_finish,research_start)
                VALUES ("'.$tech_data.'","'.$planet['planet_id'].'","'.INDEPENDENT_USERID.'","'.($actual+$time).'","'.$actual.'")';

        if(!$this->db->query($sql)) {
            $this->sdl->log('SETL - RAISE_DEFENSE: Could not write research record for settlers research!'.$sql,TICK_LOG_FILE_NPC);
            return;
        }        
        
        $tech_value = $tech_reward[$planet['research_'.($tech_data+1)]];
        
        $sql='INSERT INTO settlers_relations SET planet_id = '.$planet['planet_id'].', user_id = '.INDEPENDENT_USERID.',timestamp = '.time().',log_code = '.LC_SUP_DFNS.', mood_modifier = '.$tech_value;
        
        if(!$this->db->query($sql)) {
            $this->sdl->log('SETL - RAISE_DEFENSE: Could not write relation record for settlers research!'.$sql,TICK_LOG_FILE_NPC);
        }        
    }
    
    function ferengi_presidio($planet_id, $user_id=0) {
        if($user_id === 0) {return false;}

        $sql = 'SELECT timestamp FROM settlers_events WHERE event_code = 124 AND event_status = 0 AND user_id = '.$user_id.' AND planet_id = '.$planet_id;
        $res = $this->db->queryrow($sql);

        if($this->db->num_rows() > 0) {return true;}

        return false;
    }

    function ferengi_delegatio($planet_id,$user_id=0) {
        $sql = 'SELECT user_id FROM settlers_events WHERE event_code = 124 AND event_status = 0 AND user_id = '.$user_id.' AND planet_id = '.$planet_id;
        $res = $this->db->queryrow($sql);

        if($this->db->num_rows() > 0) {return true;}

        return -1;
    }

    function orbital_rebalance($planet_id, $num) {
        $sql = 'SELECT user_id, mood_modifier FROM settlers_relations WHERE planet_id = '.$planet_id.' AND log_code = '.LC_MIL_ORBITAL;
        $res = $this->db->queryrowset($sql);
        foreach ($res AS $res_item) {
            $defender_list[$res_item['user_id']] = array('mood_modifier' => $res_item['mood_modifier'], 'hits' => 0);
            $this->sdl->log('SETL - ORBITAL REBALANCE - User '.$res_item['user_id'].' has '.$res_item['mood_modifier'].' orbitals', TICK_LOG_FILE_NPC);
        }
        if($this->db->num_rows() > 0) {
            foreach($defender_list as $defender_id => $defender_item) {
                $this->sdl->log('SETL - ORBITAL REBALANCE - User '.$defender_id.' has a mood modifier of '.$defender_item['mood_modifier'].' and # of hits of '.$defender_item['hits'], TICK_LOG_FILE_NPC);                
                for($i = 0; $i < $defender_item['mood_modifier']; $i++) {
                    $hits_array[] = array($defender_id, 0);
                }

            }
            $i = 0;
            while($i < $num) {
                shuffle($hits_array);
                if($hits_array[$i][1] == 0) {
                    $hits_array[$i][1] = 1;
                    $defender_list[$hits_array[$i][0]]['hits']++;
                    $i++;
                }
            }
            foreach ($defender_list as $defender_id => $defender_item) {
                if($defender_item['hits'] == 0) continue;
                $sql = 'UPDATE settlers_relations SET mood_modifier = '.($defender_item['mood_modifier'] - $defender_item['hits']).' WHERE planet_id = '.$planet_id.' AND user_id = '.$defender_id.' AND log_code = '.LC_MIL_ORBITAL;
                $this->db->query($sql);
                $this->sdl->log('SETL - ORBITAL REBALANCE - User '.$defender_id.' has a new value of '.($defender_item['mood_modifier'] - $defender_item['hits']).' orbitals', TICK_LOG_FILE_NPC);
            }
        }
        else {
                $sql = 'UPDATE settlers_relations SET mood_modifier = '.($res[0]['mood_modifier'] - $num).' WHERE planet_id = '.$planet_id.' AND user_id = '.$res[0]['user_id'].' AND log_code = '.LC_MIL_ORBITAL;
                $this->db->query($sql);
                $this->sdl->log('SETL - ORBITAL REBALANCE - User '.$res[0]['user_id'].' has a new value of '.($res[0]['mood_modifier'] - $num).' orbitals', TICK_LOG_FILE_NPC);                
        }

        return;
    }
    
    public function Install($log = INSTALL_LOG_FILE_NPC)
    {
        $this->sdl->start_job('Mayflower basic system', $log);

        // We give the bot some data so that it is also Registered
        $this->bot = $this->db->queryrow('SELECT * FROM user WHERE user_id = '.INDEPENDENT_USERID);

        //Check whether the bot already lives
        if(empty($this->bot['user_id'])) {
            $this->sdl->log('We need to create TheSettlers!', $log);

            $sql = 'INSERT INTO user (user_id, user_auth_level, user_name, user_loginname, user_password,
                                      user_email, user_active, user_race, user_gfxpath, user_skinpath,
                                    user_registration_time, user_registration_ip,
                                          user_birthday, user_gender, plz, country, user_enable_sig,
                                          user_message_sig, user_signature, user_notepad, user_options, message_basement)
                         VALUES ('.INDEPENDENT_USERID.', '.STGC_BOT.', "Coloni(NPC)", "SettlersBot", "'.md5("settlers").'",
                                 "settlers@stfc.it", 1, 13, "'.DEFAULT_GFX_PATH.'", "skin1/", '.time().', "127.0.0.1",
                                 "25.06.2008", "", 16162 , "IT", 1,
                                 "",  "", "", "", "")';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
            }
        }

        //Check for Settlers cargo ship definition
        $settler_ship_id = $this->db->queryrow('SELECT settler_tmp_1 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_1'])) {
               $this->sdl->log('Writing Settler Cargo Ship!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, rof2, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Transporter","Settlers Cargo",13,1,0,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "15","0","0","5","20",
                             "2","1","5","5","3.4",
                             "5","0","10","4","0",
                             "4250","5800","4640","105","2","1",
                             "56","0","0","1",
                             "56","0","0","1",
                             720, 1, 0, 0)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_1 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        //Check for Settlers cruiser ship definition
        $settler_ship_id = (int)$this->db->queryrow('SELECT settler_tmp_2 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_2'])) {
               $this->sdl->log('Writing Settler Cruiser Ship!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, rof2, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Centaur","Settlers Cruiser",13,7,2,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "100","200","16","476","665",
                             "6","12","26","1","6.5",
                             "34","0","160","140","0",
                             "54929","54929","43719","1121","15","4",
                             "100","0","0","2",
                             "120","20","20","4",
                             1121, 6, 4, 85)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_2 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        //Check for Settlers battleship ship definition
        $settler_ship_id = (int)$this->db->queryrow('SELECT settler_tmp_3 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_3'])) {
               $this->sdl->log('Writing Settler Heavy Cruiser!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, rof2, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Achilles","Settlers Battleship",13,9,3,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "100","210","100","1535","1720",
                             "6","14","16","1","6.5",
                             "30","0","180","160","0",
                             "123872","123872","98592","2528","20","8",
                             "150","0","0","3",
                             "170","15","20","5",
                             2528, 15, 8, 250)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_3 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        //Check for Settlers Orbital Cannon definition
        $settler_ship_id = (int)$this->db->queryrow('SELECT settler_tmp_4 FROM config WHERE config_set_id = 0');
        if(empty($settler_ship_id['settler_tmp_4'])) {
               $this->sdl->log('Writing Settler Orbital!', $log);

               $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, max_torp)
                     VALUES ("'.INDEPENDENT_USERID.'","'.time().'","Orbitaler","Settlers PlanetaryShield",13,12,3,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "750","750","0","1000","100",
                             "35","25","0","0","0",
                             "35","0","33","33","0",
                             "50400","50400","40320","500","0","0",
                             "0","0","0","0",
                             "0","0","0","0",
                             480, 3, 400)';

               if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
               }

               $template_id = $this->db->insert_id();
               $sql = 'UPDATE config SET settler_tmp_4 = '.$template_id.' WHERE config_set_id = 0';
               $this->db->query($sql);
        }

        $this->sdl->finish_job('Mayflower basic system', $log);
    }

    public function Execute($debug=0)
    {
        global $ACTUAL_TICK,$cfg_data,$RACE_DATA, $ACADEMY_MAJOR_LIST, $ACADEMY_MINOR_LIST, $MINE_MAJOR_LIST, $MINE_MINOR_LIST;
        $starttime = ( microtime() + time() );

        $this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
            '<b>Starting Settlers Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

        // Update BOT user ID
        $this->bot['user_id'] = INDEPENDENT_USERID;

        // ########################################################################################
        // ########################################################################################
        // Messages answer
        $messages=array('Bot system.','Bot system.','Se desiderate avere contatti con noi, dovrete scendere direttamente su uno dei nostri pianeti.<br><br>A presto.');
        $titles=array('--','--','Grazie per averci contattato.');

        $this->ReplyToUser($titles,$messages);
        // ########################################################################################
        // ########################################################################################
        // Read Logbook

        $this->ReadLogbook();
        // ########################################################################################
        // ########################################################################################
        // Ok, only three (3) Settlers' planets per run are going to wake up. More planets means more overhead.

        
        $this->sdl->start_job('Mayflower Parliament Session', TICK_LOG_FILE_NPC);
        
        $sql = 'SELECT user_id, COUNT(planet_id) AS planets_taken FROM settlers_relations WHERE log_code = 34 GROUP BY user_id';
        
        if($res = $this->db->queryrowset($sql)) {
            foreach($res AS $criminal){
                if($criminal['planets_taken'] > 4) {
                    $sql = 'SELECT uf_id FROM user_felony WHERE user1_id = '.INDEPENDENT_USERID.' AND user2_id = '.$criminal['user_id'];
                    $ud_exists = $this->db->queryrow($sql);
                    if(!isset($ud_exists['uf_id']) || empty($ud_exists['uf_id'])) {
                        $sql = 'INSERT INTO user_felony SET user1_id = '.INDEPENDENT_USERID.', user2_id = '.$criminal['user_id'].', date = '.time();
                        $this->sdl->log('SQL P.1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);                            
                    }
                }
            }
        }
        
        $this->sdl->finish_job('Mayflower Parliament Session', TICK_LOG_FILE_NPC);
        
        $this->sdl->start_job('Mayflower Planets Building Control', TICK_LOG_FILE_NPC);

        $sql='SELECT * FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' AND npc_last_action < '.$ACTUAL_TICK.' ORDER BY npc_last_action ASC LIMIT 0, 3';

        if(($setpoint = $this->db->query($sql)) === false)
        {
            $this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
        }
        else
        {
            $_already_done = false;
            while($planet_to_serve = $this->db->fetchrow($setpoint))
            {   
                $future_lvl = [0,0,0,0,0,0,0,0,0,0,0,0,0];
                $sched_build = $this->db->queryrowset('SELECT installation_type FROM scheduler_instbuild WHERE planet_id = '.$planet_to_serve['planet_id']);
                foreach ($sched_build AS $sched_item) {
                    $future_lvl[$sched_item['installation_type']]++;
                }
                
                if(($planet_to_serve['building_1'] + $future_lvl[0]) < 9)
                {
                    /*
                    $sql = 'UPDATE planets SET building_1 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.1 '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     * 
                     */
                    $res = $this->StartBuild($ACTUAL_TICK,0,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                }
                /*
                if($planet_to_serve['building_5'] < 6)
                {
                    ---
                    $sql = 'UPDATE planets SET building_5 = 6, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                    --- 
                    $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {$res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);}
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {continue;}                    
   
                }
                 * 
                 */
                if(($planet_to_serve['building_2'] + $future_lvl[1]) < 9)
                {
                    /*
                    $sql = 'UPDATE planets SET building_2 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.2'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     * 
                     */
                    $res = $this->StartBuild($ACTUAL_TICK,1,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue; 
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                }
                if(($planet_to_serve['building_3'] + $future_lvl[2]) < 9)
                {
                    /*
                    $sql = 'UPDATE planets SET building_3 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.3'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     * 
                     */
                    $res = $this->StartBuild($ACTUAL_TICK,2,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);                        
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                }

                if(($planet_to_serve['building_4'] + $future_lvl[3]) < 9)
                {
                    /*
                    $sql = 'UPDATE planets SET building_4 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.4'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     * 
                     */
                    $res = $this->StartBuild($ACTUAL_TICK,3,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                }
                if(($planet_to_serve['building_12'] + $future_lvl[11]) < 9)
                {
                    /*
                    $sql = 'UPDATE planets SET building_12 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 1.12'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     * 
                     */
                    $res = $this->StartBuild($ACTUAL_TICK,11,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                }
                // At this point, having rebuilt HQ and mines, Settlers automatically gains level 9 Academy.
                if(($planet_to_serve['building_6'] + $future_lvl[5]) < 9)
                {
                    /*
                    $sql = 'UPDATE planets SET building_6 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 2'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     * 
                     */
                    $res = $this->StartBuild($ACTUAL_TICK,5,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                }
                // Now the Spacedock
                if(($planet_to_serve['building_7'] + $future_lvl[6]) < 3)
                {
                    $res = $this->StartBuild($ACTUAL_TICK,6,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                    /*
                    $sql = 'UPDATE planets SET building_7 = 3, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 3'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                     * 
                     */

                }
                // Now the Spaceyard
                if(($planet_to_serve['building_8'] + $future_lvl[7]) < 1)
                {
                    $res = $this->StartBuild($ACTUAL_TICK,7,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        continue;                        
                    }
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                    /*
                    $sql = 'UPDATE planets SET building_8 = 1, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 4'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);                    
                     * 
                     */
                }
                // Light orbital defense
                if((($planet_to_serve['building_10'] + $future_lvl[9] + $planet_to_serve['research_3']) < (14 + $planet_to_serve['research_3'])) && $planet_to_serve['npc_next_delivery'] > $ACTUAL_TICK)
                {
                    $res = $this->StartBuild($ACTUAL_TICK,9,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);             
                        $this->refresh_planet_to_serve($planet_to_serve);                        
                    } 
                    /*
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                     * 
                     */
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                    /*
                    $sql = 'UPDATE planets SET building_10 = '.(14 + $planet_to_serve['research_3']).', npc_last_action = '.($ACTUAL_TICK + 10).', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 5'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     */
                }
                // Heavy orbital defense
                if((($planet_to_serve['building_13'] + $future_lvl[12] + $planet_to_serve['research_3']) < (14 + $planet_to_serve['research_3'])) && $planet_to_serve['npc_next_delivery'] > $ACTUAL_TICK)
                {
                    $res = $this->StartBuild($ACTUAL_TICK,12,$planet_to_serve);
                    if($res == BUILD_ERR_ENERGY) {
                        $res = $this->StartBuild($ACTUAL_TICK,4,$planet_to_serve);
                        $this->refresh_planet_to_serve($planet_to_serve);                        
                    }  
                    /*
                    elseif($res == BUILD_ERR_QUEUE || $res == BUILD_ERR_RESOURCES) {
                        $this->delay_activity($planet_to_serve,$ACTUAL_TICK);
                        continue;
                    }
                     * 
                     */
                    elseif($res == BUILD_SUCCESS) {
                        $this->refresh_planet_to_serve($planet_to_serve);
                    }
                    /*
                    $sql = 'UPDATE planets SET building_13 = '.(14 + $planet_to_serve['research_3']).', npc_last_action = '.($ACTUAL_TICK + 30).', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 6'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                     */
                }
                
                // orbital_fleet_id = id from ship_fleets of orbital gun fleets defending planet; -1 = no fleet
                // orbital_num = number of orbital gun in the fleet
                // orbital_by_player = number of existing guns made by players
                $sql='SELECT fleet_id FROM ship_fleets WHERE fleet_name LIKE "Orbital'.$planet_to_serve['planet_id'].'"';
                $od_q = $this->db->queryrow($sql);
                if(isset($od_q['fleet_id']) && !empty($od_q['fleet_id'])) {
                    $planet_to_serve['orbital_fleet_id'] = $od_q['fleet_id'];
                    $sql='SELECT COUNT(*) AS counter FROM ships WHERE fleet_id = '.$planet_to_serve['orbital_fleet_id'];
                    $res=$this->db->queryrow($sql);
                    $planet_to_serve['orbital_num'] = $res['counter'];
                    $sql='SELECT SUM(mood_modifier) AS counter FROM settlers_relations WHERE planet_id = '.$planet_to_serve['planet_id'].' AND log_code = '.LC_MIL_ORBITAL;
                    $res=$this->db->queryrow($sql);
                    $planet_to_serve['orbital_by_player'] = $res['counter'];
                    if($planet_to_serve['orbital_by_player'] > $planet_to_serve['orbital_num']) {
                        $this->sdl->log('orbital fleet id = '.$planet_to_serve['orbital_fleet_id'].', orbital num = '.$planet_to_serve['orbital_num'].', orbital_by_player = '.$planet_to_serve['orbital_by_player'],TICK_LOG_FILE_NPC);
                        $this->sdl->log('Call orbital_rebalance for planet '.$planet_to_serve['planet_id'].' with a num of '.($planet_to_serve['orbital_by_player'] - $planet_to_serve['orbital_num']),TICK_LOG_FILE_NPC);
                        $this->orbital_rebalance($planet_to_serve['planet_id'], ($planet_to_serve['orbital_by_player'] - $planet_to_serve['orbital_num']));
                        $this->sdl->log('Back from orbital_rebalance for planet '.$planet_to_serve['planet_id'],TICK_LOG_FILE_NPC);
                    }
                }
                else {
                    $planet_to_serve['orbital_fleet_id'] = 0;
                    $planet_to_serve['orbital_num'] = 0;
                    $planet_to_serve['orbital_by_player'] = 0;
                }
                
                // Are there enough workers in the mines?
                if($planet_to_serve['workermine_1'] < 1000 || $planet_to_serve['workermine_2'] < 1000 || $planet_to_serve['workermine_3'] < 1000)
                {
                    $sql = 'UPDATE planets SET workermine_1 = 1000, workermine_2 = 1000, workermine_3 = 1000, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 7'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // Are there enough security troops?
                $troops_check = ($planet_to_serve['unit_1']*2) + ($planet_to_serve['unit_2']*3) + ($planet_to_serve['unit_3']*4) + ($planet_to_serve['unit_4']*4);
                $troops_to_train = $planet_to_serve['min_security_troops'] - $troops_check;
                $modulo = $troops_to_train % 4;
                $unit3_to_train = $unit2_to_remove = $unit1_to_remove= 0;
                if($modulo == 0) {
                    $unit3_to_train = $troops_to_train / 4;
                    $unit2_to_remove = 0;
                    $unit1_to_remove = 0;
                }
                elseif ($planet_to_serve['unit_2'] > 0 && (($planet_to_serve['unit_2']*3) % 4) != 0) {
                    $unit3_to_train = round($troops_to_train / 4) + 1;
                    $unit2_to_remove = 1;
                    $unit1_to_remove = 0;
                }
                elseif ($planet_to_serve['unit_1'] > 0 && (($planet_to_serve['unit_1']*2) % 4) != 0) {
                    $unit3_to_train = round($troops_to_train / 4) + 1;
                    $unit2_to_remove = 0;
                    $unit1_to_remove = 1;
                }
                else {
                    $unit3_to_train = round($troops_to_train / 4);
                }
                if($troops_to_train > 0)
                {
                    $sql = 'UPDATE planets SET unit_1 = unit_1 - '.$unit1_to_remove.', unit_2 = unit_2 - '.$unit2_to_remove.', unit_3 = unit_3 + '.$unit3_to_train.', npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL 7.1'.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // Settlers looking for their own autonomy!!!
                if($planet_to_serve['best_mood_user'] != INDEPENDENT_USERID) {
                    $sql = 'SELECT log_code, SUM(mood_modifier) as mood_value FROM settlers_relations WHERE planet_id = '.$planet_to_serve['planet_id'].' AND user_id = '.INDEPENDENT_USERID.' GROUP BY log_code';
                    $q_s_m = $this->db->queryrowset($sql);

                    $autonomy_basevalue = $autonomy_terror = $autonomy_dissent = $autonomy_crime = $autonomy_survival = $autonomy_welfare = $autonomy_egemony = $autonomy_tecnology = $autonomy_autharchy = 0;

                    foreach ($q_s_m AS $q_s_item) {
                        switch ($q_s_item['log_code']) {
                            case 28:
                                $autonomy_terror = $q_s_item['mood_value'];
                                break;
                            case 29:
                                $autonomy_dissent = $q_s_item['mood_value'];
                                break;
                            case 35:
                                $autonomy_crime = $q_s_item['mood_value'];
                                break;
                            case 100:
                                $autonomy_basevalue = $q_s_item['mood_value'];
                                break;
                            case 101:
                                $autonomy_survival = $q_s_item['mood_value'];
                                break;
                            case 102:
                                $autonomy_welfare = $q_s_item['mood_value'];
                                break;
                            case 103:
                                $autonomy_egemony = $q_s_item['mood_value'];
                                break;
                            case 104:
                                $autonomy_tecnology = $q_s_item['mood_value'];
                                break;
                            case 105:
                                $autonomy_autharchy = $q_s_item['mood_value'];
                                break;
                        }
                    }

                    $autonomy_modifier = 1 + ($autonomy_basevalue - $autonomy_terror) + 
                                             (4 * $planet_to_serve['research_1'] - $autonomy_crime) + 
                                             (4 * $planet_to_serve['research_2'] - $autonomy_crime) + 
                                             (4 * $planet_to_serve['research_3'] - ($autonomy_crime/4)) + 
                                             (4 * $planet_to_serve['research_4'] - ($autonomy_crime/2) - $autonomy_dissent) + 
                                             (4 * $planet_to_serve['research_5'] - $autonomy_crime);

                    $this->sdl->log('Autonomy modifier planet '.$planet_to_serve['planet_id'].' = '.$autonomy_modifier, TICK_LOG_FILE_NPC);

                    $autonomy_check = rand(1,720) + $autonomy_modifier;

                    if ($autonomy_check >= 720) {
                        $this->sdl->log('Autonomy strike!!! value = '.$autonomy_check, TICK_LOG_FILE_NPC);
                        $settlers_autonomy_chance_array = array (
                            'autonomy'  => 20,
                            'survival'  => ($autonomy_survival  == 64 ? 0 : 15 + (5 + $planet_to_serve['research_1'])),
                            'welfare'   => ($autonomy_welfare   == 64 ? 0 : 15 + (5 + $planet_to_serve['research_2'])),
                            'egemony'   => 20,
                            'tecnology' => ($autonomy_tecnology == 64 ? 0 : 15 + (5 + $planet_to_serve['research_4'])),
                            'autharchy' => ($autonomy_autharchy == 64 ? 0 : 15 + (5 + $planet_to_serve['research_5']))
                        );

                        $mood_bonus = array(0,0,0,0,0,0,1,2,4);

                        $autonomy_array = array();

                        foreach($settlers_autonomy_chance_array as $event => $probability) {
                            for($i = 0; $i < $probability; ++$i) {
                                $autonomy_array[] = $event;
                            }
                        }                

                        $autonomy_event = $autonomy_array[array_rand($autonomy_array)];

                        switch ($autonomy_event) {
                            case 'autonomy':
                                $log_code = 100;
                                $mood_actual = $autonomy_basevalue;
                                $this->raise_defense($planet_to_serve,($mood_actual+1),$ACTUAL_TICK);
                                break;
                            case 'survival':
                                $log_code = 101;
                                $mood_actual = $autonomy_survival;
                                $mood_modifier = $mood_bonus[$planet_to_serve['research_1']];
                                break;
                            case 'welfare':
                                $log_code = 102;
                                $mood_actual = $autonomy_welfare;
                                $mood_modifier = $mood_bonus[$planet_to_serve['research_2']];
                                break;
                            case 'egemony':
                                $log_code = 103;
                                $mood_actual = $autonomy_egemony;
                                $mood_modifier = $mood_bonus[$planet_to_serve['research_3']];
                                break;
                            case 'tecnology':
                                $log_code = 104;
                                $mood_actual = $autonomy_tecnology;
                                $mood_modifier = $mood_bonus[$planet_to_serve['research_4']];
                                break;
                            case 'autharchy':
                                $log_code = 105;
                                $mood_actual = $autonomy_autharchy;
                                $mood_modifier = $mood_bonus[$planet_to_serve['research_5']];
                                break;
                            default :
                                $this->sdl->log('Autonomy event anomaly = '.$autonomy_event, TICK_LOG_FILE_NPC);
                                break;
                        }

                        if($mood_actual == 0) {

                            $sql='INSERT INTO settlers_relations SET planet_id = '.$planet_to_serve['planet_id'].', user_id = '.INDEPENDENT_USERID.',timestamp = '.time().',log_code = '.$log_code.', mood_modifier = 1';

                        }
                        else {
                            $new_mood = min(($log_code == 100 ? 80 : 64), (1 + $mood_actual + $mood_modifier));

                            $sql='UPDATE settlers_relations SET mood_modifier = '.$new_mood.', timestamp = '.time().'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'].' AND user_id = '.INDEPENDENT_USERID.' AND log_code = '.$log_code;
                        }

                        $this->sdl->log('SQL 8 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);

                    }
                }
                else
                {
                    $this->sdl->log('DEBUG: planet '.$planet_to_serve['planet_id'].' skip independency phase.', TICK_LOG_FILE_NPC);
                }
                // Check for best mood user felony

                $sql = 'SELECT date FROM user_felony WHERE user1_id = '.INDEPENDENT_USERID.' AND user2_id = '.$planet_to_serve['best_mood_user'];
                $res = $this->db->queryrow($sql);
                if(isset($res['date'])) {
                    $sql = 'DELETE FROM settlers_relations WHERE planet_id = '.$planet_to_serve['planet_id'].' AND user_id = '.$planet_to_serve['best_mood_user'];
                    $this->db->query($sql);
                }
                
                // Check for best mood!!!
                
                $sql = 'SELECT user_id, SUM(mood_modifier) AS mood FROM settlers_relations
                        WHERE planet_id = '.$planet_to_serve['planet_id'].' GROUP BY user_id ORDER BY timestamp ASC';
                $q_p_m = $this->db->queryrowset($sql);
                
                // $this->sdl->log('DEBUG CHECK MOOD A'.$planet_to_serve['best_mood'].' '.$planet_to_serve['best_mood_user'], TICK_LOG_FILE_NPC);

                $best = -1000;
                $best_id = 0;
                $newbest = false;
                
                foreach($q_p_m as $q_m) {
                    if($q_m['mood'] > 0 && $q_m['mood'] > $best) {
                        $newbest = true;
                        $best = $q_m['mood'];
                        $best_id = $q_m['user_id'];
                    }
                }
                if($newbest) {
                    $sql = 'UPDATE planets SET best_mood = '.$best.', best_mood_user = '.$best_id.' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->db->query($sql);
                }

                // $this->sdl->log('DEBUG CHECK MOOD B '.$best.' '.$best_id, TICK_LOG_FILE_NPC);
                
                if($best_id != $planet_to_serve['best_mood_user'])
                {
                    if($planet_to_serve['best_mood_alert']) {
                        $log_data = array($planet_to_serve['planet_id'],$planet_to_serve['planet_name'], 0, '', 100);
                        add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, 'Comunicazione prioritaria dalla colonia '.$planet_to_serve['planet_name'], $log_data);                        
                    }
                    $planet_to_serve['best_mood'] = $best;
                    $planet_to_serve['best_mood_user'] = $best_id;
                    $planet_to_serve['best_mood_alert'] = false;
                    $this->db->query('UPDATE planets SET best_mood_alert = FALSE, best_mood_planet = NULL WHERE planet_id = '.$planet_to_serve['planet_id']);
                }
                
                // Let's activate the Academy! Sadly, we have to set ALL the fields for clearing them
                if($planet_to_serve['unittrain_actual'] == 0 && in_array($planet_to_serve['planet_type'], $ACADEMY_MAJOR_LIST))
                {
                    if($troops_to_train < 1) $troops_to_train = 0;
                    $sql = 'UPDATE planets SET unittrainid_1 = 5, unittrainid_2 = 6,
                                   unittrainid_3 = 0, unittrainid_4 = 0,
                                   unittrainid_5 = 0, unittrainid_6 = 0,
                                   unittrainid_7 = 0, unittrainid_8 = 0,
                                   unittrainid_9 = 0, unittrainid_10 = 0,
                                   unittrainnumber_1 = 3, unittrainnumber_2 = 1,
                                   unittrainnumber_3 = 0, unittrainnumber_4 = 0,
                                   unittrainnumber_5 = 0, unittrainnumber_6 = 0,
                                   unittrainnumber_7 = 0, unittrainnumber_8 = 0,
                                   unittrainnumber_9 = 0, unittrainnumber_10 = 0,
                                   unittrainnumberleft_1 = 3, unittrainnumberleft_2 = 1,
                                   unittrainnumberleft_3 = 0, unittrainnumberleft_4 = 0,
                                   unittrainnumberleft_5 = 0, unittrainnumberleft_6 = 0,
                                   unittrainnumberleft_7 = 0, unittrainnumberleft_8 = 0,
                                   unittrainnumberleft_9 = 0, unittrainnumberleft_10 = 0,
                                   unittrainendless_1 = 1, unittrainendless_2 = 1,
                                   unittrainendless_3 = 0, unittrainendless_4 = 0,
                                   unittrainendless_5 = 0, unittrainendless_6 = 0,
                                   unittrainendless_7 = 0, unittrainendless_8 = 0,
                                   unittrainendless_9 = 0, unittrainendless_10 = 0,
                                   unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).',
                                   npc_last_action = '.$ACTUAL_TICK.'
                            WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 1A '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                if($planet_to_serve['unittrain_actual'] == 0 && in_array($planet_to_serve['planet_type'], $ACADEMY_MINOR_LIST))
                {
                    if($troops_to_train < 1) $troops_to_train = 0;
                    $sql = 'UPDATE planets SET unittrainid_1 = 1, unittrainid_2 = 2,
                                   unittrainid_3 = 0, unittrainid_4 = 0,
                                   unittrainid_5 = 0, unittrainid_6 = 0,
                                   unittrainid_7 = 0, unittrainid_8 = 0,
                                   unittrainid_9 = 0, unittrainid_10 = 0,
                                   unittrainnumber_1 = 2, unittrainnumber_2 = 1,
                                   unittrainnumber_3 = 0, unittrainnumber_4 = 0,
                                   unittrainnumber_5 = 0, unittrainnumber_6 = 0,
                                   unittrainnumber_7 = 0, unittrainnumber_8 = 0,
                                   unittrainnumber_9 = 0, unittrainnumber_10 = 0,
                                   unittrainnumberleft_1 = 2, unittrainnumberleft_2 = 1,
                                   unittrainnumberleft_3 = 0, unittrainnumberleft_4 = 0,
                                   unittrainnumberleft_5 = 0, unittrainnumberleft_6 = 0,
                                   unittrainnumberleft_7 = 0, unittrainnumberleft_8 = 0,
                                   unittrainnumberleft_9 = 0, unittrainnumberleft_10 = 0,
                                   unittrainendless_1 = 1, unittrainendless_2 = 1,
                                   unittrainendless_3 = 0, unittrainendless_4 = 0,
                                   unittrainendless_5 = 0, unittrainendless_6 = 0,
                                   unittrainendless_7 = 0, unittrainendless_8 = 0,
                                   unittrainendless_9 = 0, unittrainendless_10 = 0,
                                   unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).',
                                   npc_last_action = '.$ACTUAL_TICK.'
                            WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 1B '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }

                // A - 6
                // Settlers get back a ship from unsuccesful auction. 
                $sql='SELECT ship_id FROM FHB_warteschlange WHERE user_id = '.INDEPENDENT_USERID.' LIMIT 0,1';
                $notpayed=$this->db->queryrow($sql);
                if(isset($notpayed['ship_id']) && !empty($notpayed['ship_id']))
                {
                    $sql='DELETE FROM FHB_warteschlange WHERE ship_id = '.$notpayed['ship_id'];
                    $this->sdl->log('SQL A - 6A '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    $sql='UPDATE ships SET user_id = '.INDEPENDENT_USERID.', fleet_id = -'.$planet_to_serve['planet_id'].' WHERE ship_id = '.$notpayed['ship_id'];
                    $this->sdl->log('SQL A - 6B '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 6C '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                
                if($planet_to_serve['best_mood_user'] == INDEPENDENT_USERID) {
                    $this->sdl->log('DEBUG: planet '.$planet_to_serve['planet_id'].' is truly independent!', TICK_LOG_FILE_NPC);
                    $sql = 'SELECT event_status 
                            FROM (settlers_relations sr)
                            LEFT JOIN (settlers_events se) ON (sr.planet_id = se.planet_id AND sr.user_id = se.user_id)
                            WHERE sr.planet_id = '.$planet_to_serve['planet_id'].' AND
                                  sr.log_code = 1 AND
                                  sr.user_id = '.ORION_USERID;
                    $res = $this->db->queryrow($sql);
                    if($res['event_status'] == 1){
                        $sql = 'SELECT log_code, SUM(mood_modifier) as mood_value FROM settlers_relations WHERE planet_id = '.$planet_to_serve['planet_id'].' AND user_id = '.ORION_USERID.' GROUP BY log_code';
                        $q_s_m = $this->db->queryrowset($sql);

                        $orion_basevalue = $orion_terror = $orion_survival = $orion_support = $orion_tactics = $orion_intel = $orion_resources = 0;

                        foreach ($q_s_m AS $q_s_item) {
                            switch ($q_s_item['log_code']) {
                                case 28:
                                    $orion_terror = $q_s_item['mood_value'];
                                    break;
                                case 106:
                                    $orion_basevalue = $q_s_item['mood_value'];
                                    break;
                                case 107:
                                    $orion_survival = $q_s_item['mood_value'];
                                    break;
                                case 108:
                                    $orion_support = $q_s_item['mood_value'];
                                    break;
                                case 109:
                                    $orion_tactics = $q_s_item['mood_value'];
                                    break;
                                case 110:
                                    $orion_intel = $q_s_item['mood_value'];
                                    break;
                                case 111:
                                    $orion_resources = $q_s_item['mood_value'];
                                    break;
                            }
                        }

                        $orion_modifier = 1 + ($orion_basevalue - $orion_terror);

                        $this->sdl->log('Orion modifier for planet '.$planet_to_serve['planet_id'].' = '.$orion_modifier, TICK_LOG_FILE_NPC);

                        $orion_check = rand(1,720) + $orion_modifier;

                        $this->sdl->log('Orion check for planet '.$planet_to_serve['planet_id'].' = '.$orion_check, TICK_LOG_FILE_NPC);
                        
                        if ($orion_check >= 600) {
                            $this->sdl->log('Orion power raise!!! value = '.$orion_check, TICK_LOG_FILE_NPC);
                            $orion_rise_chance_array = array (
                                'power'     => 20,
                                'survival'  => (20 + (int)((80 - $orion_basevalue) / 4)),
                                'support'   => (20 + (int)((80 - $orion_basevalue) / 4)),
                                'tactics'   => (20 + (int)((80 - $orion_basevalue) / 4)),
                                'intel'     => (20 + (int)((80 - $orion_basevalue) / 4)),
                                'resources' => (20 + (int)((80 - $orion_basevalue) / 4)),
                            );

                            $orion_array = array();

                            foreach($orion_rise_chance_array as $event => $probability) {
                                for($i = 0; $i < $probability; ++$i) {
                                    $power_array[] = $event;
                                }
                            }                

                            $orion_event = $power_array[array_rand($power_array)];

                            switch ($orion_event) {
                                case 'power':
                                    $log_code = 106;
                                    $mood_actual = $orion_basevalue;
                                    break;
                                case 'survival':
                                    $log_code = 107;
                                    $mood_actual = $orion_survival;
                                    break;
                                case 'support':
                                    $log_code = 108;
                                    $mood_actual = $orion_support;
                                    break;
                                case 'tactics':
                                    $log_code = 109;
                                    $mood_actual = $orion_tactics;
                                    break;
                                case 'intel':
                                    $log_code = 110;
                                    $mood_actual = $orion_intel;
                                    break;
                                case 'resources':
                                    $log_code = 111;
                                    $mood_actual = $orion_resources;
                                    break;
                                default :
                                    $this->sdl->log('Orion event anomaly = '.$orion_event, TICK_LOG_FILE_NPC);
                                    break;
                            }

                            if($mood_actual == 0) {

                                $sql='INSERT INTO settlers_relations SET planet_id = '.$planet_to_serve['planet_id'].', user_id = '.ORION_USERID.',timestamp = '.time().',log_code = '.$log_code.', mood_modifier = 1';

                            }
                            else {
                                $new_mood = min(80, (1 + $mood_actual));

                                $sql='UPDATE settlers_relations SET mood_modifier = '.$new_mood.', timestamp = '.time().'
                                        WHERE planet_id = '.$planet_to_serve['planet_id'].' AND user_id = '.ORION_USERID.' AND log_code = '.$log_code;
                            }

                            $this->sdl->log('SQL 8 '.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);

                        }
                    }
                }
                else 
                {
                    // A - 2
                    // Huge rewrite!
                    // If we have a ship in the spacedock, we can send it to the best friend of the planet or
                    // make and auction.
                    // The query is made on all the planets, in order to free the deleted players spacedocks
                    // Search for the oldest ship present in the spacedock.
                    $sql = 'SELECT * FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id
                            WHERE fleet_id = -'.$planet_to_serve['planet_id'].' AND ship_untouchable = 0
                            ORDER BY construction_time ASC
                            LIMIT 0,1';
                    /**************************************************************/
                    /* AC: I think would be lighter for the DB to:                */
                    /* - first retrieve the oldest ship                           */
                    /* - then retrieve the resources needed by its template       */
                    /* instead of joining the two tables ships and ship_templates */
                    /**************************************************************/
                    $t_q=$this->db->queryrow($sql);
                    if(isset($t_q['ship_id']) && !empty($t_q['ship_id']))
                    {
                        $player_found = FALSE;
                        if($planet_to_serve['best_mood'] >= 120)
                        {
                            $player_chance = min((round(($planet_to_serve['best_mood'] - 120)/4 ,0) + 51), 96);
                            if($player_chance > rand(0,100)) $player_found = TRUE;
                        }
                        if($player_found)
                        {
                            // besty found. Make a fleet, put the ship into int, warn it about that.
                            // $sql = 'SELECT user_capital from user WHERE user_id = '.$planet_to_serve['best_mood_user'];
                            // $setl_bf = $this->db->queryrow($sql);
                            $sql='INSERT INTO ship_fleets (fleet_name, user_id, owner_id, planet_id, move_id, n_ships)
                                         VALUES ("Reinforcement", '.$planet_to_serve['best_mood_user'].', '.$planet_to_serve['best_mood_user'].', '.$planet_to_serve['planet_id'].', 0, 1)';
                            //$this->sdl->log('SQL A - 2.A1 '.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $fleet_id = $this->db->insert_id();
                            $sql = 'UPDATE ships SET user_id = '.$planet_to_serve['best_mood_user'].', fleet_id = '.$fleet_id.' WHERE ship_id='.$t_q['ship_id'];
                            //$this->sdl->log('SQL A - 2.A2 '.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            //Here we need multilanguage customization
                            if($planet_to_serve['best_mood_alert']) {
                                $log_title = 'Nuova unit&agrave; disponibile dalla colonia indipendente '.$planet_to_serve['planet_name'];
                                $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], $t_q['ship_id'], $t_q['name'], 0, 0);
                                add_logbook_entry($planet_to_serve['best_mood_user'], LOGBOOK_SETTLERS, $log_title, $log_data);
                            }
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                            //$this->sdl->log('SQL A - 2.A5 '.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;
                        }
                        else
                        {
                            // No besty found; let's make an auction.
                            $_ress_1 = round($t_q['resource_1']*0.16);
                            $_ress_2 = round($t_q['resource_2']*0.16);
                            $_ress_3 = round($t_q['resource_3']*0.16);
                            $_ress_1_step = round($t_q['resource_1']*0.03);
                            $_ress_2_step = round($t_q['resource_2']*0.03);
                            $_ress_3_step = round($t_q['resource_3']*0.03);
                            $sql = 'INSERT INTO ship_trade (user,planet,start_time,end_time,ship_id,resource_1,resource_2,resource_3,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,add_resource_1,add_resource_2,add_resource_3,add_unit_1,add_unit_2,add_unit_3,add_unit_4,add_unit_5,add_unit_6,header,description,show_data,font_bold,font_colored,unowed_only)
                                    VALUES   ('.INDEPENDENT_USERID.','.$planet_to_serve['planet_id'].','.$ACTUAL_TICK.','.($ACTUAL_TICK + 480).','.$t_q['ship_id'].','.$_ress_1.','.$_ress_2.','.$_ress_3.', 0, 0, 0, 0,'.$t_q['unit_5'].','.$t_q['unit_6'].','.$_ress_1_step.','.$_ress_2_step.','.$_ress_3_step.',0,0,0,0,0,0,"'.$t_q['name'].'","This is an automatic generated auction for a ship held by the Settlers Community!!!",2,1,1,0)';
                            //$this->sdl->log('SQL A - 2.B1 '.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            $sql = 'UPDATE ships SET ship_untouchable=1 WHERE ship_id='.$t_q['ship_id'];
                            $this->db->query($sql);
                            // This update is marginal and it could be removed to decrease the server load
                            $sql = 'UPDATE user SET num_auctions=num_auctions+1 WHERE user_id='.INDEPENDENT_USERID;
                            $this->db->query($sql);
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                            //$this->sdl->log('SQL A - 2.B2 '.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;
                        }

                    }

                    // A - 4.2
                    // Troops sent to players / CC
                    if($planet_to_serve['best_mood'] >= 120 && (in_array($planet_to_serve['planet_type'], $ACADEMY_MINOR_LIST)) && $planet_to_serve['npc_next_delivery'] < $ACTUAL_TICK)
                    {
                        // Supplies first and second level troops
                        // 20% dei lavoratori presenti
                        // 40% delle truppe presenti
                        // BMU ottiene il 25% di golden share; il resto è divisto tra tutti gli user con mood >= 120

                        $_ress_4 = $_unit_1 = $_unit_2 = 0;
                        $_unit_1_cut = $_unit_2_cut = 0;
                        $_unit_trade = 0.20;
                        $_ress_golden = 0.25;
                        
                        /*
                        $troops_check = ($planet_to_serve['unit_1']*2) + ($planet_to_serve['unit_2']*3) + ($planet_to_serve['unit_3']*4) + ($planet_to_serve['unit_4']*4);
                        $security_check = $planet_to_serve['min_security_troops'] * 0.75;
                        if($troops_check > $security_check) {
                            $_ress_4 = round(0.20*$planet_to_serve['resource_4']);
                            $planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
                            $_unit_1 = round(0.40*$planet_to_serve['unit_1']);
                            $planet_to_serve['unit_1'] = $planet_to_serve['unit_1'] - $_unit_1;
                            $_unit_2 = round(0.40*$planet_to_serve['unit_2']);
                            $planet_to_serve['unit_2'] = $planet_to_serve['unit_2'] - $_unit_2;
                        }
                        */

                        $_ress_4 = round(0.20*$planet_to_serve['resource_4']);
                        $planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
                        $_unit_1 = round(0.40*$planet_to_serve['unit_1']);
                        $planet_to_serve['unit_1'] = $planet_to_serve['unit_1'] - $_unit_1;
                        $_unit_2 = round(0.40*$planet_to_serve['unit_2']);
                        $planet_to_serve['unit_2'] = $planet_to_serve['unit_2'] - $_unit_2;                        

                        if(($_ress_4 + $_unit_1 + $_unit_2) > 0)
                        {
                            $_unit_1_cut = round($_unit_trade*$_unit_1);
                            $_unit_2_cut = round($_unit_trade*$_unit_2);
                            $avail_tick = mt_rand(23,420);
                            $avail_tick = $avail_tick + $ACTUAL_TICK;
                            $sql = 'INSERT INTO `FHB_cache_trupp_trade` (`unit_1` , `unit_2` , tick ) VALUES ('.$_unit_1_cut.', '.$_unit_2_cut.', '.$avail_tick.')';                                                        
                            //$this->sdl->log('SQL A - 4.2.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);                            
                            // Golden Share
                            $golden_u_1 = round($_unit_1 * $_ress_golden);
                            $golden_u_2 = round($_unit_2 * $_ress_golden);
                            $golden_r_4 = round($_ress_4 * $_ress_golden);
                            // Fetch della lista dei player over 120
                            $sql = 'SELECT user_id, user_race, language, SUM(mood_modifier) AS mood_value 
                                    FROM settlers_relations sr 
                                    INNER JOIN user u USING(user_id) 
                                    WHERE sr.planet_id = '.$planet_to_serve['planet_id'].' AND sr.user_id > 10 
                                    GROUP BY sr.user_id HAVING SUM(mood_modifier) >= 120 ';
                            $share_list = $this->db->queryrowset($sql);
                            $n_share = $this->db->num_rows(); // Dovrebbe essere sempre come minimo 1
                            $share_u_1 = round(($_unit_1 * (1 - $_ress_golden)) / $n_share);
                            $share_u_2 = round(($_unit_2 * (1 - $_ress_golden)) / $n_share);
                            $share_r_4 = round(($_ress_4 * (1 - $_ress_golden)) / $n_share);

                            foreach ($share_list AS $share_item) {

                                switch($share_item['language']) {
                                    case 'ITA' : $log_title = 'Truppe in arrivo dalla colonia '.$planet_to_serve['planet_name']; break;
                                    case 'GER' : $log_title = 'Truppen aus der Kolonie '.$planet_to_serve['planet_name']; break;
                                    default: $log_title = 'Truppe in arrivo dalla colonia '.$planet_to_serve['planet_name']; break;
                                }
                                $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], 0, 0, 101, 0);                                
                                if($share_item['user_id'] == $planet_to_serve['best_mood_user'])
                                {                            
                                    if(is_null($planet_to_serve['best_mood_planet']))
                                    {
                                        $sql = 'INSERT INTO schulden_table 
                                                       (user_ver, user_kauf, ship_id, unit_1, unit_2, timestep, status, auktions_id)
                                                VALUES ('.$share_item['user_id'].', '.INDEPENDENT_USERID.', 0, '.($share_u_1 + $golden_u_1).', '.($share_u_2 + $golden_u_2).', '.$ACTUAL_TICK.', 1, 0)';
                                        $this->sdl->log('SQL A - 4.2.2'.$sql, TICK_LOG_FILE_NPC);                                
                                        $this->db->query($sql);                                
                                        $code = $this->db->insert_id();
                                        $sql='INSERT INTO treuhandkonto 
                                                          (code, timestep, unit_1, unit_2) 
                                              VALUES ('.$code.','.$ACTUAL_TICK.', '.($share_u_1 + $golden_u_1).', '.($share_u_2 + $golden_u_2).')';
                                        $this->sdl->log('SQL A - 4.2.3'.$sql, TICK_LOG_FILE_NPC);                                                                
                                        $this->db->query($sql);
                                        $planet_to_serve['resource_4'] += ($share_r_4 + $golden_r_4);
                                        $log_data[8]['unit_1'] = $share_u_1 - $golden_u_1;
                                        $log_data[8]['unit_2'] = $share_u_2 - $golden_u_2;
                                        add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                                        
                                    }
                                    else {                                
                                        $target_planet = $planet_to_serve['best_mood_planet'];
                                        $log_data[5] = 1;
                                        $log_data[6] = $target_planet;
                                        $sql = 'SELECT planet_name from planets WHERE planet_id = '.$planet_to_serve['best_mood_planet'];
                                        $setl_bfp = $this->db->queryrow($sql);                                
                                        $log_data[7] = $setl_bfp['planet_name'];
                                        $sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time)
                                            VALUES ('.$target_planet.', 0, 0, 0, '.($share_r_4 + $golden_r_4).', '.($share_u_1 + $golden_u_1).', '.($share_u_2 + $golden_u_2).', 0, 0, 0, 0, '.($ACTUAL_TICK + 120).')';
                                        $this->sdl->log('SQL A - 4.2.4'.$sql, TICK_LOG_FILE_NPC);
                                        $this->db->query($sql);
                                        $log_data[8]['unit_1'] = $share_u_1 - $golden_u_1;
                                        $log_data[8]['unit_2'] = $share_u_2 - $golden_u_2;
                                        $log_data[8]['ress_4'] = $share_r_4 + $golden_r_4;
                                        add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                                                                                                                    
                                    }
                                }
                                else {
                                    $sql = 'INSERT INTO schulden_table 
                                                    (user_ver, user_kauf, ship_id, unit_1, unit_2, timestep, status, auktions_id)
                                            VALUES ('.$share_item['user_id'].', '.INDEPENDENT_USERID.', 0, '.$share_u_1.', '.$share_u_2.', '.$ACTUAL_TICK.', 1, 0)';
                                    $this->sdl->log('SQL A - 4.2.5'.$sql, TICK_LOG_FILE_NPC);                                
                                    $this->db->query($sql);                                
                                    $code = $this->db->insert_id();
                                    $sql='INSERT INTO treuhandkonto 
                                                    (code, timestep, unit_1, unit_2) 
                                        VALUES ('.$code.','.$ACTUAL_TICK.', '.$share_u_1.', '.$share_u_2.')';
                                    $this->sdl->log('SQL A - 4.2.6'.$sql, TICK_LOG_FILE_NPC);                                                                
                                    $this->db->query($sql);
                                    $planet_to_serve['resource_4'] += $share_r_4;                                    
                                    $log_data[8]['unit_1'] = $share_u_1;
                                    $log_data[8]['unit_2'] = $share_u_2;
                                    add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                                    
                                }
                            }
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).',
                                        npc_next_delivery = '.($ACTUAL_TICK + 480).',
                                        resource_4 = '.$planet_to_serve['resource_4'].',
                                        unit_1 = '.$planet_to_serve['unit_1'].',
                                        unit_2 = '.$planet_to_serve['unit_2'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            //$this->sdl->log('SQL A - 4.2.5'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;                            
                        }
                    }

                    // A - 4.3                    
                    if($planet_to_serve['best_mood'] >= 120 && (in_array($planet_to_serve['planet_type'], $ACADEMY_MAJOR_LIST)) && $planet_to_serve['npc_next_delivery'] < $ACTUAL_TICK)
                    {
                        // Supply workers and specs
                        $_ress_4 = $_unit_5 = $_unit_6 = 0;
                        $_unit_5_cut = $_unit_6_cut = 0;
                        $_unit_trade = 0.20;
                        $_ress_golden = 0.25;
                        $_ress_4 = round(0.20*$planet_to_serve['resource_4']);
                        $planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
                        $_unit_5 = round(0.40*$planet_to_serve['unit_5']);
                        $planet_to_serve['unit_5'] = $planet_to_serve['unit_5'] - $_unit_5;
                        $_unit_6 = round(0.40*$planet_to_serve['unit_6']);
                        $planet_to_serve['unit_6'] = $planet_to_serve['unit_6'] - $_unit_6;                    

                        if(($_ress_4 + $_unit_5 + $_unit_6) > 0)
                        {
                            $_unit_5_cut = round($_unit_trade*$_unit_5);
                            $_unit_6_cut = round($_unit_trade*$_unit_6);
                            $avail_tick = mt_rand(23,420);
                            $avail_tick = $avail_tick + $ACTUAL_TICK;
                            $sql = 'INSERT INTO `FHB_cache_trupp_trade` (`unit_5` , `unit_6` , tick ) VALUES ('.$_unit_5_cut.', '.$_unit_6_cut.', '.$avail_tick.')';                            
                            //$this->sdl->log('SQL A - 4.3.1'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            
                            $golden_u_5 = round($_unit_5 * $_ress_golden);
                            $golden_u_6 = round($_unit_6 * $_ress_golden);
                            $golden_r_4 = round($_ress_4 * $_ress_golden);
                            // Fetch della lista dei player over 120
                            $sql = 'SELECT user_id, user_race, language, SUM(mood_modifier) AS mood_value 
                                    FROM settlers_relations sr 
                                    INNER JOIN user u USING(user_id) 
                                    WHERE sr.planet_id = '.$planet_to_serve['planet_id'].' AND sr.user_id > 10 
                                    GROUP BY sr.user_id HAVING SUM(mood_modifier) >= 120 ';
                            $share_list = $this->db->queryrowset($sql);
                            $n_share = $this->db->num_rows(); // Dovrebbe essere sempre come minimo 1
                            $share_u_5 = round(($_unit_5 * (1 - $_ress_golden)) / $n_share);
                            $share_u_6 = round(($_unit_6 * (1 - $_ress_golden)) / $n_share);
                            $share_r_4 = round(($_ress_4 * (1 - $_ress_golden)) / $n_share);
                            
                            foreach($share_list AS $share_item)
                            {

                                switch($share_item['language']) {
                                    case 'ITA' : $log_title = 'Truppe in arrivo dalla colonia '.$planet_to_serve['planet_name']; break;
                                    case 'GER' : $log_title = 'Truppen aus der Kolonie '.$planet_to_serve['planet_name']; break;
                                    default: $log_title = 'Truppe in arrivo dalla colonia '.$planet_to_serve['planet_name']; break;
                                }
                                $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], 0, 0, 101, 0);
                                if($share_item['user_id'] == $planet_to_serve['best_mood_user']){
                                    if(is_null($planet_to_serve['best_mood_planet']))
                                    {
                                        $sql = 'INSERT INTO schulden_table 
                                                       (user_ver, user_kauf, ship_id, unit_5, unit_6, timestep, status, auktions_id)
                                                VALUES ('.$share_item['user_id'].', '.INDEPENDENT_USERID.', 0, '.($share_u_5 - $golden_u_5).', '.($share_u_6 - $golden_u_6).', '.$ACTUAL_TICK.', 1, 0)';
                                        $this->sdl->log('SQL A - 4.3.2'.$sql, TICK_LOG_FILE_NPC);                                
                                        $this->db->query($sql);                                
                                        $code = $this->db->insert_id();
                                        $sql='INSERT INTO treuhandkonto 
                                                          (code, timestep, unit_5, unit_6) 
                                              VALUES ('.$code.','.$ACTUAL_TICK.', '.($share_u_5 - $golden_u_5).', '.($share_u_6 - $golden_u_6).')';
                                        $this->sdl->log('SQL A - 4.3.3'.$sql, TICK_LOG_FILE_NPC);                                                                
                                        $this->db->query($sql);
                                        $planet_to_serve['resource_4'] += ($share_r_4 + $golden_r_4);
                                        $log_data[8]['unit_1'] = $share_u_5 - $golden_u_5;
                                        $log_data[8]['unit_2'] = $share_u_6 - $golden_u_6;
                                        add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                                                                            
                                    }
                                    else {
                                        $target_planet = $planet_to_serve['best_mood_planet'];
                                        $log_data[5] = 1;
                                        $log_data[6] = $target_planet;
                                        $sql = 'SELECT planet_name from planets WHERE planet_id = '.$planet_to_serve['best_mood_planet'];
                                        $setl_bfp = $this->db->queryrow($sql);                                
                                        $log_data[7] = $setl_bfp['planet_name'];                                
                                        $sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time)
                                            VALUES ('.$target_planet.', 0, 0, 0, '.$_ress_4.', 0, 0, 0, 0, '.($share_u_5 - $golden_u_5).', '.($share_u_6 - $golden_u_6).', '.($ACTUAL_TICK + 120).')';
                                        $this->sdl->log('SQL A - 4.3.4'.$sql, TICK_LOG_FILE_NPC);
                                        $this->db->query($sql);
                                        $log_data[8]['unit_1'] = $share_u_5 - $golden_u_5;
                                        $log_data[8]['unit_2'] = $share_u_6 - $golden_u_6;
                                        $log_data[8]['ress_4'] = $share_r_4 + $golden_r_4;
                                        add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                                                                            
                                    }
                                }
                                else {
                                    $sql = 'INSERT INTO schulden_table 
                                    (user_ver, user_kauf, ship_id, unit_5, unit_6, timestep, status, auktions_id)
                                            VALUES ('.$share_item['user_id'].', '.INDEPENDENT_USERID.', 0, '.($share_u_5 - $golden_u_5).', '.($share_u_6 - $golden_u_6).', '.$ACTUAL_TICK.', 1, 0)';
                                    $this->sdl->log('SQL A - 4.3.5'.$sql, TICK_LOG_FILE_NPC);                                
                                    $this->db->query($sql);                                
                                    $code = $this->db->insert_id();
                                    $sql='INSERT INTO treuhandkonto 
                                                    (code, timestep, unit_5, unit_6) 
                                        VALUES ('.$code.','.$ACTUAL_TICK.', '.($share_u_5 - $golden_u_5).', '.($share_u_6 - $golden_u_6).')';
                                    $this->sdl->log('SQL A - 4.3.6'.$sql, TICK_LOG_FILE_NPC);                                                                
                                    $this->db->query($sql);
                                    $planet_to_serve['resource_4'] += ($share_r_4 + $golden_r_4);
                                    $log_data[8]['unit_1'] = $share_u_5;
                                    $log_data[8]['unit_2'] = $share_u_6;
                                    add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);                                    
                                }                            
                            }
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).',
                                        npc_next_delivery = '.($ACTUAL_TICK + 480).',                                
                                        resource_4 = '.$planet_to_serve['resource_4'].',
                                        unit_5 = '.$planet_to_serve['unit_5'].',
                                        unit_6 = '.$planet_to_serve['unit_6'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            //$this->sdl->log('SQL A - 4.3.7'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;                            
                        }
                    }

                    // A - 4.4
                    // Ress to trade: 25% of real production (Orion Syndicate penalty could apply)
                    if((in_array($planet_to_serve['planet_type'], $MINE_MAJOR_LIST) || in_array($planet_to_serve['planet_type'], $MINE_MINOR_LIST)) && $planet_to_serve['npc_next_delivery'] < $ACTUAL_TICK)
                    {
                        $_ress_1 = $_ress_2 = $_ress_3 = 0;
                        $_ress_cut = 0; // Eventuale parte rubata da Orione                        
                        $_ress_rateo = 0.25; // Bisogna modificarla in base al controllore
                        if($planet_to_serve['planet_type'] == 'j')
                        {
                            $_ress_1 = round($_ress_rateo*($planet_to_serve['add_1']*20*24));
                            if($_ress_1 < $planet_to_serve['resource_1'])
                            {
                                $planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
                                $_ress_1_cut = round($_ress_cut*$_ress_1);
                            }
                            else
                                $_ress_1 = 0;
                        }
                        elseif($planet_to_serve['planet_type'] == 's'|| $planet_to_serve['planet_type'] == 't')
                        {
                            $_ress_2 = round($_ress_rateo*($planet_to_serve['add_2']*20*24));
                            if($_ress_2 < $planet_to_serve['resource_2'])
                            {
                                $planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
                                $_ress_2_cut = round($_ress_cut*$_ress_2);
                            }
                            else
                                $_ress_2 = 0;
                        }
                        elseif($planet_to_serve['planet_type'] == 'i')
                        {
                            $_ress_3 = round($_ress_rateo*($planet_to_serve['add_3']*20*24));
                            if($_ress_3 < $planet_to_serve['resource_3'])
                            {
                                $planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
                                $_ress_3_cut = round($_ress_cut*$_ress_3);
                            }
                            else
                                $_ress_3 = 0;
                        }
                        elseif(in_array($planet_to_serve['planet_type'], $MINE_MAJOR_LIST))
                        {
                            $_ress_1 = round($_ress_rateo*($planet_to_serve['add_1']*20*24));
                            if($_ress_1 < $planet_to_serve['resource_1'])
                            {
                                $planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
                                $_ress_1_cut = round($_ress_cut*$_ress_1);
                            }
                            else
                                $_ress_1 = 0;
                            $_ress_2 = round($_ress_rateo*($planet_to_serve['add_2']*20*24));
                            if($_ress_2 < $planet_to_serve['resource_2'])
                            {
                                $planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
                                $_ress_2_cut = round($_ress_cut*$_ress_2);
                            }
                            else
                                $_ress_2 = 0;
                            $_ress_3 = round($_ress_rateo*($planet_to_serve['add_3']*20*24));
                            if($_ress_3 < $planet_to_serve['resource_3'])
                            {
                                $planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
                                $_ress_3_cut = round($_ress_cut*$_ress_3);
                            }
                            else
                                $_ress_3 = 0;
                        } 
                        // Ships the cut to the tradecenter
                        $sql = 'UPDATE FHB_Handels_Lager SET ress_1=ress_1+'.$_ress_1.',ress_2=ress_2+'.$_ress_2.',ress_3=ress_3+'.$_ress_3.' WHERE id=1';
                        //$this->sdl->log('SQL A - 4.7'.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);                                               
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).',
                                    npc_next_delivery = '.($ACTUAL_TICK + 480).',                                
                                    resource_1 = '.$planet_to_serve['resource_1'].',
                                    resource_2 = '.$planet_to_serve['resource_2'].',
                                    resource_3 = '.$planet_to_serve['resource_3'].'
                                WHERE planet_id = '.$planet_to_serve['planet_id'];
                        //$this->sdl->log('SQL A - 4.5.4'.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);                        
                    }
                    // A - 4.5
                    // Mining planets
                    // Share reworks
                    // Ress to share: 65% of real production (Orion Syndicate penalty could apply too!!!)
                    // So the math is:  $_ress_X = 0.65*(add_X*20*24)
                    // CBF gets golden share of 25%, remainder is equally shared among ALL the players with mood >= 120
                    if(($planet_to_serve['best_mood'] >= 120) && (in_array($planet_to_serve['planet_type'], $MINE_MAJOR_LIST) || in_array($planet_to_serve['planet_type'], $MINE_MINOR_LIST)) && $planet_to_serve['npc_next_delivery'] < $ACTUAL_TICK)
                    {
                        $_ress_1 = $_ress_2 = $_ress_3 = 0;
                        $_ress_rateo = 0.65; // Bisogna modificarla in base al controllore
                        $_ress_cut = 0; // Eventuale parte rubata da Orione
                        $_ress_golden = 0.25; // Cambia a seconda del controllore

                        if($planet_to_serve['planet_type'] == 'j')
                        {
                            $_ress_1 = round($_ress_rateo*($planet_to_serve['add_1']*20*24));
                            if($_ress_1 < $planet_to_serve['resource_1'])
                            {
                                $planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
                                $_ress_1_cut = round($_ress_cut*$_ress_1);
                            }
                            else
                                $_ress_1 = 0;
                        }
                        elseif($planet_to_serve['planet_type'] == 's'|| $planet_to_serve['planet_type'] == 't')
                        {
                            $_ress_2 = round($_ress_rateo*($planet_to_serve['add_2']*20*24));
                            if($_ress_2 < $planet_to_serve['resource_2'])
                            {
                                $planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
                                $_ress_2_cut = round($_ress_cut*$_ress_2);
                            }
                            else
                                $_ress_2 = 0;
                        }
                        elseif($planet_to_serve['planet_type'] == 'i')
                        {
                            $_ress_3 = round($_ress_rateo*($planet_to_serve['add_3']*20*24));
                            if($_ress_3 < $planet_to_serve['resource_3'])
                            {
                                $planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
                                $_ress_3_cut = round($_ress_cut*$_ress_3);
                            }
                            else
                                $_ress_3 = 0;
                        }
                        elseif(in_array($planet_to_serve['planet_type'], $MINE_MAJOR_LIST))
                        {
                            $_ress_1 = round($_ress_rateo*($planet_to_serve['add_1']*20*24));
                            if($_ress_1 < $planet_to_serve['resource_1'])
                            {
                                $planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
                                $_ress_1_cut = round($_ress_cut*$_ress_1);
                            }
                            else
                                $_ress_1 = 0;
                            $_ress_2 = round($_ress_rateo*($planet_to_serve['add_2']*20*24));
                            if($_ress_2 < $planet_to_serve['resource_2'])
                            {
                                $planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
                                $_ress_2_cut = round($_ress_cut*$_ress_2);
                            }
                            else
                                $_ress_2 = 0;
                            $_ress_3 = round($_ress_rateo*($planet_to_serve['add_3']*20*24));
                            if($_ress_3 < $planet_to_serve['resource_3'])
                            {
                                $planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
                                $_ress_3_cut = round($_ress_cut*$_ress_3);
                            }
                            else
                                $_ress_3 = 0;
                        }
                        if(($_ress_1 + $_ress_2 + $_ress_3) > 0){
                            // Golden Share
                            $golden_r_1 = round($_ress_1 * $_ress_golden);
                            $golden_r_2 = round($_ress_2 * $_ress_golden);
                            $golden_r_3 = round($_ress_3 * $_ress_golden);
                            // Fetch della lista dei player over 120
                            $sql = 'SELECT user_id, user_race, language, SUM(mood_modifier) AS mood_value 
                                    FROM settlers_relations sr 
                                    INNER JOIN user u USING(user_id) 
                                    WHERE sr.planet_id = '.$planet_to_serve['planet_id'].' AND sr.user_id > 10 
                                    GROUP BY sr.user_id HAVING SUM(mood_modifier) >= 120 ';
                            $share_list = $this->db->queryrowset($sql);
                            $n_share = $this->db->num_rows(); // Dovrebbe essere sempre come minimo 1
                            $share_r_1 = round(($_ress_1 * (1 - $_ress_golden)) / $n_share);
                            $share_r_2 = round(($_ress_2 * (1 - $_ress_golden)) / $n_share);
                            $share_r_3 = round(($_ress_3 * (1 - $_ress_golden)) / $n_share);

                            foreach ($share_list AS $share_item) {
                                switch ($share_item['language']) {
                                    case 'ITA' : $log_title = 'Risorse in arrivo dalla colonia '.$planet_to_serve['planet_name']; break;
                                    case 'GER' : $log_title = 'Eingehende Ressourcen von der Kolonie '.$planet_to_serve['planet_name']; break;
                                    default: $log_title = 'Risorse in arrivo dalla colonia '.$planet_to_serve['planet_name']; break;
                                }
                                $log_data = array($planet_to_serve['planet_id'], $planet_to_serve['planet_name'], 0, 0, 102, 0);                                                                
                                if($share_item['user_id'] == $planet_to_serve['best_mood_user']) {
                                    if(is_null($planet_to_serve['best_mood_planet']))
                                    {
                                        $sql = 'INSERT INTO schulden_table 
                                                       (user_ver, user_kauf, ship_id, ress_1, ress_2, ress_3, timestep, status, auktions_id)
                                                VALUES ('.$share_item['user_id'].', '.INDEPENDENT_USERID.', 0, '.($share_r_1 + $golden_r_1).', '.($share_r_2 + $golden_r_2).', '.($share_r_3 + $golden_r_3).' , '.$ACTUAL_TICK.', 1, 0)';
                                        $this->sdl->log('SQL A - 4.5.1'.$sql, TICK_LOG_FILE_NPC);                                
                                        $this->db->query($sql);                                
                                        $code = $this->db->insert_id();
                                        $sql='INSERT INTO treuhandkonto 
                                                          (code, timestep, ress_1, ress_2, ress_3) 
                                              VALUES ('.$code.','.$ACTUAL_TICK.', '.($share_r_1 + $golden_r_1).', '.($share_r_2 + $golden_r_2).', '.($share_r_3 + $golden_r_3).')';
                                        $this->sdl->log('SQL A - 4.5.2'.$sql, TICK_LOG_FILE_NPC);                                                                
                                        $this->db->query($sql);                                    
                                    }
                                    else 
                                    {
                                        $target_planet = $planet_to_serve['best_mood_planet'];
                                        $log_data[5] = 1;
                                        $log_data[6] = $target_planet;
                                        $sql = 'SELECT planet_name from planets WHERE planet_id = '.$planet_to_serve['best_mood_planet'];
                                        $setl_bfp = $this->db->queryrow($sql);                                
                                        $log_data[7] = $setl_bfp['planet_name'];
                                        $sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time)
                                                VALUES ('.$target_planet.', '.($share_r_1 + $golden_r_1).', '.($share_r_2 + $golden_r_2).', '.($share_r_3 + $golden_r_3).', 0, 0, 0, 0, 0, 0, 0, '.($ACTUAL_TICK + 120).')';
                                        $this->sdl->log('SQL A - 4.5.3'.$sql, TICK_LOG_FILE_NPC);
                                        $this->db->query($sql);                                                                    
                                    }
                                    $log_data[8]['ress_1'] = $share_r_1 + $golden_r_1;
                                    $log_data[8]['ress_2'] = $share_r_2 + $golden_r_2;
                                    $log_data[8]['ress_3'] = $share_r_3 + $golden_r_3;                                                                                                            
                                }
                                else {
                                    $sql = 'INSERT INTO schulden_table 
                                                    (user_ver, user_kauf, ship_id, ress_1, ress_2, ress_3, timestep, status, auktions_id)
                                            VALUES ('.$share_item['user_id'].', '.INDEPENDENT_USERID.', 0, '.$share_r_1.', '.$share_r_2.', '.$share_r_3.' , '.$ACTUAL_TICK.', 1, 0)';
                                    $this->sdl->log('SQL A - 4.5.4'.$sql, TICK_LOG_FILE_NPC);                                
                                    $this->db->query($sql);                                
                                    $code = $this->db->insert_id();
                                    $sql='INSERT INTO treuhandkonto 
                                                    (code, timestep, ress_1, ress_2, ress_3) 
                                        VALUES ('.$code.','.$ACTUAL_TICK.', '.$share_r_1.', '.$share_r_2.', '.$share_r_3.')';
                                    $this->sdl->log('SQL A - 4.5.5'.$sql, TICK_LOG_FILE_NPC);                                                                
                                    $this->db->query($sql);
                                    $log_data[8]['ress_1'] = $share_r_1;
                                    $log_data[8]['ress_2'] = $share_r_2;
                                    $log_data[8]['ress_3'] = $share_r_3;                                                                        
                                }                                
                                add_logbook_entry($share_item['user_id'], LOGBOOK_SETTLERS, $log_title, $log_data);
                            }
                            $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).',
                                        npc_next_delivery = '.($ACTUAL_TICK + 480).',                                
                                        resource_1 = '.$planet_to_serve['resource_1'].',
                                        resource_2 = '.$planet_to_serve['resource_2'].',
                                        resource_3 = '.$planet_to_serve['resource_3'].'
                                    WHERE planet_id = '.$planet_to_serve['planet_id'];
                            //$this->sdl->log('SQL A - 4.5.4'.$sql, TICK_LOG_FILE_NPC);
                            $this->db->query($sql);
                            continue;                            
                        }                                                
                    }                    
                } // end else not indipendent
                
                // A - 5
                // Planetary Orbital Shield!!!!
                // Let's give Settlers something for self-defense against Borg and nasty players
                /*
                $sql='SELECT fleet_id FROM ship_fleets WHERE fleet_name LIKE "Orbital'.$planet_to_serve['planet_id'].'"';
                $od_q = $this->db->queryrow($sql);
                if(isset($od_q['fleet_id']) && !empty($od_q['fleet_id']))
                {
                    $sql='SELECT COUNT(*) AS counter FROM ships WHERE fleet_id = '.$od_q['fleet_id'];
                    $orbital_check=$this->db->queryrow($sql);
                    if($orbital_check['counter'] < 50)
                    {
                        //No access to DB, let's put the values right in, REMEMBER TO CHECK THE TEMPLATE IN THE DB WHEN EDITING THIS!!!
                        $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time)
                                VALUES ('.$od_q['fleet_id'].', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].', 0, 200, '.time().', 400, 2, 2, '.time().')';
                        $this->db->query($sql);
                        $ticks = 20*4;
                        $ticks /= 100;
                        $ticks *= (100-2*($planet_to_serve['research_4']*$RACE_DATA[13][3]));
                        $ticks = round($ticks,0);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + $ticks).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 5.1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                }
                else
                {
                // Orbital defence fleet not exists. Let's create this.
                    $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, alert_phase, move_id, n_ships)
                            VALUES ("Orbital'.$planet_to_serve['planet_id'].'", '.INDEPENDENT_USERID.', '.$planet_to_serve['planet_id'].', '.ALERT_PHASE_GREEN.', 0, 1)';
                    $this->db->query($sql);
                    $fleet_id = $this->db->insert_id();
                    //No access to DB, let's put the values right in, REMEMBER TO CHECK THE TEMPLATE IN THE DB WHEN EDITING THIS!!!
                    $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time)
                            VALUES ('.$fleet_id.', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].', 0, 200, '.time().', 400, 2, 2, '.time().')';
                    $this->db->query($sql);
                    $ticks = 20*4;
                    $ticks /= 100;
                    $ticks *= (100-2*($planet_to_serve['research_4']*$RACE_DATA[13][3]));
                    $ticks = round($ticks,0);                    
                    $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + $ticks).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 5.0 '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                */
                if($planet_to_serve['orbital_fleet_id'] > 0)
                {
                    $orbital_cnt = $planet_to_serve['orbital_num'] - $planet_to_serve['orbital_by_player'];
                    if($orbital_cnt < 50)
                    {
                        //No access to DB, let's put the values right in, REMEMBER TO CHECK THE TEMPLATE IN THE DB WHEN EDITING THIS!!!
                        $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time)
                                VALUES ('.$planet_to_serve['orbital_fleet_id'].', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].', 0, 200, '.time().', 400, 2, 2, '.time().')';
                        $this->db->query($sql);
                        $ticks = 20*4;
                        $ticks /= 100;
                        $ticks *= (100-2*($planet_to_serve['research_4']*$RACE_DATA[13][3]));
                        $ticks = round($ticks,0);
                        $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + $ticks).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                        $this->sdl->log('SQL A - 5.1 '.$sql, TICK_LOG_FILE_NPC);
                        $this->db->query($sql);
                        continue;
                    }
                }
                else
                {
                // Orbital defence fleet not exists. Let's create this.
                    $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, owner_id, planet_id, alert_phase, move_id, n_ships)
                            VALUES ("Orbital'.$planet_to_serve['planet_id'].'", '.INDEPENDENT_USERID.', '.INDEPENDENT_USERID.', '.$planet_to_serve['planet_id'].', '.ALERT_PHASE_GREEN.', 0, 1)';
                    $this->db->query($sql);
                    $fleet_id = $this->db->insert_id();
                    //No access to DB, let's put the values right in, REMEMBER TO CHECK THE TEMPLATE IN THE DB WHEN EDITING THIS!!!
                    $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, torp, rof, rof2, last_refit_time)
                            VALUES ('.$fleet_id.', '.INDEPENDENT_USERID.', '.$cfg_data['settler_tmp_4'].', 0, 200, '.time().', 400, 2, 2, '.time().')';
                    $this->db->query($sql);
                    $ticks = 20*4;
                    $ticks /= 100;
                    $ticks *= (100-2*($planet_to_serve['research_4']*$RACE_DATA[13][3]));
                    $ticks = round($ticks,0);                    
                    $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + $ticks).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                    $this->sdl->log('SQL A - 5.0 '.$sql, TICK_LOG_FILE_NPC);
                    $this->db->query($sql);
                    continue;
                }
                
                $ticks = ($planet_to_serve['best_mood_user'] == INDEPENDENT_USERID ? 20*24 : 20);
                $sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + $ticks).' WHERE planet_id = '.$planet_to_serve['planet_id'];
                $this->sdl->log('SQL E '.$sql, TICK_LOG_FILE_NPC);
                $this->db->query($sql);
            }
        }

        $this->sdl->finish_job('Mayflower Planets Building Control', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // ########################################################################################
        // Auctions clean-up

        $this->sdl->start_job('Mayflower auctions clean-up', TICK_LOG_FILE_NPC);

        // Set to "every resources withdrawn" on Settler's paid auctions
        $sql = 'UPDATE schulden_table SET status=2
                WHERE user_ver = '.INDEPENDENT_USERID.' AND status = 1';
        if(!$this->db->query($sql)) {
            $this->sdl->log('<b>Error:</b> cannot update status to "resources withdrawn" for Settlers auctions',
                TICK_LOG_FILE_NPC);
        }

        $this->sdl->finish_job('Mayflower auctions clean-up', TICK_LOG_FILE_NPC);

        // ########################################################################################
        // ########################################################################################

        $this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
    }
}


?>
