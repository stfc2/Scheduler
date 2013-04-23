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

class moves_action_34 extends moves_common {

    var $tr_data = array();
    var $fleet = array();
    var $actions;
    var $unlwares = array();    // unloaded wares
    var $lwares = array();      // loaded wares
    var $planet_overloaded;     // space still available on planet?

    function do_unloading() {

        //$this->log(MV_M_NOTICE, 'Unloading goods...');

        $wares = array(201 => 'resource_1', 202 => 'resource_2', 203 => 'resource_3', 204 => 'resource_4', 211 => 'unit_1', 212 => 'unit_2', 213 => 'unit_3', 214 => 'unit_4', 215 => 'unit_5', 216 => 'unit_6');
        $i = 0;
        $this->planet_overloaded = false;

        foreach($wares as $code => $column) {

            if($this->actions[$code] == 0) {
                //$this->log(MV_M_NOTICE, 'Nulla da scaricare per quanto riguarda '.$column);
                $this->unlwares[$i] = 0;
                $i++;
                continue;
            }


            if($this->actions[$code] == -1) {
                if($this->fleet[$column] > 0)
                    $this->log(MV_M_NOTICE, 'Unloading <b>ALL</b> the quantity on board of '.$column.' (<b>'.$this->fleet[$column].'</b>)');
                $this->dest[$column] += $this->fleet[$column];
                $this->unlwares[$i] = $this->fleet[$column];
                $this->fleet[$column] = 0;
            }

            else {
                $value = ($this->fleet[$column] < $this->actions[$code]) ? $this->fleet[$column] : $this->actions[$code];
                if($value > 0)
                    $this->log(MV_M_NOTICE, 'Unloading <b>'.$value.'</b> of '.$column);
                $this->dest[$column] += $value;
                $this->fleet[$column] -= $value;
                $this->unlwares[$i] = $value;
            }
            $i++;
        }

    }



    function do_loading() {

        // from ship_traderoute.php... and probably from ship_fleets_loadingp/f

        //$this->log(MV_M_NOTICE, 'Loading goods...');

        $n_resources = $this->fleet['resource_1'] + $this->fleet['resource_2'] + $this->fleet['resource_3'];
        $n_units = $this->fleet['resource_4'] + $this->fleet['unit_1'] + $this->fleet['unit_2'] + $this->fleet['unit_3'] + $this->fleet['unit_4'] + $this->fleet['unit_5'] + $this->fleet['unit_6'];
        $resources = array(101 => 'resource_1', 102 => 'resource_2', 103 => 'resource_3');
        $units = array(104 => 'resource_4', 111 => 'unit_1', 112 => 'unit_2', 113 => 'unit_3', 114 => 'unit_4', 115 => 'unit_5', 116 => 'unit_6');
        $i = 0;


        foreach($resources as $code => $column) {

            $value = ($this->actions[$code] == -1) ? $this->dest[$column] : $this->actions[$code];

            if($value > $this->dest[$column]) {
                $value = $this->dest[$column];
            }

            if( ($n_resources + $value) > $this->fleet['max_resources'] ) {
                $value = $this->fleet['max_resources'] - $n_resources;
            }

            if($value > 0)
                $this->log(MV_M_NOTICE, 'Loading <b>'.$value.'</b> of '.$column);

            $this->fleet[$column] += $value;
            $this->report_load[$column] = $value;
            $this->dest[$column] -= $value;
            $this->lwares[$i] = $value;

            $n_resources += $value;
            $i++;
        }



        foreach($units as $code => $column) {

            $value = ($this->actions[$code] == -1) ? $this->dest[$column] : $this->actions[$code];

            if($value > $this->dest[$column]) {
                $value = $this->dest[$column];
            }

            if( ($n_units + $value) > $this->fleet['max_units'] ) {
                $value = $this->fleet['max_units'] - $n_units;
            }

            if($value > 0)
                $this->log(MV_M_NOTICE, 'Loading <b>'.$value.'</b> of '.$column);

            $this->fleet[$column] += $value;  
            $this->report_load[$column] = $value;
            $this->dest[$column] -= $value;
            $this->lwares[$i] = $value;

            $n_units += $value;
            $i++;
        }
    }


