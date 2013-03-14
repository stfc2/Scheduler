<?php
/*
    This file is part of STFC.it
    Copyright 2008-2013 by Andrea Carolfi (info@stfc.it) and Cristiano Delogu

    STFC.it is based on STFC
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

// #######################################################################################
// #######################################################################################
// Startup Config

// include game definitions, path url and so on
include('config.script.php');

// include commons classes and functions
include('commons.php');
include($game_path . 'include/global.php');
include($game_path . 'include/functions.php');
include($game_path . 'include/libs/maps.php');

define('TICK_LOG_FILE', $game_path . 'logs/daily.log');
define('IN_SCHEDULER', true); // we are in the scheduler...

error_reporting(E_ERROR);

if(!empty($_SERVER['SERVER_SOFTWARE'])) {
    echo 'The scheduler can only be called by CLI!'; exit;
}


// #######################################################################################
// #######################################################################################
// Init

$starttime = ( microtime() + time() );

// create logging facility
$sdl = new scheduler();

// create sql-object for db-connection
$db = new sql($config['server'].":".$config['port'],
              $config['game_database'],
              $config['user'],
              $config['password']);

$game = new game();

$sdl->log('<br><br><br><b>-------------------------------------------------------------</b><br>'.
          '<b>Starting Daily script at '.date('d.m.y H:i:s', time()).'</b>');


// #######################################################################################
// #######################################################################################
// Reset New Registration Count

$sdl->start_job('Reset New Registration Count');

if(!$db->query('UPDATE config SET new_register = 0'))
    $sdl->log('<b>Error:</b> cannot reset new registration count!');

$sdl->finish_job('Reset New Registration Count');



// #######################################################################################
// #######################################################################################
// Check sitting abuse and lock'em

$sdl->start_job('Sitting abuse check');

$sql = 'SELECT * FROM user
        WHERE (num_sitting/(num_hits+1))>0.35 AND
              (num_sitting>50 OR (num_hits<10 AND num_sitting>30))';

if(($result = $db->query($sql)) === false) {
    $sdl->log('<b>Error:</b> cannot select user data!');
}
else {
    $db->query('UPDATE user SET num_hits=0, num_sitting=0');

    if(!$db->query('UPDATE user SET num_hits=0, num_sitting=0'))
        $sdl->log('<b>Error:</b> cannot reset sitting information!');

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

        $sql = 'UPDATE user SET num_sitting=-1 WHERE user_id='.$user['user_id'];

        if(!$db->query($sql))
            $sdl->log('<b>Error:</b> cannot lock sitting for user ID '.$user['user_id'].'!');

        $sdl->log('User sitting locked: ID '.$user['user_id'].' Name: '.$user['user_name'].' Abuse: '.$val);
    }
}

$sdl->finish_job('Sitting abuse check');



// #######################################################################################
// #######################################################################################
// Create or update galaxy map image

$sdl->start_job('Create galaxy map');

$maps = new maps();  
$maps->create_galaxy_detail_map();

if (($destimg = ImageCreateTrueColor(150,150)) === false) {
    $sdl->log('<b>Error:</b> problem creating image');
}
else {
    if(($srcimg = ImageCreateFromPNG($game_path . 'maps/images/galaxy_detail.png')) === false) {
        $sdl->log('<b>Error:</b> problem opening source image');
    }
    else {
        if(!ImageCopyResampled($destimg,$srcimg,0,0,0,0,150,159,
            ImageSX($srcimg),ImageSY($srcimg))) {
            $sdl->log('<b>Error:</b> problem resizing image');
        }
        else {
            imagepng ($destimg,$game_path . 'maps/images/galaxy_detail_small.png');
        }
        imagedestroy($srcimg);
    }
    imagedestroy($destimg);
}

$sdl->finish_job('Create galaxy map');



// #######################################################################################
// #######################################################################################
// Keep light the DB: delete old fleets movements!
$sdl->start_job('Slimming ship movements');

if(($cfg_data = $db->queryrow('SELECT * FROM config')) === false) {
    $sdl->log('<b>Error:</b> cannot query tick data!');
}
else {
    $ACTUAL_TICK = $cfg_data['tick_id'];

    if(!$cfg_data['tick_stopped']) {
        $oneWeek = (7 * 24 * 60 * 60);
        $time = $ACTUAL_TICK - ($oneWeek / (TICK_DURATION * 60));

        // Delete one week old deactivated moves
        $sql = 'DELETE FROM scheduler_shipmovement
                WHERE move_finish <= '.$time.' AND
                      move_status <> 0 AND
                      move_exec_started <> 0';

        if(!$db->query($sql)) {
            $sdl->log('<b>Error:</b> cannot delete scheduler_shipmovement data!');
        }
    }
    else
        $sql->log('Tick has been stopped (Unlock in table "config")');
}
$sdl->finish_job('Slimming ship movements');



// #######################################################################################
// #######################################################################################
// Keep optimized tables with frequent deletions
$sdl->start_job('Optimize tables');

$sql = 'OPTIMIZE TABLE `FHB_cache_trupp_trade`,
                       `FHB_warteschlange`,
                       `logbook`,
                       `message`,
                       `planets`,
                       `scheduler_instbuild`,
                       `scheduler_research`,
                       `scheduler_resourcetrade`,
                       `scheduler_shipbuild`,
                       `scheduler_shipmovement`,
                       `ships`,
                       `ship_fleets`,
                       `starsystems_slots`,
                       `user`,
                       `user_diplomacy`';

if(!$db->query($sql)) {
    $sdl->log('<b>Error:</b> cannot optimize frequently shrunk tables!');
}

$sdl->finish_job('Optimize tables');



// #######################################################################################
// #######################################################################################
// Compress yesterday's log files
$sdl->start_job('Compress log files');

// Yesterday date formatted
$yesterday = date('d-m-Y', strtotime("yesterday"));

// Names of the files we are compressing
$files = array(
    $game_path."logs/tick_".$yesterday.".log",
    $game_path."logs/moves_tick_".$yesterday.".log",
    $game_path."logs/NPC_BOT_tick_".$yesterday.".log",
    $game_path."logs/fixall/tick_".$yesterday.".log",
    $game_path."logs/sixhours/tick_".$yesterday.".log"
    );

foreach ($files as $key => $file) {
    //$sdl->log("Compressing file ".$file."...");

    // Name of the gz file we are creating
    $gzfile = $file.".gz";

    // Open the gz file (w9 is the highest compression)
    if(($fp = gzopen ($gzfile, 'w9')) === false)
        $sdl->log('<b>Error:</b> cannot create compressed file '.$gzfile);
    else {
        // Compress the file
        gzwrite ($fp, file_get_contents($file));

        // Close the gz file and we are done
        gzclose($fp);
    }
}
$sdl->finish_job('Compress log files');



// #######################################################################################
// #######################################################################################
// Clean temporary security images
$sdl->start_job('Clean temporary security images');
array_map('unlink', glob($game_path."tmpsec/*.jpg"));
$sdl->finish_job('Clean temporary security images');



// #######################################################################################
// #######################################################################################
// Once a month rotate admin end error HTML log files
$today = getdate();
if ($today['mday'] == 1) {
    $sdl->start_job('Rotate log files');

    // Prepare files postfix
    $date = new DateTime('NOW');
    $date->modify('-1 day');
    $postfix = $date->format('_m_y');

    $oldnames = array(
        $game_path.'logs/admin_log.htm',
        $game_path.'logs/error_log.htm'
    );

    $newnames = array(
        $game_path.'logs/admin_log'.$postfix.'.htm',
        $game_path.'logs/error_log'.$postfix.'.htm'
    );

    foreach ($oldnames as $i => $oldname) {
        $newname = $newnames[$i];

        if (!rename ($oldname, $newname)) {
            $sdl->log('<b>Error:</b> cannot rename file '.$oldname.' into '.$newname);
        }

        if (!touch($oldname)) {
            $sdl->log('<b>Error:</b> cannot create new empty  file '.$oldname);
        }
    }
    $sdl->finish_job('Rotate log files');
}



// #######################################################################################
// #######################################################################################
// Quit and close log

$db->close();
$sdl->log('<b>Finished Daily script in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$db->i_query.'</font></b>');


?>
