<?php
/*    
    This file is part of STFC.it.
    Copyright 2008-2013 by Andrea Carolfi (info@stfc.it) and Cristiano Delogu

    STFC.it is based on STGC,
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


// ########################################################################################
// ########################################################################################
// Startup Config

// include game definitions, path url and so on
include('config.script.php');

// include functions and classes needed by the installation
include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/libs/world.php');

// include BOTs classes definitions
include('NPC_BOT.php');
include('ferengi.php');
include('memory_alpha.php');
include('settlers.php');

// include commons classes and functions
include('commons.php');

error_reporting(E_ERROR);

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'The scheduler can only be called by CLI!'; exit;
}


// ########################################################################################
// ########################################################################################
// Init

$starttime = ( microtime() + time() );

$sdl = new scheduler();

$sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
          '<b>Starting Install BOTs at '.date('d.m.y H:i:s', time()).'</b>',
    INSTALL_LOG_FILE_NPC);

// create sql-object for db-connection
$db = new sql($config['server'].":".$config['port'], $config['game_database'],
              $config['user'], $config['password']);

$game = new game();

// Install Quark BOT
$quark = new Ferengi($db,$sdl);
$quark->Install();

// Install SevenOfNine BOT
$borg = new MemoryAlpha($db,$sdl);
$borg->Install();

// Install Settlers BOT
$settlers = new Settlers($db,$sdl);
$settlers->Install();


// ########################################################################################
// ########################################################################################
// Quit and close log

$db->close();

$sdl->log('<b>Finished Install BOTs in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>',
    INSTALL_LOG_FILE_NPC);

?>

