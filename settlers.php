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
	public function Execute($debug=0)
	{
		$starttime = ( microtime() + time() );

		$this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
			'<b>Starting Settlers Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

		// Bot also enable the life we may need a few more
		$Environment = $this->db->queryrow('SELECT * FROM config LIMIT 0 , 1');
		$ACTUAL_TICK = $Environment['tick_id'];
		$STARDATE = $Environment['stardate'];

		$this->sdl->start_job('Mayflower basic system', TICK_LOG_FILE_NPC);

		//Only with adoption Bot has an existence
		if($Environment)
		{
			//So now we give the bot some data so that it is also Registered
			$this->bot = $this->db->queryrow('SELECT * FROM user WHERE user_id = '.INDEPENDENT_USERID);

			//Check whether the bot already lives
			if($this->bot['user_id']==0)
			{
				$this->sdl->log('We need to create TheSettlers!', TICK_LOG_FILE_NPC);

				$sql = 'INSERT INTO user (user_id, user_active, user_name, user_loginname, user_password,
				                          user_email, user_auth_level, user_race, user_gfxpath, user_skinpath,
				                          user_registration_time, user_registration_ip,
				                          user_birthday, user_gender, plz, country, user_enable_sig,
				                          user_message_sig, user_signature)
				         VALUES ('.INDEPENDENT_USERID.', 1, "Coloni(NPG)", "SettlersBot", "'.md5("settlers").'",
				                 "settlers@nonsolotaku.it", 1, 13, "", "skin1/", '.time().', "127.0.0.1",
				                 "25.06.2008", "", 16162 , "Italia", 1,
				                 "",  "")';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Bot: Could not create TheSettlers', TICK_LOG_FILE_NPC);
				}
			} // end user bot creation
		}else{
			$this->sdl->log('<b>Error:</b> No access to environment table!', TICK_LOG_FILE_NPC);
			return;
		}
		$this->sdl->finish_job('Mayflower basic system', TICK_LOG_FILE_NPC);
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
		
		$sql='SELECT * FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' ORDER BY npc_last_action ASC LIMIT 0, 3';
		
		if(($setpoint = $this->db->query($sql)) === false)
			{
				$this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
			}
		else
		{
			while($planet_to_serve = $this->db->fetchrow($setpoint))
			{
				if($planet_to_serve['building_5'] < 9) 
				{
					$sql = 'UPDATE planets SET building_5 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				if($planet_to_serve['building_1'] < 9) 
				{
					$sql = 'UPDATE planets SET building_1 = 9, npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 1.1 '.$sql, TICK_LOG_FILE_NPC);
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
				// Funge la funzione StartBuild????
				// Sviluppiamo le strutture sul pianeta: Controllo
			    /*
				if($planet_to_serve['building_1'] < 9) 
				{
					$this->StartBuild($ACTUAL_TICK, 0, $planet_to_serve['planet_id'];
					continue;
				}
				// Metalli
				elseif($planet_to_serve['building_2'] < 9)
				{
					$this->StartBuild($ACTUAL_TICK, 1, $planet_to_serve['planet_id'];
					continue;
				}
				// Minerali
				elseif($planet_to_serve['building_3'] < 9)
				{
					$this->StartBuild($ACTUAL_TICK, 2, $planet_to_serve['planet_id'];
					continue;
				}
				// Dilitio
				elseif($planet_to_serve['building_4'] < 9)
				{
					$this->StartBuild($ACTUAL_TICK, 3, $planet_to_serve['planet_id'];
					continue;
				}
				*/
				
				//A questo punto, avendo ricostruito il controllo e le miniere, i Settler guadagnano in automatico l'Accademia al liv 5.
				if($planet_to_serve['building_6'] < 5) 
				{
					$sql = 'UPDATE planets SET building_6 = 5, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 2'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				//Adesso lo Spazioporto
				if($planet_to_serve['building_7']< 3) 
				{
					$sql = 'UPDATE planets SET building_7 = 3, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 3'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				//ed ora il Cantiere
				if($planet_to_serve['building_8'] < 1) 
				{
					$sql = 'UPDATE planets SET building_8 = 1, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 4'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				//Difese orbitali leggere
				if($planet_to_serve['building_10'] < 14) 
				{
					$sql = 'UPDATE planets SET building_10 = 14, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 5'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				//Difese orbitali pesanti
				if($planet_to_serve['building_13'] < 14) 
				{
					$sql = 'UPDATE planets SET building_13 = 14, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 6'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				// Ci sono abbastanza lavoratori nelle miniere?
				if($planet_to_serve['workermine_1'] < 1000 || $planet_to_serve['workermine_2'] < 1000 || $planet_to_serve['workermine_3'] < 1000)
				{
					$sql = 'UPDATE planets SET workermine_1 = 1000, workermine_2 = 1000, workermine_3 = 1000, npc_last_action = '.$ACTUAL_TICK.', recompute_static = 1 WHERE planet_id = '.$planet_to_serve['planet_id'];
					$this->sdl->log('SQL 7'.$sql, TICK_LOG_FILE_NPC);
					$this->db->query($sql);
					continue;
				}
				
				$sql = 'UPDATE planets SET npc_last_action = '.$ACTUAL_TICK.' WHERE planet_id = '.$planet_to_serve['planet_id'];
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
