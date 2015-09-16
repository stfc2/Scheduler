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

class moves_action_46 extends moves_common {
    function _action_main() {

// #############################################################################
// Data from the attacker

/*        
$sql = 'SELECT '.$this->get_combat_query_fleet_columns().', f.resource_4, f.unit_1, f.unit_2, f.unit_3, f.unit_4
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        INNER JOIN ships ON ships
        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';
 */

$sql = 'SELECT '.$this->get_combat_query_fleet_columns().',
		SUM(s.unit_1 - st.min_unit_1) AS unit_1,
                SUM(s.unit_2 - st.min_unit_2) AS unit_2,
                SUM(s.unit_3 - st.min_unit_3) AS unit_3,
                SUM(s.unit_4 - st.min_unit_4) AS unit_4
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        INNER JOIN ships s USING (fleet_id)
        INNER JOIN ship_templates st ON s.template_id = st.id
        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
    return $this->log(MV_M_DATABASE, 'Could not query attackers fleets data! SKIP');
}


// #############################################################################
// Data from the defender

$sql = 'SELECT DISTINCT f.user_id,
               u.user_alliance,
               ud.ud_id, ud.accepted,
               ad.ad_id, ad.type, ad.status
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->dest['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->dest['user_id'].' ) )
        LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id = '.$this->dest['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$this->dest['user_alliance'].' ) )
        WHERE f.planet_id = '.$this->move['dest'].' AND
              f.user_id <> '.$this->dest['user_id'].' AND
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
                       f.user_id = '.$this->dest['user_id'].'
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
                   f.user_id = '.$this->dest['user_id'];
}

if(($dfd_fleets = $this->db->queryrowset($sql)) === false) {
    return $this->log(MV_M_DATABASE, 'Could not query defenders fleets data! SKIP');
}

$dfd_fleet_ids = array();

foreach($dfd_fleets as $i => $cur_fleet) {
    $dfd_fleet_ids[] = $cur_fleet['fleet_id'];
}

$dfd_fleet_ids_str = implode(',', $dfd_fleet_ids);
if($this->do_ship_combat($this->fleet_ids_str, $dfd_fleet_ids_str, MV_COMBAT_LEVEL_PLANETARY) == MV_EXEC_ERROR) {
    $this->log(MV_M_DATABASE, 'Move Action 46: Something went wrong with this fight!');
    return MV_EXEC_ERROR;
}


// #############################################################################
// If the attacker has won attempt to start takeover

// #############################################################################
// 03/04/08 - AC: Retrieve player language
switch($this->dest['language'])
{
    case 'GER':
        $dfd_title = 'Angriff der Borg auf '.$this->dest['planet_name'];
    break;
    case 'ITA':
        $dfd_title = 'Attacco Borg su '.$this->dest['planet_name'];
    break;
    default:
        $dfd_title = 'Borg attack on '.$this->dest['planet_name'];
    break;
}

$action_status = 0;

/* 11/07/08 - AC: Initialize here arrays atk/dfd_losses */
$atk_losses = array(0, 0, 0, 0, 0);
$dfd_losses = array(0, 0, 0, 0, 0);

