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
 * Реализация диспетчера кэша по умолчанию для vPDO.
 *
 * @package vPDO\Cache
 */
class vPDOCacheManager {
    const CACHE_PHP = 0;
    const CACHE_JSON = 1;
    const CACHE_SERIALIZE = 2;
    const CACHE_DIR = 'objects/';
    const LOG_DIR = 'logs/';

    /** @var vPDO */
    protected $vpdo= null;
    protected $caches= array();
    protected $options= array();
    protected $_umask= null;

    public function __construct(& $vpdo, $options = array()) {
        $this->vpdo= & $vpdo;
        $this->options= $options;
        $this->_umask= umask();
    }

    /**
     * Получите экземпляр поставщика, который реализует интерфейс vPDOCache.
     *
     * @param string $key
     * @param array $options
     *
     * @return vPDOCache|null
     */
    public function getCacheProvider($key = '', $options = array()) {
        $objCache = null;
        if (empty($key)) {
            $key = $this->getOption(vPDO::OPT_CACHE_KEY, $options, 'default');
        }
        $objCacheClass= 'vPDO\\Cache\\vPDOFileCache';
        if (!isset($this->caches[$key]) || !is_object($this->caches[$key])) {
            if ($cacheClass = $this->getOption($key . '_' . vPDO::OPT_CACHE_HANDLER, $options, $this->getOption(vPDO::OPT_CACHE_HANDLER, $options))) {
                $cacheClass = $this->vpdo->loadClass($cacheClass, VPDO_CORE_PATH, false, true);
                if ($cacheClass) {
                    $objCacheClass= $cacheClass;
                }
            }
            $options[vPDO::OPT_CACHE_KEY]= $key;
            $this->caches[$key] = new $objCacheClass($this->vpdo, $options);
            if (empty($this->caches[$key]) || !$this->caches[$key]->isInitialized()) {
                $this->caches[$key] = new vPDOFileCache($this->vpdo, $options);
            }
            $objCache = $this->caches[$key];
            $objCacheClass= get_class($objCache);
        } else {
            $objCache =& $this->caches[$key];
            $objCacheClass= get_class($objCache);
        }
        if ($this->vpdo->getDebug() === true) $this->vpdo->log(vPDO::LOG_LEVEL_DEBUG, "Возвращающий {$objCacheClass}:{$key} поставщик кэша из доступных поставщиков: " . print_r(array_keys($this->caches), 1));
        return $objCache;
    }

