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

		$sql = 'SELECT s.ship_name, t.name FROM ship_fleets f
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

		$sql = 'SELECT * FROM planets WHERE planet_id = '.$this->move['dest'];

		if(($survey_data = $this->db->queryrow($sql)) == false) {
			return $this->log(MV_M_DATABASE, 'Could not read planet data! SKIP');
		}

		$base_rateo_1_over  = $PLANETS_DATA[$survey_data['planet_type']][0]+0.12;
		$base_rateo_1_under = $PLANETS_DATA[$survey_data['planet_type']][0]-0.12;
		$base_rateo_2_over  = $PLANETS_DATA[$survey_data['planet_type']][1]+0.1;
		$base_rateo_2_under = $PLANETS_DATA[$survey_data['planet_type']][1]-0.1;
		$base_rateo_3_over  = $PLANETS_DATA[$survey_data['planet_type']][2]+0.08;
		$base_rateo_3_under = $PLANETS_DATA[$survey_data['planet_type']][2]-0.08;

		$_survey1 = 1;
		if($survey_data['rateo_1'] > $base_rateo_1_over) {
			$_survey1 = 2;
		}
		if($survey_data['rateo_1'] < $base_rateo_1_under) {
			$_survey1 = 0;
		}

		$_survey2 = 1;
		if($survey_data['rateo_2'] > $base_rateo_2_over) {
			$_survey2 = 2;
		}
		if($survey_data['rateo_2'] < $base_rateo_2_under) {
			$_survey2 = 0;
		}

		$_survey3 = 1;
		if($survey_data['rateo_3'] > $base_rateo_3_over) {
			$_survey3 = 2;
		}
		if($survey_data['rateo_3'] < $base_rateo_3_under) {
			$_survey3 = 0;
		}

		// Salviamo i dati nella planet_history

		$sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code, ship_name, survey_1, survey_2, survey_3)
			VALUES ('.$this->move['dest'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', 100,"'.$name_of_ship.'", '.$_survey1.', '.$_survey2.', '.$_survey3.')';
		
		if(!$this->db->query($sql)) {
			return $this->log(MV_M_DATABASE, 'Could not update planet details! SKIP');
		}


		// Inseriamo la notifica nel logbook

		$log_data = array(26, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0);	

		switch($this->move['language'])
		{
			case 'GER':
				$log_title = 'Forschungsauftrag';
				$log_msg1 = 'Forschungs-Verh&auml;ltnis vom Schiff';
				$log_msg2 = 'Geologische Erforschung- und Verwandt&uuml;bersichten zum Kategorienplaneten';
			break;
			case 'ITA':
				$log_title = 'Missione Esplorativa';
				$log_msg1 = 'Rapporto esplorativo dalla nave';
				$log_msg2 = 'Esplorazione e rilevamenti geologici relativi al pianeta di classe';
			break;
			default:
				$log_title = 'Exploratory Mission';
				$log_msg1 = 'Exploratory relationship from the ship';
				$log_msg2 = 'Geologic exploration and relative surveys to the planet of class';
			break;
		}

		$log_data[8] = $log_msg1.' '.$name_of_ship.'.';
		$log_data[9] = $log_msg2.' '.strtoupper($survey_data['planet_type']).' <b>'.$this->dest['planet_name'].'</b>:';
		$log_data[10] = date('d.m.y H:i', time());
		$log_data[11] = $_survey1;
		$log_data[12] = $_survey2;
		$log_data[13] = $_survey3;

		add_logbook_entry($this->move['user_id'],  LOGBOOK_TACTICAL_2, $log_title, $log_data);


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

