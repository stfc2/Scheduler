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


//########################################################################################
//########################################################################################
//Changelog sonst kapier ich bei Ramona bald nix mehr - Frauen eben

/* 14. Juni 2007
  @Thema: Truppenverkaufszahlen Graphenbrechnung
  @Aktion: geändert  bzw verbessert
*/
//########################################################################################
/**
* system: Applikation Klasse - Logging/Timing - Speicher - Debuggen
* @author  	secius und daywalker und stilgar
* @version  	2.3
* @package 	NPC -Scheduler
* @todo 	Mail an an Entwickler bei Error
* @todo		Abends eine Mail an Entwickler mit einer Debug Ausgabe / Log Ausgabe
* @todo 	Log, Error Log und Debug Log einfacher einsehbar
**/

class system {
	/**
    * 
    * @access public
    * @var int 
    */
	var $speicher_diff;
	/**
    * 
    * @access public
    * @var array
    */
	var $start_values = array();
	/**
    * 
    * @access public
    * @var int 
    */
	var $qu_z=0;
	/**
    * Fehler in den einzelnen Jobs
    * @access public
    * @var int 
    */
	var $fehler_job=0;
	/**
    * 
    * @access public
    * @var int 
    */
	var $fehler_gesamt=0;
	/**
    * Debug modus an oder aus 1 / 0
    * @access public
    * @var int 
    */
	var	$debug_logen=1;
	/**
    * Zeit beim anlegen des Objekts
    * @access public
    * @var int 
    */
	var $timegesamt=0;
	/**
    * Speicher beim anlegen des Objects
    * @access public
    * @var int 
    */
	var $speichergesamt=0;
	/**
    * für den Konstruktor
    * @access public
    * @var int 
    */
	var $tester=0;
	/**
    * Hintergrund der äußeren Tabelle
    * @access public
    * @var string
    */
	var $color="#fffff";
	/**
    * 0=log, 1=html ausgabe
    * @access public
    * @var int 
    */
	var $type=0;
	/**
    * Titel des Tests
    * @access public
    * @var string
    */
	var $title_var="";
	
