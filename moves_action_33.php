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



class moves_action_33 extends moves_common {
    function _action_main() {
        if($this->n_fleets > 1) {
            $this->deactivate(35);
            $this->report('Auctioned move '.$this->move['move_id'].' has '.$this->n_fleets.' fleets');

            return MV_EXEC_ERROR;
        }

        $sql = 'SELECT s.ship_id,
                       st.name, st.ship_torso, st.race
                FROM (ships s)
                INNER JOIN ship_templates st ON st.id = s.template_id
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(($q_aship = $this->db->query($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query ships data! SKIP');
        }

        $n_aships = $this->db->num_rows($q_aship);

        if($n_aships != 1) {
            $this->deactivate(35);
            $this->report('Auctioned move '.$this->move['move_id'].' has '.$n_aships.' ships');

            return MV_EXEC_ERROR;
        }

        $aship = $this->db->fetchrow($q_aship);

        $sql = 'UPDATE ships
                SET fleet_id = -'.$this->move['dest'].',
                    user_id = '.$this->dest['user_id'].',
                    ship_untouchable = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update ships user data! SKIP');
        }

        $sql = 'DELETE FROM ship_fleets
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not delete fleets data! SKIP');
        }

        $log_data = array(33, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'],
                          $aship['ship_id'], $aship['name'], $aship['ship_torso'], $aship['race']);

        // #############################################################################
        // 01/04/08 - AC: Retrieve player language
        switch($this->dest['language'])
        {
            case 'GER':
                $log_title = 'Ersteigertes Schiff von '.$this->move['user_name'].' angekommen';
            break;
            case 'ITA':
                $log_title = 'Ersteigertes nave di '.$this->move['user_name'].' arrivata';
            break;
            default:
                $log_title = 'Ersteigertes ship of '.$this->move['user_name'].' arrived';
            break;
        }

        add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $log_title, $log_data);

        return MV_EXEC_OK;
    }
}

?>
