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



class moves_action_42 extends moves_common {
    function _action_main() {

// #############################################################################
// Daten der Angreifer

$sql = 'SELECT fleet_id
        FROM ship_fleets
        WHERE planet_id = '.$this->move['dest'].' AND
              user_id = '.$this->move['user_id'].' AND
              alert_phase >= '.ALERT_PHASE_YELLOW;

if(!$q_atk_st_fleets = $this->db->query($sql)) {
    return $this->log(MV_M_DATABASE, 'Could not query stationated attacker fleet data! SKIP');
}

$atk_fleet_ids_str = $this->fleet_ids_str;

while($_fleet = $this->db->fetchrow($q_atk_st_fleets)) {
    $atk_fleet_ids_str .= ','.$_fleet['fleet_id'];
}

$sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
		FROM (ship_fleets f)
		INNER JOIN user u ON u.user_id = f.user_id
		WHERE f.fleet_id IN ('.$atk_fleet_ids_str.')';

if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
	return $this->log(MV_M_DATABASE, 'Could not query attackers fleets data! SKIP');}


// #############################################################################
// Daten der Verteidiger

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
    return $this->log('MySQL', 'Could not query stationated fleets user data! SKIP');
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
    $sql = 'SELECT  '.$this->get_combat_query_fleet_columns().'
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
    $sql = 'SELECT  '.$this->get_combat_query_fleet_columns().'
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

if($this->do_ship_combat($atk_fleet_ids_str, implode(',', $dfd_fleet_ids), MV_COMBAT_LEVEL_ORBITAL) == MV_EXEC_ERROR) {
	$this->log(MV_M_DATABASE, 'Move Action 42: Something went wrong with this fight!');
	return MV_EXEC_ERROR;
}


// #############################################################################
// Evtl. die übriggeblienen Schiffe des Angreifers wieder zurückschicken

if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
    $sql = 'SELECT COUNT(ship_id) AS n_ships
            FROM ships
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';
            
    if(($ships_alive = $this->db->queryrow($sql)) === false) {
        return $this->log(MV_M_DATABASE, 'Could not query ships count! SKIP');
    }

    $sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
            VALUES ('.$this->move['user_id'].', 0, 0, '.$this->move['dest'].', '.$this->move['start'].', '.$this->move['total_distance'].', '.$this->move['total_distance'].', '.$this->move['tick_speed'].', '.$this->CURRENT_TICK.', '.($this->CURRENT_TICK + ($this->move['move_finish'] - $this->move['move_begin'])).', '.$ships_alive['n_ships'].', 12, "42")';

    if(!$this->db->query($sql)) {
        return $this->log(MV_M_DATABASE, 'Could not create new movement for return! SKIP');
    }

    $new_move_id = $this->db->insert_id();

    if(!$new_move_id) {
        return $this->log(MV_M_ERROR, 'Could not get new move id! SKIP');
    }

    $sql = 'UPDATE ship_fleets
            SET move_id = '.$new_move_id.'
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        return $this->log(MV_M_DATABASE, 'Could not update fleets movement data! SKIP');
    }

    $atk_title = 'Angriff auf '.$this->dest['planet_name'].' erfolgreich';
    $dfd_title = 'Schwerer Angriff auf '.$this->dest['planet_name'];
}
else {
    $atk_title = 'Angriff auf '.$this->dest['planet_name'].' fehlgeschlagen';
    $dfd_title = 'Verteidigung von '.$this->dest['planet_name'].' erfolgreich';
}


// #############################################################################
// Logbuch schreiben

$log1_data = array(41, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(41, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets, $this->cmb[MV_CMB_KILLS_PLANETARY]);

$log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
$log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];


add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $atk_title, $log1_data);
add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL_2, $dfd_title, $log2_data);

if($n_st_user > 0) {
    $log2_data[2] = $this->move['dest'];
    $log2_data[3] = $this->dest['planet_name'];
    $log2_data[4] = $this->dest['user_id'];
    $log2_data[5] = 0;
    $log2_data[6] = 0;
    $log2_data[7] = 0;
    $log2_data[16] = 1;

    for($i = 0; $i < $n_st_user; ++$i) {
        add_logbook_entry($st_user[$i], LOGBOOK_TACTICAL_2, 'Verbündeten bei '.$this->dest['planet_name'].' verteidigt', $log2_data);
    }
}

return MV_EXEC_OK;

    }
}

?>
