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

define('PICK_RESOURCES_FROM_PLANET',1); // 1 = remove resources from BOT's planet
                                        // 0 = left BOT's planet untouched

//#######################################################################################
//#######################################################################################
// Changelog sonst kapier ich bei Ramona bald nix mehr - Frauen eben

/* 14. Juni 2007
  @Thema: Troop sales figures graph interruption
  @Action: changed or improved
*/

//#######################################################################################
//#######################################################################################
// Startconfig of Ferengi
class Ferengi extends NPC
{
    function Comparison($first,$second,$debug=0)
    {
        if($first==$second)
        {
            return 0;
        }else{
            return 1;
        }
    }

    // Function to create BOT structures
    public function Install($log = INSTALL_LOG_FILE_NPC)
    {
        // We don't use the global variable here since this function can be called also
        // by the installation script.
        $environment = $this->db->queryrow('SELECT * FROM config LIMIT 0 , 1');
        $ACTUAL_TICK = $environment['tick_id'];

        $this->sdl->start_job('Ramona basic system', $log);

        // First of all retrieve the BOT quick storage table        
        if(!($Bot_exe=$this->db->query('SELECT * FROM FHB_Bot LIMIT 0,1'))) {
            // Create the table if does not exists
            $sql = "CREATE TABLE IF NOT EXISTS `FHB_Bot` (
                        `id` int(2) NOT NULL AUTO_INCREMENT,
                        `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
                        `user_name` varchar(32) NOT NULL DEFAULT '',
                        `user_tick` int(10) NOT NULL DEFAULT '0',
                        `user_race` tinyint(3) NOT NULL DEFAULT '0',
                        `user_loginname` varchar(32) NOT NULL DEFAULT '',
                        `planet_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
                        `ship_t_1` int(10) NOT NULL DEFAULT '0',
                        `ship_t_2` int(10) NOT NULL DEFAULT '0',
                        `bot_tick` int(11) NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> cannot create FHB_bot table - ABORTED', $log);
                return;
            }

            // Should work now
            $Bot_exe=$this->db->query('SELECT * FROM FHB_Bot LIMIT 0,1');
        }

