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

$PLANETS_PER_QUADRANT = 10;

include($game_path . 'include/sql.php');
include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/libs/world.php');

$game = new game();
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

for($i = 0; $i < $PLANETS_PER_QUADRANT; $i++) {
    create_planet(0, 'quadrant', 1);
}

for($i = 0; $i < $PLANETS_PER_QUADRANT; $i++) {
    create_planet(0, 'quadrant', 2);
}

for($i = 0; $i < $PLANETS_PER_QUADRANT; $i++) {
    create_planet(0, 'quadrant', 3);
}

for($i = 0; $i < $PLANETS_PER_QUADRANT; $i++) {
    create_planet(0, 'quadrant', 4);
}


echo $PLANETS_PER_QUADRANT;

?>
