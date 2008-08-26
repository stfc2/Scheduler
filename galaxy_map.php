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

include($game_path . 'include/sql.php');
include($game_path . 'include/libs/maps.php');
include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/text_races.php');
include($game_path . 'include/race_data.php');
include($game_path . 'include/ship_data.php');
include($game_path . 'include/libs/moves.php');

$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection
$game = new game();


$maps = new maps();  
$maps->create_galaxy_detail_map();

$destimg=ImageCreateTrueColor(150,150) or die("Problem In Creating image");
$srcimg=ImageCreateFromPNG($game_path . 'maps/images/galaxy_detail.png') or die("Problem In opening Source Image");
ImageCopyResampled($destimg,$srcimg,0,0,0,0,150,159,ImageSX($srcimg),ImageSY($srcimg)) or die("Problem In resizing");
imagepng ($destimg,$game_path . 'maps/images/galaxy_detail_small.png');
imagedestroy($destimg);
imagedestroy($srcimg);


?>