if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {

    $atk_units = array(0, 0, 0, 0, 0);

    // 20/06/08 - AC: Borg as no "workers"
    for($i = 0; $i < count($atk_fleets); ++$i) {
        $atk_units[0] += $atk_fleets[$i]['unit_1'];
        $atk_units[1] += $atk_fleets[$i]['unit_2'];
        $atk_units[2] += $atk_fleets[$i]['unit_3'];
        $atk_units[3] += $atk_fleets[$i]['unit_4'];
    }

    $dfd_units = array($this->dest['unit_1'], $this->dest['unit_2'], $this->dest['unit_3'], $this->dest['unit_4'],$this->dest['resource_4']);

    // Are there some defenders?
    if (array_sum($dfd_units)>0)
    {
        $ucmb = UnitFight($atk_units, $this->move['user_race'], $dfd_units, $this->dest['user_race'], $this->mid);
        $n_atk_alive = array_sum($ucmb[0]);
        $atk_alive = $ucmb[0];
        $dfd_alive = $ucmb[1];
    }
    // No ground troops presents!
    else
    {
        $n_atk_alive=1;
        $atk_alive = $atk_units;
        $dfd_alive = array(0, 0, 0, 0, 0);
    }

    // 01/07/08 - AC: Count losses for each part
    for($i = 0; $i < 5; ++$i) {
        $atk_losses[$i] = $atk_units[$i] - $atk_alive[$i];
        $dfd_losses[$i] = $dfd_units[$i] - $dfd_alive[$i];
    }

    // If there are no more attackers, the defender
    // always won even if the defending had no units
    if($n_atk_alive == 0) {
        // In order to eliminate the troops of the aggressor, simply reset all transporters

        // 21/06/08 - AC: Keep out workers from battle!
        $sql = 'UPDATE ships
                INNER JOIN ship_templates ON ships.template_id = ship_templates.id
                        SET unit_1 = ship_templates.min_unit_1,
                        unit_2 = ship_templates.min_unit_2,
                        unit_3 = ship_templates.min_unit_3,
                        unit_4 = ship_templates.min_unit_4
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update ship fleets unit transport data! SKIP');
        }

        // Simply update the defender troops

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

        // #############################################################################
        // 03/04/08 - AC: Retrieve player language
        switch($this->move['language'])
        {
            case 'GER':
                $atk_title = 'Angriff auf '.$this->dest['planet_name'].' teilweise erfolgreich';
            break;
            case 'ITA':
                $atk_title = 'Attacco su '.$this->dest['planet_name'].' parzialmente riuscito';
            break;
            default:
                $atk_title = 'Attack on '.$this->dest['planet_name'].' partially successful';
            break;
        }
    }
    else {
        account_log($this->move['user_id'], $this->dest['user_id'], 4);

        // Export a ruler switch

        // We need the number of planets, which owns the colonizing
        // (we want planet_owner_enum degree of certainty)

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

        // We accommodate as much troops, as on the colony ship were, on the planet
        $planet_units = array(0, 0, 0, 0);

        // 25/06/08 - AC: Check available space on target planet
        $sql = 'SELECT max_units FROM planets WHERE planet_id = '.$this->move['dest'];

        if(($plspace = $this->db->queryrow($sql)) === false) {
            $this->log(MV_M_DATABASE, 'Could not query planet max units data! CONTINUE AND USE INSTABLE VALUE');
            $plspace['max_units'] = 1300; // Minimum size available
        }

        if(($atk_alive[0] * 2 + $atk_alive[1] * 3 + $atk_alive[2] * 4 + $atk_alive[3] * 4) > $plspace['max_units'])
        {
            $this->log(MV_M_NOTICE, 'Alive units exceed planet maximum units, we need to recall aboard the surplus');

            // AC: Recall aboard the surplus troops
            $i = 0;
            while($n_atk_alive) {
                if(($planet_units[0] * 2 + $planet_units[1] * 3 +
                    $planet_units[2] * 4 + $planet_units[3] * 4) >= $plspace['max_units'])
                    break;
                if($atk_alive[$i] > 0)
                {
                    $planet_units[$i]++;
                    $atk_alive[$i]--;
                    $n_atk_alive--;
                }
                else
                    $i++;
            }

            // Borg doesn't have cargo, simply use the Cube
            /*
            $sql = 'UPDATE ship_fleets
                    SET unit_1 = '.$atk_alive[0].',
                        unit_2 = '.$atk_alive[1].',
                        unit_3 = '.$atk_alive[2].',
                        unit_4 = '.$atk_alive[3].'
                    WHERE fleet_id = '.$this->fleet_ids_str;

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update fleets units data! CONTINUE');
            }
             * 
             */
        } // alive troops doesn't exceed max planet units
        else {
            for($i = 0; $i <= 3; ++$i)
                $planet_units[$i] += $atk_alive[$i];

            /*
            $sql = 'UPDATE ship_fleets
                    SET unit_1 = 0,
                        unit_2 = 0,
                        unit_3 = 0,
                        unit_4 = 0
                    WHERE fleet_id IN ('.$this->fleet_ids_str.')';

            if(!$this->db->query($sql)) {
                return $this->log(MV_M_DATABASE, 'Could not update ship fleets unit transport data! SKIP');
            }
             * 
             */
        }

        $sql = 'DELETE FROM scheduler_instbuild
                WHERE planet_id = '.$this->move['dest'];

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete scheduler instbuild data! CONTINUE');
        }

        $sql = 'DELETE FROM scheduler_research
                WHERE planet_id = '.$this->move['dest'];

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete scheduler research data! CONTINUE');
        }

        global $NUM_BUILDING, $MAX_BUILDING_LVL;

        // As someone yes number == max_index sets
        $n_buildings = $NUM_BUILDING + 1;

        $building_levels = array();

        $building_damage = 0.01 * mt_rand(40, 60);

        // Set new home world
        if($this->move['dest'] == $this->dest['user_capital']) {
            for($i = 0; $i < $n_buildings; ++$i) {
                $j = $i + 1;

                $building_levels[$i] = (int) ($this->dest['building_'.$j] > $MAX_BUILDING_LVL[0][$i]) ? $MAX_BUILDING_LVL[0][$i] : round($this->dest['building_'.$j] * $building_damage, 0);
            }

            if($this->dest['user_planets'] == 1) {
                // #############################################################################
                // 03/04/08 - AC: Retrieve player language
                switch($this->dest['language'])
                {
                    case 'GER':
                        $msg_title = 'Verlust aller deiner Planeten';
                        $msg_body = 'Da du alle deine Planeten verloren hast, wurde f&uuml;r dich ein neuer Planet an einer zuf&uuml;ligen Stelle der Galaxie erstellt.';
                    break;
                    case 'ITA':
                        $msg_title = 'Perdita di tutti i pianeti';
                        $msg_body = 'Dato che hai perduto tutti i pianeti ne &egrave; stato creato uno nuovo nella galassia.';
                    break;
                    default:
                        $msg_title = 'Loss of all your planets';
                        $msg_body = 'Since you lost all your planets a new one have been created in the body of the galaxy.';
                    break;
                }
                SystemMessage($this->dest['user_id'], $msg_title, $msg_body);
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

        $sql = 'DELETE FROM scheduler_shipbuild WHERE planet_id = '.$this->move['dest'];

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not delete ship building queue! CONTINUE');
        }

        if($this->dest['planet_type'] == "m" || $this->dest['planet_type'] == "o" ||
           $this->dest['planet_type'] == "p" || $this->dest['planet_type'] == "x" ||
           $this->dest['planet_type'] == "y" || $this->dest['planet_type'] == "f" ||
           $this->dest['planet_type'] == "g") {
            $def_tech_lev = 9;
        }
        else {
            $def_tech_lev = 6;
        }
        

        $sql = 'UPDATE planets
                SET planet_owner = '.$this->move['user_id'].',
                    planet_name = "Unimatrix #'.$this->move['dest'].'",
                    best_mood = 0,
                    best_mood_user = 0,
                    planet_owned_date = '.time().',
                    planet_owner_enum = '.($n_planets - 1).',
                    resource_4 = '.$ucmb[1][4].',
                    planet_next_attack = 0,
                    planet_attack_ships = 0,
                    planet_attack_type = 0,
                    research_3 = '.$def_tech_lev.',
                    research_4 = '.$def_tech_lev.',
                    research_5 = '.$def_tech_lev.',
                    recompute_static = 1,
                    building_1 = 9,
                    building_2 = '.$building_levels[1].',
                    building_3 = '.$building_levels[2].',
                    building_4 = '.$building_levels[3].',
                    building_5 = 9,
                    building_6 = 9,
                    building_7 = '.$building_levels[6].',
                    building_8 = '.$building_levels[7].',
                    building_9 = '.$building_levels[8].',
                    building_10 = '.$building_levels[9].',
                    building_11 = '.$building_levels[10].',
                    building_12 = '.$building_levels[11].',
                    building_13 = '.$building_levels[12].',
                    unit_1 = '.$planet_units[0].',
                    unit_2 = '.$planet_units[1].',
                    unit_3 = '.$planet_units[2].',
                    unit_4 = '.$planet_units[3].',
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
                    unittrainid_1 = 0, unittrainid_2 = 0, unittrainid_3 = 0, unittrainid_4 = 0, unittrainid_5 = 0, unittrainid_6 = 0, unittrainid_7 = 0, unittrainid_8 = 0, unittrainid_9 = 0, unittrainid_10 = 0, 
                    unittrainnumber_1 = 0, unittrainnumber_2 = 0, unittrainnumber_3 = 0, unittrainnumber_4 = 0, unittrainnumber_5 = 0, unittrainnumber_6 = 0, unittrainnumber_7 = 0, unittrainnumber_8 = 0, unittrainnumber_9 = 0, unittrainnumber_10 = 0, 
                    unittrainnumberleft_1 = 0, unittrainnumberleft_2 = 0, unittrainnumberleft_3 = 0, unittrainnumberleft_4 = 0, unittrainnumberleft_5 = 0, unittrainnumberleft_6 = 0, unittrainnumberleft_7 = 0, unittrainnumberleft_8 = 0, unittrainnumberleft_9 = 0, unittrainnumberleft_10 = 0, 
                    unittrainendless_1 = 0, unittrainendless_2 = 0, unittrainendless_3 = 0, unittrainendless_4 = 0, unittrainendless_5 = 0, unittrainendless_6 = 0, unittrainendless_7 = 0, unittrainendless_8 = 0, unittrainendless_9 = 0, unittrainendless_10 = 0, 
                    unittrain_actual = 0,
                    unittrainid_nexttime=0,
                    planet_insurrection_time=0
                WHERE planet_id = '.$this->move['dest'];

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
        }


