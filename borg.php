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

/* 23. May 2008
  @Author: Carolfi - Delogu
  @Action: carry on destruction to the galaxy
*/

define (BORG_SIGNATURE, 'We are the Borg. Lower your shields and surrender your ships.<br>We will add your biological and technological distinctiveness to our own.<br>Your culture will adapt to service us. Resistance is futile.');

define (BORG_SPHERE, 'Borg Sphere');

define (BORG_CUBE, 'Borg Cube');

define (BORG_RACE,'6'); // Well, this one should be defined in global.php among the other races */

/* ######################################################################################## */
/* ######################################################################################## */
// Startconfig of Borg
class Borg extends NPC
{
	public function Execute($debug=0)
	{
		$starttime = ( microtime() + time() );

		// Read debug config
		if($debug_data = $this->db->queryrow('SELECT * FROM borg_debug LIMIT 0,1'))
		{
			if($debug_data['debug']==0 || $debug_data['debug']==1)
				$debug=$debug_data['debug'];
		}

		$game = new game();

		$this->sdl->log("\n".'<b>-------------------------------------------------------------</b>'."\n".
			'<b>Starting Borg Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

		// Bot also enable the life we may need a few more
		$Environment = $this->db->queryrow('SELECT * FROM config LIMIT 0 , 1');
		$ACTUAL_TICK = $Environment['tick_id'];
		$STARDATE = $Environment['stardate'];

		$this->sdl->start_job('SevenOfNine basic system', TICK_LOG_FILE_NPC);

		//Only with adoption Bot has an existence
		if($Environment)
		{
			$this->sdl->log("The conversation with SevenOfNine begins, oh, but I think that there is no possibility to talk with her",
				TICK_LOG_FILE_NPC);
			$Bot_exe=$this->db->query('SELECT * FROM borg_bot LIMIT 0,1');

			// Create BOT table if it doesn't exist
			if($Bot_exe === false)
			{
				$sql = 'CREATE TABLE `'.$this->db->login['database'].'`.`borg_bot` (
				            `id` INT( 2 ) NOT NULL AUTO_INCREMENT ,
				            `user_id` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT \'0\',
				            `planet_id` SMALLINT( 5 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `ship_template1` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `ship_template2` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `user_tick` INT( 10 ) NOT NULL ,
				            PRIMARY KEY (  `id` )
				        ) ENGINE = MYISAM';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> cannot create borg_bot table - ABORTED', TICK_LOG_FILE_NPC);
					return;
				}
			}

			$num_bot=$this->db->num_rows($Bot_exe);
			if($num_bot < 1)
			{
				$sql = 'INSERT INTO borg_bot (user_id,user_tick,planet_id,ship_template1,ship_template2)
				        VALUES ("0","0","0","0","0")';
				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Abort the program because of errors when creating the user', TICK_LOG_FILE_NPC);
					return;
				}
			}

			//So now we give the bot some data so that it is also Registered
			$this->bot = $this->db->queryrow('SELECT * FROM borg_bot LIMIT 0,1');

