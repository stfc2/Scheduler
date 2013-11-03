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



error_reporting(E_ALL);
ini_set('memory_limit', '30M');
set_time_limit(180); // 3 minutes

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
	echo 'The scheduler can only be called by CLI!'; exit;

}


// include global definitions + functions + game-class + player-class
include($game_path . 'include/global.php');
include($game_path . 'include/sql.php');
// create sql-object for db-connection
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection


exec('cd '.$game_path.'sig_tmp/; rm -f *.jpg');

$rank_honor = array();
$rank_honor[0]=0;
$rank_honor[1]=25;
$rank_honor[2]=50;
$rank_honor[3]=150;
$rank_honor[4]=250;
$rank_honor[5]=400;
$rank_honor[6]=700;
$rank_honor[7]=1200;
$rank_honor[8]=2000;
$rank_honor[9]=5000;


$userqry=$db->query('SELECT u.user_name, u.user_planets, u.user_points,u.user_honor, u.user_race,u.user_rank_points,a.alliance_name, u.language FROM (user u) LEFT JOIN (alliance a) ON  a.alliance_id=u.user_alliance WHERE u.user_enable_sig=1 AND user_points>0');

$passed=$total=0;
while (($player_data=$db->fetchrow($userqry))==true)
{
	if(!isset($player_data['alliance_name'])) {$player_data['alliance_name']='-';}

	$rank_nr=1;
	if ($player_data['user_honor']>=$rank_honor[0]) $rank_nr=1;
	if ($player_data['user_honor']>=$rank_honor[1]) $rank_nr=2;
	if ($player_data['user_honor']>=$rank_honor[2]) $rank_nr=3;
	if ($player_data['user_honor']>=$rank_honor[3]) $rank_nr=4;
	if ($player_data['user_honor']>=$rank_honor[4]) $rank_nr=5;
	if ($player_data['user_honor']>=$rank_honor[5]) $rank_nr=6;
	if ($player_data['user_honor']>=$rank_honor[6]) $rank_nr=7;
	if ($player_data['user_honor']>=$rank_honor[7]) $rank_nr=8;
	if ($player_data['user_honor']>=$rank_honor[8]) $rank_nr=9;
	if ($player_data['user_honor']>=$rank_honor[9]) $rank_nr=10;

	$image = ImageCreateFromJPEG($game_path . "sig_gfx/sig_tpl.jpg");

	if ($player_data['user_race']==0)
	{
		switch($player_data['language'])
		{
			case 'GER':
				$user_race='Föderation';
			break;
			case 'ITA':
				$user_race='Federazione';
			break;
			default:
				$user_race='Federation';
			break;
		}
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/foederation.jpg");
	}
	if ($player_data['user_race']==1)
	{
		switch($player_data['language'])
		{
			case 'GER':
				$user_race='Romulaner';
			break;
			case 'ITA':
				$user_race='Romulani';
			break;
			default:
				$user_race='Romulan';
			break;
		}
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/romulaner.jpg");
	}
	if ($player_data['user_race']==2)
	{
		switch($player_data['language'])
		{
			case 'GER':
				$user_race='Klingone';
			break;
			case 'ITA':
				$user_race='Klingon';
			break;
			default:
				$user_race='Klingon';
			break;
		}
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/klingonen.jpg");
	}
	if ($player_data['user_race']==3)
	{
		switch($player_data['language'])
		{
			case 'GER':
				$user_race='Cardassianer';
			break;
			case 'ITA':
				$user_race='Cardassiani';
			break;
			default:
				$user_race='Cardassian';
			break;
		}
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/cardassia.jpg");
	}
	if ($player_data['user_race']==4)
	{
		switch($player_data['language'])
		{
			case 'GER':
				$user_race='Dominion';
			break;
			case 'ITA':
				$user_race='Dominio';
			break;
			default:
				$user_race='Dominium';
			break;
		}
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/dominion.jpg");
	}
	if ($player_data['user_race']==5)
	{
		$user_race='Ferengi';
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/ferengi.jpg");
	}
	if ($player_data['user_race']==8)
	{
		$user_race='Breen';
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/breen.jpg");
	}
	if ($player_data['user_race']==9)
	{
		switch($player_data['language'])
		{
			case 'GER':
				$user_race='Hirogen';
			break;
			case 'ITA':
				$user_race='Hirogeni';
			break;
			default:
				$user_race='Hirogen';
			break;
		}
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/hirogen.jpg");
	}
	if ($player_data['user_race']==10)
	{
		$user_race='Krenim';
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/krenim.jpg");
	}
	if ($player_data['user_race']==11)
	{
		$user_race='Kazon';
		$image = ImageCreateFromJPEG($game_path . "sig_gfx/kazon.jpg");
	}


	switch($player_data['language'])
	{
		case 'GER':
			$str_ally = 'Allianz: ';
			$str_points = 'Punkte: ';
			$str_planets = 'Planeten: ';
			$str_honor = 'Verdienst: ';
			$str_race = 'Rasse: ';
		break;
		case 'ITA':
			$str_ally = 'Alleanza: ';
			$str_points = 'Punti: ';
			$str_planets = 'Pianeti: ';
			$str_honor = 'Onore: ';
			$str_race = 'Specie: ';
		break;
		default:
			$str_ally = 'Alliance: ';
			$str_points = 'Points: ';
			$str_planets = 'Planets: ';
			$str_honor = 'Honor: ';
			$str_race = 'Race: ';
		break;
	}


	//$image = ImageCreateFromJPEG($game_path."sig_gfx/sig_tpl.jpg");
	//$image_rank = ImageCreateFromJPEG($game_path . 'sig_gfx/rank_'.$rank_nr.'.jpg');
	$color_1=imagecolorallocate($image,255,240,70);
	$color_2=imagecolorallocate($image,150,140,30);
	$color_3=imagecolorallocate($image,255,255,255);
	$color_4=imagecolorallocate($image,255,0,0);
	//imagestring ($image, 4,2,2,'Herrscher:', $color_1);
	//imagestring ($image, 5,2,1,$player_data['user_name'].' ('.$player_data['user_rank_points'].'.)', $color_2);
	imagestring ($image, 5,3,2,$player_data['user_name'].' ('.$player_data['user_rank_points'].'.)', $color_1);
	//imagestring ($image, 5,120,1,'Präsident', $color_1);
	imageline($image,0,19,195,19,$color_1);
	imagestring ($image, 3,2,25,$str_ally.$player_data['alliance_name'], $color_1);
	imagestring ($image, 3,2,35,$str_points.$player_data['user_points'], $color_1);
	imagestring ($image, 3,2,45,$str_planets.$player_data['user_planets'], $color_1);
	//ImageCopyResized($image,$image_rank,300,30,0,0,110,24,ImageSX($image_rank),ImageSY($image_rank));
	imagestring ($image, 3,120,35,$str_honor.$player_data['user_honor'], $color_1);
	imagestring ($image, 3,120,45,$str_race.$user_race, $color_1);
	//imagestring ($image, 3,270,45,'Präsident', $color_1);
	//imagestring ($image, 2,405,46,$game_url, $color_1);
	imagestring ($image, 2,370,46,$game_url, $color_1);
	//imagejpeg($image,$game_path . 'sig_tmp/'.strtolower($player_data['user_name']).'.jpg',60);
	imagejpeg($image,$game_path . 'sig_tmp/'.strtolower($player_data['user_name']).'.jpg',75); // Increase a bit the quality
}



?>
