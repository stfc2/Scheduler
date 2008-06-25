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

		$this->sdl->log("\n".'<b>-------------------------------------------------------------</b>'."\n".
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
				$this->sdl->log('We need to create SevenOfNine', TICK_LOG_FILE_NPC);

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
					$this->sdl->log('<b>Error:</b> Bot: Could not create SevenOfNine', TICK_LOG_FILE_NPC);
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
		$messages=array('Bot system.','Bot system.','Bot system.');
		$titles=array('--','--','--');

		$this->ReplyToUser($titles,$messages);
		// ########################################################################################
		// ########################################################################################
		// Read Logbook

		$this->ReadLogbook();
		// ########################################################################################
		// ########################################################################################

		$this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font>'."\n".'Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
	}
}


?>
