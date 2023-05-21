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

/* 23. May 2008
  @Author: Carolfi - Delogu
  @Action: carry on destruction to the galaxy
*/

/* ######################################################################################## */
/* ######################################################################################## */
// Startconfig of Borg
class Borg extends NPC
{
    // Function to create BOT structures
    public function Install($log = INSTALL_LOG_FILE_NPC)
    {
        // We don't use the global variable here since this function can be called also
        // by the installation script.
        $environment = $this->db->queryrow('SELECT * FROM config LIMIT 0 , 1');
        $ACTUAL_TICK = $environment['tick_id'];
        $FUTURE_SHIP = $environment['future_ship'];

        $this->sdl->start_job('SevenOfNine basic system', $log);

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
                        `ship_template3` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
                        `user_tick` INT( 10 ) NOT NULL ,
                        `attack_quadrant` TINYINT( 3 ) UNSIGNED NOT NULL DEFAULT  \''.BORG_QUADRANT.'\',
                        `attacked_user1` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
                        `attacked_user2` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
                        `attacked_user3` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
                        `attacked_user4` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
                        `last_attacked` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT  \'0\',
                        `wrath_size` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT  \'30\',
                        PRIMARY KEY (  `id` )
                    ) ENGINE = MYISAM';

            if(!$this->db->query($sql))
            {
                $this->sdl->log('<b>Error:</b> cannot create borg_bot table - ABORTED', $log);
                return;
            }
        }

        $num_bot=$this->db->num_rows($Bot_exe);
        if($num_bot < 1)
        {
            $sql = 'INSERT INTO borg_bot (user_id,user_tick, tick_last_colo, planet_id,ship_template1,ship_template2,ship_template3)
                    VALUES ("0","0","12000","0","0","0","0")';
            if(!$this->db->query($sql))
            {
                $this->sdl->log('<b>Error:</b> could not insert borg_bot data - ABORTED', $log);
                return;
            }
        }

        //So now we give the bot some data so that it is also Registered
        $this->bot = $this->db->queryrow('SELECT * FROM borg_bot LIMIT 0,1');

        //Check whether the bot already lives
        if($this->bot['user_id']==0)
        {
            $this->sdl->log('We need to create SevenOfNine', $log);

            $sql = 'INSERT INTO user (user_id, user_active, user_name, user_loginname, user_password, user_email,
                                      user_auth_level, user_race, user_gfxpath, user_skinpath, user_registration_time,
                                      user_registration_ip, user_birthday, user_gender, plz, country,
                                      user_enable_sig,user_message_sig,
                                      user_signature, user_notepad, user_options, message_basement)
                     VALUES ('.BORG_USERID.', 1, "Borg(NPC)", "BorgBot", "'.md5("borgcube").'", "borg@stfc.it",
                             '.STGC_BOT.', '.BORG_RACE.', "/stfc_gfx/", "skin1/", '.time().',
                             "127.0.0.1", "23.05.2008", "", 16162 , "IT",
                             1, "<br><br><p><b>We are the Borg, resistance is futile</b></p>",
                             "'.BORG_SIGNATURE.'","", "", "")';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not create SevenOfNine - ABORTED', $log);
                return;
            }

            $sql = 'UPDATE borg_bot
                    SET user_id="'.BORG_USERID.'",
                        user_tick="'.$ACTUAL_TICK.'"
                    WHERE id="'.$this->bot['id'].'"';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not update borg_bot table - ABORTED', $log);
                return;
            }

            // Avoid a DB query
            $this->bot['user_id'] = BORG_USERID;
        } // end user bot creation

        /*
        // Check whether the bot has a planet
        if($this->bot['planet_id'] == 0) {
            $this->sdl->log('<b>SevenOfNine needs new body</b>', $log);

            while($this->bot['planet_id']==0 or $this->bot['planet_id']=='empty')
            {
                $this->sdl->log('Create new planet', $log);
                $this->db->lock('starsystems_slots');
                $this->bot['planet_id']=create_planet($this->bot['user_id'], 'quadrant', BORG_QUADRANT);
                $this->db->unlock();
                if($this->bot['planet_id'] == 0)
                {
                    $this->sdl->log('<b>Error:</b> could not create SevenOfNine\'s planet - ABORTED', $log);
                    return;
                }

                $sql = 'UPDATE user
                        SET user_points = "400",
                            user_planets = "1",
                            last_active = "4294967295",
                            user_capital = "'.$this->bot['planet_id'].'",
                            active_planet = "'.$this->bot['planet_id'].'"
                        WHERE user_id = '.$this->bot['user_id'];

                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not update SevenOfNine\'s attack protection info - CONTINUED', $log);
                }

                // That one is for resetting the Borg Targets List
                $sql = 'TRUNCATE TABLE borg_target';
                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not reset borg_target - CONTINUED', $log);
                }

                //Bot gets best values for his body, he should also look good
                $this->sdl->log('Give best values to the planet', $log);
                $sql = 'UPDATE planets SET planet_points = 1200,building_1 = 9,building_2 = 15,building_3 = 15,
                        building_4 = 15,building_5 = 16,building_6 = 9,building_7 = 15,building_8 = 9,
                        building_9 = 9,building_10 = 35,building_11 = 9,building_12 = 15,building_13 = 35,
                        unit_1 = 20000,unit_2 = 10000,unit_3 = 5000,unit_4 = 5000,unit_5 = 0,unit_6 = 0,
                        planet_name = "Unimatrix #'.$this->bot['planet_id'].'",
                        research_1 = 15,research_2 = 15,research_3 = 15,research_4 = 15,research_5 = 9,
                        workermine_1 = 1600,workermine_2 = 1600,workermine_3 = 1600,resource_4 = 4000
                        WHERE planet_owner = '.$this->bot['user_id'].' and planet_id='.$this->bot['planet_id'];

                if(!$this->db->query($sql))
                    $this->sdl->log('<b>Error:</b> could not improve SevenOfNine\'s planet - CONTINUED', $log);

                $sql = 'UPDATE borg_bot SET planet_id='.$this->bot['planet_id'].' WHERE user_id = '.$this->bot['user_id'];
                if(!$this->db->query($sql))
                    $this->sdl->log('<b>Error:</b> could not update SevenOfNine ID card with planet info - CONTINUED', $log);
            } // end while
        } // end planet creation
        
         * 
         */

        // Check whether the ship already has templates
        $reload=0;
        if($this->bot['ship_template1']==0)
        {
            /**
             * Brief comments of SECOND prototype of Borg Sphere:
             *
             * Light weapons: 420
             * Heavy weapons: 1176
             * Planetary weapons: 8
             * Hull: 2970
             * Shield: 6241
             *
             * Reaction: 8
             * Readiness: 17
             * Agility: 36
             * Experience: 50
             * Warp: 10 (Borg has transwarp engines)
             *
             * Sensors: 48
             * Camouflage: 0
             * Energy available: 160
             * Energy used: 140
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
             * Drone simple: 196
             * Assault drone: 0
             * Elite drone: 0
             * Commander drone: 4
             *
             * Maximum crew:
             *
             * Drone simple: 196
             * Assault drone: 0
             * Elite drone: 0
             * Commander drone: 4
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
                                                buildtime, rof, rof2, max_torp)
                     VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_SPHERE.'","Exploration ship","'.BORG_RACE.'",6,2,
                             -1,-1,-1,-1,-1,
                             -1,-1,-1,-1,-1,
                             "420","1176","8","2970","6241",
                             "8","17","36","50","10",
                             "48","0","160","140","0",
                             "50000","50000","50000","500","100","10",
                             "196","0","0","4",
                             "196","0","0","4",
                             0, 8, 6, 85)';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not save BOT template 1 - ABORTED', $log);
                return;
            }

            // Update ship template id with the freshly created one
            $this->bot['ship_template1'] = $this->db->insert_id();
        }
        if($this->bot['ship_template2']==0)
        {
            /**
             * Brief comments of SECOND prototype of Borg Cube:
             *
             * Light weapons: 420
             * Heavy weapons: 1176
             * Planetary weapons: 140
             * Hull: 7796
             * Shield: 11441
             *
             * Reaction: 8
             * Readiness: 20
             * Agility: 22
             * Experience: 50
             * Warp: 10 (Borg has transwarp engines)
             *
             * Sensors: 42
             * Camouflage: 0
             * Energy available: 300
             * Energy used: 300
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
             * Drone simple: 294
             * Assault drone: 0
             * Elite drone: 0
             * Commander drone: 6
             *
             * Maximum crew:
             *
             * Drone simple: 9294
             * Assault drone: 6000
             * Elite drone: 3000
             * Commander drone: 6
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
                                                buildtime, rof, rof2, max_torp)
                    VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_CUBE.'","Assimilation ship","'.BORG_RACE.'",9,3,
                            -1,-1,-1,-1,-1,
                            -1,-1,-1,-1,-1,
                            "420","1176","140","7796","11441",
                            "8","20","22","50","10",
                            "42","0","300","300","0",
                            "500000","500000","500000","50000","39","16",
                            "300","0","0","6",
                            "300","0","0","6",
                            0, 21, 11, 1000)';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> Could not save BOT template 2 - ABORTED', $log);
                return;
            }

            // Update ship template id with the freshly created one
            $this->bot['ship_template2'] = $this->db->insert_id();
        }

        if($this->bot['ship_template3']==0)
        {
            /**
             * Brief comments of THIRD prototype of Borg Cube:
             *
             * Light weapons: 420
             * Heavy weapons: 1176
             * Planetary weapons: 140
             * Torpedoes: 800
             * ROF: 16
             * Hull: 12623
             * Shield: 22882
             *
             * Reaction: 8
             * Readiness: 28
             * Agility: 15
             * Experience: 50
             * Warp: 10 (Borg has transwarp engines)
             *
             * Sensors: 70
             * Camouflage: 0
             * Energy available: 600
             * Energy used: 600
             *
             * Resources needed for construction:
             *
             * Metal: 500000
             * Minerals: 500000
             * Dilithium: 500000
             * Workers: 50000
             * Technicians: 250
             * Physicians: 250
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
             * Drone simple: 65000   <--- Max 65535
             * Assault drone: 21000
             * Elite drone: 15000
             * Commander drone: 5000
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
                                                buildtime, rof, rof2, max_torp)
                    VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_TACT.'","Combat Ship","'.BORG_RACE.'",11,3,
                            -1,-1,-1,-1,-1,
                            -1,-1,-1,-1,-1,
                            "4200","11760","140","300000","500000",
                            "8","28","15","50","10",
                            "70","0","600","600","0",
                            "500000","500000","500000","50000","250","250",
                            "0","0","500","12",
                            "0","0","500","12",
                            0, 54, 38, 5000)';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> Could not save BOT template 3 - ABORTED '.$sql, $log);
                return;
            }

            // Update ship template id with the freshly created one
            $this->bot['ship_template3'] = $this->db->insert_id();
        }

        if($this->bot['unimatrixzero_tp']==0)
        {
            /**
             * Brief comments of FIRST prototype of Unimatrix Zero:
             *
             * Light weapons: 1
             * Heavy weapons: 1
             * Planetary weapons: 1
             * Torpedoes: 25000
             * ROF: 25
             * Hull: 850000
             * Shield: 850000
             *
             * Reaction: 5
             * Readiness: 80
             * Agility: 5
             * Experience: 100
             * Warp: 10 (Borg has transwarp engines)
             *
             * Sensors: 80
             * Camouflage: 0
             * Energy available: 10000
             * Energy used: 10000
             *
             * Resources needed for construction:
             *
             * Metal: 1
             * Minerals: 1
             * Dilithium: 1
             * Workers: 1
             * Technicians: 1
             * Physicians: 1
             *
             * Minimum crew:
             *
             * Drone simple: 65000
             * Assault drone: 25000
             * Elite drone: 25000
             * Commander drone: 5000
             *
             * Maximum crew:
             *
             * Drone simple: 65000   <--- Max 65535
             * Assault drone: 25000
             * Elite drone: 25000
             * Commander drone: 5000
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
            									buildtime, rof, rof2, max_torp)
            		VALUES ('.$this->bot['user_id'].','.time().', "'.BORG_UM0.'", "Main Borg Base", '.BORG_RACE.',12,3,
            				-1, -1, -1, -1, -1,
            				-1, -1, -1, -1, -1,
            				1, 1, 1, 850000, 850000,
            				5, 80, 5, 100, 10,
            				80, 0, 10000, 10000, 0,
            				1, 1, 1, 1, 1, 1,
            				65000, 25000, 25000, 5000,
            				65000, 25000, 25000, 5000,
            				0, 25, 25, 25000)';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> Could not save BOT Unimatrix Zero - ABORTED '.$sql, $log);
                return;
            }

            // Update ship template id with the freshly created one
            $this->bot['unimatrixzero_tp'] = $this->db->insert_id();
        }

        if($reload > 0) {
            $this->sdl->log('Ship templates built', $log);

            $sql = 'UPDATE borg_bot
                    SET ship_template1 = '.$this->bot['ship_template1'].',
                        ship_template2 = '.$this->bot['ship_template2'].',
                        ship_template3 = '.$this->bot['ship_template3'].',
                        unimatrixzero_tp = '.$this->bot['unimatrixzero_tp'].'
                    WHERE user_id = '.$this->bot['user_id'];
            if(!$this->db->query($sql))
                $this->sdl->log('<b>Error:</b> Could not update SevenOfNine ID card with ship templates info - CONTINUED', $log);
        }
            
        // ########################################################################################
        // ########################################################################################
        // Change the BOT password
        $this->ChangePassword($log);

        // Future Humans
        $this->sdl->start_job('Future Humans Setup&Maintenance',$log);

        if($FUTURE_SHIP == 0) {
            $sql = 'SELECT count(*) AS fhship_check FROM ship_templates
                    WHERE template_id = '.$FUTURE_SHIP;
            $result = $this->db->queryrow($sql);
            if($result['fhship_check'] == 0) {
                $sql = 'INSERT INTO user (user_id, user_active, user_name, user_loginname, user_password, user_email,
                                          user_auth_level, user_race, user_gfxpath, user_skinpath, user_registration_time,
                                          user_registration_ip, user_birthday, user_gender, plz, country,
                                          user_enable_sig,user_message_sig,
                                          user_signature, user_notepad, user_options, message_basement)
                         VALUES (7, 1, "FutureHumans(NPC)", "FutureHumansBot", "'.md5("PromForAll").'", "futurehumans@stfc.it",
                                 '.STGC_BOT.', 12, "/stfc_gfx/", "skin1/", '.time().',
                                 "127.0.0.1", "23.05.2008", "", 16162 , "IT",
                                 1, "No More Borg in the Galaxy",
                                 "","", "", "")';

                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not create FutureHumans ID', $log);
                    return;
                }                
                // We put the Future Humans Ship Template in the DB
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
                        VALUES ("'.FUTURE_HUMANS_UID.'","'.time().'","Prometeus","Anti-Borg, Multirole, Heavy Assault Ship",12,11,3,
                                -1,-1,-1,-1,-1,
                                -1,-1,-1,-1,-1,
                                "425","1280","0","38500","26500",
                                "65","35","75","10","9.9",
                                "65","0","480","480","0",
                                "35000","300000","300000","2900","36","20",
                                "0","0","120","5",
                                "45","35","155","8",
                                4800, 46, 46, 850)';

                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> could not insert Future Humans Ship Template - ABORTED', $log);
                    return;
                }

                $FUTURE_SHIP = $this->db->insert_id();

                if(!$this->db->query('UPDATE config SET future_ship = '.$FUTURE_SHIP))
                    $this->sdl->log('<b>Error:</b> could not update future ship template id - CONTINUED',$log);

                $this->sdl->log('Template created with id '.$FUTURE_SHIP,$log);
            }
        }

        $this->sdl->finish_job('Future Humans Setup&Maintenance',$log);
    }

    public function Execute($debug=0)
    {
    global $ACTUAL_TICK,$FUTURE_SHIP;

            $starttime = ( microtime() + time() );

            // Read debug config
            if($debug_data = $this->db->queryrow('SELECT * FROM borg_debug LIMIT 0,1'))
            {
                    if($debug_data['debug']==0 || $debug_data['debug']==1)
                            $debug=$debug_data['debug'];
            }

            $this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
                    '<b>Starting Borg Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

    $this->bot = $this->db->queryrow('SELECT * FROM borg_bot LIMIT 0,1');
    if($this->bot)
        $this->sdl->log("The conversation with SevenOfNine begins, oh, but I think that there is no possibility to talk with her",TICK_LOG_FILE_NPC);
    else {
        $this->sdl->log('<b>Error:</b> no access to the bot table - ABORTED', TICK_LOG_FILE_NPC);
        return;
    }
        
// ########################################################################################
// ########################################################################################
// UM0 Check

    if($this->bot['shutdown'] == 1) {
        $this->sdl->log('Bot is shut down', TICK_LOG_FILE_NPC);
        return;      
    }
    else {
        $sql = 'SELECT COUNT(*) AS cnt, fleet_id, planet_id FROM ship_fleets
                WHERE fleet_name = "Unimatrix Zero" AND user_id = '.$this->bot['user_id'];
        $f_c = $this->db->queryrow($sql);
        if($f_c['cnt'] == 0) {
            $this->sdl->log('UM0 Fleet is no longer, shutting down all operation', TICK_LOG_FILE_NPC);
            // Load Borg Planet List
            $borg_pl = $this->db->queryrowset('SELECT planet_id FROM planets WHERE planet_owner = '.BORG_USERID);
            // Surrender all controlled planet to Settlers
            $this->db->query('UPDATE planets SET planet_owner = '.INDEPENDENT_USERID.' WHERE planet_owner = '.BORG_USERID);
            foreach ($borg_pl AS $borg_planet){
                // Insert into planet_details historical record log_code 30                
                $sql = 'INSERT INTO planet_details (planet_id, user_id, timestamp, log_code)
                                            VALUES ('.$borg_planet['planet_id'].', '.BORG_USERID.', '.time().', 30)';
                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> Could not create historical record for borg planet <b>'.$borg_planet['planet_id'].'</b>!'.$sql, TICK_LOG_FILE_NPC);
                }
                // Insert into settlers_relations Founder Record log_code 30
                $sql = 'INSERT INTO settlers_relations (planet_id, race_id, user_id, timestamp, log_code, mood_modifier)
                                                VALUES ('.$borg_planet['planet_id'].', 13, '.INDEPENDENT_USERID.', '.time().', 30, 80)';
                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> Could not create Settlers Founder data for <b>'.$borg_planet['planet_id'].'</b>!'.$sql, TICK_LOG_FILE_NPC);
                }                
                // Delete all ships in the building queue
                $sql = 'DELETE FROM scheduler_shipbuild WHERE planet_id = '.$borg_planet['planet_id'];                
                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> Could not create Settlers Founder data for <b>'.$borg_planet['planet_id'].'</b>!'.$sql, TICK_LOG_FILE_NPC);
                }                
            }
            // Dumping TOP TEN Borg Fighters RAW data
            $ttbf = $this->db->queryrowset('SELECT user_name, threat_level FROM borg_target INNER JOIN user USING (user_id) ORDER BY threat_level DESC LIMIT 0,10');
            $this->sdl->log('!!! TOP TEN Borg Target RAW data DUMP !!!', TICK_LOG_FILE_NPC);
            foreach ($ttbf AS $ttbf_item) {
                $this->sdl->log('Player '.$ttbf_item['user_name'].' with a threat level of '.$ttbf_item['threat_level'], TICK_LOG_FILE_NPC);
            }
            // Delete all fleets belonging to Borg
            if(!$this->db->query('DELETE FROM scheduler_shipmovement WHERE user_id = '.BORG_USERID)) {
                $this->sdl->log('<b>Error:</b> could not delete borg moves', TICK_LOG_FILE_NPC);
            }            
            // Delete all ships belonging to Borg
            if(!$this->db->query('DELETE FROM ships WHERE user_id = '.BORG_USERID)) {
                $this->sdl->log('<b>Error:</b> could not delete borg ships', TICK_LOG_FILE_NPC);
            }
            // Delete all fleets belonging to Borg
            if(!$this->db->query('DELETE FROM ship_fleets WHERE user_id = '.BORG_USERID)) {
                $this->sdl->log('<b>Error:</b> could not delete borg fleets', TICK_LOG_FILE_NPC);
            }            
            // borg_target table deletion
            if(!$this->db->query('TRUNCATE TABLE borg_target')) {
                $this->sdl->log('<b>Error:</b> could not reset borg_target', TICK_LOG_FILE_NPC);
            }
            // borg_npc_target table deletion
            if(!$this->db->query('TRUNCATE TABLE borg_npc_target')) {
                $this->sdl->log('<b>Error:</b> could not reset borg_npc_target', TICK_LOG_FILE_NPC);
            }
            // SHUTTING DOWN BOT!
            $this->db->query('UPDATE borg_bot SET shutdown = 1');
            return;
        }
        else {
            $this->bot['fleet_id'] = $f_c['fleet_id'];                 
        }
    }

// ########################################################################################
// ########################################################################################
/*
// Settlers Check

    $flag_a1         = false; // if true, bot can attack settlers planets
    $max_live_attack = 0; // Numero massimo di attacchi da portare contemporaneamente

    $sql = 'SELECT count(*) as user_planets FROM planets WHERE planet_owner = '.BORG_USERID;

    if(($res = $this->db->queryrow($sql)) === false)
    {
            $this->sdl->log('<b>Error:</b> Bot: Could not read Borg data', TICK_LOG_FILE_NPC);
    }
    elseif($res['user_planets'] < BORG_MAXPLANETS) {
        $flag_a1 = true;
        $max_live_attack = floor($res['user_planets'] / 18);
    }
*/
    
    $res = $this->db->queryrow('SELECT COUNT(*) AS user_planets FROM planets WHERE planet_owner = '.BORG_USERID);
    $this->bot['user_planets'] = $res['user_planets'];
    
// Colonization Check
    
    //$colotest = 198 - floor(($ACTUAL_TICK - $this->bot['tick_last_colo']) / 120);    
    
    $colotest = ($this->bot['tick_last_colo'] > 0 ? $this->bot['tick_last_colo'] - 1 : 0);
    $this->sdl->log('DEBUG:BORG_1: colotest = '.$colotest, TICK_LOG_FILE_NPC);
    $this->db->query('UPDATE borg_bot SET tick_last_colo = '.$colotest);
    
// Number of available fleets check
    
    $res = $this->db->queryrow('SELECT COUNT(*) AS n_fleets FROM ship_fleets WHERE user_id = '.BORG_USERID);
    $this->bot['n_fleets'] = $res['n_fleets'] - 1;
    
// Idle check value
    
    $idle_check = min(1*$this->bot['n_fleets'], 64);
    
    
// ########################################################################################
// ########################################################################################
// Messages answer        
    $messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
    $titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

    $this->ReplyToUser($titles,$messages);
    
// ########################################################################################
// ########################################################################################
//Sensors monitoring and user warning
/*
        $messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
        $titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

        //$this->CheckSensors($ACTUAL_TICK,$titles,$messages);

        //
        // 13/11/08 - AC: Stop sending ONLY messages to nasty players! ^^
        //
        $this->sdl->start_job('Sensors monitor', TICK_LOG_FILE_NPC);
        $msgs_number=0;
        $sql='SELECT user_id FROM `scheduler_shipmovement`
              WHERE user_id>9 AND
                    move_status=0 AND
                    move_exec_started!=1 AND
                    move_finish>'.$ACTUAL_TICK.' AND
                    dest="'.$this->bot['planet_id'].'"';
        $attackers=$this->db->query($sql);
        while($attacker = $this->db->fetchrow($attackers))
        {
                $this->sdl->log('The User '.$attacker['user_id'].' is trying to attack bot planet', TICK_LOG_FILE_NPC);
                // Check the user presence in the borg_target table

                $acheck = $this->db->queryrow('SELECT COUNT(*) AS is_present FROM borg_target WHERE user_id = '.$attacker['user_id']);

                if($acheck['is_present'] == 0) {
                        // Add the attacker in the borg_target table
                        $this->db->query('INSERT INTO borg_target (user_id, last_check) VALUES ("'.$attacker['user_id'].'", 0)');
                        $msgs_number++;

                        $diplospeech = $this->db->queryrowset('SELECT planet_id FROM settlers_relations WHERE user_id = '.$attacker['user_id'].' AND log_code = 2');
                        
                        foreach ($diplospeech AS $diploitem) {
                            $this->db->query('INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$diploitem['planet_id'].', '.$attacker['user_id'].', '.time().', 22, 10)');
                        }
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

                        $this->MessageUser($this->bot['user_id'],$attacker['user_id'],$title, $text);
                }
        }
        $this->sdl->log('Number of "messages" sent:'.$msgs_number, TICK_LOG_FILE_NPC);
        $this->sdl->finish_job('Sensors monitor', TICK_LOG_FILE_NPC);
        
*/
// ########################################################################################
// ########################################################################################
// Starsystem checking 
    
    $this->sdl->start_job('Starsystems check', TICK_LOG_FILE_NPC); 
    // Controlliamo che il sistema del "pianeta madre" sia "pulito"

    $sql = 'SELECT DISTINCT planet_id, planet_owner FROM planets WHERE system_id = '.$this->bot['system_id'].' AND planet_owner > 10';

    $res = $this->db->queryrowset($sql);

    foreach($res AS $invader) {

        $this->db->query('INSERT INTO borg_target (user_id, last_check) VALUES ("'.$invader['planet_owner'].'", 0)');

        $diplospeech = $this->db->queryrowset('SELECT planet_id FROM settlers_relations WHERE user_id = '.$attacker['user_id'].' AND log_code = 2');

        foreach ($diplospeech AS $diploitem) {
            $this->db->query('INSERT INTO settlers_relations (planet_id, user_id, timestamp, log_code, mood_modifier) VALUES ('.$diploitem['planet_id'].', '.$attacker['user_id'].', '.time().', 22, 10)');
        }            

        $sql = 'INSERT INTO borg_npc_target (planet_id, tries, live_attack, primary_planet, priority) 
                VALUES ("'.$invader['planet_id'].'", 0, 0, 2, 0)
                ON DUPLICATE KEY UPDATE primary_planet = 2';

        $this->db->query($sql);

    }

    $this->sdl->finish_job('Starsystems check', TICK_LOG_FILE_NPC);        
    
// ########################################################################################
// ########################################################################################
// Create Sentry Fleet
    
    /*
    $this->sdl->start_job('Create Sentry Fleet', TICK_LOG_FILE_NPC);
 
    $sql = 'SELECT DISTINCT ship_fleets.fleet_id
            FROM ship_fleets
            INNER JOIN ships ON ships.fleet_id = ship_fleets.fleet_id
            LEFT JOIN planets ON planets.planet_id = ship_fleets.planet_id
            LEFT JOIN scheduler_shipmovement ON ship_fleets.move_id = scheduler_shipmovement.move_id
            LEFT JOIN planets p2 ON scheduler_shipmovement.start = p2.planet_id
            LEFT JOIN planets p3 ON scheduler_shipmovement.dest  = p3.planet_id
            WHERE fleet_name LIKE "Fleet Node#%S" AND
                  (planets.system_id = '.$this->bot['system_id'].' OR
                  (p2.system_id = '.$this->bot['system_id'].' AND
                   p3.system_id = '.$this->bot['system_id'].')
                  )
            LIMIT 0,1';
    
    $res = $this->db->queryrow($sql);
    
    if(!isset($res['fleet_id']) || empty($res['fleet_id'])) {
        $this->sdl->log('DEBUG:BORG_1: Creating a new Sentry Fleet from UM0:'.$sql, TICK_LOG_FILE_NPC);
        
        $sql = 'SELECT planet_id FROM ship_fleets WHERE fleet_id = '.$this->bot['fleet_id'];
        
        $q_planet = $this->db->queryrow($sql);
                
        $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships, alert_phase, homebase)
                VALUES ("Fleet Node#'.(rand(0,3999)).'S", '.BORG_USERID.', '.$q_planet['planet_id'].', 0, 1, 2, 0)';

        if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not insert new fleet data', TICK_LOG_FILE_NPC);}

        $fleet_id = $this->db->insert_id();
        
        // We move one Tact Cube from UM0 Fleet to the new sentry fleet
        $sql = 'SELECT ship_id FROM ships INNER JOIN ship_templates ON ships.template_id = ship_templates.id
                WHERE ship_torso = 11 AND ship_class = 3 AND fleet_id = '.$this->bot['fleet_id'].' LIMIT 2,1';
        
        $res = $this->db->queryrow($sql);
        
        $sql = 'UPDATE ships SET fleet_id = '.$fleet_id.' WHERE ship_id = '.$res['ship_id'];
        
        if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not move Tact Cube into new Sentry Fleet '.$sql, TICK_LOG_FILE_NPC);}
            
        // We move three Cubes from UM0 Fleet to the new sentry fleet
        $sql = 'SELECT ship_id FROM ships INNER JOIN ship_templates ON ships.template_id = ship_templates.id
                WHERE ship_torso = 9 AND ship_class = 3 AND fleet_id = '.$this->bot['fleet_id'].' LIMIT 6,3';        
        
        $res = $this->db->queryrowset($sql);
        
        foreach ($res AS $res_item) {
            $sql = 'UPDATE ships SET fleet_id = '.$fleet_id.' WHERE ship_id = '.$res_item['ship_id'];

            if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not move Cube into new Sentry Fleet '.$sql, TICK_LOG_FILE_NPC);}            
        }
        
        // We move fourteen Spheres from UM0 Fleet to the new sentry fleet
        $sql = 'SELECT ship_id FROM ships INNER JOIN ship_templates ON ships.template_id = ship_templates.id
                WHERE ship_torso = 6 AND ship_class = 2 AND fleet_id = '.$this->bot['fleet_id'].' LIMIT 28,14';        
        
        $res = $this->db->queryrowset($sql);
        
        foreach ($res AS $res_item) {
            $sql = 'UPDATE ships SET fleet_id = '.$fleet_id.' WHERE ship_id = '.$res_item['ship_id'];

            if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not move Sphere into new Sentry Fleet '.$sql, TICK_LOG_FILE_NPC);}            
        }        
        
    }

    $this->sdl->finish_job('Create Sentry Fleet', TICK_LOG_FILE_NPC);
     * 
     */
// ########################################################################################
// ########################################################################################
// Create defences for BOT planets
    
        $this->sdl->start_job('Create Borg defences on assimilated planets', TICK_LOG_FILE_NPC);

        // We need many infos here, for StartBuild() function
        $sql = 'SELECT planet_id, system_id, planet_type,
                       building_1, building_2, building_3, building_4, building_5,
               building_6, building_7, building_8, building_9, building_10,
               building_11, building_12, building_13,
               resource_1, resource_2, resource_3, resource_4,
               research_3, research_4,
               workermine_1, workermine_2, workermine_3,
               unittrain_actual
                FROM planets
                WHERE npc_last_action < '.$ACTUAL_TICK.' AND planet_owner = '.BORG_USERID.' ORDER BY npc_last_action ASC LIMIT 0, 3';

        $planets = $this->db->query($sql);

        // Select each planet
        while($planet = $this->db->fetchrow($planets))
        {
                if($planet['resource_4'] > 100) {
                    $mine = 0;
                    $max_reached = 0;
                    $miners_updated = false;
                    $miners[0] = $planet['workermine_1'];
                    $miners[1] = $planet['workermine_2'];
                    $miners[2] = $planet['workermine_3'];
                    $workers = $planet['resource_4'];
                    $mines_level[0] = $planet['building_2'];
                    $mines_level[1] = $planet['building_3'];
                    $mines_level[2] = $planet['building_4'];

                    while($workers > 100 && $max_reached < 3) {
                        // If there is space for new workers
                        // $this->sdl->log('mine = '.$mine.' miners[mine] = '.$miners[$mine].' mines_level[mine] = '.$mines_level[$mine].' workers = '.$workers, TICK_LOG_FILE_NPC);
                        if($miners[$mine] < (($mines_level[$mine]*100)+100)) {
                            $miners[$mine]+=100;
                            $workers-=100;
                            $miners_updated = true;
                        }
                        // There is no space available, perhaps we can increase the mine level?
                        else {
                            $max_reached++;
                        }
                        $mine++;
                        if($mine > 2)
                            $mine = 0;
                    }

                    // If we exit from the while because there isn't space available on the mines
                    if($max_reached && ($mines_level[0] < 9 || $mines_level[1] < 9 || $mines_level[2] < 9)) {
                        // Search the mine with lowest level
                        $min = $mines_level[0];
                        $mine = 1;
                        for($i = 0;$i < 3;$i++)
                            if($mines_level[$i] < $min)
                            {
                                $min = $mines_level[$i];
                                $mine = $i + 1;
                            }

                        // Start to build a new mine level
                        $res = $this->StartBuild($ACTUAL_TICK,$mine,$planet);
                        if($res == BUILD_ERR_ENERGY)
                            $res = $this->StartBuild($ACTUAL_TICK,4,$planet);
                    }

                    if($miners_updated) {
                        $sql='UPDATE planets SET
                                     workermine_1 = '.$miners[0].',
                                     workermine_2 = '.$miners[1].',
                                     workermine_3 = '.$miners[2].',
                                     resource_4 = '.$workers.'
                              WHERE planet_id = '.$planet['planet_id'];

                        $this->sdl->log('Mines workers increased to: '.$miners[0].'/'.$miners[1].'/'.$miners[2].' on Borg planet: <b>#'.$planet['planet_id'].'</b>', TICK_LOG_FILE_NPC);

                        if(!$this->db->query($sql)) {
                            $this->sdl->log('<b>Error:</b> could not update mines workers!', TICK_LOG_FILE_NPC);
                        }
                    }
                }            
                
                if($planet['building_7'] < 9) {
                        $res = $this->StartBuild($ACTUAL_TICK,6,$planet);
                        if($res == BUILD_ERR_ENERGY)
                                $res = $this->StartBuild($ACTUAL_TICK,4,$planet);
                }
                else
                {
                        switch($planet['planet_type'])
                        {
                        case "e":
                        case "f":
                        case "g":
                        case "l":
                        case "k":
                        case "m":
                        case "o":
                        case "p":
                                if($planet['unittrain_actual'] == 0)
                                {
                                        $sql = 'UPDATE planets SET	unittrainid_1 = 3, unittrainid_2 = 2,
                                                                                                unittrainnumber_1 = 2, unittrainnumber_2 = 1,
                                                                                                unittrainnumberleft_1 = 2, unittrainnumberleft_2 = 1,
                                                                                                unittrainendless_1 = 1, unittrainendless_2 = 1,
                                                                                                unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).'
                                                        WHERE planet_id = '.$planet['planet_id'];
                                        $this->db->query($sql);
                                }
                                break;
                        }
                }
                    
                // Build some orbital guns
                if($planet['building_10'] < (15 + $planet['research_3'])) {
                        $res = $this->StartBuild($ACTUAL_TICK,9,$planet);
                        if($res == BUILD_ERR_ENERGY)
                                $res = $this->StartBuild($ACTUAL_TICK,4,$planet);
                }
                if($planet['building_13'] < (15 + $planet['research_3'])) {
                        $res = $this->StartBuild($ACTUAL_TICK,12,$planet);
                        if($res == BUILD_ERR_ENERGY)
                                $res = $this->StartBuild($ACTUAL_TICK,4,$planet);
                }
                                
                switch($planet['planet_type'])
                {
                    case "e":
                    case "f":
                    case "g":
                    case "l":
                    case "k":
                    case "m":
                    case "o":
                    case "p":
                        $prod_chance = 34;
                        $sql = 'SELECT `fleet_id`, `planet_id`, `fleet_name` FROM `ship_fleets` WHERE `user_id` = '.BORG_USERID.' AND `fleet_name` = "Fleet Node#'.$planet['planet_id'].'" LIMIT 0,1';
                        $fleet = $this->db->queryrow($sql);
                        
                        if(!empty($fleet['fleet_id'])) {$is_fleet_on = true;} else {$is_fleet_on = false;}
                        
                        if(!$is_fleet_on) {
                            $this->sdl->log('DEBUG:BORG_1: Fleet Missing for planet #'.$planet['planet_id'], TICK_LOG_FILE_NPC);
                            $prod_chance = 100;
                        }
                        
                        $sql = 'SELECT s.ship_id, st.max_unit_1, st.max_unit_2, st.max_unit_3, st.max_unit_4
                                FROM ships s 
                                INNER JOIN ship_templates st ON s.template_id = st.id 
                                WHERE st.owner = '.BORG_USERID.' AND
                                      st.ship_torso = 9 AND
                                      s.fleet_id = -'.$planet['planet_id'];
                        
                        $ships = $this->db->queryrowset($sql);
                        
                        foreach ($ships as $ship) {
                            $this->sdl->log('DEBUG:BORG_1: New Cube '.$ship['ship_id'].' found on planet #'.$planet['planet_id'], TICK_LOG_FILE_NPC);
                            if(!$is_fleet_on) {
                                $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships, alert_phase, homebase)
                                        VALUES ("Fleet Node#'.$planet['planet_id'].'", '.BORG_USERID.', '.$planet['planet_id'].', 0, 1, 2, '.$planet['planet_id'].')';

                                if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not insert new fleet data', TICK_LOG_FILE_NPC);}

                                $fleet_id = $this->db->insert_id();

                                if(!$fleet_id) {
                                    $this->sdl->log('DEBUG:BORG_3: - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);
                                }
                                else
                                {
                                    $is_fleet_on = true;
                                    $fleet['fleet_id'] = $fleet_id;
                                }                                

                                $sql = 'UPDATE ships SET unit_1 = '.$ship['max_unit_1'].', 
                                                         unit_2 = '.$ship['max_unit_2'].', 
                                                         unit_3 = '.$ship['max_unit_3'].',
                                                         unit_4 = '.$ship['max_unit_4'].', 
                                                         fleet_id = '.$fleet_id.' 
                                        WHERE ship_id = '.$ship['ship_id'];

                                if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not insert new fleet data', TICK_LOG_FILE_NPC);}

                                continue;
                            }
                            if($fleet['planet_id'] == $planet['planet_id']) {
                                $sql = 'UPDATE ships SET unit_1 = '.$ship['max_unit_1'].', 
                                                         unit_2 = '.$ship['max_unit_2'].', 
                                                         unit_3 = '.$ship['max_unit_3'].',
                                                         unit_4 = '.$ship['max_unit_4'].', 
                                                         fleet_id = '.$fleet['fleet_id'].' 
                                        WHERE ship_id = '.$ship['ship_id'];

                                if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not add ship to fleet '.$fleet['fleet_name'], TICK_LOG_FILE_NPC);}                                
                            }
                        }
                        
                        /*
                        if(!empty($ship['ship_id'])) {
                            $this->sdl->log('DEBUG:BORG_1: New Cube '.$ship['ship_id'].' found on planet #'.$planet['planet_id'], TICK_LOG_FILE_NPC);
                            
                            $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships, alert_phase, homebase)
                                    VALUES ("Fleet Node#'.$planet['planet_id'].'", '.BORG_USERID.', '.$planet['planet_id'].', 0, 1, 2, '.$planet['planet_id'].')';
                            
                            if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not insert new fleet data', TICK_LOG_FILE_NPC);}
                            
                            $fleet_id = $this->db->insert_id();

                            if(!$fleet_id) {$this->sdl->log('DEBUG:BORG_3: - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);}
                            
                            $sql = 'UPDATE ships SET unit_1 = '.$ship['max_unit_1'].', 
                                                     unit_2 = '.$ship['max_unit_2'].', 
                                                     unit_3 = '.$ship['max_unit_3'].',
                                                     unit_4 = '.$ship['max_unit_4'].', 
                                                     fleet_id = '.$fleet_id.' 
                                    WHERE ship_id = '.$ship['ship_id'];
                            
                            if(!$this->db->query($sql)) {$this->sdl->log('DEBUG:BORG_3: Could not insert new fleet data', TICK_LOG_FILE_NPC);}                            
                            
                            break;
                        }
                        */
                        
                        $sql = 'SELECT COUNT(*) as n_ships FROM scheduler_shipbuild WHERE planet_id = '.$planet['planet_id'];
                        
                        $shipbuild = $this->db->queryrow($sql);
                        
                        if(empty($shipbuild['n_ships']) && rand(0,99) < $prod_chance) {
                            $this->sdl->log('DEBUG:BORG_1: Started costruction of a new Ship on planet #'.$planet['planet_id'], TICK_LOG_FILE_NPC);
                            $crew = $this->db->queryrow('SELECT max_unit_1, max_unit_2, max_unit_3, max_unit_4 FROM ship_templates WHERE id = '.$this->bot['ship_template2']);
                            $_buildtime = 20*24*5;
                            $sql = 'INSERT INTO scheduler_shipbuild SET ship_type = '.$this->bot['ship_template2'].', planet_id = '.$planet['planet_id'].', line_id = 0, start_build = '.$ACTUAL_TICK.', finish_build = '.($ACTUAL_TICK + $_buildtime).', unit_1 = '.$crew['max_unit_1'].', unit_2 = '.$crew['max_unit_2'].', unit_3 = '.$crew['max_unit_3'].', unit_4 = '.$crew['max_unit_4'];
                            $this->db->query($sql);
                            break;
                        }
                        break;
                }
                
                // Updating npc_last_action
                $this->db->query('UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 1).' WHERE planet_id = '.$planet['planet_id']);
        }

$this->sdl->finish_job('Create Borg defences on assimilated planets', TICK_LOG_FILE_NPC);
// ########################################################################################
// ########################################################################################
// Settlers Assimilation Program(tm)!!!
/*
$this->sdl->start_job('Settlers Assimilation Program - BETA -', TICK_LOG_FILE_NPC);

    // Settlers Target Analysis phase
    $sql = 'SELECT planet_id, planet_type, bt.user_id FROM planets p
                                                      LEFT JOIN borg_npc_target bnt USING (planet_id)
                                                      LEFT JOIN borg_target bt ON best_mood_user = bt.user_id
            WHERE planet_owner = '.INDEPENDENT_USERID.' AND bnt.planet_id IS NULL
            ORDER BY planet_owned_date ASC LIMIT 0, 10';
    $set_q1 = $this->db->query($sql);
    $set_rows = $this->db->num_rows($set_q1);
    if($set_rows > 0)
    {
        $this->sdl->log('DEBUG: Settlers Target Analysis Phase', TICK_LOG_FILE_NPC);
        $data_q1 = $this->db->fetchrowset($set_q1);
        foreach($data_q1 AS $item_q1)
        {
            $primary_flag = 0;
            if ($item_q1['planet_type'] == 'm' || $item_q1['planet_type'] == 'o' || $item_q1['planet_type'] == 'p' || $item_q1['planet_type'] == 'g' || $item_q1['planet_type'] == 'l' ||
                $item_q1['planet_type'] == 'k' || $item_q1['planet_type'] == 'f' || $item_q1['planet_type'] == 'e')
                $primary_flag = 1;

            $priority = 0;
            if(!empty($item_q1['user_id'])) $priority = 10;

            $sql = 'INSERT INTO borg_npc_target (planet_id, tries, priority, primary_planet)
                    VALUES ('.$item_q1['planet_id'].', 0, '.$priority.', '.$primary_flag.')';
            if(!$this->db->query($sql))
            {
                $this->sdl->log('Could not insert new borg npc target data: '.$sql ,TICK_LOG_FILE_NPC);
            }
        }
    }

$this->sdl->finish_job('Settlers Assimilation Program - BETA -', TICK_LOG_FILE_NPC);        
*/

// ########################################################################################
// ########################################################################################
// Fleet Action Phase
$this->sdl->start_job('Fleet Action Program - BETA -', TICK_LOG_FILE_NPC);
            
    $sql='SELECT fleet_id, fleet_name, n_ships, planet_id, system_id, npc_idles, homebase FROM ship_fleets
          INNER JOIN planets USING (planet_id)
          WHERE ship_fleets.npc_last_action < '.$ACTUAL_TICK.' AND user_id = '.BORG_USERID.' AND
          move_id = 0 AND fleet_name LIKE "%Fleet Node%"
          ORDER BY ship_fleets.npc_last_action ASC LIMIT 0, 1';

    if(($setpoint = $this->db->query($sql)) === false)
    {
        $this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
    }
    else
    {
        while($fleet_to_serve = $this->db->fetchrow($setpoint)) {
            
            if(strpos($fleet_to_serve['fleet_name'], 'S') === false) $flag_sentry = false; else $flag_sentry = true;
            
            $this->sdl->log('DEBUG:BORG_1: Is now acting fleet '.$fleet_to_serve['fleet_id'].': '.$fleet_to_serve['fleet_name'], TICK_LOG_FILE_NPC );
            
            $this->RRBorgFleet($fleet_to_serve['fleet_id']);
                       
            // Check dell'orbita per eventuali bersagli
            // $this->sdl->log('DEBUG:BORG_1: Orbit check '.$fleet_to_serve['fleet_id'].': '.$fleet_to_serve['fleet_name'], TICK_LOG_FILE_NPC );
     
            $dfd_user = $this->db->queryrow('SELECT DISTINCT user_id FROM ship_fleets WHERE planet_id = '.$fleet_to_serve['planet_id'].' AND user_id <> '.BORG_USERID.' LIMIT 0,1');
            
            if(isset($dfd_user['user_id']) && !empty($dfd_user['user_id'])) {
                // Attacchiamo l'utente vicino
                $this->AttackNearFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $fleet_to_serve['planet_id'], $fleet_to_serve['n_ships'], $dfd_user['user_id']);
                break;
            }
            
            // Check sensori!!!
            // $this->sdl->log('DEBUG:BORG_1: Sensor check '.$fleet_to_serve['fleet_id'].': '.$fleet_to_serve['fleet_name'], TICK_LOG_FILE_NPC );

            $sql = 'SELECT dest AS planet_id, move_finish  
                    FROM scheduler_shipmovement
                    INNER JOIN planets ON dest = planet_id
                    WHERE move_status = 0 AND
                          scheduler_shipmovement.user_id > 10 AND
                          system_id = '.$fleet_to_serve['system_id'].' AND
                          move_finish < '.($ACTUAL_TICK + 60).' AND                              
                          move_finish > '.($ACTUAL_TICK + 4).' AND
                          dest <> '.$fleet_to_serve['planet_id'].'    
                    LIMIT 0,1';

            $inc_fleet = $this->db->queryrow($sql);

            if(isset($inc_fleet['planet_id']) && !empty($inc_fleet['planet_id'])) {
                // Spostiamo la flotta per intercettare!!!! 
                $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $inc_fleet['planet_id'], 11);
                break;
            }
                
            if($flag_sentry) {
                $sql = 'SELECT DISTINCT planet_id, planet_owner, planet_type FROM planets WHERE system_id = '.$fleet_to_serve['system_id'].' AND (planet_owner > 10 OR planet_owner = '.INDEPENDENT_USERID.')';

                $res = $this->db->queryrowset($sql);

                foreach($res AS $invader) {
                    $primary_planet = 0;
                    switch ($invader['planet_owner']) {
                        case INDEPENDENT_USERID:
                            $primary_planet = $this->IsPrimaryPlanet($invader['planet_type']);
                            break;
                        default :
                            $primary_planet = 2;
                            break;
                    }
                    $sql = 'INSERT IGNORE INTO borg_npc_target (planet_id, tries, live_attack, primary_planet, priority) 
                                                        VALUES ("'.$invader['planet_id'].'", 0, 0, '.$primary_planet.', 0)';
                    $this->db->query($sql);
                    $this->sdl->log('Inhabited planet detected, listed in the borg_npc_target: '.$sql ,TICK_LOG_FILE_NPC);
                    if($invader['planet_owner'] > 10) {
                        $sql = 'INSERT IGNORE INTO borg_target (user_id) VALUES ('.$invader['planet_owner'].')';
                        $this->db->query($sql);
                        $this->sdl->log('Borg Fleet found a player: '.$sql ,TICK_LOG_FILE_NPC);
                    }
                }                
                
                /*
                // Controlliamo le orbite di tutti i pianeti del sistema
                $sql = 'SELECT planet_id, SUM(n_ships) as count_ships FROM (ship_fleets sf)
                        INNER JOIN (planets p) USING (planet_id)
                        WHERE sf.user_id <> '.BORG_USERID.' AND p.system_id = '.$fleet_to_serve['system_id'].'
                        GROUP BY planet_id
                        ORDER BY count_ships ASC LIMIT 0,1';
                 * 
                 */
                
                // Controlliamo le orbite dei pianeti "vicini" alla flotta !!!
                $fleetpos = $this->FleetPos($fleet_to_serve['planet_id']);
                
                $scan_range = $this->SentryScanRange($this->bot['system_id'], $fleetpos['system_global_x'], $fleetpos['system_global_y']);
                
                $sql = 'SELECT planet_id, SUM(n_ships) as count_ships FROM (ship_fleets sf)
                        INNER JOIN (planets p) USING (planet_id)
                        INNER JOIN (starsystems s) ON s.system_id = p.system_id
                        WHERE sf.user_id <> '.BORG_USERID.' AND 
                        ((system_global_x > '.$fleetpos['system_global_x'].' - '.$scan_range.' AND system_global_x < '.$fleetpos['system_global_x'].' + '.$scan_range.') AND
                         (system_global_y > '.$fleetpos['system_global_y'].' - '.$scan_range.' AND system_global_y < '.$fleetpos['system_global_y'].' + '.$scan_range.'))
                        GROUP BY planet_id
                        ORDER BY count_ships ASC
                        LIMIT 0,1';
                $invader_fleets = $this->db->queryrow($sql);
                if(isset($invader_fleets['planet_id']) && !empty($invader_fleets['planet_id'])){
                    $this->sdl->log('DEBUG:BORG_1: Sentry Borg fleet '.$fleet_to_serve['fleet_name'].' detected intruders around '.$invader_fleets['planet_id'].' at range '.$scan_range.'!!! Moving to intercept',TICK_LOG_FILE_NPC);
                    $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $invader_fleets['planet_id'], 11);                
                }
                else {
                    $this->db->query('UPDATE ship_fleets SET npc_last_action = '.($ACTUAL_TICK + 1*20).' WHERE fleet_id = '.$fleet_to_serve['fleet_id']);                    
                }
                break;                             
            }
                
            if($fleet_to_serve['npc_idles'] > ($idle_check+28) && $fleet_to_serve['planet_id'] != $fleet_to_serve['homebase']) {
                $this->db->query('UPDATE ship_fleets SET npc_idles = 0 WHERE fleet_id = '.$fleet_to_serve['fleet_id']);
                $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $fleet_to_serve['homebase'], 11);
                break;                
            }

            
            /*
            if($flag_a1) {
                $this->sdl->log('DEBUG:BORG_1: Target selection started', TICK_LOG_FILE_NPC );                
                // We only do this if bot is allowed to attack Settlers planets                        

                $fleetpos = $this->FleetPos($fleet_to_serve['planet_id']);

                // Raccolta dati pianeti bersagli primari, i migliori 50 a disposizione

                $sql='SELECT planet_id, tries, priority, system_global_x, system_global_y
                      FROM (planets p) 
                      INNER JOIN (borg_npc_target bnt) USING (planet_id)
                      INNER JOIN (starsystems s) ON s.system_id = p.system_id
                      WHERE planet_owner = '.INDEPENDENT_USERID.' AND 
                            bnt.primary_planet = 1 AND 
                            bnt.live_attack < '.(max(3, $max_live_attack)).' AND 
                            bnt.delay = 0 
                      ORDER BY priority DESC, live_attack ASC, planet_owned_date ASC 
                      LIMIT 0, 50';

                $min_distance = 10000000;
                $reslistptarget = $this->db->queryrowset($sql);
                // Selezioniamo il più vicino
                foreach ($reslistptarget AS $restarget) {
                    $distance = get_distance(array($fleetpos['system_global_x'], $fleetpos['system_global_y']), array($restarget['system_global_x'], $restarget['system_global_y']));
                    if($distance < $min_distance) {
                        $min_distance = $distance;
                        $primary_target = $restarget;
                    }                        
                }
                // Controlliamo se il bersaglio primario è in canna
                if( (!isset($primary_target['planet_id'])) || (empty($primary_target['planet_id'])) ) {
                    // Andata male, non abbiamo un primary, cerchiamo un secondary tra i migliori 50 a disposizione
                    $sql = 'SELECT planet_id, system_global_x, system_global_y 
                            FROM (planets p)
                            INNER JOIN borg_npc_target bnt USING (planet_id)
                            WHERE planet_owner = '.INDEPENDENT_USERID.' AND 
                                  bnt.primary_planet = 0 AND
                                  bnt.live_attack = 0 AND
                                  bnt.delay = 0
                            ORDER BY bnt.priority DESC, planet_owned_date ASC
                            LIMIT 0, 50';

                    $min_distance = 10000000;
                    $resliststarget = $this->db->queryrowset($sql);
                    // Selezioniamo il più vicino
                    foreach ($resliststarget AS $restarget) {
                        $distance = get_distance(array($fleetpos['system_global_x'], $fleetpos['system_global_y']), array($restarget['system_global_x'], $restarget['system_global_y']));
                        if($distance < $min_distance) {
                            $min_distance = $distance;
                            $secondary_target = $restarget;
                        }                        
                    }

                }
                $this->sdl->log('DEBUG:BORG_1: Target selection ended', TICK_LOG_FILE_NPC );

                if(!empty($primary_target['planet_id'])) $this->sdl->log('DEBUG:BORG_3: Primary target is: '.$primary_target['planet_id'], TICK_LOG_FILE_NPC );
                if(!empty($secondary_target['planet_id'])) $this->sdl->log('DEBUG:BORG_3: Secondary target is: '.$secondary_target['planet_id'], TICK_LOG_FILE_NPC );

                if(!empty($secondary_target['planet_id']))
                {
                // Borg attacks a secondary target
                    $this->sdl->log('DEBUG:BORG_2: Attacking secondary target!', TICK_LOG_FILE_NPC );
                    $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $secondary_target['planet_id']);
                    $sql='UPDATE borg_npc_target SET live_attack = live_attack + 1 WHERE planet_id = '.$secondary_target['planet_id'];
                    $this->db->query($sql);
                }
                elseif(!empty($primary_target['planet_id']))
                {
                // Borg attacks a primary target
                    $this->sdl->log('DEBUG:BORG_2: Attacking primary target!!!', TICK_LOG_FILE_NPC );
                    if($primary_target['priority'] > 1) $this->AddBorgCube($fleet_to_serve['fleet_id']);
                    if($primary_target['priority'] > 0) $this->AddBorgSpheres($fleet_to_serve['fleet_id']);
                    $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $primary_target['planet_id']);
                    $sql='UPDATE borg_npc_target SET live_attack = live_attack + 1 WHERE planet_id = '.$primary_target['planet_id'];
                    $this->db->query($sql);
                }
                else
                {
                    $this->sdl->log('DEBUG:BORG_4: No target available, Borg Fleet skipping action.', TICK_LOG_FILE_NPC );
                }
            }
            else
             * 
             */
            if($fleet_to_serve['npc_idles'] > $idle_check) {
                // Colonize !!!         
                // We only do this if bot is NOT allowed to attack Settlers planets                        
                $sql = 'SELECT planet_owner, planet_type FROM planets WHERE planet_id = '.$fleet_to_serve['planet_id'];
                $plinfo = $this->db->queryrow($sql);
                if(isset($plinfo['planet_owner']) && empty($plinfo['planet_owner']) && rand(0,14400) > (int)$colotest) {
                    $sql = 'DELETE FROM scheduler_instbuild
                            WHERE planet_id = '.$fleet_to_serve['planet_id'];

                    if(!$this->db->query($sql)) {
                        $this->sdl->log('Could not delete scheduler instbuild data! CONTINUE', TICK_LOG_FILE_NPC );
                    }

                    $sql = 'DELETE FROM scheduler_shipbuild
                            WHERE planet_id = '.$fleet_to_serve['planet_id'];

                    if(!$this->db->query($sql)) {
                        $this->sdl->log('Could not delete shipbuild data! CONTINUE', TICK_LOG_FILE_NPC );
                    }

                    $sql = 'DELETE FROM scheduler_research
                            WHERE planet_id = '.$fleet_to_serve['planet_id'];

                    if(!$this->db->query($sql)) {
                        $this->sdl->log(MV_M_DATABASE, 'Could not delete scheduler research data! CONTINUE', TICK_LOG_FILE_NPC );
                    }
                    
                    if($plinfo['planet_type'] == "m" || $plinfo['planet_type'] == "o" ||
                       $plinfo['planet_type'] == "p" || $plinfo['planet_type'] == "e" ||
                       $plinfo['planet_type'] == "f" || $plinfo['planet_type'] == "g" ||
                       $plinfo['planet_type'] == "l" || $plinfo['planet_type'] == "k") {
                        $def_tech_lev = 9;
                    }
                    else {
                        $def_tech_lev = 6;
                    }

                    $sql = 'UPDATE planets
                            SET npc_last_action = 0,
                                planet_owner = '.BORG_USERID.',
                                planet_name = "Unimatrix #'.$fleet_to_serve['planet_id'].'",
                                best_mood = 0,
                                best_mood_user = 0,
                                planet_available_points = 677,
                                planet_owned_date = '.time().',
                                planet_owner_enum = '.($this->bot['user_planets'] - 1).',
                                resource_4 = 3000,
                                planet_next_attack = 0,
                                planet_attack_ships = 0,
                                planet_attack_type = 0,
                                research_2 = 9,
                                research_3 = '.$def_tech_lev.',
                                research_4 = '.$def_tech_lev.',
                                research_5 = '.$def_tech_lev.',
                                recompute_static = 1,
                                building_1 = 9,
                                building_2 = 1,
                                building_3 = 1,
                                building_4 = 1,
                                building_5 = 9,
                                building_6 = 9,
                                building_7 = 9,
                                building_8 = 9,
                                building_9 = 1,
                                building_10 = 0,
                                building_11 = 1,
                                building_12 = 1,
                                building_13 = 0,
                                unit_1 = 100,
                                unit_2 = 400,
                                unit_3 = 400,
                                unit_4 = 10,
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
                                planet_insurrection_time=0
                            WHERE planet_id = '.$fleet_to_serve['planet_id'];

                    if(!$this->db->query($sql)) {
                        $this->sdl->log('Could not update planets data!'.$sql, TICK_LOG_FILE_NPC );
                    }
                    
                    $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code)
                            VALUES ('.$fleet_to_serve['planet_id'].', '.BORG_USERID.', 0, '.BORG_USERID.', 0, '.time().', 25)';

                    if(!$this->db->query($sql)) {
                        $this->sdl->log('Could not update planet details data!'.$sql, TICK_LOG_FILE_NPC );
                    }                                            
                    
                    $this->db->query('UPDATE borg_bot SET tick_last_colo = tick_last_colo + 480');
                    $this->db->query('UPDATE ship_fleets SET npc_last_action = '.($ACTUAL_TICK + 3*20).', npc_idles = 0 WHERE fleet_id = '.$fleet_to_serve['fleet_id']);
                    break;
                }
                elseif(isset($plinfo['planet_owner']) && empty($plinfo['planet_owner'])) {
                    $this->db->query('UPDATE ship_fleets SET npc_last_action = '.($ACTUAL_TICK + 3*20).', npc_idles = npc_idles + 1 WHERE fleet_id = '.$fleet_to_serve['fleet_id']);
                    $this->sdl->log('DEBUG:BORG_1: Colonization attempt failed this time for planet '.$fleet_to_serve['planet_id'], TICK_LOG_FILE_NPC );
                    break;
                }
                elseif(isset($plinfo['planet_owner']) && $plinfo['planet_owner'] == INDEPENDENT_USERID) {                    
                    $sql = 'INSERT INTO borg_npc_target (planet_id, tries, live_attack, primary_planet, priority) 
                                                 VALUES ("'.$fleet_to_serve['planet_id'].'", 0, 0, '.$this->IsPrimaryPlanet($plinfo['planet_type']).', 0)
                            ON DUPLICATE KEY UPDATE primary_planet = 2';                    
                    $this->db->query($sql);
                    $this->sdl->log('Inhabited Settlers planet detected, listed in the borg_npc_target: '.$sql ,TICK_LOG_FILE_NPC);                    
                }
                elseif(isset($plinfo['planet_owner']) && $plinfo['planet_owner'] > 10) {
                    $sql = 'INSERT INTO borg_npc_target (planet_id, tries, live_attack, primary_planet, priority) 
                                                 VALUES ("'.$fleet_to_serve['planet_id'].'", 0, 0, 2, 0)
                            ON DUPLICATE KEY UPDATE primary_planet = 2';                    
                    $this->db->query($sql);
                    $this->sdl->log('Inhabited user planet detected, listed in the borg_npc_target: '.$sql ,TICK_LOG_FILE_NPC);
                    $sql = 'INSERT INTO borg_target (user_id) VALUES ('.$plinfo['planet_owner'].')';
                    $this->db->query($sql);
                    $this->sdl->log('Borg found a player: '.$sql ,TICK_LOG_FILE_NPC);
                }
            }
            
            // E adesso, il VERO lavoro grosso!
            if($fleet_to_serve['npc_idles'] > $idle_check) {
                if($avail_planets_list = $this->GetAvailPlanetsList($fleet_to_serve['system_id'])) {
                    // Spostiamo la flotta
                    $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $avail_planets_list[0]['planet_id'], 11);
                }
                else {
                    // Iniziano i dolori
                    $this->sdl->log('DEBUG:BORG_1: Begin system search phase for fleet '.$fleet_to_serve['fleet_id'].': '.$fleet_to_serve['fleet_name'], TICK_LOG_FILE_NPC );                    
                    
                    $fleetpos = $this->FleetPos($fleet_to_serve['planet_id']);
                    
                    $direction = array(0 => 'up', 1 => 'up-right', 2 => 'right', 3 => 'down-right', 4 => 'down', 5 => 'down-left', 6 => 'left', 7 => 'up-left');
                    
                    // Premio come miglior intricato sistema per "guardarsi attorno"...
                    $best_match = -10000;
                    for ($i = 0; $i < 8; $i++) {
                        switch ($direction[$i]) {
                            case 'up':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x = '.$fleetpos['system_global_x'].' AND
                                        system_global_y < '.$fleetpos['system_globaly'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_y DESC
                                        LIMIT 0,1';
                                break;
                            case 'up-right':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x > '.$fleetpos['system_global_x'].' AND
                                        system_global_y < '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_x ASC, system_global_y DESC
                                        LIMIT 0,1';                                
                                break;
                            case 'right':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x > '.$fleetpos['system_global_x'].' AND
                                        system_global_y = '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_x ASC
                                        LIMIT 0,1';                                
                                break;                            
                            case 'down-right':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x > '.$fleetpos['system_global_x'].' AND
                                        system_global_y > '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_x ASC, system_global_y ASC
                                        LIMIT 0,1';                                
                                break;
                            case 'down':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x = '.$fleetpos['system_global_x'].' AND
                                        system_global_y > '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_y ASC
                                        LIMIT 0,1';                                
                                break;
                            case 'down-left':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x < '.$fleetpos['system_global_x'].' AND
                                        system_global_y > '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_x DESC, system_global_y ASC
                                        LIMIT 0,1';                                
                                break;                            
                            case 'left':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x < '.$fleetpos['system_global_x'].' AND
                                        system_global_y = '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_x DESC
                                        LIMIT 0,1';                                
                                break;
                            case 'up-left':
                                $sql = 'SELECT system_id, system_global_x, system_global_y
                                        FROM starsystems
                                        WHERE system_global_x < '.$fleetpos['system_global_x'].' AND
                                        system_global_y < '.$fleetpos['system_global_y'].'  AND
                                        system_closed = 0
                                        ORDER BY system_global_x DESC, system_global_y DESC 
                                        LIMIT 0,1';                                
                                break;                            
                        }
                        
                        if($res = $this->db->queryrow($sql)) {
                            $res1 = $this->db->queryrow('SELECT COUNT(*) AS tally FROM planets WHERE system_id = '.$res['system_id'].' AND planet_owner = 0');
                            $res2 = $this->db->queryrow('SELECT COUNT(*) AS tally FROM planets WHERE system_id = '.$res['system_id'].' AND planet_owner = '.BORG_USERID);
                            $res3 = $this->db->queryrow('SELECT COUNT(*) AS tally FROM planets WHERE system_id = '.$res['system_id'].' AND planet_owner = '.INDEPENDENT_USERID);
                            $res4 = $this->db->queryrow('SELECT COUNT(*) AS tally FROM planets WHERE system_id = '.$res['system_id'].' AND planet_type IN ("e", "f", "g", "l", "k", "m", "o", "p")');
                            $res5 = $this->db->queryrow('SELECT planet_id, planet_owner FROM planets WHERE system_id = '.$res['system_id'].' ORDER BY planet_distance_id DESC LIMIT 0,1');

                            $distance = (20000 - get_distance(array($fleetpos['system_global_x'], $fleetpos['system_global_y']), array($res['system_global_x'], $res['system_global_y']))) / 100;

                            if($res2['tally'] > 0) {$borg_value = -200;} else {$borg_value = 0;}
                            
                            $value = $distance + ($res1['tally'] * 3) + $borg_value + ($res3['tally'] * 20) + ($res4['tally'] * 10);
                            
                            if($best_match < $value) {
                                $best_match  = $value;
                                $best_planet = $res5['planet_id'];
                                $best_owner  = $res5['planet_owner'];
                            }
                            $this->sdl->log('Ryoga-Effect:Borg Fleet '.$fleet_to_serve['fleet_id'].' looking into direction '.$direction[$i].' found system '.$res['system_id'].', value '.$value, TICK_LOG_FILE_NPC );                            
                        }
                    }
                    
                    if($best_match > 0) {
                        $this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $best_planet, ($best_owner != 0 ? 46 : 11));
                        $this->sdl->log('Borg Fleet '.$fleet_to_serve['fleet_id'].' sent to '.$best_planet.' owned by '.$best_owner, TICK_LOG_FILE_NPC );
                    }
                    else {
                        $this->db->query('UPDATE ship_fleets SET npc_last_action = '.($ACTUAL_TICK + 3*20).', npc_idles = 0 WHERE fleet_id = '.$fleet_to_serve['fleet_id']);
                    }
                        
                }
                break;
            }
            
            $this->db->query('UPDATE ship_fleets SET npc_last_action = '.($ACTUAL_TICK + 3*20).', npc_idles = npc_idles + 1 WHERE fleet_id = '.$fleet_to_serve['fleet_id']);
        }
    }

        
