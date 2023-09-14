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

use APCIterator;
use vPDO\vPDO;

/**
 * Предоставляет реализацию xPDOCache на базе APC.
 *
 * Для этого требуется расширение APC для PHP версии 3.1.4 или более поздней. Более ранние версии
 * не было всех необходимых методов пользовательского кэширования.
 *
 * @package vPDO\Cache
 */
class vPDOAPCCache extends vPDOCache {
    public function __construct(& $xpdo, $options = array()) {
        parent :: __construct($xpdo, $options);
        if (function_exists('apc_exists')) {
            $this->initialized = true;
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "vPDOAPCCache[{$this->key}]: Ошибка при создании поставщика кэша APC; для vPDOAPCCache требуется расширение APC для PHP версии 2.0.0 или более поздней.");
        }
    }

    public function add($key, $var, $expire= 0, $options= array()) {
        $added= apc_add(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $added;
    }

    public function set($key, $var, $expire= 0, $options= array()) {
        $set= apc_store(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $set;
    }

    public function replace($key, $var, $expire= 0, $options= array()) {
        $replaced = false;
        if (apc_exists($key)) {
            $replaced= apc_store(
                $this->getCacheKey($key),
                $var,
                $expire
            );
        }
        return $replaced;
    }

    public function delete($key, $options= array()) {
        $deleted = false;
        if ($this->getOption(vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE, $options, false)) {
            if (class_exists('APCIterator', true)) {
                $iterator = new APCIterator('user', '/^' . str_replace('/', '\/', $this->getCacheKey($key)) . '/', APC_ITER_KEY);
                if ($iterator) {
                    $deleted = apc_delete($iterator);
                }
            } else {
                $deleted = $this->flush($options);
            }
        } else {
            $deleted = apc_delete($this->getCacheKey($key));
        }

        return $deleted;
    }

    public function get($key, $options= array()) {
        $value= apc_fetch($this->getCacheKey($key));
        return $value;
    }

    public function flush($options= array()) {
        $flushed = false;
        if (class_exists('APCIterator', true) && $this->getOption('flush_by_key', $options, true) && !empty($this->key)) {
            $iterator = new APCIterator('user', '/^' . str_replace('/', '\/', $this->key) . '\//', APC_ITER_KEY);
            if ($iterator) {
                $flushed = apc_delete($iterator);
            }
        } else {
            $flushed = apc_clear_cache('user');
        }
        return $flushed;
    }
}
