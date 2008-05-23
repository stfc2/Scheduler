<?PHP
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

// include game definitions, path url and so on
include('config.script.php');


// Combat Functions



function mlog($foo1=0,$foo2=0) {}



function newlog($message,$message2,$foo=0)
{
    $fp = fopen(TICK_LOG_FILE, 'a');
        fwrite($fp, $message." )<b>".$foo."</b> ".$message2."\n");
        echo str_replace('\n','<br>',$message." ".$message2."\n");
        fclose($fp);	
}


define('CWIN_ATTACKER', 0);

define('CWIN_DEFENDER', 1);





function limit($val, $max) {

    return ($val > $max) ? $max : $val;

}





function ShipFight($attacking_ships,$defending_ships,$move_id,$num_orbitaldefense=0,$num_smallorbitaldefense=0, $planet_owner=0, $all_orbital = false)

{

global $UNIT_DATA,$SHIP_DATA,$SHIP_TORSO;



$start_time = (time() + microtime());


$overview['attacker']=$attacking_ships['num_ships']=count($attacking_ships);
$overview['defender']=$defending_ships['num_ships']=count($defending_ships);





$add[0]=16;
$add[1]=31;
$add[2]=46;
$add[3]=0;
$add[4]=0;
$add[5]=0;
$add[6]=0;
$add[7]=46;
$add[8]=0;
$add[9]=0;



if (!$all_orbital)
{
if ($num_orbitaldefense>20) $num_orbitaldefense/=2;
else if ($num_orbitaldefense>10) $num_orbitaldefense=10;
if ($num_smallorbitaldefense>20) $num_smallorbitaldefense/=2;
else if ($num_smallorbitaldefense>10) $num_smallorbitaldefense=10;
}



$optimal=array(0.05,0.25,0.55,0.25);

$atk_classes=array();

// Ship-rank informations:

$ship_ranks = array(
0=>0,
1=>10,
2=>50,
3=>60,
4=>70,
5=>80,
6=>90,
7=>99,
8=>100,
9=>101,
);



$ship_rank_bonus = array(
0=>0,
1=>.02,
2=>.05,
3=>.08,
4=>.12,
5=>.16,
6=>.20,
7=>.24,
8=>.28,
9=>.32,
);



// Angreifer Bonus berechnen:
$bonus_attacker=0;


if($attacking_ships['num_ships']<7500)
{
	for ($t=0; $t<$attacking_ships['num_ships']; $t++)
	
	{
	
	$atk_classes[$attacking_ships[$t]['ship_class']]++;
	
	}
	
	$diff =abs($atk_classes[0]-$attacking_ships['num_ships']*$optimal[0]);
	
	$diff+=abs($atk_classes[1]-$attacking_ships['num_ships']*$optimal[1]);
	
	$diff+=abs($atk_classes[2]-$attacking_ships['num_ships']*$optimal[2]);
	
	$diff+=abs($atk_classes[3]-$attacking_ships['num_ships']*$optimal[3]);
	
	$bonus_attacker=0.25-(0.5/$attacking_ships['num_ships']*$diff);
}


if ($bonus_attacker<0) $bonus_attacker=0;



$dfd_classes=array();

// Verteidiger Bonus berechnen:
$bonus_defender=0;


if($defending_ships['num_ships']<7500)
{
	for ($t=0; $t<$defending_ships['num_ships']; $t++)
	{
	$dfd_classes[$defending_ships[$t]['ship_class']]++;
	}
	
	$diff =abs($dfd_classes[0]-$defending_ships['num_ships']*$optimal[0]);
	
	$diff+=abs($dfd_classes[1]-$defending_ships['num_ships']*$optimal[1]);
	
	$diff+=abs($dfd_classes[2]-$defending_ships['num_ships']*$optimal[2]);
	
	$diff+=abs($dfd_classes[3]-$defending_ships['num_ships']*$optimal[3]);
	
	$bonus_defender=0.25-(0.5/$defending_ships['num_ships']*$diff);
	
	if ($bonus_defender<0) $bonus_defender=0;
}


$bonus_attacker++;

$bonus_defender++;






if($attacking_ships['num_ships']<7500)
for ($t=0; $t<$attacking_ships['num_ships']; $t++)

{

$rank_nr=1;

if ($attacking_ships[$t]['experience']>=$ship_ranks[0]) $rank_nr=1;

if ($attacking_ships[$t]['experience']>=$ship_ranks[1]) $rank_nr=2;

if ($attacking_ships[$t]['experience']>=$ship_ranks[2]) $rank_nr=3;

if ($attacking_ships[$t]['experience']>=$ship_ranks[3]) $rank_nr=4;

if ($attacking_ships[$t]['experience']>=$ship_ranks[4]) $rank_nr=5;

if ($attacking_ships[$t]['experience']>=$ship_ranks[5]) $rank_nr=6;

if ($attacking_ships[$t]['experience']>=$ship_ranks[6]) $rank_nr=7;

if ($attacking_ships[$t]['experience']>=$ship_ranks[7]) $rank_nr=8;

if ($attacking_ships[$t]['experience']>=$ship_ranks[8]) $rank_nr=9;

if ($attacking_ships[$t]['experience']>=$ship_ranks[9]) $rank_nr=10;

$attacking_ships[$t]['value_1']*=($ship_rank_bonus[$rank_nr-1]+$bonus_attacker);

$attacking_ships[$t]['value_2']*=($ship_rank_bonus[$rank_nr-1]+$bonus_attacker);

$attacking_ships[$t]['value_3']*=($ship_rank_bonus[$rank_nr-1]+$bonus_attacker);

$attacking_ships[$t]['value_6']*=($ship_rank_bonus[$rank_nr-1]+$bonus_attacker);

$attacking_ships[$t]['value_7']*=($ship_rank_bonus[$rank_nr-1]+$bonus_attacker);

$attacking_ships[$t]['value_8']*=($ship_rank_bonus[$rank_nr-1]+$bonus_attacker);

}



if($defending_ships['num_ships']<7500)
for ($t=0; $t<$defending_ships['num_ships']; $t++)
{
$rank_nr=1;

if ($defending_ships[$t]['experience']>=$ship_ranks[0]) $rank_nr=1;

if ($defending_ships[$t]['experience']>=$ship_ranks[1]) $rank_nr=2;

if ($defending_ships[$t]['experience']>=$ship_ranks[2]) $rank_nr=3;

if ($defending_ships[$t]['experience']>=$ship_ranks[3]) $rank_nr=4;

if ($defending_ships[$t]['experience']>=$ship_ranks[4]) $rank_nr=5;

if ($defending_ships[$t]['experience']>=$ship_ranks[5]) $rank_nr=6;

if ($defending_ships[$t]['experience']>=$ship_ranks[6]) $rank_nr=7;

if ($defending_ships[$t]['experience']>=$ship_ranks[7]) $rank_nr=8;

if ($defending_ships[$t]['experience']>=$ship_ranks[8]) $rank_nr=9;

if ($defending_ships[$t]['experience']>=$ship_ranks[9]) $rank_nr=10;

$defending_ships[$t]['value_1']*=($ship_rank_bonus[$rank_nr-1]+$bonus_defender);

$defending_ships[$t]['value_2']*=($ship_rank_bonus[$rank_nr-1]+$bonus_defender);

$defending_ships[$t]['value_3']*=($ship_rank_bonus[$rank_nr-1]+$bonus_defender);

$defending_ships[$t]['value_6']*=($ship_rank_bonus[$rank_nr-1]+$bonus_defender);

$defending_ships[$t]['value_7']*=($ship_rank_bonus[$rank_nr-1]+$bonus_defender);

$defending_ships[$t]['value_8']*=($ship_rank_bonus[$rank_nr-1]+$bonus_defender);

}







// Erstschlag berechnen:

// Sensoren*0.5 + Reaktion*2 + Bereitschaft*3 + Wendigkeit + Tarnung d. eig. Schiffs

// + rand(0,5) bei att, rand(0,7) bei def



for ($t=0; $t<$attacking_ships['num_ships']; $t++)

{

$firststrike=$attacking_ships[$t]['value_11']*0.5+$attacking_ships[$t]['value_6']*2+$attacking_ships[$t]['value_7']*3+$attacking_ships[$t]['value_8']+$attacking_ships[$t]['value_12'];

if ($firststrike<1) $firststrike=1;
$firststrike*=(1+($attacking_ships[$t]['experience']/5000));
$attacking_ships[$t]['firststrike']=$firststrike;
$attacking_ships[$t]['shields']=$attacking_ships[$t]['value_4'];
$attacking_ships[$t]['shields']=$attacking_ships[$t]['value_4'];
if ($attacking_ships[$t]['experience']<10) $attacking_ships[$t]['experience']=10;
if ($attacking_ships[$t]['experience']<10) $attacking_ships[$t]['experience']=10;
}




for ($t=0; $t<$defending_ships['num_ships']; $t++)
{

$firststrike=$defending_ships[$t]['value_11']*0.5+$defending_ships[$t]['value_6']*2+$defending_ships[$t]['value_7']*3+$defending_ships[$t]['value_8']+$defending_ships[$t]['value_12'];
if ($firststrike<1) $firststrike=1;
$firststrike*=(1+($defending_ships[$t]['experience']/5000));
$defending_ships[$t]['firststrike']=$firststrike;
$defending_ships[$t]['shields']=$defending_ships[$t]['value_4'];
$defending_ships[$t]['shields']=$defending_ships[$t]['value_4'];
if ($defending_ships[$t]['experience']<10) $defending_ships[$t]['experience']=10;
if ($defending_ships[$t]['experience']<10) $defending_ships[$t]['experience']=10;
}



if ((time() + microtime())-$start_time>10)
{
newlog('Moves [NewCombat]', 'Run to shipconfigure-1 done within '.((time() + microtime())-$start_time).' seconds', $move_id);
}



// Now merge the 2 arrays:

unset($shipidlist);

for ($t=0; $t<$attacking_ships['num_ships']; $t++)
{
$attacking_overview[$attacking_ships[$t]['name']][0]++;
$attacking_overview[$attacking_ships[$t]['name']][1]=$attacking_ships[$t]['ship_torso'];


$tmpship['ship_id']=count($shipidlist)+1;
$shipidlist[]=$attacking_ships[$t]['ship_id'];
$shipexperiencelist[]=$attacking_ships[$t]['experience'];
$shipownerlist[]=$attacking_ships[$t]['user_id'];
$shiphplist[]=$attacking_ships[$t]['hitpoints'];

$tmpship['party']=0;
$tmpship['ship_torso']=$attacking_ships[$t]['ship_torso'];
$tmpship['experience']=$attacking_ships[$t]['experience'];
$tmpship['hitpoints']=$attacking_ships[$t]['hitpoints'];
$tmpship['value_1']=$attacking_ships[$t]['value_1'];
$tmpship['value_2']=$attacking_ships[$t]['value_2'];
$tmpship['value_6']=$attacking_ships[$t]['value_6'];
$tmpship['value_7']=$attacking_ships[$t]['value_7'];
$tmpship['value_8']=$attacking_ships[$t]['value_8'];
$tmpship['value_11']=$attacking_ships[$t]['value_11'];
$tmpship['value_12']=$attacking_ships[$t]['value_12'];
$tmpship['value_4']=$attacking_ships[$t]['value_4'];
$tmpship['firststrike']=$attacking_ships[$t]['firststrike'];
$ships[$tmpship['ship_id']]=$tmpship;
}




for ($t=0; $t<$defending_ships['num_ships']; $t++)
{
$defending_overview[$defending_ships[$t]['name']][0]++;
$defending_overview[$defending_ships[$t]['name']][1]=$defending_ships[$t]['ship_torso'];


$tmpship['ship_id']=count($shipidlist)+1;
$shipidlist[]=$defending_ships[$t]['ship_id'];
$shipexperiencelist[]=$defending_ships[$t]['experience'];
$shipownerlist[]=$defending_ships[$t]['user_id'];
$shiphplist[]=$defending_ships[$t]['hitpoints'];

$tmpship['party']=1;
$tmpship['ship_torso']=$defending_ships[$t]['ship_torso'];
$tmpship['experience']=$defending_ships[$t]['experience'];
$tmpship['hitpoints']=$defending_ships[$t]['hitpoints'];
$tmpship['value_1']=$defending_ships[$t]['value_1'];
$tmpship['value_2']=$defending_ships[$t]['value_2'];
$tmpship['value_6']=$defending_ships[$t]['value_6'];
$tmpship['value_7']=$defending_ships[$t]['value_7'];
$tmpship['value_8']=$defending_ships[$t]['value_8'];
$tmpship['value_11']=$defending_ships[$t]['value_11'];
$tmpship['value_12']=$defending_ships[$t]['value_12'];
$tmpship['value_4']=$defending_ships[$t]['value_4'];
$tmpship['firststrike']=$defending_ships[$t]['firststrike'];
$ships[$tmpship['ship_id']]=$tmpship;
}




if ((time() + microtime())-$start_time>10)
{
newlog('Moves [NewCombat]', 'Run to shipconfigure-2 done within '.((time() + microtime())-$start_time).' seconds', $move_id);
}



// Schiffsauflistung erstellen:
$text.='<u>Attacking ships:</u><br>';
foreach ($attacking_overview as $key => $overview)
{
$text.=$key.' (Hull: '.($overview[1]+1).'): <b>'.$overview[0].'x</b><br>';
}

$text.='<br><u>Defending ships:</u><br>';

if ($num_orbitaldefense>0) $text.='Orbital cannon (station): <b>'.round($num_orbitaldefense).'x</b><br>';
if ($num_smallorbitaldefense>0) $text.='L. Orbital cannon (station): <b>'.round($num_smallorbitaldefense).'x</b><br>';

foreach ($defending_overview as $key => $overview)
{
$text.=$key.' (Hull: '.($overview[1]+1).'): <b>'.$overview[0].'x</b><br>';
}



$orbital_defense['ship_id']=0;
$orbital_defense['party']=1;
$orbital_defense['ship_torso']=4;
$orbital_defense['experience']=10;
$orbital_defense['hitpoints']=PLANETARY_DEFENSE_DEFENSE;
$orbital_defense['value_1']=PLANETARY_DEFENSE_ATTACK;
$orbital_defense['value_2']=PLANETARY_DEFENSE_ATTACK2;
$orbital_defense['value_6']=20;
$orbital_defense['value_7']=20;
$orbital_defense['value_8']=20;
$orbital_defense['value_11']=5;
$orbital_defense['value_12']=0;
$orbital_defense['value_4']=0;
$orbital_defense['firststrike']=100;


$orbital_defense2['ship_id']=0;
$orbital_defense2['party']=1;
$orbital_defense2['ship_torso']=4;
$orbital_defense2['experience']=10;
$orbital_defense2['hitpoints']=SPLANETARY_DEFENSE_DEFENSE;
$orbital_defense2['value_1']=SPLANETARY_DEFENSE_ATTACK;
$orbital_defense2['value_2']=SPLANETARY_DEFENSE_ATTACK2;
$orbital_defense2['value_6']=20;
$orbital_defense2['value_7']=20;
$orbital_defense2['value_8']=20;
$orbital_defense2['value_11']=5;
$orbital_defense2['value_12']=0;
$orbital_defense2['value_4']=0;
$orbital_defense2['firststrike']=100;




for ($t=0; $t<$num_orbitaldefense; $t++)
{
$orbital_defense['ship_id']=$t+90000;
$ships[]=$orbital_defense;
}


for ($t=0; $t<$num_smallorbitaldefense; $t++)
{
$orbital_defense2['ship_id']=$t+90100;
$ships[]=$orbital_defense2;
}


unset($attacking_ships);
unset($defending_ships);



newlog('Moves [NewCombat]', 'Prepared '.count($ships).' ships including '.$num_orbitaldefense.'+'.$num_smallorbitaldefense.' orbital platforms', $move_id);
if ((time() + microtime())-$start_time>10)
{
newlog('Moves [NewCombat]', 'Prepared '.count($ships).' ships including '.$num_orbitaldefense.'+'.$num_smallorbitaldefense.' orbital platforms in '.((time() + microtime())-$start_time).' seconds', $move_id);
$stime = (time() + microtime());
}

$randtime = (time() + microtime());

// In besseren Zeiten war geplant, die Schiffe zu sortieren, nicht zu mischen :/
shuffle($ships);
newlog('Moves [NewCombat]', 'shuffle data time: '.((time() + microtime()) - $randtime), $move_id);

$savetime = (time() + microtime());


$file=fopen($script_path.'stgc-fightmodule/combat.data','w');
//$file2=fopen('fight_'.$move_id.'.data','w');
foreach ($ships as $key => $ship)
{

	$t++;
	$ships[$key][0]=$t;
	$ship[0]=$t;
	
	foreach ($ship as $key2 => $ship_sub)
	{
	$ship[$key2]=round($ship[$key2]);
	}

$imploded=implode(':',$ship).'
';

fwrite($file,$imploded);
//fwrite($file2,$imploded);

}

fclose($file);
//fclose($file2);
newlog('Moves [NewCombat]', 'transmit data time: '.((time() + microtime()) - $savetime), $move_id);



$cppstarttime = (time() + microtime());


exec($script_path.'stgc-fightmodule/newfight',$data);
newlog('Moves [NewCombat]', 'C++ calculation time: '.((time() + microtime()) - $cppstarttime), $move_id);
newlog('Moves [NewCombat]', $data[1].' has won!', $move_id);




$winner=$data[1];







$new_data=explode(':',$data[2]);

//!! Last new data is no real element!!! because c++ puts : at end of string
newlog('Moves [NewCombat]', 'Ship necessary updates (for Statistics): '.((count($new_data)-1)/4), $move_id);


$num_damaged=0;
$num_damaged_1=0;
$num_damaged_2=0;
$winner_victims=0;


$updates_temp=array();




$reviewstarttime = (time() + microtime());



// Kampfauswertung:

for ($t=0; $t<count($new_data)-1; $t+=4)
{

if ($new_data[$t+1]<90000) $new_data[$t+1]--;

	// Opfer z�hlen:
	if ($new_data[$t]==0) 
	{

		if ($new_data[$t+1]<90000)
		{
			if ($new_data[$t+2]==$data[1]) $winner_victims++;

			$victims[]=$shipidlist[$new_data[$t+1]];
			$victimtextlist.='ID'.$shipidlist[$new_data[$t+1]].',';
		}
		else
		{
			if ($new_data[$t+1]<90100) $lost_planetary++;
			else $lost_splanetary++;
		}
	$total_victims++;
	}

	// Erfahrung verteilen:
	else if ($new_data[$t]==2) 
	{
		if ($new_data[$t+1]<90000) 
		{
			$updates_temp[$shipidlist[$new_data[$t+1]]][0]=$new_data[$t+3];
			if (!isset($updates_temp[$shipidlist[$new_data[$t+1]]][1]))  $updates_temp[$shipidlist[$new_data[$t+1]]][1]=$shiphplist[$new_data[$t+1]];
			newlog('Moves [NewCombat]', 'Experience: '.$shipexperiencelist[$new_data[$t+1]].' --> '.$new_data[$t+3].' (id='.$shipidlist[$new_data[$t+1]].')',$move_id);

		}


	$update_honor[$shipownerlist[$new_data[$t+1]]]+=($new_data[$t+3]-$shipexperiencelist[$new_data[$t+1]])/10;
	$gained_exp++;
	}
	// Schiffsupdates registrieren:
	else 
	{
	if ($new_data[$t+1]<90000)
	{
	
	$updates_temp[$shipidlist[$new_data[$t+1]]][1]=(int)$new_data[$t+3];
	if (!isset($updates_temp[$shipidlist[$new_data[$t+1]]][0]))  $updates_temp[$shipidlist[$new_data[$t+1]]][0]=$shipexperiencelist[$new_data[$t+1]];
	
	if (((int)$new_data[$t+3])/$shiphplist[$new_data[$t+1]]<0.25) $num_damaged_2++;	
	else
	if (((int)$new_data[$t+3])/$shiphplist[$new_data[$t+1]]<0.5) $num_damaged_1++;	
	$num_damaged++;	
	}
	}
}



foreach ($updates_temp as $key => $updt)
{
if (!isset($updt[0])) {newlog('Moves [NewCombat]', 'Error: NO Experience found!!',$move_id); $updt[0]=1;}
if (!isset($updt[1])) {newlog('Moves [NewCombat]', 'Error: NO Hitpoints found!!',$move_id); $updt[1]=1;}
$update_ships[]=array($key,$updt[1],limit($updt[0],10000));
}


newlog('Moves [NewCombat]','We have '.count($updates_temp).' updates; '.$gained_exp.' gained exp; '.$num_damaged.' damaged and '.$total_victims.' victims (incl. planetary)',$move_id);
newlog('Moves [NewCombat]','Losses (ids with placed in front ID): '.$victimtextlist,$move_id);

$overview['attacker_alive']=0;
$overview['defender_alive']=0;

if ($winner==0) $overview['attacker_alive']=$overview['attacker']-$winner_victims;
if ($winner==1) $overview['defender_alive']=$overview['defender']-$winner_victims;





newlog('Moves [NewCombat]', 'evaluation time: '.((time() + microtime()) - $reviewstarttime), $move_id);





if ($winner==0) $text.='<br>The attacking ships have won the fight.<br><br>';
else $text.='<br>The defending ships have won the fight.<br><br>';
$text.='From the victorious ships became...<br>';
$text.='... <b>'.$winner_victims.'</b> destroyed<br>';
$text.='... <b>'.$num_damaged.'</b> damaged<br>';
$text.='&nbsp;&nbsp;&nbsp;&nbsp;Which <b>'.$num_damaged_1.'</b> strong<br>';
$text.='&nbsp;&nbsp;&nbsp;&nbsp;and <b>'.$num_damaged_2.'</b> very strong<br><br>';

if ($lost_planetary!=0) $text.='There were '.$lost_planetary.' Orbital Cannon destroyed.<br>';
if ($lost_splanetary!=0) $text.='There were '.$lost_splanetary.' Light Orbital Cannon destroyed.<br>';

$text.='<br>';



$total_time = (time() + microtime()) - $start_time;

newlog('Moves [NewCombat]', 'Time elapsed '.$total_time, $move_id);





return (array($winner,$overview,$victims,$text,$lost_planetary,$lost_splanetary,$update_ships,$update_honor));


}














