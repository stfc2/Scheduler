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
// Data from the attacker

$sql = 'SELECT '.$this->get_combat_query_fleet_columns().', f.resource_4, f.unit_1, f.unit_2, f.unit_3, f.unit_4
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        WHERE f.fleet_id IN ('.$this->fleet_ids_str.')';

if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
    return $this->log(MV_M_DATABASE, 'Could not query attackers fleets data! SKIP');
}

/* 01/07/08 -AC: I need to move this query here, in order to correctly report attacker
 *               losses in case he lost the battle.
 */
$sql = 'SELECT ss.fleet_id,
               SUM(ss.unit_1 - st.min_unit_1) AS troop_1,
               SUM(ss.unit_2 - st.min_unit_2) AS troop_2,
               SUM(ss.unit_3 - st.min_unit_3) AS troop_3,
               SUM(ss.unit_4 - st.min_unit_4) AS troop_4
        FROM ships ss, ship_templates st
        WHERE ss.template_id = st.id
        AND ss.fleet_id IN ('.$this->fleet_ids_str.')
        GROUP BY ss.fleet_id';

if(($atk_crews = $this->db->queryrowset($sql)) === false) {
    return $this->log(MV_M_DATABASE, 'Could not query attacking fleet data! SKIP');
}


// #############################################################################
// Data from the defender

$user_id = $this->dest['user_id'];
$planetary_attack = true;

// The course could be optimized, but it should be possible 
// compatibility to provide much action_51.php maintained
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
// If the attacker has won attempt to start takeover

// #############################################################################
// 03/04/08 - AC: Retrieve player language
switch($this->dest['language'])
{
    case 'GER':
        $dfd_title = 'Angriff auf '.$this->dest['planet_name'];
    break;
    case 'ITA':
        $dfd_title = 'Attacco su '.$this->dest['planet_name'];
    break;
    default:
        $dfd_title = 'Attack on '.$this->dest['planet_name'];
    break;
}

$action_status = 0;

/* 11/07/08 - AC: Initialize here arrays atk/dfd_losses */
$atk_losses = array(0, 0, 0, 0, 0);
$dfd_losses = array(0, 0, 0, 0, 0);

