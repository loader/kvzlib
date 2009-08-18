<?php
/**
 * An attempt at solid cache invalidation.
 * When writing cache, certain events that make it invalid have
 * to be registered.
 * When these events occur cache can be purged
 *
 * @author kvz
 */

/**
 * Easy static access to EventCacheInst
 *
 */
class EventCache {
    static public    $instanceClass = 'EventCacheInst';
    static public    $config = array();
    static protected $_instance = null;
    
    static public function getInstance() {
        if (EventCache::$_instance === null) {
            EventCache::$_instance = new self::$instanceClass(EventCache::$config);
        }

        return EventCache::$_instance;
    }
    static public function setOption($key, $val = null) {
        $_this = EventCache::getInstance();
        return $_this->setOption($key, $val);
    }
    static public function read($key) {
        $_this = EventCache::getInstance();
        return $_this->read($key);
    }
    static public function getKeys($event) {
        $_this = EventCache::getInstance();
        return $_this->getKeys($event);
    }
    static public function getEvents() {
        $_this = EventCache::getInstance();
        return $_this->getEvents();
    }

    /*
    // PHP 5.3
    static public function  __callStatic($name, $arguments) {
        $_this = EventCache::getInstance();
        $call = array($_this, $name);
        if (is_callable($call)) {
            return call_user_func_array($call, $arguments);
        }

        return false;
    }
    */

    static public function squashArrayTo1Dim($array) {
        foreach($array as $k=>$v) {
            if (is_array($v)) {
                $array[$k] = md5(json_encode($v));
            }
        }
        return $array;
    }

    static public function magicKey($scope, $method, $args = array(), $events = array(), $options = array()) {
        $_this = EventCache::getInstance();
        $dlm   = '.';
        $dls   = '@';

        $keyp = array();
        if (is_object($scope)) {
            if (!empty($scope->name)) {
                $keyp[] = $scope->name;
            } else {
                $keyp[] = get_class($scope);
            }
        } elseif (is_string($scope)) {
            $keyp[] = $scope;
        }
        $keyp[] = $method;
        if (is_string($options)) {
            $options = array(
                'unique' => $options,
            );
        }
        if (!empty($options['unique'])) {
            $options['unique'] = self::squashArrayTo1Dim((array)$options['unique']);
            $keyp = array_merge($keyp, $options['unique']);
        }
        $keyp[] = join($dls, $args);

        $keyp = $_this->sane($keyp);
        $key  = join($dlm, $keyp);
        return $key;
    }

    static protected function _execute($callback, $args) {
        // Can we Execute Callback?
        if (!is_callable($callback)) {
            trigger_error('Can\'t call '.join('::', $callback).' is it public?', E_USER_ERROR);
            return false;
        }
        return call_user_func_array($callback, $args);
    }

    static public function magic($scope, $method, $args = array(), $events = array(), $options = array()) {
        $key      = self::magicKey($scope, $method, $args, $events, $options);
        $callback = array($scope, '_'.$method);
        
        if (!empty($options['disable'])) {
            return self::_execute($callback, $args);
        }

        if (!($val = self::read($key))) {
            $val = self::_execute($callback, $args);
            self::write($key, $val, $events, $options);
        }
        
        return $val;
    }

    static public function write($key, $val, $events = array(), $options = array()) {
        $_this = EventCache::getInstance();
        return $_this->write($key, $val, $events, $options);
    }
    
    static public function trigger($event) {
        $_this = EventCache::getInstance();
        return $_this->trigger($event);
    }
    static public function flush() {
        $_this = EventCache::getInstance();
        return $_this->flush();
    }
}

/**
 * Main EventCache Class
 *
 */
class EventCacheInst {
    const LOG_EMERG = 0;
    const LOG_ALERT = 1;
    const LOG_CRIT = 2;
    const LOG_ERR = 3;
    const LOG_WARNING = 4;
    const LOG_NOTICE = 5;
    const LOG_INFO = 6;
    const LOG_DEBUG = 7;
    