function UnitFight($atk_units, $atk_race, $dfd_units, $dfd_race, $move_id)
{
global $RACE_DATA;
$atk_alive=$atk_units;
$dfd_alive=$dfd_units;


$total_dmg[0]=$atk_alive[0]*GetAttackUnit(0,$atk_race)+$atk_alive[1]*GetAttackUnit(1,$atk_race)+$atk_alive[2]*GetAttackUnit(2,$atk_race)+$atk_alive[3]*GetAttackUnit(3,$atk_race)+$RACE_DATA[$atk_race][21]*$atk_alive[4]*0.2;
$total_dmg[1]=$dfd_alive[0]*GetAttackUnit(0,$dfd_race)+$dfd_alive[1]*GetAttackUnit(1,$dfd_race)+$dfd_alive[2]*GetAttackUnit(2,$dfd_race)+$dfd_alive[3]*GetAttackUnit(3,$dfd_race)+$RACE_DATA[$dfd_race][21]*$dfd_alive[4];

$total_dfd[0]=$atk_alive[0]*GetDefenseUnit(0,$atk_race)+$atk_alive[1]*GetDefenseUnit(1,$atk_race)+$atk_alive[2]*GetDefenseUnit(2,$atk_race)+$atk_alive[3]*GetDefenseUnit(3,$atk_race)+$RACE_DATA[$atk_race][21]*$atk_alive[4]*0.25;
$total_dfd[1]=$dfd_alive[0]*GetDefenseUnit(0,$dfd_race)+$dfd_alive[1]*GetDefenseUnit(1,$dfd_race)+$dfd_alive[2]*GetDefenseUnit(2,$dfd_race)+$dfd_alive[3]*GetDefenseUnit(3,$dfd_race)+$RACE_DATA[$dfd_race][21]*$dfd_alive[4]*1.3;


if ($total_dmg[0]/$total_dfd[1]>$total_dmg[1]/$total_dfd[0])
{
// Attacker Wins:
$percent=$total_dfd[1]/$total_dmg[0];
$total_dmg[1]*=$percent;
// Dfd Dmg on Worker:
if ($total_dmg[1]>=$RACE_DATA[$atk_race][21]*2*$atk_alive[4]) {$total_dmg[1]-=$RACE_DATA[$atk_race][21]*2*$atk_alive[4]; $atk_alive[4]=0;}
else {$atk_alive[4]-=$total_dmg[1]/($RACE_DATA[$atk_race][21]*2); $total_dmg[1]=0;}

// Dfd Dmg:
for ($t=0; $t<4; $t++)
{
if ($total_dmg[1]<=0) break;
if ($total_dmg[1]>=GetDefenseUnit($t,$atk_race)*$atk_alive[$t]) {$total_dmg[1]-=GetDefenseUnit($t,$atk_race)*$atk_alive[$t]; $atk_alive[$t]=0;}
else {$atk_alive[$t]-=$total_dmg[1]/GetDefenseUnit($t,$atk_race); $total_dmg[1]=0; break;}
}

$dfd_alive=array(0,0,0,0,0);

}
else
{
$percent=$total_dmg[0]/$total_dmg[1];
$total_dmg[0]*=$percent;

// Atk Dmg on Worker:
if ($total_dmg[0]>=$RACE_DATA[$dfd_race][21]*2*$dfd_alive[4]) {$total_dmg[0]-=$RACE_DATA[$dfd_race][21]*2*$dfd_alive[4]; $dfd_alive[4]=0;}
else {$dfd_alive[4]-=$total_dmg[0]/($RACE_DATA[$dfd_race][21]*2); $total_dmg[0]=0;}

// Atk Dmg:
for ($t=0; $t<4; $t++)
{
if ($total_dmg[0]<=0) break;
if ($total_dmg[0]>=GetDefenseUnit($t,$dfd_race)*$dfd_alive[$t]) {$total_dmg[0]-=GetDefenseUnit($t,$dfd_race)*$dfd_alive[$t]; $dfd_alive[$t]=0;}
else {$dfd_alive[$t]-=$total_dmg[0]/GetDefenseUnit($t,$dfd_race); $total_dmg[0]=0; break;}
}

$atk_alive=array(0,0,0,0,0);

}


for ($t=0; $t<5; $t++)
{
if ($dfd_alive[$t]<0) $dfd_alive[$t]=0;
if ($atk_alive[$t]<0) $atk_alive[$t]=0;
if ($dfd_alive[$t]>$dfd_units[$t]) $dfd_alive[$t]=$dfd_units[$t];
if ($atk_alive[$t]>$atk_units[$t]) $atk_alive[$t]=$atk_units[$t];

$dfd_alive[$t]=round($dfd_alive[$t]);
$atk_alive[$t]=round($atk_alive[$t]);
}

return (array(0=>$atk_alive,1=>$dfd_alive));

}