	/**
 	* title: schreibt bei Debug on für die Debugdatei einen Title.
 	* @author     secius
 	* @version    1.0
  	*/
	var $debug_array_logen=0;
	var $debug_sql_logen=0;
	function title()
	{
		if($this->debug_logen==1)
		{
			$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
			$this->ausgabe($fp,'','','');
			if($this->type==1)
			{
				$style_1="<hr>";
			}
			$this->ausgabe($fp,'',$style_1,'');
			$this->ausgabe($fp,'','','');
			if($this->type==1)
			{
				$style_1="<table border=1 bgcolor='#24EBE2'><tr><td><font color=#0000ff><b>";
				$style_2="</b></font>";
				$style_3="<i>";
				$style_4="</i>";
			}
			$this->ausgabe($fp,$style_1,$style_3.'[TITLE]'.$style_4.'STFC - Ferengi Handelsbot -powered by Tobi'.$style_3.'[/TITLE]'.$style_4.'');
			$this->ausgabe('Titel des Test:'.$this->title_var.'');
		if($this->debug_logen==1)
		{
			$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
			$this->ausgabe($fp,'','***File:'.__FILE__);
			$this->ausgabe($fp,'','***PHP-Version:'.PHP_VERSION);
			$this->ausgabe($fp,'','***PHP-OS:'.PHP_OS);
			$zeit=TIME_OFFSET/3600;
			$this->ausgabe($fp,'','***Datum:'.date("d-m-Y--H:i:s").'');
			fclose($fp);
		}
		}
	}
	/**
  	* system: Standartkonstrukor, mit dem optinonalen parameter $debugger. Stellt zeit ein und bei Debug on macht er den kopf der Datei.
  	*
  	* @todo		Beschreibung der funktion auf 1.2 ändern
  	* @author   secius
  	* @version  1.2
  	* @param 	int $type 		(optional) 0=Log, 1=HTML - style
  	* @param    int $debugger   (optional) 0=Kein Debug, 1=Debuggen
  	* @param 	string $color	(optional) #ffffff - Hintergrundfarbe Tabelle außen
  	* @param	string title	(optional) Title für den Test
  	*
  	*/
	function system($type=0,$debugger=0,$color="#ffffff",$title='',$sql_param=0,$array_param=0)
	{
		$this->debug_array_logen=$array_param;
		$this->debug_sql_logen=$sql_param;
		$this->debug_logen=1;
		$this->color=$color;
		$this->type=0; 	
		$this->title_var=$title;
		if($this->type==0) define('TICK_LOG_FILE_DEBUG', FILE_PATH_hg.'logs/LOG_DEBUG_NPC_BOT_tick_'.date('d-m-Y', time()).'.log');
		if($this->type==1)	define('TICK_LOG_FILE_DEBUG', FILE_PATH_hg.'logs/HTML_DEBUG_NPC_BOT_tick_'.date('d-m-Y', time()).'.htm');
        	if($this->tester==0)
        	{
        	$this->timegesamt=time()+microtime();
        	$this->speichergesamt=memory_get_usage();
       		 }
	}
	/**
	 * ausgabe: schreibt den Ãœbergebenen Text in eine Datei
	 * @param datei $datei erzeugte Variable mit fopen
	 * @param string $zw Text vor dem Text
	 * @param string $text Der eigentliche Text
	 * @param string $end optional, schluss text
	 */
	function ausgabe($datei,$zw,$text,$end="")
	{
		if($this->type==1)
		{
			$end.="<br>";
		}
		$text=$zw.$text.$end;
		fwrite($datei, $text."\n");
	}
	/**
	 * footer: Fuß der Datei mit Gesamtinofs
	 * @param $queries_gesamt,$queries_gesamt_false
	 */
	function footer($queries_gesamt,$queries_gesamt_false)
	{
		if($this->debug_logen==1)
		{
			if($this->type==1)
			{
				$style_1="</td><tr></table>";
			}
			$time=(time()+microtime())-$this->timegesamt;
			$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
			$this->ausgabe($fp,'','---------------------------------------------------------------','');
			$this->ausgabe($fp,'','Gesamtzeit: '.$time.'('.$this->timegesamt.') // Gesamtqueries: '.$queries_gesamt.' // Fehlerhaftequeries: '.$queries_gesamt_false.' // Speicher: '.(memory_get_usage()-$this->speichergesamt).' // Fehler:'.$this->fehler_gesamt.''.$style_1);
			fclose($fp);
		}
	}
	 /**
	 * error: Soll für Fehler, besonders Fehler bei SQL-Anweisungen benutzt werden
	 * @param string $message Nachricht aus dem Code
	 * @param string $line Konstante __LINE__
	 * @param int $wichtig optional, art des Fehlers, 2=Warning, 3=ERROR, 4=FATAL
	 */
	function error($message,$line,$wichtig=0)
	{
		if($this->debug_logen==0)$fp = fopen(TICK_LOG_FILE_ERROR, 'a');
		if($this->debug_logen==1)$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
		if($wichtig==0) $this->ausgabe($fp,'','[ERROR][Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message,'');
		if($wichtig==1) $this->ausgabe($fp,'','[ERROR][Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.'','');
		if($this->type==1)
		{
			$style_1="<font color=#ff0000><b>";
			$style_2="</b></font>";
		}
		if($wichtig==2) $this->ausgabe($fp,$style_1,'[WARNING]'.$style_2.'[Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.'','');
		if($wichtig==3) $this->ausgabe($fp,$style_1,'[ERROR]'.$style_2.'[Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.'','');
		if($wichtig==4) $this->ausgabe($fp,$style_1,'[FATAL]'.$style_2.'[Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.'','');
		fclose($fp);
		$this->fehler_job++;
	}
	/**
	 * debug: Soll nur zum Debugen benutzt werden und beduetet zwar mehr code und mehr auslastung, dient aber der Fehleranalyse und für Tests
	 * @param string $message Nachricht aus dem Code
	 * @param string $line Konstante __LINE__
	 * @param int $wichtig optional, art des Fehlers, 0-2 =DEBUG, 3=STARTJOB,4=ENDJOB
	 */
	function debug($message,$line,$wichtig=0)
	{
		if($this->debug_logen==1 && $wichtig!=7)
		{
		if($this->type==1)
		{
			$style_1="<table border=1 bgcolor='".$this->color."'><tr><td><font color=#0000ff><b>";
			$style_2="<font color=#0000ff><b>";
			$style_3="</font>";
			$style_4="</font></td></tr></table>";
			$style_5="<table bgcolor='#ffffff'><tr><td>";
			$style_6="</td></tr></table>";
			$style_7="</b>";
		}
		$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
		if($wichtig==0) $this->ausgabe($fp,$style_5,'[DEBUG][Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.''.$style_6);
		if($wichtig==1) $this->ausgabe($fp,$style_5,'[DEBUG][Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.''.$style_6);
		if($wichtig==2) $this->ausgabe($fp,$style_5,'[DEBUG][Line:'.$line.'][Date:'.date("H:i:s").'] -- '.$message.''.$style_6);
		if($wichtig==3) $this->ausgabe($fp,$style_1,'[STARTJOB]'.$style_7.'[Date:'.date("H:i:s").'] -- '.$message.''.$style_3);
		if($wichtig==4) $this->ausgabe($fp,$style_2,'[ENDJOB]'.$style_7.'[Date:'.date("H:i:s").'] -- '.$message.''.$style_4);
		fclose($fp);
		}
	}
	/**
	 * log: Einfache Hinweise
	 * @param string $message Nachricht aus dem Code
	 */
	function log($message,$line='')
	{
		$fp = fopen(TICK_LOG_FILE_NPC, 'a');
		fwrite($fp, $message."\n");
		echo str_replace('\n','<br>',$message.'\n');
		fclose($fp);
		if($this->debug_logen==1)
		{
			$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
			if($line=='') $line='???';
			if($this->type==1)
			{
				$style_1="<font color=#009900>";
				$style_2="</font>";
			}
			$this->ausgabe($fp,$style_1,'[LOG][LINE:'.$line.'] -- '.$message.''.$style_2);
			fclose($fp);
		}
	}
	/**
	 * speicher: Dient für die Speicherbenutzung der einzelenen Punkte
	 * @param int $a optional,ob geschrieben werden soll oder nicht
	 */
	function speicher($a=0)
	{
		$speicher = memory_get_usage();
		if($a==0) $this->log('Speicherbenutzung:'.$speicher);
		return $speicher;
	}
	/**
	 * start_job: Zum starten eines neuen Abschnitts
	 * @param string $name Name des Jobs
	 * @param int $querie Anzahl der Queries bis zu dem Punkt
	 */
	function start_job($name,$querie)
	{
		$this->qu_z=$querie;
		if($this->debug_logen==1) $this->debug(''.$name.'...',__LINE__,3);
		if($this->debug_logen!=1) $this->log('<font color=#0000ff>Starting <b>'.$name.'</b>...</font>');
		//$this->speicher();
		$this->speicher_diff = $this->speicher(1);
		$this->start_values = time() + microtime();
	}
	/**
	 * finish_job: Für das Ende eines Abschnitts
	 * @param string $name Name des Jobs
	 * @param int $querie Anzahl der Queries bis zu dem Punkt
	 */
	function finish_job($name,$querie)
	{
		//$this->speicher();
		if($this->debug_logen!=1) $this->log('<font color=#0000ff>Executed <b>'.$name.'</b> (</font><font color=#ff0000>queries: '.($querie - $this->qu_z).'</font><font color=#0000ff>) in </font><font color=#009900>'.round( (time() + microtime()) - $this->start_values, 4).' secs <=> '.($this->speicher(1)-$this->speicher_diff).' </font><font color=#ff0000> [Fehler: '.$this->fehler_job.']</font><br>');
		if($this->debug_logen==1) $this->debug('Executed '.$name.' (queries: '.($querie - $this->qu_z).') in '.round( (time() + microtime()) - $this->start_values, 4).' secs <=> '.($this->speicher(1)-$this->speicher_diff).'  [Fehler: '.$this->fehler_job.']',__LINE__,4);
		$this->fehler_gesamt+=$this->fehler_job;
		$this->fehler_job=0;
	}
	function manuel($sql_befehl)
	{
		$fp = fopen(TODO, 'a');
		$this->ausgabe($fp,'','SQL-Anweisung:'.$sql_befehl);
		fclose($fp);
	}
	/**
	* array_debug: Um den Inhalt eines Arrays in die Debug datei zu schreiben
	* @param array $arraya Das auszulesende Array
	* @param int $art 0=eindimensional, 1= zweidimensional 
	*/
	function array_debug($arraya,$art=0)
	{
		
		if($this->debug_array_logen==1)
		{
			$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');0;
			if($art==0)
			{
				foreach ($arraya as $value)
				{
					$this->ausgabe($fp,'','[ARRAY-]['.$zahl.']'.$value);
				}
			}else if($art==1)
			{
				foreach ($arraya as $key => $value)
				{				
						$this->ausgabe($fp,'','[ARRAY]['.$zahl.']['.$key.']'.$value);
				}
			}
			fclose($fp);
		}
	}
	/**
	* debug_info: Funktion zum infomierne über eine SQL Anweisung, kopiert aus der Klasse "SQL" und für das DEbuggen umgeschrieben
	* @param array $sql_debug_array Array aus der SQL-Klasse
	*/
	function debug_info($sql_debug_array)
	{
		//Tabele felder
        $text='<table bgcolor="#666666" width="80%" cellpadding="5" cellspacing="1">
  		<tr><td><b>Query Server Intensity</b></td><td bgcolor="#CCCCCC" align="center"><b>query</b></td><td bgcolor="#CCCCCC" align="center"><b>execution time</b></td>
   		<td bgcolor="#CCCCCC" align="center"><b>table(s)</b></td><td bgcolor="#CCCCCC" align="center"><b>type</b></td>
    	<td bgcolor="#CCCCCC" align="center"><b>possible keys</b></td><td bgcolor="#CCCCCC" align="center"><b>key</b></td>
    	<td bgcolor="#CCCCCC" align="center"><b>key length</b></td><td bgcolor="#CCCCCC" align="center"><b>ref</b></td>
    	<td bgcolor="#CCCCCC" align="center"><b>rows</b></td><td bgcolor="#CCCCCC" align="center"><b>extra</b></td></tr> ';
        foreach($sql_debug_array as $data) {
            $time = round($data['time'], 5);
            $sql = $data['sql'];
            $q_explain = mysql_query('EXPLAIN '.$sql, $this->link_id);
            $data['explain'] = mysql_fetch_array($q_explain, MYSQL_ASSOC);
            $danger = 1;
            $table =         (!empty($data['explain']['table']))         ? $data['explain']['table']         : '';
            $type =          (!empty($data['explain']['type']))          ? $data['explain']['type']          : '';
            $possible_keys = (!empty($data['explain']['possible_keys'])) ? $data['explain']['possible_keys'] : '';	
            $key =           (!empty($data['explain']['key']))           ? $data['explain']['key']           : '';
            $key_len =       (!empty($data['explain']['key_len']))       ? $data['explain']['key_len']       : '';
            $ref =           (!empty($data['explain']['ref']))           ? $data['explain']['ref']           : '';
            $rows =          (!empty($data['explain']['rows']))          ? $data['explain']['rows']          : '';
            $extra =         (!empty($data['explain']['Extra']))         ? $data['explain']['Extra']         : '';
            if($time > 0.05) $danger++;
            if($time > 0.1) $danger++;
            if($time > 1) $danger++;
            if($type == 'ALL') $danger++;
            if($type == 'index') $danger++;
            if($type == 'range') $danger++;
            if($type == 'ref') $danger++;
            if($rows >= 200) $danger++;
            if(!empty($possible_keys) && empty($key)) $danger++;
            if((strpos($extra, 'Using filesort') !== false) || (strpos($extra, 'Using temporary') !== false)) $danger++;
            switch($danger) {
                case 1:  $color[0] = '#DADADA'; $color[1] = '1'; break;
                case 2:  $color[0] = '#DAD0D0'; $color[1] = '2';break;
                case 3:  $color[0] = '#DACACA'; $color[1] = '3';break;
                case 4:  $color[0] = '#DAC0C0'; $color[1] = '4';break;
                case 5:  $color[0] = '#DABABA'; $color[1] = '5';break;
                case 6:  $color[0] = '#DAB0B0'; $color[1] = '6';break;
                case 7:  $color[0] = '#DAAAAA'; $color[1] = '7';break;
                case 8:  $color[0] = '#DA9090'; $color[1] = '8';break;
                case 9:  $color[0] = '#DA8A8A'; $color[1] = '9';break;
                default: $color[0] = '#FF0000'; $color[1] = '0';break;
            }
           $text.='	<tr><td bgcolor="'.$color[0].'">'.$color[1].'</td>		
			<td bgcolor="'.$color[0].'"><pre style=" font-size:12px; font-family:courier new">'.wordwrap(trim(str_replace("\t", '', $sql))).'</pre></td>
			<td bgcolor="'.$color[0].'">'.$time.'</td><td bgcolor="'.$color[0].'">'.$table[2].'</td>
			<td bgcolor="'.$color[0].'">'.$type.'</td><td bgcolor="'.$color[0].'">'.$possible_keys.'</td>
			<td bgcolor="'.$color[0].'">'.$key.'</td><td bgcolor="'.$color[0].'">'.$key_len.'</td>
			<td bgcolor="'.$color[0].'">'.$ref.'</td><td bgcolor="'.$color[0].'">'.$rows.'</td>
			<td bgcolor="'.$color[0].'">Intensity '.$danger.(!empty($extra) ? '; '.$extra : '').'</td>
			</tr>';
        	
        }
        $text.='</table>'; 
		if($this->debug_sql_logen==1)
		{
			$fp = fopen(TICK_LOG_FILE_DEBUG, 'a');
			$this->ausgabe($fp,"",$text,"");
			fclose($fp);
		}
    }
}

//
########################################################################################
// SQL-Abstraktions-Klasse und andere Funktionen

class sql_npc {
	var $login = array();
	var $error = array();

	var $link_id = 0;
	var $query_id = 0;

	var $i_query = 0;
	var $t_query = 0;
	var $debug = false;
	var $d_query = array();
	var $false_querys=0;

	var $already_reconnected = -1; // So ist bei erster Verbindung 0

	function sql_npc($server, $database, $user, $password = '') {
		$this->login = array(
		'server' => $server,
		'database' => $database,
		'user' => $user,
		'password' => $password
		);

	}
	function connect() {
		$sdl = new system(0,1);
		if(!is_resource($this->link_id)) {
			if($this->already_reconnected == 5) {
				$sdl->error('ILLEGAL: Mysql->connect(): Trying to reconnect 6th time! DIE',__LINE__,4);
				exit;
			}
			if(!$this->link_id = @mysql_connect($this->login['server'], $this->login['user'], $this->login['password'])) {
				$sdl->error('CRITICAL: Mysql->connect(): Could not connect to mysql server! DIE',__LINE__,4);
				exit;
			}
			if(!@mysql_select_db($this->login['database'], $this->link_id)) {
				$sdl->error('CRITICAL: Mysql->connect(): Could not select database! DIE',__LINE__,4);
				exit;
			}

			$this->already_reconnected++;
		}
		return true;
	}
 function lock() {
        $tables = func_get_args();
        $tables[] = 'planets';
        $tables[] = 'starsystems';
        $tables[] = 'user';
        $n_tables = count($tables);
        $table_str = array();
        if($n_tables == 0) return;
        for($i = 0; $i < $n_tables; ++$i) {
            $table_str[] = $tables[$i].' WRITE';
        }
        $sql = 'LOCK TABLES '.implode(',', $table_str);
        if(!mysql_query($sql, $this->link_id)) {
            $this->raise_error(false, false, $sql);
        }
        return true;
    }
    function unlock() {
        if(!mysql_query('UNLOCK TABLES', $this->link_id)) {
            $this->raise_error(false, false, 'UNLOCK TABLES');
        }
        return true;
    }
	function raise_error($message = false, $number = false, $sql = '') {
		$sdl = new system(0,1);
		if($message === false) $message = mysql_error($this->link_id);
		if($number === false) $number = mysql_errno($this->link_id);
		$this->false_querys++;
		$this->error = array(
		'message' => $message,
		'number' => $number,
		'sql' => $sql
		);
		
		$sdl->error('Datenbankfehler('.$this->error["number"].'): '.$this->error['message'].'<br>'.$this->error['sql'].'<br>',__LINE__,4);
		return false;
	}
	function close() {
		if(is_resource($this->link_id)) {
			if(!@mysql_close($this->link_id)) {
				return $this->raise_error();
			}
		}

		return true;
	}
	function num_rows($query)
	{
		if(!$this->connect()) {
			return $this->raise_error(false, false, $query);
		}
		if(!$select=@mysql_query($query)){
			return $this->raise_error(false, false, $query);
		}else{
			return $select=mysql_num_rows($select);
		}

	}
	function fetchrow($query_id = false, $result_type = MYSQL_ASSOC)
	{
		if($query_id === false) $query_id = $this->query_id;
		if(!$_row = @mysql_fetch_array($query_id, $result_type)) {
			if(($_error = mysql_error()) !== '') {
				return $this->raise_error($_error);
			}
			else {
				return array();
			}
		}
		return $_row;
	}
	function query($query)
	{
		$sdl = new system(0,1);
		$sdl->debug($query,__LINE__,7);
		$this->i_query++;
		if(!$this->connect()) {
			return $this->raise_error(false, false, $query);
		}
		$start_time = time() + microtime();
		if(!$select=@mysql_query($query)){
			return $this->raise_error(false, false, $query);
		}else{
			$total_time = (time() + microtime()) - $start_time;
			if($this->debug)
			{
				unset($this->d_query);
				
            	$this->d_query[] = array(
                'sql' => $query,
                'time' => $total_time
            );
        	}
			if(@mysql_num_rows($select)<=0){
				return $this->raise_error(false, false, $query);
			}else{
				return $select=mysql_fetch_array($select);
			}
		}

	}
	function query_a($query, $unbuffered = false)
	{
		$sdl = new system(0,1);
		$sdl->debug($query,__LINE__,7);
		if(!$this->connect()) return false;
		$query_function = ($unbuffered) ? 'mysql_unbuffered_query' : 'mysql_query';
		$start_time = time() + microtime();
		if(!$this->query_id = @$query_function($query, $this->link_id)) {
			return $this->raise_error(false, false, $query);
		}
        $total_time = (time() + microtime()) - $start_time;
        if($this->debug) {
        	unset($this->d_query);
            $this->d_query[]= array(
                'sql' => $query,
                'time' => $total_time
            );
        }
		$this->t_query += $total_time;
		++$this->i_query;
		return $this->query_id;
	}
	function insert($query)
	{
		$start_time = time() + microtime();
		if(!(mysql_query($query)))
		{
			return $this->raise_error(false, false, $query);
		}else{
			$total_time = (time() + microtime()) - $start_time;
			if($this->debug)
			{
        	unset($this->d_query);
            $this->d_query[] = array(
                'sql' => $query,
                'time' => $total_time
            );
        	}
			return true;
		}
	}
    function fetchrowset($query_id = 0, $result_type = MYSQL_ASSOC)
    {
        if(!is_resource($query_id)) $query_id = $this->query_id;
        $_row = $_rowset = array();
        while($_row = @mysql_fetch_array($query_id, $result_type)) {
            $_rowset[] = $_row;
        }
        if(!$_rowset) {
            if(($_error = mysql_error()) !== '') {
                return $this->raise_error();
            }
            else {
                return array();
            }
        }
        return $_rowset;
    }
    function queryrow($query, $result_type = MYSQL_ASSOC) {
        if(!$_qid = $this->query_a($query)) {
            return false;
        }

        return $this->fetchrow($_qid, $result_type);
    }
    function num_rows_a($query_id = 0)
    {
        if(!is_resource($query_id)) $query_id = $this->query_id;
        $_num = @mysql_num_rows($query_id);
        if($_num === false) {
            return $this->raise_error();
        }
        return $_num;
    }
    function insert_id()
    {
        $_id = @mysql_insert_id($this->link_id);
        if($_id === false) {
            return $this->raise_error();
        }
        return $_id;
    }
    function queryrowset($query, $result_type = MYSQL_ASSOC) {

        if(!$_qid = $this->query_a($query, true)) {

            return false;

        }



        return $this->fetchrowset($_qid, $result_type);

    }
    function free_result($query_id = false) {

        if($query_id === false) $query_id = $this->query_id;



        if(!@mysql_free_result($query_id)) {

            return $this->raise_error();

        }
         return true;

    }
       function affected_rows() {

        $_num = @mysql_affected_rows($this->link_id);



        if($_num === false) {

            return $this->raise_error();

        }
       


        return $_num;

    }
}


function vergleich($first,$second,$debug=0)
{
	if($first==$second)
	{
		return 0;
	}else{
		return 1;
	}
}

function add_logbook_entry_x2($user_id, $log_type, $log_title, $log_data) {
	$db = new sql_npc($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection    
    $game= new game();
    if (!isset($user_id) || !isset($log_type) ||!isset($log_title) ||!isset($log_data)) return;
    if (empty($user_id) || empty($log_type) ||empty($log_title) ||empty($log_data)) return;

    $sql = 'INSERT INTO logbook (user_id, log_type, log_date, log_read, log_title, log_data)
            VALUES ('.$user_id.', '.$log_type.', '.$game->TIME.', 0, "'.$log_title.'", "'.addslashes(serialize($log_data)).'")';

    if(!$db->query_a($sql)) {
        message(DATABASE_ERROR, 'Could not insert new logbook data');
    }

    $sql = 'UPDATE user
            SET unread_log_entries = unread_log_entries + 1
            WHERE user_id = '.$user_id;

    if(!$db->query_a($sql)) {
        message(DATABASE_ERROR, 'Could not update user unread log entries data');
    }

    return true;
}

function create_system_b($id_type, $id_value) {
    $sdl = new system(0,1);
    $game = new game();
	$dad = new sql_npc($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection    $sector_id = $system_x = $system_y = 0;
    
    switch($id_type) {
        case 'quadrant':
            $quadrant_id = $id_value;

            $sql = 'SELECT *
                    FROM starsystems_slots
                    WHERE quadrant_id = '.$quadrant_id.'
                    LIMIT 1';
                          
            if(($free_slot = $dad->queryrow($sql)) === false) {
                		
		$sdl->log('world::create_system_b(): Could not query starsystem slots');
            }

            if(!empty($free_slot['sector_id'])) extract($free_slot);
        break;
        
        case 'sector':
            $sql = 'SELECT *
                    FROM starsystems_slots
                    WHERE sector_id = '.$id_value.'
                    LIMIT 1';
                    
            if(($free_slot = $dad->queryrow($sql)) === false) {
                		
		$sdl->log('world::create_system_b(): Could not query starsystem slots');
            }
            
            if(!empty($free_slot['sector_id'])) extract($free_slot);
        break;
        
        case 'slot':
            $params = explode(':', $id_value);
            
            $sql = 'SELECT *
                    FROM starsystems_slots
                    WHERE sector_id = '.(int)$params[0].' AND
                          system_x = '.(int)$params[1].' AND
                          system_y = '.(int)$params[2].'
                    LIMIT 1';
                    
            if(($free_slot = $dad->queryrow($sql)) === false) {
				$sdl->log('World::create_system_b(): Could not query starsystem slots');
            }

            if(!empty($free_slot['sector_id'])) extract($free_slot);
        break;
    }
    
    if(empty($sector_id)) {
		$sdl->log('System konnte nicht erstellt werden', 'world::create_system_b(): $sector_id = empty');
    }
    
    $star_size = mt_rand($game->starsize_range[0], $game->starsize_range[1]);

    $required_borders = ($game->sector_map_split - 1);
    $px_per_field = ( ($game->sector_map_size - $required_borders) / $game->sector_map_split);

    $root_x = ($px_per_field * ($system_x - 1) ) + ($system_x - 1);
    $root_y = ($px_per_field * ($system_y - 1) ) + ($system_y - 1);
    
    $border_distance = $px_per_field / 4;
    $border_distance = max($border_distance, ( ($star_size * 0.45) + 3 ) );

    $system_map_x = mt_rand( ($root_x + $border_distance), $root_x + ($px_per_field - $border_distance) );
    $system_map_y = mt_rand( ($root_y + $border_distance), $root_y + ($px_per_field - $border_distance) );

    $system_coords = $game->get_system_gcoords($system_x, $system_y, $sector_id);

    $star_base_color = mt_rand(0, 3);

    switch($star_base_color) {
        // Blauer (junger) Stern
        case 0:
            $star_color = array(mt_rand(0, 25), mt_rand(0, 25), mt_rand(150, 255));
        break;

        // Weißer Stern
        case 1:
            $star_color = array(mt_rand(220, 255), mt_rand(220, 255), mt_rand(220, 255));
        break;

        // Gelber Stern
        case 2:
            $star_color = array(mt_rand(200, 255), mt_rand(50, 150), mt_rand(0, 25));
        break;

        // Roter Stern
        case 3:
            $star_color = array(mt_rand(150, 255), mt_rand(0, 25), mt_rand(0, 25));
        break;

        // Brauner (alter) Stern
        case 4:
            $star_color = array(mt_rand(100, 150), mt_rand(40, 80), mt_rand(0, 15));
        break;
    }

    $sql = 'INSERT INTO starsystems (system_name, sector_id, system_x, system_y, system_map_x, system_map_y, system_global_x, system_global_y, system_starcolor_red, system_starcolor_green, system_starcolor_blue, system_starsize)
            VALUES ("System '.$game->get_sector_name($sector_id).':'.$game->get_system_cname($system_x, $system_y).'", '.$sector_id.', '.$system_x.', '.$system_y.', '.$system_map_x.', '.$system_map_y.', '.$system_coords[0].', '.$system_coords[1].', '.$star_color[0].', '.$star_color[1].', '.$star_color[2].', '.$star_size.')';

    if(!$dad->query_a($sql)) {
		$sdl->log('world::create_system_b(): Could not insert new system data // '.$sql,__LINE__);
    }

    $new_system_id = $dad->insert_id();
    
    $sql = 'DELETE FROM starsystems_slots
            WHERE sector_id = '.$sector_id.' AND
                  system_x = '.$system_x.' AND
                  system_y = '.$system_y;
                  
    if(!$dad->query_a($sql)) {
		$sdl->log('world::create_system_b(): Could not delete starsystems slot data // '.$sql,__LINE__);
    }

    return array($new_system_id, $sector_id);
}
function create_planet_a($user_id, $id_type, $id_value) {
    global $PLANETS_DATA;
    $game = new game();
    $sdl = new system(0,1);
	$dad = new sql_npc($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

    $system_id = $sector_id = 0;

    switch($id_type) {
        case 'quadrant':
            $quadrant_id = $id_value;
            
            // Ãœberprüfen, ob ein passendes System bereits existiert
            
            // In einem von 7 Fällen erstellt er auf jeden Fall ein neues System
            if(mt_rand(1, 7) != 3) {
                $sector_id_min = ( ($quadrant_id - 1) * $game->sectors_per_quadrant) + 1;
                $sector_id_max = $quadrant_id * $game->sectors_per_quadrant;

                $sql = 'SELECT system_id, sector_id, system_n_planets
                        FROM starsystems
                        WHERE sector_id >= '.$sector_id_min.' AND
                              sector_id <= '.$sector_id_max.' AND
                              system_closed = 0 AND
                              system_n_planets < '.$game->system_max_planets;
                              
                if(($q_systems = $dad->query_a($sql)) === false) {
					$sdl->log('world::create_planet_a(): '.$sql.'',__LINE__);
                }
                
                $available_systems = array();
                $n_available = 0;

                while($system = $dad->fetchrow($q_systems)) {
                	$available_systems[] = array($system['sector_id'], $system['system_id']);
                	
                	//if( ++$n_available > 30) break;
                }
                
                $chosen_system = $available_systems[array_rand($available_systems)];
                
                $sector_id = $chosen_system[0];
                $system_id = $chosen_system[1];
            }
            
            // Wenn ein neues System erstellt werden muss ($system_id = 0), dann sind alle Orbitale frei
            // (in der Alpha-2 hat er dann trotzdem eins gesucht *roll*)
            // Ansonsten ein freies suchen
            
            $free_distances = array( array(43, 53), array(68, 78), array(93, 103), array(118, 128), array(143, 155) );
              
            if(!$system_id) {
                $_temp = create_system_b('quadrant', $quadrant_id);
                
                $system_id = $_temp[0];
                $sector_id = $_temp[1];
            }
            else {
                $sql = 'SELECT planet_distance_id FROM planets WHERE system_id = '.$system_id;
                        
                if(($planet_did = $dad->queryrowset($sql)) === false) {
                    
					$sdl->log('world::create_planet_a(): Could not query planets did data // '.$sql,__LINE__);
                }

                for($i = 0; $i < count($planet_did); ++$i) {
                    unset($free_distances[$planet_did[$i]['planet_distance_id']]);
                }

                if(empty($free_distances)) {
					$sdl->log('Planet konnte nicht erstellt werden', 'world::create_planet_a(): $free_distances[] = empty',__LINE__);
                }
            }
        break;
        
        case 'sector':
            $sector_id = $id_value;

            // Ãœberprüfen, ob ein passendes System bereits existiert

            // In einem von 3 Fällen erstellt er auf jeden Fall ein neues System
            if(mt_rand(1, 3) != 2) {
                $sql = 'SELECT system_id, sector_id, system_n_planets
                        FROM starsystems
                        WHERE sector_id >= '.$sector_id.' AND
                              system_closed = 0';

                if(($q_systems = $dad->query_a($sql)) === false) {
				$sdl->log('world::create_planet_a(): Could not query systems data',__LINE__);
                }

                while($system = $dad->fetchrow($q_systems)) {
                    if($system['system_n_planets'] > $game->system_max_planets) {
		$sdl->log('world', 'System '.$system['system_id'].' has '.$system['system_n_planets'],__LINE__);
                    }
                    elseif($system['system_n_planets'] < $game->system_max_planets) {
                        $system_id = $system['system_id'];
                        $sector_id = $system['sector_id'];
                        break;
                    }
                }
            }

            // Wenn ein neues System erstellt werden muss ($system_id = 0), dann sind alle Orbitale frei
            // (in der Alpha-2 hat er dann trotzdem eins gesucht *roll*)
            // Ansonsten ein freies suchen

            $free_distances = array( array(43, 53), array(68, 78), array(93, 103), array(118, 128), array(143, 155) );

            if(!$system_id) {
                $_temp = create_system_b('sector', $sector_id);

                $system_id = $_temp[0];
                //$sector_id = $_temp[1];
            }
            else {
                $sql = 'SELECT planet_distance_id
                        FROM planets
                        WHERE system_id = '.$system_id;

                if(($planet_did = $dad->queryrowset($sql)) === false) {
		$sdl->log('world::create_planet_a(): Could not query planets did data',__LINE__);
                }

                for($i = 0; $i < count($planet_did); ++$i) {
                    unset($free_distances[$planet_did[$i]['planet_distance_id']]);
                }

                if(empty($free_distances)) {
		$sdl->log('Planet konnte nicht erstellt werden', 'world::create_planet_a(): $free_distances[] = empty',__LINE__);
                }
            }
        break;
        
        case 'system':
            $free_distances = array( array(43, 53), array(68, 78), array(93, 103), array(118, 128), array(143, 155) );
            $system_id = $id_value;
            
            // HINWEIS: Das gewählte System MUSS existieren
            
            $sql = 'SELECT sector_id, planet_distance_id
                    FROM planets
                    WHERE system_id = '.$system_id;
                    
            if(($planet_did = $dad->queryrowset($sql)) === false) {
		$sdl->log('world::create_planet_a(): Could not query planet did data',__LINE__);
            }
            
            for($i = 0; $i < count($planet_did); ++$i) {
                unset($free_distances[$planet_did[$i]['planet_distance_id']]);
            }
            
            $sector_id = $planet_did[0]['sector_id'];
        break;
    }

    $planet_distance_id = array_rand($free_distances);
	$zzz++;
	$planet_ABC[$zzz] = $planet_distance_id ;
    $planet_distance_px = mt_rand($game->planet_distances[$planet_distance_id][0], $game->planet_distances[$planet_distance_id][1]);

    // Erstellen!
    if(!$user_id) {
		  
        $type_probabilities = array(
            // viel Metall, wenig Mineralien, wenig Latinum (15%)
            'a' => 7,  // 3.0  0.1  0.8  0.1
            'b' => 8,  // 3.0  0.1  0.8  0.1
           
            // mittel Metall, Mineralien, Latinum (60%)
            'c' => 10,  // 1.0  1.0  1.0  0.6
            'd' => 12,  // 1.0  1.0  1.0  0.5
            'e' => 8,   // 1.0  1.0  1.0  0.7
            'f' => 6,   // 1.0  1.0  1.0  0.8
            'h' => 13,  // 1.0  1.0  1.0  0.5
            'k' => 11,  // 1.0  1.0  1.0  0.4
            
            // viel Metall, mittel Mineralien, mittel Latinum (10%)
            'g' => 10, // 1.5  1.0  1.0  0.8
            
            // wenig Metall, viel Mineralien, viel Latinum (5%)
            'i' => 5,  // 0.3  1.6  1.6  0.5
            
            // wenig Metall, sehr viel Mineralien, wenig Latinum (5%)
            'j' => 2,  // 0.3  2.0  0.4  0.6
            'l' => 3,  // 0.4  1.9  0.5  0.7
            
            // M/N/Y (5%)
            'm' => 1,  // 1.0  1.0  1.0  1.0
            'n' => 1,  // 0.95 0.95 0.95 1.1
            'y' => 3,  // 2.5  2.5  2.5  0.2
        );

        $type_array = array();

        foreach($type_probabilities as $type => $probability) {
            for($i = 0; $i < $probability; ++$i) {
                $type_array[] = $type;
            }
        }
        
        $planet_type = $type_array[array_rand($type_array)];
        $sql = 'INSERT INTO planets (planet_name, system_id, sector_id, planet_type, planet_owner, planet_owned_date, planet_distance_id, planet_distance_px, planet_covered_distance, planet_tick_cdistance, planet_max_cdistance, resource_1, resource_2, resource_3, resource_4, planet_points)
                VALUES ("Unbek. PlanetB", '.$system_id.', '.$sector_id.', "'.$planet_type.'", 0, '.$game->TIME.', '.$planet_distance_id.', '.$planet_distance_px.', 0, '.( mt_rand(10, 30) ).', '.( 2 * M_PI * $planet_distance_px ).', 0, 0, 0, 0, 0)';
    }
    else {
        $planet_type = (mt_rand(1, 2) == 1) ? 'm' : 'n';
		
        $sql = 'INSERT INTO planets (planet_name, system_id, sector_id, planet_type, planet_owner, planet_owned_date, planet_distance_id, planet_distance_px, planet_covered_distance, planet_tick_cdistance, planet_max_cdistance, resource_1, resource_2, resource_3, resource_4, planet_points, recompute_static, max_resources, max_worker, max_units, workermine_1, workermine_2, workermine_3)
                VALUES ("Unbek. Kolonie", '.$system_id.', '.$sector_id.', "'.$planet_type.'", '.$user_id.', '.$game->TIME.', '.$planet_distance_id.', '.$planet_distance_px.', 0, '.( mt_rand(10, 30) ).', "'.( 2 * M_PI * $planet_distance_px ).'", 200, 200, 100, 100, 10, 1, 5000, 40000, 80000, 100, 100, 100)';
    	 
    
    }

    if(!$dad->query_a($sql)) {
		$sdl->log('world::create_planet_a(): Could not insert new planet data=>'.$sql.'',__LINE__);
    }

    $planet_id = $dad->insert_id();

    $sql = 'UPDATE starsystems
            SET system_n_planets = system_n_planets + 1
            WHERE system_id = '.$system_id;
            
    if(!$dad->query_a($sql)) {
		$sdl->log('world::create_planet_a(): Could not update starsystem data',__LINE__);
    }

    return $planet_id;
}
/* ######################################################################################## */
/* ######################################################################################## */
// Startkonfig des NPCs
class NPC
{

	function MessageUser($sender,$receiver, $header, $message)
	{
		$db = new sql_npc($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection		
		$sdl = new system(0,1,"#ecce66");
		$game = new game();
		if ($db->query_a('INSERT INTO message (sender, receiver, subject, text, rread,time) VALUES ('.$sender.',"'.$receiver.'","'.addslashes($header).'","'.addslashes($message).'",0,'.$game->TIME.')')==false)
			{
			$sdl->error('systemmessage_query: Could not call INSERT INTO in message :-:INSERT INTO message (sender, receiver, subject, text, rread,time)
                     VALUES ('.$sender.',"'.$receiver.'","'.addslashes($header).'","'.addslashes($message).'",0, '.$game->TIME.')',__LINE__,3);
			}
		$sdl->debug_info($db->d_query[$db->debug_zaehler]);
		$sdl->log("Senderid:".$sender." // Receiverid:".$receiver,__LINE__);
		$num=$db->query('SELECT COUNT(id) as unread FROM message WHERE (receiver="'.$receiver.'") AND (rread=0)');
		if($num['unread']>0) $db->query_a('UPDATE user SET unread_messages="'.$num['unread'].'" WHERE user_id="'.$receiver.'"');
		$sdl->log("Num:".$num['unread'],__LINE__);
 		$sdl->debug_info($db->d_query[$db->debug_zaehler]);

 		return true;
	}
	
	public function NPC($debuggen=0,$title="",$type=0,$color="#ffffff")
	{
		$starttime = ( microtime() + time() );
		$db = new sql_npc('localhost', 'db', 'root','');
		$debug_array_logen=0;
		$debug_sql_logen=0;
		if($debug_zu= $db->query('SELECT * FROM FHB_debug LIMIT 0,1'))
		{
			if($debug_zu['debug']==0 || $debug_zu['debug']==1)$debuggen=$debug_zu['debug'];
			if($debug_zu['array']==0 || $debug_zu['array']==1)$debug_array_logen=$debug_zu['array'];
			if($debug_zu['style']==0 || $debug_zu['style']==1)$type=$debug_zu['style'];
			if($debug_zu['sql']==0 || $debug_zu['sql']==1)$debug_sql_logen=$debug_zu['sql'];
		}
		$sdl = new system($type,$debuggen,$color,$title,$debug_sql_logen,$debug_array_logen);
		$sdl->title();
		$sdl_2 = new system($type,0,"#ecce66");

		$game = new game();

		$sdl->log("\n".'<b>-------------------------------------------------------------</b>'."\n".
		'<b>Starting Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>',__LINE__);
		$sdl_2->start_job('Eine weitere Daten-Sammlung aus dem Leben des Bots');
		//Damit der Bot auch Leben kann brauchen wir ein paar Infos
		$Umwelt = $db->query('SELECT * FROM config LIMIT 0 , 1');
		$sdl->debug_info($db->d_query);
		$sdl->array_debug($Umwelt,1);
		$ACTUAL_TICK = $Umwelt['tick_id'];
		$sdl->debug('Tick:'.$ACTUAL_TICK,__LINE__,1);
		$STARDATE = $Umwelt['stardate'];
		$sdl->debug('Stardate:'.$STARDATE,__LINE__,1);
		$sdl->start_job('Grundsystem von Ramona Überprüfen und wenn was fehlt ausführen',$db->i_query);
		//Erst feststellung ob der Bot schon ein exitenz besitzt
		if($Umwelt)
		{
			$sdl->log("Das Gespräch mit Ramona beginnt, ach ist sie nicht schön und dann hat sie einen so tollen Charakter",__LINE__);
			$Bot_exe=$db->num_rows('SELECT * FROM FHB_Bot LIMIT 0 , 1');
			$sdl->array_debug($Bot_exe,1);
			if($Bot_exe<1)
			{
				$sql='INSERT INTO FHB_Bot (user_id,user_name,user_tick,user_race,user_loginname,planet_id,ship_t_1,ship_t_2)
				VALUES ("0","","0","0","","0","0","0")';		
				if(!$db->query_a($sql))
				{
					$sdl->error('Bot bekamm kein Existenzrecht',__LINE__,4);
					$sdl->log('Abruch des Programms wegen Fehler beim erstellen des Users',__LINE__);
					exit;
				}
				$sdl->debug_info($db->d_query);
			}
			//So jetzt geben wir dem Bot mal ein paar Daten damit er auch Registriert ist
			$Bot = $db->query('SELECT * FROM FHB_Bot Limit 0,1');
			$sdl->debug_info($db->d_query);
			$sdl->array_debug($Bot,1);
			//Schauen ob der Bot schon lebt
			if($Bot['user_id']==0){
				$sdl->log('Ramona wird erschaffen',__LINE__);
				$sql = 'INSERT INTO user (user_active, user_name, user_loginname, user_password, user_email, user_auth_level, user_race, user_gfxpath, user_skinpath, user_registration_time, user_registration_ip, user_birthday, user_gender, plz, country,user_enable_sig,user_message_sig,user_signature)
        	    VALUES (1, "Ferengi Quark(NPC)", "Bot", "'.md5("bundu").'", "xxx@xxx.de", 1, 5, "", "skin1/", '.time().', "100.0.0.1", "20.04.2007", "w", 76149 , "Deutschland",1,"<br><br><p><b>i.A. der Ferengihandelsgilde</b></p>","Ich wohne im Rechenzentrum Karlsruhe - so jetzt aber schluss mit lustig")';
			
				if(!$db->query_a($sql))
				{
					$sdl->error('Bot: Konnte keine Ramona erstellen',__LINE__,4);
				}else{
					$sdl->debug('SQL:'.$sql,__LINE__,7);
					$sdl->debug_info($db->d_query);
					$sql = 'Select * FROM user WHERE user_name="Ferengi Quark(NPC)" and user_loginname="Bot" and user_auth_level=1';
					$sdl->debug('SQL:'.$sql,__LINE__,7);
					$Bot_zw = $db->query($sql);
					$sdl->array_debug($Bot_zw,1);
					if(!$Bot_zw['user_id'])
					{
						$sdl->error('Die Variable $Bot_zw hat keinen inhalt',__LINE__,4);
						//break;
					}
					$sdl->debug_info($db->d_query);
					$sql = 'UPDATE FHB_Bot SET user_id="'.$Bot_zw['user_id'].'",user_name="'.$Bot_zw['user_name'].'",user_tick="'.$ACTUAL_TICK.'",user_loginname="'.$Bot_zw['user_loginname'].'",user_race="'.$Bot_zw['user_race'].'" WHERE id="'.$Bot['id'].'"';
					$sdl->debug('SQL:'.$sql,__LINE__,7);
					if(!$db->query_a($sql)) {
						$sdl->error('Bot Ausweis:Konnte den Ausweis nicht verändern',__LINE__,4);
					}
					$sdl->debug_info($db->d_query);
					$Bot = $db->query('SELECT * FROM FHB_Bot');
					$sdl->array_debug($Bot,1);
					$sdl->debug_info($db->d_query);
				}
			}
			//Der Bot sollte ja auch einen Körper haben der nach was aussieht
			if($Bot['planet_id']==0){
				$sdl->log('<b>Ramona bekommt einen neuen Körper</b>',__LINE__);
				while($Bot['planet_id']==0 or $Bot['planet_id']=='empty'){
						$sdl->log('Neuer Planet',__LINE__);
						$db->lock('starsystems_slots');
						$Bot['planet_id']=create_planet_a($Bot['user_id'], 'quadrant', 4);
						$db->unlock();
						if($Bot['planet_id'] ==0)
						{
							$sdl->error('Bot Plani id geht nicht');
							exit;
						}
						$sdl->debug($Bot['planet_id'],__LINE__,2);
						$sql = 'UPDATE user SET user_points = "400",user_planets = "1",last_active = "5555555555", user_attack_protection = "'.($ACTUAL_TICK + 1500).'",user_capital = "'.$Bot['planet_id'].'",active_planet = "'.$Bot['planet_id'].'" WHERE user_id = "'.$Bot['user_id'].'"';
						$sdl->debug('SQL:'.$sql,__LINE__,7);
					if(!$db->query_a($sql)) {
						$sdl->error('Bot Koerper:Planet wurde nicht erstellt',__LINE__,4);
					}else{
						$sdl->debug_info($db->d_query);
      					//Bot bekommt bessere werte für seinen Körper, er soll ja auch gut aussehen
						$sdl->log('Besser Werte für den Planet',__LINE__);
      					$sql = 'UPDATE planets SET planet_points = 500,building_1 = 9,building_2 = 9,	building_3 = 9,	building_4 = 9,	building_5 = 9,	building_6 = 9,	building_7 = 9,	building_8 = 9, building_9 = 9,	building_10 = 9,building_11 = 9,building_12 = 9,building_13 = 9,unit_3=20000,planet_name = "HaendlerBasis",resource_4 = 44000
         				WHERE planet_owner = '.$Bot['user_id'].' and planet_id='.$Bot['planet_id'].'';
      					$sdl->debug('SQL:'.$sql,__LINE__,7);
      					if(!$db->query_a($sql)) $sdl->error('Bot Körper: Konnte den Körper nicht verbessern',__LINE__,3);
      					$sdl->debug_info($db->d_query);
      					$sql = 'UPDATE FHB_Bot SET planet_id='.$Bot['planet_id'].' WHERE user_id = '.$Bot['user_id'].'';
      					$sdl->debug('SQL:'.$sql,__LINE__,7);
      					if(!$db->query_a($sql)) $sdl->error('Bot Ausweis: Konnte beim Ausweis die Planet Infos nicht ändern',__LINE__,4);
      					$sdl->debug_info($db->d_query);
					}
				}
			}
			//Schauen ob der Bot schon Schiffstemplates hat
			$Bot = $db->query('SELECT * FROM FHB_Bot');
			$sdl->debug_info($db->d_query);
			$sdl->debug('SQL: SELECT * FROM FHB_Bot',__LINE__,7);
			$sdl->array_debug($Bot,1);
			$neu_laden=0;
			if($Bot['ship_t_1']==0)
			{
				$neuladen++;
				$sql = 'INSERT INTO ship_templates
		(owner, timestamp, name, description, race, ship_torso, ship_class, component_1, component_2, component_3, component_4, component_5, component_6, component_7, component_8, component_9, component_10,
		value_1, value_2, value_3, value_4, value_5, value_6, value_7, value_8, value_9, value_10, value_11, value_12, value_13, value_14, value_15,
		resource_1, resource_2, resource_3, resource_4, unit_5, unit_6, min_unit_1, min_unit_2, min_unit_3, min_unit_4, max_unit_1, max_unit_2, max_unit_3, max_unit_4, buildtime) VALUES
		("'.$Bot['user_id'].'","'.time().'","Ferengi Handelsschiff- Alpha","Transporter","'.$Bot['user_race'].'",1,0,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,
		"50","50","0","250","250","40","40","40","50","9.99","40","40","1","1","0",
		"200000","200000","200000","40000","5000","5000","5000","2500","2500","5000","5000","2500","2500",2000,0)';
				$sdl->debug('SQL:'.$sql,__LINE__,7);
				if(!$db->query_a($sql))  $sdl->error('Bot ShipsTemps: Temp 1 wurde nicht gespeichert',__LINE__,4);
				$sdl->debug_info($db->d_query);
			}
			if($Bot['ship_t_2']==0)
			{
				$neuladen++;
				$sql= 'INSERT INTO ship_templates
		(owner, timestamp, name, description, race, ship_torso, ship_class, component_1, component_2, component_3, component_4, component_5, component_6, component_7, component_8, component_9, component_10,
		value_1, value_2, value_3, value_4, value_5, value_6, value_7, value_8, value_9, value_10, value_11, value_12, value_13, value_14, value_15,
		resource_1, resource_2, resource_3, resource_4, unit_5, unit_6, min_unit_1, min_unit_2, min_unit_3, min_unit_4, max_unit_1, max_unit_2, max_unit_3, max_unit_4, buildtime) VALUES
		("'.$Bot['user_id'].'","'.time().'","Leichter Jaeger - Alpha","Kampfschiff","'.$Bot['user_race'].'",3,0,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,
		"4000","4000","100","6000","6000","60","60","60","60","9.99","60","60","1","1","0",
		"500000","500000","500000","50000","5000","10000","10000","2500","2500","5000","5000","2500","2500",2000,0)';
				$sdl->debug('SQL:'.$sql,__LINE__,7);
				if(!$db->query_a($sql))  $sdl->error('Bot ShipsTemps: Temp 2 wurde nicht gespeichert',__LINE__,4);
				$sdl->debug_info($db->d_query);
			}
			if($neuladen>0)
			{
				$sdl->log('Schiffstemplates bauen',__LINE__);
				$Bot_temps=$db->query('SELECT id FROM ship_templates s WHERE owner='.$Bot['user_id'].'');
				$sdl->debug('SQL:SELECT id FROM ship_templates s WHERE owner='.$Bot['user_id'].'',__LINE__,7);
				$sdl->debug_info($db->d_query);
				$sdl->array_debug($Bot_temps,1);
				$zaehler_temps=0;
				$Bot_neu = array();
				$Bot_neu[0]=$Bot_temps[0]['id']; //weis nicht ob das geht
				$Bot_neu[1]=$Bot_temps[1]['id'];
				$sql = 'UPDATE FHB_Bot SET ship_t_1 = '.$Bot_neu[0].',ship_t_2 = '.$Bot_neu[1].' WHERE user_id = '.$Bot['user_id'];
				$sdl->debug('SQL:'.$sql,__LINE__,7);
				if(!$db->query_a($sql))  $sdl->error('Bot ShipsTemps: Konnte die Temp id nicht abspeichern',__LINE__,2);
				$sdl->debug_info($db->d_query);
			}
		}else{
			$sdl->array_debug($Bot_exe);
			$sdl->error('Kein zugriff auf die Bot Tabelle=>'.$Bot_exe.'',__LINE__,4);
			$sdl->log('Kein zugriff auf die Bot Tabelle=>'.$Bot_exe,__LINE__);
			exit;
		}
		$sdl->finish_job('Grundsystem',$db->i_query);
		// ########################################################################################
		// ########################################################################################
		//PW des Bot änderns - nix angreifen
		$sdl->start_job('PW ändern',$db->i_query);
		$botdaten = $db->query('SELECT * FROM FHB_Bot LIMIT 0 , 1');
		$wert_new=rand(1,9);
		$zufall="vv";
		if($wert_new==2) $wert_new='v'.$zufall.'h';
		if($wert_new==3) $wert_new='a'.$zufall.'h';
		if($wert_new==4) $wert_new='b'.$zufall.'h';
		if($wert_new==5) $wert_new='c'.$zufall.'h';
		if($wert_new==6) $wert_new='d'.$zufall.'h';
		if($wert_new==7) $wert_new='e'.$zufall.'h';
		if($wert_new==1) $wert_new='f'.$zufall.'h';
		if($wert_new==8) $wert_new='g'.$zufall.'h';
		if($wert_new==9) $wert_new='h'.$zufall.'h';
		if($db->query_a('UPDATE `user` SET `user_password`=MD5("'.$wert_new.'") WHERE `user_id` ='.$botdaten['user_id'].''))
		{
			$sdl->log('Jetzt gibts nur One-Night-Stands, keine Längeren Beziehungen',__LINE__ );
		}
		$sdl->finish_job('PW ändern',$db->i_query);
		// ########################################################################################
		// ########################################################################################
		//Nachrichten beantworten
		$sdl->start_job('Nachrichten beantworten',$db->i_query);
		$anzahl_igms=0;
		$Bot = $db->query('SELECT * FROM FHB_Bot');
		$sdl->debug_info($db->d_query);
		$sdl->array_debug($Bot,1);
		$sql = 'SELECT * FROM message WHERE receiver='.$Bot['user_id'].' AND rread=0';
		$sdl->debug('SQL:'.$sql,__LINE__,7);
		if(!$q_message = $db->query_a($sql))
		{
			$sdl->error('IGM: Konnte Nachrichten nicht querien',__LINE__,2);
		}else{
		$sdl->debug_info($db->d_query);
		while($message = $db->fetchrow($q_message))
		{
			$messaget='<center><b>Guten Tag</b></center>
			<br>
    		<br>
    		Ihre Nachricht an uns wird keine Wirkung erzielen. Wir bearbeiten alles Sachgemäß und gleich.<br>
			Wenn Sie meinen uns erpressen zu müssen, uns unter Druck zu setzen oder sonstige Gewaltgedanken gegen uns haben - vergessen Sie diese sofort wieder.
		<br>
		Da wir uns, was militärische Verteidigung angeht, nicht auf irgendwas einlassen, sind unsere Waffen geladen und unsere Schiffe kampfbereit. Sollte Agression erfolgen, werden wir mit noch stärkerer Härte zurückschlagen.
		<br>
		<br>
		Wenn Sie nur mal Hallo sagen wollten, nehmen Sie das oben als Warnung. Sollten Sie merken, dass unser Liefersystem Problem hat, melden Sie das bitte an die Galaktische Administration (Administratoren des Spiels für die die es nicht verstehen).
    	<br><br>-----------------------------------<br>
		Noch einen schönen Tag wünscht Ihnen die Ferengi Handelsgesselschaft<br>	i.A. Schreiber der kein Gehalt bekommt';
			$this->MessageUser($Bot['user_id'],$message['sender'],'<b>Antwort auf ihr Schreiben</b>',$messaget);
			$anzahl_igms++;
		}
		}
		$sql = 'UPDATE message SET rread=1 WHERE receiver='.$Bot['user_id'];
		$sdl->debug('SQL:'.$sql,__LINE__,7);
		if(!$db->query_a($sql))$sdl->error('Konnte Nachrichten nicht auf gelesen setzen',__LINE__,2);
		$sdl->debug_info($db->d_query);
		$sdl->log('Anzahl Nachrichten:'.$anzahl_igms,__LINE__);
		$sdl->finish_job('Nachrichten beantworten',$db->i_query);

		// ########################################################################################
		// ########################################################################################
		// Shiptrade Scheduler
		//So damit die Truppen dann auch wirklich bezhalt werden müssen, jetzt muss es nur noch tapsicher gemacht werden
		$sdl->start_job('Auktionsscheduler',$db->i_query);
		$alpha_zaehler='0';
		if(!$Bot = $db->query('SELECT * FROM FHB_Bot')) $sdl->error('<b>Kein Zugriff auf Bot Tabelle : SELECT * FROM FHB_Bot',__LINE__,4);
		$sdl->array_debug($Bot,1);
		$sdl->debug_info($db->d_query);
		$sql = 'SELECT s.*,u.user_name,u.num_auctions,COUNT(b.id) AS num_bids FROM (ship_trade s)
		LEFT JOIN (user u) ON u.user_id=s.user
		LEFT JOIN (bidding b) ON b.trade_id=s.id
		WHERE s.scheduler_processed = 0 AND s.end_time <='.$ACTUAL_TICK.' GROUP BY s.id';
		$sdl->debug('SQL:'.$sql,__LINE__,1);
		if(($q_strade = $db->query_a($sql)) === false) {
			$sdl->error('<b>Error:</b> Could not query scheduler shiptrade data! CONTINUED',__LINE__,4);
		}
		else
		{
			$sdl->debug_info($db->d_query);
			$n_shiptrades = 0;
			while($tradedata = $db->fetchrow($q_strade)) 
			{
				$sdl->debug('$tradedata[num_bids]:'.$tradedata['num_bids'],__LINE__,1);
   				if ($tradedata['num_bids']<1)
   				{
   				$sdl->log('shiptrade['.$tradedata['id'].'] had no bids',__LINE__);
   				// Verkäufer:
	    		$log_data=array(
	    		'player_name' => '',
	    		'auction_id' => $tradedata['id'],
	    		'auction_name' => $tradedata['header'],
	    		'resource_1' => 0,
	    		'resource_2' => 0,
	    		'resource_3' => 0,
	    		'unit_1'=>0,
	    		'unit_2'=>0,
	    		'unit_3'=>0,
	    		'unit_4'=>0,
	    		'unit_5'=>0,
	    		'unit_6'=>0,
	    		'ship_id' => $tradedata['ship_id'], );
			add_logbook_entry($tradedata['user'], LOGBOOK_AUCTION_VENDOR, 'Auktionsbenachrichtigung(Erfolgslos)',$log_data);
       			if ($db->query_a('UPDATE ships SET ship_untouchable=0 WHERE ship_id='.$tradedata['ship_id'])!=true)
	    		$sdl->error('<b>Error (Critical)</b>: shiptrade['.$tradedata['id'].']: failed to free ship['.$tradedata['ship_id'].']!',__LINE__,4);
   				}
   				else
   				{
$sdl->debug('SQL: SELECT b.*,u.user_id,u.user_name FROM (bidding b) LEFT JOIN (user u) ON u.user_id=b.user WHERE b.trade_id ="'.$tradedata['id'].'" ORDER BY b.max_bid DESC LIMIT 1',__LINE__,2); 
				$sdl->log('Trade id: '.$tradedata['id'],__LINE__);
   				$hbieter= $db->query('SELECT b.*,u.user_id,u.user_name FROM (bidding b) LEFT JOIN (user u) ON u.user_id=b.user WHERE b.trade_id ="'.$tradedata['id'].'" ORDER BY b.max_bid DESC LIMIT 1');
				
   				$sdl->debug_info($db->d_query);
				$sdl->array_debug($hbieter,1);
   				$endprice[0]=-1;
   				$endprice[1]=-1;
   				$endprice[2]=-1;
   				$endprice[3]=-1;
   				$endprice[4]=-1;
   				$endprice[5]=-1;
   				$endprice[6]=-1;
   				$endprice[7]=-1;
   				$endprice[8]=-1;
   				$sdl->array_debug($endprice,0);
	    		if ($tradedata['num_bids']<2)
	   			{
	    		$end_price[0]=$tradedata['resource_1'];
	    		$end_price[1]=$tradedata['resource_2'];
	    		$end_price[2]=$tradedata['resource_3'];
	    		$end_price[3]=$tradedata['unit_1'];
	    		$end_price[4]=$tradedata['unit_2'];
	    		$end_price[5]=$tradedata['unit_3'];
	    		$end_price[6]=$tradedata['unit_4'];
	    		$end_price[7]=$tradedata['unit_5'];
	    		$end_price[8]=$tradedata['unit_6'];
	    		$sdl->array_debug($end_price,1);
	    		}
	    		else
	    		{
	    		$prelast_bid=$db->queryrow('SELECT * FROM bidding  WHERE trade_id ="'.$tradedata['id'].'" ORDER BY max_bid DESC LIMIT 1,1');
	    		$sdl->array_debug($prelast_bid,1);
	    		$sdl->debug_info($db->d_query);
	    		// Um zu testen, ob ein Gleichstand besteht, dann wird ja nicht max_bid +1
	    		$last_bid=$db->queryrow('SELECT * FROM bidding WHERE trade_id ="'.$tradedata['id'].'" ORDER BY max_bid DESC LIMIT 1');
	    		$sdl->debug_info($db->d_query);
	    		$sdl->array_debug($last_bidd,1);
	    		$sdl->log('Last Bid:'.$last_bid['max_bid'].' and Prelastbid:'.$prelast_bid['max_bid'],__LINE__);
	    		if ($last_bid['max_bid']!=$prelast_bid['max_bid'])
	    		{
	    		$end_price[0]=($tradedata['resource_1']+($prelast_bid['max_bid']+1)*$tradedata['add_resource_1']);
	    		$end_price[1]=($tradedata['resource_2']+($prelast_bid['max_bid']+1)*$tradedata['add_resource_2']);
	    		$end_price[2]=($tradedata['resource_3']+($prelast_bid['max_bid']+1)*$tradedata['add_resource_3']);
	    		$end_price[3]=($tradedata['unit_1']+($prelast_bid['max_bid']+1)*$tradedata['add_unit_1']);
	    		$end_price[4]=($tradedata['unit_2']+($prelast_bid['max_bid']+1)*$tradedata['add_unit_2']);
	    		$end_price[5]=($tradedata['unit_3']+($prelast_bid['max_bid']+1)*$tradedata['add_unit_3']);
	    		$end_price[6]=($tradedata['unit_4']+($prelast_bid['max_bid']+1)*$tradedata['add_unit_4']);
	    		$end_price[7]=($tradedata['unit_5']+($prelast_bid['max_bid']+1)*$tradedata['add_unit_5']);
	    		$end_price[8]=($tradedata['unit_6']+($prelast_bid['max_bid']+1)*$tradedata['add_unit_6']);
	    		$sdl->array_debug($end_price,0);
	    		}
	    		else
	    		{
	    		$end_price[0]=($tradedata['resource_1']+($prelast_bid['max_bid'])*$tradedata['add_resource_1']);
	    		$end_price[1]=($tradedata['resource_2']+($prelast_bid['max_bid'])*$tradedata['add_resource_2']);
	    		$end_price[2]=($tradedata['resource_3']+($prelast_bid['max_bid'])*$tradedata['add_resource_3']);
	    		$end_price[3]=($tradedata['unit_1']+($prelast_bid['max_bid'])*$tradedata['add_unit_1']);
	    		$end_price[4]=($tradedata['unit_2']+($prelast_bid['max_bid'])*$tradedata['add_unit_2']);
	    		$end_price[5]=($tradedata['unit_3']+($prelast_bid['max_bid'])*$tradedata['add_unit_3']);
	    		$end_price[6]=($tradedata['unit_4']+($prelast_bid['max_bid'])*$tradedata['add_unit_4']);
	    		$end_price[7]=($tradedata['unit_5']+($prelast_bid['max_bid'])*$tradedata['add_unit_5']);
	    		$end_price[8]=($tradedata['unit_6']+($prelast_bid['max_bid'])*$tradedata['add_unit_6']);
	    		$sdl->array_debug($end_price,0);
	    		}
	    	}

	    		if ($end_price[0]<0 || $end_price[1]<0 || $end_price[2]<0 || $end_price[3]<0 || $end_price[4]<0 || $end_price[5]<0 || $end_price[6]<0 || $end_price[7]<0 || $end_price[8]<0)
	    {$sdl->error('<b>Error:</b> shiptrade['.$tradedata['id'].'] had '.$tradedata['num_bids'].' bids but didn\'t find end_price',__LINE__,4);}
	    else
	    {
	    	// Verkäufer:
	    	$log_data=array(
	    	'player_name' => $hbieter['user_name'],
	    	'player_id' => $hbieter['user_id'],
	    	'auction_id' => $tradedata['id'],
	    	'auction_name' => $tradedata['header'],
	    	'resource_1' => $end_price[0],
	    	'resource_2' => $end_price[1],
	    	'resource_3' => $end_price[2],
	    	'unit_1' => $end_price[3],
	    	'unit_2' => $end_price[4],
	    	'unit_3' => $end_price[5],
	    	'unit_4' => $end_price[6],
	    	'unit_5' => $end_price[7],
	    	'unit_6' => $end_price[8],
	    	'ship_id' => $tradedata['ship_id'],
	    	);
	    	$sdl->array_debug($log_data,1);
	    	if(!add_logbook_entry($tradedata['user'], LOGBOOK_AUCTION_VENDOR, 'Auktionsbenachrichtigung(Verkauft)',$log_data)) $sdl->error('Logbook konnte nicht geschrieben werden - '.$tradedata['user'],__LINE__,3);;

	    	// Käufer:
	    	$log_data=array(
	    	'player_name' => $tradedata['user'],
	    	'auction_id' => $tradedata['id'],
	    	'auction_name' => $tradedata['header'],
	    	'resource_1' => $end_price[0],
	    	'resource_2' => $end_price[1],
	    	'resource_3' => $end_price[2],
	    	'unit_1' => $end_price[3],
	    	'unit_2' => $end_price[4],
	    	'unit_3' => $end_price[5],
	    	'unit_4' => $end_price[6],
	    	'unit_5' => $end_price[7],
	    	'unit_6' => $end_price[8],
	    	'ship_id' => $tradedata['ship_id'],
	    	);
	    	$sdl->array_debug($log_data,1);
	    	if(!add_logbook_entry($hbieter['user_id'], LOGBOOK_AUCTION_PURCHASER, 'Auktionsbenachrichtigung(Gekauft)',$log_data)) $sdl->error('Logbook konnte nicht geschrieben werden - '.$hbieter['user_id'],__LINE__,3);
		$sdl->log('Bieter:'.$hbieter['user_id'].' and Verkäufer:'.$tradedata['user'],__LINE__);
	    	$sql='INSERT INTO schulden_table (user_ver,user_kauf,ship_id,ress_1,ress_2,ress_3,unit_1,unit_2,unit_3,unit_4,unit_5,unit_6,timestep,auktions_id)
				VALUES ('.$tradedata['user'].','.$hbieter['user_id'].','.$tradedata['ship_id'].','.$end_price[0].','.$end_price[1].','.$end_price[2].','.$end_price[3].','.$end_price[4].','.$end_price[5].','.$end_price[6].','.$end_price[7].','.$end_price[8].','.$ACTUAL_TICK.','.$tradedata['id'].')';
	    	$sdl->debug('SQL:'.$sql,__LINE__,7);
	    	if(!$db->query_a($sql))
	    	{
	    		$sdl->error('<b>Error: (Critical)</b> Schulden wurden nicht gespeichert: "'.$sql.'" - TICK EXECUTION CONTINUED',__LINE__,4);
	    	}else{
	    	$sdl->debug_info($db->d_query);
	    	$code=$db->insert_id();
	    	$sdl->log('[Code]: '.$code.'',__LINE__);
	    	$sql='INSERT INTO treuhandkonto (code,timestep) VALUES ('.$code.','.$ACTUAL_TICK.')';
	    	$sdl->debug('SQL:'.$sql,__LINE__,7);
	    	if(!$db->query_a($sql)) $sdl->error('<b>Auktion</b>Konnte keine Nummer festlegen -'.$code,__LINE__,4);
	    	$sdl->debug_info($db->d_query);
	    	$sql='SELECT * FROM treuhandkonto WHERE code='.$code.'';
	    	$sdl->debug('SQL:'.$sql,__LINE__,7);
	    	$Auktionsnummer = $db->query($sql);
	    	$sdl->array_debug($Auktionsummer,1);
	    	$sdl->debug_info($db->d_query);
	    	//Schiff dem NPC geben
	    	$plani=$Bot['planet_id']*(-1);
	    	$sdl->debug('Planet:'.$plani,__LINE__,1);
	    	$sql='UPDATE ships SET user_id="'.$Bot['user_id'].'", fleet_id="'.$plani.'" WHERE ship_id="'.$tradedata['ship_id'].'"';
	    	$sdl->debug('SQL:'.$sql,__LINE__,7);
	    	$sdl->log('Ramona bekommt ein Schiff ('.$plani.')...'.$sql,__LINE__);	
	    	if(!$db->query_a($sql)) {$sdl->error('<b>Error: (Critical)</b>Konnte schiff nicht dem Bot geben - '.$sql,__LINE__,4);}else{
	    	$sdl->debug_info($db->d_query);
	    	$sdl->log('shiptrade['.$tradedata['id'].'], processed sucessfully',__LINE__);}
		}
	    }
   	}

   	++$n_shiptrades;
			}

			$sql = 'UPDATE ship_trade SET scheduler_processed=1
        	WHERE end_time <= '.$ACTUAL_TICK.' AND scheduler_processed=0
       		LIMIT '.$n_shiptrades;
			$sdl->debug('$n_shiptrades'.$n_shiptrades,__LINE__,1);
			$sdl->debug('SQL:'.$sql,__LINE__,7);
			if(!$db->query_a($sql)) {
    			$sdl->error('<b>Error: (Critical)</b> Could not update scheduler_shiptrade data - TICK EXECUTION CONTINUED -'.$sql,__LINE__,4);
			}
			$sdl->debug_info($db->d_query);
			unset($tradedata);
		}
		$sdl->finish_job('Auktionsscheduler',$db->i_query);

		// ########################################################################################
		// ########################################################################################
		//Treunhand konto ueberpruefen
		$sdl->start_job('Treuhandkonto ueberwachen',$db->i_query);
		$schulden_bezahlt=0;
		$schuldner=0;
		$spassbieter=0;
		$nachrichten_a =0;
		$konten=0;
		$Bot = $db->query('SELECT * FROM FHB_Bot');
		$sdl->debug_info($db->d_query);
		$sdl->array_debug($Bot,1);
		$zeit_raum = 20*24*6;
		$zeit_raum_h = 20*24*3;
		$sdl->debug('Zeit:'.$zeit_raum.' // H:'.$zeit_raum_h,__LINE__,1);
		$sql_a='SELECT * FROM schulden_table WHERE status="0"';
		if(0<$db->num_rows($sql_a))
		{
            
			$sdl->log('Schulden ueberpruefen....',__LINE__);
			
			$sql = 'SELECT * FROM schulden_table WHERE status=0';
			if(($handel = $db->query_a($sql)) === false) {
				$sdl->error('<b>Error:</b> Could not query scheduler shiptrade data! CONTINUED //'.$sql,__LINE__,3);
			}else{
			  $treffera=$db->num_rows($sql);
			  $sdl->debug('Treffer:'.$treffera,__LINE__,1);
				$sdl->debug_info($db->d_query);
				while($schulden= $db->fetchrow($handel))
				{
					$sdl->array_debug($schulden,1);
					if($schulden['id']==null)
					{
						$sdl->log('<b>Error:</b>Keine Id vorhanden',__LINE__);
					}else{
						$sql = 'SELECT * FROM treuhandkonto WHERE code="'.$schulden['id'].'"';
						$sdl->debug('SQL:'.$sql,__LINE__,7);
						$treffer=$db->num_rows($sql);
						if($treffer>1){
							$sdl->error('<b>(Fehler:1000)Programmierfehler:</b>Anscheinend gibt es mehrer Treuhandkonten auf die selbe schulden_table --'.$sql,__LINE__,4);
						}else if($treffer<=0){
							$sdl->error('<b>(Fehler:2000)Bug:</b>Anscheinend gibt es kein Treuhandkonto auf die schulden_table -- '.$sql,__LINE__,4);
						}else if($treffer==1){
							$treuhand=$db->query($sql);
							$sdl->array_debug($treuhand,1);
							//Nun schauen wir ob schon alles bezahlt wurde
							$wert[1]=vergleich($treuhand['unit_1'],$schulden['unit_1']);
							$wert[2]=vergleich($treuhand['unit_2'],$schulden['unit_2']);
							$wert[3]=vergleich($treuhand['unit_3'],$schulden['unit_3']);
							$wert[4]=vergleich($treuhand['unit_4'],$schulden['unit_4']);
							$wert[5]=vergleich($treuhand['unit_5'],$schulden['unit_5']);
							$wert[6]=vergleich($treuhand['unit_6'],$schulden['unit_6']);
							$wert[7]=vergleich($treuhand['ress_1'],$schulden['ress_1']);
							$wert[8]=vergleich($treuhand['ress_2'],$schulden['ress_2']);
							$wert[9]=vergleich($treuhand['ress_3'],$schulden['ress_3']);
							$wert[10]=vergleich($treuhand['code'],$schulden['id']);
							$sdl->array_debug($wert,1);
							$wert_ende=0;
							
							for($aaa=1;$aaa<11;$aaa++)
							{
								if($wert[$aaa]==1)
								{
									$wert_ende=1;
								}
							}
							if($wert_ende==1)
							{
								//Schauen ob jemand seinen Handel versäumt hat
                 					$sdl->log('--//--||=Nicht alles bezahlt=||--\\--||'.$schulden['user_kauf'].'||'.$treuhand['code'].'||',__LINE__);
								if($treuhand['timestep']!=$schulden['timestep'])
								{
									$sdl->error('<b>(Fehler:3000) - Irgendwie haben '.$schulden['user_kauf'].' mit code '.$schulden['id'].'/'.$treuhand['code'].' haben unterschiedliche timesteps',__LINE__,4);
								}else {
									if(($treuhand['timestep']+$zeit_raum_h)<=$ACTUAL_TICK && ($treuhand['timestep']+$zeit_raum)>$ACTUAL_TICK && $schulden['mahnung']==0)
									{
										$sdl->debug('Time: '.$treuhand['timestep']+$zeit_raum_h.' || Tick:'.$ACTUAL_TICK,__LINE__,1);
										//Schauen wer ne Mahnung bekommz
										$schuldner++;
										$User_kauf_V = $db->query('SELECT user_id,user_trade FROM user WHERE user_id="'.$schulden['user_kauf'].'"');
										$sdl->debug_info($db->d_query);
										$sdl->debug('Userkauf[user_trade]:'.$User_kauf_V['user_trade'],__LINE__,1);
										$db->query_a('UPDATE user SET user_trade="'.($User_kauf_V['user_trade']+1).'" WHERE user_id="'.$User_kauf_V['user_id'].'"');
										$sdl->debug_info($db->d_query);
										$messaget='<center><b>Guten Tag</b></center>
										<br>
   										Sie sind dabei die Frist zur Bezahlung zu Überschreiten, für einen Handel den sie Abgeschlossen haben.<br>
   										Hiermit werden sie ermahnt, sollten sie ihre Schulden nicht bezahlen werden entsprechende Maßnahmen eingeleitet.
										<br>--------------------------------------<br>
										Hochachtungsvoll Ferengi Handelsgilde';
										$this->MessageUser($Bot['user_id'],$schulden['user_kauf'],'<b>Mahnung die Erste</b>',$messaget);
										$nachrichten_a++;
										//User bekommt verwarnung
										if(!$db->query_a('UPDATE schulden_table SET mahnung=mahnung+1 WHERE id="'.$treuhand['code'].'" and user_ver="'.$schulden['user_ver'].'"')) $sdl->error('Konnte Mahnung nicht schreiben -- UPDATE schulden_table SET mahnung=mahnung+1 WHERE id="'.$treuhand['code'].'" and user_ver="'.$schulden['user_ver'].'"',__LINE__,2);
										$sdl->debug_info($db->d_query);
									}
									if($ACTUAL_TICK>=($zeit_raum+$treuhand['timestep']))
									{

										$spassbieter++;
										$konten++;

										$sdl->debug_info($db->d_query);
										//Einträge löschen
										$sql_1 = 'DELETE FROM schulden_table WHERE user_ver="'.$schulden['user_ver'].'" and user_kauf="'.$schulden['user_kauf'].'" and id="'.$schulden['id'].'"';
										$sdl->debug('SQL:'.$sql_1,__LINE__,7);
										if(!$db->query_a($sql_1)) 	$sdl->error('<b>(Fehler:5000)ERROR-DELETE</b> <>'.$sql_1.'',__LINE__,4);
										$sdl->debug_info($db->d_query);
										$sql_2 = 'DELETE FROM treuhandkonto WHERE code="'.$schulden['id'].'"';
										$sdl->debug('SQL:'.$sql_2,__LINE__,7);
										if(!$db->query_a($sql_2)) 	$sdl->error('<b>(Fehler:5000)ERROR-DELETE</b> <>'.$sql_2.'',__LINE__,4);
										$sdl->debug_info($db->d_query);


										$sql_1='INSERT INTO FHB_sperr_list VALUES(null,'.$schulden['user_kauf'].','.$ACTUAL_TICK.')';
										$sdl->debug('SQL:'.$sql_1,__LINE__,7);
										
										if(!$db->query_a($sql_1)) $sdl->error('<b>Keinen Eintrag/b>User:'.$schulden['user_kauf'].' - bekam keine weitere user trade <>'.$sql_x.'',__LINE__,4);
										$sdl->debug_info($db->d_query);
										//So jetzt noch beide Benachrichtigen
										//TODO Log buch machen - grund wieso es noch nicht gemacht wurde:
										/*
										 [01:38] <Tobi|away> Nachricht oder Log?
										 [01:38] <Mojo1987> log
										 [01:39] <Tobi|away> hm
										 [01:39] <Tobi|away> hast du schonmal logbuch gemacht?
										 [01:40] <Mojo1987> nee von log hab ich keinen schimmer :D
										 */
										$nachrichten_a++;
										$messaget='<center><b>Guten Tag</b></center>
										<br>
   									Sie haben die Frist zur Bezahlung ihrer Schulden Überschritten, damit wird der Handel Rückgängig gemacht. Sie erhalten dafür einen Eintrag in das Schuldnerbuch.<br>
										Gesamt Einträge:'.$User_kauf_V['user_trade'].'<br>
										Sollten sie weiter Auffallen wird das ernsthafte Konsequenzen für sie haben.<br>
										Dieser Beschluss ist Gültig, sollten sie das Gefühl haben ungerecht behandelt zu werden, können sie sich über den normalen Beschwerde Weg beschweren.
										<br>--------------------------------------<br>
										Hochachtungsvoll Ferengi Handelsgilde';
										$this->MessageUser($Bot['user_id'],$schulden['user_kauf'],'<b>Mahnung mit Folgen</b>',$messaget);

										

										$sdl->debug_info($db->d_query);

											$sdl->log('User: '.$schulden['user_ver'].' bekommt sein Schiff: '.$schulden['ship_id'],__LINE__);
											$sql_c='INSERT INTO `FHB_warteschlange` VALUES (NULL , '.$schulden['user_ver'].', '.$schulden['ship_id'].')';
											if(!$db->query_a($sql_c))   $sdl->error('<b>Error: (Critical)</b>Konnte schiff nicht in die Warteschleife tun //'.$sql_c,__LINE__,4);
											$sdl->debug('SQL:'.$sql_c,__LINE__,7);	
											$sdl->debug_info($db->d_query);
										$nachrichten_a++;	
										$user_name=$db->query('SELECT user_name FROM user WHERE user_id='.$schulden['user_kauf'].'');
										$message_b ='<center><b>Guten Tag</b></center>
    									<br>
    									Ihr Handel mit '.$user_name.' wurde rückgängig gemacht, ihr Schiff steht ihnen absofort wieder zurverfügung. Sollte es jedoch nicht wieder zurverfügung stehen, wenden sie sich bitte an den Support.
    									<br>Um ihren Handelspartner kümmern wir uns schon. Er wird eine gerechte Strafe bekommen<br>
    									<br>--------------------------------------<br>
    									Hochachtungsvoll Ferengi Handelsgilde';
										$this->MessageUser($Bot['user_id'],$schulden['user_ver'],'<b>Handel'.$schulden['code'].' ist nichtig</b>',$message_b);
									}
								}
							}
							elseif($wert_ende==0)
							{
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['unit_1'].'||'.$schulden['unit_1'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['unit_2'].'||'.$schulden['unit_2'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['unit_3'].'||'.$schulden['unit_3'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['unit_4'].'||'.$schulden['unit_4'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['unit_5'].'||'.$schulden['unit_5'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['unit_6'].'||'.$schulden['unit_6'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['ress_1'].'||'.$schulden['ress_1'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['ress_2'].'||'.$schulden['ress_2'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$treuhand['ress_3'].'||'.$schulden['ress_3'].'||',__LINE__);
							$sdl->log('!!!||=Vergleich--\\--'.$schulden['id'].'||'.$treuhand['code'].'||',__LINE__);
								//Schulden auf fertig stellen und sichtbar machen
								$schulden_bezahlt++;
								$sql_1 ='UPDATE schulden_table SET status="1" WHERE id='.$treuhand['code'].'';
								$sdl->debug('SQL:'.$sql_1,__LINE__,7);
								if(!$db->query_a($sql_1)) {
									$sdl->error('schulden_table, konnte bei '.$sql.' = status nicht aendern',__LINE__,4);
								}
								$messaget_c='<center><b>Guten Tag</b></center>
										<br>
   										Sie haben Neue Ressourcen und/oder Truppen auf ihrem Treuhandkonto.
										<br>--------------------------------------<br>
										Hochachtungsvoll Ferengi Handelsgilde';

								$sdl->debug_info($db->d_query);
								//Ship dem user geben
								$sdl->log('User: '.$schulden['user_kauf'].' bekommt ein Schiff: '.$schulden['ship_id'],__LINE__);
								$messaget_a='<center><b>Guten Tag</b></center>
										<br>
   										Sie können ihr ersteigertes Schiff abhohlen.
										<br>--------------------------------------<br>
										Hochachtungsvoll Ferengi Handelsgilde';

								$sql_c='INSERT INTO `FHB_warteschlange` VALUES (NULL , '.$schulden['user_kauf'].', '.$schulden['ship_id'].')';
								if(!$db->query_a($sql_c))   $sdl->error('<b>Error: (Critical)</b>Konnte schiff nicht in die Warteschleife tun //'.$sql_c,__LINE__,4);
								$sdl->debug('SQL:'.$sql_c,__LINE__,7);	
								$sdl->debug_info($db->d_query);
								$konten++;

							}
						}
					}
				}
			}
		}else{
			$sdl->log('[MELDUNG]Keine Schulden vorhanden',__LINE__);
		}
		//Leere Konton destroyen
		if(($konton_destroy=$db->query_a('SELECT * FROM schulden_table WHERE status=2'))==true){
		while($konton_destroy_t=$db->fetchrow($konton_destroy)){
		$sql_e='DELETE FROM schulden_table WHERE id='.$konton_destroy_t['id'].' AND status=2';
								if(!$db->query_a($sql_e))
								{
									$sdl->error('<b>Error: (Critical)</b>Konnte eintrag schulden_table nicht löschen //Code:'.$konton_destroy_t['id'].'//'.$sql_c,__LINE__,4);
								}else{
									$sql_d='DELETE FROM treuhandkonto WHERE code="'.$konton_destroy_t['id'].'"';
									if(!$db->query_a($sql_d))
									{
										$sdl->error('<b>Error: (Critical)</b>Konnte eintrag treuhandkonto nicht löschen //Code:'.$konton_destroy_t['id'].'//'.$sql_c,__LINE__,4);
										$sdl->manuel($sql_c);
									}
								}
                }
								}
  else{$sdl->error('[Leere Konten] Konten Abfrage ging nicht || '.$konton_destroy.' = status nicht aendern',__LINE__,4);}
		$sdl->log('Bezahlte schulden: '.$schulden_bezahlt,__LINE__);
		$sdl->log('Anzahl Schuldner: '.$schuldner,__LINE__);
		$sdl->log('Geöschte Konten: '.$schuldner,__LINE__);
		$sdl->log('Anzahl Spassbieter: '.$spassbieter,__LINE__);
		$sdl->log('Anzahl Nachrichten: '.$nachrichten_a++,__LINE__);
		$sdl->finish_job('Treunhandkonto ueberwachen',$db->i_query);	
		// ########################################################################################
		// Börsenkurse ausrechenen
		// Where tick und Klasse
		$sdl->start_job('Börse Schiffshandel',$db->i_query);
		$sql_1 = array();
		$sql_2 = array();
		$sql_3 = array();
		$min_tick=$ACTUAL_TICK-(20*24);
		$sdl->debug('Min Tick:'.$min_tick,__LINE__,1);
		if($min_tick<0) $min_tick=0;
		$max_tick=$ACTUAL_TICK;
		$sdl->debug('Max Tick:'.$max_tick,__LINE__,1);
		$new_preis = $ACTUAL_TICK%20;
		$new_preis=($new_preis==0) ? 'true' : 'false';
		$sdl->log('Aktueller Tick:'.$ACTUAL_TICK.' -- '.$new_preis.' -- Zeitraum von:'.$min_tick.'',__LINE__);

		if($new_preis=='true')
		{
			$sdl->log('Graph wird neu gemacht.....',__LINE__);
			include("simple_graph.class.php");
			exec('cd '.FILE_PATH_hg.'kurs/; rm *.png');
			
			$sdl->start_job('Kauf - Unit',$db->i_query);
			$this->graph_zeichnen("unit_1",$ACTUAL_TICK);
			$this->graph_zeichnen("unit_2",$ACTUAL_TICK);
			$this->graph_zeichnen("unit_3",$ACTUAL_TICK);
			$this->graph_zeichnen("unit_4",$ACTUAL_TICK);
			$this->graph_zeichnen("unit_5",$ACTUAL_TICK);
			$this->graph_zeichnen("unit_6",$ACTUAL_TICK);
			$sdl->finish_job('Kauf - Unit',$db->i_query);
		}				

		// ########################################################################################
		// ########################################################################################
		//User freigeben fürs HZ
		$sdl->start_job('User freigeben',$db->i_query);
		$sql = "SELECT user_id,user_trade,trade_tick FROM user WHERE user_trade>0 AND trade_tick<=".$ACTUAL_TICK." AND trade_tick!=0";
		if(!$temps=$db->query_a($sql)) $sdl->error('User abfragen schieffgelaufen -- '.$sql,__LINE__,2);;
		$sdl->debug_info($db->d_query);
		$anzahl_freigeben=0; 
		while($result = $db->fetchrow($temps))
		{
			$sdl->array_debug($result,1);
			//[23:19] <Secius> da stehe ne sql anweisung
			//[23:19] <Secius> abernichts sendet sie zurDB
			//[23:19] <Mojo1987> lol
			//[23:19] <Mojo1987> das gut^^
			$sql_x='UPDATE user SET trade_tick=0 WHERE user_id="'.$result['user_id'].'"';
			$sdl->log('User:'.$result['user_id'].' bekam die Freiheit um den Frauen dieser Galaxy das Fürchten zu leeren',__LINE__);
			if(!$db->query_a($sql_x)) $sdl->error('Fehler beim updaten des Users --'.$sql_x,__LINE__,3);
			$sdl->debug_info($db->d_query);
			$anzahl_freigeben++;
			$message_b ='<b>Ihr HZ-Bann ist zu ende</b>
    		<br>
    		Sollten sie keine Zugriff haben, bitte bei den Supportern melden - dazu die Uhrzeit angeben wo sie diese Nachricht bekommen haben. Sonst kann ihnen nicht geholfen werden.
    		<br>--------------------------------------<br>
    		Vorsitzender des Ferengi Finanz- und Handelsministeriums';
			$this->MessageUser($Bot['user_id'],$result['user_id'],'<b>HZ-Entbannung</b>',$message_b);			
		}
		$sdl->log('Es wurden '.$anzahl_freigeben.' User freigegeben',__LINE__);
		$sdl->finish_job('User freigeben',$db->i_query);
		// ########################################################################################
		// ########################################################################################
		//User sperren fürs HZ
		$sdl->start_job('User Sperren',$db->i_query);
		$sql = "SELECT count(*) as anzahl,user_id FROM FHB_sperr_list GROUP By user_id";
		if(!$temps=$db->query_a($sql)) $sdl->error('User abfragen schieffgelaufen -- '.$sql,__LINE__,2);
		$sdl->debug_info($db->d_query);
		$anzahl_sperren=0; 
		$user_liste='';
		while($result = $db->fetchrow($temps))
		{
			$sdl->array_debug($result,1);
			$sperre=0;
			if($result['anzahl']>0)
			{
				if($result['anzahl']<3)
				{
					$sperre=$result['anzahl']*480*2;
				}else if($result['anzahl']>2)
				{
					$sperre=(pow($result['anzahl'],2))*360;
				}
				$sql_abfrage_1='SELECT user_id,user_trade,trade_tick FROM user WHERE user_id="'.$result['user_id'].'"';

				if(!($sql_abfrage_1=$db->query($sql_abfrage_1))) $sdl->error('User abfragen schieffgelaufen -- '.$sql_abfrage_1,__LINE__,2);
				$sdl->array_debug($sql_abfrage_1,1);

				$sdl->debug_info($db->d_query);
				if($sql_abfrage_1['user_trade']<$result['anzahl'] && $sql_abfrage_1['trade_tick']!=0)
				{
					$anzahl_sperren++;
					$sql_x='UPDATE user SET user_trade='.$result['anzahl'].',trade_tick=trade_tick+'.$sperre.' WHERE user_id="'.$result['user_id'].'"';
					$sdl->debug('SQL:'.$sql_x,__LINE__,7);
					$sdl->log('User:'.$result['user_id'].' bekamm eine höhere Strafe - Böse sind wir, die denen Unrecht getan wurde',__LINE__);
					if(!$db->query_a($sql_x)) $sdl->error('User '.$result['user_id'].' bekamm nicht seine Spere von '.$sperre.' ticks</b>',__LINE__,3);
					$sdl->debug_info($db->d_query);
					$message_b ='<b>Sie haben einen Bann fürs HZ bekommen</b>
    				<br>
    				Aufgrund ihrer erneuten Schulden bei Auktionen bekommen sie eine weitere Sperre von '.$sperre.' Ticks.<br>
				Beschwerden sind sinnlos. Einfach das nächste mal bezahlen.<br><br>
				Der Grund kann aber auch fehlverhalten im HZ sein.
    				<br>--------------------------------------<br>
    				Vorsitzender des Ferengi Finanz- und Handelsministeriums';
					$this->MessageUser($Bot['user_id'],$result['user_id'],'<b>HZ-Bann</b>',$message_b);						
		
				}else if($sql_abfrage_1['user_trade']<$result['anzahl'] && $sql_abfrage_1['trade_tick']==0)
				{
					$anzahl_sperren++;
					$endtick=$sperre+$ACTUAL_TICK;
					$sql_x='UPDATE user SET user_trade='.$result['anzahl'].',trade_tick='.($sperre+$ACTUAL_TICK).' WHERE user_id="'.$result['user_id'].'"';
					$sdl->debug('SQ1796L:'.$sql_x,__LINE__,7);
					$sdl->log('User:'.$result['user_id'].' wurde gebannt - Auf das er seiner Strafe gerecht werde',__LINE__);
					if(!$db->query_a($sql_x)) $sdl->error('User '.$result['user_id'].' bekamm nicht seine Spere von '.($sperre*3).' Minuten.</b>',__LINE__,3);
					$sdl->debug_info($db->d_query);
					$message_b ='<b>Sie haben einen Bann fürs HZ bekommen</b>
    				<br>
    				Aufgrund ihrer Schulden bei Auktionen bekommen sie eine Sperre von '.($sperre*3).' Minuten.<br>
				Beschwerden sind sinnlos. Einfach das nächste mal bezahlen.
				<br><br>
				Der Grund kann aber auch fehlverhalten im HZ sein.
    				<br>--------------------------------------<br>
    				Vorsitzender des Ferengi Finanz- und Handelsministeriums';
					$this->MessageUser($Bot['user_id'],$result['user_id'],'<b>HZ-Bann</b>',$message_b);	
				}
				else if($sql_abfrage_1['user_trade']==$result['anzahl'])
				{
					$user_liste.='| '.$result['user_id'].' |';

				}
				
				
			}
			
		}
		$sdl->log('User '.$user_liste.' haben schon ihre Strafen und lassen mich nun mein Problem in sachen Frauen klären.',__LINE__);
		$sdl->log('Es wurden '.$anzahl_sperren.' User gesppert',__LINE__);
		$sdl->finish_job('User Sperren',$db->i_query);
		// ########################################################################################
		$Bot = $db->query('SELECT * FROM FHB_Bot'); ########################################################################################		
		// ########################################################################################
		 ########################################################################################
		// ########################################################################################
		//FHB_Sperrliste aufräumen
		$sdl->start_job('Karteileichen in der Sperrliste entfernen',$db->i_query);
		$sql = "SELECT count(*) as anzahl,user_id FROM FHB_sperr_list GROUP By user_id";
		if(!$temps=$db->query_a($sql)) $sdl->error('User abfragen schieffgelaufen -- deleten '.$sql,__LINE__,2);
		$sdl->debug_info($db->d_query);
		$anzahl_sperren=0; 
		while($result = $db->fetchrow($temps))
		{
			$sql_select='SELECT user_id FROM user WHERE user_id='.$result['user_id'].'';
			if($db->num_rows($sql_select)<=0)
			{
				if(!$db->query_a('DELETE FROM FHB_sperr_list WHERE user_id='.$result['user_id'].'')) $sdl->error('Could not Delete Schulden of dead user '.$sql,__LINE__,2);
				$sdl->log('Strafen von User: '.$result['user_id'].' gelöscht',__LINE__);
			}
		}
		$sdl->finish_job('Karteileichen in der Sperrliste entfernen',$db->i_query);
		 ########################################################################################
		//Lernen ist langweilig hier das cheaten von ress durch truppen verkauf behoben
		$sdl->start_job('Soldier Transaktion',$db->i_query);
		$transaktionen=0;
		$sql='SELECT * FROM FHB_cache_trupp_trade WHERE tick<='.$ACTUAL_TICK.'';
		$sql=$db->query_a($sql);
		while($cache_trade = $db->fetchrow($sql))
		{
			$transaktionen++;
			$db->lock('FHB_Handels_Lager');
			$update_action='UPDATE FHB_Handels_Lager SET unit_1=unit_1+'.$cache_trade['unit_1'].',unit_2=unit_2+'.$cache_trade['unit_2'].',unit_3=unit_3+'.$cache_trade['unit_3'].',unit_4=unit_4+'.$cache_trade['unit_4'].',unit_5=unit_5+'.$cache_trade['unit_5'].',unit_6=unit_6+'.$cache_trade['unit_6'].' WHERE id=1';
			if(!$db->query_a($update_action))$sdl->error('Could not update Handelslager - '.$update_action,__LINE__,2);
			$db->unlock('FHB_Handels_Lager');
			$delete_action='DELETE FROM FHB_cache_trupp_trade WHERE id='.$cache_trade['id'].'';
			if(!$db->query_a($delete_action))$sdl->error('Could not update Handelslager - '.$delete_action,__LINE__,2);
			
		}
		$sdl->log('Transaktionen: '.$transaktionen.'',__LINE__);
		$sdl->finish_job('Soldier Transaktion',$db->i_query);
		/*
		FHB_stats graphen zeichen
		*/
		 ########################################################################################
		//Sensoren Überwachen und user anschreiben
 		$sdl->start_job('Sensoren überwachen',$db->i_query);
 		$anzahl_sesn_igms=0;
		$angreifer='SELECT * FROM `scheduler_shipmovement` WHERE user_id>9 AND   	
move_status=0 AND move_exec_started!=1 AND move_finish>'.$ACTUAL_TICK.' AND  dest="'.$Bot['planet_id'].'"';
		$angreifer=$db->query_a($angreifer);
		$sdl->debug_info($db->d_query);
		while($result = $db->fetchrow($angreifer))
		{	
			$sdl->array_debug($result,1);
			$sdl->log('Der User '.$result['user_id'].' will doch erhlich seine Fleet zerschrotten - noobs, alles noobs die ganze Gala und Tap ist ober Noob',__LINE__);
			$anzahl_sesn_igms++;
			$ziel_planet='SELECT planet_owner,planet_name FROM `planets` WHERE planet_owner ='.$result['user_id'].' LIMIT 0 , 1';
			$ziel_planet=$db->query($ziel_planet);
			$message_b ='<center><b>Stellen Sie sofort den Angriff ein!</b></center>
    		<br>
    		<br>
    		Sie sind auf unseren Senoren erschienen. Unsere Flotten sind auf Abfangkurs.<br><br>
			Ein Weiterfliegen hätte einen Krieg zur Folge, den wir ohne Rücksicht auf Verluste gegen Sie und ihre Verbündeten führen werden.
			<br>Es sind 5 Kleine Angriffsgeschwarder unterwegs zu ihrem Planeten '.$ziel_planet['planet_name'].'.
    		<br>--------------------------------------<br>
    		Commander der Alpha-Flotte des Handelsimperiums';
			$this->MessageUser($Bot['user_id'],$result['user_id'],'<b>Sie sind auf unseren Sensoren</b>',$message_b);						
		}
		$sdl->log('Anzahl Nachrichten:'.$anzahl_sesn_igms,__LINE__);
		$sdl->finish_job('Sensoren überwachen',$db->i_query);
 		// ########################################################################################
		// ########################################################################################
		//Schiffe erstellen
		$sdl->start_job('Schiffe erstellen',$db->i_query);
		$abfragen='SELECT * FROM `ship_fleets` WHERE fleet_name="Alpha-Flotte IVX" and user_id='.$Bot['user_id'].' LIMIT 0, 1';
		if($db->num_rows($abfragen)<=0)
		{
			$sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
                    VALUES ("Alpha-Flotte IVX", '.$Bot['user_id'].', '.$Bot['planet_id'].', 0, 4000)';
			if(!$db->query_a($sql)) $sdl->error('Could not insert new fleets data',__LINE__,2);
			$sdl->debug_info($db->d_query);
			$fleet_id = $db->insert_id();
			$sdl->debug('Fleedid:'.$fleet_id,__LINE__,1);
			$sql_x5= 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, move_id, n_ships)
                    VALUES ("Abfang-Flotte-Omega", '.$Bot['user_id'].', '.$Bot['planet_id'].', 0, 1000)';                  
			
			if(!$db->query_a($sql_x5)) $sdl->error('Could not insert new fleets data',__LINE__,2);
			$sdl->debug_info($db->d_query);
			$fleet_id_x5= $db->insert_id();
			$sdl->debug('Fleedid:'.$fleet_id_x5,__LINE__,1);
			if(!$fleet_id) $sdl->error('Error - '.$fleet_id.' = empty',__LINE__,2);
			if(!$fleet_id_x5) $sdl->error('Error - '.$fleet_id.' = empty',__LINE__,2);
			$sql_a= 'SELECT * FROM ship_templates WHERE id = '.$Bot['ship_t_1'];
			$sql_b= 'SELECT * FROM ship_templates WHERE id = '.$Bot['ship_t_2'];
			if(($stpl_a = $db->query($sql_a)) === false) $sdl->error('Could not query ship template data - '.$sql_a,__LINE__,2);
			if(($stpl_b = $db->query($sql_b)) === false) $sdl->error('Could not query ship template data - '.$sql_b,__LINE__,2);
			$units_str_1 = $stpl_a['min_unit_1'].', '.$stpl_a['min_unit_2'].', '.$stpl_a['min_unit_3'].', '.$stpl_a['min_unit_4'];
			$units_str_2 = $stpl_b['min_unit_1'].', '.$stpl_b['min_unit_2'].', '.$stpl_b['min_unit_3'].', '.$stpl_b['min_unit_4'];
	 		$sql_c= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
            VALUES ('.$fleet_id.', '.$Bot['user_id'].', '.$Bot['ship_t_1'].', '.$stpl_a['value_9'].', '.$stpl_a['value_5'].', '.$game->TIME.', '.$units_str_1.')';    
	 		$sql_d= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
            VALUES ('.$fleet_id.', '.$Bot['user_id'].', '.$Bot['ship_t_2'].', '.$stpl_b['value_9'].', '.$stpl_b['value_5'].', '.$game->TIME.', '.$units_str_2.')';
	 		$sql_x55= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
            VALUES ('.$fleet_id_x5.', '.$Bot['user_id'].', '.$Bot['ship_t_2'].', '.$stpl_b['value_9'].', '.$stpl_b['value_5'].', '.$game->TIME.', '.$units_str_2.')';
	 		for($i = 0; $i < 4000; ++$i)
	 		{
	 			if($i<400){
	 				if(!$db->query_a($sql_c)) {
	 					$sdl->error('Could not insert new ships #'.$i.' data',__LINE__,2);
	 				}
	 			}else{
	 				if(!$db->query_a($sql_d)) {
	 					$sdl->error('Could not insert new ships #'.$i.' data',__LINE__,2);
	 				}
	 			}
	 		}
			for($i = 0; $i < 1000; ++$i)
			{
	 			if(!$db->query_a($sql_x55)) {
	 				$sdl->error('Could not insert new ships #'.$i.' data',__LINE__,2);
	 			}
	 		}
	 		$sdl->error('Fleed: '.$fleet_id.' - 2000 Schiffe erstellt',__LINE__,2);
		}
		$flotte=$db->query($abfragen);
		if($flotte['n_ships']<4000)
		{
			$gebraucht=4000-$flotte['n_ships'];
			$sql = 'UPDATE ship_fleets SET n_ships = n_ships + '.$gebraucht.' WHERE fleet_id = '.$flotte['fleet_id'];
			if(!$db->query_a($sql)) $sdl->error('Could not update new fleets data',__LINE__,2);
			$sql_b= 'SELECT * FROM ship_templates WHERE id = '.$Bot['ship_t_2'];
			if(($stpl = $db->query($sql_b)) === false) $sdl->error('Could not query ship template data - '.$sql_b,__LINE__,2);
			$units_str = $stpl['min_unit_1'].', '.$stpl['min_unit_2'].', '.$stpl['min_unit_3'].', '.$stpl['min_unit_4'];
			$sql_d= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time, unit_1, unit_2, unit_3, unit_4)
            VALUES ('.$flotte['fleet_id'].', '.$Bot['user_id'].', '.$Bot['ship_t_2'].', '.$stpl['value_9'].', '.$stpl['value_5'].', '.$game->TIME.', '.$units_str.')';
			for($i = 0; $i < $gebraucht; ++$i)
			{
	 				if(!$db->query_a($sql_d)) {
	 					$sdl->error('Could not insert new ships #'.$i.' data',__LINE__,2);
	 				}
	 		}
	 		$sdl->log('Fleed: '.$flotte['fleet_id'].' -um '.$gebraucht.' Schiffe upgedatet',__LINE__);
		}
   		$sdl->finish_job('Schiffe erstellen',$db->i_query); 
	
        // ########################################################################################
		// ########################################################################################
		
		$sdl_2->finish_job('Bot Lebensgeschichte',$db->i_query);
		$sdl->footer($db->i_query,$db->false_querys);
		$db->close();
		if($debuggen==1)echo "Test mit dem Titel '<".$title.">' beendet";
	}
	function graph_zeichnen($art,$ticker,$stand="",$temp_id="")
	{

		$Umwelt = mysql_query('SELECT * FROM config LIMIT 0 , 1');
		while($Umwelt_t=mysql_fetch_array($Umwelt))
		{$ACTUAL_TICK = $Umwelt_t['tick_id'];}

		$sql='SELECT '.$art.', tick FROM FHB_handel_log WHERE art=1  ORDER BY `tick` ASC  ';
		$sql=mysql_query($sql);
		$num_rows = mysql_num_rows($sql);
		$wert=0;
		$zaehler=0;
		$zaehlera=0;
		$end_tick=0;
		$zw_zw_zw=0;
		$start_tick=0;
		$ticker=$ACTUAL_TICK;
		$zeit=(($ACTUAL_TICK-24176)*3)/60;
		$zeit=(int)$zeit;
		while($daten=mysql_fetch_array($sql))
		{
		if($tick==0)$tick=$daten['tick'];
		if($end_tick==0)$end_tick=$daten['tick']+80;
		if($start_tick==0)$start_tick=$daten['tick'];
		if($daten['tick']>$ticker) break;
		if($end_tick<=$daten['tick'] || $daten['tick']==$ACTUAL_TICK || $num_rows==$zaehler)
			{
			$arr[$zaehlera]['size']=$wert;
			$stunden=($ticker-$daten['tick'])*3;
			$minuten_start=($ticker-$start_tick)*3;
			$minuten_ende=($ticker-$end_tick)*3;
			$minuten_start_x=($start_tick)*3;
			$minuten_ende_y=($end_tick)*3;
			$stunde=date("H");
			$start=floor(($minuten_start)/60);
			$ende=floor(($minuten_ende)/60);

			$ergebniss_start=($stunde+2)-($start-((floor($start/24))*24));
			if($ergebniss_start<0)$ergebniss_start=24+($ergebniss_start);
			$ergebniss_ende=($stunde+2)-($ende-((floor($ende/24))*24));
			if($ergebniss_ende<0)$ergebniss_ende=24+($ergebniss_ende);
		
			$arr[$zaehlera]['name']=$ergebniss_start.'h-'.$ergebniss_ende.'h';
			$tick=0;
			$zaehlera++;
			$end_tick=$end_tick+80;
			$start_tick=$end_tick;
			$wert=0;
			}
		$zaehler++;
		$wert+=$daten[$art];

		}
		$zaehlera=$zaehlera-7;
		for($aa=0;$aa<7;$aa++)
		{
			$ausgabe[$aa]['size']=$arr[$zaehlera]['size'];
			$ausgabe[$aa]['name']=$arr[$zaehlera]['name'];
			$zaehlera++;
		}
		//zuerst einmal ohne Where, vielleicht ein zaehler um das Problem zu lösen? Aber Tap ist ja wieder offline - wegen seinem motorrad
		$simpleGraph2 = &new simpleGraph();
		$simpleGraph2->create("430", "200");
		$simpleGraph2->headline("Verkaufszahlen von ".$art);
		$simpleGraph2->line($ausgabe);
		$simpleGraph2->showGraph(FILE_PATH_hg."kurs/".$art."_.png");
		unset($arr);
		unset($ausgabe);
	} 
		
}	
	
		
?>
