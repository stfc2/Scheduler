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

/* 14. Juni 2007
  @Thema: Truppenverkaufszahlen Graphenbrechnung
  @Action: geändert  bzw verbessert
*/

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
	
	function ChangePassword()
	{
		$this->sdl->start_job('PW change', TICK_LOG_FILE_NPC);
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
				TICK_LOG_FILE_NPC);
		}
		$this->sdl->finish_job('PW change', TICK_LOG_FILE_NPC);
	}

	function ReplyToUser($titles,$messages)
	{
		$this->sdl->start_job('Messages answer', TICK_LOG_FILE_NPC);
		$msgs_number=0;
		$sql = 'SELECT * FROM message
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
		$sql='SELECT * FROM `scheduler_shipmovement` WHERE user_id>9 AND 
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

	function CreateFleet($name,$template,$num)
	{
		$this->sdl->log('Check fleet "'.$name.'" composition', TICK_LOG_FILE_NPC);
		$query='SELECT fleet_id FROM `ship_fleets` WHERE fleet_name="'.$name.'" AND user_id='.$this->bot['user_id'].' LIMIT 0, 1';
		$fleet = $this->db->query($query);
		if($this->db->num_rows()<=0)
		{
			$sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
				VALUES ("'.$name.'", '.$this->bot['user_id'].', '.$this->bot['planet_id'].', 0, '.$num.')';
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Error:</b> Could not insert new fleet data', TICK_LOG_FILE_NPC);
			$fleet_id = $this->db->insert_id();

			if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);

			$sql= 'SELECT * FROM `ship_templates` WHERE `id` = '.$template;
			if(($stpl = $this->db->queryrow($sql)) === false)
				$this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

			$sql= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
			                          unit_1, unit_2, unit_3, unit_4)
			       VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$template.', '.$stpl['value_9'].',
			               '.$stpl['value_5'].', '.time().', '.$stpl['min_unit_1'].', '.$stpl['min_unit_2'].',
			               '.$stpl['min_unit_3'].', '.$stpl['min_unit_4'].')';
			for($i = 0; $i < $num; ++$i)
			{
				if(!$this->db->query($sql)) {
					$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
				}
			}
			$this->sdl->log('Fleet: '.$fleet_id.' - '.$num.' ships created', TICK_LOG_FILE_NPC);
		}

		return $fleet_id;
	}

	function RestoreFleetLosses($name,$template,$num)
	{
		$query='SELECT * FROM `ship_fleets` WHERE fleet_name="'.$name.'" and user_id='.$this->bot['user_id'].' LIMIT 0, 1';
		$fleet=$this->db->queryrow($query);
		if($fleet['n_ships'] < $num)
		{
			$this->sdl->log('Fleet "'.$name.'" has only '.$fleet['n_ships'].' ships - we need restore', TICK_LOG_FILE_NPC);
			$needed = $num - $fleet['n_ships'];

			$sql = 'UPDATE ship_fleets SET n_ships = n_ships + '.$needed.' WHERE fleet_id = '.$fleet['fleet_id'];
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Error:</b> Could not update new fleets data', TICK_LOG_FILE_NPC);

			$sql = 'SELECT * FROM ship_templates WHERE id = '.$template;
			if(($stpl = $this->db->queryrow($sql)) === false)
				$this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_b, TICK_LOG_FILE_NPC);

			$units_str = $stpl['min_unit_1'].', '.$stpl['min_unit_2'].', '.$stpl['min_unit_3'].', '.$stpl['min_unit_4'];
			$sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience,
			                           hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
			        VALUES ('.$fleet['fleet_id'].', '.$this->bot['user_id'].', '.$template.', '.$stpl['value_9'].',
			                '.$stpl['value_5'].', '.time().', '.$units_str.')';

			for($i = 0; $i < $needed; ++$i)
			{
				if(!$this->db->query($sql)) {
					$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
				}
			}
			$this->sdl->log('Fleet: '.$fleet['fleet_id'].' - updated to '.$needed.' ships', TICK_LOG_FILE_NPC);
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

	public function NPC(&$db, &$sdl)
	{
		$this->db = $db;
		$this->sdl = $sdl;
	}
}


?>