    protected $_logLevels = array(
        self::LOG_EMERG => 'emerg',
        self::LOG_ALERT => 'alert',
        self::LOG_CRIT => 'crit',
        self::LOG_ERR => 'err',
        self::LOG_WARNING => 'warning',
        self::LOG_NOTICE => 'notice',
        self::LOG_INFO => 'info',
        self::LOG_DEBUG => 'debug'
    );
    
    protected $_config = array(
        'app' => 'base',
        'delimiter' => '-',
        'adapter' => 'EventCacheMemcachedAdapter',
        'trackEvents' => false,
        'motherEvents' => array(),
        'disable' => false,
        'servers' => array(
            '127.0.0.1',
        ),
    );
    
    protected $_dir   = null;
    protected $_Cache = null;

    /**
     * Init
     *
     * @param <type> $config
     */
    public function  __construct($config) {
        $this->_config       = array_merge($this->_config, $config);
        $this->_Cache        = new $this->_config['adapter'](array(
            'servers' => $this->_config['servers'],
        ));
    }
    /**
     * Set options
     *
     * @param <type> $key
     * @param <type> $val
     * @return <type>
     */
    public function setOption($key, $val = null) {
        if (is_array($key) && $val === null) {
            foreach ($key as $k => $v) {
                if (!$this->setOption($k, $v)) {
                    return false;
                }
            }
            return true;
        }

        $this->_config[$key] = $val;
        return true;
    }

    /**
     * Save a key
     *
     * @param <type> $key
     * @param <type> $val
     * @param <type> $events
     * @param <type> $options
     * @return <type>
     */
    public function write($key, $val, $events = array(), $options = array()) {
        if (!empty($this->_config['disable'])) {
            return false;
        }

        if (!isset($options['ttl'])) $options['ttl'] = 0;

        // In case of 'null' e.g.
        if (empty($events)) {
            $events = array();
        }
        
        // Mother events are attached to all keys
        if (!empty($this->_config['motherEvents'])) {
            $events = array_merge($events, (array)$this->_config['motherEvents']);
        }

        $this->register($key, $events);

        $kKey = $this->cKey('key', $key);

        $this->debug('Set key: %s with val: %s', $kKey, $val);
        return $this->_set($kKey, $val, $options['ttl']);
    }
    /**
     * Get a key
     *
     * @param <type> $key
     * @return <type>
     */
    public function read($key) {
        if (!empty($this->_config['disable'])) {
            return false;
        }
        
        $kKey = $this->cKey('key', $key);

        $this->debug('Get key: %s', $kKey);
        return $this->_get($kKey);
    }
    /**
     * Adds array item
     *
     * @param <type> $listKey
     * @param <type> $key
     * @param <type> $val
     * @param <type> $ttl
     * @return <type>
     */
    public function listAdd($listKey, $key = null, $val = null, $ttl = 0) {
        $memKey = $this->cKey('key', $listKey);
        $this->debug('Add item: %s with value: %s to list: %s. ttl: %s',
            $key,
            $val,
            $memKey,
            $ttl
        );
        return $this->_listAdd($memKey, $key, $val, $ttl);
    }
    /**
     * Delete a key
     *
     * @param <type> $key
     * @return <type>
     */
    public function delete($key) {
        $kKey = $this->cKey('key', $key);
        $this->debug('Del key: %s', $kKey);
        return $this->_del($kKey);
    }
    /**
     * Clears All EventCache keys. (Only works with 'trackEvents' enabled)
     *
     * @param <type> $events
     * @return <type>
     */
    public function clear($events = null) {
        if (!$this->_config['trackEvents']) {
            $this->err('You need to enable the slow "trackEvents" option for this');
            return false;
        }

        if ($events === null) {
            $events = $this->getEvents();
            if (!empty($events)) {
               $this->clear($events);
            }
        } else {
            $events = (array)$events;
            foreach($events as $eKey=>$event) {
                $cKeys = $this->getCKeys($event);
                $this->_del($cKeys);
                
                $this->_del($eKey);
            }
        }
    }
    /**
     * Kills everything in (mem) cache. Everything!
     *
     * @return <type>
     */
    public function flush() {
        return $this->_flush();
    }

