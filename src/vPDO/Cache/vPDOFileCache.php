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

use vPDO\vPDO;

/**
 * Простая реализация кэширования на основе файлов с использованием исполняемого файла PHP.
 *
 * Это может быть использовано для снижения нагрузки на базу данных, хотя общая производительность
 * примерно то же самое, что и без файлового кэша. Для достижения максимальной производительности и
 * масштабируемости используйте сервер с memcached и расширением PHP memcache
 * настроен.
 *
 * @package vPDO\Cache
 */
class vPDOFileCache extends vPDOCache {
    public function __construct(& $vpdo, $options = array()) {
        parent :: __construct($vpdo, $options);
        $this->initialized = true;
    }

    public function getCacheKey($key, $options = array()) {
        $cachePath = $this->getOption('cache_path', $options);
        $cacheExt = $this->getOption('cache_ext', $options, '.cache.php');
        $key = parent :: getCacheKey($key, $options);
        return $cachePath . $key . $cacheExt;
    }

    public function add($key, $var, $expire= 0, $options= array()) {
        $added= false;
        if (!file_exists($this->getCacheKey($key, $options))) {
            if ($expire === true)
                $expire= 0;
            $added= $this->set($key, $var, $expire, $options);
        }
        return $added;
    }

    public function set($key, $var, $expire= 0, $options= array()) {
        $set= false;
        if ($var !== null) {
            if ($expire === true)
                $expire= 0;
            $expirationTS= $expire ? time() + $expire : 0;
            $expireContent= '';
            if ($expirationTS) {
                $expireContent= 'if(time() > ' . $expirationTS . '){return null;}';
            }
            $fileName= $this->getCacheKey($key, $options);
            $format = (integer) $this->getOption(vPDO::OPT_CACHE_FORMAT, $options, vPDOCacheManager::CACHE_PHP);
            switch ($format) {
                case vPDOCacheManager::CACHE_SERIALIZE:
                    $content= serialize(array('expires' => $expirationTS, 'content' => $var));
                    break;
                case vPDOCacheManager::CACHE_JSON:
                    $content= $this->vpdo->toJSON(array('expires' => $expirationTS, 'content' => $var));
                    break;
                case vPDOCacheManager::CACHE_PHP:
                default:
                    $content= '<?php ' . $expireContent . ' return ' . var_export($var, true) . ';';
                    break;
            }
            $set= $this->vpdo->cacheManager->writeFile($fileName, $content);
        }
        return $set;
    }

    public function replace($key, $var, $expire= 0, $options= array()) {
        $replaced= false;
        if (file_exists($this->getCacheKey($key, $options))) {
            if ($expire === true)
                $expire= 0;
            $replaced= $this->set($key, $var, $expire, $options);
        }
        return $replaced;
    }

    public function delete($key, $options= array()) {
        $deleted= false;
        if ($this->getOption(vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE, $options, false)) {
            $cacheKey= $this->getCacheKey($key, array_merge($options, array('cache_ext' => '')));
            if (file_exists($cacheKey) && is_dir($cacheKey)) {
                $results = $this->vpdo->cacheManager->deleteTree($cacheKey, array_merge(array('deleteTop' => false, 'skipDirs' => false, 'extensions' => array('.cache.php')), $options));
                if ($results !== false) {
                    $deleted = true;
                }
            }
        }
        $cacheKey= $this->getCacheKey($key, $options);
        if (file_exists($cacheKey)) {
            $deleted= @ unlink($cacheKey);
        }
        return $deleted;
    }

    public function get($key, $options= array()) {
        $value= null;
        $cacheKey= $this->getCacheKey($key, $options);
        if (file_exists($cacheKey)) {
            if ($file = @fopen($cacheKey, 'rb')) {
                $format = (integer) $this->getOption(vPDO::OPT_CACHE_FORMAT, $options, vPDOCacheManager::CACHE_PHP);
                if (flock($file, LOCK_SH)) {
                    switch ($format) {
                        case vPDOCacheManager::CACHE_PHP:
                            $value= @include $cacheKey;
                            break;
                        case vPDOCacheManager::CACHE_JSON:
                            $payload = stream_get_contents($file);
                            if ($payload !== false) {
                                $payload = $this->vpdo->fromJSON($payload);
                                if (is_array($payload) && isset($payload['expires']) && (empty($payload['expires']) || time() < $payload['expires'])) {
                                    if (array_key_exists('content', $payload)) {
                                        $value= $payload['content'];
                                    }
                                }
                            }
                            break;
                        case vPDOCacheManager::CACHE_SERIALIZE:
                            $payload = stream_get_contents($file);
                            if ($payload !== false) {
                                $payload = unserialize($payload);
                                if (is_array($payload) && isset($payload['expires']) && (empty($payload['expires']) || time() < $payload['expires'])) {
                                    if (array_key_exists('content', $payload)) {
                                        $value= $payload['content'];
                                    }
                                }
                            }
                            break;
                    }
                    flock($file, LOCK_UN);
                    if ($value === null && $this->getOption('removeIfEmpty', $options, true)) {
                        fclose($file);
                        @ unlink($cacheKey);
                        return $value;
                    }
                }
                @fclose($file);
            }
        }
        return $value;
    }

    public function flush($options= array()) {
        $cacheKey= $this->getCacheKey('', array_merge($options, array('cache_ext' => '')));
        $results = $this->vpdo->cacheManager->deleteTree($cacheKey, array_merge(array('deleteTop' => false, 'skipDirs' => false, 'extensions' => array('.cache.php')), $options));
        return ($results !== false);
    }
}