    /**
     * Получите опцию из предоставленных опций, параметров CacheManager или самого vpdo.
     *
     * @param string $key Уникальный идентификатор для опции.
     * @param array $options Набор явных параметров для переопределения параметров из vPDO 
     * или реализации vPDOCacheManager.
     * @param mixed $default Необязательное значение по умолчанию, возвращаемое,
     * если значение не найдено.
     * @return mixed Значение параметра.
     */
    public function getOption($key, $options = array(), $default = null) {
        $option = $default;
        if (is_array($key)) {
            if (!is_array($option)) {
                $default= $option;
                $option= array();
            }
            foreach ($key as $k) {
                $option[$k]= $this->getOption($k, $options, $default);
            }
        } elseif (is_string($key) && !empty($key)) {
            if (is_array($options) && !empty($options) && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (is_array($this->options) && !empty($this->options) && array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } else {
                $option = $this->vpdo->getOption($key, null, $default);
            }
        }
        return $option;
    }

    /**
     * Получите разрешения для папок по умолчанию на основе umask
     *
     * @return integer Разрешения для папок по умолчанию.
     */
    public function getFolderPermissions() {
        $perms = 0777;
        $perms = $perms & (0777 - $this->_umask);
        return $perms;
    }

    /**
     * Получите права доступа к файлам по умолчанию на основе umask
     *
     * @return integer Права доступа к файлам по умолчанию.
     */
    public function getFilePermissions() {
        $perms = 0666;
        $perms = $perms & (0666 - $this->_umask);
        return $perms;
    }

    /**
     * Получите абсолютный путь к доступному для записи каталогу для хранения файлов.
     *
     * @access public
     * @return string Абсолютный путь к каталогу кэша vPDO.
     */
    public function getCachePath() {
        $cachePath= false;
        if (!isset ($this->vpdo->config['cache_path'])) {
            while (true) {
                if (!empty ($_ENV['TMP'])) {
                    if ($cachePath= strtr($_ENV['TMP'], '\\', '/'))
                        break;
                }
                if (!empty ($_ENV['TMPDIR'])) {
                    if ($cachePath= strtr($_ENV['TMPDIR'], '\\', '/'))
                        break;
                }
                if (!empty ($_ENV['TEMP'])) {
                    if ($cachePath= strtr($_ENV['TEMP'], '\\', '/'))
                        break;
                }
                if ($temp_file= @ tempnam(md5(uniqid(rand(), true)), '')) {
                    $cachePath= strtr(dirname($temp_file), '\\', '/');
                    @ unlink($temp_file);
                }
                break;
            }
            if ($cachePath) {
                if ($cachePath[strlen($cachePath) - 1] != '/') $cachePath .= '/';
                $cachePath .= '.xpdo-cache';
            }
        }
        else {
            $cachePath= strtr($this->vpdo->config['cache_path'], '\\', '/');
        }
        if ($cachePath) {
            $perms = $this->getOption('new_folder_permissions', null, $this->getFolderPermissions());
            if (is_string($perms)) $perms = octdec($perms);
            if (@ $this->writeTree($cachePath, $perms)) {
                if ($cachePath[strlen($cachePath) - 1] != '/') $cachePath .= '/';
                if (!is_writeable($cachePath)) {
                    @ chmod($cachePath, $perms);
                }
            } else {
                $cachePath= false;
            }
        }
        return $cachePath;
    }

    /**
     * Записывает файл в файловую систему.
     *
     * @access public
     * @param string $filename Абсолютный путь к местоположению, в котором будет находиться файл
     * быть вписанным.
     * @param string $content Содержимое только что записанного файла.
     * @param string $mode Режим php-файла для записи. По умолчанию используется значение "wb". Обратите внимание, что этот метод всегда
     * использует a (с b или t, если указано) для открытия файла, и что любой режим, кроме a, означает существующий файл
     * содержимое будет перезаписано.
     * @param array $options Массив опций для этой функции.
     * @return int|bool Возвращает количество байт, записанных в файл, или значение false в случае сбоя.
     */
    public function writeFile($filename, $content, $mode= 'wb', $options= array()) {
        $written= false;
        if (!is_array($options)) {
            $options = is_scalar($options) && !is_bool($options) ? array('new_folder_permissions' => $options) : array();
        }
        $dirname= dirname($filename);
        if (!file_exists($dirname)) {
            $this->writeTree($dirname, $options);
        }
        $mode = str_replace('+', '', $mode);
        switch ($mode[0]) {
            case 'a':
                $append = true;
                break;
            default:
                $append = false;
                break;
        }
        $fmode = (strlen($mode) > 1 && in_array($mode[1], array('b', 't'))) ? "a{$mode[1]}" : 'a';
        $file= @fopen($filename, $fmode);
        if ($file) {
            if ($append === true) {
                $written= fwrite($file, $content);
            } else {
                $locked = false;
                $attempt = 1;
                $attempts = (integer) $this->getOption(vPDO::OPT_CACHE_ATTEMPTS, $options, 1);
                $attemptDelay = (integer) $this->getOption(vPDO::OPT_CACHE_ATTEMPT_DELAY, $options, 1000);
                while (!$locked && ($attempts === 0 || $attempt <= $attempts)) {
                    if ($this->getOption('use_flock', $options, true)) {
                        $locked = flock($file, LOCK_EX | LOCK_NB);
                    } else {
                        $lockFile = $this->lockFile($filename, $options);
                        $locked = $lockFile != false;
                    }
                    if (!$locked && $attemptDelay > 0 && ($attempts === 0 || $attempt < $attempts)) {
                        usleep($attemptDelay);
                    }
                    $attempt++;
                }
                if ($locked) {
                    fseek($file, 0);
                    ftruncate($file, 0);
                    $written= fwrite($file, $content);
                    if ($this->getOption('use_flock', $options, true)) {
                        flock($file, LOCK_UN);
                    } else {
                        $this->unlockFile($filename, $options);
                    }
                }
            }
            @fclose($file);
        }
        return ($written !== false);
    }

    /**
     * Добавьте эксклюзивную блокировку к файлу для атомарных операций записи в многопоточных средах.
     *
     * vPDO::OPT_USE_FLOCK должно быть установлено значение false (или 0), иначе vPDO будет считать flock надежным.
     *
     * @param string $file Имя файла, который нужно заблокировать.
     * @param array $options Множество вариантов для этого процесса.
     * @return boolean True только в том случае, если текущий процесс получил эксклюзивную блокировку для записи.
     */
    public function lockFile($file, array $options = array()) {
        $locked = false;
        $lockDir = $this->getOption('lock_dir', $options, $this->getCachePath() . 'locks' . DIRECTORY_SEPARATOR);
        if ($this->writeTree($lockDir, $options)) {
            $lockFile = $this->lockFileName($file, $options);
            if (!file_exists($lockFile)) {
                $myPID = (php_sapi_name() == 'cli' || !isset($_SERVER['SERVER_ADDR']) ? gethostname() : $_SERVER['SERVER_ADDR']) . '.' . getmypid();
                $myPID .= mt_rand();
                $tmpLockFile = "{$lockFile}.{$myPID}";
                if (file_put_contents($tmpLockFile, $myPID)) {
                    if (link($tmpLockFile, $lockFile)) {
                        $locked = true;
                    }
                    @unlink($tmpLockFile);
                }
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "The lock_dir at {$lockDir} is not writable and could not be created");
        }
        if (!$locked) $this->vpdo->log(vPDO::LOG_LEVEL_WARN, "Attempt to lock file {$file} failed");
        return $locked;
    }

    /**
     * Снимите исключительную блокировку с файла, созданного с помощью lockFile().
     *
     * @param string $file Имя файла, который нужно разблокировать.
     * @param array $options Множество вариантов для этого процесса.
     */
    public function unlockFile($file, array $options = array()) {
        @unlink($this->lockFileName($file, $options));
    }

    /**
     * Получите абсолютный путь к файлу блокировки для указанного пути к файлу.
     *
     * @param string $file Абсолютный путь для получения имени файла блокировки.
     * @param array $options Множество вариантов для этого процесса.
     * @return string Абсолютный путь к файлу блокировки
     */
    protected function lockFileName($file, array $options = array()) {
        $lockDir = $this->getOption('lock_dir', $options, $this->getCachePath() . 'locks' . DIRECTORY_SEPARATOR);
        return $lockDir . preg_replace('/\W/', '_', $file) . $this->getOption(vPDO::OPT_LOCKFILE_EXTENSION, $options, '.lock');
    }

    /**
     * Рекурсивно записывает дерево каталогов файлов в файловую систему
     *
     * @access public
     * @param string $dirname Каталог для записи
     * @param array $options Массив опций для этой функции. Также может быть значением, представляющим
     * режим разрешений для записи в новые каталоги, хотя он устарел.
     * @return boolean Возвращает значение true, если каталог был успешно записан.
     */
    public function writeTree($dirname, $options= array()) {
        $written= false;
        if (!empty ($dirname)) {
            if (!is_array($options)) $options = is_scalar($options) && !is_bool($options) ? array('new_folder_permissions' => $options) : array();
            $mode = $this->getOption('new_folder_permissions', $options, $this->getFolderPermissions());
            if (is_string($mode)) $mode = octdec($mode);
            $dirname= strtr(trim($dirname), '\\', '/');
            if ($dirname[strlen($dirname) - 1] == '/') $dirname = substr($dirname, 0, strlen($dirname) - 1);
            if (is_dir($dirname) || (is_writable(dirname($dirname)) && @mkdir($dirname, $mode))) {
                $written= true;
            } elseif (!$this->writeTree(dirname($dirname), $options)) {
                $written= false;
            } else {
                $written= @ mkdir($dirname, $mode);
            }
            if ($written && !is_writable($dirname)) {
                @ chmod($dirname, $mode);
            }
        }
        return $written;
    }

    /**
     * Копирует файл из исходного файла в целевой каталог.
     *
     * @access public
     * @param string $source Абсолютный путь к исходному файлу.
     * @param string $target Абсолютный путь к целевому пункту назначения
     * каталог.
     * @param array $options Множество опций для этой функции.
     * @return boolean|array Возвращает значение true, если операция копирования прошла успешно, или один элемент
     * массив с именем файла в качестве ключа и результатами статистики успешно скопированного файла в качестве результата.
     */
    public function copyFile($source, $target, $options = array()) {
        $copied= false;
        if (!is_array($options)) $options = is_scalar($options) && !is_bool($options) ? array('new_file_permissions' => $options) : array();
        if (func_num_args() === 4) $options['new_folder_permissions'] = func_get_arg(3);
        if ($this->writeTree(dirname($target), $options)) {
            $existed= file_exists($target);
            if ($existed && $this->getOption('copy_newer_only', $options, false) && (($ttime = filemtime($target)) > ($stime = filemtime($source)))) {
                $this->vpdo->log(vPDO::LOG_LEVEL_INFO, "xPDOCacheManager->copyFile(): Skipping copy of newer file {$target} ({$ttime}) from {$source} ({$stime})");
            } else {
                $copied= copy($source, $target);
            }
            if ($copied) {
                if (!$this->getOption('copy_preserve_permissions', $options, false)) {
                    $fileMode = $this->getOption('new_file_permissions', $options, $this->getFilePermissions());
                    if (is_string($fileMode)) $fileMode = octdec($fileMode);
                    @ chmod($target, $fileMode);
                }
                if ($this->getOption('copy_preserve_filemtime', $options, true)) @ touch($target, filemtime($source));
                if ($this->getOption('copy_return_file_stat', $options, false)) {
                    $stat = stat($target);
                    if (is_array($stat)) {
                        $stat['overwritten']= $existed;
                        $copied = array($target => $stat);
                    }
                }
            }
        }
        if (!$copied) {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "xPDOCacheManager->copyFile(): Could not copy file {$source} to {$target}");
        }
        return $copied;
    }