			//Check whether the bot already lives
			if($this->bot['user_id']==0)
			{
				$this->sdl->log('We need to create SevenOfNine', TICK_LOG_FILE_NPC);

				$sql = 'INSERT INTO user (user_active, user_name, user_loginname, user_password, user_email,
				                          user_auth_level, user_race, user_gfxpath, user_skinpath, user_registration_time,
				                          user_registration_ip, user_birthday, user_gender, plz, country,
				                          user_enable_sig,user_message_sig,
				                         user_signature)
				         VALUES (1, "Borg(NPG)", "BorgBot", "'.md5("borgcube").'", "borg@nonsolotaku.it",
				                 1, '.BORG_RACE.', "", "skin1/", '.time().',
				                 "127.0.0.1", "23.05.2008", "", 16162 , "Italia",
				                 1, "<br><br><p><b>We are the Borg, resistance is futile</b></p>",
				                 "'.BORG_SIGNATURE.'")';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Bot: Could not create SevenOfNine', TICK_LOG_FILE_NPC);
				}
				else
				{
					$sql = 'Select * FROM user WHERE user_name="Borg(NPG)" and user_loginname="BorgBot" and user_auth_level=1';
					$Bot_data = $this->db->queryrow($sql);
					if(!$Bot_data['user_id'])
					{
						$this->sdl->log('<b>Error:</b> The variable $Bot_data has no content', TICK_LOG_FILE_NPC);
						//break;
					}
					$sql = 'UPDATE borg_bot SET user_id="'.$Bot_data['user_id'].'",user_tick="'.$ACTUAL_TICK.'" WHERE id="'.$this->bot['id'].'"';
					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Bot card: Could not change the card', TICK_LOG_FILE_NPC);
					}
					$this->bot = $this->db->queryrow('SELECT * FROM borg_bot');
				}
			} // end user bot creation

			//The bot should also have a body of what looks
			if($this->bot['planet_id']==0)
			{
				$this->sdl->log('<b>SevenOfNine needs new body</b>', TICK_LOG_FILE_NPC);

				while($this->bot['planet_id']==0 or $this->bot['planet_id']=='empty')
				{
					$this->sdl->log('Create new planet', TICK_LOG_FILE_NPC);
					$this->db->lock('starsystems_slots');
					$this->bot['planet_id']=create_planet($this->bot['user_id'], 'quadrant', 2);
					$this->db->unlock();
					if($this->bot['planet_id'] == 0)
					{
						$this->sdl->log('<b>Error:</b> Bot Planet id doesn\'t go', TICK_LOG_FILE_NPC);
						return;
					}

					$sql = 'UPDATE user SET user_points = "400",user_planets = "1",last_active = "5555555555",
					                        user_attack_protection = "'.($ACTUAL_TICK + 14400).'",
					                        user_capital = "'.$this->bot['planet_id'].'",
					                        active_planet = "'.$this->bot['planet_id'].'"
					        WHERE user_id = '.$this->bot['user_id'];

					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Bot body: Planet has not been created', TICK_LOG_FILE_NPC);
					}
					else
					{
						//Bot gets best values for his body, he should also look good
						$this->sdl->log('Give best values to the planet', TICK_LOG_FILE_NPC);
						$sql = 'UPDATE planets SET planet_points = 1200,building_1 = 9,building_2 = 15,building_3 = 15,
							building_4 = 15,building_5 = 16,building_6 = 9,building_7 = 15,building_8 = 9,
							building_9 = 9,building_10 = 35,building_11 = 9,building_12 = 15,building_13 = 35,
							unit_1 = 20000,unit_2 = 20000,unit_3 = 20000,unit_4 = 5000,unit_5 = 5000,unit_6=5000,
							planet_name = "Unimatrix Zero",
							research_1 = 15,research_2 = 15,research_3 = 15,research_4 = 15,research_5 = 9,
							workermine_1 = 1600,workermine_2 = 1600,workermine_3 = 1600,resource_4 = 4000
							WHERE planet_owner = '.$this->bot['user_id'].' and planet_id='.$this->bot['planet_id'];

						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> Bot body: the body could not be improved', TICK_LOG_FILE_NPC);

						$sql = 'UPDATE borg_bot SET planet_id='.$this->bot['planet_id'].' WHERE user_id = '.$this->bot['user_id'];
						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> Bot card: could not change planet info card', TICK_LOG_FILE_NPC);
					}
				} // end while
			} // end planet creation

			//Bot shows whether the ship already has templates
			$reload=0;
			if($this->bot['ship_template1']==0)
			{
				/**
				 * Brief comments of VERY FIRST prototype of Borg Sphere:
				 *
				 * Light weapons: 500
				 * Heavy weapons: 500
				 * Planetary weapons: 50
				 * Hull: 600
				 * Shield: 600
				 *
				 * Reaction: 30
				 * Readiness: 30
				 * Agility: 30
				 * Experience: 20
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 20
				 * Camouflage: 0
				 * Energy available: 200
				 * Energy used: 200
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 50000
				 * Minerals: 50000
				 * Dilithium: 50000
				 * Workers: 500
				 * Technicians: 100
				 * Physicians: 10
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 100
				 * Assault drone: 25
				 * Elite drone: 25
				 * Commander drone: 5
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 300
				 * Assault drone: 70
				 * Elite drone: 50
				 * Commander drone: 10
				 */ 
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				         VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_SPHERE.'","Exploration ship","'.BORG_RACE.'",1,0,
				                 -1,-1,-1,-1,-1,
				                 -1,-1,-1,-1,-1,
				                 "500","500","50","600","600",
				                 "30","30","30","20","10",
				                 "20","0","200","200","0",
				                 "50000","50000","50000","500","100","10",
				                 "100","25","25","5",
				                 "300","70","50",10,
				                 0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 1 was not saved', TICK_LOG_FILE_NPC);
			}
			if($this->bot['ship_template2']==0)
			{
				/**
				 * Brief comments of VERY FIRST prototype of Borg Cube:
				 *
				 * Light weapons: 4000
				 * Heavy weapons: 4000
				 * Planetary weapons: 150
				 * Hull: 6000
				 * Shield: 6000
				 *
				 * Reaction: 60
				 * Readiness: 60
				 * Agility: 10
				 * Experience: 60
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 40
				 * Camouflage: 0
				 * Energy available: 2000
				 * Energy used: 2000
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 500000
				 * Minerals: 500000
				 * Dilithium: 500000
				 * Workers: 50000
				 * Technicians: 10000
				 * Physicians: 1000
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 10000
				 * Assault drone: 2500
				 * Elite drone: 2500
				 * Commander drone: 500
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 30000
				 * Assault drone: 7000
				 * Elite drone: 5000
				 * Commander drone: 1000
				 */ 
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				        VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_CUBE.'","Assimilation ship","'.BORG_RACE.'",3,0,
				                -1,-1,-1,-1,-1,
				                -1,-1,-1,-1,-1,
				                "4000","4000","100","6000","6000",
				                "60","60","60","10","10",
				                "40","0","2000","2000","0",
				                "500000","500000","500000","50000","10000","1000",
				                "10000","2500","2500","500",
				                "30000","7000","5000","1000",
				                0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 2 was not saved', TICK_LOG_FILE_NPC);
			}

			if($reload>0)
			{
				$this->sdl->log('Ship templates built', TICK_LOG_FILE_NPC);
				$Bot_temps=$this->db->query('SELECT id FROM ship_templates s WHERE owner='.$this->bot['user_id']);
				$zaehler_temps=0;
				$Bot_neu = array();

				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[0]=$tempID['id'];
				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[1]=$tempID['id'];


				$sql = 'UPDATE borg_bot SET ship_template1 = '.$Bot_neu[0].',ship_template2 = '.$Bot_neu[1].' WHERE user_id = '.$this->bot['user_id'];
				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: could not save the template id', TICK_LOG_FILE_NPC);
			}
		}else{
			$this->sdl->log('<b>Error:</b> No access to environment table!', TICK_LOG_FILE_NPC);
			return;
		}
		$this->sdl->finish_job('SevenOfNine basic system', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		//PW des Bot änderns - nix angreifen
		$this->ChangePassword();
		// ########################################################################################
		// ########################################################################################
		// Messages answer
		$messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
		$titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

		$this->ReplyToUser($titles,$messages);
		// ########################################################################################
		// ########################################################################################
		//Sensors monitoring and user warning
		$messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
		$titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

		$this->CheckSensors($ACTUAL_TICK,$titles,$messages);
		// ########################################################################################
		// ########################################################################################
		//Ships creation
		$this->sdl->start_job('Creating ships', TICK_LOG_FILE_NPC);
/*		$abfragen=$this->db->query('SELECT * FROM `ship_fleets` WHERE fleet_name="Alpha-Fleet IVX" and user_id='.$this->bot['user_id'].' LIMIT 0, 1');

		if($this->db->num_rows()<=0)
		{
			$sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
				VALUES ("Alpha-Fleet IVX", '.$this->bot['user_id'].', '.$this->bot['planet_id'].', 0, 4000)';
			if(!$this->db->query($sql)) $this->sdl->log('<b>Error:</b> Could not insert new fleets data', TICK_LOG_FILE_NPC);
			$fleet_id = $this->db->insert_id();
			$sql_x5= 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
				VALUES ("Interception -Fleet-Omega", '.$this->bot['user_id'].', '.$this->bot['planet_id'].', 0, 1000)';

			if(!$this->db->query($sql_x5)) $this->sdl->log('<b>Error:</b> Could not insert new fleets data', TICK_LOG_FILE_NPC);
			$fleet_id_x5= $this->db->insert_id();
			if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);
			if(!$fleet_id_x5) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);
			$sql_a= 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_template1'];
			$sql_b= 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_template2'];
			if(($stpl_a = $this->db->queryrow($sql_a)) === false) $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_a, TICK_LOG_FILE_NPC);
			if(($stpl_b = $this->db->queryrow($sql_b)) === false) $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_b, TICK_LOG_FILE_NPC);
			$units_str_1 = $stpl_a['min_unit_1'].', '.$stpl_a['min_unit_2'].', '.$stpl_a['min_unit_3'].', '.$stpl_a['min_unit_4'];
			$units_str_2 = $stpl_b['min_unit_1'].', '.$stpl_b['min_unit_2'].', '.$stpl_b['min_unit_3'].', '.$stpl_b['min_unit_4'];
			$sql_c= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
				VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_template1'].', '.$stpl_a['value_9'].', '.$stpl_a['value_5'].', '.$game->TIME.', '.$units_str_1.')';
			$sql_d= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
				VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_template2'].', '.$stpl_b['value_9'].', '.$stpl_b['value_5'].', '.$game->TIME.', '.$units_str_2.')';
			$sql_x55= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
				VALUES ('.$fleet_id_x5.', '.$this->bot['user_id'].', '.$this->bot['ship_template2'].', '.$stpl_b['value_9'].', '.$stpl_b['value_5'].', '.$game->TIME.', '.$units_str_2.')';
			for($i = 0; $i < 4000; ++$i)
			{
				if($i<400){
					if(!$this->db->query($sql_c)) {
						$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
					}
				}else{
					if(!$this->db->query($sql_d)) {
						$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
					}
				}
			}
			for($i = 0; $i < 1000; ++$i)
			{
				if(!$this->db->query($sql_x55)) {
					$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
				}
			}
			$this->sdl->log('Fleet: '.$fleet_id.' - 2000 ships created', TICK_LOG_FILE_NPC);
		}
		$flotte=$this->db->fetchrow($abfragen);
		if($flotte['n_ships']<4000)
		{
			$gebraucht=4000-$flotte['n_ships'];
			$sql = 'UPDATE ship_fleets SET n_ships = n_ships + '.$gebraucht.' WHERE fleet_id = '.$flotte['fleet_id'];
			if(!$this->db->query($sql)) $this->sdl->log('<b>Error:</b> Could not update new fleets data', TICK_LOG_FILE_NPC);
			$sql_b= 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_template2'];
			if(($stpl = $this->db->queryrow($sql_b)) === false) $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_b, TICK_LOG_FILE_NPC);
			$units_str = $stpl['min_unit_1'].', '.$stpl['min_unit_2'].', '.$stpl['min_unit_3'].', '.$stpl['min_unit_4'];
			$sql_d= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
				VALUES ('.$flotte['fleet_id'].', '.$this->bot['user_id'].', '.$this->bot['ship_template2'].', '.$stpl['value_9'].', '.$stpl['value_5'].', '.$game->TIME.', '.$units_str.')';
			for($i = 0; $i < $gebraucht; ++$i)
			{
					if(!$this->db->query($sql_d)) {
						$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
					}
			}
			$this->sdl->log('Fleet: '.$flotte['fleet_id'].' -to '.$gebraucht.' ships updated', TICK_LOG_FILE_NPC);
		}*/
		$this->sdl->finish_job('Creating ships', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################

		$this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font>'."\n".'Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
	}
}


?>
