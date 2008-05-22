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

class moves_action_55 extends moves_common {
    function _action_main() {

// #############################################################################
// Daten der Angreifer

$sql = 'SELECT '.$this->get_combat_query_fleet_columns().', f.resource_4, f.unit_1, f.unit_2, f.unit_3, f.unit_4
		FROM (ship_fleets f)
		INNER JOIN user u ON u.user_id = f.user_id
		WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
	return $this->log(MV_M_DATABASE, 'Could not query attackers fleets data! SKIP');
}


// #############################################################################
// Daten der Verteidiger

$user_id = $this->dest['user_id'];
$planetary_attack = true;

// Das kï¿½nte man natrlich optimieren, doch es soll mï¿½lichst
// viel Kompatibilitï¿½ zur Vorlage action_51.php erhalten bleiben
$cur_user = array(
    'user_id' => $user_id,
    'user_name' => $this->dest['user_name'],
    'user_race' => $this->dest['user_race'],
    'user_planets' => $this->dest['user_planets'],
    'user_alliance' => $this->dest['user_alliance']
);

$sql = 'SELECT DISTINCT f.user_id,
               u.user_alliance,
               ud.ud_id, ud.accepted,
               ad.ad_id, ad.type, ad.status
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$user_id.' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$user_id.' ) )
        LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$cur_user['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$cur_user['user_alliance'].' ) )
        WHERE f.planet_id = '.$this->move['dest'].' AND
              f.user_id <> '.$user_id.' AND
              f.user_id <> '.$this->move['user_id'].' AND
              f.alert_phase >= '.ALERT_PHASE_YELLOW;

if(!$q_st_uid = $this->db->query($sql)) {
    return $this->log(MV_M_DATABASE, 'Could not query stationated fleets user data! SKIP');
}

$st_user = array();

while($st_uid = $this->db->fetchrow($q_st_uid)) {
    $allied = false;

    if($st_uid['user_alliance'] == $this->dest['user_alliance']) $allied = true;

    if(!empty($st_uid['ud_id'])) {
        if($st_uid['accepted'] == 1) $allied = true;
    }

    if(!empty($st_uid['ad_id'])) {
        if( ($st_uid['type'] == ALLIANCE_DIPLOMACY_PACT) && ($st_uid['status'] == 0) ) $allied = true;
    }

    if($allied) $st_user[] = $st_uid['user_id'];
}

$n_st_user = count($st_user);

if($n_st_user > 0) {
    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
             FROM (ship_fleets f)
             INNER JOIN user u ON u.user_id = f.user_id
             WHERE f.planet_id = '.$this->move['dest'].' AND
			       (
                     (
                       f.user_id = '.$user_id.'
                     )
                     OR
                     (
                       f.user_id IN ('.implode(',', $st_user).') AND
                       f.alert_phase >= '.ALERT_PHASE_YELLOW.'
                     )
                   )';
}
else {
    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
             FROM (ship_fleets f)
             INNER JOIN user u ON u.user_id = f.user_id
             WHERE f.planet_id = '.$this->move['dest'].' AND
                   f.user_id = '.$user_id;
}

if(($dfd_fleets = $this->db->queryrowset($sql)) === false) {
	return $this->log(MV_M_DATABASE, 'Could not query defenders fleets data! SKIP');
}

$dfd_fleet_ids = array();

foreach($dfd_fleets as $i => $cur_fleet) {
	$dfd_fleet_ids[] = $cur_fleet['fleet_id'];
}

if($this->do_ship_combat($this->fleet_ids_str, implode(',', $dfd_fleet_ids), MV_COMBAT_LEVEL_PLANETARY) == MV_EXEC_ERROR) {
	$this->log(MV_M_DATABASE, 'Move Action 55: Something went wrong with this fight!');
    return MV_EXEC_ERROR;
}


// #############################################################################
// Wenn der Angreifer gewonnen hat, ï¿½ernahmeversuch starten

$dfd_title = 'Angriff auf '.$this->dest['planet_name'];

$action_status = 0;