    /**
     * DisAssociate keys with events
     *
     * @param <type> $key
     * @param <type> $events
     */
    public function unregister($key, $events = array()) {
        return $this->register($key, $events, true);
    }
    
    /**
     * Associate keys with events (if you can't do it immediately with 'write')
     *
     * @param <type> $key
     * @param <type> $events
     * @param <type> $del
     */
    public function register($key, $events = array(), $del = false) {
        $events = (array)$events;
        if ($this->_config['trackEvents']) {
            // Slows down performance
            $etKey = $this->cKey('events', 'track');
            foreach($events as $event) {
                $eKey = $this->cKey('event', $event);
                if ($del) {
                    $this->_listDel($etKey, $eKey);
                } else {
                    $this->_listAdd($etKey, $eKey, $event);
                }
            }
        }

        foreach($events as $event) {
            $eKey = $this->cKey('event', $event);
            $kKey = $this->cKey('key', $key);
            if ($del) {
                $this->_listDel($eKey, $kKey);
            } else {
                $this->_listAdd($eKey, $kKey, $key);
            }
        }
        return true;
    }

    /**
     * Call this function when your event has fired
     *
     * @param <type> $event
     */
    public function trigger($event) {
        $cKeys = $this->getCKeys($event);
        return $this->_del($cKeys);
    }

    // Get events
    public function getEvents() {
        if (!$this->_config['trackEvents']) {
            $this->err('You need to enable the slow "trackEvents" option for this');
            return false;
        }
        
        $etKey  = $this->cKey('events', 'track');
        $events = $this->_get($etKey);
        return $events ? $events : array();
    }

    /**
     * Get event's keys
     *
     * @param <type> $event
     * @return <type>
     */
    public function getKeys($event) {
        $eKey = $this->cKey('event', $event);
        return $this->_get($eKey);
    }
    
    /**
     * Get internal keys
     *
     * @param <type> $event
     * @return <type>
     */
    public function getCKeys($event) {
        $list = $this->getKeys($event);
        if (!is_array($list)) {
            return $list;
        }
        return array_keys($list);
    }
    
    
    /**
     * Returns a (mem)cache-ready key
     *
     * @param <type> $type
     * @param <type> $key
     * @return <type>
     */
    public function cKey($type, $key) {
        $cKey = $this->sane($key);
        return $this->_config['app'].$this->_config['delimiter'].$type.$this->_config['delimiter'].$this->sane($cKey);
    }
    /**
     * Sanitizes a string
     *
     * @param <type> $str
     * @return <type>
     */
    public function sane($str) {
        if (is_array($str)) {
            foreach($str as $k => $v) {
                $str[$k] = $this->sane($v);
            }
            return $str;
        } else {
            $allowed = array(
                '0-9' => true,
                'a-z' => true,
                'A-Z' => true,
                '\-' => true,
                '\_' => true,
                '\.' => true,
                '\@' => true,
            );

            if (isset($allowed['\\'.$this->_config['delimiter']])) {
                unset($allowed['\\'.$this->_config['delimiter']]);
            }
            
            return preg_replace('/[^'.join('', array_keys($allowed)).']/', '_', $str);
        }
    }

    /**
     * Log debug messages.
     *
     * @param <type> $str
     * @return <type>
     */
    public function debug($str) {
        $args = func_get_args();
        return self::_log(self::LOG_DEBUG, array_shift($args), $args);
    }
    /**
     * Log error messages
     *
     * @param <type> $str
     * @return <type>
     */
    public function err($str) {
        $args = func_get_args();
        return self::_log(self::LOG_ERR, array_shift($args), $args);
    }
    /**
     * Real function used by err, debug, etc, wrappers
     *
     * @param <type> $level
     * @param <type> $str
     * @param <type> $args
     * @return <type>
     */
    protected function _log($level, $str, $args) {
        foreach ($args as $k=>$arg) {
            if (is_array($arg)) {
                $args[$k] = var_export($arg, true);
            }
        }
        
        $log  = '';
        $log .= '';
        $log .= '['.date('M d H:i:s').']';
        $log .= ' ';
        $log .= str_pad($this->_logLevels[$level], 8, ' ', STR_PAD_LEFT);
        $log .= ': ';
        $log .= vsprintf($str, $args);
        return $this->out($log);
    }

