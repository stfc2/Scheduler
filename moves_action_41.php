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




class moves_action_41 extends moves_common {
    function _action_main() {

// #############################################################################
// Daten der Angreifer
/*
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
*/
//move_finish
$sql_user='SELECT user_alliance,user_id FROM user WHERE user_id='.$this->move['user_id'].'';
if(($user_attacker = $this->db->queryrow($sql_user)) === false) {
	return $this->log(MV_M_DATABASE, 'Could not dates from angreifer aus user hohlen - mein englisch ich weis! SKIP');
}
$sql_3 = 'SELECT DISTINCT f.user_id,f.fleet_id,ssm.dest,
               u.user_alliance,
               ud.ud_id, ud.accepted,
               ad.ad_id, ad.type, ad.status,ssm.move_id
        FROM (ship_fleets f)
        INNER JOIN user u ON u.user_id = f.user_id
        LEFT JOIN (scheduler_shipmovement ssm) ON ssm.move_id=f.move_id
        LEFT JOIN user_diplomacy ud ON ( ( ud.user1_id = '.$this->move['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->move['user_id'].' ) )
        LEFT JOIN alliance_diplomacy ad ON ( ( ad.alliance1_id ='.$user_attacker['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$user_attacker['user_alliance'].' ) ) 
        WHERE ssm.move_finish<='.$this->move['move_finish'].' AND
              f.user_id <>'.$this->dest['user_id'].' AND
ssm.move_status=0 AND ssm.action_code!=31 AND ssm.action_code!=33 AND ssm.action_code!=34 AND ssm.action_code!=14 AND ssm.action_code!=22 AND ssm.action_code!=23 AND
f.move_id!=0 AND
ssm.dest='.$this->move['dest'].' AND
              f.user_id <> '.$this->move['user_id'].' AND
              f.alert_phase >= '.ALERT_PHASE_YELLOW;
$this->log(MV_M_DATABASE, $sql_3);
if(!$l_st_uid = $this->db->query($sql_3)) {
    return $this->log(MV_M_DATABASE, 'Could not query stationated fleets user data! SKIP');
}

$at_user = array();

while($at_uid = $this->db->fetchrow($l_st_uid)) {

    $allied = false;

    if($at_uid['user_alliance'] == $user_attacker['user_alliance']) $allied = true;

    if(!empty($at_uid['ud_id'])) {
        if($at_uid['accepted'] == 1) $allied = true;
    }

    if(!empty($at_uid['ad_id'])) {
        if( ($at_uid['type'] == ALLIANCE_DIPLOMACY_PACT) && ($at_uid['status'] == 0) ) $allied = true;
    }

    if($allied) $at_user[] = $at_uid['user_id'];

}


$a_st_user = count($at_user);
$this->log(MV_M_DATABASE,$a_st_user .'Gemeinsamer Angriff:::'.$zahl);
if($a_st_user > 0) {
    $sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
             FROM (ship_fleets f)
             INNER JOIN user u ON u.user_id = f.user_id
LEFT JOIN (scheduler_shipmovement ssm) ON ssm.move_id=f.move_id
             WHERE ssm.dest='.$this->move['dest'].' AND ssm.action_code!=31 AND ssm.action_code!=33 AND ssm.action_code!=34 AND ssm.action_code!=14 AND ssm.action_code!=22 AND ssm.action_code!=23 AND ssm.move_finish<='.$this->move['move_finish'].' AND ssm.move_status=0 AND f.move_id!=0 AND
			       (
                     (
                       f.user_id = '.$this->move['user_id'].' AND f.alert_phase >= '.ALERT_PHASE_YELLOW.'
                     )
                     OR
                     (
                       f.user_id IN ('.implode(',', $at_user).') AND
                       f.alert_phase >= '.ALERT_PHASE_YELLOW.'
                     )
                   ) ';
}
else {
$sql = 'SELECT '.$this->get_combat_query_fleet_columns().'
		FROM (ship_fleets f)
		INNER JOIN user u ON u.user_id = f.user_id
		JOIN (scheduler_shipmovement ssm) ON ssm.user_id=f.user_id
		WHERE ssm.dest='.$this->move['dest'].' AND ssm.action_code!=31 AND ssm.action_code!=33 AND ssm.action_code!=34 AND ssm.action_code!=14 AND ssm.action_code!=22 AND ssm.action_code!=23 AND ssm.move_finish<='.$this->move['move_finish'].' AND ssm.move_status=0 AND f.move_id!=0 AND
		f.fleet_id IN ('.$this->fleet_ids_str.')';
}

if(($atk_fleets = $this->db->queryrowset($sql)) === false) {
	return $this->log(MV_M_DATABASE, 'Could not query defenders fleets data! SKIP');
}

$atk_fleets_ids = array();
$zahl=0;
foreach($atk_fleets as $i => $cur_fleet) {
$zahl++;
$this->log(MV_M_DATABASE,'Flotte::'.$cur_fleet['fleet_id'].'::||:Alert:::'.$cur_fleet['alert_phase'].':::||:Name:::'.$cur_fleet['fleet_name']);
	$atk_fleets_ids[] = $cur_fleet['fleet_id'];
}
if($zahl<1)
{
	return MV_EXEC_ERROR;
}

// #############################################################################
// Daten der Verteidiger

$sql = 'SELECT DISTINCT f.user_id,
               u.user_alliance,
               ud.ud_id, ud.accepted,
               ad.ad_id, ad.type, ad.status
        FROM (ship_fleets f)
        INNER JOIN (user u) ON u.user_id = f.user_id
        LEFT JOIN (user_diplomacy ud) ON ( ( ud.user1_id = '.$this->dest['user_id'].' AND ud.user2_id = f.user_id ) OR ( ud.user1_id = f.user_id AND ud.user2_id = '.$this->dest['user_id'].' ) )
        LEFT JOIN (alliance_diplomacy ad) ON ( ( ad.alliance1_id = '.$this->dest['user_alliance'].' AND ad.alliance2_id = u.user_alliance) OR ( ad.alliance1_id = u.user_alliance AND ad.alliance2_id = '.$this->dest['user_alliance'].' ) )
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
             INNER JOIN (user u) ON u.user_id = f.user_id
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
$this->log(MV_M_DATABASE,'Flotte::'.$cur_fleet['fleet_id'].'::||:Alert:::'.$cur_fleet['alert_phase'].':::||:Name:::'.$cur_fleet['fleet_name']);
	$dfd_fleet_ids[] = $cur_fleet['fleet_id'];
}
$this->log('Test','Muschu<br>'.$zahl);
if($this->do_ship_combat(implode(',', $atk_fleets_ids), implode(',', $dfd_fleet_ids), MV_COMBAT_LEVEL_ORBITAL) == MV_EXEC_ERROR) {
	$this->log(MV_M_DATABASE, 'Move Action 41: Something went wrong with this fight!');
    return MV_EXEC_ERROR;
}


// #############################################################################
// Evtl. die übriggeblienen Schiffe des Angreifers in den Orbit

if($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) {
    $sql = 'UPDATE ship_fleets
            SET planet_id = '.$this->move['dest'].',
                move_id = 0
            WHERE fleet_id IN ('.$this->fleet_ids_str.')';

    if(!$this->db->query($sql)) {
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
    }

    // #############################################################################
    // 02/04/08 - AC: Retrieve player language
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

    switch($this->dest['language'])
    {
        case 'GER':
            $dfd_title = 'Schwerer Angriff auf '.$this->dest['planet_name'];
        break;
        case 'ITA':
            $dfd_title = 'Attacco fatale su '.$this->dest['planet_name'];
        break;
        default:
            $dfd_title = 'Fatal attack on '.$this->dest['planet_name'];
        break;
    }
}
else {
    // #############################################################################
    // 02/04/08 - AC: Retrieve player language
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

    switch($this->dest['language'])
    {
        case 'GER':
            $dfd_title = 'Verteidigung von '.$this->dest['planet_name'].' erfolgreich';
        break;
        case 'ITA':
            $dfd_title = 'Difesa di '.$this->dest['planet_name'].' riuscita';
        break;
        default:
            $dfd_title = 'Defence of '.$this->dest['planet_name'].' successful';
        break;
    }
}


// #############################################################################
// Logbuch schreiben

$log1_data = array(41, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log2_data = array(41, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_DEFENDER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_DEFENDER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets, $this->cmb[MV_CMB_KILLS_PLANETARY]);

$log1_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
$log2_data[10] = $this->cmb[MV_CMB_KILLS_EXT];

add_logbook_entry($this->move['user_id'], LOGBOOK_TACTICAL_2, $atk_title, $log1_data);
add_logbook_entry($this->dest['user_id'], LOGBOOK_TACTICAL_2, $dfd_title, $log2_data);

$log3_data = array(99, $this->move['user_id'], $this->move['start'], $this->start['planet_name'], $this->start['user_id'], $this->move['dest'], $this->dest['planet_name'], $this->dest['user_id'], MV_CMB_ATTACKER, ( ($this->cmb[MV_CMB_WINNER] == MV_CMB_ATTACKER) ? 1 : 0 ), 0,0, $atk_fleets, $dfd_fleets);
$log3_data[10] = $this->cmb[MV_CMB_KILLS_EXT];
if($a_st_user > 0) {
    $log3_data[2] = $this->move['dest'];
    $log3_data[3] = $this->dest['planet_name'];
    $log3_data[4] = $this->dest['user_id'];
    $log3_data[5] = 0;
    $log3_data[6] = 0;
    $log3_data[7] = 0;
    $log3_data[16] = 1;

    for($i = 0; $i < $a_st_user; ++$i) {
        // #############################################################################
        // 02/04/08 - AC: Retrieve player language
        $log_title = 'One of your allies attacked '.$this->dest['planet_name'];
        $sql = 'SELECT language FROM user WHERE user_id = '.$at_user[$i];
        if(!($lang = $this->db->queryrow($sql))) {
            $this->log(MV_M_DATABASE, 'Could not retrieve player language');
        }
        else
        {
            switch($lang['language'])
            {
                case 'GER':
                    $log_title = 'Mit Verbündeten bei '.$this->dest['planet_name'].' angegriffen';
                break;
                case 'ITA':
                    $log_title = 'Attacco alleato presso '.$this->dest['planet_name'];
                break;
            }
        }

        add_logbook_entry($at_user[$i], LOGBOOK_TACTICAL_2, $log_title, $log3_data);
    }
}
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
        // 02/04/08 - AC: Retrieve player language
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
                    $log_title = 'Verbündeten bei '.$this->dest['planet_name'].' verteidigt';
                break;
                case 'ITA':
                    $log_title = 'Difesa alleata presso '.$this->dest['planet_name'];
                break;
            }
        }

        add_logbook_entry($st_user[$i], LOGBOOK_TACTICAL_2, $log_title, $log2_data);
    }
}

//
if(!$aad = $this->db->query($sql_3)) {
    return $this->log(MV_M_DATABASE, 'Could not query stationated fleets user data! SKIP');
}

while($aa_uid = $this->db->fetchrow($aad))
{
$this->log(MV_M_DATABASE, '::::'.$aa_uid['move_id'].'Now we delete:'.$this->move['user_id'].'<br>');
if($aa_uid['move_id']!=$this->move['user_id'])
{
      /*  $sql = 'UPDATE scheduler_shipmovement
                SET move_exec_started = move_exec_started + 1
                WHERE move_id = '.$this->mid;*/

     /*   if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update move exec started data! SKIP');;
        } */
$sql = 'UPDATE ship_fleets
            SET planet_id = '.$aa_uid['dest'].',
                move_id = 0
            WHERE fleet_id ='.$aa_uid['fleet_id'].'';
    if(!$this->db->query($sql)) {
        return $this->log(MV_M_DATABASE, 'Could not update fleets location data! SKIP');
    }
$sql = 'UPDATE scheduler_shipmovement
                    SET move_status = 11
                    WHERE move_id = '.$aa_uid['move_id'].'';

            if(!$this->db->query($sql)) {
                $this->log(MV_M_DATABASE, 'Could not update move status data! CONTINUE');
            }
//
}
}
$this->log('Test','muku<br>'.$zahl);
return MV_EXEC_OK;

    }
}

?>