function PlanetaryAttack($num_planetary,$planet,$focus=0,$destr_multiply=1)

{
$destr_multiply=1;
newlog('Moves [Combat]', 'Planetary Attack strength: '.$num_planetary.';  multiply: '.$destr_multiply, $move_id);

if ($num_planetary<=0) return $planet;



$num_destroy=$num_planetary/100*(1/$destr_multiply);
//Das Truppenbomben laut Tap nicht Truppenbomben nennen - sonst meint jeder das man das extra könnte - als das ein Problem 
//wäre....
newlog('Moves [Combat]', 'Planetary Attack num_destroy: '.$num_destroy, $move_id);

if ($num_destroy<1 && $num_destroy>0) if (rand(0,100)>$num_planetary) return $planet;

if ($num_destroy<=0) $num_destroy=1;

$num_destroy=round($num_destroy);
$num_pla = $num_planetary;

if ($num_pla > 5000){
 $num_pla = 5000;
}
 $dead_lv1=round($num_pla/(10+rand(0,40)));
 $dead_lv2=round($num_pla/(10+rand(0,60)));
 $dead_lv3=round($num_pla/(10+rand(0,100)));
 $dead_lv4=round($num_pla/(10+rand(0,40)));
 $dead_lv5=round($num_pla/(10+rand(0,40)));
 $dead_lv6=round($num_pla/(10+rand(0,40)));

 if($planet['unit_1']>$dead_lv1) { $planet['unit_1']-=$dead_lv1; } else { $planet['unit_1']=0; $dead_lv1=0; }
 if($planet['unit_2']>$dead_lv2) { $planet['unit_2']-=$dead_lv2; } else { $planet['unit_2']=0; $dead_lv2=0; }
 if($planet['unit_3']>$dead_lv3) { $planet['unit_3']-=$dead_lv3; } else { $planet['unit_3']=0; $dead_lv3=0; }
 if($planet['unit_4']>$dead_lv4) { $planet['unit_4']-=$dead_lv4; } else { $planet['unit_4']=0; $dead_lv4=0; }
 if($planet['unit_5']>$dead_lv5) { $planet['unit_5']-=$dead_lv5; } else { $planet['unit_5']=0; $dead_lv5=0; }
 if($planet['unit_6']>$dead_lv6) { $planet['unit_6']-=$dead_lv6; } else { $planet['unit_6']=0; $dead_lv6=0; }

 newlog('Moves [Combat]', 'Planetary Attack dead Units: LV1:'.$dead_lv1.'>>'.$planet['unit_1'], $move_id);
 newlog('Moves [Combat]', 'Planetary Attack dead Units: LV2:'.$dead_lv2.'>>'.$planet['unit_2'], $move_id);
 newlog('Moves [Combat]', 'Planetary Attack dead Units: LV3:'.$dead_lv3.'>>'.$planet['unit_3'], $move_id);
 newlog('Moves [Combat]', 'Planetary Attack dead Units: LV4:'.$dead_lv4.'>>'.$planet['unit_4'], $move_id);
 newlog('Moves [Combat]', 'Planetary Attack dead Units: LV5:'.$dead_lv5.'>>'.$planet['unit_5'], $move_id);
 newlog('Moves [Combat]', 'Planetary Attack dead Units: LV6:'.$dead_lv6.'>>'.$planet['unit_6'], $move_id);


(int)$dead_worker=round($num_planetary/(10+rand(0,40)));

if($planet['resource_4']>$dead_worker) { $planet['resource_4']-=$dead_worker; } else { $dead_worker=0; $planet['resource_4']=0; }

//$planet['dead_worker']=$dead_worker;
newlog('Moves [Combat]', 'Planetary Attack dead_worker: '.$dead_worker.'>><<'.$planet['resource_4'], $move_id);

if ($planet['resource_4']<0) $planet['resource_4']=0;
if ($planet['unit_1']<0) $planet['unit_1']=0;
if ($planet['unit_2']<0) $planet['unit_2']=0;
if ($planet['unit_3']<0) $planet['unit_3']=0;
if ($planet['unit_4']<0) $planet['unit_4']=0;
if ($planet['unit_5']<0) $planet['unit_5']=0;
if ($planet['unit_6']<0) $planet['unit_6']=0;



   /*0 => 'Hauptquartier',

        1 => 'Metallminen',

        2 => 'Mineralienminen',

        3 => 'Latinumraffinerie',

        +4 => 'Kraftwerk',

        5 => 'Akademie',

        +6 => 'Raumhafen',

        +7 => 'Schiffswerft',

        +8 => 'Forschungszentrum',

        +9 => 'Planet. Verteid.',

        +10 => 'Handelszentrum',

        +11 => 'Silos',*/





if ($focus==1)

{

for ($t=0; $t<12; $t++) {$destroy[]=$t;$destroy[]=$t;}

$destroy[]=1;

$destroy[]=1;

$destroy[]=2;

$destroy[]=2;

$destroy[]=3;

$destroy[]=3;

$destroy[]=11;

$destroy[]=11;

}

else if ($focus==2)

{

for ($t=0; $t<12; $t++) {$destroy[]=$t;$destroy[]=$t;}

$destroy[]=5;

$destroy[]=5;

$destroy[]=7;

$destroy[]=7;

$destroy[]=4;

$destroy[]=4;

}

else if ($focus==3)

{

for ($t=0; $t<12; $t++) {$destroy[]=$t;$destroy[]=$t;}

$destroy[]=0;

$destroy[]=0;

$destroy[]=8;

$destroy[]=8;

$destroy[]=10;

$destroy[]=10;

}

else

{

for ($t=0; $t<12; $t++) {$destroy[]=$t;$destroy[]=$t;$destroy[]=$t;}

}







for ($t=0; $t<$num_destroy; $t++)

{

$build=rand(0,count($destroy)-1);

if (($destroy[$build]+1)!=10)

{

$planet['building_'.($destroy[$build]+1)]--;

if ($planet['building_'.($destroy[$build]+1)]<0) $planet['building_'.($destroy[$build]+1)]=0;



}

}



if ($planet['building_1']<1) $planet['building_1']=1;

return $planet;

}



