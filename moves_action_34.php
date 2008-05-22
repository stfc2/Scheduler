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

    

    function do_unloading() {

        $wares = array(201 => 'resource_1', 202 => 'resource_2', 203 => 'resource_3', 204 => 'resource_4', 211 => 'unit_1', 212 => 'unit_2', 213 => 'unit_3', 214 => 'unit_4', 215 => 'unit_5', 216 => 'unit_6');

        

        foreach($wares as $code => $column) {

            if($this->actions[$code] == 0) continue;

            

            elseif($this->actions[$code] == -1) {

                $this->dest[$column] += $this->fleet[$column];

                $this->fleet[$column] = 0;

            }

            else {

                $value = ($this->fleet[$column] < $this->actions[$code]) ? $this->fleet[$column] : $this->actions[$code];

                

                $this->dest[$column] += $value;

                $this->fleet[$column] -= $value;

            }

        }

    }

    

    function do_loading() {

        // von ship_traderoute.php...und das wahrscheinlich von ship_fleets_loadingp/f

        

        $n_resources = $this->fleet['resource_1'] + $this->fleet['resource_2'] + $this->fleet['resource_3'];

        $n_units = $this->fleet['resource_4'] + $this->fleet['unit_1'] + $this->fleet['unit_2'] + $this->fleet['unit_3'] + $this->fleet['unit_4'] + $this->fleet['unit_5'] + $this->fleet['unit_6'];

        

        $resources = array(101 => 'resource_1', 102 => 'resource_2', 103 => 'resource_3');

        $units = array(104 => 'resource_4', 111 => 'unit_1', 112 => 'unit_2', 113 => 'unit_3', 114 => 'unit_4', 115 => 'unit_5', 116 => 'unit_6');



        foreach($resources as $code => $column) {

            $value = ($this->actions[$code] == -1) ? $$this->dest[$column] : $this->actions[$code];



            if($value > $this->dest[$column]) {

                $value = $this->dest[$column];

            }



            if( ($n_resources + $value) > $this->fleet['max_resources'] ) {

                $value = $max_resources - $n_resources;

            }



            $$this->fleet[$column] += $value;

            $this->dest[$column] -= $value;



            $n_resources += $value;

        }



        foreach($units as $code => $column) {

            $value = ($this->actions[$code] == -1) ? $this->dest[$column] : $this->actions[$code];



            if($value > $this->dest[$column]) {

                $value = $this->dest[$column];

            }



            if( ($n_units + $value) > $this->fleet['max_units'] ) {

                $value = $max_units - $n_units;

            }



            $this->fleet[$column] += $value;

            $this->dest[$column] -= $value;



            $n_units += $value;

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

        

        if($this->move['dest'] == $this->tr_data[2]) $this->actions = &$this->tr_data[4];

        else $this->actions = &$this->tr_data[3];

        

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

        

        $this->do_unloading();



        if($this->move['user_id'] != $this->dest['planet_owner']) {

            $sql = 'SELECT ud.ud_id,

                           a.alliance_id,

                           ad.ad_id, ad.type, ad.status

                    FROM (user u)

                    LEFT JOIN user_diplomacy ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = '.$this->dest['planet_owner'].' ) OR (ud.user1_id = '.$this->dest['planet_owner'].' AND ud.user2_id = '.$this->move['user_id'].' ) )

                    LEFT JOIN alliance a ON a.alliance_id = u.user_alliance

                    LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$this->move['user_alliance'].' AND ad.alliance2_id = a.alliance_id ) OR ( ad.alliance1_id = a.alliance_id AND ad.alliance2_id = '.$this->move['user_alliance'].' ) )

                    WHERE u.user_id = '.$this->dest['planet_owner'];

                    

            if(($diplomacy = $db->queryrow($sql)) === false) {

                return $this->log(MV_M_DATABASE, 'Could not query diplomacy data');

            }

            

            $allied = false;

            

            if(!empty($diplomacy['ud_id'])) $allied = true;

            

            if( ($diplomacy['alliance_id'] != 0) && ($diplomacy['alliance_id'] == $this->move['user_alliance']) ) $allied = true;

            

            if(!empty($diplomacy['ad_id'])) {

                if($diplomacy['type'] == 2) {

                    if($diplomacy['status'] == 0) $allied = true;

                }

            }

        }

        else {

            $allied = true;

        }

        

        if($allied) $this->do_loading();

        

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
                SET resource_1 = resource_1 - '.$this->fleet['resource_1'].',
                    resource_2 = resource_2 - '.$this->fleet['resource_2'].',
                    resource_3 = resource_3 - '.$this->fleet['resource_2'].',
                    resource_4 = resource_4 - '.$this->fleet['resource_2'].',
                    unit_1 = unit_1 - '.$this->fleet['unit_1'].',
                    unit_2 = unit_2 - '.$this->fleet['unit_2'].',
                    unit_3 = unit_3 - '.$this->fleet['unit_3'].',
                    unit_4 = unit_4 - '.$this->fleet['unit_4'].',
                    unit_5 = unit_5 - '.$this->fleet['unit_5'].',
                    unit_6 = unit_6 - '.$this->fleet['unit_6'].'
                WHERE planet_id = '.$this->dest['planet_id'];

                

        if(!$this->db->query($sql)) {

            return $this->log(MV_M_DATABASE, 'Could not update planets resource data');

        }

        

        $this->flags['keep_move_alive'] = true;

        

        return MV_EXEC_OK;

    }

}



?>

