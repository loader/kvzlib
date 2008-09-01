#!/usr/bin/php -q
<?php
/*
 * pear install -f Testing_DocTest
*/


class KvzLib {
    
    /**
     * System is unusable
     */
    const LOG_EMERG = 0;
    
    /**
     * Immediate action required
     */ 
    const LOG_ALERT = 1;
    
    /**
     * Critical conditions
     */
    const LOG_CRIT = 2;
    
    /**
     * Error conditions
     */
    const LOG_ERR = 3;
    
    /**
     * Warning conditions
     */
    const LOG_WARNING = 4;
    
    /**
     * Normal but significant
     */
    const LOG_NOTICE = 5;
    
    /**
     * Informational
     */
    const LOG_INFO = 6;
    
    /**
     * Debug-level messages
     */
    const LOG_DEBUG = 7;
        
    
    protected $_cmds = array();
    protected $_path = "";
    
    
    public function KvzLib($path=false) {
        if (!$path) {
            $this->log("Path: '$path' is empty", KvzLib::LOG_EMERG);
            return false;
        }
        
        if (!file_exists($path)) {
            $this->log("Path: '$path' does not exist", KvzLib::LOG_EMERG);
            return false;
        }
        
        $this->_path = $path;
        
        $this->_which("pear", true);
        $this->_which("phpdt", true);
    }
    
    public function log($str, $level=KvzLib::LOG_INFO) {
        echo $str."\n";
        
        if ($level < KvzLib::LOG_CRIT) {
            die();
        }
        
        return true;
    }
    
    public function exe($cmd) {
        $numargs  = func_num_args();
        $arg_list = func_get_args();
        $args     = array();
        
        $cmdE = $this->_cmds[$cmd];
        if ($numargs > 1) {
            for ($i = 1; $i < $numargs; $i++) {
                $args[] = $arg_list[$i];
            }        
        }
        
        if (count($args)) {
            $cmdE .= " ". implode(" ", $args); 
        }
        
        $this->log($cmdE, KvzLib::LOG_DEBUG);
        
        return $this->_exe($cmdE);
    }
    
    protected function _exe($cmd) {
        exec($cmd, $o, $r);
        if ($r != 0) {
            return false;
        }
        return $o;
    }
    
    protected function _which($cmd, $dieOnFail=false) {
        $cmdW = "which ".escapeshellcmd($cmd);
        if (($o = $this->_exe($cmdW)) === false) {
            if ($dieOnFail) {
                $this->log("Command: '$cmd' ", KvzLib::LOG_EMERG);
            }
            return false;
        }
        
        $this->_cmds[$cmd] = implode("\n", $o);
        
        return true;
    }
    
    public function test(){
        $x = $this->exe("phpdt", $this->_path);
        echo implode("\n", $x);
    }
    
}


$KvzLib = new KvzLib(dirname(__FILE__));
$KvzLib->test();


?>