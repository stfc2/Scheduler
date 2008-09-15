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
// include game definitions, path url and so on
include('config.script.php');
include($game_path . 'include/sql.php');
include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/libs/world.php');

$game = new game();
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

$result=$db->query('SELECT * FROM user WHERE (num_sitting/(num_hits+1))>0.35 AND (num_sitting>50 OR (num_hits<10 AND num_sitting>30))');
$db->query('UPDATE user SET num_hits=0, num_sitting=0');
while ($user=$db->fetchrow($result))
{
	/* 08/05/08 - AC: It seems that sometime 'num_hits' can be 0, check added */
	if($user['num_hits'] == 0) $user['num_hits'] = 1;

	$val=($user['num_sitting']+1)/$user['num_hits'];
	/* 24/04/08 - AC: Add language translation based on user language */
	switch($user['language'])
	{
		case 'GER':
			$text='fast ausschlie&szlig;lich gesittet';
			if ($val<0.8) $text='stark &uuml;berm&auml;&szlig;ig gesittet';
			if ($val<0.6) $text='&Uuml;berm&auml;&szlig;ig gesittet';
			if ($val<0.45) $text='etwas zuviel gesittet';
			$message='Hallo '.$user['user_name'].',<br>dein Account kann <b>ab jetzt</b> f&uuml;r <b>einen Tag</b> nicht gesittet werden, weil er innerhalb der letzten 24 Stunden <b>'.$text.'</b> wurde.<br>Diese Nachricht wurde automatisch generiert, Beschwerden beim STFC2-Team bringen nichts.<br>~ Sitting-Abuse-Automatik';
			$title='Sittingsperre';
		break;
		case 'ITA':
			$text='quasi esclusivamente abusato';
			if ($val<0.8) $text='fortemente abusato';
			if ($val<0.6) $text='eccessivamente abusato';
			if ($val<0.45) $text='leggermente abusato';
			$message='Ciao '.$user['user_name'].',<br>il tuo account <b>da adesso</b> per <b>un giorno</b> non pu&ograve; essere dato in sitting, perch&eacute; nelle ultime 24 ore &egrave; stato <b>'.$text.'</b>.<br>Questo messaggio &egrave; stato generato automaticamente, lamentele al team di STFC2 sono inutili.<br>~ Abuso Sitting Automatico';
			$title='Sitting bloccato';
		break;
		default:
			$text='almost exclusively sitted';
			if ($val<0.8) $text='very excessively sitted';
			if ($val<0.6) $text='excessively sitted';
			if ($val<0.45) $text='a little too sitted';
			$message='Hello '.$user['user_name'].',<br>your account <b>now</b> for <b>one day</b> can not be sitted, because within the last 24 hours it was <b>'.$text.'</ b>.<br>This message was automatically generated, complaints to the STFC2 team bring nothing.<br>~Automatic Abuse Sitting';
			$title='Sitting locked';
		break;
	}
	SystemMessage($user['user_id'],$title,$message);
	$db->query('UPDATE user SET num_sitting=-1 WHERE user_id='.$user['user_id']);
	echo 'User sitting locked: ID '.$user['user_id'].' Name: '.$user['user_name'].' Abuse: '.$val.'\n';
};




?>
