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



class moves_action_24 extends moves_common {
    function _action_main() {
        // #############################################################################
        // Logbuch vorbereiten, um jederzeit abbrechen zu können

        $log_data = array(24, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0);


        // #############################################################################
        // Planeten überprüfen

        if(!empty($this->dest['user_id'])) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                    
            // Hier wär eine Stelle, an der ich benachrichtigt werden sollte
            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update fleets location data! CONTINUE');
            }

            $log_data[8] = -1;
            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, 'Kolonisation von '.$this->dest['planet_name'].' fehlgeschlagen', $log_data);

            return MV_EXEC_OK;
        }


        // #############################################################################
        // Kolonisationsschiff überprüfen

        if(empty($this->action_data[0])) {
            return $this->log(MV_M_ERROR, 'action_24: Could not find required action_data entry [0]! SKIP');
        }

        $ship_id = (int)$this->action_data[0];

        $sql = 'SELECT s.ship_id, s.user_id, s.unit_1, s.unit_2, s.unit_3, s.unit_4,
                       st.name, st.race, st.ship_torso, st.min_unit_1, st.min_unit_2, st.min_unit_3, st.min_unit_4,
                       f.fleet_id, f.move_id, f.n_ships
                FROM (ships s)
                INNER JOIN (ship_templates st) ON st.id = s.template_id
                INNER JOIN (ship_fleets f) ON f.fleet_id = s.fleet_id
                WHERE s.ship_id = '.$ship_id;
                
        if(($cship = $this->db->queryrow($sql)) === false) {
            return $this->log(MV_M_DATABASE, 'Could not query cship data! SKIP');
        }

        $cship_exists = true;

        if(empty($cship['ship_id'])) {
            $cship_exists = false;
        }
        elseif($cship['user_id'] != $this->move['user_id']) {
            $cship_exists = false;
        }
        elseif($cship['ship_torso'] != SHIP_TYPE_COLO) {
            $cship_exists = false;
        }
        elseif($cship['move_id'] != $this->mid) {
            $cship_exists = false;
        }

        if(!$cship_exists) {
            $sql = 'UPDATE ship_fleets
                    SET planet_id = '.$this->move['dest'].',
                        move_id = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                    
            // Hier wär eine Stelle, an der ich benachrichtigt werden sollte
            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update fleets location data! CONTINUE');
            }

            $log_data[8] = -2;
            add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, 'Kolonisation von '.$this->dest['planet_name'].' fehlgeschlagen', $log_data);

            return MV_EXEC_OK;
        }


        // #############################################################################
        // Wir brauchen die Anzahl der Planeten, die der kolonisierende besitzt
        // (wir wollen planet_owner_enum sicher bestimmen)

        $sql = 'SELECT COUNT(planet_id) AS n_planets
                FROM planets
                WHERE planet_owner = '.$this->move['user_id'];
                
        if(($pcount = $this->db->queryrow($sql)) === false) {
            $this->log(MV_M_DATABASE, 'Could not query planets count data! CONTINUE USING INSTABLE VALUE');

            $n_planets = $cur_move['user_planets'];
        }
        else {
            $n_planets = $pcount['n_planets'];
        }


        // #############################################################################
        // Kolonisation versuchen

        $sql = 'DELETE FROM scheduler_instbuild
                WHERE planet_id = '.$this->move['dest'];
                
        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete scheduler instbuild data! CONTINUE');
        }

        $sql = 'DELETE FROM scheduler_shipbuild
                WHERE planet_id = '.$this->move['dest'];

        if(!$this->db->query($sql)) {
            $this->log('MySQL', 'Could not delete shipbuild data! CONTINUE');
        }

        $sql = 'DELETE FROM scheduler_research
                WHERE planet_id = '.$this->move['dest'];
                
        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete scheduler research data! CONTINUE');
        }

        $sql = 'UPDATE planets
                SET planet_owner = '.$this->move['user_id'].',
                    planet_owned_date = '.time().',
                    planet_owner_enum = '.($n_planets - 1).',
                    research_1 = 0,
                    research_2 = 0,
                    research_3 = 0,
                    research_4 = 0,
                    research_5 = 0,
                    resource_1 = 50,
                    resource_2 = 50,
                    resource_3 = 0,
                    resource_4 = 10,
                    recompute_static = 1,
                    building_1 = 1,
                    building_2 = 0,
                    building_3 = 0,
                    building_4 = 0,
                    building_5 = 0,
                    building_6 = 0,
                    building_7 = 0,
                    building_8 = 0,
                    building_9 = 0,
                    building_10 = 0,
                    building_11 = 0,
                    building_12 = 0,
                    building_13 = 0,
                    unit_1 = '.($cship['unit_1'] - $cship['min_unit_1']).',
                    unit_2 = '.($cship['unit_2'] - $cship['min_unit_2']).',
                    unit_3 = '.($cship['unit_3'] - $cship['min_unit_3']).',
                    unit_4 = '.($cship['unit_4'] - $cship['min_unit_4']).',
                    unit_5 = 0,
                    unit_6 = 0,
                    workermine_1 = 100,
                    workermine_2 = 100,
                    workermine_3 = 100,
                    catresearch_1 = 0,
                    catresearch_2 = 0,
                    catresearch_3 = 0,
                    catresearch_4 = 0,
                    catresearch_5 = 0,
                    catresearch_6 = 0,
                    catresearch_7 = 0,
                    catresearch_8 = 0,
                    catresearch_9 = 0,
                    catresearch_10 = 0,
                    unittrainid_1 = 0,
                    unittrainid_2 = 0,
                    unittrainid_3 = 0,
                    unittrainid_4 = 0,
                    unittrainid_5 = 0,
                    unittrainid_6 = 0,
                    unittrainid_7 = 0,
                    unittrainid_8 = 0,
                    unittrainid_9 = 0,
                    unittrainid_10 = 0,
                    unittrainnumber_1 = 0,
                    unittrainnumber_2 = 0,
                    unittrainnumber_3 = 0,
                    unittrainnumber_4 = 0,
                    unittrainnumber_5 = 0,
                    unittrainnumber_6 = 0,
                    unittrainnumber_7 = 0,
                    unittrainnumber_8 = 0,
                    unittrainnumber_9 = 0,
                    unittrainnumber_10 = 0,
                    unittrainnumberleft_1 = 0,
                    unittrainnumberleft_2 = 0,
                    unittrainnumberleft_3 = 0,
                    unittrainnumberleft_4 = 0,
                    unittrainnumberleft_5 = 0,
                    unittrainnumberleft_6 = 0,
                    unittrainnumberleft_7 = 0,
                    unittrainnumberleft_8 = 0,
                    unittrainnumberleft_9 = 0,
                    unittrainnumberleft_10 = 0,
                    unittrain_actual = 0,
		    		unittrainid_nexttime=0,
		    		building_queue=0,
                  	planet_surrender=0
                WHERE planet_id = '.$this->move['dest'];
     
            $this->log('SQL Debug', ''.$sql.'');
       
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
        }

        //Konzept des Schiffe vernichten bei feindlicher Übernahme.
        //Version 0.2b von Mojo1987 - Berechnung angepasst

        $this->log('Schiffsübergabe Schutz', 'Beginne löschen von Schiffen');

        $sql = 'SELECT s.ship_id FROM (ships s) WHERE s.fleet_id = -'.$this->move['dest'].'';

        if(!$del_ship = $this->db->query($sql)) {
           $this->log('Fehler beim Schiffe holen', 'Could not query planets ships! CONTINUE - '.$sql.'');
        }

        while($ship_wahl = $this->db->fetchrow($del_ship)) {

          $zufall = mt_rand(6,18);

          if($zufall>=8) {
          
            $sql = 'DELETE FROM ships WHERE ship_id = '.$ship_wahl['ship_id'].'';

            if(!$this->db->query($sql)) {
                $this->log('Fehler beim Schiffe löschen', 'Could not query deleted ship! CONTINUE');
            }
            else { $this->log('Schiffe gelöscht', 'Ship_ID: '.$ship_wahl['ship_id'].' Zufallszahl: '.$zufall.' <b>ERFOLG!</b>'); }
          }
        
        }
        $this->log('Schiffsübergabe Schutz', 'Löschen beendet');


        $sql = 'DELETE FROM ships
                WHERE fleet_id = -'.$this->move['dest'].' OR
                      ship_id = '.$ship_id;
                      
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not delete ships data! SKIP');
        }

        if($cship['n_ships'] == 1) {
            $sql = 'DELETE FROM ship_fleets
                    WHERE fleet_id = '.$cship['fleet_id'];
        }
        else {
            $sql = 'UPDATE ship_fleets
                    SET n_ships = n_ships - 1
                    WHERE fleet_id = '.$cship['fleet_id'];
        }

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update/delete cships fleet data! CONTINUE');
        }


        // #############################################################################
        // Flotten positionieren

        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';
                
        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
        }


        // #############################################################################
        // Logbuch schreiben

        $log_data[8] = 1;
        $log_data[9] = $cship['name'];
        $log_data[10] = $cship['race'];

        add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, 'Kolonisation von '.$this->dest['planet_name'].' erfolgreich', $log_data);

        return MV_EXEC_OK;
    }
}

?>