    function _action_main() {

        if($this->flags['combat_happened']) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id = '.$this->fleet_ids[0];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update ships position data');
            }

            return MV_EXEC_OK;
        }


        if($this->flags['free_dest_planet']) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id = '.$this->fleet_ids[0];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update ships position data');
            }

            return MV_EXEC_OK;
        }


        $this->tr_data = $this->action_data;

        $this->tr_data[5] *= 2;

        if($this->tr_data[5] < 0) $this->tr_data[5] = -1;

        // On which planet we are arrived?
        if($this->move['dest'] == $this->tr_data[2]) $this->actions = &$this->tr_data[4];
        else $this->actions = &$this->tr_data[3];

        $this->log(MV_M_NOTICE, 'Buondi! Sono quello della flotta <b>'.$this->fleet_ids[0].'</b> del Boss <b>'.$this->move['user_id'].'</b>, in arrivo sul pianeta <b>'.$this->dest['planet_id'].'</b> che appartiene a <b>'.$this->dest['user_id'].'</b> per un trasporto.');

        $sql = 'SELECT resource_1, resource_2, resource_3, resource_4, unit_1, unit_2, unit_3, unit_4, unit_5, unit_6
                FROM ship_fleets
                WHERE fleet_id = '.$this->fleet_ids[0];

        if(($this->fleet = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query fleet data');
        }

        $sql = 'SELECT COUNT(s.ship_id) AS n_transporter
                FROM (ships s, ship_templates st)
                WHERE s.fleet_id = '.$this->fleet_ids[0].' AND
                      st.id = s.template_id AND
                      st.ship_torso = '.SHIP_TYPE_TRANSPORTER;

        if(($n_transporter = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query n_transporter data');
        }



        $this->fleet['max_resources'] = $n_transporter['n_transporter'] * MAX_TRANSPORT_RESOURCES;
        $this->fleet['max_units'] = $n_transporter['n_transporter'] * MAX_TRANSPORT_UNITS;


        /* START OF RULE TO AVOID ROUTES BETWEEN SITTER AND SITTED */
        /* !! THIS SHOULD BE REMOVED AS SOON AS EVERY PROHIBITED ROUTE WILL BE REMOVED !! */
        $route_blocked = false;

        $sql = 'SELECT user_sitting_id1, user_sitting_id2, user_sitting_id3,
                       user_sitting_id4, user_sitting_id5
                FROM user
                WHERE user_id = '.$this->move['user_id'];

        if(($move_sitters = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query sitters data of the move owner');
        }

        $sql = 'SELECT user_sitting_id1, user_sitting_id2, user_sitting_id3,
                       user_sitting_id4, user_sitting_id5
                FROM user
                WHERE user_id = '.$this->dest['user_id'];

        if(($dest_sitters = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query sitters data of the dest owner');
        }

        if(($move_sitters['user_sitting_id1'] == $this->dest['user_id']) ||
           ($move_sitters['user_sitting_id2'] == $this->dest['user_id']) ||
           ($move_sitters['user_sitting_id3'] == $this->dest['user_id']) ||
           ($move_sitters['user_sitting_id4'] == $this->dest['user_id']) ||
           ($move_sitters['user_sitting_id5'] == $this->dest['user_id'])) {
            $route_blocked = true;
            $log_message = 'Trade route between sitter <b>'.$this->dest['user_id'].'</b> and sitted <b>'.$this->move['user_id'].'</b> are forbidden!!!';
        }

        if(($dest_sitters['user_sitting_id1'] == $this->move['user_id']) ||
           ($dest_sitters['user_sitting_id2'] == $this->move['user_id']) ||
           ($dest_sitters['user_sitting_id3'] == $this->move['user_id']) ||
           ($dest_sitters['user_sitting_id4'] == $this->move['user_id']) ||
           ($dest_sitters['user_sitting_id5'] == $this->move['user_id'])) {
            $route_blocked = true;
            $log_message = 'Trade route between sitter <b>'.$this->move['user_id'].'</b> and sitted <b>'.$this->dest['user_id'].'</b> are forbidden!!!';
        }

        if($route_blocked) {
            switch($this->move['language'])
            {
                case 'GER':
                    $message='Hallo '.$this->move['user_name'].',<br>Handelswege zwischen Sitter und sitted Konto sind verboten.<br>Diese Nachricht wurde automatisch generiert, Beschwerden beim STFC2-Team bringen nichts.<br>~ Sitting-Abuse-Automatik';
                    $title='Routesperre';
                break;
                case 'ITA':
                    $message='Ciao '.$this->move['user_name'].',<br>non le rotte commerciali tra account sitter e sittato non sono ammesse.<br>Questo messaggio &egrave; stato generato automaticamente, lamentele al team di STFC2 sono inutili.<br>~ Abuso Sitting Automatico';
                    $title='Rotta bloccata';
                break;
                default:
                    $message='Hello '.$this->move['user_name'].',<br>trade routes between sitter and sitted account are forbidden.<br>This message was automatically generated, complaints to the STFC2 team bring nothing.<br>~Automatic Abuse Sitting';
                    $title='Route blocked';
                break;
            }
            SystemMessage($this->move['user_id'],$title,$message);
            return $this->log(MV_M_NOTICE, $log_message);
        }

        /* END OF RULE TO AVOID ROUTES BETWEEN SITTER AND SITTED */

        $this->do_unloading();


        if($this->move['user_id'] != $this->dest['user_id']) {
            $sql = 'SELECT ud.ud_id, a.alliance_id, ad.ad_id, ad.type, ad.status 
                    FROM (user u)
                    LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = '.$this->dest['user_id'].' ) OR (ud.user1_id = '.$this->dest['user_id'].' AND ud.user2_id = '.$this->move['user_id'].' ) )
                    LEFT JOIN alliance a ON a.alliance_id = u.user_alliance
                    LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$this->move['user_alliance'].' AND ad.alliance2_id = a.alliance_id ) OR ( ad.alliance1_id = a.alliance_id AND ad.alliance2_id = '.$this->move['user_alliance'].' ) )
                    WHERE u.user_id = '.$this->dest['user_id'];

            if(($diplomacy = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query diplomacy data');
            }

            $allied = false;

            // Players has a private pact?
            if(!empty($diplomacy['ud_id'])) $allied = true;

            // Players are in the same alliance?
            if( ($diplomacy['alliance_id'] != 0) && ($diplomacy['alliance_id'] == $this->move['user_alliance']) ) $allied = true;

            // Player's alliances are sharing a Cooperation pact?
            if(!empty($diplomacy['ad_id'])) {
                if($diplomacy['type'] == 3) {
                    if($diplomacy['status'] == 0) $allied = true;
                }
            }
        }

        else {
            $allied = true;
        }

        if($allied && ($this->tr_data[5] < 0 || $this->tr_data[5] == 4)) $this->do_loading();

        $sql = 'UPDATE ship_fleets
                SET resource_1 = '.$this->fleet['resource_1'].',
                    resource_2 = '.$this->fleet['resource_2'].',
                    resource_3 = '.$this->fleet['resource_3'].',
                    resource_4 = '.$this->fleet['resource_4'].',
                    unit_1 = '.$this->fleet['unit_1'].',
                    unit_2 = '.$this->fleet['unit_2'].',
                    unit_3 = '.$this->fleet['unit_3'].',
                    unit_4 = '.$this->fleet['unit_4'].',
                    unit_5 = '.$this->fleet['unit_5'].',
                    unit_6 = '.$this->fleet['unit_6'].'
                WHERE fleet_id = '.$this->fleet_ids[0];

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update ship fleets resource data');
        }

        $sql = 'UPDATE planets
                SET resource_1 = '.$this->dest['resource_1'].',
                    resource_2 = '.$this->dest['resource_2'].',
                    resource_3 = '.$this->dest['resource_3'].',
                    resource_4 = '.$this->dest['resource_4'].',
                    unit_1 = '.$this->dest['unit_1'].',
                    unit_2 = '.$this->dest['unit_2'].',
                    unit_3 = '.$this->dest['unit_3'].',
                    unit_4 = '.$this->dest['unit_4'].',
                    unit_5 = '.$this->dest['unit_5'].',
                    unit_6 = '.$this->dest['unit_6'].'
                WHERE planet_id = '.$this->dest['planet_id'];

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update planets resource data');
        }

        // #############################################################################
        // Returns ships if requested

        if($this->tr_data[5] < 0 || $this->tr_data[5] == 4) {
            $new_move_begin = $this->CURRENT_TICK;
            $new_move_finish = ($this->move['move_finish'] - $this->move['move_begin']) + $this->CURRENT_TICK;


            $sql = 'UPDATE scheduler_shipmovement
                    SET start = '.$this->move['dest'].',
                        dest = '.$this->move['start'].',
                        move_begin = '.$new_move_begin.',
                        move_finish = '.$new_move_finish.',
                        remaining_distance = total_distance,
                        action_data = "'.serialize($this->tr_data).'"
                    WHERE move_id = '.$this->mid;

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update move data! SKIP');
            }

            $this->flags['keep_move_alive'] = true;
        }
        else {
            $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
            }
        }

        // #############################################################################
        // Logbook entry

        $sql = 'SELECT st.name, st.ship_torso, st.race,
                       COUNT(s.ship_id) AS n_ships
                FROM ship_templates st, ship_fleets f, ships s
                WHERE f.fleet_id IN ('.$this->fleet_ids_str.') AND
                      s.template_id = st.id AND
                      s.fleet_id = f.fleet_id
                GROUP BY st.id
                ORDER BY st.ship_torso ASC, st.race ASC';

        if(!$q_stpls = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not query ships templates data! SKIP');
        }

        $log_data = array(34, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], array(), $this->unlwares, $this->lwares, $this->planet_overloaded);

        while($stpl = $this->db->fetchrow($q_stpls)) {
            $log_data[8][] = array($stpl['name'], $stpl['ship_torso'], $stpl['race'], $stpl['n_ships']);
        }

        // #############################################################################
        // Retrieve player language
        switch($this->move['language'])
        {
            case 'GER':
                $log_title1 = 'Transport bei '.$this->dest['planet_name'].' durchgef&uuml;hrt';
                $log_title2 = 'Transport bei '.$this->dest['planet_name'].' erhalten';
            break;
            case 'ITA':
                $log_title1 = 'Trasporto per '.$this->dest['planet_name'].' compiuto';
                $log_title2 = 'Trasporto per '.$this->dest['planet_name'].' ricevuto';
            break;
            default:
                $log_title1 = 'Transport for '.$this->dest['planet_name'].' accomplished';
                $log_title2 = 'Transport for '.$this->dest['planet_name'].' received';
            break;
        }

        // Add logbook entries only if players had activated them
        if($this->action_data[6])
            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $log_title1, $log_data);

        // Only one logbook if the owner of the planet is also the owner of the fleets
        if($this->move['user_id'] != $this->dest['user_id'])
            add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $log_title2, $log_data);

        return MV_EXEC_OK;
    }

}



?>