// DC >>>> Historycal record in planet_details, with label '29'
        $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code, defeat_uid, defeat_aid)
                VALUES ('.$this->move['dest'].', 0, 0, 0, 0, '.time().', 29,'.$this->dest['user_id'].', '.$this->dest['user_alliance'].')';

        if(!$this->db->query($sql)) {
            $this->log(MV_M_DATABASE, 'Could not update planet details data! CONTINUE');
        }
// DC <<<<

// DC >>>> Update borg_target table; the $this->dest['user_id'] entry may not exists, so do not check error
        $sql = 'UPDATE borg_target SET battle_lost = battle_lost + 1,
                                       planets_back = planets_back + 1,
                                       under_attack = under_attack - 1
                WHERE user_id = '.$this->dest['user_id'];
        $this->db->query($sql);
// DC <<<<

// DC >>>>
        $sql = 'DELETE FROM borg_npc_target WHERE planet_id = '.$this->move['dest'];
        $this->db->query($sql);
        $sql = 'UPDATE scheduler_shipmovement SET action_code = 11
                WHERE action_code = 46 AND move_id <> '.$this->mid.'
                AND move_status = 0 AND user_id = '.BORG_USERID.' AND dest = '.$this->move['dest'];
        $this->db->query($sql);
// DC <<<<
// DC >>>> Settlers management
        if($this->dest['user_id'] == INDEPENDENT_USERID)
        {
            $sql = 'SELECT sr.user_id, u.language FROM settlers_relations sr
                    LEFT JOIN user u ON u.user_id = sr.user_id
                    WHERE planet_id = '.$this->move['dest'].' GROUP BY sr.user_id';
            if($m_u_q = $this->db->queryrowset($sql))
            {
                foreach($m_u_q as $m_u)
                {
                    $log_data = array($this->move['dest'],$this->dest['planet_name'], 0, '', 103);
                    switch($m_u['language'])
                    {
                    case 'GER':
                        $log_title = 'Automatic distress message from ';
                    break;
                    case 'ITA':
                        $log_title = 'Richiesta automatica di soccorso dalla colonia ';
                    break;
                    default:
                        $log_title = 'Automatic distress message from ';
                    break;
                    }

                    add_logbook_entry($m_u['user_id'], LOGBOOK_SETTLERS, $log_title.$this->dest['planet_name'], $log_data);
                }
                $sql = 'DELETE FROM settlers_relations WHERE planet_id = '.$this->move['dest'];
                $this->db->query($sql);
            }
        }
