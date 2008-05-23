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
//Changelog sonst kapier ich bei Ramona bald nix mehr - Frauen eben

/* 14. Juni 2007
  @Thema: Truppenverkaufszahlen Graphenbrechnung
  @Action: geändert  bzw verbessert
*/

/* ######################################################################################## */
/* ######################################################################################## */
// Startconfig of NPCs
class NPC
{
	var $db;

	var $sdl;

	var $bot = array();

	function MessageUser($sender,$receiver, $header, $message)
	{
		$game = new game();
		if ($this->db->query('INSERT INTO message (sender, receiver, subject, text, rread,time) VALUES ('.$sender.',"'.$receiver.'","'.addslashes($header).'","'.addslashes($message).'",0,'.$game->TIME.')')==false)
		{
			$this->sdl->log('systemmessage_query: Could not call INSERT INTO in message :-:INSERT INTO message (sender, receiver, subject, text, rread,time)
				VALUES ('.$sender.',"'.$receiver.'","'.addslashes($header).'","'.addslashes($message).'",0, '.$game->TIME.')', TICK_LOG_FILE_NPC);
		}
		$this->sdl->log("Senderid:".$sender." // Receiverid:".$receiver, TICK_LOG_FILE_NPC);
		$num=$this->db->queryrow('SELECT COUNT(id) as unread FROM message WHERE (receiver="'.$receiver.'") AND (rread=0)');
		if($num['unread']>0) $this->db->query('UPDATE user SET unread_messages="'.$num['unread'].'" WHERE user_id="'.$receiver.'"');
		$this->sdl->log("Num:".$num['unread'], TICK_LOG_FILE_NPC);

		return true;
	}
	
	function ChangePassword()
	{
		$this->sdl->start_job('PW change', TICK_LOG_FILE_NPC);
		$wert_new=rand(1,9);
		$zufall="vv";
		if($wert_new==2) $wert_new='v'.$zufall.'h';
		if($wert_new==3) $wert_new='a'.$zufall.'h';
		if($wert_new==4) $wert_new='b'.$zufall.'h';
		if($wert_new==5) $wert_new='c'.$zufall.'h';
		if($wert_new==6) $wert_new='d'.$zufall.'h';
		if($wert_new==7) $wert_new='e'.$zufall.'h';
		if($wert_new==1) $wert_new='f'.$zufall.'h';
		if($wert_new==8) $wert_new='g'.$zufall.'h';
		if($wert_new==9) $wert_new='h'.$zufall.'h';
		if($this->db->query('UPDATE `user` SET `user_password`=MD5("'.$wert_new.'") WHERE `user_id` ='.$this->bot['user_id'].''))
		{
			$this->sdl->log('Now there are only One-Night-Stands, no longer relations', TICK_LOG_FILE_NPC);
		}
		$this->sdl->finish_job('PW change', TICK_LOG_FILE_NPC);
	}

	function ReplyToUser($messageArray,$titleArray)
	{
		$this->sdl->start_job('Messages answer', TICK_LOG_FILE_NPC);
		$msgs_number=0;
		$sql = 'SELECT * FROM message WHERE receiver='.$this->bot['user_id'].' AND rread=0';
		if(!$q_message = $this->db->query($sql))
		{
			$this->sdl->log('<b>Error:</b> IGM: Could not query messages', TICK_LOG_FILE_NPC);
		}else{
			while($message = $this->db->fetchrow($q_message))
			{
				// Recover language of the sender
				$sql = 'SELECT language FROM user WHERE user_id='.$message['sender'];
				if(!($language = $this->db->queryrow($sql)))
					$this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);

				switch($language['language'])
				{
					case 'GER':
						$text=$messageArray[1];
						$title=$titleArray[1];
					break;
					case 'ITA':
						$text=$messageArray[2];
						$title=$titleArray[2];
					break;
					default:
						$text=$messageArray[0];
						$title=$titleArray[0];
					break;
				}

				$this->MessageUser($this->bot['user_id'],$message['sender'],$title,$text);
				$msgs_number++;
			}
		}
		$sql = 'UPDATE message SET rread=1 WHERE receiver='.$this->bot['user_id'];
		if(!$this->db->query($sql))$this->sdl->log('<b>Error:</b> Message could not set to read', TICK_LOG_FILE_NPC);
		$this->sdl->log('Number of messages:'.$msgs_number, TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Messages answer', TICK_LOG_FILE_NPC);
	}

	function CheckSensors($ACTUAL_TICK,$messageArray,$titleArray)
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
			$sql='SELECT planet_owner,planet_name FROM `planets` WHERE planet_owner ='.$attacker['user_id'].' LIMIT 0 , 1';
			$target_planet=$this->db->queryrow($sql);

			// Recover language of the sender
			$sql = 'SELECT language FROM user WHERE user_id='.$attacker['user_id'];
			if(!($language = $this->db->queryrow($sql)))
				$this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);

			switch($language['language'])
			{
				case 'GER':
					$text=$messageArray[1];
					$title=$titleArray[1];
				break;
				case 'ITA':
					$text=$messageArray[2];
					$title=$titleArray[2];
				break;
				default:
					$text=$messageArray[0];
					$title=$titleArray[0];
				break;
			}

			$this->MessageUser($this->bot['user_id'],$attacker['user_id'],$title,
				str_replace("<TARGETPLANET>",$target_planet['planet_name'],$text));
		}
		$this->sdl->log('Number of messages:'.$msgs_number, TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Sensors monitor', TICK_LOG_FILE_NPC);
	}

	public function NPC(&$db, &$sdl)
	{
		$this->db = $db;
		$this->sdl = $sdl;
	}
}


?>
