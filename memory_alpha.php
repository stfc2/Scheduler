<?php
/*
    This file is part of STFC.it
    Copyright 2008-2017 by Andrea Carolfi (carolfi@stfc.it) and
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

//########################################################################################
//########################################################################################
class MemoryAlpha extends NPC {
    public function Install($log = INSTALL_LOG_FILE_NPC) {
        $this->sdl->start_job('Memory Alpha Archive System', $log);
        
        $this->db->query('INSERT INTO memory_alpha_triggers SET id = 1');
        
        $this->db->query('INSERT INTO memory_alpha_storage SET id = 1, alpha_charted = "", alpha_tform = "", alpha_cships = "", alpha_off = ""');
        
        $this->sdl->finish_job('Memory Alpha Archive System', $log);
    }
    
    public function Execute($debug=0) {
        $starttime = ( microtime() + time() );
        
        $this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
            '<b>Starting Memory Alpha Archive Tutor at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);
        
        if($debug){
            $this->sdl->log('Archive opening', TICK_LOG_FILE_NPC);
        }
        
        $sql = 'SELECT * FROM memory_alpha_triggers WHERE id = 1';
        
        if(!($triggers = $this->db->queryrow($sql)))
        {
            $this->sdl->log('<b>Error:</b> Tutor: cannot open main DB', TICK_LOG_FILE_NPC);
        }
        else {
            // Main
            if($triggers['trigger_1'] == 1) {
                $this->sdl->start_job('Sistemi esplorati', TICK_LOG_FILE_NPC);                
                
                if(!$this->f_trigger_1()) {
                    $this->sdl->log('Tutor: f_trigger_1 fail!!!', TICK_LOG_FILE_NPC);
                }
                
                $this->sdl->finish_job('Sistemi esplorati', TICK_LOG_FILE_NPC);
            }

            if($triggers['trigger_2'] == 1) {
                $this->sdl->start_job('Pianeti terraformati', TICK_LOG_FILE_NPC);                
                
                if(!$this->f_trigger_2()) {
                    $this->sdl->log('Tutor: f_trigger_2 fail!!!', TICK_LOG_FILE_NPC);
                }
                
                $this->sdl->finish_job('Pianeti terraformati', TICK_LOG_FILE_NPC);
            }

            if($triggers['trigger_3'] == 1) {
                $this->sdl->start_job('Navi Capitali costruite', TICK_LOG_FILE_NPC);                
                
                if(!$this->f_trigger_3()) {
                    $this->sdl->log('Tutor: f_trigger_3 fail!!!', TICK_LOG_FILE_NPC);
                }
                
                $this->sdl->finish_job('Navi Capitali costruite', TICK_LOG_FILE_NPC);
            }

            if($triggers['trigger_4'] == 1) {
                $this->sdl->start_job('Ufficiali Comandanti', TICK_LOG_FILE_NPC);                
                
                if(!$this->f_trigger_4()) {
                    $this->sdl->log('Tutor: f_trigger_4 fail!!!', TICK_LOG_FILE_NPC);
                }
                
                $this->sdl->finish_job('Ufficiali Comandanti', TICK_LOG_FILE_NPC);
            }            
            // Fine Main
        }        
        
        $this->sdl->log('<b>Tutor finished in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
    }
    
    private function f_trigger_1() {
        $sql = 'SELECT user_id, user_name, user_charted FROM user WHERE user_active = 1 AND user_auth_level = 1 AND user_charted > 0 ORDER BY user_charted DESC LIMIT 0,10';
        $results = $this->db->queryrowset($sql);
        foreach ($results AS $key => $res_item) {
            $charted_rank[$key] = array('user_id'       => $res_item['user_id'],
                                          'user_name'     => $res_item['user_name'],
                                          'user_charted'  => $res_item['user_charted']
                                   );
        }
        
        $sql = 'UPDATE memory_alpha_triggers SET trigger_1 = 0 WHERE id = 1';
        $this->db->query($sql);
                
        $sql = 'UPDATE memory_alpha_storage SET alpha_charted = "'.(urlencode(serialize($charted_rank))).'" WHERE id = 1';
        if(!$this->db->query($sql)) {return false;}
        return true;
    }
    
    private function f_trigger_2() {
        $sql='SELECT user_id, user_name, user_tform_planets FROM user WHERE user_active = 1 AND user_auth_level = 1 AND user_tform_planets > 0 ORDER BY user_tform_planets DESC LIMIT 0,10';
        $results = $this->db->queryrowset($sql);
        foreach ($results AS $key => $res_item) {
            $tform_rank[$key] = array ('user_id' => $res_item['user_id'],
                                       'user_name' => $res_item['user_name'],
                                       'user_tform_planets' => $res_item['user_tform_planets']
                                      );
        }
        
        $sql = 'UPDATE memory_alpha_triggers SET trigger_2 = 0 WHERE id = 1';
        $this->db->query($sql);
        
        $sql = 'UPDATE memory_alpha_storage SET alpha_tform = "'.(urlencode(serialize($tform_rank))).'" WHERE id = 1';
        if(!$this->db->query($sql)) {return false;}
        return true;        
    }
    
        private function f_trigger_3() {
        $sql='SELECT user_id, user_name, user_made_cships FROM user WHERE user_active = 1 AND user_auth_level = 1 AND user_made_cships > 0 ORDER BY user_made_cships DESC LIMIT 0,10';
        $results = $this->db->queryrowset($sql);
        foreach ($results AS $key => $res_item) {
            $cships_rank[$key] = array ('user_id' => $res_item['user_id'],
                                       'user_name' => $res_item['user_name'],
                                       'user_made_cships' => $res_item['user_made_cships']
                                      );
        }
        
        $sql = 'UPDATE memory_alpha_triggers SET trigger_3 = 0 WHERE id = 1';
        $this->db->query($sql);
        
        $sql = 'UPDATE memory_alpha_storage SET alpha_cships = "'.(urlencode(serialize($cships_rank))).'" WHERE id = 1';
        if(!$this->db->query($sql)) {return false;}
        return true;        
    }
    
        private function f_trigger_4() {
        $sql='SELECT user_id, user_name, officer_name, officer_rank, officer_level 
              FROM officers
              INNER JOIN user USING(user_id)
              WHERE user_active = 1 AND user_auth_level = 1 AND officer_level > 0 ORDER BY officer_xp DESC LIMIT 0,20';
        $results = $this->db->queryrowset($sql);
        foreach ($results AS $key => $res_item) {
            $officers_rank[$key] = array ('user_id' => $res_item['user_id'],
                                       'user_name' => $res_item['user_name'],
                                       'officer_name' => $res_item['officer_name'],
                                       'officer_rank' => $res_item['officer_rank'],
                                       'officer_level' => $res_item['officer_level']                
                                      );
        }
        
        $sql = 'UPDATE memory_alpha_triggers SET trigger_4 = 0 WHERE id = 1';
        $this->db->query($sql);
        
        $sql = 'UPDATE memory_alpha_storage SET alpha_off = "'.(urlencode(serialize($officers_rank))).'" WHERE id = 1';
        if(!$this->db->query($sql)) {return false;}
        return true;        
    }    
}