if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
    if(empty($this->action_data[0])) {
        return $this->log('Moves', 'action_55: Could not find required action_data entry [0]! SKIP');
    }

    $ship_id = (int)$this->action_data[0];

    // We make it quite safe here, to see whether the
    // ship is

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

        // Does player has reached max number of planets?
        if($n_planets < USER_MAX_PLANETS) {
            $atk_units = array( ($cship['unit_1'] - $cship['min_unit_1']) , ($cship['unit_2'] - $cship['min_unit_2']) , ($cship['unit_3'] - $cship['min_unit_3']) , ($cship['unit_4'] - $cship['min_unit_4']),0 );

            // 20/06/08 - AC: Keep out workers from battle!
            for($i = 0; $i < count($atk_fleets); ++$i) {
                $atk_units[0] += $atk_fleets[$i]['unit_1'];
                $atk_units[1] += $atk_fleets[$i]['unit_2'];
                $atk_units[2] += $atk_fleets[$i]['unit_3'];
                $atk_units[3] += $atk_fleets[$i]['unit_4'];
                //$atk_units[4] += $atk_fleets[$i]['resource_4']; <-- What the hell is this field?? No workers in the battle!
                // 23/06/08 - AC: Store the space occupied by workers
                $worker_per_fleets[$atk_fleets[$i]['fleet_id']] = $atk_fleets[$i]['resource_4'];
            }

            // 090408 DC:  ------ Let those fucking soldiers on ships do their job!!!!
            for($i = 0; $i < count($atk_crews); ++$i) {
                $atk_units[0] += $atk_crews[$i]['troop_1'];
                $atk_units[1] += $atk_crews[$i]['troop_2'];
                $atk_units[2] += $atk_crews[$i]['troop_3'];
                $atk_units[3] += $atk_crews[$i]['troop_4'];
            }
            // 090409 DC:  ------

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
                $sql = 'UPDATE ship_fleets
                        SET unit_1 = 0,
                            unit_2 = 0,
                            unit_3 = 0,
                            unit_4 = 0
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

                // 090408 DC:  ------ Finally the fucking soldiers have done their job!!!! 
                $sql = 'UPDATE ships ss, ship_templates st
                        SET ss.unit_1 = st.min_unit_1,
                            ss.unit_2 = st.min_unit_2,
                            ss.unit_3 = st.min_unit_3,
                            ss.unit_4 = st.min_unit_4
                        WHERE ss.template_id = st.id
                    AND ss.fleet_id IN ('.$this->fleet_ids_str.')';
                $this->log(MV_M_NOTICE, 'Update ships with the minimum troops value: '.$sql);
                if(!$this->db->query($sql))
                    return $this->log(MV_M_DATABASE, 'Could not update attacking ships! SKIP');
                // 090408 DC:  ------ 

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

                // We accommodate as much troops, as on the colony ship were, on the planet
                $planet_units = array(0, 0, 0, 0);

                // 21/06/08 - AC: Check available space on target planet
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

                    $this->log(MV_M_NOTICE, 'Troops that will return aboard: Lev1 '.$atk_alive[0].
                        ' Lev2 '.$atk_alive[1].' Lev3 '.$atk_alive[2].' Lev4 '.$atk_alive[3]);

                    // We pick ALL transporter of the fleets and then distribute

                    $sql = 'SELECT s.ship_id, s.fleet_id
                            FROM (ships s)
                            INNER JOIN (ship_templates st) ON s.template_id = st.id
                            WHERE s.fleet_id IN ('.$this->fleet_ids_str.') AND
                                  st.ship_torso = '.SHIP_TYPE_TRANSPORTER;

                    if(!$q_ftrans = $this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not query fleets transporter data! CONTINUE');
                    }

                    // 27/10/08 - AC: First of all, loads all available transports
                    $space_per_fleets = array();

                    while($_ship = $this->db->fetchrow($q_ftrans)) {
                        if(!isset($space_per_fleets[$_ship['fleet_id']])) $space_per_fleets[$_ship['fleet_id']] = $worker_per_fleets[$_ship['fleet_id']] * (-1);

                        $space_per_fleets[$_ship['fleet_id']] += MAX_TRANSPORT_UNITS;
                    }

                    foreach($space_per_fleets as $fleet_id => $free_space) {
                        if($free_space == 0) continue;

                        $still_free = $free_space;
                        $new_load = array(0, 0, 0, 0);

                        for($i = 0; $i < 4; ++$i) {
                            if($atk_alive[$i] > 0) {
                                $new_load[$i] = ($atk_alive[$i] > $still_free) ? $still_free : $atk_alive[$i];

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

                        if(!$this->db->query($sql)) {
                            $this->log(MV_M_DATABASE, 'Could not update fleets units data! CONTINUE');
                        }
                    }

                    // 27/10/08- AC: If there are still some extra troops on ground
                    $n_atk_still_to_board = array_sum($atk_alive);
                    if($n_atk_still_to_board > 0) {
                        $this->log(MV_M_NOTICE,
                            'There are still: Lev1 '.$atk_alive[0].
                                            ' Lev2 '.$atk_alive[1].
                                            ' Lev3 '.$atk_alive[2].
                                            ' Lev4 '.$atk_alive[3].
                                            ' troops to embark!');

                        // Extra troops didn't really leave the ships so we have to remove the losses
                        $sql = 'SELECT ss.ship_id,
                                       st.min_unit_1, st.max_unit_1,
                                       st.min_unit_2, st.max_unit_2,
                                       st.min_unit_3, st.max_unit_3,
                                       st.min_unit_4, st.max_unit_4
                                FROM ships ss, ship_templates st
                                WHERE ss.template_id = st.id AND
                                      ss.fleet_id IN ('.$this->fleet_ids_str.')';

                        if(!$q_ships = $this->db->query($sql)) {
                            $this->log(MV_M_DATABASE, 'Could not query fleets ships data! CONTINUE');
                        }

                        // Distribute troops on available ships
                        while($ship = $this->db->fetchrow($q_ships)) {
                            $new_crew = array(0, 0, 0, 0);

                            // For each type of troops
                            for($i = 0; $i < 4; ++$i) {
                                $j = $i + 1;

                                // Are there still some troops?
                                if($atk_alive[$i] > 0) {
                                    $new_crew[$i] = ($atk_alive[$i] > $ship['max_unit_'.$j]) ? $ship['max_unit_'.$j] : $atk_alive[$i];
                                    $atk_alive[$i] -= $new_crew[$i];
                                }
                                // Then use min level
                                else
                                    $new_crew[$i] = $ship['min_unit_'.$j];

                                $n_atk_still_to_board -= $new_crew[$i];
                            }

                            // Update ship's crew
                            $sql = 'UPDATE ships
                                    SET unit_1 = '.$new_crew[0].',
                                        unit_2 = '.$new_crew[1].',
                                        unit_3 = '.$new_crew[2].',
                                        unit_4 = '.$new_crew[3].'
                                    WHERE ship_id ='.$ship['ship_id'];

                            if(!$this->db->query($sql)) {
                                $this->log(MV_M_DATABASE, 'Could not update ship crew data! CONTINUE');
                            }

                            // If we have finished boarding phase, we are ready to takeoff ;)
                            if($n_atk_still_to_board == 0)
                                break;
                        }
                    }
                } // alive troops doesn't exceed max planet units
                else {
                    for($i = 0; $i <= 3; ++$i)
                        $planet_units[$i] += $atk_alive[$i];

                    // 28/06/08 - AC: Snort! Delete landed troops!
                    $sql = 'UPDATE ship_fleets
                            SET unit_1 = 0,
                                unit_2 = 0,
                                unit_3 = 0,
                                unit_4 = 0
                            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

                    if(!$this->db->query($sql)) {
                        return $this->log(MV_M_DATABASE, 'Could not update ship fleets unit transport data! SKIP');
                    }

                    // 27/10/08 - AC: Delete also extra troops on ships...
                    $sql = 'UPDATE ships ss, ship_templates st
                            SET ss.unit_1 = st.min_unit_1,
                                ss.unit_2 = st.min_unit_2,
                                ss.unit_3 = st.min_unit_3,
                                ss.unit_4 = st.min_unit_4
                            WHERE ss.template_id = st.id AND
                                  ss.fleet_id IN ('.$this->fleet_ids_str.')';

                    if(!$this->db->query($sql))
                        return $this->log(MV_M_DATABASE, 'Could not update attacking ships to minimum crew! SKIP');
                }

                $this->log(MV_M_NOTICE, 'Troops that will remain on the planet: Lev1 '.$planet_units[0].
                    ' Lev2 '.$planet_units[1].' Lev3 '.$planet_units[2].' Lev4 '.$planet_units[3]);

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

                $sql = 'DELETE FROM scheduler_resourcetrade
                        WHERE planet = '.$this->move['dest'];

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not delete resource data! CONTINUE');
                }

                $sql = 'DELETE FROM scheduler_shipbuild
                        WHERE planet_id = '.$this->move['dest'];

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not delete shipbuild data! CONTINUE');
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

                $sql = 'UPDATE planets
                        SET planet_owner = '.$this->move['user_id'].',
                            planet_owned_date = '.time().',
                            planet_owner_enum = '.($n_planets - 1).',
                            planet_available_points = '.($this->get_structure_points($this->move['user_id'], $this->move['dest'])).',
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

                //$this->log('SQL Debug', ''.$sql.'');

                if(!$this->db->query($sql)) {
                    return $this->log(MV_M_DATABASE, 'Could not update planets data! SKIP');
                }

// DC ---- History record in planet_details, with label '26'
                $sql = 'INSERT INTO planet_details (planet_id, user_id, alliance_id, source_uid, source_aid, timestamp, log_code, defeat_uid, defeat_aid)
                        VALUES ('.$this->move['dest'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.$this->move['user_id'].', '.$this->move['user_alliance'].', '.time().', 26,'.$this->dest['user_id'].', '.$this->dest['user_alliance'].')';

                if(!$this->db->query($sql)) {
                    $this->log(MV_M_DATABASE, 'Could not update planet details data! CONTINUE');
                }

                // If the attack was on a settlers planet, we get rid of the moods data!

                if($this->dest['user_id'] == INDEPENDENT_USERID) {
                    $this->log(MV_M_NOTICE, 'Colony: Settlers taken over!!! They gonna be no more...');

                    $sql = 'DELETE FROM planet_details WHERE planet_id = '.$this->dest['planet_id'].' AND log_code = 300';

                    if(!$this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not delete settlers moods! CONTINUE!');   
                    }
                }
// DC ----

            $this->log('Update Planetdata', 'Limited resources');

            //Concept of vessels destroyed by hostile takeover.
            //Version 0.2b by Mojo1987 - Calculation adjusted

            $this->log('Ship destruction', 'Begin ships deletion');

            $sql = 'SELECT s.ship_id FROM (ships s) WHERE s.fleet_id = -'.$this->move['dest'].'';

            if(!$del_ship = $this->db->query($sql)) {
               $this->log('Errors with ships get', 'Could not query planets ships! CONTINUE - '.$sql.'');
            }

            while($ship_wahl = $this->db->fetchrow($del_ship)) {

              $zufall = mt_rand(6,18);

              if($zufall>=8) {

                $sql = 'DELETE FROM ships WHERE ship_id = '.$ship_wahl['ship_id'].'';

                if(!$this->db->query($sql)) {
                    $this->log('Error with ship deletion', 'Could not query deleted ship! CONTINUE');
                }
                else { $this->log('Ships removed', 'Ship_ID: '.$ship_wahl['ship_id'].' Random number: '.$zufall.' <b>SUCCESS!</b>'); }
              }

            }

            $this->log('Ship destruction', 'Deletion ended');

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
                            $this->log(MV_M_DATABASE, 'Could not update user alliance Borg honor data! CONTINUE');
                        }
                    }

                    send_premonition_to_user($this->move['user_id']);

                    // #############################################################################
                    // 03/04/08 - AC: Retrieve player language
                    switch($this->move['language'])
                    {
                        case 'GER':
                            $msg_title = 'Verdienst im Kampf gegen die Borg';
                            $msg_text = 'F&uuml;e gerade erbrachten Verdienst gegen die neue Invasion des Borg-Kollektives befinden sich im Anflug auf euren Heimatplaneten ein speziell f&ouml;sen Kampf entwickeltes Schiff. Versucht nicht die enthaltene Technologie zu verstehen, sie wurde wirkungsvoll gegen euch abgeschirmt - nutzt sie stattdessen in eurem weiteren Kampf!';
                        break;
                        case 'ITA':
                            $msg_title = 'Merito in battaglia contro i Borg';
                            $msg_text = 'Proprio per rendere merito contro la nuova invasione del collettivo Borg si trova in approccio al tuo pianeta madre una nave appositamente progettata per combatterli. Non provare a capire la tecnologia inclusa, &egrave; stata efficacemente schermata contro di te - utilizzala invece ulteriormente nella tua lotta!';
                        break;
                        default:
                            $msg_title = 'Merit in the battle against the Borg';
                            $msg_text = 'For precisely rendered merit against the new invasion of the Borg Collective are located in the approach to your home planet fuse fight a specially designed ship. Do not try to understand the included technology, it was effectively shielded against you - instead uses it further in your fight!';
                       break;
                    }

                    $sql = 'INSERT INTO message (sender, receiver, subject, text, time)
                            VALUES ('.FUTURE_HUMANS_UID.', '.$this->move['user_id'].', "'.$msg_title.'", "'.$msg_text.'", '.time().')';

                    if(!$this->db->query($sql)) {
                        $this->log(MV_M_DATABASE, 'Could not send message');
                    }
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
        // Max planets reached
        else {
            $action_status = -3;

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
    }
    // No colonization ship found
    else {
        $action_status = -2;

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

    $sql = 'UPDATE ship_fleets
            SET planet_id = '.$this->move['dest'].',
                move_id = 0
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        // Here one could also report and then go on
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
    }
}
// Attacker has lost orbital battle
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

    // 01/07/08 - The attacker has lost the fleets
    for($i = 0; $i < count($atk_fleets); ++$i) {
        $atk_losses[0] += $atk_fleets[$i]['unit_1'];
        $atk_losses[1] += $atk_fleets[$i]['unit_2'];
        $atk_losses[2] += $atk_fleets[$i]['unit_3'];
        $atk_losses[3] += $atk_fleets[$i]['unit_4'];
        $atk_losses[4] += $atk_fleets[$i]['resource_4'];
    }
    for($i = 0; $i < count($atk_crews); ++$i) {
        $atk_losses[0] += $atk_crews[$i]['troop_1'];
        $atk_losses[1] += $atk_crews[$i]['troop_2'];
        $atk_losses[2] += $atk_crews[$i]['troop_3'];
        $atk_losses[3] += $atk_crews[$i]['troop_4'];
    }
}


// #############################################################################
// Write logbook

$log1_data = array(55, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(55, $this->move['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], 0, 0, 0, MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);

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
    $log2_data[2] = $this->move['start'];
    $log2_data[3] = $this->dest['planet_name'];
    $log2_data[4] = $user_id;
    $log2_data[5] = 0;
    $log2_data[6] = 0;
    $log2_data[7] = 0;
    $log2_data[16] = 1;

    for($i = 0; $i < $n_st_user; ++$i) {
        // #############################################################################
        // 24/06/08 - AC: Retrieve player language
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
