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


//#######################################################################################
//#######################################################################################
//Changelog:

include('NPC_BOT_defines.php');

//#######################################################################################
//#######################################################################################
// Startconfig of NPCs
class NPC
{
	var $db;

	var $sdl;

	var $bot = array();

	function MessageUser($sender,$receiver, $header, $message)
	{
		$header = addslashes($header);
		$message = addslashes($message);

		// Avoid sending the message if the receiver is another BOT
		if(($receiver == FERENGI_USERID) ||
			($receiver == INDEPENDENT_USERID) ||
			($receiver == BORG_USERID)) return;

		$sql = 'INSERT INTO message (sender, receiver, subject, text, rread, time)
		        VALUES ('.$sender.','.$receiver.',"'.$header.'","'.$message.'",0,'.time().')';

		if ($this->db->query($sql)==false)
		{
			$this->sdl->log('<b>Error:</b> Cannot send message to user '.$receiver.'!',
				TICK_LOG_FILE_NPC);
		}
		$this->sdl->log("Senderid:".$sender." // Receiverid:".$receiver,
			TICK_LOG_FILE_NPC);

		$num=$this->db->queryrow('SELECT COUNT(id) as unread FROM message
		                          WHERE (receiver="'.$receiver.'") AND (rread=0)');
		if($num['unread']>0)
			$this->db->query('UPDATE user SET unread_messages="'.$num['unread'].'"
			                  WHERE user_id="'.$receiver.'"');

		$this->sdl->log("Num:".$num['unread'], TICK_LOG_FILE_NPC);

		return true;
	}

	function ChangePassword($log = TICK_LOG_FILE_NPC)
	{
		$this->sdl->start_job('PW change', $log);
		$new=rand(1,9);
		$random="vv";
		if($new==2) $new='v'.$random.'h';
		if($new==3) $new='a'.$random.'h';
		if($new==4) $new='b'.$random.'h';
		if($new==5) $new='c'.$random.'h';
		if($new==6) $new='d'.$random.'h';
		if($new==7) $new='e'.$random.'h';
		if($new==1) $new='f'.$random.'h';
		if($new==8) $new='g'.$random.'h';
		if($new==9) $new='h'.$random.'h';
		if($this->db->query('UPDATE `user` SET `user_password`=MD5("'.$new.'")
		                     WHERE `user_id` ='.$this->bot['user_id']))
		{
			$this->sdl->log('Now there are only One-Night-Stands, no longer relations',
				$log);
		}
		$this->sdl->finish_job('PW change', $log);
	}

	function ReplyToUser($titles,$messages)
	{
		$this->sdl->start_job('Messages answer', TICK_LOG_FILE_NPC);
		$msgs_number=0;
		$sql = 'SELECT sender FROM message
		        WHERE receiver='.$this->bot['user_id'].' AND rread=0';

		if(!$q_message = $this->db->query($sql))
		{
			$this->sdl->log('<b>Error:</b> IGM: Could not query messages',
				TICK_LOG_FILE_NPC);
		}else{
			while($message = $this->db->fetchrow($q_message))
			{
				// Recover language of the sender
				$sql = 'SELECT language FROM user WHERE user_id='.$message['sender'];
				if(!($language = $this->db->queryrow($sql)))
					$this->sdl->log('<b>Error:</b> Cannot read user language!',
						TICK_LOG_FILE_NPC);

				switch($language['language'])
				{
					case 'GER':
						$text=$messages[1];
						$title=$titles[1];
					break;
					case 'ITA':
						$text=$messages[2];
						$title=$titles[2];
					break;
					default:
						$text=$messages[0];
						$title=$titles[0];
					break;
				}

				$this->MessageUser($this->bot['user_id'],$message['sender'],$title,$text);
				$msgs_number++;
			}
		}
		$sql = 'UPDATE message SET rread=1 WHERE receiver='.$this->bot['user_id'];
		if(!$this->db->query($sql))
			$this->sdl->log('<b>Error:</b> Message could not set to read',
				TICK_LOG_FILE_NPC);
		$this->sdl->log('Number of messages:'.$msgs_number, TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Messages answer', TICK_LOG_FILE_NPC);
	}

	function CheckSensors($ACTUAL_TICK,$titles,$messages)
	{
		$this->sdl->start_job('Sensors monitor', TICK_LOG_FILE_NPC);
		$msgs_number=0;
		$sql='SELECT user_id FROM `scheduler_shipmovement` WHERE user_id>9 AND 
			move_status=0 AND move_exec_started!=1 AND move_finish>'.$ACTUAL_TICK.' AND  dest="'.$this->bot['planet_id'].'"';
		$attackers=$this->db->query($sql);
		while($attacker = $this->db->fetchrow($attackers))
		{
			$this->sdl->log('The User '.$attacker['user_id'].' is trying to attack bot planet', TICK_LOG_FILE_NPC);
			$msgs_number++;
			$sql='SELECT planet_owner,planet_name FROM `planets`
			      WHERE planet_owner ='.$attacker['user_id'].' LIMIT 0 , 1';
			$target_planet=$this->db->queryrow($sql);

			// Recover language of the sender
			$sql = 'SELECT language FROM user WHERE user_id='.$attacker['user_id'];
			if(!($language = $this->db->queryrow($sql)))
				$this->sdl->log('<b>Error:</b> Cannot read user language!',
					TICK_LOG_FILE_NPC);

			switch($language['language'])
			{
				case 'GER':
					$text=$messages[1];
					$title=$titles[1];
				break;
				case 'ITA':
					$text=$messages[2];
					$title=$titles[2];
				break;
				default:
					$text=$messages[0];
					$title=$titles[0];
				break;
			}

			$this->MessageUser($this->bot['user_id'],$attacker['user_id'],$title,
				str_replace("<TARGETPLANET>",$target_planet['planet_name'],$text));
		}
		$this->sdl->log('Number of messages:'.$msgs_number, TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Sensors monitor', TICK_LOG_FILE_NPC);
	}

	function CreateFleet($name,$template,$num,$planet_id = 0)
	{
		// No planet? Assume Bot's one
		if($planet_id == 0) $planet_id = $this->bot['planet_id'];

		$this->sdl->log('Check fleet "'.$name.'" composition', TICK_LOG_FILE_NPC);
		$query='SELECT fleet_id FROM `ship_fleets` WHERE fleet_name="'.$name.'" AND user_id='.$this->bot['user_id'].' LIMIT 0, 1';
		$q_fleet = $this->db->query($query);
		if($this->db->num_rows()<=0)
		{
			$sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
				VALUES ("'.$name.'", '.$this->bot['user_id'].', '.$planet_id.', 0, '.$num.')';
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Error:</b> Could not insert new fleet data', TICK_LOG_FILE_NPC);
			$fleet_id = $this->db->insert_id();

			if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);

			$sql= 'SELECT max_unit_1, max_unit_2, max_unit_3, max_unit_4, rof, max_torp,
			              value_5, value_9
			       FROM `ship_templates` WHERE `id` = '.$template;
			if(($stpl = $this->db->queryrow($sql)) === false)
				$this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

			$sql= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
			                          rof, torp, unit_1, unit_2, unit_3, unit_4)
			       VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$template.', '.$stpl['value_9'].',
			               '.$stpl['value_5'].', '.time().', '.$stpl['rof'].', '.$stpl['max_torp'].', 
			               '.$stpl['max_unit_1'].', '.$stpl['max_unit_2'].',
			               '.$stpl['max_unit_3'].', '.$stpl['max_unit_4'].')';
			for($i = 0; $i < $num; ++$i)
			{
				if(!$this->db->query($sql)) {
					$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
				}
			}
			$this->sdl->log('Fleet: '.$fleet_id.' - '.$num.' ships created', TICK_LOG_FILE_NPC);
		}
		else {
			$fleet = $this->db->fetchrow($q_fleet);
			$fleet_id = $fleet['fleet_id'];
		}

		return $fleet_id;
	}

	function RestoreFleetLosses($name,$template,$num)
	{
		$query='SELECT fleet_id, n_ships FROM `ship_fleets` WHERE fleet_name="'.$name.'" and user_id='.$this->bot['user_id'].' LIMIT 0, 1';
		$fleet=$this->db->queryrow($query);
		if (empty($fleet)) {
			$this->sdl->log('<u>Warning:</u> Fleet: '.$name.' does not exists! - SKIP', TICK_LOG_FILE_NPC);
			return;
		}

		if($fleet['n_ships'] < $num)
		{
			$this->sdl->log('Fleet "'.$name.'" has only '.$fleet['n_ships'].' ships - we need restore', TICK_LOG_FILE_NPC);
			$needed = $num - $fleet['n_ships'];

			$sql = 'UPDATE ship_fleets SET n_ships = n_ships + '.$needed.' WHERE fleet_id = '.$fleet['fleet_id'];
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Error:</b> Could not update new fleets data', TICK_LOG_FILE_NPC);

			$sql = 'SELECT min_unit_1, min_unit_2, min_unit_3, min_unit_4, rof, max_torp,
			               value_5, value_9
			        FROM ship_templates WHERE id = '.$template;
			if(($stpl = $this->db->queryrow($sql)) === false)
				$this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

			if (empty($stpl))
				$this->sdl->log('<b>Error:</b> Could not found template '.$template.'!', TICK_LOG_FILE_NPC);
			else {
				$units_str = $stpl['min_unit_1'].', '.$stpl['min_unit_2'].', '.$stpl['min_unit_3'].', '.$stpl['min_unit_4'];
				$sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience,
						                   hitpoints, construction_time, rof, torp, unit_1, unit_2, unit_3, unit_4)
						VALUES ('.$fleet['fleet_id'].', '.$this->bot['user_id'].', '.$template.', '.$stpl['value_9'].',
						        '.$stpl['value_5'].', '.time().', '.$stpl['rof'].', '.$stpl['max_torp'].', '.$units_str.')';

				for($i = 0; $i < $needed; ++$i)
				{
					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
					}
				}
				$this->sdl->log('Fleet: '.$fleet['fleet_id'].' - updated to '.$needed.' ships', TICK_LOG_FILE_NPC);
			}
		}
	}

	function RepairFleet($name)
	{
		$query='SELECT fleet_id FROM `ship_fleets` WHERE fleet_name="'.$name.'" and user_id='.$this->bot['user_id'].' LIMIT 0, 1';
		$fleet=$this->db->queryrow($query);
		$sql='SELECT s.ship_id, st.value_5, st.max_torp FROM ships s LEFT JOIN ship_templates st ON s.template_id = st.id WHERE fleet_id = '.$fleet['fleet_id'];
		$ships=$this->db->query($sql);
		while($ship_to_check = $this->db->fetchrow($ships))
		{
			$sql='UPDATE ships SET hitpoints = '.$ship_to_check['value_5'].', torp = '.$ship_to_check['max_torp'].' WHERE ship_id = '.$ship_to_check['ship_id'];
			$this->db->query($sql);
		}
	}

	function ReadLogbook()
	{
		$this->sdl->start_job('Read Logbook', TICK_LOG_FILE_NPC);
		$sql = 'UPDATE logbook SET log_read=1 WHERE user_id='.$this->bot['user_id'];
		if(!$this->db->query($sql))
			$this->sdl->log('<b>Error:</b> Logbook message could not be set to read',
				TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Read Logbook', TICK_LOG_FILE_NPC);
	}

	function StartBuild($ACTUAL_TICK,$building,$planet)
	{
		global $MAX_BUILDING_LVL;
		$res = BUILD_ERR_DB;

		// Building queue full?
		$this->db->queryrow('SELECT installation_type FROM scheduler_instbuild WHERE planet_id='.$planet['planet_id']);
		if ($this->db->num_rows() >= BUILDING_QUEUE_LEN)
			return BUILD_ERR_QUEUE;

		// Retrieve some BOT infos
		$sql = 'SELECT user_race, user_capital, pending_capital_choice FROM user WHERE user_id='.$this->bot['user_id'];
		$userdata=$this->db->queryrow($sql);
		$race = $userdata['user_data'];

		// Planet selected is capital or not?
		$capital=(($userdata['user_capital']==$planet['planet_id']) ? 1 : 0);
		if ($userdata['pending_capital_choice']) $capital=0;

		// Check if building hasn't reached max level
		if($planet['building_'.($building+1)] == $MAX_BUILDING_LVL[$capital][$building])
			return BUILD_ERR_MAXLEVEL;

		// Calculate resources needed for the building
		$resource_1 = GetBuildingPrice($building,0,$planet,$race);
		$resource_2 = GetBuildingPrice($building,1,$planet,$race);
		$resource_3 = GetBuildingPrice($building,2,$planet,$race);

		// Check resources availability
		if ($planet['resource_1']>=$resource_1 &&
		    $planet['resource_2']>=$resource_2 &&
		    $planet['resource_3']>=$resource_3)
		{
			// Calculate planet power consumption
			$buildings=$planet['building_1']+$planet['building_2']+$planet['building_3']+$planet['building_4']+
				   $planet['building_10']+$planet['building_6']+$planet['building_7']+$planet['building_8']+
				   $planet['building_9']+$planet['building_11']+$planet['building_12']+$planet['building_13'];

			/* I think we don't need this check here...
			if (($building==11 && $planet['building_1']<4) ||
			($building==10 && $planet['building_1']<3) ||
			($building==6 && $planet['building_1']<5) ||
			($building==8 && $planet['building_1']<9) ||
			($building==7 && $planet['building_7']<1) ||
			($building==9 && ($planet['building_6']<5 ||$planet['building_7']<1)) ||
			($building==12 && ($planet['building_6']<1 || $planet['building_7']<1)) )
			{
				return BUILD_ERR_REQUIRED;
			}*/

			// If we are building a power plant, or there is energy still available
			if ($building==4 || $buildings<=($capital ? $planet['building_5']*11+14 : $planet['building_5']*15+3))
			{
				// Remove resources needed from the planet
				$sql = 'UPDATE planets SET
				               resource_1=resource_1-'.$resource_1.',
				               resource_2=resource_2-'.$resource_2.',
				               resource_3=resource_3-'.$resource_3.'
				        WHERE planet_id= '.$planet['planet_id'];

				if (!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Cannot remove resources need for construction from planet #'.$planet['planet_id'].'!', TICK_LOG_FILE_NPC);

				// Check planet activity
				$sql = 'SELECT build_finish FROM scheduler_instbuild
				        WHERE planet_id='.$planet['planet_id'].'
				        ORDER BY build_finish DESC';
				$userquery=$this->db->queryrow($sql);
				if ($this->db->num_rows()>0)
				{
					$build_start = $userquery['build_finish'];
					// BOT take a little bonus here: future level of the building is not
					// considered, also for simplicity.
					$build_finish = $build_start + GetBuildingTimeTicks($building,$planet,$race);
				}
				else {
					$build_start = $ACTUAL_TICK;
					$build_finish = $build_start + GetBuildingTimeTicks($building,$planet,$race);
				}

				$sql = 'INSERT INTO scheduler_instbuild (installation_type,planet_id,build_start,build_finish)
					    VALUES ("'.$building.'",
					            "'.$planet['planet_id'].'",
					            "'.$build_start.'",
					            "'.$build_finish.'")';

				if (!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> cannot add building <b>#'.$building.'</b> to the planet <b>#'.$planet['planet_id'].'</b>',
						TICK_LOG_FILE_NPC);
				else {
					$this->sdl->log('Construction of <b>#'.$building.'</b> started on planet <b>#'.$planet['planet_id'].'</b>',
						TICK_LOG_FILE_NPC);
					$res = BUILD_SUCCESS;
				}
			}
			else {
				$this->sdl->log('Insufficient energy on planet <b>#'.$planet['planet_id'].'</b> for building <b>#'.$building.'</b>',
					TICK_LOG_FILE_NPC);
				$res = BUILD_ERR_ENERGY;
			}
		}
		else {
			$this->sdl->log('Insufficient resources on planet <b>#'.$planet['planet_id'].'</b> for building <b>#'.$building.'</b>',
				TICK_LOG_FILE_NPC);
			$res = BUILD_ERR_RESOURCES;
		}
		return $res;
	}

	/**
	 * NOTE: Use this function ONLY if you want to use NPC class without BOT core execution.
	 */
	function LoadNPCUserData($botID)
	{
		// Initialize $bot attribute
		$this->bot = $this->db->queryrow('SELECT user_id FROM user WHERE user_id = '.$botID);
	}

	public function NPC(&$db, &$sdl)
	{
		$this->db = $db;
		$this->sdl = $sdl;
	}
}


?>