$this->sdl->finish_job('Fleet Action Program - BETA -', TICK_LOG_FILE_NPC);         
 
// ########################################################################################
// ########################################################################################
$this->sdl->start_job('Cleaning System Program - BETA -', TICK_LOG_FILE_NPC);

    $loop = true;
    
    $primary_planet = 2;
    
    $sql = 'SELECT bnt.planet_id, bnt.priority, system_global_x, system_global_y, bt.threat_level
            FROM borg_npc_target bnt                
            INNER JOIN (planets p) USING (planet_id)
            INNER JOIN (starsystems s) ON s.system_id = p.system_id
            LEFT JOIN  (borg_target bt) ON bt.user_id = p.planet_owner 
            WHERE bnt.primary_planet = '.$primary_planet.' AND bnt.live_attack = 0 AND bnt.delay = 0 LIMIT 0,1';

    while ($loop) {
        $resinvader = $this->db->queryrow($sql);

        if(isset($resinvader['planet_id']) && !empty($resinvader['planet_id'])) {
            // Cerchiamo la flotta più vicina per attaccare il bersaglio
            $sql = 'SELECT fleet_id, starsystems.system_global_x, starsystems.system_global_y
                    FROM ship_fleets
                    INNER JOIN (planets) USING (planet_id)
                    INNER JOIN (starsystems) USING (system_id)
                    WHERE ship_fleets.user_id = '.BORG_USERID.' AND ship_fleets.planet_id <> 0 AND fleet_name LIKE "%Fleet Node%"';

            $resfleets = $this->db->queryrowset($sql);

            $min_distance = 10000000;
            foreach ($resfleets AS $atkfleet) {
                $distance = get_distance(array($resinvader['system_global_x'], $resinvader['system_global_y']), array($atkfleet['system_global_x'], $atkfleet['system_global_y']));
                if($distance < $min_distance) {
                    $min_distance = $distance;
                    $departingfleet = $atkfleet['fleet_id'];
                }
            }

            if(isset($departingfleet) && !empty($departingfleet)) {
                if(isset($resinvader['threat_level']) && $resinvader['threat_level'] > 450.0) $this->PromoteCubeToTact($departingfleet);            
                if($resinvader['priority'] > 1) $this->AddBorgCube($departingfleet);
                if($resinvader['priority'] > 0) $this->AddBorgSpheres($departingfleet);

                $this->RRBorgFleet($departingfleet);
                
                $this->SendBorgFleet($ACTUAL_TICK, $departingfleet, $resinvader['planet_id'], 46);
                $sql='UPDATE borg_npc_target SET live_attack = live_attack + 1 WHERE planet_id = '.$resinvader['planet_id'];
                $this->db->query($sql);
                $this->sdl->log('DEBUG:BORG_1: Sending fleet '.$departingfleet.' to assimilate planet '.$resinvader['planet_id'], TICK_LOG_FILE_NPC );
            }
            $loop = false;            
        }
        else {
            
            if($primary_planet > 0) {$primary_planet--;} else {$loop = false;}
            
            $sql = 'SELECT bnt.planet_id, bnt.priority, system_global_x, system_global_y, bt.threat_level
                    FROM borg_npc_target bnt                
                    INNER JOIN (planets p) USING (planet_id)
                    INNER JOIN (starsystems s) ON s.system_id = p.system_id
                    LEFT JOIN  (borg_target bt) ON bt.user_id = p.planet_owner 
                    WHERE bnt.primary_planet = '.$primary_planet.' AND bnt.live_attack = 0 AND bnt.delay = 0 LIMIT 0,1';            
            }
    }