function Spy($spy_sensor,$spy_cloak,$spy_ships,$planet_sensor,$spacedock_lvl)
{

$cloak=(($spy_sensor/$spy_ships)*5+($spy_cloak/$spy_ships)*125)*10;

$sensor=$planet_sensor+($spacedock_lvl+1)*200; // 2000 is max. planet-standard



if ($cloak<=0) $cloak=1;

if ($sensor<=0) $sensor=1;



$chance_identify=round(100*($sensor/$cloak));

if ($chance_identify<5) $chance_identify=5;

if ($chance_identify>70) $chance_identify=70;	

$rval=rand(0,100);

$identified=0;

if ($rval<$chance_identify) $identified=1;



$sensor_chance=$spy_sensor/(8+3*$identified);

if ($sensor_chance>90) $sensor_chance=90;

if ($sensor_chance<5) $sensor_chance=5;





$id_res=array();

$id_unit=array();

$id_build=array();

$id_tech=array();

$id_tech2=array();



// identify resources:

for ($t=0; $t<4; $t++)

{

$id=0;

$rval=rand(0,100);

if ($rval<=$sensor_chance) $id=1;

if ($id) $id_res[]=$t;

}



// identify units:

for ($t=0; $t<6; $t++)

{

$id=0;

$rval=rand(0,100);

if ($rval<=$sensor_chance) $id=1;

if ($id) $id_unit[]=$t;

}



// identify buildings:

for ($t=0; $t<12; $t++)

{

$id=0;

$rval=rand(0,100);

if ($rval<=$sensor_chance) $id=1;

if ($id) $id_build[]=$t;

}



// identify tech1 = local research:

for ($t=0; $t<5; $t++)

{

$id=0;

$rval=rand(0,100);

if ($rval<=$sensor_chance) $id=1;

if ($id) $id_tech1[]=$t;

}



// identify tech2 = catresearch:

for ($t=0; $t<5; $t++)

{

$id=0;

$rval=rand(0,100);

if ($rval<=$sensor_chance) $id=1;

if ($id) $id_tech2[]=$t;

}

	

return (array(0=>$identified,1=>$id_res,2=>$id_unit,3=>$id_build,4=>$id_tech1,5=>$id_tech2));	

}

