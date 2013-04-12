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



class moves_action_31 extends moves_common {
    function _action_main() {
        account_log($this->move['user_id'], $this->dest['user_id'], 0);

        // #############################################################################
        // Determine the total raw

        $sql = 'SELECT fleet_id,
                       resource_1, resource_2, resource_3, resource_4,
                       unit_1, unit_2, unit_3, unit_4, unit_5, unit_6
                FROM ship_fleets
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(($q_rfleets = $this->db->query($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query fleets resources data! SKIP');
        }

        // #############################################################################
        // Transferred resources

        $wares_names = array('resource_1', 'resource_2', 'resource_3', 'resource_4', 'unit_1', 'unit_2', 'unit_3', 'unit_4', 'unit_5', 'unit_6');

        // $pwares -> Wares on the planet
        // $fwares -> Wares on the fleet
        // $twares -> transferred goods (needed for debt calculation)

        $pwares = $fwares = $twares = array();

        // Push check Var initialized
        $push = false;

        for($i = 0; $i < count($wares_names); ++$i) {
            $pwares[$i] = (int)$this->dest[$wares_names[$i]];
            $twares[$i] = 0;
        }

        $planet_overloaded = false;

        while($rfleet = $this->db->fetchrow($q_rfleets)) {
            $fleet_id = $rfleet['fleet_id'];

            $n_fwares = $rfleet['resource_1'] + $rfleet['resource_2'] + $rfleet['resource_3'] + $rfleet['resource_4'] + $rfleet['unit_1'] + $rfleet['unit_2'] + $rfleet['unit_3'] + $rfleet['unit_4'] + $rfleet['unit_5'] + $rfleet['unit_6'];

            if($n_fwares == 0) continue;

            // Check try to push
            if($this->move['user_id']!=$this->dest['user_id']) { 
              $push = true; 
              continue; 
            }

            foreach($pwares as $i => $p_value) {
                $value = $fwares[$i] = (int)$rfleet[$wares_names[$i]];

                if($value == 0) continue;

                if($i == 3) {
                    if( ($pwares[$i] + $value) > $this->dest['max_worker'] ) {
                        $value = $this->dest['max_worker'] - $pwares[$i];
                        $planet_overloaded = true;
                    }
                }
                elseif($i <= 2) {
                    if( ($pwares[$i] + $value) > $this->dest['max_resources'] ) {
                        $value = $this->dest['max_resources'] - $pwares[$i];
                        $planet_overloaded = true;
                    }
                }
                else {
                    if( ($pwares[$i] + $value) > $this->dest['max_units'] ) {
                        $value = $this->dest['max_units'] - $pwares[$i];
                        $planet_overloaded = true;
                    }
                }

                $fwares[$i] -= $value;
                $pwares[$i] += $value;
                $twares[$i] += $value;
            }

            $sql = 'UPDATE ship_fleets
                    SET resource_1 = '.$fwares[0].',
                        resource_2 = '.$fwares[1].',
                        resource_3 = '.$fwares[2].',
                        resource_4 = '.$fwares[3].',
                        unit_1 = '.$fwares[4].',
                        unit_2 = '.$fwares[5].',
                        unit_3 = '.$fwares[6].',
                        unit_4 = '.$fwares[7].',
                        unit_5 = '.$fwares[8].',
                        unit_6 = '.$fwares[9].'
                    WHERE fleet_id = '.$fleet_id;
                    
            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update resource data of fleet #'.$fleet_id.'! SKIP');
            }
        }

        $sql = 'UPDATE planets
                SET resource_1 = '.$pwares[0].',
                    resource_2 = '.$pwares[1].',
                    resource_3 = '.$pwares[2].',
                    resource_4 = '.$pwares[3].',
                    unit_1 = '.$pwares[4].',
                    unit_2 = '.$pwares[5].',
                    unit_3 = '.$pwares[6].',
                    unit_4 = '.$pwares[7].',
                    unit_5 = '.$pwares[8].',
                    unit_6 = '.$pwares[9].'
                WHERE planet_id = '.$this->move['dest'];
                
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update planets resource data! SKIP');
        }

        // Reworking of $ twares to the expected size of the debt calculation
        // (one might even rewrite, but why bother when it works...)

        $rfleets = array('n1' => $twares[0], 'n2' => $twares[1], 'n3' => $twares[2]);


        // #############################################################################
        // Check debts

        $sql = 'SELECT id, resource_1, resource_2, resource_3, ship_id
                FROM bidding_owed
                WHERE user = '.$this->move['user_id'].' AND
                      receiver = '.$this->dest['user_id'].'
                ORDER BY id ASC';
                
        if(!$q_debts = $this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not query bidding owed data! SKIP');
        }

        $n_debts = $this->db->num_rows($q_debts);

        if($n_debts > 0) {
            while($debt = $this->db->fetchrow($q_debts)) {
                if($debt['resource_1'] > 0) {
                    if($debt['resource_1'] >= $rfleets['n1']) {
                        $debt['resource_1'] -= $rfleets['n1'];
                        $rfleets['n1'] = 0;
                    }
                    else {
                        $rfleets['n1'] -= $debt['resource_1'];
                        $debt['resource_1'] = 0;
                    }
                }
                if($debt['resource_2'] > 0) {
                    if($debt['resource_2'] >= $rfleets['n2']) {
                        $debt['resource_2'] -= $rfleets['n2'];
                        $rfleets['n2'] = 0;
                    }
                    else {
                        $rfleets['n2'] -= $debt['resource_2'];
                        $debt['resource_2'] = 0;
                    }
                }

                if($debt['resource_3'] > 0) {
                    if($debt['resource_3'] >= $rfleets['n3']) {
                        $debt['resource_3'] -= $rfleets['n3'];
                        $rfleets['n3'] = 0;
                    }
                    else {
                        $rfleets['n3'] -= $debt['resource_3'];
                        $debt['resource_3'] = 0;
                    }
                }

                if( ($debt['resource_1'] <= 0) && ($debt['resource_2'] <= 0) && ($debt['resource_3'] <= 0) && ($debt['ship_id'] <= 0) ) {
                    $sql = 'DELETE FROM bidding_owed
                            WHERE id = '.$debt['id'];
                            
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not delete bidding owed data! SKIP');
                    }
                }
                else {
                    $sql = 'UPDATE bidding_owed
                            SET resource_1 = '.$debt['resource_1'].',
                                resource_2 = '.$debt['resource_2'].',
                                resource_3 = '.$debt['resource_3'].'
                            WHERE id = '.$debt['id'];
                            
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update bidding owed data! SKIP');
                    }
                }
            }
        }

        // #############################################################################
        // Ships return

        $sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
                VALUES ('.$this->move['user_id'].', 0, 0, '.$this->move['dest'].', '.$this->move['start'].', '.$this->move['total_distance'].', '.$this->move['total_distance'].', '.$this->move['tick_speed'].', '.$this->CURRENT_TICK.', '.($this->CURRENT_TICK + ($this->move['move_finish'] - $this->move['move_begin'])).', '.$this->move['n_ships'].', 12, "'.serialize(31).'")';

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

        // #############################################################################
        // Create a logbook entry

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

        $log_data = array(31, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], array(), $twares, $planet_overloaded, $push);

        while($stpl = $this->db->fetchrow($q_stpls)) {
            $log_data[8][] = array($stpl['name'], $stpl['ship_torso'], $stpl['race'], $stpl['n_ships']);
        }

        // #############################################################################
        // 31/03/08 - AC: Retrieve player language
        switch($this->move['language'])
        {
            case 'GER':
                $log_title1 = 'Transport bei '.$this->dest['planet_name'].' nicht durchgeführt';
                $log_title3 = 'Transport bei '.$this->dest['planet_name'].' durchgeführt';
            break;
            case 'ITA':
                $log_title1 = 'Trasporto per '.$this->dest['planet_name'].' non compiuto';
                $log_title3 = 'Trasporto per '.$this->dest['planet_name'].' compiuto';
            break;
            default:
                $log_title1 = 'Transport for '.$this->dest['planet_name'].' not accomplished';
                $log_title3 = 'Transport for '.$this->dest['planet_name'].' accomplished';
            break;
        }

        switch($this->dest['language'])
        {
            case 'GER':
                $log_title2 = 'Transport bei '.$this->dest['planet_name'].' konnte nicht abgeladen werden';
                $log_title4 = 'Transport bei '.$this->dest['planet_name'].' erhalten';
            break;
            case 'ITA':
                $log_title2 = 'Trasporto per '.$this->dest['planet_name'].' non scaricato';
                $log_title4 = 'Trasporto per '.$this->dest['planet_name'].' ricevuto';
            break;
            default:
                $log_title2 = 'Transport for '.$this->dest['planet_name'].' could not be unloaded';
                $log_title4 = 'Transport for '.$this->dest['planet_name'].' received';
            break;
        }

        if($push) {

          add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $log_title1, $log_data);
          add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $log_title2, $log_data);

        }
        else {

          add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $log_title3, $log_data);
          add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $log_title4, $log_data);

        }

        return MV_EXEC_OK;
    }
}

?>