$this->sdl->finish_job('Cleaning System Program - BETA -', TICK_LOG_FILE_NPC);

// ########################################################################################
// ########################################################################################
// PLAYERS Assimilation Program(tm)!!!!!!

$this->sdl->start_job('PLAYERS Assimilation Program - BETA -', TICK_LOG_FILE_NPC);


$sql = 'SELECT bt.* FROM borg_target bt LEFT JOIN user u ON bt.user_id = u.user_id WHERE u.user_vacation_end < '.$ACTUAL_TICK.' ORDER BY last_check ASC, threat_level DESC, planets_taken DESC, under_attack ASC LIMIT 0,1';
$primary_target = $this->db->queryrow($sql);
if(isset($primary_target['user_id']) && !empty($primary_target['user_id']))
{
        // Checking existing attacks on the way
        $sql = 'SELECT COUNT(*) as live_attack FROM scheduler_shipmovement sm INNER JOIN planets p ON sm.dest = p.planet_id
                WHERE sm.user_id = '.BORG_USERID.' AND
                      action_code = 46 AND
                      move_status = 0 AND
                      move_exec_started = 0 AND
                      p.planet_owner = '.$primary_target['user_id'];
        $res0 = $this->db->queryrow($sql);
        $live_attack = $res0['live_attack'];

        // Computing the target threat level!!!!
        $sql = 'SELECT count(*) as class1_ships FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id WHERE ship_class = 1 AND ships.user_id = '.$primary_target['user_id'];
        $res00 = $this->db->queryrow($sql);                        
        $sql = 'SELECT count(*) as class2_ships FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id WHERE ship_class = 2 AND ships.user_id = '.$primary_target['user_id'];
        $res1 = $this->db->queryrow($sql);
        $sql = 'SELECT count(*) as class3_ships FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id WHERE ship_class = 3 AND ships.user_id = '.$primary_target['user_id'];
        $res2 = $this->db->queryrow($sql);
        $sql = 'SELECT count(*) as prom_ships FROM ships WHERE template_id = '.$FUTURE_SHIP.' AND ships.user_id = '.$primary_target['user_id'];
        $res3 = $this->db->queryrow($sql);
        $sql = 'SELECT count(*) as settlers FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' AND best_mood_user = '.$primary_target['user_id'];
        $res4 = $this->db->queryrow($sql);
        $bad_factor = round((20*$primary_target['planets_taken'] + 0.1*$res00['class1_ships'] + 0.8*$res1['class2_ships'] + 1.6*$res2['class3_ships'] + 0.5*$res4['settlers'] + 2.7*$res3['prom_ships'] + pow($primary_target['battle_win'],1.15)),3);
        $this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' got '.$bad_factor.' as bad_factor', TICK_LOG_FILE_NPC);

        $good_factor = round((80*$primary_target['planets_back'] + 9*$primary_target['under_attack'] + pow($primary_target['battle_lost'],1.2)),3) ;
        $this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' got '.$good_factor.' as good_factor', TICK_LOG_FILE_NPC);

        $threat_level = $bad_factor - $good_factor;

        $new_attack_count = $old_attack_count = $primary_target['under_attack'];
        $add_cube    = false;
        $add_spheres = false;

        $max_attack = 0;

        /*
        if($threat_level > 1400.0)
        {
                $sql = 'SELECT fleet_id, planet_id FROM ship_fleets
                        WHERE user_id = '.BORG_USERID.' AND move_id = 0 AND
                              fleet_name LIKE "%Fleet Node%"
                        ORDER BY npc_last_action ASC LIMIT 0, 3';
                $attack_fleet_query = $this->db->query($sql);
                $sort_string = ' ORDER BY planet_points DESC';
                $max_attack = 3;
                $add_cube = true;
                $add_spheres = true;
        }
        else if($threat_level > 950.0)
        {
                $sql = 'SELECT fleet_id, planet_id FROM ship_fleets
                        WHERE user_id = '.BORG_USERID.' AND move_id = 0 AND
                              fleet_name LIKE "%Fleet Node%"
                        ORDER BY npc_last_action ASC LIMIT 0, 3';
                $attack_fleet_query = $this->db->query($sql);
                $sort_string = ' ORDER BY planet_points ASC LIMIT 0,25';
                $max_attack = 3;
                $add_cube = true;                                
                $add_spheres = true;
        }
        else if($threat_level > 450.0)
        {
                $sql = 'SELECT fleet_id, planet_id FROM ship_fleets
                        WHERE user_id = '.BORG_USERID.' AND move_id = 0 AND
                              npc_last_action < '.$ACTUAL_TICK.' AND
                              fleet_name LIKE "%Fleet Node%"
                        ORDER BY npc_last_action ASC LIMIT 0, 2';
                $attack_fleet_query = $this->db->query($sql);
                $sort_string = ' ORDER BY planet_points ASC LIMIT 0,20';
                $max_attack = 2;
                $add_spheres = true;
        }
        else if($threat_level > 200.0)
        {
                $sql = 'SELECT fleet_id, planet_id FROM ship_fleets
                        WHERE user_id = '.BORG_USERID.' AND move_id = 0 AND
                              npc_last_action < '.$ACTUAL_TICK.' AND
                              fleet_name LIKE "%Fleet Node%"
                        ORDER BY npc_last_action ASC LIMIT 0, 1';
                $attack_fleet_query = $this->db->query($sql);
                $sort_string = ' AND p.planet_type NOT IN("m", "o", "p") AND planet_points < 451 ORDER BY planet_points ASC LIMIT 0,5';
                $max_attack = 1;
        }

        $this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' has '.$live_attack.' live attacks at the moment', TICK_LOG_FILE_NPC);
        // Attacking Sequence starts here!
        while(($attack_fleet_data = $this->db->fetchrow($attack_fleet_query)) && ($live_attack < $max_attack))
        {
                // Locate the fleet actual position
                $sql = 'SELECT s.system_global_x, s.system_global_y FROM (planets p) INNER JOIN (starsystems s) ON s.system_id = p.system_id WHERE p.planet_id = '.$attack_fleet_data['planet_id'];

                $fleetpos = $this->db->queryrow($sql);

                $sql = 'SELECT p.planet_id, p.planet_attack_type, s.system_global_x, s.system_global_y
                        FROM (planets p) 
                        INNER JOIN (starsystems s)       ON s.system_id = p.system_id 
                        LEFT JOIN  (borg_npc_target bnt) ON p.planet_id = bnt.planet_id
                        WHERE p.planet_attack_type = 0 
                        AND (bnt.delay IS NULL OR bnt.delay = 0)
                        AND p.planet_owner = '.$primary_target['user_id'].$sort_string;

                $target_query = $this->db->query($sql);

                // Select the nearest target planet to the fleet
                $min_distance = 10000000;
                while($target_item = $this->db->fetchrow($target_query)) {

                        $distance = get_distance(
                                array($fleetpos['system_global_x'], $fleetpos['system_global_y']),
                                array($target_item['system_global_x'], $target_item['system_global_y'])
                        );
                        if($distance < $min_distance)
                        {
                                $min_distance = $distance;
                                $chosen_target = $target_item;
                        }
                }

                //We have a target? If we do, let's send the fleet
                if(isset($chosen_target['planet_id']) && !empty($chosen_target['planet_id']) && ($chosen_target['planet_attack_type'] != 46))
                {
                        if($add_cube) $this->AddBorgCube($attack_fleet_data['fleet_id']);
                        if($add_spheres) $this->AddBorgSpheres($attack_fleet_data['fleet_id']);
                        $this->SendBorgFleet($ACTUAL_TICK, $attack_fleet_data['fleet_id'], $chosen_target['planet_id']);
                        $live_attack++;
                        $new_attack_count++;
                        $this->sdl->log('BORG Attack!!! ->'.$primary_target['user_id'].' on planet '.$chosen_target['planet_id'], TICK_LOG_FILE_NPC);
                }
        }

*/

        if($new_attack_count > $old_attack_count) $attack_count = $new_attack_count; else $attack_count = $old_attack_count;
        
        $sql = 'UPDATE borg_target SET threat_level = '.$threat_level.', last_check = '.$ACTUAL_TICK.', under_attack = '.$attack_count.' WHERE user_id = '.$primary_target['user_id'];
        $this->db->query($sql);
        $this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' got '.$threat_level.' as threat_level', TICK_LOG_FILE_NPC);
}

