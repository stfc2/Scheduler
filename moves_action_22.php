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



class moves_action_22 extends moves_common {

    function _action_main() {

        // #############################################################################
        // Data of the scouts or the target planet
        // (resembles get_fleet_details())

        $sql = 'SELECT COUNT(s.ship_id) AS n_ships,
                       MAX(st.ship_torso) AS highest_torso,
                       SUM(st.value_11) AS sum_sensors,
                       SUM(st.value_12) AS sum_cloak,
                AVG(st.value_9) AS scoutxp
                FROM (ship_fleets f)
                INNER JOIN ships s ON s.fleet_id = f.fleet_id
                INNER JOIN ship_templates st ON st.id = s.template_id
                WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

        if(($spy_fleet = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query main spy fleets data! SKIP');
        }

        if($spy_fleet['highest_torso'] != SHIP_TYPE_SCOUT) {
            $this->deactivate(35);
            $this->report('Spy fleet has no-scout torso withflying');

            return MV_EXEC_ERROR;
        }

        $dest_planet = get_friendly_orbit_fleets($this->move['dest'], $this->dest['user_id'], $this->dest['user_alliance']);


        // #############################################################################
        // Spy()

        $spy_result = Spy($spy_fleet['sum_sensors'], $spy_fleet['sum_cloak'], $spy_fleet['n_ships'], $dest_planet['sum_sensors'], $this->dest['building_7'], $spy_fleet['scoutxp']);

        $log2_data = array();


        if($spy_result[0]) {

            // unsuccessful

            $sql = 'DELETE FROM ship_fleets
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not delete fleets data! SKIP');
            }

            $sql = 'DELETE FROM ships
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not delete ships data! CONTINUE');

                $this->report('Could not delete ships data due spy mission');
            }

            $log1_data = $log2_data = array(22, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], $spy_fleet['n_ships']);

        }
        else {

            // Calculate gained experience

            $difficult_rate = $dest_planet['sum_sensors'] + ($this->dest['building_7'] +1)*200;
            $exp = ((float)$difficult_rate/266)+5.0;

            $sql = 'UPDATE ships SET experience = experience+'.$exp.'
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update fleet EXP! CONTINUE');
            }

            // Scouts returns

            $sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status,
                        move_exec_started, start, dest, total_distance,
                        remaining_distance, tick_speed, move_begin, move_finish, n_ships,
                        action_code, action_data)
                    VALUES ('.$this->move['user_id'].', 0, 0,
                        '.$this->move['dest'].', '.$this->move['start'].',
                        '.$this->move['total_distance'].', '.$this->move['total_distance'].',
                        '.$this->move['tick_speed'].', '.$this->CURRENT_TICK.',
                        '.($this->CURRENT_TICK + ($this->move['move_finish'] - $this->move['move_begin'])).',
                        '.$this->move['n_ships'].', 12, "'.serialize(22).'")';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not create new movement for return! SKIP');
            }

            $new_move_id = $this->db->insert_id();

            if(!$new_move_id) {
                return $this->log(MV_M_ERROR, 'Could not get new move id! SKIP');
            }


            $sql = 'UPDATE ship_fleets
                    SET move_id = '.$new_move_id.'
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets movement data! SKIP');
            }

            $log1_data = array(22, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], $spy_fleet['n_ships'], $spy_result, $this->dest['user_race']);
        }



        $log1_data[9] = array($spy_result[0], array(), array(), array(), array(), array());



        for($i = 0; $i < count($spy_result[1]); ++$i) $log1_data[9][1][$spy_result[1][$i]] = $this->dest['resource_'.($spy_result[1][$i] + 1)];

        for($i = 0; $i < count($spy_result[2]); ++$i) if ($spy_result[2][$i]==1) $log1_data[9][2][$i] = $this->dest['unit_'.($i + 1)];

        for($i = 0; $i < count($spy_result[3]); ++$i) $log1_data[9][3][$spy_result[3][$i]] = $this->dest['building_'.($spy_result[3][$i] + 1)];

        for($i = 0; $i < count($spy_result[4]); ++$i) $log1_data[9][4][$spy_result[4][$i]] = $this->dest['research_'.($spy_result[4][$i] + 1)];

        for($i = 0; $i < count($spy_result[5]); ++$i) $log1_data[9][5][$spy_result[5][$i]] = $this->dest['catresearch_'.($spy_result[5][$i] + 1)];



        $log1_data[10] = $this->dest['user_race'];


        // #############################################################################
        // 31/03/08 - AC: Retrieve player language
        switch($this->move['language'])
        {
            case 'GER':
                $log_title = 'Spionagebericht von ';
                $log_success = ' ausspioniert';
            break;
            case 'ITA':
                $log_title = 'Report spionaggio di ';
                $log_success = ' spiato';
            break;
            default:
                $log_title = 'Spy report of ';
                $log_success = ' spied';
            break;
        }

        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $log_title.$this->dest['planet_name'], $log1_data);


        if(!empty($log2_data)) add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $this->dest['planet_name'].$log_success, $log2_data);


        return MV_EXEC_OK;

    }

}



?>

