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


$sql = 'UPDATE planets
        SET planet_covered_distance = planet_covered_distance + planet_tick_cdistance
        WHERE planet_max_cdistance > (planet_covered_distance + planet_tick_cdistance)';
        
if(!$db->query($sql)) {
    $sdl->log('- <b>Error:</b> Could not update first planet rotation data! CONTINUE');
}

$sql = 'UPDATE planets
        SET planet_covered_distance = (planet_covered_distance + planet_tick_cdistance - planet_max_cdistance)
        WHERE planet_max_cdistance <= (planet_covered_distance + planet_tick_cdistance)';
        
if(!$db->query($sql)) {
    $sdl->log('- <b>Error:</b> Could not update second planet rotation data! CONTINUE');
}

$sql = 'UPDATE planets
        SET planet_current_x = 0,
            planet_current_y = 0';
            
if(!$db->query($sql)) {
    $sdl->log('- <b>Error:</b> Couldnot update planet current coord data! CONTINUE');
}

?>
