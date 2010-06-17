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




class moves_action_14 extends moves_common {

    function _action_main() {

        // #############################################################################

        // Summe der Rohstoffe ermitteln

        

        $sql = 'SELECT fleet_id, user_id,

                       resource_1, resource_2, resource_3, resource_4,

                       unit_1, unit_2, unit_3, unit_4, unit_5, unit_6

                FROM ship_fleets

                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

                

        if(($q_rfleets = $this->db->query($sql)) === false) {

            return $this->log(MV_M_DATABASE, 'Could not query fleets resources data! SKIP');

        }



        // #############################################################################

        // Rohstoffe übertragen



        $wares_names = array('resource_1', 'resource_2', 'resource_3', 'resource_4', 'unit_1', 'unit_2', 'unit_3', 'unit_4', 'unit_5', 'unit_6');



        // $pwares -> Waren auf dem Planeten

        // $fwares -> Waren auf der Flotte



        $pwares = $fwares = $transported = array();
		$transported[0]=0;
		$transported[1]=0;
		$transported[2]=0;
		$transported[3]=0;
		$transported[4]=0;
		$transported[5]=0;
		$transported[6]=0;
		$transported[7]=0;
		$transported[8]=0;
		$transported[9]=0;


        for($i = 0; $i < count($wares_names); ++$i) {

            (float)$pwares[$i] = (float)$this->dest[$wares_names[$i]];

        }


        $push = false;

        $planet_overloaded = false;

        $user_id = get_userid_by_planet($this->move['dest']);

        

        while($rfleet = $this->db->fetchrow($q_rfleets)) {

            $fleet_id = $rfleet['fleet_id'];



            $n_fwares = $rfleet['resource_1'] + $rfleet['resource_2'] + $rfleet['resource_3'] + $rfleet['resource_4'] + $rfleet['unit_1'] + $rfleet['unit_2'] + $rfleet['unit_3'] + $rfleet['unit_4'] + $rfleet['unit_5'] + $rfleet['unit_6'];



            if($n_fwares == 0) continue;

            $this->log('Transport lock', 'Fleet-UserID: '.$rfleet['user_id'].' - Planet-UserID: '.$user_id.'');

            if($rfleet['user_id']!=$user_id) {
              $push = true;
              continue;
            }

            foreach($pwares as $i => $p_value) {

                (float)$value = (float)$fwares[$i] = (float)$rfleet[$wares_names[$i]];



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
                
                $transported[$i]+=$value;

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



        // #############################################################################

        // Schiffe zurückgeschicken



        $sql = 'UPDATE scheduler_shipmovement

                SET start = '.$this->move['dest'].',

                    dest = '.$this->move['start'].',

                    total_distance = '.$this->move['total_distance'].',

                    remaining_distance = '.$this->move['total_distance'].',

                    move_begin = '.$this->CURRENT_TICK.',

                    move_finish = '.($this->CURRENT_TICK + ($this->move['move_finish'] - $this->move['move_begin'])).',

                    action_code = 12,

                    action_data = "14"

                WHERE move_id = '.$this->mid;

                

        if(!$this->db->query($sql)) {

            return $this->log(MV_M_DATABASE, 'Could not update moves data! SKIP');

        }



        $this->flags['keep_move_alive'] = true;



        return MV_EXEC_OK;

    }

}



?>

