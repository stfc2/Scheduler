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



class moves_action_23 extends moves_common {
    function _action_main() {
        account_log($this->move['user_id'], $this->dest['user_id'], 1);

        // #############################################################################
        // Schiffe aktualisieren

        $sql = 'UPDATE ships
                SET user_id = '.$this->dest['user_id'].'
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update ships user data! SKIP');
        }

        // #############################################################################
        // Flotten aktualisieren

        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0,
                    user_id = '.$this->dest['user_id'].'
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
        }

        // #############################################################################
        // Schulden überprüfen
        
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
            $sql = 'SELECT SUM(resource_1) AS n1, SUM(resource_2) AS n2, SUM(resource_3) AS n3
                    FROM ship_fleets
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(($rfleets = $this->db->queryrow($sql)) === false) {
                return $this->log(MV_M_DATABASE, 'Could not query fleets resources data! SKIP');
            }

            $ships = array();

            $sql = 'SELECT ship_id
                    FROM ships
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                    
            if(!$q_ships = $this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not query ships id data! SKIP');
            }

            while($_ship = $this->db->fetchrow($q_ships)) {
                $ships[$_ship['ship_id']] = true;
            }

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

                if($debt['ship_id'] > 0) {
                    if(!empty($ships[$debt['ship_id']])) $debt['ship_id'] = 0;
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
                                resource_3 = '.$debt['resource_3'].',
                                ship_id = '.$debt['ship_id'].'
                            WHERE id = '.$debt['id'];
                            
                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update bidding owed data! SKIP');
                    }
                }
            }
        }

        // #############################################################################
        // Logbuch-Eintrag erstellen

        $sql = 'SELECT st.name, st.ship_torso, st.race,
                       COUNT(s.ship_id) AS n_ships
                FROM ship_templates st, ship_fleets f, ships s
                WHERE f.fleet_id IN ('.$this->fleet_ids_str.') AND
                      s.template_id = st.id AND
                      s.fleet_id = f.fleet_id
                GROUP BY st.id
                ORDER BY st.ship_torso ASC, st.race ASC';
                
        if(!$q_stpls = $this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not query ships templates data! ABORTED LOGBOOK');
        }

        $log_data = array(23, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], array());

        while($stpl = $this->db->fetchrow($q_stpls)) {
            $log_data[8][] = array($stpl['name'], $stpl['ship_torso'], $stpl['race'], $stpl['n_ships']);
        }

        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, 'Flottenverband '.$this->dest['user_name'].' übergeben', $log_data);
        add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, 'Flottenverband von '.$this->move['user_name'].' hat sich ergeben', $log_data);

        return MV_EXEC_OK;
    }
}

?>
