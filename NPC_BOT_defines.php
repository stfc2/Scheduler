<?php
/*
    This file is part of STFC.it
    Copyright 2008-2013 by Andrea Carolfi (carolfi@stfc.it) and
    Cristiano Delogu (delogu@stfc.it).

    STFC.it is based on STFC,
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

// Constants for StartBuild and Install common methods 
define('BUILD_SUCCESS',0);
define('BUILD_ERR_QUEUE', -1);
define('BUILD_ERR_RESOURCES', -2);
define('BUILD_ERR_REQUIRED', -3);
define('BUILD_ERR_ENERGY', -4);
define('BUILD_ERR_DB', -5);
define('BUILD_ERR_MAXLEVEL',-6);
define('INSTALL_LOG_FILE_NPC', $game_path.'logs/NPC_installation.log');


// Constant for Ramona Ferengi BOT
define('PICK_RESOURCES_FROM_PLANET',1); // 1 = remove resources from BOT's planet
                                        // 0 = left BOT's planet untouched

define('MALL_RESOURCES_AVAILABLE',0);   // 1 = resourses available at the Feregi's Mall
                                        // 0 = no resources available


// Constant for SevenOfNine Borg BOT
define ('BORG_SIGNATURE', 'We are the Borg. Lower your shields and surrender your ships.<br>We will add your biological and technological distinctiveness to our own.<br>Your culture will adapt to service us. Resistance is futile.');

define ('BORG_SPHERE', 'Borg Sphere');

define ('BORG_CUBE', 'Borg Cube');

define ('BORG_TACT', 'Borg Tact Cube');

define ('BORG_UM0', 'Borg Unimatrix 0');

define ('BORG_RACE','6'); // Well, this one should be defined in global.php among the other races */

define ('BORG_QUADRANT', 2); // Default Borg belong to Delta quadrant

define ('BORG_CYCLE', 1200); // One attack each how many tick?

define ('BORG_CHANCE', 80); // Attack is not sistematic, leave a little chance

define ('BORG_MINATTACK', 10); // Attack only players with at least n planets

define ('BORG_BIGPLAYER', 12000); // Send a cube instead of spheres to player above this points

define ('BORG_MAXPLANETS', 1); // Start to assimilate below this planets amount


// Constant for Mayflower Settlers BOT

?>