    public function out($str) {
        echo $str . "\n";
        return true;
    }

    /**
     * Add remove element from an array in cache
     *
     * @param <type> $memKey
     * @param <type> $cKey
     * @param <type> $ttl
     * @return <type>
     */
    protected function _listDel($memKey, $cKey, $ttl = 0) {
        $list = $this->_get($memKey);
        if (is_array($list) && array_key_exists($cKey, $list)) {
            unset($list[$cKey]);
            return $this->_set($memKey, $list, $ttl);
        }
        // Didn't have to remove non-existing key
        return null;
    }

    /**
     * Add one element to an array in cache
     *
     * @param <type> $memKey
     * @param <type> $cKey
     * @param <type> $val
     * @param <type> $ttl
     * @return <type>
     */
    protected function _listAdd($memKey, $cKey = null, $val = null, $ttl = 0) {
        if ($val === null) {
            $val = time();
        }
        $list = $this->_get($memKey);
        if (empty($list)) {
            $list = array();
        }
        if ($cKey === null) {
            $list[] = $val;
        } else {
            $list[$cKey] = $val;
        }
        return $this->_set($memKey, $list, $ttl);
    }

    /**
     * Add real key
     *
     * @param <type> $cKey
     * @param <type> $val
     * @param <type> $ttl
     * @return <type>
     */
    protected function _add($cKey, $val, $ttl = 0) {
        return $this->_Cache->add($cKey, $val, $ttl);
    }
    /**
     * Delete real key
     *
     * @param <type> $cKeys
     * @param <type> $ttl
     * @return <type>
     */
    protected function _del($cKeys, $ttl = 0) {
        if (empty($cKeys)) {
            return null;
        }
        if (is_array($cKeys)) {
            foreach($cKeys as $cKey) {
                if (!$this->_del($cKey)) {
                    return false;
                }
            }
            return true;
        }
        
        return $this->_Cache->delete($cKeys, $ttl);
    }
    /**
     * Set real key
     *
     * @param <type> $cKey
     * @param <type> $val
     * @param <type> $ttl
     * @return <type>
     */
    protected function _set($cKey, $val, $ttl = 0) {
        return $this->_Cache->set($cKey, $val, $ttl);
    }
    /**
     * Get real key
     *
     * @param <type> $cKey
     * @return <type>
     */
    protected function _get($cKey) {
        return $this->_Cache->get($cKey);
    }
    /**
     * Flush!
     *
     * @return <type>
     */
    protected function _flush() {
        return $this->_Cache->flush();
    }
}

/**
 * Memcache adapter to EventCache
 *
 */
class EventCacheMemcachedAdapter {
	protected $_config = array(
		'servers' => null,
	);

	protected $_memd;

	public function __construct($options) {
		$this->_config =  $options + $this->_config;

		$this->_memd = new Memcache();
		foreach ($this->_config['servers'] as $server) {
			call_user_func_array(array($this->_memd, 'addServer'), $server);
		}
	}

	public function get($key) {
		return $this->_memd->get($key);
	}

	public function flush() {
		return $this->_memd->flush();
	}

	public function set($key, $val, $ttl = 0) {
		return $this->_memd->set($key, $val, 0, $ttl);
	}

	public function add($key, $val, $ttl = 0) {
		return $this->_memd->add($key, $val, 0, $ttl);
	}

	public function delete($key, $ttl = 0) {
		return $this->_memd->delete($key, $ttl);
	}

	public function increment($key, $value = 1) {
		return $this->_memd->increment($key, $value);
	}

	public function decrement($key, $value = 1) {
		return $this->_memd->decrement($key, $value);
	}
}
?>