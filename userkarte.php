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
error_reporting(E_ERROR);
ini_set('memory_limit', '200M');

include('|script_dir|/game/include/sql.php');
include('|script_dir|/game/include/global.php');
include('|script_dir|/game/include/functions.php');

// create sql-object for db-connection
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection


$game = new game();

if ( ($userlist = $db->queryrowset('SELECT user_name, plz, country FROM user WHERE user_active=1 AND plz>0')) === false) {die;}



$db = new sql($config['server'].":".$config['port'], $config['geodb_database'], $config['user'], $config['password']); // create sql-object for db-connection



//Range-Bereich  
$range_min['x']=5.8;  
$range_max['x']=17.2;  
$range_min['y']=45.8;  
$range_max['y']=55.1;  

// Größe der Karte in Pixel  
$karte_groesse_x=650;  
$karte_groesse_y=836;  



$num=0;
foreach ($userlist as $user)
{
	$sql="SELECT * FROM geodb_locations WHERE plz like '%".$user['plz']."%' AND adm0='".$user['country']."'  ORDER BY LENGTH(plz)";  
	$r=$db->queryrow($sql);
	if ($r['laenge']>0)
	{
		// Dann Geokoordinaten auf Bildgröße skalieren und absolute X,Y-Koordinaten speichern  
		$xpos= floor( ($r['laenge'] - $range_min['x']) * ($karte_groesse_x / ($range_max['x'] - $range_min['x']))/5);  
		$ypos= floor( ($r['breite'] - $range_max['y']) * ($karte_groesse_y / ($range_min['y'] - $range_max['y']))/5);  
		$daten[$xpos][$ypos][]=$user['user_name'].' '.$user['country'].' - '.$r['name'].' ('.$user['plz'].')';
		$num++;
	}
}
	
$image = ImageCreateFromPNG("|script_dir|/karte.png");
imageAlphaBlending($image, true);
imageSaveAlpha($image, true);
$color_1=imagecolorallocate($image,255,255,255);

imagestring ($image, 2,0,0,'www.stgc.de - '.gmdate('d.m.y H:i', (time() +TIME_OFFSET)).' - Fried Egg - '.$num.' Spieler', $color_1);


$map_data.='<map name="bbmap"> ';


foreach($daten as $xpos => $datensatz) {
   foreach ($datensatz as $ypos => $datensatz2) {
      
      $intensity=170+count($datensatz2)*10;
      if ($intensity>255) $intensity=255;
      $color=imagecolorallocate($image,$intensity,$intensity,$intensity);
		imagefilledellipse($image, $xpos*5, $ypos*5, 5,5, $color);
		$map_data.='<area shape="circle" coords="'.($xpos*5).','.($ypos*5).',5" onmouseover="return overlib(\'';
		foreach($datensatz2 as $name) $map_data.=$name.'<br>';
		$map_data.='\', CAPTION, \'Spieler:\', WIDTH, 300, FGCOLOR, \'#ffffff\', TEXTCOLOR, \'#ffffff\', FGBACKGROUND,\'|game_url|:82/stgc5_gfx/skin1/bg_stars1.gif\', BGCOLOR, \'#687b88\', BORDER, 2, CAPTIONFONT, \'Arial\', CAPTIONSIZE, 2, TEXTFONT, \'Arial\', TEXTSIZE, 2);" onmouseout="return nd();">
		';
	}
}

$map_data.='<img src="bbkarte.png" width='.$karte_groesse_x.' height='.$karte_groesse_y.' alt="Userkarte der Brown Bobby Galaxie" usemap="#bbmap" ismap>
';
        
$image_small = imagecreatetruecolor($karte_groesse_x/5,$karte_groesse_y/5);
imagealphablending($image_small, false);
imagesavealpha($image_small,true);
$transparent = imagecolorallocatealpha($image_small, 255, 255, 255, 127);
imagefilledrectangle($image_small, 0, 0, $karte_groesse_x/5,$karte_groesse_y/5, $transparent);
imagecopyresampled($image_small, $image, 0, 0, 0, 0, $karte_groesse_x/5, $karte_groesse_y/5, $karte_groesse_x, $karte_groesse_y);

imagepng($image_small,"|script_dir|/bbkarte_thumb.png");
imagepng($image,"|script_dir|/bbkarte.png");


$fp=fopen ('|script_dir|/bbkarte.htm','w');
fputs($fp,$map_data);
fclose($fp);


exit;

?>
