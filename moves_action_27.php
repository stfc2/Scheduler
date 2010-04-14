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




class moves_action_27 extends moves_common {

	function _action_main() {

		global $PLANETS_DATA;

		// #############################################################################
		// Away Team Missions!!!
		//
		// Beam us down! Energy!
		//
		//#############################################################################

		$sql = 'SELECT s.ship_name, s.experience, s.ship_id, t.name FROM ship_fleets f
		               LEFT JOIN ships s ON s.fleet_id = f.fleet_id
		               LEFT JOIN ship_templates t ON t.id = s.template_id
		        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

		if(($ship_details = $this->db->queryrow($sql)) == false) {
			$this->log(MV_M_DATABASE, 'Could not read ship name! CONTINUE');
			$name_of_ship = '<i>Sconosciuto</i>';
		}
		else {
			if(!empty($ship_details['ship_name'])) {
				$name_of_ship = '<b>'.$ship_details['ship_name'].'</b>';
			}
			else {
				$name_of_ship = '<b><i>&#171;'.$ship_details['name'].'&#187;</i></b>';
			}
		}
		// Check Action Code
		if(empty($this->action_data[0])) {
            $this->log(MV_M_DATABASE, 'action_27: Could not find required action_data entry [0]! FORCED TO ZERO');
            $this->action_data[0] = 0;
        }
        
        switch($this->action_data[0]){
	        case 0:
	        	// Calcolo Exp per missione
				if($ship_details['experience'] < 65) {

					$actual_exp = $ship_details['experience'];

					$exp = (2.5/((float)$actual_exp*0.0635))+0.6;
						$sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];

//					$this->log(MV_M_NOTICE, 'SQL per update EXP: '.$sql);
		
					if(!$this->db->query($sql)) {
						$this->log(MV_M_DATABASE, 'Could not update ship exp! CONTINUE');
					}

				}
	          	// Primo Contatto
	          	switch($this->move['language'])
        		{
            	case 'GER':
                	$f_c_title = 'First contact on ';
                	$f_c_fail = ' failed';
                	$f_c_success = ' done';
            	break;
            	case 'ITA':
                	$f_c_title = 'Primo contatto su ';
                	$f_c_fail = ' fallito';
                	$f_c_success = ' avvenuto';
            	break;
            	default:
                	$f_c_title = 'First contact on ';
                	$f_c_fail = ' failed';
                	$f_c_success = ' done';
            	break;
        		}
	        	
				// Controllo del mood del pianeta
				$log_data = array(27, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0);
				
				$sql = 'SELECT mood_race'.$this->move['user_race'].' AS mood_race FROM planet_details WHERE planet_id = '.$this->move['dest'].' AND log_code = 300';
				if(($mood_data = $this->db->queryrow($sql)) == false) {
					return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
				}
				
				if($mood_data['mood_race'] > 105) {
					// Mossa non valida. Il mood supera già i 105 punti.
					$log_data[8] = -1;
	        		add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
				}
				else
				{
					// Mossa valida. Calcoliamo il fattore random di riuscita della missione.
					$outcome = mt_rand(0, 100);					
				
					if($mood_data['mood_race'] >= $outcome)  {
						// Missione riuscita
						// Aggiorniamo il log_code 300 del pianeta
						$sql = 'UPDATE planet_details SET mood_race'.$this->move['user_race'].' = mood_race'.$this->move['user_race'].' + '.(mt_rand(1, 3)).' WHERE planet_id = '.$this->move['dest'].' AND log_code = 300';
						if(!$this->db->query($sql)) {
							return $this->log(MV_M_DATABASE, 'Could not update planet mood! SKIP!!!');
						}
						// Calcolo Exp per missione
						if($ship_details['experience'] < 75) {
							$actual_exp = $ship_details['experience'];
							$exp = (2.5/((float)$actual_exp*0.0635))+0.6;
							$sql = 'UPDATE ships SET experience = experience+'.$exp.' WHERE ship_id = '.$ship_details['ship_id'];
							if(!$this->db->query($sql)) {
								return $this->log(MV_M_DATABASE, 'Could not update ship exp! SKIP');
							}
						}
						$log_data[8] = 1;
	        			add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_success, $log_data);
					}
					else
					{
						// Missione fallita!
						$log_data[8] = -2;
	        			add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $f_c_title.$this->dest['planet_name'].$f_c_fail, $log_data);
					}
				}
	        	break;
        }

		$sql = 'UPDATE ship_fleets
		        SET planet_id = '.$this->move['dest'].',
		            move_id = 0
		        WHERE fleet_id IN ('.$this->fleet_ids_str.')';

		if(!$this->db->query($sql)) {
			return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
		}
		return MV_EXEC_OK;
	}

}



?>