// DC <<<< Settlers management        

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

        $action_status = 1;

        // #############################################################################
        // 03/04/08 - AC: Retrieve player language
        switch($this->move['language'])
        {
            case 'GER':
                $atk_title = 'Angriff auf '.$this->dest['planet_name'].' erfolgreich';
            break;
            case 'ITA':
                $atk_title = 'Attacco su '.$this->dest['planet_name'].' riuscito';
            break;
            default:
                $atk_title = 'Attack on '.$this->dest['planet_name'].' successful';
            break;
        }
    }

// DC >>>>
    if($this->dest['user_id'] == INDEPENDENT_USERID)
    {
        $sql = 'SELECT sr.user_id, u.language FROM settlers_relations sr
                LEFT JOIN user u ON u.user_id = sr.user_id
                WHERE planet_id = '.$this->move['dest'].' GROUP BY sr.user_id';
        if($m_u_q = $this->db->queryrowset($sql))
        {
            foreach($m_u_q as $m_u)
            {
                $log_data = array($this->move['dest'],$this->dest['planet_name'], 0, '', 104);
                switch($m_u['language'])
                {
                    case 'GER':
                        $log_title = 'Distress call from ';
                    break;
                    case 'ITA':
                        $log_title = 'Richiesta di soccorso dalla colonia ';
                    break;
                    default:
                        $log_title = 'Distress call from ';
                    break;
                }
                    
                add_logbook_entry($m_u['user_id'], LOGBOOK_SETTLERS, $log_title.$this->dest['planet_name'], $log_data);
            }
        }
    }
