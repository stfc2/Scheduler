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


//########################################################################################
//########################################################################################

/* 25. June 2008
  @Author: Carolfi - Delogu
  @Action: Actually keep clean Settler's logbook
*/

/* ######################################################################################## */
/* ######################################################################################## */
// Startconfig of Settlers
class Settlers extends NPC
{
    public function Install($log = INSTALL_LOG_FILE_NPC)
    {
        $this->sdl->start_job('Mayflower basic system', $log);

        // We give the bot some data so that it is also Registered
        $this->bot = $this->db->queryrow('SELECT * FROM user WHERE user_id = '.INDEPENDENT_USERID);

        //Check whether the bot already lives
        if($this->bot['user_id'] == 0) {
            $this->sdl->log('We need to create TheSettlers!', $log);

            $sql = 'INSERT INTO user (user_id, user_active, user_name, user_loginname, user_password,
                                      user_email, user_auth_level, user_race, user_gfxpath, user_skinpath,
                                    user_registration_time, user_registration_ip,
                                          user_birthday, user_gender, plz, country, user_enable_sig,
                                          user_message_sig, user_signature, user_notepad, user_options, message_basement)
                         VALUES ('.INDEPENDENT_USERID.', '.STGC_BOT.', "Coloni(NPG)", "SettlersBot", "'.md5("settlers").'",
                                 "settlers@stfc.it", 1, 13, "", "skin1/", '.time().', "127.0.0.1",
                                 "25.06.2008", "", 16162 , "IT", 1,
                                 "",  "", "", "", "")';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not create TheSettlers - ABORTED', $log);
            }
        }
        $this->sdl->finish_job('Mayflower basic system', $log);
    }

	public function Execute($debug=0)
	{
		global $ACTUAL_TICK,$cfg_data;
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
				if($planet_to_serve['building_1'] < 9) 
				{
					$sql = 'UPDATE planets SET building_1 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1.1 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				if($planet_to_serve['building_5'] < 9) 
				{
					$sql = 'UPDATE planets SET building_5 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				if($planet_to_serve['building_2'] < 9) 
				{
					$sql = 'UPDATE planets SET building_2 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1.2'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}if($planet_to_serve['building_3'] < 9) 
				{
					$sql = 'UPDATE planets SET building_3 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1.3'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}if($planet_to_serve['building_4'] < 9) 
				{
					$sql = 'UPDATE planets SET building_4 = 9, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1.4'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// At this point, having rebuilt HQ and mines, Settlers automatically gains level 5 Academy.
				if($planet_to_serve['building_6'] < 5) 
				{
					$sql = 'UPDATE planets SET building_6 = 5, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 2'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// Now the Spacedock
				if($planet_to_serve['building_7']< 3) 
				{
					$sql = 'UPDATE planets SET building_7 = 3, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 3'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// Now the Spaceyard
				if($planet_to_serve['building_8'] < 1) 
				{
					$sql = 'UPDATE planets SET building_8 = 1, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 4'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// Light orbital defense
				if($planet_to_serve['building_10'] < 14) 
				{
					$sql = 'UPDATE planets SET building_10 = 14, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 5'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// Heavy orbital defense
				if($planet_to_serve['building_13'] < 14) 
				{
					$sql = 'UPDATE planets SET building_13 = 14, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 6'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// Are there enough workers in the mines?
				if($planet_to_serve['workermine_1'] < 1000 || $planet_to_serve['workermine_2'] < 1000 || $planet_to_serve['workermine_3'] < 1000)
				{
					$sql = 'UPDATE planets SET workermine_1 = 1000, workermine_2 = 1000, workermine_3 = 1000, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 7'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				
				// Let's activate the Academy!
				if($planet_to_serve['unittrain_actual'] == 0 &&
				  ($planet_to_serve['planet_type'] == 'm' || $planet_to_serve['planet_type'] == 'o' || $planet_to_serve['planet_type'] == 'p'))
				{
					$sql = 'UPDATE planets SET unittrainid_1 = 2, unittrainid_2 = 1,
								   unittrainid_3 = 5, unittrainid_4 = 6,
								   unittrainnumber_1 = 2, unittrainnumber_2 = 1,
								   unittrainnumber_3 = 1, unittrainnumber_4 = 1,
								   unittrainnumberleft_1 = 2, unittrainnumberleft_2 = 1,
								   unittrainnumberleft_3 = 1, unittrainnumberleft_4 = 1,
								   unittrainendless_1 = 1, unittrainendless_2 = 1,
								   unittrainendless_3 = 1, unittrainendless_4 = 1,
								   unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).',
								   npc_last_action = '.$ACTUAL_TICK.'
						WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL A - 1A '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}

				if($planet_to_serve['unittrain_actual'] == 0 &&
				  ($planet_to_serve['planet_type'] == 'a' || 
				   $planet_to_serve['planet_type'] == 'b' ||
				   $planet_to_serve['planet_type'] == 'c' ||
				   $planet_to_serve['planet_type'] == 'd' ||
				   $planet_to_serve['planet_type'] == 'h' ||
				   $planet_to_serve['planet_type'] == 'k' ||
				   $planet_to_serve['planet_type'] == 'l' ||
				   $planet_to_serve['planet_type'] == 'n'))
				{
					$sql = 'UPDATE planets SET unittrainid_1 = 2, unittrainid_2 = 1,
								   unittrainnumber_1 = 2, unittrainnumber_2 = 1,
								   unittrainnumberleft_1 = 2, unittrainnumberleft_2 = 1,
								   unittrainendless_1 = 1, unittrainendless_2 = 1,
								   unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).',
								   npc_last_action = '.$ACTUAL_TICK.'
						WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL A - 1B '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}


				// If we have a ship in the spacedock, let's make an auction with it. 
				// The query is made on all the planets, in order to free the deleted players spacedocks
				// Search for the oldest ship present in the spacedock.
				$sql = 'SELECT * FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id
				        WHERE fleet_id = -'.$planet_to_serve['planet_id'].' AND
				              ship_untouchable = 0
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
					$_ress_1 = round($t_q['resource_1']*0.16);
					$_ress_2 = round($t_q['resource_2']*0.16);
					$_ress_3 = round($t_q['resource_3']*0.16);
					$_ress_1_step = round($t_q['resource_1']*0.03);
					$_ress_2_step = round($t_q['resource_2']*0.03);
					$_ress_3_step = round($t_q['resource_3']*0.03);
					$sql = 'INSERT INTO ship_trade (user,planet,start_time,end_time,ship_id,resource_1,resource_2,resource_3,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,add_resource_1,add_resource_2,add_resource_3,add_unit_1,add_unit_2,add_unit_3,add_unit_4,add_unit_5,add_unit_6,header,description,show_data,font_bold,font_colored,unowed_only)
					        VALUES   ('.INDEPENDENT_USERID.','.$planet_to_serve['planet_id'].','.$ACTUAL_TICK.','.($ACTUAL_TICK + 480).','.$t_q['ship_id'].','.$_ress_1.','.$_ress_2.','.$_ress_3.', 0, 0, 0, 0,'.$t_q['unit_5'].','.$t_q['unit_6'].','.$_ress_1_step.','.$_ress_2_step.','.$_ress_3_step.',0,0,0,0,0,0,"'.$t_q['name'].'","This is an automatic generated auction for a ship held by the Settlers Community!!!",2,1,1,0)';
					$this->sdl->log('SQL A - 2.1 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					$sql = 'UPDATE ships SET ship_untouchable=1 WHERE ship_id='.$t_q['ship_id'];
					$this->db->query($sql);
					// This update is marginal and it could be removed to decrease the server load
					$sql = 'UPDATE user SET num_auctions=num_auctions+1 WHERE user_id='.INDEPENDENT_USERID;
					$this->db->query($sql);
					$sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 720).' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL A - 2.2 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;	
				}

				// This phase is definitely very heavy in terms of DB accesses, it's better to do that just one time per execution.
				if(!$_already_done)
				{
					$sql = 'SELECT s.user_id, ud.ud_id, u.user_capital, SUM(s.mood_modifier) 
						FROM settlers_relations s
						LEFT JOIN user u ON s.user_id = u.user_id 
						LEFT JOIN user_diplomacy ud ON s.user_id = ud.user2_id AND ud.user1_id = '.INDEPENDENT_USERID.' 
						WHERE s.planet_id = '.$planet_to_serve['planet_id'].' 
					        GROUP BY s.user_id ORDER BY s.timestamp ASC, SUM(s.mood_modifier) DESC LIMIT 0,1';
					$setl_q = $this->db->queryrow($sql);
					if(isset($setl_q['user_id']) && isset($setl_q['user_capital']) && !empty($setl_q['user_id']) && !empty($setl_q['user_capital']))
					{
						$_already_done = true;
						$this->sdl->log('SQL A - 4.0a', TICK_LOG_FILE_NPC);
						$_ress_1 = $_ress_2 = $_ress_3 = $_ress_4 = $_unit_1 = $_unit_2 = $_unit_5 = $_unit_6 = 0;
						$bonus = 1.0;
						if(isset($setl_q['ud_id']) && !empty($setl_q['ud_id']))
						{
							$bonus = 1.5;
						}
						switch($planet_to_serve['planet_type'])
						{
						// Mining planets
						// Supplies 12%-15% of their production to the player with the higher mood
						case "f":
						case "j":
							$_ress_1 = round(0.15*$planet_to_serve['resource_1']);
							$planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
							break;
						case "e":
						case "s":
							$_ress_2 = round(0.15*$planet_to_serve['resource_2']);
							$planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
							break;
						case "i":	
						case "g":
						case "h":
						case "t":
							$_ress_3 = round(0.12*$planet_to_serve['resource_3']);
							$planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
							break;
						case "x":
						case "y":
							$_ress_1 = round(0.15*$planet_to_serve['resource_1']);
							$planet_to_serve['resource_1'] = $planet_to_serve['resource_1'] - $_ress_1;
							$_ress_2 = round(0.15*$planet_to_serve['resource_2']);
							$planet_to_serve['resource_2'] = $planet_to_serve['resource_2'] - $_ress_2;
							$_ress_3 = round(0.12*$planet_to_serve['resource_3']);
							$planet_to_serve['resource_3'] = $planet_to_serve['resource_3'] - $_ress_3;
							break;
						// Mercenary Planets
						// Supplies Technician and Physician
						case "m":
						case "o":
						case "p":
							$_ress_4 = round(0.10*$planet_to_serve['resource_4']);
							$planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
							$_unit_5 = round(0.30*$planet_to_serve['unit_5']);
							$planet_to_serve['unit_5'] = $planet_to_serve['unit_5'] - $_unit_5;
							$_unit_6 = round(0.30*$planet_to_serve['unit_2']);
							$planet_to_serve['unit_6'] = $planet_to_serve['unit_6'] - $_unit_6;
							break;
						// Supplies first and second level troops
						case "a":
						case "b":
						case "c":
						case "d":
						case "h":
						case "k":
						case "l":
						case "n":						    
							$_ress_4 = round(0.10*$planet_to_serve['resource_4']);
							$planet_to_serve['resource_4'] = $planet_to_serve['resource_4'] - $_ress_4;
							$_unit_1 = round(0.30*$planet_to_serve['unit_1']);
							$planet_to_serve['unit_1'] = $planet_to_serve['unit_1'] - $_unit_1;
							$_unit_2 = round(0.30*$planet_to_serve['unit_2']);
							$planet_to_serve['unit_2'] = $planet_to_serve['unit_2'] - $_unit_2;
							break;
						}
						if(($_ress_1 + $_ress_2 + $_ress_3 + $_ress_4 + $_unit_1 + $_unit_2 + $_unit_5 + $_unit_6) > 0)
						{
						$sql = 'INSERT INTO scheduler_resourcetrade (planet,resource_1,resource_2,resource_3,resource_4,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,arrival_time) 
						               VALUES ('.$setl_q['user_capital'].', '.($_ress_1*$bonus).', '.($_ress_2*$bonus).', '.($_ress_3*$bonus).', '.($_ress_4*$bonus).', '.($_unit_1*$bonus).', '.($_unit_2*$bonus).', 0, 0, '.($_unit_5*$bonus).', '.($_unit_6*$bonus).', '.($ACTUAL_TICK + 120).')';
						$this->sdl->log('SQL A - 4.1'.$sql, TICK_LOG_FILE_NPC);
						$this->db->query($sql);
						$sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 500).',
									   resource_1 = '.$planet_to_serve['resource_1'].',
									   resource_2 = '.$planet_to_serve['resource_2'].',
									   resource_3 = '.$planet_to_serve['resource_3'].',
									   resource_4 = '.$planet_to_serve['resource_4'].',
									   unit_1 = '.$planet_to_serve['unit_1'].',
									   unit_2 = '.$planet_to_serve['unit_2'].',
									   unit_5 = '.$planet_to_serve['unit_5'].',
									   unit_6 = '.$planet_to_serve['unit_6'].' 
						        WHERE planet_id = '.$planet_to_serve['planet_id'];
						$this->sdl->log('SQL A - 4.2'.$sql, TICK_LOG_FILE_NPC);
						$this->db->query($sql);
						}
						continue;
					}
				}


				// Let's build a ship!!! We could avoid the use of the scheduler, just to decrease the overhead...
				// In the definitive version, only certain planets should build ships.
				$sql = 'SELECT COUNT(*) as ship_queue FROM scheduler_shipbuild WHERE planet_id = '.$planet_to_serve['planet_id'];
				$s_q = $this->db->queryrow($sql);
				if($s_q['ship_queue'] == 0 && ($planet_to_serve['planet_type'] == 'h' || $planet_to_serve['planet_type'] == 'k' || $planet_to_serve['planet_type'] == 'l'))
				{
					$_buildtime = 65; // Yep, no access to the DB, let's avoid it...
					$sql = 'INSERT INTO scheduler_shipbuild SET ship_type = '.$cfg_data['settler_tmp_1'].', planet_id = '.$planet_to_serve['planet_id'].', start_build = '.$ACTUAL_TICK.', finish_build = '.($ACTUAL_TICK + $_buildtime).', unit_1 = 15, unit_2 = 0, unit_3 = 0, unit_4 = 1';
					$this->sdl->log('SQL A - 3.1 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					$sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 760).' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL A - 3.2 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// We build big combat ships (hull 12)!!!
				if($s_q['ship_queue'] == 0 && ($planet_to_serve['planet_type'] == 'e' || $planet_to_serve['planet_type'] == 'f' || $planet_to_serve['planet_type'] == 'g' || $planet_to_serve['planet_type'] == 'x' || $planet_to_serve['planet_type'] == 'y'))
				{
					$_buildtime = 420; // Yep, no access to the DB, let's avoid it...
					$sql = 'INSERT INTO scheduler_shipbuild SET ship_type = '.$cfg_data['settler_tmp_3'].', planet_id = '.$planet_to_serve['planet_id'].', start_build = '.$ACTUAL_TICK.', finish_build = '.($ACTUAL_TICK + $_buildtime).', unit_1 = 200, unit_2 = 95, unit_3 = 65, unit_4 = 6';
					$this->sdl->log('SQL A - 3.3 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					$sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 1440).' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL A - 3.4 '.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}

				$sql = 'UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 20).' WHERE planet_id = '.$planet_to_serve['planet_id'];
				$this->sdl->log('SQL E'.$sql, TICK_LOG_FILE_NPC);
				$this->db->query($sql);
			}
		}

		$this->sdl->finish_job('Mayflower Planets Building Control', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################

		$this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
	}
}


?>