if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
    if(empty($this->action_data[0])) {
        return $this->log('Moves', 'action_55: Could not find required action_data entry [0]! SKIP');
    }

    $ship_id = (int)$this->action_data[0];

    // Wir machen es ganz sicher hier, um zu sehen, ob das
    // Schiff dabei ist

    $sql = 'SELECT s.ship_id, s.user_id, s.unit_1, s.unit_2, s.unit_3, s.unit_4,
                   st.ship_torso, st.race, st.min_unit_1, st.min_unit_2, st.min_unit_3, st.min_unit_4,
                   f.fleet_id, f.move_id, f.n_ships
            FROM (ships s)
            INNER JOIN ship_templates st ON st.id = s.template_id
            INNER JOIN ship_fleets f ON f.fleet_id = s.fleet_id
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

    if($cship_exists) {
        $atk_units = array( ($cship['unit_1'] - $cship['min_unit_1']) , ($cship['unit_2'] - $cship['min_unit_2']) , ($cship['unit_3'] - $cship['min_unit_3']) , ($cship['unit_4'] - $cship['min_unit_4']),0 );


        for($i = 0; $i < count($atk_fleets); ++$i) {
            $atk_units[0] += $atk_fleets[$i]['unit_1'];
            $atk_units[1] += $atk_fleets[$i]['unit_2'];
            $atk_units[2] += $atk_fleets[$i]['unit_3'];
            $atk_units[3] += $atk_fleets[$i]['unit_4'];
            $atk_units[4] += $atk_fleets[$i]['resource_4'];
        }

        $dfd_units = array($this->dest['unit_1'], $this->dest['unit_2'], $this->dest['unit_3'], $this->dest['unit_4'],$this->dest['resource_4']);

		if (array_sum($dfd_units)>0)
		{
		
        $ucmb = UnitFight($atk_units, $this->move['user_race'], $dfd_units, $this->dest['user_race'], $this->mid);
        $n_atk_alive = array_sum($ucmb[0]);
		}
		else
		$n_atk_alive=1;
		
        // Wenn keine Angreifer mehr da sind, hat der Verteidigende
        // immer gewonnen auï¿½r der verteidigende hatte keine Einheiten
        if($n_atk_alive == 0) {
            // Um die Truppen des Angreifers zu beseitigen, einfach alle Transporter resetten

            $sql = 'UPDATE ship_fleets
                    SET unit_1 = 0,
                        unit_2 = 0,
                        unit_3 = 0,
                        unit_4 = 0,
                        resource_4 = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update ship fleets unit transport data! SKIP');
            }

            // Truppen des Verteidigenden einfach updaten

            $sql = 'UPDATE planets
                    SET unit_1 = '.$ucmb[1][0].',
                        unit_2 = '.$ucmb[1][1].',
                        unit_3 = '.$ucmb[1][2].',
                        unit_4 = '.$ucmb[1][3].',
                        resource_4 = '.$ucmb[1][4].'
                    WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update dest planet units data! SKIP');
            }

            $action_status = -1;

            $atk_title = 'Angriff auf '.$this->dest['planet_name'].' teilweise erfolgreich';
        }
        else {
            account_log($this->move['user_id'], $this->dest['user_id'], 4);

            // Ein Herrscher-Switch ausfhren

            // Wir brauchen die Anzahl der Planeten, die der kolonisierende besitzt
            // (wir wollen planet_owner_enum sicher bestimmen)

            $sql = 'SELECT COUNT(planet_id) AS n_planets
                    FROM planets
                    WHERE planet_owner = '.$this->move['user_id'];

            if(($pcount = $this->db->queryrow($sql)) === false) {
                $this->log(MV_M_DATABASE, 'Could not query planets count data! CONTINUE AND USE INSTABLE VALUE');

                $n_planets = $this->move['user_planets'];
            }
            else {
                $n_planets = $pcount['n_planets'];
            }

            $atk_alive = $ucmb[0];

            /*
            // Fehler bei Übernahme, deshalb wieder deaktivert - Mojo1987
            // Wir bringen soviel Truppen, wie auf dem Kolonieschiff waren, auf dem Planeten unter
            $planet_units = array();

            for($i = 0; $i <= 3; ++$i) {
                $j = $i + 1;

                $alive = $atk_units[$i] - $atk_alive[$i];
                $planet_units[$i] = ($alive > $cship['unit_'.$j]) ? $cship['unit_'.$j] : $alive;

                $atk_alive[$i] -= $alive;
                $n_atk_alive -= $alive;
            }
            */

            // Nun die restlichen überlebenden Truppen auf dem Planeten verteilen,
            // sollten noch welche da sein

            /*
            if($n_atk_alive > 0) {
                // Wir holen uns ALLE Transporter der Flotten und verteilen dann

                $sql = 'SELECT s.ship_id, s.fleet_id
                        FROM (ships s, ship_templates st)
                        WHERE s.fleet_id IN ('.$fleet_ids_str.') AND
                              st.ship_torso = '.SHIP_TYPE_TRANSPORTER;

                if(!$q_ftrans = $db->query($sql)) {
                    mlog('MySQL', 'Could not query fleets transporter data! JUMP TO NEXT', $move_id);

                    return MOVE_EXEC_ERROR;
                }

                $n_space = 0;
                $space_per_fleets = array();

                while($_ship = $db->fetchrow($q_ftrans)) {
                    $n_space += MAX_TRANSPORT_UNITS;

                    if(!isset($space_per_fleets[$_ship['fleet_id']])) $space_per_fleets[$_ship['fleet_id']] = $worker_per_fleets[$_ship['fleet_id']] * (-1);

                    $space_per_fleets[$_ship['fleet_id']] += MAX_TRANSPORT_UNITS;
                }

                $n_space -= $n_worker;

                foreach($space_per_fleets as $fleet_id => $free_space) {
                    if($free_space == 0) continue;

                    $still_free = $free_space;
                    $new_load = array(0, 0, 0, 0);

                    for($i = 0; $i < 4; ++$i) {
                        $j = $i + 1;

                        if($atk_alive[$i] > 0) {
                            $new_load[$i] = ($atk_alive[$i] > $still_free) ? ($still_free - $atk_alive[$i]) : $atk_alive[$i];

                            $atk_alive[$i] -= $new_load[$i];
                            $still_free -= $new_load[$i];
                        }

                        if($still_free == 0) break;
                    }

                    $sql = 'UPDATE ship_fleets
                            SET unit_1 = '.$new_load[0].',
                                unit_2 = '.$new_load[1].',
                                unit_3 = '.$new_load[2].',
                                unit_4 = '.$new_load[3].'
                            WHERE fleet_id = '.$fleet_id;

                    if(!$db->query($sql)) {
                        mlog('MySQL', 'Could not update fleets units data! JUMP TO NEXT');

                        return MOVE_EXEC_ERROR;
                    }
                }

            }
            */

            $sql = 'DELETE FROM scheduler_instbuild
                    WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not delete scheduler instbuild data! CONTINUE');
            }

            $sql = 'DELETE FROM scheduler_research
                    WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log('MySQL', 'Could not delete scheduler research data! CONTINUE');
            }

            $sql = 'DELETE FROM scheduler_resourcetrade
                    WHERE planet = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log('MySQL', 'Could not delete resource data! CONTINUE');
            }

            $sql = 'DELETE FROM scheduler_shipbuild
                    WHERE planet_id = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log('MySQL', 'Could not delete shipbuild data! CONTINUE');
            }
            
            global $NUM_BUILDING, $MAX_BUILDING_LVL;

            // Da ja jemand anzahl == max_index setzt
            $n_buildings = $NUM_BUILDING + 1;

            $building_levels = array();

            $building_damage = 0.01 * mt_rand(40, 60);

            // Heimatwelt neu setzen
            if($this->move['dest'] == $this->dest['user_capital']) {
                for($i = 0; $i < $n_buildings; ++$i) {
                    $j = $i + 1;

                    $building_levels[$i] = (int) ($this->dest['building_'.$j] > $MAX_BUILDING_LVL[0][$i]) ? $MAX_BUILDING_LVL[0][$i] : round($this->dest['building_'.$j] * $building_damage, 0);
                }

                if($this->dest['user_planets'] == 1) {
                    SystemMessage($this->dest['user_id'], 'Verlust aller deiner Planeten', 'Da du alle deine Planeten verloren hast, wurde für dich ein neuer Planet an einer zufälligen Stelle der Galaxie erstellt.');
                }
                else {
                    if(!$this->db->query('SET @i=0')) {
                        return $this->log(MV_M_DATABASE, 'Could not set sql iterator variable for planet owner enum! SKIP');
                    }

                    $sql = 'UPDATE planets
                            SET planet_owner_enum = (@i := (@i + 1))-1
                            WHERE planet_owner = '.$this->dest['user_id'].'
                            ORDER BY planet_owned_date ASC, planet_id ASC';

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update planet owner enum data! SKIP');
                    }

                    $sql = 'SELECT planet_id
                            FROM planets
                            WHERE planet_owner = '.$this->dest['user_id'].'
                            ORDER BY planet_owner_enum ASC
                            LIMIT 1';

                    if(($first_planet = $this->db->queryrow($sql)) === false) {
                        return $this->log(MV_M_DATABASE, 'Could not query first planet data! SKIP');
                    }

                    $sql = 'UPDATE user
                            SET last_active = '.time().',
                                last_ip = "0.0.0.0",
                                user_capital = '.$first_planet['planet_id'].',
                                pending_capital_choice = 1
                            WHERE user_id = '.$this->dest['user_id'];

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update user capital data! SKIP');
                    }
                }
            }
            else {
                for($i = 0; $i < $n_buildings; ++$i) {
                    $building_levels[$i] = round($this->dest['building_'.($i + 1)] * $building_damage, 0);
                }
            }

            $sql = 'DELETE FROM resource_trade
                    WHERE planet = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not delete resource trade data! CONTINUE');
            }

            $sql = 'SELECT ship_id
                    FROM ship_trade
                    WHERE scheduler_processed = 0 AND
                          planet = '.$this->move['dest'];

            if(!$q_shipt = $this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not query ship trade data! CONTINUE');
            }
            else {
                $ship_ids = array();

                while($_ship = $this->db->fetchrow($q_shipt)) {
                    $ship_ids[] = $_ship['ship_id'];
                }

                if(count($ship_ids) > 0) {
                    $sql = 'UPDATE ships
                            SET ship_untouchable = 0
                            WHERE ship_id IN ('.implode(',', $ship_ids).')';

                    if(!$this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not update ships untouchable data! CONTINUE');
                    }
                }
            }

            $sql = 'DELETE FROM ship_trade
                    WHERE scheduler_processed = 0 AND
                          planet = '.$this->move['dest'];

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not delete ship trade data! CONTINUE');
            }

            $sql = 'UPDATE planets
                    SET planet_owner = '.$this->move['user_id'].',
                        planet_owned_date = '.time().',
                        planet_owner_enum = '.($n_planets - 1).',
                        resource_4 = 0,
                        recompute_static = 1,
                        building_1 = '.$building_levels[0].',
                        building_2 = '.$building_levels[1].',
                        building_3 = '.$building_levels[2].',
                        building_4 = '.$building_levels[3].',
                        building_5 = '.$building_levels[4].',
                        building_6 = '.$building_levels[5].',
                        building_7 = '.$building_levels[6].',
                        building_8 = '.$building_levels[7].',
                        building_9 = '.$building_levels[8].',
                        building_10 = '.$building_levels[9].',
                        building_11 = '.$building_levels[10].',
                        building_12 = '.$building_levels[11].',
                        building_13 = '.$building_levels[12].',
                        unit_1 = '.$atk_alive[0].',
                        unit_2 = '.$atk_alive[1].',
                        unit_3 = '.$atk_alive[2].',
                        unit_4 = '.$atk_alive[3].',
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
                        planet_insurrection_time=0,
		    	        building_queue=0,
                      planet_surrender=0
                    WHERE planet_id = '.$this->move['dest'];
            
            $this->log('SQL Debug', ''.$sql.'');

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
            }



        $this->log('Update Planetdata', 'Ressourcen limitiert');

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
        //
        // Konzept:
        //
        // Wenn ein Planet Ã¼berÃ¼llt ist, zieht er der Reihe nach von den Einheitenwerten
        // das ab, was benÃ¶tigt wird, damit der Planet nicht mehr Ã¼berfÃ¼llt ist.
        // Da die unit-Felder UNSIGNED sind (das ist SEHR wichtig), wird es max. 0
        // Wenn man bei der nÃ¤chsten ÃœberprÃ¼fung, wenn man die vorherige Einheit weglÃ¤sst,
        // der Planet noch immer Ã¼berfÃ¼llt ist, wird so weitergemacht.

        // Unit-1
        $sql = 'UPDATE planets
            SET unit_1 = unit_1 - ( ( (unit_1 * 2 + unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 2 )
                WHERE planet_owner = '.$this->move['user_id'].' AND
                  (unit_1 * 2 + unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

        if(!$this->db->query($sql)) {
        $this->log(MV_M_DATABASE,'- Warning: Could not update planet max units data by unit 1! CONTINUED');
        }

        // Unit-2
        $sql = 'UPDATE planets
            SET unit_2 = unit_2 - ( ( (unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 3 )
                WHERE planet_owner = '.$this->move['user_id'].' AND
                  (unit_2 * 3 + unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

        if(!$this->db->query($sql)) {
        $this->log(MV_M_DATABASE,'- Warning: Could not update planet max units data by unit 1! CONTINUED');
        }

        // Unit-3
        $sql = 'UPDATE planets
            SET unit_3 = unit_3 - ( ( (unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 4 )
                WHERE planet_owner = '.$this->move['user_id'].' AND
                  (unit_3 * 4 + unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

        if(!$this->db->query($sql)) {
        $this->log(MV_M_DATABASE,'- Warning: Could not update planet max units data by unit 1! CONTINUED');
        }

        // Unit-4
        $sql = 'UPDATE planets
            SET unit_4 = unit_4 - ( ( (unit_4 * 4 + unit_5 * 4 + unit_6 * 4) - max_units) / 4 )
                WHERE planet_owner = '.$this->move['user_id'].' AND
                  (unit_4 * 4 + unit_5 * 4 + unit_6 * 4) > max_units';

        if(!$this->db->query($sql)) {
        $this->log(MV_M_DATABASE,'- Warning: Could not update planet max units data by unit 1! CONTINUED');
        }

        // Unit-5
        $sql = 'UPDATE planets
            SET unit_5 = unit_5 - ( ( (unit_5 * 4 + unit_6 * 4) - max_units) / 4 )
                WHERE planet_owner = '.$this->move['user_id'].' AND
                  (unit_5 * 4 + unit_6 * 4) > max_units';

        if(!$this->db->query($sql)) {
        $this->log(MV_M_DATABASE,'- Warning: Could not update planet max units data by unit 1! CONTINUED');
        }

        // Unit-6
        $sql = 'UPDATE planets
                SET unit_6 = unit_6 - ( ( (unit_6 * 4) - max_units) / 4 )
            WHERE planet_owner = '.$this->move['user_id'].' AND
                  (unit_6 * 4) > max_units';

        if(!$this->db->query($sql)) {
        $this->log(MV_M_DATABASE,'- Warning: Could not update planet max units data by unit 1! CONTINUED');
        }

        $this->log('Update Planetdata', 'Einheiten begrenzt');



            if(!$this->db->query('SET @i=0')) {
                return $this->log(MV_M_DATABASE, 'Could not set sql iterator variable for planet owner enum (the invading player)! SKIP');
            }

            $sql = 'UPDATE planets
                    SET planet_owner_enum = (@i := (@i + 1))-1
                    WHERE planet_owner = '.$this->move['user_id'].'
                    ORDER BY planet_owned_date ASC, planet_id ASC';
                    
            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update planet owner enum data (the invading player)! SKIP');
            }

            $sql = 'UPDATE ships
                    SET user_id = '.$this->move['user_id'].'
                    WHERE fleet_id = -'.$this->move['dest'];

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update ships location data! SKIP');
            }

            if($this->dest['user_id'] == BORG_USERID) {
			    if((int)$this->move['user_alliance'] != 0) {
                    $sql = 'UPDATE alliance
                            SET borg_invade = borg_invade + 1
                            WHERE alliance_id = '.$this->move['user_alliance'];

                    if(!$this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not update user alliance borg honor data! CONTINUE');
                    }
                }

                send_premonition_to_user($this->move['user_id']);

                $msg_title = 'Verdienst im Kampf gegen die Borg';

                $msg_text = 'Für eure gerade erbrachten Verdienst gegen die neue Invasion des Borg-Kollektives befinden sich im Anflug auf euren Heimatplaneten ein speziell für diesen Kampf entwickeltes Schiff. Versucht nicht die enthaltene Technologie zu verstehen, sie wurde wirkungsvoll gegen euch abgeschirmt - nutzt sie stattdessen in eurem weiteren Kampf!';

                $sql = 'INSERT INTO message (sender, receiver, subject, text, time)
                        VALUES ('.FUTURE_HUMANS_UID.', '.$this->move['user_id'].', "'.$msg_title.'", "'.$msg_text.'", '.time().')';

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not send message');
                }
			}

            $action_status = 1;

            $atk_title = 'Angriff auf '.$this->dest['planet_name'].' erfolgreich';
        }

        $sql = 'DELETE FROM ships
                WHERE ship_id = '.$ship_id;

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
    }
    else {
        $action_status = -2;

        $atk_title = 'Angriff auf '.$this->dest['planet_name'].' teilweise erfolgreich';
    }

    $sql = 'UPDATE ship_fleets
            SET planet_id = '.$this->move['dest'].',
                move_id = 0,
                unit_1 = 0,
                unit_2 = 0,
                unit_3 = 0,
                unit_4 = 0,
                resource_4 = 0
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        // Hier kï¿½nte man auch reporten und dann weitermachen
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
    }
}
else {
    $atk_title = 'Angriff auf '.$this->dest['planet_name'].' fehlgeschlagen';
}


// #############################################################################
// Logbuch schreiben

$log1_data = array(55, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(55, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);

$log1_data[17] = $log2_data[17] = $action_status;

$log1_data[20] = $this->dest['user_race'];
$log2_data[20] = $this->move['user_race'];

for($i = 0; $i < 5; ++$i) {
    $log1_data[18][$i] = $log2_data[19][$i] = ($atk_units[$i] - $ucmb[0][$i]);
    $log1_data[19][$i] = $log2_data[18][$i] = ($dfd_units[$i] - $ucmb[1][$i]);
}

$log1_data[19][4] = $log2_data[18][4] = ((int)$this->dest['resource_4'] - $ucmb[1][4]);

$log2_data[14] = $this->cmb[MV_CMB_KILLS_PLANETARY];

$log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
$log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];


add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $atk_title, $log1_data);
add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $dfd_title, $log2_data);

if($n_st_user > 0) {
    $log2_data[2] = $this->move['start'];
    $log2_data[3] = $this->dest['planet_name'];
    $log2_data[4] = $user_id;
    $log2_data[5] = 0;
    $log2_data[6] = 0;
    $log2_data[7] = 0;
    $log2_data[16] = 1;

    for($i = 0; $i < $n_st_user; ++$i) {
        add_logbook_entry($st_user[$i], LOGBOOK_TACTICAL, 'Verbndeten bei '.$this->dest['planet_name'].' verteidigt', $log2_data);
    }
}

return MV_EXEC_OK;

    }
}

?>