// DC <<<<

    $sql = 'UPDATE ship_fleets
            SET planet_id = '.$this->move['dest'].',
                move_id = 0
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        // Here one could also report and then go on
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
    }
}
else {
    // #############################################################################
    // 03/04/08 - AC: Retrieve player language
    switch($this->move['language'])
    {
        case 'GER':
            $atk_title = 'Angriff auf '.$this->dest['planet_name'].' fehlgeschlagen';
        break;
        case 'ITA':
            $atk_title = 'Attacco su '.$this->dest['planet_name'].' fallito';
        break;
        default:
            $atk_title = 'Attack on '.$this->dest['planet_name'].' failed';
        break;
    }

// DC >>>> Begin Borg Adaption Phase!!!
    
    $adaption_chance_array = array (
        'no_event'  => 90,
        'adapt_PRI' => 13,
        'adapt_SEC' => 12,
        'adapt_SHI' => 7,
        'adapt_ARM' => 9,
        'adapt_REA' => 4,
        'adapt_RED' => 5,
        'adapt_AGI' => 3,
        'adapt_ROF' => 4,
        'adapt_ROF2' => 2
    );

    $adaption_array = array();

    foreach($adaption_chance_array as $event => $probability) {
        for($i = 0; $i < $probability; ++$i) {
            $adaption_array[] = $event;
        }
    }
    
    $adaption_event = $adaption_array[array_rand($adaption_array)];
    
    if($adaption_event == 'no_event') {
        $this->log(MV_M_NOTICE, 'Borg Adaption Phase not occured this time.');
    }
    else {
        $this->log(MV_M_NOTICE, 'Borg Adaption Phase begin NOW.');
        $borg_bot = $this->db->queryrow('SELECT ship_template2 FROM borg_bot WHERE id = 1');
    
        $borgcube_tp = $this->db->queryrow('SELECT * FROM ship_templates WHERE owner = '.BORG_USERID.' AND id = '.$borg_bot['ship_template2']);        
        
        $newvalue1 = $borgcube_tp['value_1'];
        $newvalue2 = $borgcube_tp['value_2'];
        $newvalue4 = $borgcube_tp['value_4'];
        $newvalue5 = $borgcube_tp['value_5'];
        $newvalue6 = $borgcube_tp['value_6'];
        $newvalue7 = $borgcube_tp['value_7'];
        $newvalue8 = $borgcube_tp['value_8'];
        $newrof    = $borgcube_tp['rof'];
        $newrof2   = $borgcube_tp['rof2'];
        
        switch($adaption_event) {
            case 'adapt_PRI':
                $newvalue1 = $newvalue1 * 1.015;
                $this->log(MV_M_NOTICE, 'Borg Primary Attack Value ++.');
                break;
            case 'adapt_SEC':
                $newvalue2 = $newvalue2 * 1.015;
                $this->log(MV_M_NOTICE, 'Borg Secondary Attack Value ++.');
                break;
            case 'adapt_SHI':
                $newvalue4 = $newvalue4 * 1.025;
                $this->log(MV_M_NOTICE, 'Borg Armor Value ++.');
                break;
            case 'adapt_ARM':
                $newvalue5 = $newvalue5 * 1.020;
                $this->log(MV_M_NOTICE, 'Borg Shield Value ++.');
                break;
            case 'adapt_REA':
                $newvalue6 = $newvalue6 + 1;
                $this->log(MV_M_NOTICE, 'Borg Reaction Value ++.');
                break;
            case 'adapt_RED':
                $newvalue7 = $newvalue7 + 1;
                $this->log(MV_M_NOTICE, 'Borg Readiness Value ++.');
                break;
            case 'adapt_AGI':
                $newvalue8 = $newvalue8 + 1;
                $this->log(MV_M_NOTICE, 'Borg Agility Value ++.');
                break;
            case 'adapt_ROF':
                $newrof = $newrof + 1;
                $this->log(MV_M_NOTICE, '+++ Borg ROF Value +++.');
                break;
            case 'adapt_ROF2':
                $newrof2 = $newrof2 + 1;
                $this->log(MV_M_NOTICE, '+++ Borg ROF2 Value +++.');
                break;            
        }
        
        $oldversion = 0;
        
        sscanf($borgcube_tp['name'],"Adapted Cube#%u",$oldversion);
        
        $sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
                                                component_1, component_2, component_3, component_4, component_5,
                                                component_6, component_7, component_8, component_9, component_10,
                                                value_1, value_2, value_3, value_4, value_5,
                                                value_6, value_7, value_8, value_9, value_10,
                                                value_11, value_12, value_13, value_14, value_15,
                                                resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
                                                min_unit_1, min_unit_2, min_unit_3, min_unit_4,
                                                max_unit_1, max_unit_2, max_unit_3, max_unit_4,
                                                buildtime, rof, rof2, max_torp)
                VALUES ("'.$borgcube_tp['owner'].'","'.time().'","Adapted Cube#'.($oldversion + 1).'","Assimilation ship","'.$borgcube_tp['race'].'",9,3,
                            -1,-1,-1,-1,-1,
                            -1,-1,-1,-1,-1,
                            "'.$newvalue1.'","'.$newvalue2.'","140","'.$newvalue4.'","'.$newvalue5.'",
                            "'.$newvalue6.'","'.$newvalue7.'","'.$newvalue8.'","50","10",
                            "42","0","300","300","0",
                            "500000","500000","500000","50000","39","16",
                            "294","0","0","6",
                            "9294","6000","3000","6",
                            0, '.$newrof.', '.$newrof2.', 1000)';
        
        $this->db->query($sql);
        
        $newtpid = $this->db->insert_id();
        
        $this->db->query('UPDATE borg_bot SET ship_template2 = '.$newtpid.' WHERE id = 1 ');
        
        $this->log(MV_M_NOTICE, 'Borg Adaption Phase ends NOW.');
    }

    