    /**
     * Рекурсивно копирует дерево каталогов из исходного каталога в целевой
     * каталог.
     *
     * @access public
     * @param string $source Абсолютный путь к исходному каталогу.
     * @param string $target Абсолютный путь к целевому каталогу назначения.
     * @param array $options Множество опций для этой функции.
     * @return array|boolean Возвращает массив всех файлов и папок, которые были скопированы, или значение false.
     */
    public function copyTree($source, $target, $options= array()) {
        $copied= false;
        $source= strtr($source, '\\', '/');
        $target= strtr($target, '\\', '/');
        if ($source[strlen($source) - 1] == '/') $source = substr($source, 0, strlen($source) - 1);
        if ($target[strlen($target) - 1] == '/') $target = substr($target, 0, strlen($target) - 1);
        if (is_dir($source . '/')) {
            if (!is_array($options)) $options = is_scalar($options) && !is_bool($options) ? array('new_folder_permissions' => $options) : array();
            if (func_num_args() === 4) $options['new_file_permissions'] = func_get_arg(3);
            if (!is_dir($target . '/')) {
                $this->writeTree($target . '/', $options);
            }
            if (is_dir($target)) {
                if (!is_writable($target)) {
                    $dirMode = $this->getOption('new_folder_permissions', $options, $this->getFolderPermissions());
                    if (is_string($dirMode)) $dirMode = octdec($dirMode);
                    if (! @ chmod($target, $dirMode)) {
                        $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "{$target} is not writable and permissions could not be modified.");
                    }
                }
                if ($handle= @ opendir($source)) {
                    $excludeItems = $this->getOption('copy_exclude_items', $options, array('.', '..','.svn','.svn/','.svn\\'));
                    $excludePatterns = $this->getOption('copy_exclude_patterns', $options);
                    $copiedFiles = array();
                    $error = false;
                    while (false !== ($item= readdir($handle))) {
                        $copied = false;
                        if (is_array($excludeItems) && !empty($excludeItems) && in_array($item, $excludeItems)) continue;
                        if (is_array($excludePatterns) && !empty($excludePatterns) && $this->matches($item, $excludePatterns)) continue;
                        $from= $source . '/' . $item;
                        $to= $target . '/' . $item;
                        if (is_dir($from)) {
                            if (!($copied= $this->copyTree($from, $to, $options))) {
                                $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not copy directory {$from} to {$to}");
                                $error = true;
                            } else {
                                $copiedFiles = array_merge($copiedFiles, $copied);
                            }
                        } elseif (is_file($from)) {
                            if (!$copied= $this->copyFile($from, $to, $options)) {
                                $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not copy file {$from} to {$to}; could not create directory.");
                                $error = true;
                            } else {
                                $copiedFiles[] = $to;
                            }
                        } else {
                            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not copy {$from} to {$to}");
                        }
                    }
                    @ closedir($handle);
                    if (!$error) $copiedFiles[] = $target;
                    $copied = $copiedFiles;
                } else {
                    $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not read source directory {$source}");
                }
            } else {
                $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Could not create target directory {$target}");
            }
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, "Source directory {$source} does not exist.");
        }
        return $copied;
    }

    /**
     * Рекурсивно удаляет дерево каталогов файлов.
     *
     * @access public
     * @param string $dirname Абсолютный путь к исходному каталогу для удаления.
     * @param array $options Множество опций для этой функции.
     * @return boolean Возвращает значение true, если удаление было успешным.
     */
    public function deleteTree($dirname, $options= array('deleteTop' => false, 'skipDirs' => false, 'extensions' => array('.cache.php'))) {
        $result= false;
        if (is_dir($dirname)) { /* Operate on dirs only */
            if (substr($dirname, -1) != '/') {
                $dirname .= '/';
            }
            $result= array ();
            if (!is_array($options)) {
                $numArgs = func_num_args();
                $options = array(
                    'deleteTop' => is_scalar($options) ? (boolean) $options : false
                    ,'skipDirs' => $numArgs > 2 ? func_get_arg(2) : false
                    ,'extensions' => $numArgs > 3 ? func_get_arg(3) : array('.cache.php')
                );
            }
            $hasMore= true;
            if ($handle= opendir($dirname)) {
                $limit= 4;
                $extensions= $this->getOption('extensions', $options, array('.cache.php'));
                $excludeItems = $this->getOption('delete_exclude_items', $options, array('.', '..','.svn','.svn/','.svn\\'));
                $excludePatterns = $this->getOption('delete_exclude_patterns', $options);
                while ($hasMore && $limit--) {
                    if (!$handle) {
                        $handle= opendir($dirname);
                    }
                    $hasMore= false;
                    while (false !== ($file= @ readdir($handle))) {
                        if (is_array($excludeItems) && !empty($excludeItems) && in_array($file, $excludeItems)) continue;
                        if (is_array($excludePatterns) && !empty($excludePatterns) && $this->matches($file, $excludePatterns)) continue;
                        if ($file != '.' && $file != '..') { /* Ignore . and .. */
                            $path= $dirname . $file;
                            if (is_dir($path)) {
                                $suboptions = array_merge($options, array('deleteTop' => !$this->getOption('skipDirs', $options, false)));
                                if ($subresult= $this->deleteTree($path, $suboptions)) {
                                    $result= array_merge($result, $subresult);
                                }
                            }
                            elseif (is_file($path)) {
                                if (is_array($extensions) && !empty($extensions) && !$this->endsWith($file, $extensions)) continue;
                                if (unlink($path)) {
                                    array_push($result, $path);
                                } else {
                                    $hasMore= true;
                                }
                            }
                        }
                    }
                    closedir($handle);
                }
                if ($this->getOption('deleteTop', $options, false)) {
                    if (@ rmdir($dirname)) {
                        array_push($result, $dirname);
                    }
                }
            }
        } else {
            $result= false; /* return false if attempting to operate on a file */
        }
        return $result;
    }

    /**
     * Проверяет, заканчивается ли строка определенным шаблоном или набором шаблонов.
     *
     * @access public
     * @param string $string Строка для проверки.
     * @param string|array $pattern Шаблон или массив шаблонов для проверки.
     * @return boolean True, если строка заканчивается шаблоном или любым из предоставленных шаблонов.
     */
    public function endsWith($string, $pattern) {
        $matched= false;
        if (is_string($string) && ($stringLen= strlen($string))) {
            if (is_array($pattern)) {
                foreach ($pattern as $subPattern) {
                    if (is_string($subPattern) && $this->endsWith($string, $subPattern)) {
                        $matched= true;
                        break;
                    }
                }
            } elseif (is_string($pattern)) {
                if (($patternLen= strlen($pattern)) && $stringLen >= $patternLen) {
                    $matched= (substr($string, -$patternLen) === $pattern);
                }
            }
        }
        return $matched;
    }

    /**
     * Проверяет, соответствует ли строка определенному шаблону или набору шаблонов.
     *
     * @access public
     * @param string $string Строка для проверки.
     * @param string|array $pattern Шаблон или массив шаблонов для проверки.
     * @return boolean True, если строка соответствует шаблону или любому из предоставленных шаблонов.
     */
    public function matches($string, $pattern) {
        $matched= false;
        if (is_string($string) && ($stringLen= strlen($string))) {
            if (is_array($pattern)) {
                foreach ($pattern as $subPattern) {
                    if (is_string($subPattern) && $this->matches($string, $subPattern)) {
                        $matched= true;
                        break;
                    }
                }
            } elseif (is_string($pattern)) {
                $matched= preg_match($pattern, $string);
            }
        }
        return $matched;
    }

    /**
     * Сгенерируйте исполняемое на PHP представление vPDOObject.
     *
     * @todo Полная функциональность, связанная с $generateRelated.
     * @todo Добавьте поддержку stdObject.
     *
     * @access public
     * @param \vPDO\Om\vPDOObject $obj Объект vPDOObject для генерации файла кэша для
     * @param string $objName Имя vPDOObject
     * @param boolean $generateObjVars Если true, также будут сгенерированы карты для всех
     * объектные переменные. По умолчанию установлено значение false.
     * @param boolean $generateRelated Если true, также будут сгенерированы карты для всех
     * связанные объекты. По умолчанию установлено значение false.
     * @param string $objRef Ссылка на экземпляр vPDO в строке формат.
     * @param int $format Формат для кэширования. По умолчанию используется
     * vPDOCacheManager::CACHE_PHP, который настроен на кэширование в исполняемом формате PHP.
     * @return string Исходный файл карты в строковом формате.
     */
    public function generateObject($obj, $objName, $generateObjVars= false, $generateRelated= false, $objRef= 'this->xpdo', $format= vPDOCacheManager::CACHE_PHP) {
        // $source= false;
        // if (is_object($obj) && $obj instanceof \vPDO\Om\vPDOObject) {
        //     $className= $obj->_class;
        //     $source= "\${$objName}= \${$objRef}->newObject('{$className}');\n";
        //     $source .= "\${$objName}->fromArray(" . var_export($obj->toArray('', true), true) . ", '', true, true);\n";
        //     if ($generateObjVars && $objectVars= get_object_vars($obj)) {
        //         foreach ($objectVars as $vk => $vv) {
        //             if ($vk === 'vb') {
        //                 $source .= "\${$objName}->{$vk}= & \${$objRef};\n";
        //             }
        //             elseif ($vk === 'vpdo') {
        //                 $source .= "\${$objName}->{$vk}= & \${$objRef};\n";
        //             }
        //             elseif (!is_resource($vv)) {
        //                 $source .= "\${$objName}->{$vk}= " . var_export($vv, true) . ";\n";
        //             }
        //         }
        //     }
        //     if ($generateRelated && !empty ($obj->_relatedObjects)) {
        //         foreach ($obj->_relatedObjects as $className => $fk) {
        //             foreach ($fk as $key => $relObj) {} /* TODO: complete $generateRelated functionality */
        //         }
        //     }
        // }
        // return $source;
    }

    /**
     * Добавьте пару ключ-значение к поставщику кэша, если она еще не существует.
     *
     * @param string $key Уникальный ключ, идентифицирующий сохраняемый элемент.
     * @param mixed & $var Ссылка на переменную PHP, представляющую элемент.
     * @param integer $lifetime Секунд элемент будет действителен в кэше.
     * @param array $options Дополнительные параметры для операции добавления кэша.
     * @return boolean True, если добавление прошло успешно.
     */
    public function add($key, & $var, $lifetime= 0, $options= array()) {
        $return= false;
        if ($cache = $this->getCacheProvider($this->getOption(vPDO::OPT_CACHE_KEY, $options))) {
            // $value= null;
            // if (is_object($var) && $var instanceof \vPDO\Om\vPDOObject) {
            //     $value= $var->toArray('', true);
            // } else {
                $value= $var;
            // }
            $return= $cache->add($key, $value, $lifetime, $options);
        }
        return $return;
    }

    /**
     * Замените пару ключ-значение в поставщике кэша.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий заменяемый элемент.
     * @param mixed & $var Ссылка на переменную PHP, представляющую элемент.
     * @param integer $lifetime Секунд элемент будет действителен в objcache.
     * @param array $options Дополнительные параметры для операции замены кэша.
     * @return boolean True, если замена прошла успешно.
     */
    public function replace($key, & $var, $lifetime= 0, $options= array()) {
        $return= false;
        if ($cache = $this->getCacheProvider($this->getOption(vPDO::OPT_CACHE_KEY, $options), $options)) {
            // $value= null;
            // if (is_object($var) && $var instanceof \vPDO\Om\vPDOObject) {
            //     $value= $var->toArray('', true);
            // } else {
                $value= $var;
            // }
            $return= $cache->replace($key, $value, $lifetime, $options);
        }
        return $return;
    }

    /**
     * Установите пару ключ-значение в поставщике кэша.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий устанавливаемый элемент.
     * @param mixed & $var Ссылка на переменную PHP, представляющую элемент.
     * @param integer $lifetime Секунд элемент будет действителен в objcache.
     * @param array $options Дополнительные параметры для операции установки кэша.
     * @return boolean True, если установка прошла успешно
     */
    public function set($key, & $var, $lifetime= 0, $options= array()) {
        $return= false;
        if ($cache = $this->getCacheProvider($this->getOption(vPDO::OPT_CACHE_KEY, $options), $options)) {
            // $value= null;
            // if (is_object($var) && $var instanceof \vPDO\Om\vPDOObject) {
            //     $value= $var->toArray('', true);
            // } else {
                $value= $var;
            // }
            $return= $cache->set($key, $value, $lifetime, $options);
        } else {
            $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, 'No cache implementation found.');
        }
        return $return;
    }

    /**
     * Удалите пару ключ-значение из поставщика кэша.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий удаляемый элемент.
     * @param array $options Дополнительные опции для удаления кэша.
     * @return boolean Значение True, если удаление прошло успешно.
     */
    public function delete($key, $options = array()) {
        $return= false;
        if ($cache = $this->getCacheProvider($this->getOption(vPDO::OPT_CACHE_KEY, $options), $options)) {
            $return= $cache->delete($key, $options);
        }
        return $return;
    }

    /**
     * Получите значение от поставщика кэша по ключу.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий извлекаемый элемент.
     * @param array $options Дополнительные опции для извлечения из кэша.
     * @return mixed Значение ключа кэша объекта
     */
    public function get($key, $options = array()) {
        $return= false;
        if ($cache = $this->getCacheProvider($this->getOption(vPDO::OPT_CACHE_KEY, $options), $options)) {
            $return= $cache->get($key, $options);
        }
        return $return;
    }

    /**
     * Очистите содержимое поставщика кэша.
     *
     * @access public
     * @param array $options Дополнительные опции для очистки кэша.
     * @return boolean True, если сброс был успешным.
     */
    public function clean($options = array()) {
        $return= false;
        if ($cache = $this->getCacheProvider($this->getOption(vPDO::OPT_CACHE_KEY, $options), $options)) {
            $return= $cache->flush($options);
        }
        return $return;
    }

    /**
     * Обновите определенные или все поставщики кэша.
     *
     * Поведение по умолчанию заключается в вызове clean() с предоставленными параметрами
     *
     * @param array $providers Ассоциативный массив с ключами, представляющими ключ поставщика кэша,
     * а значение - массив параметров.
     * @param array &$results Ассоциативный массив для сбора результатов для каждого поставщика.
     * @return array Массив результатов для каждого обновленного поставщика.
     */
    public function refresh(array $providers = array(), array &$results = array()) {
        if (empty($providers)) {
            foreach ($this->caches as $cacheKey => $cache) {
                $providers[$cacheKey] = array();
            }
        }
        foreach ($providers as $key => $options) {
            if (array_key_exists($key, $this->caches) && !array_key_exists($key, $results)) {
                $results[$key] = $this->clean(array_merge($options, array(vPDO::OPT_CACHE_KEY => $key)));
            }
        }
        return (array_search(false, $results, true) === false);
    }

    /**
     * Экранирует все одинарные кавычки в строке
     *
     * @access public
     * @param string $s Строка, в которую нужно заключить одинарные кавычки.
     * @return string Строка, заключенная в одинарные кавычки, экранирована.
     */
    public function escapeSingleQuotes($s) {
        $q1= array (
            "\\",
            "'"
        );
        $q2= array (
            "\\\\",
            "\\'"
        );
        return str_replace($q1, $q2, $s);
    }
}
