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




class moves_action_26 extends moves_common {

    function _action_main() {
	global $PLANETS_DATA;

        // #############################################################################   
		// Survey Mission!!!
		//
		// Beam us down! Energy!
		//
		//#############################################################################
		
		if(empty($this->action_data)) {
			return $this->log(MV_M_DATABASE, 'Action 26: Mancano le informazioni in action_data');
		}
		
		
		$sql = 'SELECT * FROM planets WHERE planet_id = '.$this->move['dest'];
		
		if(($survey_data = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
        }
					
		// Salviamo i dati nella planet_history
		$sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, survey_1, survey_2, survey_3)'
				.'VALUES ('.$this->move['dest'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', '.$survey_data['rateo_1'].', '.$survey_data['rateo_2'].', '.$survey_data['rateo_3'].')';
		
		// Inseriamo la notifica nel logbook
		$base_rateo_1 = $PLANETS_DATA[$survey_data['planet_type']][0];
		$base_rateo_2 = $PLANETS_DATA[$survey_data['planet_type']][1];
		$base_rateo_3 = $PLANETS_DATA[$survey_data['planet_type']][2];
		
		if(($survey_data['rateo_1'] > ($base_rateo_1 + 0.5))) {
			$log['color_1'] = 'green';
		}
			elseif(($survey_data['rateo_1'] < ($base_rateo_1 - 0.5)) {
				$log['color_1'] = 'red';
			}
				else {
					$log['color_1'] = 'grey';
				}
		
		if(($survey_data['rateo_2'] > ($base_rateo_2 + 0.5))) {
			$log['color_2'] = 'green';
		}
			elseif(($survey_data['rateo_2'] < ($base_rateo_2 - 0.5))) {
				$log['color_2'] = 'red';
			}
				else {
					$log['color_2'] = 'grey';
				}
				
		if(($survey_data['rateo_3'] > ($base_rateo_3 + 0.5))) {
			$log['color_3'] = 'green';
		}
			elseif(($survey_data['rateo_3'] < ($base_rateo_3 - 0.5))) {
				$log['color_3'] = 'red';
			}
				else {
					$log['color_3'] = 'grey';
				}
		
		$log['log_title'] = 'Rapporto esplorativo dalla nave '.$this->action_data['ship_name'];
		$log['log_subtitle'] = 'Esplorazione e rilevamenti geologici relativi al pianeta <b>'.$this->action_data['planet_name'].'</b> ['.$this->action_data['planet_loc'].']';
		$log['log_date'] = date('d.m.y H:i:s', time());
		
		add_logbook_entry($this->move['user_id'], LOGBOOK_SURVEY, 'Rapporto esplorativo', $log);
		/*
		// Smistiamo le informazioni all'alleanza, se desiderato
		if($this->action_data['share'] == 1) {
			$sql = 'SELECT `user_id` FROM `user` WHERE `user_alliance` = '.$this->move['user_alliance'].' AND `user_id` <> '.$this->move['user_id'];
			if(($share_ally = $this->db->query($sql)) === false) {
				return $this->log(MV_M_DATABASE, 'Could not read alliance members data!');
			}
			while($share_ally = $this->db->fetchrow($sql)) {
				$sql_ally = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, survey_1, survey_2, survey_3)'
				.'VALUES ('.$this->move['dest'].', '.$share_ally['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', '.$survey_data['rateo_1'].', '.$survey_data['rateo_2'].', '.$survey_data['rateo_3'].')';
				if(!$this->db->query($sql_ally) {
					return $this->log(MV_M_DATABASE, 'Could not INSERT survey planet data!');
				}
				
			}
			
		}
		*/
				
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