// DC <<<< End Borg Adaption Phase

// DC >>>> Settlers management
    if($this->dest['user_id'] == INDEPENDENT_USERID)
    {
        // Check for defenders
        if($n_st_user > 0)
        {
            $sql = 'SELECT user_id from borg_target WHERE user_id IN ('.implode(",", $st_user).')';
            if(!($def_q = $this->db->query($sql))) {
                $this->log(MV_M_DATABASE, 'Could not query borg_target data! '.$sql);
            }
            $num_row = $this->db->num_rows($def_q);
            if($num_row > 0)
            {
                // Update priority of this planet!!!
                $sql = 'UPDATE borg_npc_target SET priority = priority + '.(10*$num_row).' WHERE planet_id = '.$this->move['dest'];
                if(!$this->db->query($sql)) $this->log(MV_M_DATABASE, 'Could not update borg_npc_target data! '.$sql);
                $def_i_q = $this->db->fetchrowset($def_q);
                foreach($def_i_q AS $item_def_q)
                {
                    $sql = 'UPDATE borg_target SET battle_win = battle_win + 1 WHERE user_id = '.$item_def_q['user_id'];
                    if(!$this->db->query($sql)) $this->log(MV_M_DATABASE, 'Could not update borg_target data! '.$sql);
                }
            }
        }

        $sql = 'UPDATE borg_npc_target SET tries = tries + 1,
                                           live_attack = live_attack - 1 
                WHERE planet_id = '.$this->move['dest'];
        $this->db->query($sql);

        $sql = 'SELECT sr.user_id, u.language FROM settlers_relations sr
                LEFT JOIN user u ON u.user_id = sr.user_id
                WHERE planet_id = '.$this->move['dest'].' GROUP BY sr.user_id';
        if($m_u_q = $this->db->queryrowset($sql))
        {
            foreach($m_u_q as $m_u)
            {
                $log_data = array($this->move['dest'],$this->dest['planet_name'], 0, '', 104);
                switch($m_u['language'])
                {
                    case 'GER':
                        $log_title = 'Distress call from ';
                    break;
                    case 'ITA':
                        $log_title = 'Richiesta di soccorso dalla colonia ';
                    break;
                    default:
                        $log_title = 'Distress call from ';
                    break;
                }

                add_logbook_entry($m_u['user_id'], LOGBOOK_SETTLERS, $log_title.$this->dest['planet_name'], $log_data);
            }
        }
    }