        // Check if the BOT exists
        $num_bot=$this->db->num_rows($Bot_exe);
        if($num_bot<1)
        {
            $sql = 'INSERT INTO FHB_Bot (user_id,user_name,user_tick,user_race,user_loginname,planet_id,ship_t_1,ship_t_2)
                    VALUES ("0","","0","0","","0","0","0")';
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> cannot insert FHB_bot data - ABORTED', $log);
                return;
            }
        }

        // So now we should have valid data
        $this->bot = $this->db->queryrow('SELECT * FROM FHB_Bot LIMIT 0,1');

        // Check whether the bot already lives
        if($this->bot['user_id'] == 0) {
            $sql = 'INSERT INTO user (user_active, user_name, user_loginname, user_password, user_email, user_auth_level,
                                      user_race, user_gfxpath, user_skinpath, user_registration_time, user_registration_ip,
                                      user_birthday, user_gender, plz, country,user_enable_sig,user_message_sig,
                                      user_signature, user_notepad, user_options, message_basement)
                    VALUES (1, "Quark(NPG)", "Bot", "'.md5("bundu").'", "xxx@xxx.de", '.STGC_BOT.',
                            5, "", "skin1/", '.time().', "100.0.0.1",
                            "20.04.2007", "w", 76149 , "DE",1,"<br><br><p><b>I.A. of the Ferengi Trade Guild</b></p>",
                            "I live in the computing centre Karlsruhe - so now however conclusion with merry","","","")';
            if(!$this->db->query($sql))
            {
                $this->sdl->log('<b>Error:</b> could not create Ramona - ABORTED', $log);
                return;
            }

            $this->sdl->log('Ramona is created', $log);

            // Update BOT card
            $bot_UID = $this->db->insert_id();

            $sql = 'UPDATE FHB_Bot
                    SET user_id="'.$bot_UID.'",
                        user_name="Quark(NPG)",
                        user_tick="'.$ACTUAL_TICK.'",
                        user_loginname="Bot",
                        user_race="5"
                    WHERE id="'.$this->bot['id'].'"';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not update Ramona ID card - ABORTED', $log);
                return;
            }

            // Avoid a DB query
            $this->bot['user_id'] = $bot_UID;
        }

        // Check whether the bot has a planet
        if($this->bot['planet_id'] == 0) {
            $this->sdl->log('<b>Ramona needs a new body</b>', $log);

            while($this->bot['planet_id'] == 0 or $this->bot['planet_id'] == 'empty') {
                $this->sdl->log('New planet', $log);
                $this->db->lock('starsystems_slots');
                $this->bot['planet_id'] = create_planet($this->bot['user_id'], 'quadrant', 4);
                $this->db->unlock();

                if($this->bot['planet_id'] == 0) {
                    $this->sdl->log('<b>Error:</b> could not create Ramona\'s planet - ABORTED', $log);
                    return;
                }

                $sql = 'UPDATE user
                        SET user_points = "400",
                            user_planets = "1",
                            last_active = "4294967295",
                            user_attack_protection = "'.($ACTUAL_TICK + 1500).'",
                            user_capital = "'.$this->bot['planet_id'].'",
                            active_planet = "'.$this->bot['planet_id'].'"
                        WHERE user_id = "'.$this->bot['user_id'].'"';

                if(!$this->db->query($sql))
                    $this->sdl->log('<b>Error:</b> Could not update Ramona\'s attack protection info - CONTINUED', $log);

                // Bot gets better values for her body, she should always looks good
                $this->sdl->log('Better values for the Planet', $log);
                $sql = 'UPDATE planets SET planet_points = 500,building_1 = 9,building_2 = 9,building_3 = 9,
                            building_4 = 9,building_5 = 9,building_6 = 9,building_7 = 9,building_8 = 9,
                            building_9 = 9,building_10 = 9,building_11 = 9,building_12 = 9,building_13 = 9,
                            unit_1 = 2000,unit_2 = 2000,unit_3 = 2000,unit_4 = 500,unit_5 = 500,unit_6=500,
                            planet_name = "Dealer Base",
                            research_1 = 9,research_2 = 9,research_3 = 9,research_4 = 9,research_5 = 9,
                            workermine_1 = 1600,workermine_2 = 1600,workermine_3 = 1600,resource_4 = 4000
                        WHERE planet_owner = '.$this->bot['user_id'].' and planet_id='.$this->bot['planet_id'];

                if(!$this->db->query($sql))
                    $this->sdl->log('<b>Error:</b> Could not improve Ramona\'s planet - CONTINUED', $log);

                $sql = 'UPDATE FHB_Bot SET planet_id='.$this->bot['planet_id'].' WHERE user_id = '.$this->bot['user_id'];

                if(!$this->db->query($sql))
                    $this->sdl->log('<b>Error:</b> could not update Ramona ID card with planet info - CONTINUED', $log);
            }
        }

        // Check whether the ship already has templates
        $reloading=0;
        if($this->bot['ship_t_1'] == 0) {
/*              $sql = 'INSERT INTO ship_templates
                    (owner, timestamp, name, description, race, ship_torso, ship_class, component_1, component_2, component_3, component_4, component_5, component_6, component_7, component_8, component_9, component_10,
                    value_1, value_2, value_3, value_4, value_5, value_6, value_7, value_8, value_9, value_10, value_11, value_12, value_13, value_14, value_15,
                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6, min_unit_1, min_unit_2, min_unit_3, min_unit_4, max_unit_1, max_unit_2, max_unit_3, max_unit_4, buildtime) VALUES
                    ("'.$this->bot['user_id'].'","'.time().'","Ferengi Trade Ship - Alpha","Transport","'.$this->bot['user_race'].'",1,0,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,
                    "50","50","0","250","250","40","40","40","50","9.99","40","40","1","1","0",
                    "200000","200000","200000","40000","5000","5000","5000","2500","2500","5000","5000","2500","2500",2000,0)';*/
            $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5, 
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4, buildtime)
                    VALUES ("'.$this->bot['user_id'].'","'.time().'","NPC Quark Trade","Transport","'.$this->bot['user_race'].'",1,0,
                            -1,-1,-1,-1,-1,-1,-1,-1,-1,-1,
                            "50","50","0","250","250",
                            "40","40","40","50","9.99",
                            "40","40","1","1","0",
                            "200000","200000","200000","40000","5000","5000",
                            "5000","2500","2500","5000",
                            "5000","2500","2500",2000,0)';
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not save BOT template 1 - ABORTED', $log);
                return;
            }

            // Update ship template id with the freshly created one
            $this->bot['ship_t_1'] = $this->db->insert_id();
            $reloading++;
        }
        if($this->bot['ship_t_2']==0)
        {
/*              $sql= 'INSERT INTO ship_templates
                    (owner, timestamp, name, description, race, ship_torso, ship_class, component_1, component_2, component_3, component_4, component_5, component_6, component_7, component_8, component_9, component_10,
                    value_1, value_2, value_3, value_4, value_5, value_6, value_7, value_8, value_9, value_10, value_11, value_12, value_13, value_14, value_15,
                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6, min_unit_1, min_unit_2, min_unit_3, min_unit_4, max_unit_1, max_unit_2, max_unit_3, max_unit_4, buildtime) VALUES
                    ("'.$this->bot['user_id'].'","'.time().'","Light Hunter - Alpha","Combat ship","'.$this->bot['user_race'].'",3,0,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,
                    "4000","4000","100","6000","6000","60","60","60","60","9.99","60","60","1","1","0",
                    "500000","500000","500000","50000","5000","10000","10000","2500","2500","5000","5000","2500","2500",2000,0)';*/
            $sql= 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                               component_1, component_2, component_3, component_4, component_5,
                                               component_6, component_7, component_8, component_9, component_10,
                                               value_1, value_2, value_3, value_4, value_5,
                                               value_6, value_7, value_8, value_9, value_10,
                                               value_11, value_12, value_13, value_14, value_15,
                                               resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                               min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                               max_unit_1, max_unit_2, max_unit_3, max_unit_4, buildtime)
                    VALUES ("'.$this->bot['user_id'].'","'.time().'","NPC Quark Hunter","Combat ship","'.$this->bot['user_race'].'",3,0,
                            -1,-1,-1,-1,-1,-1,-1,-1,-1,-1,
                            "4000","4000","100","6000","6000",
                            "60","60","60","60","9.99",
                            "60","60","1","1","0",
                            "500000","500000","500000","50000","5000","10000",
                            "10000","2500","2500","5000",
                            "5000","2500","2500",2000,0)';
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> could not save BOT template 2 - ABORTED', $log);
                return;
            }

            // Update ship template id with the freshly created one
            $this->bot['ship_t_2'] = $this->db->insert_id();
            $reloading++;
        }
        if($reloading > 0) {
            $this->sdl->log('Update BOT ship templates', $log);

            $sql = 'UPDATE FHB_Bot
                    SET ship_t_1 = '.$this->bot['ship_t_1'].',
                        ship_t_2 = '.$this->bot['ship_t_2'].'
                    WHERE user_id = '.$this->bot['user_id'];

            if(!$this->db->query($sql)) 
                $this->sdl->log('<b>Error:</b> could not update Ramona ID card with ship templates info - CONTINUED', $log);
        }
        $this->sdl->finish_job('Ramona basic system', $log);

        // ########################################################################################
        // ########################################################################################
        // Change the BOT password
        $this->ChangePassword($log);
    }

    public function Execute($debuggen=0,$title="",$type=0,$color="#ffffff")
    {
        global $sdl,$ACTUAL_TICK,$STARDATE;

        $starttime = ( microtime() + time() );
        $debug_array_logen=0;
        $debug_sql_logen=0;
        if($debug_zu= $this->db->queryrow('SELECT * FROM FHB_debug LIMIT 0,1'))
        {
            if($debug_zu['debug']==0 || $debug_zu['debug']==1)$debuggen=$debug_zu['debug'];
            if($debug_zu['array']==0 || $debug_zu['array']==1)$debug_array_logen=$debug_zu['array'];
            if($debug_zu['style']==0 || $debug_zu['style']==1)$type=$debug_zu['style'];
            if($debug_zu['sql']==0 || $debug_zu['sql']==1)$debug_sql_logen=$debug_zu['sql'];
        }

        $game = new game();

        $this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
            '<b>Starting Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

        // We need some info to allow the BOT to live
        $this->bot = $this->db->queryrow('SELECT * FROM FHB_Bot LIMIT 0,1');
        if($this->bot)
            $this->sdl->log("The conversation with Ramona begins, oh, it is not beautiful, and then, it has such a great personality", TICK_LOG_FILE_NPC);
        else {
            $this->sdl->log('<b>Error:</b> No access to the bot table - ABORTED', TICK_LOG_FILE_NPC);
            return;
        }

        // ########################################################################################
        // ########################################################################################
        // Messages answer
        $messages = array(
            '<center><b>Good morning</b></center><br><br>
            Your message to us has no effect. We handle everything properly and immediately at same.<br>
            If you think we need to blackmail us or put pressure or violence or other thoughts
            against us - forget it immediately.<br>
            Since we are what military defence is concerned, not something to admit, our weapons are loaded
            and our ships combat-ready.<br>If agression should take place, we will strike back with still stronger
            hardness.<br><br>
            If you wanted to say just hello, take that above as a warning. If you realize that our delivery
            system has problem, please report to the Galactic Administration
            (Administrators of the game to understand).
            <br><br>-----------------------------------<br>
            The Ferengi Trade Guild wish you a nice day<br>
            I.A. writer does not receive salary',
            '<center><b>Guten Tag</b></center><br><br>
            Ihre Nachricht an uns wird keine Wirkung erzielen. Wir bearbeiten alles Sachgem&auml;&beta; und gleich.<br>
            Wenn Sie meinen uns erpressen zu m&uuml;ssen, uns unter Druck zu setzen oder sonstige Gewaltgedanken
            gegen uns haben - vergessen Sie diese sofort wieder.<br>
            Da wir uns, was milit&auml;rische Verteidigung angeht, nicht auf irgendwas einlassen, sind unsere Waffen
            geladen und unsere Schiffe kampfbereit. Sollte Agression erfolgen, werden wir mit noch st&auml;rkerer
            H&auml;rte zur&uuml;ckschlagen.<br><br>
            Wenn Sie nur mal Hallo sagen wollten, nehmen Sie das oben als Warnung. Sollten Sie merken, dass
            unser Liefersystem Problem hat, melden Sie das bitte an die Galaktische Administration
            (Administratoren des Spiels f&uuml;r die die es nicht verstehen).
            <br><br>-----------------------------------<br>
            Noch einen schönen Tag w&uuml;nscht Ihnen die Ferengi Handelsgesselschaft<br>
            I.A. Schreiber der kein Gehalt bekommt',
            '<center><b>Buongiorno</b></center><br><br>
            il vostro messaggio non ha alcun effetto su di noi. Essi vengono tutti trattati appropriatamente ed
            immediatamente allo stesso modo.<br>
            Se state pensando di ricattarci, di fare pressione, di usare violenza o di compiere altre azioni ostili 
            contro di noi - scordatevelo immediatamente.<br>
            Conosciamo molto bene ci&ograve; che concerne la difesa militare, non &egrave; cosa da
            nascondere, quindi le nostre armi sono cariche e le nostre navi da combattimento pronte.<br>
            Se dovesse avvenire un&#146;aggressione, ci sar&agrave; una forte ritorsione con maggiore durezza.<br><br>
            Se volevate solo dire &quot;ciao&quot;, prendete questo come un avviso. Se invece avete riscontrato problemi con
            il nostro sistema di consegne, per favore riportatelo all&#146;Amministrazione
            Galattica (gli amministratori del gioco per intenderci).
            <br><br>-----------------------------------<br>
            La Gilda del Commercio Ferengi vi augura una buona giornata<br>
            Gli scrittori dell&#146;I.A. non sono pagati');

        $titles = array('<b>In reply to your letter</b>',
            '<b>Antwort auf ihr Schreiben</b>',
            '<b>Risposta alla sua lettera</b>');

        $this->ReplyToUser($titles,$messages);

        // ########################################################################################
        // ########################################################################################
        // Shiptrade Scheduler
        // So, in order, the troops will have to be paid really well, now it just needs to be made​safe, Tap
        $this->sdl->start_job('Shiptrade Scheduler', TICK_LOG_FILE_NPC);

        $sql = 'SELECT s.*,u.user_name,u.num_auctions,u.language,COUNT(b.id) AS num_bids FROM (ship_trade s)
                LEFT JOIN (user u) ON u.user_id=s.user
                LEFT JOIN (bidding b) ON b.trade_id=s.id
                WHERE s.scheduler_processed = 0 AND s.end_time <='.$ACTUAL_TICK.' GROUP BY s.id';
        if(($q_strade = $this->db->query($sql)) === false) {
            $this->sdl->log('<b>Error:</b> cannot query scheduler shiptrade data! - CONTINUED', TICK_LOG_FILE_NPC);
        }
        else
        {
            $n_shiptrades = 0;
            while($tradedata = $this->db->fetchrow($q_strade))
            {
                // Check if auction had no bids
                if ($tradedata['num_bids']<1)
                {
                    $this->sdl->log('<i>Notice:</i> shiptrade['.$tradedata['id'].'] had no bids', TICK_LOG_FILE_NPC);

                    // Vendor:
                    $log_data=array(
                        'player_name' => '',
                        'auction_id' => $tradedata['id'],
                        'auction_name' => $tradedata['header'],
                        'resource_1' => 0,
                        'resource_2' => 0,
                        'resource_3' => 0,
                        'unit_1' => 0,
                        'unit_2' => 0,
                        'unit_3' => 0,
                        'unit_4' => 0,
                        'unit_5' => 0,
                        'unit_6' => 0,
                        'ship_id' => $tradedata['ship_id'],
                    );

                    // Retrieve language of the vendor
                    switch($tradedata['language'])
                    {
                        case 'GER':
                            $log_title = 'Auktion benachrichtigung (Erfolgslos)';
                        break;
                        case 'ENG':
                            $log_title = 'Auction notification (no success)';
                        break;
                        case 'ITA':
                            $log_title = 'Notifica asta (non riuscita)';
                        break;
                        default:
                            $log_title = 'Auction notification (no success)';
                    }

                    if(!add_logbook_entry($tradedata['user'], LOGBOOK_AUCTION_VENDOR, $log_title, $log_data))
                        $this->sdl->log('<b>Error:</b> Logbook could not be written - '.$tradedata['user'], TICK_LOG_FILE_NPC);;

                    // Unlock the auctioned ship
                    if ($this->db->query('UPDATE ships SET ship_untouchable=0 WHERE ship_id='.$tradedata['ship_id'])!=true)
                        $this->sdl->log('<b>CRITICAL ERROR</b>: shiptrade['.$tradedata['id'].']: failed to free ship['.$tradedata['ship_id'].']! - CONTINUED',
                            TICK_LOG_FILE_NPC);
                }
                else
                {
                    $this->sdl->log('Trade id: '.$tradedata['id'].' ended successfully', TICK_LOG_FILE_NPC);

                    // Retrieve higher bidder
                    $sql = 'SELECT b.*,u.user_id,u.user_name,u.language
                            FROM (bidding b) LEFT JOIN (user u) ON u.user_id=b.user
                            WHERE b.trade_id ="'.$tradedata['id'].'"
                            ORDER BY b.max_bid DESC LIMIT 1';
                    $purchaser = $this->db->queryrow($sql);

                    // Check if there was only one bid
                    if ($tradedata['num_bids']<2)
                    {
                        // End price is simply the entry price
                        $end_price[0]=$tradedata['resource_1'];
                        $end_price[1]=$tradedata['resource_2'];
                        $end_price[2]=$tradedata['resource_3'];
                        $end_price[3]=$tradedata['unit_1'];
                        $end_price[4]=$tradedata['unit_2'];
                        $end_price[5]=$tradedata['unit_3'];
                        $end_price[6]=$tradedata['unit_4'];
                        $end_price[7]=$tradedata['unit_5'];
                        $end_price[8]=$tradedata['unit_6'];
                    }
                    else
                    {
                        // Use the maximum bid achieved to calculate the amount of resources has to be paid
                        $end_price[0]=($tradedata['resource_1']+($purchaser['max_bid'])*$tradedata['add_resource_1']);
                        $end_price[1]=($tradedata['resource_2']+($purchaser['max_bid'])*$tradedata['add_resource_2']);
                        $end_price[2]=($tradedata['resource_3']+($purchaser['max_bid'])*$tradedata['add_resource_3']);
                        $end_price[3]=($tradedata['unit_1']+($purchaser['max_bid'])*$tradedata['add_unit_1']);
                        $end_price[4]=($tradedata['unit_2']+($purchaser['max_bid'])*$tradedata['add_unit_2']);
                        $end_price[5]=($tradedata['unit_3']+($purchaser['max_bid'])*$tradedata['add_unit_3']);
                        $end_price[6]=($tradedata['unit_4']+($purchaser['max_bid'])*$tradedata['add_unit_4']);
                        $end_price[7]=($tradedata['unit_5']+($purchaser['max_bid'])*$tradedata['add_unit_5']);
                        $end_price[8]=($tradedata['unit_6']+($purchaser['max_bid'])*$tradedata['add_unit_6']);
                    }

                    // Vendor:
                    $log_data=array(
                        'player_name' => $purchaser['user_name'],
                        'player_id' => $purchaser['user_id'],
                        'auction_id' => $tradedata['id'],
                        'auction_name' => $tradedata['header'],
                        'resource_1' => $end_price[0],
                        'resource_2' => $end_price[1],
                        'resource_3' => $end_price[2],
                        'unit_1' => $end_price[3],
                        'unit_2' => $end_price[4],
                        'unit_3' => $end_price[5],
                        'unit_4' => $end_price[6],
                        'unit_5' => $end_price[7],
                        'unit_6' => $end_price[8],
                        'ship_id' => $tradedata['ship_id'],
                    );

                    // Retrieve language of the vendor
                    switch($tradedata['language'])
                    {
                        case 'GER':
                            $log_title = 'Auktion benachrichtigung (Verkauft)';
                        break;
                        case 'ENG':
                            $log_title = 'Auction notification (sold)';
                        break;
                        case 'ITA':
                            $log_title = 'Notifica asta (vendita)';
                        break;
                        default:
                             $log_title = 'Auction notification (sold)';
                    }

                    if(!add_logbook_entry($tradedata['user'], LOGBOOK_AUCTION_VENDOR, $log_title, $log_data))
                        $this->sdl->log('<b>Error:</b> Logbook could not be written - '.$tradedata['user'], TICK_LOG_FILE_NPC);;

                    // Purchaser:
                    $log_data=array(
                        'player_name' => $tradedata['user'],
                        'auction_id' => $tradedata['id'],
                        'auction_name' => $tradedata['header'],
                        'resource_1' => $end_price[0],
                        'resource_2' => $end_price[1],
                        'resource_3' => $end_price[2],
                        'unit_1' => $end_price[3],
                        'unit_2' => $end_price[4],
                        'unit_3' => $end_price[5],
                        'unit_4' => $end_price[6],
                        'unit_5' => $end_price[7],
                        'unit_6' => $end_price[8],
                        'ship_id' => $tradedata['ship_id'],
                    );

                    // Retireve language of the purchaser
                    switch($purchaser['language'])
                    {
                        case 'GER':
                            $log_title = 'Auktion benachrichtigung (Gekauft)';
                        break;
                        case 'ENG':
                            $log_title = 'Auction notification (bought)';
                        break;
                        case 'ITA':
                            $log_title = 'Notifica asta (acquisto)';
                        break;
                        default:
                             $log_title = 'Auction notification (bought)';
                    }

                    if(!add_logbook_entry($purchaser['user_id'], LOGBOOK_AUCTION_PURCHASER, $log_title, $log_data))
                        $this->sdl->log('<b>Error:</b> Logbook could not be written - '.$purchaser['user_id'], TICK_LOG_FILE_NPC);

                    $this->sdl->log('Bidder ID: '.$purchaser['user_id'].' and Seller ID: '.$tradedata['user'], TICK_LOG_FILE_NPC);

                    // Insert payment request for purchase user
                    $sql = 'INSERT INTO schulden_table (user_ver,user_kauf,ship_id,
                                                        ress_1,ress_2,ress_3,
                                                        unit_1,unit_2,unit_3,
                                                        unit_4,unit_5,unit_6,
                                                        timestep,auktions_id)
                            VALUES ('.$tradedata['user'].','.$purchaser['user_id'].','.$tradedata['ship_id'].',
                                    '.$end_price[0].','.$end_price[1].','.$end_price[2].',
                                    '.$end_price[3].','.$end_price[4].','.$end_price[5].',
                                    '.$end_price[6].','.$end_price[7].','.$end_price[8].',
                                    '.$ACTUAL_TICK.','.$tradedata['id'].')';

                    if(!$this->db->query($sql))
                    {
                        $this->sdl->log('<b>CRITICAL ERROR:</b> debts were not saved: "'.$sql.'" -  CONTINUED',
                            TICK_LOG_FILE_NPC);
                    }else{
                        $code=$this->db->insert_id();

                        $this->sdl->log('Payment request inserted [Code]: '.$code, TICK_LOG_FILE_NPC);

                        // Create an account for vendor user
                        $sql='INSERT INTO treuhandkonto (code,timestep) VALUES ('.$code.','.$ACTUAL_TICK.')';
                        if(!$this->db->query($sql))
                            $this->sdl->log('<b>CRITICAL ERROR:</b> cannot create auction trust account number: '.$code.' CONTINUED',
                                TICK_LOG_FILE_NPC);

                        // Ship enters in the spacedock of the BOT
                        $spacedock=$this->bot['planet_id']*(-1);
                        $sql = 'UPDATE ships SET user_id="'.$this->bot['user_id'].'",
                                                 fleet_id="'.$spacedock.'"
                                WHERE ship_id="'.$tradedata['ship_id'].'"';
                        if(!$this->db->query($sql)) {
                            $this->sdl->log('<b>CRITICAL ERROR:</b> cannot give the ship to Ramona - '.$sql, TICK_LOG_FILE_NPC);
                        }
                        else {
                            $this->sdl->log('Ramona got a ship ('.$spacedock.')... shiptrade['.$tradedata['id'].'], processed sucessfully',
                                TICK_LOG_FILE_NPC);
                        }
                    }
                }

                ++$n_shiptrades;
            }

            $sql = 'UPDATE ship_trade SET scheduler_processed=1
                    WHERE end_time <= '.$ACTUAL_TICK.' AND scheduler_processed=0
                    LIMIT '.$n_shiptrades;
            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>CRITICAL ERROR:</b> cannot update scheduler_shiptrade data - CONTINUED -'.$sql,
                    TICK_LOG_FILE_NPC);
            }
            unset($tradedata);
        }
        $this->sdl->finish_job('Shiptrade Scheduler', TICK_LOG_FILE_NPC);

        // ########################################################################################
        // ########################################################################################
        // Trust Account monitor
        $this->sdl->start_job('Trust Account monitor', TICK_LOG_FILE_NPC);
        $schulden_bezahlt=0;
        $schuldner=0;
        $spassbieter=0;
        $nachrichten_a =0;
        $konten=0;
        $zeit_raum = 20*24*6;
        $zeit_raum_h = 20*24*3;
        $sql_a=$this->db->query('SELECT * FROM schulden_table WHERE status="0"');
        if(0<$this->db->num_rows($sql_a))
        {
            $this->sdl->log('Examine debts....', TICK_LOG_FILE_NPC);

            $sql = 'SELECT * FROM schulden_table WHERE status=0';
            if(($handel = $this->db->query($sql)) === false) {
                $this->sdl->log('<b>Error:</b> Could not query scheduler shiptrade data! CONTINUED //'.$sql, TICK_LOG_FILE_NPC);
            }else{
                $treffera=$this->db->num_rows();
                while($schulden= $this->db->fetchrow($handel))
                {
                    if($schulden['id']==null)
                    {
                        $this->sdl->log('<b>Error:</b>No ID available', TICK_LOG_FILE_NPC);
                    }else{
                        $sql = $this->db->query('SELECT * FROM treuhandkonto WHERE code="'.$schulden['id'].'"');
                        $treffer=$this->db->num_rows();
                        if($treffer>1){
                            $this->sdl->log('<b>(Error:1000)Programming error:</b>Apparently, there are several trust accounts on the same schulden_table --'.$sql, TICK_LOG_FILE_NPC);
                        }else if($treffer<=0){
                            $this->sdl->log('<b>(Error:2000)Bug:</b>Apparently, there is no trust account to the schulden_table -- '.$sql, TICK_LOG_FILE_NPC);
                        }else if($treffer==1){
                            $treuhand=$this->db->fetchrow($sql);
                            //Now we look whether everything were already paid
                            $wert[1]=$this->Comparison($treuhand['unit_1'],$schulden['unit_1']);
                            $wert[2]=$this->Comparison($treuhand['unit_2'],$schulden['unit_2']);
                            $wert[3]=$this->Comparison($treuhand['unit_3'],$schulden['unit_3']);
                            $wert[4]=$this->Comparison($treuhand['unit_4'],$schulden['unit_4']);
                            $wert[5]=$this->Comparison($treuhand['unit_5'],$schulden['unit_5']);
                            $wert[6]=$this->Comparison($treuhand['unit_6'],$schulden['unit_6']);
                            $wert[7]=$this->Comparison($treuhand['ress_1'],$schulden['ress_1']);
                            $wert[8]=$this->Comparison($treuhand['ress_2'],$schulden['ress_2']);
                            $wert[9]=$this->Comparison($treuhand['ress_3'],$schulden['ress_3']);
                            $wert[10]=$this->Comparison($treuhand['code'],$schulden['id']);
                            $wert_ende=0;
                            
                            for($aaa=1;$aaa<11;$aaa++)
                            {
                                if($wert[$aaa]==1)
                                {
                                    $wert_ende=1;
                                }
                            }
                            if($wert_ende==1)
                            {
                                // Look if anyone has missed his trading
                                $this->sdl->log('--//--||=Everything not paid=||--\\--||'.$schulden['user_kauf'].'||'.$treuhand['code'].'||', TICK_LOG_FILE_NPC);
                                if($treuhand['timestep']!=$schulden['timestep'])
                                {
                                    $this->sdl->log('<b>(Error:3000)</b> - Someone having '.$schulden['user_kauf'].' with code '.$schulden['id'].'/'.$treuhand['code'].' have different time steps', TICK_LOG_FILE_NPC);
                                }else {
                                    if(($treuhand['timestep']+$zeit_raum_h)<=$ACTUAL_TICK && ($treuhand['timestep']+$zeit_raum)>$ACTUAL_TICK && $schulden['mahnung']==0)
                                    {
                                        // Look who got new reminder
                                        $schuldner++;
                                        $User_kauf_V = $this->db->queryrow('SELECT user_id,user_trade FROM user WHERE user_id="'.$schulden['user_kauf'].'"');
                                        $this->db->query('UPDATE user SET user_trade="'.($User_kauf_V['user_trade']+1).'" WHERE user_id="'.$User_kauf_V['user_id'].'"');

                                        /* 10/03/08 - AC: Recover language of the sender */
                                        $sql = 'SELECT language FROM user WHERE user_id='.$schulden['user_kauf'];
                                        if(!($language = $this->db->queryrow($sql)))
                                        {
                                            $this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);
                                            $language['language'] = 'ENG';
                                        }

                                        switch($language['language'])
                                        {
                                            case 'GER':
                                                $text='<center><b>Guten Tag</b></center>
                                                    <br>
                                                    Sie sind dabei die Frist zur Bezahlung zu &Uuml;berschreiten, f&uuml;r einen Handel
                                                    den sie Abgeschlossen haben.<br>
                                                    Hiermit werden sie ermahnt, sollten sie ihre Schulden nicht bezahlen
                                                    werden entsprechende Ma&beta;nahmen eingeleitet.
                                                    <br>--------------------------------------<br>
                                                    Hochachtungsvoll Ferengi Handelsgilde';
                                                $title = '<b>Mahnung die Erste</b>';
                                            break;
                                            case 'ENG':
                                                $text='<center><b>Good morning</b></center><br><br>
                                                    You are about to exceed the time limit for payment, for a trade you have completed.
                                                    Remember that appropriate measures will be take if you should not pay your debts.
                                                    <br>--------------------------------------<br>
                                                    Full respect from The Ferengi Trade Guild';
                                                $title = '<b>Warning</b>';
                                            break;
                                            case 'ITA':
                                                $text='<center><b>Buongiorno</b></center><br><br>
                                                    Il termine di pagamento per un commercio che avete concluso si sta avvicinando.<br>
                                                    Si ricorda che saranno intraprese appropriate misure se non dovesse pagare i suoi debiti.
                                                    <br>--------------------------------------<br>
                                                    Massimo rispetto dalla Gilda del Commercio Ferengi';
                                                $title = '<b>Avvertimento</b>';
                                            break;
                                        }

                                        $this->MessageUser($this->bot['user_id'],$schulden['user_kauf'],$title,$text);
                                        $nachrichten_a++;
                                        //User bekommt verwarnung
                                        if(!$this->db->query('UPDATE schulden_table SET mahnung=mahnung+1 WHERE id="'.$treuhand['code'].'" and user_ver="'.$schulden['user_ver'].'"')) $this->sdl->log('<b>Error:</b> Could not write warning -- UPDATE schulden_table SET mahnung=mahnung+1 WHERE id="'.$treuhand['code'].'" and user_ver="'.$schulden['user_ver'].'"', TICK_LOG_FILE_NPC);
                                    }
                                    if($ACTUAL_TICK>=($zeit_raum+$treuhand['timestep']))
                                    {

                                        $spassbieter++;
                                        $konten++;

                                        // Delete entries
                                        $sql_1 = 'DELETE FROM schulden_table WHERE user_ver="'.$schulden['user_ver'].'" and user_kauf="'.$schulden['user_kauf'].'" and id="'.$schulden['id'].'"';
                                        if(!$this->db->query($sql_1))     $this->sdl->log('<b>(Error:5000)ERROR-DELETE</b> <>'.$sql_1.'', TICK_LOG_FILE_NPC);
                                        $sql_2 = 'DELETE FROM treuhandkonto WHERE code="'.$schulden['id'].'"';
                                        if(!$this->db->query($sql_2))     $this->sdl->log('<b>(Error:5000)ERROR-DELETE</b> <>'.$sql_2.'', TICK_LOG_FILE_NPC);

                                        $sql_1='INSERT INTO FHB_sperr_list VALUES(null,'.$schulden['user_kauf'].','.$ACTUAL_TICK.')';
                                        
                                        if(!$this->db->query($sql_1)) $this->sdl->log('<b>No entry/b>User:'.$schulden['user_kauf'].' - got no further User Trade <>'.$sql_x.'', TICK_LOG_FILE_NPC);
                                        //So jetzt noch beide Benachrichtigen
                                        //TODO Log buch machen - grund wieso es noch nicht gemacht wurde:
                                        /*
                                         [01:38] <Tobi|away> Nachricht oder Log?
                                         [01:38] <Mojo1987> log
                                         [01:39] <Tobi|away> hm
                                         [01:39] <Tobi|away> hast du schonmal logbuch gemacht?
                                         [01:40] <Mojo1987> nee von log hab ich keinen schimmer :D
                                         */
                                        $nachrichten_a++;

                                        /* 10/03/08 - AC: Recover language of the sender */
                                        $sql = 'SELECT language FROM user WHERE user_id='.$schulden['user_kauf'];
                                        if(!($language = $this->db->queryrow($sql)))
                                        {
                                            $this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);
                                            $language['language'] = 'ENG';
                                        }

                                        switch($language['language'])
                                        {
                                            case 'GER':
                                                $text='<center><b>Guten Tag</b></center><br><br>
                                                    Sie haben die Frist zur Bezahlung ihrer Schulden Überschritten,
                                                    damit wird der Handel R&uuml;ckg&auml;ngig gemacht. Sie erhalten daf&uuml;r einen
                                                    Eintrag in das Schuldnerbuch.<br>
                                                    Gesamt Eintr&auml;ge:'.$User_kauf_V['user_trade'].'<br>
                                                    Sollten sie weiter Auffallen wird das ernsthafte Konsequenzen f&uuml;r sie haben.<br>
                                                    Dieser Beschluss ist G&uuml;ltig, sollten sie das Gef&uuml;hl haben ungerecht
                                                    behandelt zu werden, können sie sich &uuml;ber den normalen Beschwerde Weg
                                                    beschweren.<br>
                                                    <br>--------------------------------------<br>
                                                    Hochachtungsvoll Ferengi Handelsgilde';
                                                $title = '<b>Mahnung mit Folgen</b>';
                                            break;
                                            case 'ENG':
                                                $text='<center><b>Good morning</b></center><br><br>
                                                    You have crossed the term of the payment of your debts, thus the trade
                                                    is reversed. You&#146;ll get for it an entry in the debtor&#146;s book.<br>
                                                    Total entries:'.$User_kauf_V['user_trade'].'<br>
                                                    Attention, if you continue, there will be severe consequences for you.<br>
                                                    This decision is important, if you should feel to be treated unfairly, you can
                                                    appeal through the normal way of complain.<br>
                                                    <br>--------------------------------------<br>
                                                    Full respect from The Ferengi Trade Guild';
                                                $title = '<b>Reminder with consequences</b>';
                                            break;
                                            case 'ITA':
                                                $text='<center><b>Buongiorno</b></center><br><br>
                                                    Avete superato il termine ultimo per il pagamento dei vostri debiti, pertanto
                                                    lo scambio sar&agrave; annullato. Per questo ricever&agrave; una nota nel libro
                                                    dei debitori.<br>
                                                    Totale voci:'.$User_kauf_V['user_trade'].'<br>
                                                    Attenzione, se persistete, ci saranno severe conseguenze per voi.<br>
                                                    Questa decisione &egrave; importante, se doveste sentirvi trattati in modo sleale,
                                                    potete appellarvi tramite la normale procedura di reclamo.<br>
                                                    <br>--------------------------------------<br>
                                                    Massimo rispetto dalla Gilda del Commercio Ferengi';
                                                $title = '<b>Avviso delle conseguenze</b>';
                                            break;
                                        }

                                        $this->MessageUser($this->bot['user_id'],$schulden['user_kauf'],$title,$text);


                                        $this->sdl->log('User: '.$schulden['user_ver'].' bekommt sein Schiff: '.$schulden['ship_id'], TICK_LOG_FILE_NPC);
                                        $sql_c='INSERT INTO `FHB_warteschlange` VALUES (NULL , '.$schulden['user_ver'].', '.$schulden['ship_id'].')';
                                        if(!$this->db->query($sql_c))   $this->sdl->log('<b>Error: (Critical)</b>could not put ship in the queue //'.$sql_c, TICK_LOG_FILE_NPC);
                                        $nachrichten_a++;    
                                        $user_name=$this->db->queryrow('SELECT user_name FROM user WHERE user_id='.$schulden['user_kauf'].'');

                                        /* 10/03/08 - AC: Recover language of the sender */
                                        $sql = 'SELECT language FROM user WHERE user_id='.$schulden['user_kauf'];
                                        if(!($language = $this->db->queryrow($sql)))
                                        {
                                            $this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);
                                            $language['language'] = 'ENG';
                                        }

                                        switch($language['language'])
                                        {
                                            case 'GER':
                                                $text='<center><b>Guten Tag</b></center><br><br>
                                                    Ihr Handel mit '.$user_name.' wurde r&uuml;ckg&auml;ngig gemacht, ihr Schiff steht
                                                    ihnen absofort wieder zurverf&uuml;gung. Sollte es jedoch nicht wieder
                                                    zurverf&uuml;gung stehen, wenden sie sich bitte an den Support.<br>
                                                    Um ihren Handelspartner k&uuml;mmern wir uns schon. Er wird eine gerechte Strafe
                                                    bekommen<br>
                                                    <br>--------------------------------------<br>
                                                    Hochachtungsvoll Ferengi Handelsgilde';
                                                $title = '<b>Handel'.$schulden['code'].' ist nichtig</b>';
                                            break;
                                            case 'ENG':
                                                $text='<center><b>Good morning</b></center><br><br>
                                                    Your trade with '.$user_name.' was undone, your ship will come back.
                                                    If it does not return at home, please contact Support.<br>
                                                    We already take care of your trading partner.
                                                    He will receive a fair punishment.<br>
                                                    <br>--------------------------------------<br>
                                                    Full respect from The Ferengi Trade Guild';
                                                $title = '<b>Trade'.$schulden['code'].' is void</b>';
                                            break;
                                            case 'ITA':
                                                $text='<center><b>Buongiorno</b></center><br><br>
                                                    Il vostro commercio con '.$user_name.' &egrave; stato annullato, la vostra nave
                                                    ritorner&agrave; indietro. Se non dovesse tornare, per favore contattare il
                                                    Supporto.<br>
                                                    Ci siamo gi&agrave; presi cura della vostra controparte commerciale.
                                                    Ricever&agrave; una giusta punizione.<br>
                                                    <br>--------------------------------------<br>
                                                    Massimo rispetto dalla Gilda del Commercio Ferengi';
                                                $title = '<b>Scambio'.$schulden['code'].' annullato</b>';
                                            break;
                                        }

                                        $this->MessageUser($this->bot['user_id'],$schulden['user_ver'],$title,$text);
                                    }
                                }
                            }
                            elseif($wert_ende==0)
                            {
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['unit_1'].'||'.$schulden['unit_1'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['unit_2'].'||'.$schulden['unit_2'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['unit_3'].'||'.$schulden['unit_3'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['unit_4'].'||'.$schulden['unit_4'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['unit_5'].'||'.$schulden['unit_5'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['unit_6'].'||'.$schulden['unit_6'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['ress_1'].'||'.$schulden['ress_1'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['ress_2'].'||'.$schulden['ress_2'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$treuhand['ress_3'].'||'.$schulden['ress_3'].'||', TICK_LOG_FILE_NPC);
                                $this->sdl->log('!!!||=Comparison--\\--'.$schulden['id'].'||'.$treuhand['code'].'||', TICK_LOG_FILE_NPC);
                                // Debt to finish and make visible
                                $schulden_bezahlt++;
                                $sql_1 ='UPDATE schulden_table SET status="1" WHERE id='.$treuhand['code'].'';
                                if(!$this->db->query($sql_1)) {
                                    $this->sdl->log('<b>Error:</b> schulden_table, was at '.$sql.' = status will not change', TICK_LOG_FILE_NPC);
                                }
                                $messaget_c='<center><b>Guten Tag</b></center>
                                    <br>
                                    Sie haben Neue Ressourcen und/oder Truppen auf ihrem Treuhandkonto.
                                    <br>--------------------------------------<br>
                                    Hochachtungsvoll Ferengi Handelsgilde';

                                // Ship given to the user
                                $this->sdl->log('User: '.$schulden['user_kauf'].' got a ship: '.$schulden['ship_id'], TICK_LOG_FILE_NPC);
                                $messaget_a='<center><b>Guten Tag</b></center>
                                    <br>
                                    Sie können ihr ersteigertes Schiff abhohlen.
                                    <br>--------------------------------------<br>
                                    Hochachtungsvoll Ferengi Handelsgilde';

                                $sql_c='INSERT INTO `FHB_warteschlange` VALUES (NULL , '.$schulden['user_kauf'].', '.$schulden['ship_id'].')';
                                if(!$this->db->query($sql_c))   $this->sdl->log('<b>Error: (Critical)</b>could not put ship in the queue //'.$sql_c, TICK_LOG_FILE_NPC);
                                $konten++;

                            }
                        }
                    }
                }
            }
        }else{
            $this->sdl->log('[MESSAGE]No debts available', TICK_LOG_FILE_NPC);
        }
        // Empty accounts destruction
        $deleted_accounts = 0;
        if(($konton_destroy=$this->db->query('SELECT * FROM schulden_table WHERE status=2'))==true)
        {
            while($konton_destroy_t=$this->db->fetchrow($konton_destroy))
            {
                $sql_e='DELETE FROM schulden_table WHERE id='.$konton_destroy_t['id'].' AND status=2';
                if(!$this->db->query($sql_e))
                {
                    $this->sdl->log('<b>Error: (Critical)</b>Could not delete entry in schulden_table //Code:'.$konton_destroy_t['id'].'//'.$sql_c, TICK_LOG_FILE_NPC);
                }else{
                    $sql_d='DELETE FROM treuhandkonto WHERE code="'.$konton_destroy_t['id'].'"';
                    if(!$this->db->query($sql_d))
                    {
                        $this->sdl->log('<b>Error: (Critical)</b>Could not delete entry in treuhandkonto //Code:'.$konton_destroy_t['id'].'//'.$sql_c, TICK_LOG_FILE_NPC);
                    }
                    $deleted_accounts++;
                }
            }
        }
        else{$this->sdl->log('[Empties accounts] Accounts query was not executed || '.$konton_destroy.' = status will not change', TICK_LOG_FILE_NPC);}
        $this->sdl->log('Paid debts: '.$schulden_bezahlt, TICK_LOG_FILE_NPC);
        $this->sdl->log('Number of debtors: '.$schuldner, TICK_LOG_FILE_NPC);
        $this->sdl->log('Deleted accounts: '.$deleted_accounts, TICK_LOG_FILE_NPC);
        $this->sdl->log('Number of fun bidders: '.$spassbieter, TICK_LOG_FILE_NPC);
        $this->sdl->log('Number of messages: '.$nachrichten_a++, TICK_LOG_FILE_NPC);
        $this->sdl->finish_job('Trust Account monitor', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // ########################################################################################
        // Calculate troops sales
        $this->sdl->start_job('Stock trading ship', TICK_LOG_FILE_NPC);
        $min_tick=$ACTUAL_TICK-(20*24);
        if($min_tick<0) $min_tick=0;
        $max_tick=$ACTUAL_TICK;
        $new_graph = (($ACTUAL_TICK % 20) == 0) ? 'true' : 'false';
        $this->sdl->log('Actual Tick: '.$ACTUAL_TICK.' -- '.$new_graph.' -- Period of: '.$min_tick, TICK_LOG_FILE_NPC);

        if($new_graph == 'true')
        {
            $this->sdl->log('New graph is made.....', TICK_LOG_FILE_NPC);
            include("simple_graph.class.php");
            exec('cd '.FILE_PATH_hg.'kurs/; rm -f *.png');

            $this->sdl->start_job('Purchase - Unit', TICK_LOG_FILE_NPC);
            $this->graph_draw("unit_1");
            $this->graph_draw("unit_2");
            $this->graph_draw("unit_3");
            $this->graph_draw("unit_4");
            $this->graph_draw("unit_5");
            $this->graph_draw("unit_6");
            $this->sdl->finish_job('Purchase - Unit', TICK_LOG_FILE_NPC);
        }
        $this->sdl->finish_job('Stock trading ship', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // ########################################################################################
        // Users release from TC
        $this->sdl->start_job('User release', TICK_LOG_FILE_NPC);
        $sql = "SELECT user_id,user_trade,trade_tick FROM user WHERE user_trade>0 AND trade_tick<=".$ACTUAL_TICK." AND trade_tick!=0";
        if(!$temps=$this->db->query($sql)) $this->sdl->log('<b>Error:</b> User query went wrong -- '.$sql, TICK_LOG_FILE_NPC);
        $anzahl_freigeben=0; 
        while($result = $this->db->fetchrow($temps))
        {
            //[23:19] <Secius> there is a sql statement
            //[23:19] <Secius> but nothing sends it to the DB
            //[23:19] <Mojo1987> lol
            //[23:19] <Mojo1987> the good^^
            $sql_x='UPDATE user SET trade_tick=0 WHERE user_id="'.$result['user_id'].'"';
            $this->sdl->log('User:'.$result['user_id'].' got the freedom to the women of this Galaxy fear to empty', TICK_LOG_FILE_NPC);
            if(!$this->db->query($sql_x)) $this->sdl->log('<b>Error:</b> update of the user --'.$sql_x, TICK_LOG_FILE_NPC);
            $anzahl_freigeben++;

            /* 17/03/08 - AC: Recover language of the sender */
            $sql = 'SELECT language FROM user WHERE user_id='.$result['user_id'];
            if(!($language = $this->db->queryrow($sql)))
            {
                $this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);
                $language['language'] = 'ENG';
            }

            switch($language['language'])
            {
                case 'GER':
                    $text ='<b>Ihr HZ-Bann ist zu ende</b>
                        <br>
                        Sollten sie keine Zugriff haben, bitte bei den Supportern melden - dazu die
                        Uhrzeit angeben wo sie diese Nachricht bekommen haben. Sonst kann ihnen nicht
                        geholfen werden.
                        <br>--------------------------------------<br>
                        Vorsitzender des Ferengi Finanz- und Handelsministeriums';
                    $title = '<b>HZ-Entbannung</b>';
                break;
                case 'ENG':
                    $text ='<b>Your CC-ban is over</b>
                        <br>
                        If you have no access to Commercial Centre, please report to the support
                        staff - in addition indicate to them rhe time of this message. Otherwise
                        you cannot be helped by them.
                        <br>--------------------------------------<br>
                        Chairman of the Financial Ferengi - and Trade Ministry';
                    $title = '<b>CC-ban ended</b>';
                break;
                case 'ITA':
                    $text='<b>La vostra sospensione CC &egrave; terminata</b>
                        <br>
                        Se non doveste avere accesso al Centro Commerciale, per favore informare lo
                        staff di supporto - in aggiunta indicare loro la data di questo messaggio.
                        Altrimenti non potrete essere aiutati da loro.
                        <br>--------------------------------------<br>
                        Presidente delle Finanze Ferengi - e Ministro del Commercio';
                    $title = '<b>Sospensione CC terminata</b>';
                break;
            }
            $this->MessageUser($this->bot['user_id'],$result['user_id'],$title,$text);
        }
        $this->sdl->log('There were '.$anzahl_freigeben.' user released', TICK_LOG_FILE_NPC);
        $this->sdl->finish_job('User release', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // ########################################################################################
        // Users lock for TC
        $this->sdl->start_job('User lock', TICK_LOG_FILE_NPC);
        $sql = "SELECT count(*) as anzahl,user_id FROM FHB_sperr_list GROUP By user_id";
        if(!$temps=$this->db->query($sql)) $this->sdl->log('<b>Error:</b> User query went wrong -- '.$sql, TICK_LOG_FILE_NPC);
        $anzahl_sperren=0; 
        $user_liste='';
        while($result = $this->db->fetchrow($temps))
        {
            $sperre=0;
            if($result['anzahl']>0)
            {
                if($result['anzahl']<3)
                {
                    $sperre=$result['anzahl']*480*2;
                }else if($result['anzahl']>2)
                {
                    $sperre=(pow($result['anzahl'],2))*360;
                }
                $sql_abfrage_1='SELECT user_id,user_trade,trade_tick FROM user WHERE user_id="'.$result['user_id'].'"';

                if(!($sql_abfrage_1=$this->db->queryrow($sql_abfrage_1))) $this->sdl->log('<b>Error:</b> User query went wrong -- '.$sql_abfrage_1, TICK_LOG_FILE_NPC);

                if($sql_abfrage_1['user_trade']<$result['anzahl'] && $sql_abfrage_1['trade_tick']!=0)
                {
                    $anzahl_sperren++;
                    $sql_x='UPDATE user SET user_trade='.$result['anzahl'].',trade_tick=trade_tick+'.$sperre.' WHERE user_id="'.$result['user_id'].'"';
                    $this->sdl->log('User:'.$result['user_id'].' has gotten a higher punishment - We, which was done to those injustice, are bad', TICK_LOG_FILE_NPC);
                    if(!$this->db->query($sql_x)) $this->sdl->log('<b>Error:</b> User '.$result['user_id'].' cannot be locked for '.$sperre.' ticks</b>', TICK_LOG_FILE_NPC);

                    /* 17/03/08 - AC: Recover language of the sender */
                    $sql = 'SELECT language FROM user WHERE user_id='.$result['user_id'];
                    if(!($language = $this->db->queryrow($sql)))
                    {
                        $this->sdl->log('<b>Error:</b>Cannot read user language!', TICK_LOG_FILE_NPC);
                        $language['language'] = 'ENG';
                    }

                    switch($language['language'])
                    {
                        case 'GER':
                            $text ='<b>Sie haben einen Bann f&uuml;rs HZ bekommen</b>
                                <br>Aufgrund ihrer erneuten Schulden bei Auktionen bekommen sie eine weitere Sperre von '.$sperre.' Ticks.<br>
                                Beschwerden sind sinnlos. Einfach das n&auml;chste mal bezahlen.<br><br>
                                Der Grund kann aber auch fehlverhalten im HZ sein.
                                <br>--------------------------------------<br>
                                Vorsitzender des Ferengi Finanz- und Handelsministeriums';
                            $title = '<b>HZ-Bann</b>';
                        break;
                        case 'ENG':
                            $text ='<b>You have received a ban for CC</b>
                                <br>Due to your renewed debts with auctions you get a further block of '.$sperre.' Ticks.<br>
                                Complaints are senseless. Simply pay the next time.<br><br>
                                However, the reason can also be failures in the CC.
                                <br>--------------------------------------<br>
                                Chairman of the Financial Ferengi - and Trade Ministry';
                            $title = '<b>CC-ban</b>';
                        break;
                        case 'ITA':
                            $text='<b>Avete ricevuto una sospesione per il CC</b>
                                <br>A causa dei vostri rinnovati debiti con le aste avete ricevuto un blocco di '.$sperre.' tick.<br>
                                Reclamare &egrave; insensato. Basta pagare la prossima volta.<br><br>
                                Tuttavia, ci potrebbe essere un errore nel sistema del CC.
                                <br>--------------------------------------<br>
                                Presidente delle Finanze Ferengi - e Ministro del Commercio';
                            $title = '<b>Sospensione CC</b>';
                        break;
                    }
                    $this->MessageUser($this->bot['user_id'],$result['user_id'],$title,$text);

                }else if($sql_abfrage_1['user_trade']<$result['anzahl'] && $sql_abfrage_1['trade_tick']==0)
                {
                    $anzahl_sperren++;
                    $endtick=$sperre+$ACTUAL_TICK;
                    $sql_x='UPDATE user SET user_trade='.$result['anzahl'].',trade_tick='.($sperre+$ACTUAL_TICK).' WHERE user_id="'.$result['user_id'].'"';
                    $this->sdl->log('User:'.$result['user_id'].' was banned - which he will meet his doom', TICK_LOG_FILE_NPC);
                    if(!$this->db->query($sql_x)) $this->sdl->log('<b>Error:</b> User '.$result['user_id'].' cannot be locked for '.($sperre*3).' minutes.</b>', TICK_LOG_FILE_NPC);

                    /* 17/03/08 - AC: Recover language of the sender */
                    $sql = 'SELECT language FROM user WHERE user_id='.$result['user_id'];
                    if(!($language = $this->db->queryrow($sql)))
                    {
                        $this->sdl->log('<b>Error:</b> Cannot read user language!', TICK_LOG_FILE_NPC);
                        $language['language'] = 'ENG';
                    }

                    switch($language['language'])
                    {
                        case 'GER':
                            $text ='<b>Sie haben einen Bann f&uuml;rs HZ bekommen</b>
                                <br>Aufgrund ihrer Schulden bei Auktionen bekommen sie eine Sperre von '.($sperre*3).' Minuten.<br>
                                Beschwerden sind sinnlos. Einfach das n&auml;chste mal bezahlen.<br><br>
                                Der Grund kann aber auch fehlverhalten im HZ sein.
                                <br>--------------------------------------<br>
                                Vorsitzender des Ferengi Finanz- und Handelsministeriums';
                            $title = '<b>HZ-Bann</b>';
                        break;
                        case 'ENG':
                            $text ='<b>You have received a ban for CC</b>
                                <br>Due to your debts with auctions you get a block of '.($sperre*3).' minutes.<br>
                                Complaints are senseless. Simply pay the next time.<br><br>
                                However, the reason can also be failures in the CC.
                                <br>--------------------------------------<br>
                                Chairman of the Financial Ferengi - and Trade Ministry';
                            $title = '<b>CC-ban</b>';
                        break;
                        case 'ITA':
                            $text='<b>Avete ricevuto una sospesione per il CC</b>
                                <br>A causa dei vostri debiti con le aste avete ricevuto un blocco di '.($sperre*3).' minuti.<br>
                                Reclamare &egrave; insensato. Basta pagare la prossima volta.<br><br>
                                Tuttavia, ci potrebbe essere un errore nel sistema del CC.
                                <br>--------------------------------------<br>
                                Presidente delle Finanze Ferengi - e Ministro del Commercio';
                            $title = '<b>Sospensione CC</b>';
                        break;
                    }
                    $this->MessageUser($this->bot['user_id'],$result['user_id'],$title,$text);
                }
                else if($sql_abfrage_1['user_trade']==$result['anzahl'])
                {
                    $user_liste.='| '.$result['user_id'].' |';

                }
                
                
            }
            
        }
        $this->sdl->log('User '.$user_liste.' have their penalties and let me turn my problem in women clarify matters.', TICK_LOG_FILE_NPC);
        $this->sdl->log('There were '.$anzahl_sperren.' User locked', TICK_LOG_FILE_NPC);
        $this->sdl->finish_job('User lock', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // ########################################################################################

        /* 04/03/08 - AC: Hmmm, something is missing here... */

        /****
         **
         ** 04/03/08 - AC: Ok, I've guessed that here we need to update the resource availability of Ramona
         **
         ****/
        $this->sdl->start_job('Update Ramona resources svailability', TICK_LOG_FILE_NPC);

        // Read resources and units available on Ramona's planet
        $sql='SELECT unit_1, unit_2, unit_3, unit_4, unit_5, unit_6, resource_1, resource_2, resource_3 FROM planets
            WHERE planet_id = '.$this->bot['planet_id'];

        $resources = $this->db->queryrow($sql);

        $this->sdl->log('Available resources: '.$resources['resource_1'].' -- '.$resources['resource_2'].' -- '.$resources['resource_3'], TICK_LOG_FILE_NPC);

        $this->sdl->log('Available units: '.$resources['unit_1'].' -- '.$resources['unit_2'].' -- '.$resources['unit_3'].' -- '.$resources['unit_4'].' -- '.$resources['unit_5'].' -- '.$resources['unit_6'], TICK_LOG_FILE_NPC);

        // Check if the table for the Commercial Centre is already present
        $sql='SELECT unit_1, unit_2, unit_3, unit_4, unit_5, unit_6, ress_1, ress_2, ress_3 FROM FHB_Handels_Lager
            WHERE id=1';

        if(!($tradecenter = $this->db->queryrow($sql)))
        {
            $this->sdl->log('<b>Warning:</b> Table FHB_Handels_Lager was empty! CONTINUED', TICK_LOG_FILE_NPC);

            // Create an entry item in the table
            $sql = 'INSERT INTO FHB_Handels_Lager (unit_1, unit_2,unit_3, unit_4,unit_5,unit_6,ress_1,ress_2,ress_3)
                VALUES (0, 0, 0, 0, 0, 0, 0, 0, 0)';

            if(!$this->db->query($sql)) {
                $this->sdl->log('<b>Error:</b> Could not insert Handelslager - '.$update_res, TICK_LOG_FILE_NPC);
            }
        }
        else
        {
            $this->db->lock('FHB_Handels_Lager');

            $pick_u = array();

            $pick_u[0] = $pick_u[1] = $pick_u[2] = $pick_u[3] = $pick_u[4] = $pick_u[5] = 0;

            // Pick up units only if there is a little stock pile
            if($tradecenter['unit_1'] < 450)
            {
                if($resources['unit_1'] > 500)
                    $pick_u[0] = $resources['unit_1'] / 450;
            }
            if($tradecenter['unit_2'] < 450)
            {
                if($resources['unit_2'] > 500)
                    $pick_u[1] = $resources['unit_2'] / 450;
            }
            if($tradecenter['unit_3'] < 450)
            {
                if($resources['unit_3'] > 500)
                    $pick_u[2] = $resources['unit_3'] / 450;
            }
            if($tradecenter['unit_4'] < 200)
            {
                if($resources['unit_4'] > 500)
                    $pick_u[3] = $resources['unit_4'] / 200;
            }
            if($tradecenter['unit_5'] < 200)
            {
                if($resources['unit_5'] > 500)
                    $pick_u[4] = $resources['unit_5'] / 200;
            }
            if($tradecenter['unit_6'] < 200)
            {
                if($resources['unit_6'] > 500)
                    $pick_u[5] = $resources['unit_6'] / 200;
            }

            $this->sdl->log('Add units: '.$pick_u[0].' -- '.$pick_u[1].' -- '.$pick_u[2].' -- '.$pick_u[3].' -- '.$pick_u[4].' -- '.$pick_u[5], TICK_LOG_FILE_NPC);

            // 200408 DC ---- Nothing is for nothing
            $pick_r = array();

            $pick_r[0] = $pick_r[1] = $pick_r[2] = 0;
            
            // Soldier lvl 1
            if ($pick_u[0] > 0) {
                $metal_cost   = ($pick_u[0]*280);
                $mineral_cost = ($pick_u[0]*235);
                if($metal_cost > $tradecenter['ress_1']  || $mineral_cost > $tradecenter['ress_2'] )
                    $pick_u[0] = 0;
                else {
                    $this->sdl->log('Resources for Lvl 1 Soldiers: Metals '.$metal_cost.' Minerals '.$mineral_cost, TICK_LOG_FILE_NPC);
                    $pick_r[0] += $metal_cost;
                    $pick_r[1] += $mineral_cost;
                }
            }
            // Soldier lvl 2
            if ($pick_u[1] > 0) {
                $metal_cost   = ($pick_u[1]*340);
                $mineral_cost = ($pick_u[1]*225);
                if($metal_cost > $tradecenter['ress_1']  || $mineral_cost > $tradecenter['ress_2'] )  
                    $pick_u[1] = 0;
                else {
                    $this->sdl->log('Resources for Lvl 2 Soldiers: Metals '.$metal_cost.' Minerals '.$mineral_cost, TICK_LOG_FILE_NPC);
                    $pick_r[0] += $metal_cost;
                    $pick_r[1] += $mineral_cost;
                }
            }
            // Soldier lvl 3
            if ($pick_u[2] > 0) {
                $metal_cost     = ($pick_u[2]*650);
                $mineral_cost   = ($pick_u[2]*450);
                $dilithium_cost = ($pick_u[2]*350);
                if($metal_cost > $tradecenter['ress_1']  || $mineral_cost > $tradecenter['ress_2']  || $dilithium_cost > $tradecenter['ress_3'] )
                    $pick_u[2] = 0;
                else {
                    $this->sdl->log('Resources for Lvl 3 Soldiers: Metals '.$metal_cost.' - Minerals '.$mineral_cost.' - Dilithium '.$dilithium_cost, TICK_LOG_FILE_NPC);
                    $pick_r[0] += $metal_cost;
                    $pick_r[1] += $mineral_cost;
                    $pick_r[2] += $dilithium_cost;
                }
            }
            // Captains
            if ($pick_u[3] > 0) {
                $metal_cost     = ($pick_u[3]*410);
                $mineral_cost   = ($pick_u[3]*210);
                $dilithium_cost = ($pick_u[3]*115);
                if($metal_cost > $tradecenter['ress_1']  || $mineral_cost > $tradecenter['ress_2']  || $dilithium_cost > $tradecenter['ress_3'] )
                    $pick_u[3] = 0;
                else {
                    $this->sdl->log('Resources for Captains: Metals '.$metal_cost.' - Minerals '.$mineral_cost.' - Dilithium '.$dilithium_cost, TICK_LOG_FILE_NPC);
                    $pick_r[0] += $metal_cost;
                    $pick_r[1] += $mineral_cost;
                    $pick_r[2] += $dilithium_cost;
                }
            }
            // Techs
            if ($pick_u[4] > 0) {
                $metal_cost     = ($pick_u[4]*650);
                $mineral_cost   = ($pick_u[4]*440);
                $dilithium_cost = ($pick_u[4]*250);
                if($metal_cost > $tradecenter['ress_1']  || $mineral_cost > $tradecenter['ress_2']  || $dilithium_cost > $tradecenter['ress_3'] )
                    $pick_u[4] = 0;
                else {
                    $this->sdl->log('Resources for Techs: Metals '.$metal_cost.' - Minerals '.$mineral_cost.' - Dilithium '.$dilithium_cost, TICK_LOG_FILE_NPC);
                    $pick_r[0] += $metal_cost;
                    $pick_r[1] += $mineral_cost;
                    $pick_r[2] += $dilithium_cost;
                }
            }
            // Docs
            if ($pick_u[5] > 0) {
                $metal_cost     = ($pick_u[5]*1000);
                $mineral_cost   = ($pick_u[5]*500);
                $dilithium_cost = ($pick_u[5]*200);
                if($metal_cost > $tradecenter['ress_1']  || $mineral_cost > $tradecenter['ress_2']  || $dilithium_cost > $tradecenter['ress_3'] ) 
                    $pick_u[5] = 0;
                else {
                    $this->sdl->log('Resources for Docs: Metals '.$metal_cost.' - Minerals '.$mineral_cost.' - Dilithium '.$dilithium_cost, TICK_LOG_FILE_NPC);
                    $pick_r[0] += $metal_cost;
                    $pick_r[1] += $mineral_cost;
                    $pick_r[2] += $dilithium_cost;
                }
            }
            // DC ----

            // Pick up resources only if there is a little stock pile
            // 200408 DC ----  Sorry, non more fundings to the CC
            // 220610 AC ----  But it's needed after a galaxy reset
            if(PICK_RESOURCES_FROM_PLANET) {
                if($tradecenter['ress_1'] < 350000)
                {
                    if($resources['resource_1'] > 150000)
                        $pick_r[0] = $resources['resource_1']  /  100;
                }
                if($tradecenter['ress_2'] < 350000)
                {
                    if($resources['resource_2'] > 150000)
                        $pick_r[1] = $resources['resource_2']  /  100;
                }
                if($tradecenter['ress_3'] < 350000)
                {
                    if($resources['resource_3'] > 150000)
                        $pick_r[2] = $resources['resource_3']  /  125;
                }

                $this->sdl->log('Add resources: '.$pick_r[0].' -- '.$pick_r[1].' -- '.$pick_r[2], TICK_LOG_FILE_NPC);

                $update_res='UPDATE FHB_Handels_Lager SET
                    unit_1=unit_1+'.$pick_u[0].',unit_2=unit_2+'.$pick_u[1].',unit_3=unit_3+'.$pick_u[2].',
                    unit_4=unit_4+'.$pick_u[3].',unit_5=unit_5+'.$pick_u[4].',unit_6=unit_6+'.$pick_u[5].',
                    ress_1=ress_1+'.$pick_r[0].',ress_2=ress_2+'.$pick_r[1].',ress_3=ress_3+'.$pick_r[2].' WHERE id=1';
                }
            else {
                $this->sdl->log('Picking resources from CC: '.$pick_r[0].' -- '.$pick_r[1].' -- '.$pick_r[2], TICK_LOG_FILE_NPC);

                $update_res='UPDATE FHB_Handels_Lager SET
                    unit_1=unit_1+'.$pick_u[0].',unit_2=unit_2+'.$pick_u[1].',unit_3=unit_3+'.$pick_u[2].',
                    unit_4=unit_4+'.$pick_u[3].',unit_5=unit_5+'.$pick_u[4].',unit_6=unit_6+'.$pick_u[5].',
                    ress_1=ress_1-'.$pick_r[0].',ress_2=ress_2-'.$pick_r[1].',ress_3=ress_3-'.$pick_r[2].' WHERE id=1';
            }

            // Update resources and units available in the commercial centre
            if(!$this->db->query($update_res)) {
                $this->sdl->log('<b>Error:</b> Could not update Handelslager - '.$update_res, TICK_LOG_FILE_NPC);
            }
            else
            {
                // Remove resources from Ramona's planet
                if(PICK_RESOURCES_FROM_PLANET) {
                    $sql='UPDATE planets SET
                            unit_1=unit_1-'.$pick_u[0].',unit_2=unit_2-'.$pick_u[1].',unit_3=unit_3-'.$pick_u[2].',
                            unit_4=unit_4-'.$pick_u[3].',unit_5=unit_5-'.$pick_u[4].',unit_6=unit_6-'.$pick_u[5].',
                            resource_1=resource_1-'.$pick_r[0].',resource_2=resource_2-'.$pick_r[1].',
                            resource_3=resource_3-'.$pick_r[2].'
                        WHERE planet_id = '.$this->bot['planet_id'];
                }
                else {
                    $sql='UPDATE planets SET
                            unit_1=unit_1-'.$pick_u[0].',unit_2=unit_2-'.$pick_u[1].',unit_3=unit_3-'.$pick_u[2].',
                            unit_4=unit_4-'.$pick_u[3].',unit_5=unit_5-'.$pick_u[4].',unit_6=unit_6-'.$pick_u[5].'
                        WHERE planet_id = '.$this->bot['planet_id'];
                }
                if(!$this->db->query($sql)) {
                    $this->sdl->log('<b>Error:</b> Could not update Ramona\'s planet - '.$sql, TICK_LOG_FILE_NPC);
                }

                // If needed, we have to tell to Ramona to create some fresh units
                $sql='SELECT unit_1, unit_2, unit_3, unit_4, unit_5, unit_6 FROM planets
                    WHERE planet_id = '.$this->bot['planet_id'];

                if($units = $this->db->queryrow($sql))
                {
                    //
                    // Actually we simply reinsert initial value in the table...
                    //
                    $train_u = array();

                    $train_u[0] = $train_u[1] = $train_u[2] = $train_u[3] = $train_u[4] = $train_u[5] = 0;

                    if($units['unit_1'] <= 500)
                        $train_u[0] = 1000;
                    if($units['unit_2'] <= 500)
                        $train_u[1] = 1000;
                    if($units['unit_3'] <= 500)
                        $train_u[2] = 1000;
                    if($units['unit_4'] <= 500)
                        $train_u[3] = 1000;
                    if($units['unit_5'] <= 500)
                        $train_u[4] = 1000;
                    if($units['unit_6'] <= 500)
                        $train_u[5] = 1000;

                    // Have we something to do?
                    if($train_u[0] != 0 || $train_u[1] != 0 || $train_u[2] != 0 ||
                       $train_u[3] != 0 || $train_u[4] != 0 || $train_u[5] != 0)
                    {
                        $this->sdl->log('Produce new units: '.$train_u[0].' -- '.$train_u[1].' -- '.$train_u[2].' -- '.$train_u[3].' -- '.$train_u[4].' -- '.$train_u[5], TICK_LOG_FILE_NPC);

                        $sql='UPDATE planets SET
                            unit_1=unit_1+'.$train_u[0].',unit_2=unit_2+'.$train_u[1].',unit_3=unit_3+'.$train_u[2].',
                            unit_4=unit_4+'.$train_u[3].',unit_5=unit_5+'.$train_u[4].',unit_6=unit_6+'.$train_u[5].'
                            WHERE planet_id = '.$this->bot['planet_id'];

                        if(!$this->db->query($sql)) {
                            $this->sdl->log('<b>Error:</b> Could not instruct Ramona to produce new units - '.$sql, TICK_LOG_FILE_NPC);
                        }
                    }
                }
                else
                    $this->sdl->log('<b>Error:</b> Cannot read from Ramona\'s planet!', TICK_LOG_FILE_NPC);
            }
            $this->db->unlock('FHB_Handels_Lager');
        }

        $this->sdl->finish_job('Update Ramona resources svailability', TICK_LOG_FILE_NPC);

        // ########################################################################################
        // ########################################################################################
        // ########################################################################################
        // Ferengi Trade Center blacklist Cleanup
        $this->sdl->start_job('Remove card index corpse in the check list', TICK_LOG_FILE_NPC);
        $sql = "SELECT count(*) as anzahl,user_id FROM FHB_sperr_list GROUP By user_id";
        if(!$temps=$this->db->query($sql)) $this->sdl->log('<b>Error:</b> User query went wrong -- delete '.$sql, TICK_LOG_FILE_NPC);
        $anzahl_sperren=0; 
        while($result = $this->db->fetchrow($temps))
        {
            $sql_select=$this->db->query('SELECT user_id FROM user WHERE user_id='.$result['user_id']);
            if($this->db->num_rows($sql_select)<=0)
            {
                if(!$this->db->query('DELETE FROM FHB_sperr_list WHERE user_id='.$result['user_id'].''))
                    $this->sdl->log('<b>Error:</b> Could not delete debts of dead user '.$sql, TICK_LOG_FILE_NPC);
                $this->sdl->log('Punishments of user: '.$result['user_id'].' deleted', TICK_LOG_FILE_NPC);
            }
        }
        $this->sdl->finish_job('Remove card index corpse in the check list', TICK_LOG_FILE_NPC);
        // ########################################################################################
        // Learning is boring here fixed the cheating of resources by troops sale
        $this->sdl->start_job('Soldier Transaction', TICK_LOG_FILE_NPC);
        $transaktionen=0;
        $sql='SELECT * FROM FHB_cache_trupp_trade WHERE tick<='.$ACTUAL_TICK.'';
        $sql=$this->db->query($sql);
        while($cache_trade = $this->db->fetchrow($sql))
        {
            $transaktionen++;
            $this->db->lock('FHB_Handels_Lager');
            $update_action='UPDATE FHB_Handels_Lager SET unit_1=unit_1+'.$cache_trade['unit_1'].',unit_2=unit_2+'.$cache_trade['unit_2'].',unit_3=unit_3+'.$cache_trade['unit_3'].',unit_4=unit_4+'.$cache_trade['unit_4'].',unit_5=unit_5+'.$cache_trade['unit_5'].',unit_6=unit_6+'.$cache_trade['unit_6'].' WHERE id=1';
            if(!$this->db->query($update_action))$this->sdl->log('<b>Error:</b> Could not update Handelslager - '.$update_action, TICK_LOG_FILE_NPC);
            $this->db->unlock('FHB_Handels_Lager');
            $delete_action='DELETE FROM FHB_cache_trupp_trade WHERE id='.$cache_trade['id'].'';
            if(!$this->db->query($delete_action))$this->sdl->log('<b>Error:</b> Could not update Handelslager - '.$delete_action, TICK_LOG_FILE_NPC);
            
        }
        $this->sdl->log('Transactions: '.$transaktionen, TICK_LOG_FILE_NPC);
        $this->sdl->finish_job('Soldier Transaction', TICK_LOG_FILE_NPC);
        /*
        FHB_stats graph characters
        */
        // ########################################################################################
        // Sensors monitoring and user warning

        $messages=array('<br><center><b>Stop the attack immediately!</b></center>
                        <br>You appeared on our sensors. Our fleets are on intercepting course.<br><br>
                        Flying on would result in a war which we will lead without taking into consideration losses against you and your allies.
                        <br>There are 5 small Light Attack Hunter on the way to your planet <TARGETPLANET>.
                        <br>--------------------------------------<br>
                        Commander of Alpha-Fleet of the Trading Empire',
                        '<br><center><b>Stellen Sie sofort den Angriff ein!</b></center>
                        <br>Sie sind auf unseren Sensoren erschienen. Unsere Flotten sind auf Abfangkurs.<br><br>
                        Ein Weiterfliegen h&auml;tte einen Krieg zur Folge, den wir ohne R&uuml;cksicht
                        auf Verluste gegen Sie und ihre Verb&uuml;ndeten f&uuml;hren werden.
                        <br>Es sind 5 Kleine Angriffsgeschwarder unterwegs zu ihrem Planeten <TARGETPLANET>.
                        <br>--------------------------------------<br>
                        Commander der Alpha-Flotte des Handelsimperiums',
                        '<br><center><b>Fermate immediatamente il vostro attacco!</b></center>
                        <br>Siete apparso sui nostri sensori. Le nostre flotte sono in rotta di intercettazione.<br><br>
                        Continuare provocherebbe una guerra che condurremo senza prendere in considerazione le perdite vostre e dei vostri alleati.
                        <br>Ci sono 5 piccoli Caccia Leggeri d&#146;Attacco in rotta verso il vostro pianeta <TARGETPLANET>.
                        <br>--------------------------------------<br>
                        Comandante dell&#146;Alpha-Fleet dell&#146;Impero Commerciale');

        $titles = array('<b>You are on our sensors</b>',
                        '<b>Sie sind auf unseren Sensoren</b>',
                        '<b>Siete sui nostri sensori</b>');

        $this->CheckSensors($ACTUAL_TICK,$titles,$messages);
        // ########################################################################################
        // ########################################################################################
        // Ships creation
        $this->sdl->start_job('Creating ships', TICK_LOG_FILE_NPC);

        $this->sdl->log('Check fleet "Alpha-Fleet IVX" composition', TICK_LOG_FILE_NPC);
        $query='SELECT * FROM `ship_fleets` WHERE fleet_name="Alpha-Fleet IVX" and user_id='.$this->bot['user_id'].' LIMIT 0, 1';
        $this->db->query($query);
        if($this->db->num_rows()<=0)
        {
            $sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
                VALUES ("Alpha-Fleet IVX", '.$this->bot['user_id'].', '.$this->bot['planet_id'].', 0, 4000)';
            if(!$this->db->query($sql))
                $this->sdl->log('<b>Error:</b> Could not insert new fleets data', TICK_LOG_FILE_NPC);
            else {
                $fleet_id = $this->db->insert_id();
                $stpl1_found = true;
                $stpl2_found = true;

                if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);

                $sql_a= 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_t_1'];
                $sql_b= 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_t_2'];
                if(($stpl_a = $this->db->queryrow($sql_a)) === false)
                    $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_a, TICK_LOG_FILE_NPC);
                if(($stpl_b = $this->db->queryrow($sql_b)) === false)
                    $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_b, TICK_LOG_FILE_NPC);

                // Check if the templates exists
                if (empty($stpl_a)) $stpl1_found = false;
                if (empty($stpl_b)) $stpl2_found = false;
                
                $units_str_1 = $stpl_a['min_unit_1'].', '.$stpl_a['min_unit_2'].', '.$stpl_a['min_unit_3'].', '.$stpl_a['min_unit_4'];
                $units_str_2 = $stpl_b['min_unit_1'].', '.$stpl_b['min_unit_2'].', '.$stpl_b['min_unit_3'].', '.$stpl_b['min_unit_4'];
                $sql_c= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
                    VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_t_1'].', '.$stpl_a['value_9'].', '.$stpl_a['value_5'].', '.$game->TIME.', '.$units_str_1.')';
                $sql_d= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
                    VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_t_2'].', '.$stpl_b['value_9'].', '.$stpl_b['value_5'].', '.$game->TIME.', '.$units_str_2.')';
                for($i = 0; $i < 4000; ++$i)
                {
                    if($i<400){
                        // Skip if template not found
                        if (!$stpl1_found) continue;
                        
                        if(!$this->db->query($sql_c)) {
                            $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
                        }
                    }else{
                        // Skip if template not found
                        if (!$stpl2_found) continue;

                        if(!$this->db->query($sql_d)) {
                            $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
                        }
                    }
                }
                $this->sdl->log('Fleet: '.$fleet_id.' - 4000 ships created', TICK_LOG_FILE_NPC);
            }
        }
        // Check whether someone has destroyed some Quark's ships
        $this->RestoreFleetLosses("Alpha-Fleet IVX",$this->bot['ship_t_2'],4000);

        $this->sdl->log('Check fleet "Interception Omega" composition', TICK_LOG_FILE_NPC);
        $query='SELECT * FROM `ship_fleets` WHERE fleet_name="Interception Omega" and user_id='.$this->bot['user_id'].' LIMIT 0, 1';
        $this->db->query($query);
        if($this->db->num_rows()<=0)
        {
            $sql= 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
                VALUES ("Interception Omega", '.$this->bot['user_id'].', '.$this->bot['planet_id'].', 0, 1000)';
            if(!$this->db->query($sql))
                $this->sdl->log('<b>Error:</b> Could not insert new fleets data', TICK_LOG_FILE_NPC);
            else {
                $fleet_id= $this->db->insert_id();

                if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);

                $sql_b= 'SELECT * FROM ship_templates WHERE id = '.$this->bot['ship_t_2'];
                if(($stpl_b = $this->db->queryrow($sql_b)) === false)
                    $this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql_b, TICK_LOG_FILE_NPC);

                if (!empty($stpl_b)) {
                    $units_str_2 = $stpl_b['min_unit_1'].', '.$stpl_b['min_unit_2'].', '.$stpl_b['min_unit_3'].', '.$stpl_b['min_unit_4'];
                    $sql= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
                        VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_t_2'].', '.$stpl_b['value_9'].', '.$stpl_b['value_5'].', '.$game->TIME.', '.$units_str_2.')';

                    for($i = 0; $i < 1000; ++$i)
                    {
                        if(!$this->db->query($sql)) {
                            $this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
                        }
                    }
                    $this->sdl->log('Fleet: '.$fleet_id.' - 1000 ships created', TICK_LOG_FILE_NPC);
                }
                else
                    $this->sdl->log('<b>Error:</b> Could not found template '.$this->bot['ship_t_2'].'!', TICK_LOG_FILE_NPC);
            }
        }
        // Check whether someone has destroyed some Quark's ships
        $this->RestoreFleetLosses("Interception Omega",$this->bot['ship_t_2'],1000);

        $this->sdl->finish_job('Creating ships', TICK_LOG_FILE_NPC);

        // ########################################################################################
        // ########################################################################################

        $this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
    }

    function graph_draw($kind)
    {
        global $ACTUAL_TICK;

        // At the moment the query read ALL the troops traded since the game started...
        // I believe we can do better then this...
        $sql = 'SELECT '.$kind.', tick FROM FHB_handel_log WHERE art=1  ORDER BY `tick` ASC';
        if(($units_sold = $this->db->query($sql)) === false) {
            $this->sdl->log('<b>Error:</b> cannot read transactions data for units '.$kind.'! SKIP',TICK_LOG_FILE_NPC);
            return;
        }

        $num_rows = $this->db->num_rows();
        $worth=0;
        $count=0;
        $count_a=0;
        $end_tick=0;
        $start_tick=0;
        $hour = date("H");

        while($data = $this->db->fetchrow($units_sold))
        {
            // Place $end_tick four hours ahead of current read tick
            if($end_tick == 0) $end_tick = $data['tick'] + (240 / TICK_DURATION);
            if($start_tick == 0) $start_tick = $data['tick'];

            if($data['tick'] > $ACTUAL_TICK) break;

            if($end_tick <= $data['tick'] || $data['tick'] == $ACTUAL_TICK || $num_rows == $count)
            {
                // Calculate elapsed minutes between actual tick and last troops traded
                $minutes_start   = ($ACTUAL_TICK - $start_tick) * TICK_DURATION;
                $minutes_end     = ($ACTUAL_TICK - $end_tick) * TICK_DURATION;

                // Convert minutes into hours
                $start = floor($minutes_start / 60);
                $end   = floor($minutes_end / 60);

                // Convert hours into human readable time
                $earnings_start = $hour - ($start - (floor($start / 24) * 24));
                if($earnings_start < 0) $earnings_start = 24 + $earnings_start;
                $earnings_end = $hour - ($end - (floor($end / 24) * 24));
                if($earnings_end < 0) $earnings_end = 24 + ($earnings_end);

                // Setup graph line properties
                $arr[$count_a]['size']=$worth;
                $arr[$count_a]['name']=$earnings_start.'h-'.$earnings_end.'h';

                // Prepare to elaborate another frame of 4h
                $worth=0;
                $count_a++;
                $start_tick = $end_tick;
                $end_tick = $end_tick + (240 / TICK_DURATION);
            }
            $count++;
            $worth+=$data[$kind];
        }

        // Plot only the last seven four hours frames
        $count_a=$count_a-7;
        for($aa=0;$aa<7;$aa++)
        {
            $output[$aa]['size']=$arr[$count_a]['size'];
            $output[$aa]['name']=$arr[$count_a]['name'];
            $count_a++;
        }

        $simpleGraph2 = new simpleGraph();
        $simpleGraph2->create("430", "200");
        $simpleGraph2->headline("Sales figures ".$kind);
        $simpleGraph2->line($output);
        $simpleGraph2->showGraph(FILE_PATH_hg."kurs/".$kind.".png");
        unset($arr);
        unset($output);
    }

}


?>