$this->sdl->finish_job('PLAYERS Assimilation Program - BETA -', TICK_LOG_FILE_NPC);

// ########################################################################################
// ########################################################################################



		$this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
	}

        function FleetPos($planet_id) {
            // Posizione della flotta
            $sql = 'SELECT s.system_global_x, s.system_global_y
                    FROM (planets p) INNER JOIN (starsystems s) ON s.system_id = p.system_id
                    WHERE p.planet_id = '.$planet_id;

            if($res = $this->db->queryrow($sql)) {return $res;} else {return false;}
        }        

        function GetAvailPlanetsList($system) {
            // Lista dei pianeti ancora liberi nel sistema della flotta
            $sql = 'SELECT planet_id FROM planets WHERE system_id = '.$system.' AND planet_owner = 0 ORDER BY planet_distance_id ASC';
            
            if($res = $this->db->queryrowset($sql)) {return $res;} else {return false;}
        }
        
	function SendBorgFleet($ACTUAL_TICK,$fleet_id,$dest,$action = 46) {

		if($action == 0) $action = 11;
                
                $mask = false;
                if($action == 46) $mask = true;
                
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
			$this->sdl->log('Borg fleet for mission does not exist, already moving?'.$sql,TICK_LOG_FILE_NPC);
			return false;
		}

		if($fleet['start_system_id'] == $fleet['dest_system_id']) {
			$distance = $velocity = 0;
			$min_time = 6;
                        $mask = false;
		}
		else {
			$distance = get_distance(array($fleet['start_x'], $fleet['start_y']), array($fleet['dest_x'], $fleet['dest_y']));
			$velocity = warpf(10);
			$min_time = ceil( ( ($distance / $velocity) / TICK_DURATION ) );
		}

		if($min_time < 1) $min_time = 1;

		$sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
		         VALUES ('.$fleet['user_id'].', 0, 0, '.($mask ?  0 : $fleet['start']).', '.$dest.', '.$distance.', '.$distance.', '.($velocity * TICK_DURATION).', '.$ACTUAL_TICK.', '.($ACTUAL_TICK + $min_time).', '.$fleet['n_ships'].', '.$action.', "")';

		if(!$this->db->query($sql)) {
			$this->sdl->log('Could not insert new movement data',TICK_LOG_FILE_NPC);
			return false;
		}

		$new_move_id = $this->db->insert_id();

		if(empty($new_move_id)) {
			$this->sdl->log('Could not send Borg fleet $new_move_id = empty',TICK_LOG_FILE_NPC);
			return false;
		}

		if($action == 46)
			$sql = 'UPDATE ship_fleets SET planet_id = 0, npc_idles = 0, move_id = '.$new_move_id.', npc_last_action = '.($ACTUAL_TICK + $min_time + 60).' WHERE fleet_id = '.$fleet['fleet_id'];
		else
			$sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$new_move_id.' WHERE fleet_id = '.$fleet['fleet_id'];

		if(!$this->db->query($sql)) {
			$this->sdl->log('Could not update Borg fleet data',TICK_LOG_FILE_NPC);
			return false;
		}

		return true;
	}
        
        function AttackNearFleet($ACTUAL_TICK, $fleet_id, $planet_id, $n_ships, $user_id) {
            $action_data = array((int)$user_id);

            $sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
                    VALUES ('.BORG_USERID.', 0, 0, '.$planet_id.', '.$planet_id.', 0, 0, 0, '.$ACTUAL_TICK.', '.($ACTUAL_TICK + 2).', '.$n_ships.', 51, "'.serialize($action_data).'")';

            if(!$this->db->query($sql)) {
                $this->sdl->log('DEBUG: Borg fleet'.$fleet_id.' cannot attack!!!: '.$sql,TICK_LOG_FILE_NPC);
                return false;
            }

            $new_move_id = $this->db->insert_id();

            $sql = 'UPDATE ship_fleets
                    SET planet_id = 0,
                        npc_idles = 0,
                        move_id = '.$new_move_id.'
                    WHERE fleet_id IN ('.$fleet_id.')';

            if(!$this->db->query($sql)) {
                $this->sdl->log('DEBUG: Borg fleet'.$fleet_id.' cannot update move data!!!: '.$sql,TICK_LOG_FILE_NPC);
                return false;
            }
            
            return true;
        }

    function AddBorgSpheres($fleet_id)
    {
        $this->sdl->log('Adding Spheres to fleet id='.$fleet_id, TICK_LOG_FILE_NPC);

        // Let's clear ALL Spheres already present in the fleet
        $sql = 'DELETE FROM ships WHERE fleet_id = '.$fleet_id.' AND template_id = '.$this->bot['ship_template1'];
        $this->db->query($sql); // No check, could be no spheres were present

        // Sphere number = Cube * 2
        $sql = 'SELECT count(*) as n_ships FROM ships
                INNER JOIN ship_templates ON template_id = id
                WHERE ship_torso = 9 AND ship_class = 3 AND owner = '.BORG_USERID.' 
                AND fleet_id = '.$fleet_id;
        $_n_ships = $this->db->queryrow($sql);
        $num = $_n_ships['n_ships'] * 2;

        // Sphere number = Tact * 3
        $sql = 'SELECT count(*) as n_ships FROM ships
                INNER JOIN ship_templates ON template_id = id
                WHERE ship_torso = 11 AND ship_class = 3 AND owner = '.BORG_USERID.' 
                AND fleet_id = '.$fleet_id;
        $_n_ships = $this->db->queryrow($sql);
        $num += $_n_ships['n_ships'] * 5;

        if($num < 1) $num = 1; // Safeguard;

        $sql = 'SELECT max_unit_1, max_unit_2, max_unit_3, max_unit_4, rof, rof2, max_torp,
                       value_4, value_9
                FROM `ship_templates` WHERE `id` = '.$this->bot['ship_template1'];
        if(($stpl = $this->db->queryrow($sql)) === false)
            $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

        $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
                                   rof, rof2, torp, unit_1, unit_2, unit_3, unit_4)
                VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_template1'].', '.$stpl['value_9'].',
                        '.$stpl['value_4'].', '.time().', '.$stpl['rof'].', '.$stpl['rof2'].', '.$stpl['max_torp'].',
                        '.$stpl['max_unit_1'].', '.$stpl['max_unit_2'].',
                        '.$stpl['max_unit_3'].', '.$stpl['max_unit_4'].')';

        for($i = 0; $i < $num; ++$i)
        {
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
            }
        }
        $this->sdl->log('Fleet: '.$fleet_id.' - '.$num.' Spheres added', TICK_LOG_FILE_NPC);
    }
    
    function AddBorgCube($fleet_id)
    {
        $this->sdl->log('Adding Cubes to fleet id='.$fleet_id, TICK_LOG_FILE_NPC);
        
        $num = 0;
        
        // Check # of Tacts
        $sql = 'SELECT count(*) as n_ships FROM ships
                INNER JOIN ship_templates ON template_id = id
                WHERE ship_torso = 11 AND ship_class = 3 AND owner = '.BORG_USERID.' 
                AND fleet_id = '.$fleet_id;
        $_n_ships = $this->db->queryrow($sql);
        $tacts = $_n_ships['n_ships'];        
        
        // Check # of Cubes
        $sql = 'SELECT count(*) as n_ships FROM ships
                INNER JOIN ship_templates ON template_id = id
                WHERE ship_torso = 9 AND ship_class = 3 AND owner = '.BORG_USERID.' 
                AND fleet_id = '.$fleet_id;
        $_n_ships = $this->db->queryrow($sql);
        $cubes = $_n_ships['n_ships'];
        
        if($tacts < 1) $tacts = 1; // Safeguard;
        if($cubes < 1) $cubes = 1; // Safeguard;
        
        if($cubes < (5 * $tacts)) {
            $sql= 'SELECT * FROM `ship_templates` WHERE `id` = '.$this->bot['ship_template2'];
            if(($stpl = $this->db->queryrow($sql)) === false)
                $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

            $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
                                       rof, rof2, torp, unit_1, unit_2, unit_3, unit_4)
                    VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_template2'].', '.$stpl['value_9'].',
                            '.$stpl['value_4'].', '.time().', '.$stpl['rof'].', '.$stpl['rof2'].', '.$stpl['max_torp'].',
                            '.$stpl['max_unit_1'].', '.$stpl['max_unit_2'].',
                            '.$stpl['max_unit_3'].', '.$stpl['max_unit_4'].')';            
            
            switch ($tacts){
                case 0: // Safeguard
                case 1:
                    switch($cubes) {
                        case 0: // Safeguard
                        case 1:
                        case 2:
                        case 3:
                            $num = 2;
                            break;
                        case 4:
                            $num = 1;
                            break;
                        default:
                            $num = 0;                    
                            break;
                    }
                    break;
                case 2:
                    switch($cubes) {
                        case 0: // Safeguard
                        case 1:
                        case 2:
                        case 3:
                        case 4:                            
                            $num = 4;
                            break;
                        case 5:
                        case 6:
                            $num = 2;
                            break;
                        case 7:
                        case 8:
                        case 9:                            
                            $num = 1;
                            break;
                        default:
                            $num = 0;                    
                            break;
                    }                    
                    break;
                case 3:
                    switch($cubes) {
                        case 0: // Safeguard
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:                            
                            $num = 5;
                            break;
                        case 7:
                        case 8:
                        case 9:
                            $num = 3;
                            break;
                        case 10:
                        case 11:
                        case 12:
                            $num = 2;
                            break;
                        case 13:
                        case 14:
                            $num = 1;
                            break;
                        default:
                            $num = 0;                    
                            break;
                    }
                    break;
                default :
                    $num = 0;
                    break;
            }
            

            
            for($i = 0; $i < $num; ++$i)
            {
                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
                }
            }            
        }
        
        $this->sdl->log('Fleet: '.$fleet_id.' - '.$num.' Cubes were added', TICK_LOG_FILE_NPC);
    }
    
    function PromoteCubeToTact($fleet_id)
    {
        $this->sdl->log('Promoting Cube to Tact in the fleet id='.$fleet_id, TICK_LOG_FILE_NPC);
        
        $num = 0;
        
        // Check # of Tacts
        $sql = 'SELECT count(*) as n_ships FROM ships INNER JOIN ship_templates ON ships.template_id = ship_templates.id
                WHERE ship_templates.ship_torso = 11 AND fleet_id = '.$fleet_id;
        $_n_ships = $this->db->queryrow($sql);
        $tacts = $_n_ships['n_ships'];        
        
        // Check # of Cubes
        $sql = 'SELECT count(*) as n_ships, ship_id FROM ships INNER JOIN ship_templates ON ships.template_id = ship_templates.id
                WHERE ship_templates.ship_torso = 9 AND fleet_id = '.$fleet_id.' LIMIT 0,1';
        $_n_ships = $this->db->queryrow($sql);
        $cubes = $_n_ships['n_ships'];        
        
        if($tacts <= 2 && $cubes >= 1) 
        {
            $sql= 'SELECT * FROM `ship_templates` WHERE `id` = '.$this->bot['ship_template3'];
            if(($stpl = $this->db->queryrow($sql)) === false)
                $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

            $sql = 'UPDATE ships SET template_id = '.$this->bot['ship_template3'].',
                                     hitpoints = '.$stpl['value_4'].', 
                                     rof = '.$stpl['rof'].', 
                                     rof2 = '.$stpl['rof2'].', 
                                     torp = '.$stpl['max_torp'].',
                                     unit_1 = '.$stpl['max_unit_1'].',
                                     unit_2 = '.$stpl['max_unit_2'].',
                                     unit_3 = '.$stpl['max_unit_3'].',
                                     unit_4 = '.$stpl['max_unit_4'].'
                    WHERE ship_id = '.$_n_ships['ship_id'];
            
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
            }            
            
            $num = 1;
        }
        
        $this->sdl->log('Fleet: '.$fleet_id.' - '.$num.' Tacts were added', TICK_LOG_FILE_NPC);        
    }
    
    function RRBorgFleet($fleet_id) {
        // Controllo status flotta

        $sql = 'SELECT ship_id, unit_1, unit_2, unit_3, unit_4, hitpoints, value_4,
                       max_unit_1, max_unit_2, max_unit_3, max_unit_4, max_torp
                       FROM ships
                       INNER JOIN ship_templates ON ship_templates.id = ships.template_id
                       WHERE fleet_id = '.$fleet_id;

        $reships = $this->db->queryrowset($sql);

        foreach ($reships AS $ship_to_check) {
            // Risetta i droni a bordo, ripara eventualmente lo scafo

            $sql = 'UPDATE ships SET unit_1 = '.$ship_to_check['max_unit_1'].', unit_2 = '.$ship_to_check['max_unit_2'].', unit_3 = '.$ship_to_check['max_unit_3'].', unit_4 = '.$ship_to_check['max_unit_4'].',
                                     hitpoints = '.$ship_to_check['value_4'].', torp = '.$ship_to_check['max_torp'].' WHERE ship_id = '.$ship_to_check['ship_id'];
            $this->db->query($sql);
        }        
    }
    
    function IsPrimaryPlanet($planet_type){
        $primary_planet = 0;
        switch ($planet_type){
            case 'm':
            case 'o':
            case 'p':
            case 'g':
            case 'l':
            case 'k':
            case 'f':
            case 'e':
                $primary_planet = 1;
                break;
            default :
                $primary_planet = 0;
                break;
        }        
        return $primary_planet;
    }
    
    function SentryScanRange($system_id, $fleet_global_x, $fleet_global_y){
        // Sentry in 8500 AU range from UM0 got a wider scan range
        $range = 2;
        
        $sql = 'SELECT system_global_x, system_global_y FROM starsystems WHERE system_id = '.$system_id;

        $res = $this->db->queryrow($sql);
        
        $distance = get_distance(array($res['system_global_x'], $res['system_global_y']), array($fleet_global_x, $fleet_global_y));
        
        if($distance > 4000) $range = 5;
        
        return $range;
    }
    
    function AdaptFleet($fleet_id, $id1, $id2)
    {
        // We switch the template of spheres and cubes attacking.

        if(!empty($id1))
        {
            $sql = 'UPDATE ships SET template_id = '.$id1.'
                    WHERE user_id = '.BORG_USERID.' AND fleet_id = '.$fleet_id.' AND template_id = '.$this->bot['ship_template1'];

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not update spheres template data'. $sql, TICK_LOG_FILE_NPC);
            }

            $sql = 'UPDATE ships s INNER JOIN ship_templates ON s.template_id = st.id
                    SET s.hitpoints = st.value_4,
                        s.torp = st.max_torp
                    WHERE s.fleet_id = '.$fleet_id.' AND s.template_id = '.$id1;

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not update spheres ship data'. $sql, TICK_LOG_FILE_NPC);
            }
        }

        if(!empty($id2))
        {
            $sql = 'UPDATE ships SET template_id = '.$id2.'
                    WHERE fleet_id = '.$fleet_id.' AND template_id = '.$this->bot['ship_template2'];

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not update cubes template data'. $sql, TICK_LOG_FILE_NPC);
            }

            $sql = 'UPDATE ships s INNER JOIN ship_templates ON s.template_id = st.id
                    SET s.hitpoints = st.value_4,
                        s.torp = st.max_torp
                    WHERE s.fleet_id = '.$fleet_id.' AND s.template_id = '.$id2;

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not update cubes ship data'. $sql, TICK_LOG_FILE_NPC);
            }
        }
    }
    
	function RestoreBorgFleet($name,$num)
	{
            
            $query='SELECT * FROM `ship_fleets` WHERE fleet_name="'.$name.'" and user_id='.$this->bot['user_id'].' LIMIT 0, 1';
            $fleet=$this->db->queryrow($query);
            if (empty($fleet)) {
                $this->sdl->log('<u>Warning:</u> Fleet: '.$name.' does not exists! - SKIP', TICK_LOG_FILE_NPC);
		return;
            }
                
            $query='SELECT COUNT(*) AS counter FROM ships INNER JOIN ship_templates ON template_id = id
                    WHERE user_id = '.BORG_USERID.' AND fleet_id = '.$fleet['fleet_id'].'
                    AND ship_torso = 9 AND ship_class = 3';
            
            $ships=$this->db->queryrow($query);
            if (empty($ships)) {
                $this->sdl->log('<u>Warning:</u> Fleet: '.$name.' does not exists! - SKIP', TICK_LOG_FILE_NPC);
		return;
            }
            
            if($ships['counter'] < $num)
            {
		$this->sdl->log('Fleet "'.$name.'" has only '.$ships['counter'].' cubes - we need restore', TICK_LOG_FILE_NPC);
		$needed = $num - $ships['counter'];

		$sql = 'UPDATE ship_fleets SET n_ships = n_ships + '.$needed.' WHERE fleet_id = '.$fleet['fleet_id'];
                if(!$this->db->query($sql))
		$this->sdl->log('<b>Error:</b> Could not update new fleets data', TICK_LOG_FILE_NPC);

		$sql = 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_template2'];
		if(($stpl = $this->db->queryrow($sql)) === false)
                    $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

                if (empty($stpl))
                    $this->sdl->log('<b>Error:</b> Could not found template '.$template.'!', TICK_LOG_FILE_NPC);
                else {
                    $units_str = $stpl['min_unit_1'].', '.$stpl['min_unit_2'].', '.$stpl['min_unit_3'].', '.$stpl['min_unit_4'];
                    $sql = 'INSERT INTO ships (fleet_id, user_id, template_id, experience,
                                               hitpoints, construction_time, rof, rof2, torp, unit_1, unit_2, unit_3, unit_4)
                            VALUES ('.$fleet['fleet_id'].', '.$this->bot['user_id'].', '.$this->bot['ship_template2'].', '.$stpl['value_9'].',
                                    '.$stpl['value_4'].', '.time().', '.$stpl['rof'].', '.$stpl['rof2'].', '.$stpl['max_torp'].', '.$units_str.')';

                    for($i = 0; $i < $needed; ++$i)
                    {
                        if(!$this->db->query($sql)) {
                            $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
                        }
                    }
            $this->sdl->log('Fleet: '.$fleet['fleet_id'].' - updated to '.$needed.' cubes', TICK_LOG_FILE_NPC);
                }
            }
	}    
}


?>
