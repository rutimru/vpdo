<?php
/**
 * Этот файл является частью пакета vPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace vPDO\Cache;

use Redis;
use vPDO\vPDO;

/**
 * Предоставляет реализацию vPDOCache на базе redis.
 *
 * Для этого требуется расширение redis для PHP.
 *
 * @package vPDO\Cache
 */
class vPDORedisCache extends vPDOCache {
    protected $redis = null;

    public function __construct(& $xpdo, $options = array()) {
        parent :: __construct($xpdo, $options);
        if (class_exists('Redis', true)) {
            $this->redis= new Redis();
            if ($this->redis) {
                $server = explode(':', $this->getOption($this->key . '_redis_server', $options, $this->getOption('redis_server', $options, 'localhost:6379')));                
                if($this->redis->pconnect($server[0], (integer) $server[1])){                    
                    $redis_auth=$this->getOption('redis_auth', $options, '');
                    if(!empty($redis_auth)){
                        $this->redis->auth($redis_auth);    
                    }
                    $this->redis->select((integer)$this->getOption('redis_db', $options, 0));
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                    $this->initialized = true;                        
                }   
            } else {
                $this->redis = null;
                $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "xPDORedisCache[{$this->key}]: Error creating redis provider for server(s): " . $this->getOption($this->key . '_redisd_server', $options, $this->getOption('redisd_server', $options, 'localhost:6379')));
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "xPDORedisCache[{$this->key}]: Error creating redis provider; xPDORedisCache requires the PHP redis extension.");
        }
    }

    public function add($key, $var, $expire= 0, $options= array()) {
        $added= false;
        if(!$this->redis->exists($this->getCacheKey($key))){          
            $added=$this->redis->set($this->getCacheKey($key),$var,$expire); 
        }
        return $added;
    }

    public function set($key, $var, $expire= 0, $options= array()) {
        $set=$this->redis->set($this->getCacheKey($key),$var,$expire); 
        return $set;
    }

    public function replace($key, $var, $expire= 0, $options= array()) {
        $replaced=false;
        if($this->redis->exists($this->getCacheKey($key))){          
            $replaced=$this->redis->set($this->getCacheKey($key),$var,$expire); 
        }
        return $replaced;
    }

    public function delete($key, $options= array()) {
        if ($this->getOption(vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE, $options, false)) {
            $deleted= $this->flush($options);
        } else {
            $deleted= $this->redis->delete($this->getCacheKey($key));
        }

        return $deleted;
    }

    public function get($key, $options= array()) {
        $value= $this->redis->get($this->getCacheKey($key));
        return $value;
    }

    public function flush($options= array()) {
        return $this->redis->flushDb();
    }
}