// DC <<<< Settlers management

    // 01/07/08 - The attacker has lost the fleets
    for($i = 0; $i < count($atk_fleets); ++$i) {
        $atk_losses[0] += $atk_fleets[$i]['unit_1'];
        $atk_losses[1] += $atk_fleets[$i]['unit_2'];
        $atk_losses[2] += $atk_fleets[$i]['unit_3'];
        $atk_losses[3] += $atk_fleets[$i]['unit_4'];
        $atk_losses[4] += $atk_fleets[$i]['resource_4'];
    }
}


// #############################################################################
// Write logbook

$log1_data = array(46, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(46, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);

$log1_data[17] = $log2_data[17] = $action_status;

$log1_data[20] = $this->dest['user_race'];
$log2_data[20] = $this->move['user_race'];

for($i = 0; $i < 5; ++$i) {
    $log1_data[18][$i] = $log2_data[19][$i] = $atk_losses[$i];
    $log1_data[19][$i] = $log2_data[18][$i] = $dfd_losses[$i];
}

//$log1_data[19][4] = $log2_data[18][4] = (int)$dfd_losses[4]; already done by for above...

$log2_data[14] = $this->cmb[MV_CMB_KILLS_PLANETARY];

$log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
$log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];


add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL, $atk_title, $log1_data);
add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL, $dfd_title, $log2_data);

if($n_st_user > 0) {
    $log2_data[2] = $this->move['dest'];
    $log2_data[3] = $this->dest['planet_name'];
    $log2_data[4] = $this->dest['user_id'];
    $log2_data[5] = 0;
    $log2_data[6] = 0;
    $log2_data[7] = 0;
    $log2_data[16] = 1;

    for($i = 0; $i < $n_st_user; ++$i) {
        // #############################################################################
        // 03/04/08 - AC: Retrieve player language
        $log_title = 'One of your allies defended '.$this->dest['planet_name'];
        $sql = 'SELECT language FROM user WHERE user_id = '.$st_user[$i];
        if(!($lang = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Could not retrieve player language');
        }
        else
        {
            switch($lang['language'])
            {
                case 'GER':
                    $log_title = 'Verb&uuml;ndeten bei '.$this->dest['planet_name'].' verteidigt';
                break;
                case 'ENG':
                    $log_title = 'Joined the defenders of the '.$this->dest['planet_name'].' planet';
                break;
                case 'ITA':
                    $log_title = 'Difesa alleata presso '.$this->dest['planet_name'];
                break;
            }
        }

        add_logbook_entry($st_user[$i], LOGBOOK_TACTICAL, $log_title, $log2_data);
    }
}

return MV_EXEC_OK;

    }
}

?>
