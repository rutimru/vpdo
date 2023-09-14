<?php
/**
 * Этот файл является частью пакета vPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

/**
 * Это основной класс vPDO.
 *
 * @author Vitaly Surkov <surkov@rutim.ru>
 * @copyright Copyright (C) 2022-2023, Vitaly Surkov
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @package vpdo
 */
namespace vPDO;

use Composer\Autoload\ClassLoader;
use Exception;
use Psr\Container\ContainerInterface;
use vPDO\Om\vPDOCriteria;
use vPDO\Om\vPDOQuery;

if (!defined('VPDO_CORE_PATH')) {
    $vpdo_core_path= strtr(realpath(dirname(__FILE__)), '\\', '/') . '/';
    /**
     * @var string Полный путь к корневому каталогу vPDO.
     */
    define('VPDO_CORE_PATH', $VPDO_CORE_PATH);
    unset($VPDO_CORE_PATH);
}
if (!defined('VPDO_CLI_MODE')) {
    /**
     * @var bool Указывает, является ли PHP_SAPI cli.
     */
    define('VPDO_CLI_MODE', PHP_SAPI === 'cli');
}

/**
 * Оболочка для PDO, которая поддерживает объектно-реляционную модель данных.
 *
 * vPDO обеспечивает централизованный доступ к данным через простой объектно-ориентированный API к
 * определенной структуре данных. Он предоставляет фактические методы подключения
 * к источнику данных, получая постоянные метаданные для любого класса, расширенного из
 * класс {@link vPDOObject} (основной или пользовательский), загружающий менеджеры источников данных
 * когда это необходимо для управления структурами таблиц и извлечения экземпляров (или строк)
 * любого объекта в модели.
 *
 * С помощью различных расширений вы также можете изменять и перенаправлять классы инженеров
 * и карты метаданных для vPDO, поддерживающие классы, модели и свойства
 * их собственные контейнеры (базы данных, таблицы, столбцы и т.д.) или изменения в них,
 * и многое другое.
 *
 * @package vpdo
 */
#[\AllowDynamicProperties]
class vPDO {
    /**#@+
     * Константы
     */
    const OPT_AUTO_CREATE_TABLES = 'auto_create_tables';
    const OPT_BASE_CLASSES = 'base_classes';
    const OPT_BASE_PACKAGES = 'base_packages';
    const OPT_CACHE_COMPRESS = 'cache_compress';
    const OPT_CACHE_DB = 'cache_db';
    const OPT_CACHE_DB_COLLECTIONS = 'cache_db_collections';
    const OPT_CACHE_DB_OBJECTS_BY_PK = 'cache_db_objects_by_pk';
    const OPT_CACHE_DB_EXPIRES = 'cache_db_expires';
    const OPT_CACHE_DB_HANDLER = 'cache_db_handler';
    const OPT_CACHE_DB_SIG_CLASS = 'cache_db_sig_class';
    const OPT_CACHE_DB_SIG_GRAPH = 'cache_db_sig_graph';
    const OPT_CACHE_EXPIRES = 'cache_expires';
    const OPT_CACHE_FORMAT = 'cache_format';
    const OPT_CACHE_HANDLER = 'cache_handler';
    const OPT_CACHE_KEY = 'cache_key';
    const OPT_CACHE_PATH = 'cache_path';
    const OPT_CACHE_PREFIX = 'cache_prefix';
    const OPT_CACHE_MULTIPLE_OBJECT_DELETE = 'multiple_object_delete';
    const OPT_CACHE_ATTEMPTS = 'cache_attempts';
    const OPT_CACHE_ATTEMPT_DELAY = 'cache_attempt_delay';
    const OPT_CALLBACK_ON_REMOVE = 'callback_on_remove';
    const OPT_CALLBACK_ON_SAVE = 'callback_on_save';
    const OPT_CONNECTIONS = 'connections';
    const OPT_CONN_INIT = 'connection_init';
    const OPT_CONN_MUTABLE = 'connection_mutable';
    const OPT_OVERRIDE_TABLE_TYPE = 'override_table';
    const OPT_HYDRATE_FIELDS = 'hydrate_fields';
    const OPT_HYDRATE_ADHOC_FIELDS = 'hydrate_adhoc_fields';
    const OPT_HYDRATE_RELATED_OBJECTS = 'hydrate_related_objects';
    const OPT_LOCKFILE_EXTENSION = 'lockfile_extension';
    const OPT_USE_FLOCK = 'use_flock';
    const OPT_ON_SET_STRIPSLASHES = 'on_set_stripslashes';
    const OPT_SETUP = 'setup';
    const OPT_TABLE_PREFIX = 'table_prefix';
    const OPT_VALIDATE_ON_SAVE = 'validate_on_save';
    const OPT_VALIDATOR_CLASS = 'validator_class';

    const LOG_LEVEL_FATAL = 0;
    const LOG_LEVEL_ERROR = 1;
    const LOG_LEVEL_WARN = 2;
    const LOG_LEVEL_INFO = 3;
    const LOG_LEVEL_DEBUG = 4;

    const SCHEMA_VERSION = '1.0';

    /**
     * @var array Статическая коллекция экземпляров pdo.
     */
    protected static $instances = array();
    /**
     * @var object Одиночный модуль автозагрузки PSR-0.
     */
    protected static $loader;

    /**
     * @var \PDO Ссылка на экземпляр PDO, используемый текущим vPDOConnection.
     */
    public $pdo= null;
    /**
     * @var array Параметры конфигурации для экземпляра vPDO.
     */
    public $config= null;
    /**
     * @var Om\vPDODriver Экземпляр vPDODriver для использования экземплярами vPDOConnection.
     */
    public $driver= null;
    /**
     * @var vPDOMap Карта метаданных источника данных для всех загруженных классов.
     */
    public $map= null;
    /**
     * @var string Пакет по умолчанию для указания классов по имени.
     */
    public $package= '';
    /**
     * @var array Массив, хранящий пакеты и информацию, относящуюся к конкретному пакету.
     */
    public $packages= array ();
    /**
     * @var Om\vPDOManager Менеджер источников данных по запросу.
     */
    public $manager= null;
    /**
     * @var Cache\vPDOCacheManager Поставщик услуг кэширования зарегистрировался для этого экземпляра.
     */
    public $cacheManager= null;
    /**
     * @var string Корневой путь для использования файловых служб кэширования.
     */
    private $cachePath= null;
    /**
     * @var ContainerInterface|array Контейнер или массив (устаревший) дополнительной службы
     * классы для этого экземпляра vPDO.
     */
    public $services= null;
    /**
     * @var float Время начала запроса, инициализируемого при запуске конструктора
     * called.
     */
    public $startTime= 0;
    /**
     * @var int Количество прямых запросов к базе данных, выполненных во время запроса.
     */
    public $executedQueries= 0;
    /**
     * @var int Количество времени обработки запросов, затраченного на запросы к базе данных.
     */
    public $queryTime= 0;
    /**
     * @var array Карта табличных классов, управляемых этим экземпляром.
     */
    public $classMap = array();
    /**
     * @var vPDOConnection Текущее vPDOConnection для этого экземпляра vPDO.
     */
    public $connection = null;
    /**
     * @var array Соединения PDO, управляемые этим экземпляром vPDO.
     */
    private $_connections = array();
    /**
     * @var integer Уровень ведения журнала для экземпляра vPDO.
     */
    protected $logLevel= self::LOG_LEVEL_FATAL;
    /**
     * @var string Цель ведения журнала по умолчанию для экземпляра vPDO.
     */
    protected $logTarget= 'ECHO';
    /**
     * @var boolean Указывает на состояние отладки данного экземпляра..
     */
    protected $_debug= false;
    /**
     * @var boolean Флаг глобального кэша, который можно использовать для включения/отключения всего кэширования.
     */
    public $_cacheEnabled= false;
    /**
     * @var string Указывает открывающий экранирующий символ, используемый для конкретного компонента database engine.
     */
    public $_escapeCharOpen= '';
    /**
     * @var string Указывает закрывающий экранирующий символ, используемый для конкретного компонента database engine.
     */
    public $_escapeCharClose= '';
    /**
     * @var string Представляет символ, используемый для цитирования строк для конкретного драйвера.
     */
    public $_quoteChar= "'";

    /**
     * Create, retrieve, or update specific vPDO instances.
     *
     * @param string|int|null $id An optional identifier for the instance. If not set
     * a uniqid will be generated and used as the key for the instance.
     * @param array|ContainerInterface|null $config An optional container or array of config data
     * for the instance.
     * @param bool $forceNew If true a new instance will be created even if an instance
     * with the provided $id already exists in vPDO::$instances.
     *
     * @throws vPDOException If a valid instance is not retrieved.
     * @return vPDO An instance of vPDO.
     */
    public static function getInstance($id = null, $config = null, $forceNew = false) {
        $instances =& self::$instances;
        if (is_null($id)) {
            if (!is_null($config) || $forceNew || empty($instances)) {
                $id = uniqid(__CLASS__);
            } else {
                $id = key($instances);
            }
        }
        if ($forceNew || !array_key_exists($id, $instances) || !($instances[$id] instanceof vPDO)) {
            $instances[$id] = new vPDO(null, null, null, $config);
        } elseif ($instances[$id] instanceof vPDO && is_array($config)) {
            $instances[$id]->config = array_merge($instances[$id]->config, $config);
        }
        if (!($instances[$id] instanceof vPDO)) {
            throw new vPDOException("Error getting " . __CLASS__ . " instance, id = {$id}");
        }
        return $instances[$id];
    }

    /**
     * Получите автозагрузчик Composer, используемый этой библиотекой.
     *
     * @return ClassLoader Экземпляр автозагрузчика, используемый всеми экземплярами vPDO.
     */
    public static function getLoader()
    {
        $loader =& self::$loader;
        if ($loader === null) {
            $loader = include __DIR__ . '/../bootstrap.php';
        }
        return $loader;
    }

    /**
     * Конструктор vPDO.
     *
     * Этот метод используется для создания нового объекта vPDO с подключением к определенному
     * контейнеру базы данных.
     *
     * @param mixed                    $dsn           Допустимая строка подключения DSN.
     * @param string                   $username      Имя пользователя базы данных с соответствующими разрешениями.
     * @param string                   $password      Пароль для пользователя базы данных.
     * @param array|ContainerInterface $options       Контейнер зависимостей или массив параметров vPDO.
     *                                                Вы должны настроить массив параметров vPDO внутри контейнера и передать контейнер для
     *                                                будущая совместимость.
     * @param array|null               $driverOptions Параметры PDO, зависящие от конкретного драйвера.
     *
     * @throws vPDOException Если возникает ошибка при создании экземпляра.
     */
    public function __construct($dsn, $username= '', $password= '', $options= array(), $driverOptions= null) {
        try {
            $this->config = $this->initConfig($options);
            if ($this->services === null) {
                $this->services = new vPDOContainer();
            }
            $this->setLogLevel($this->getOption('log_level', null, vPDO::LOG_LEVEL_FATAL, true));
            $this->setLogTarget($this->getOption('log_target', null, php_sapi_name() === 'cli' ? 'ECHO' : 'HTML', true));
            if (!empty($dsn)) {
                $this->addConnection($dsn, $username, $password, $this->config, $driverOptions);
            }
            if (isset($this->config[vPDO::OPT_CONNECTIONS])) {
                $connections = $this->config[vPDO::OPT_CONNECTIONS];
                if (is_string($connections)) {
                    $connections = $this->fromJSON($connections);
                }
                if (is_array($connections)) {
                    foreach ($connections as $connection) {
                        $this->addConnection(
                            $connection['dsn'],
                            $connection['username'],
                            $connection['password'],
                            $connection['options'],
                            $connection['driverOptions']
                        );
                    }
                }
            }
            $initOptions = $this->getOption(vPDO::OPT_CONN_INIT, null, array());
            $this->config = array_merge($this->config, $this->getConnection($initOptions)->config);
            $this->getDriver();
            $this->map = new vPDOMap($this);
            $this->setPackage('Om', VPDO_CORE_PATH, $this->config[vPDO::OPT_TABLE_PREFIX]);
            if (isset($this->config[vPDO::OPT_BASE_PACKAGES]) && !empty($this->config[vPDO::OPT_BASE_PACKAGES])) {
                $basePackages= explode(',', $this->config[vPDO::OPT_BASE_PACKAGES]);
                foreach ($basePackages as $basePackage) {
                    $exploded= explode(':', $basePackage, 2);
                    if ($exploded) {
                        $path= $exploded[1];
                        $prefix= null;
                        if (strpos($path, ';')) {
                            $details= explode(';', $path);
                            if ($details && count($details) == 2) {
                                $path= $details[0];
                                $prefix = $details[1];
                            }
                        }
                        $this->addPackage($exploded[0], $path, $prefix);
                    }
                }
            }
            if (isset($this->config[vPDO::OPT_BASE_CLASSES])) {
                foreach (array_keys($this->config[vPDO::OPT_BASE_CLASSES]) as $baseClass) {
                    $this->loadClass($baseClass);
                }
            }
            if (isset($this->config[vPDO::OPT_CACHE_PATH])) {
                $this->cachePath = $this->config[vPDO::OPT_CACHE_PATH];
            }
        } catch (Exception $e) {
            throw new vPDOException("Не удалось создать экземпляр vPDO: " . $e->getMessage());
        }
    }

    /**
     * Инициализируйте конфигурационный массив vPDO.
     *
     * @param ContainerInterface|array $data Источник входных данных конфигурации. В настоящее время принимает зависимость
     * контейнер, содержащий запись 'config', содержащую массив конфигурации vPDO, или массив
     * содержащий конфигурацию напрямую (устарел).
     *
     * @return array Массив конфигурационных данных vPDO.
     */
    protected function initConfig($data) {
        if ($data instanceof ContainerInterface) {
            $this->services = $data;
            if ($this->services->has('config')) {
                $data = $this->services->get('config');
            }
        }
        if (!is_array($data)) {
            $data = array(vPDO::OPT_TABLE_PREFIX => '');
        }

        return $data;
    }

    /**
     * Добавьте экземпляр vPDOConnection в пул подключений vPDO.
     *
     * @param string $dsn PDO DSN представляющий сведения о подключении.
     * @param string $username Учетные данные пользователя для подключения.
     * @param string $password Учетные данные с паролем для подключения.
     * @param array $options Множество вариантов подключения.
     * @param null $driverOptions Множество опций драйвера PDO для подключения.
     * @return boolean Истинно, если было добавлено действительное соединение.
     */
    public function addConnection($dsn, $username= '', $password= '', array $options= array(), $driverOptions= null) {
        $added = false;
        $connection= new vPDOConnection($this, $dsn, $username, $password, $options, $driverOptions);
        if ($connection instanceof vPDOConnection) {
            $this->_connections[]= $connection;
            $added= true;
        }
        return $added;
    }

    /**
     * Получите vPDOConnection из пула подключений vPDO.
     *
     * @param array $options Множество вариантов для установления соединения.
     * @return vPDOConnection|null Экземпляр vPDOConnection или null, если не удалось восстановить соединение.
     */
    public function getConnection(array $options = array()) {
        $conn =& $this->connection;
        $mutable = $this->getOption(vPDO::OPT_CONN_MUTABLE, $options, null);
        if (!($conn instanceof vPDOConnection) || ($mutable !== null && (($mutable == true && !$conn->isMutable()) || ($mutable == false && $conn->isMutable())))) {
            if (!empty($this->_connections)) {
                shuffle($this->_connections);
                $conn = reset($this->_connections);
                while ($conn) {
                    if ($mutable !== null && (($mutable == true && !$conn->isMutable()) || ($mutable == false && $conn->isMutable()))) {
                        $conn = next($this->_connections);
                        continue;
                    }
                    $this->connection =& $conn;
                    break;
                }
            } else {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Не удалось получить действительное vPDOConnection", '', __METHOD__, __FILE__, __LINE__);
            }
        }
        return $this->connection;
    }

    /**
     * Получите или создайте PDO-соединение с базой данных, указанной в конфигурации.
     *
     * @param array $driverOptions Необязательный набор параметров драйвера для использования
     * при создании подключения.
     * @param array $options Множество опций vPDO для подключения.
     * @return boolean Возвращает значение true, если PDO-соединение было создано успешно.
     */
    public function connect($driverOptions= array (), array $options= array()) {
        $connected = false;
        $this->getConnection($options);
        if ($this->connection instanceof vPDOConnection) {
            $connected = $this->connection->connect($driverOptions);
            if ($connected) {
                $this->pdo =& $this->connection->pdo;
            }
        }
        return $connected;
    }

    /**
     * Устанавливает определенный пакет модели для использования при поиске классов.
     *
     * Этот пакет имеет форму package.subpackage.subsubpackage и будет
     * добавлен в начало каждого класса vPDOObject, на который ссылаются в
     * методах vPDO, таких как {@link vPDO::loadClass()}, {@link vPDO::GetObject()},
     * {@* {@ссылка vPDO::getCollection()}, {@link vPDOObject::getOne()}, {@link
     * vPDOObject::addOne()} и т.д.
     *
     * @param string $pkg A package name to use when looking up classes in vPDO.
     * @param string $path The root path for looking up classes in this package.
     * @param string|null $prefix Provide a string to define a package-specific table_prefix.
     * @param string|null $namespacePrefix An optional namespace prefix for working with PSR-4.
     * @return bool
     */
    public function setPackage($pkg= '', $path= '', $prefix= null, $namespacePrefix= null) {
        if (empty($path) && isset($this->packages[$pkg])) {
            $path= $this->packages[$pkg]['path'];
            $prefix= !is_string($prefix) && array_key_exists('prefix', $this->packages[$pkg]) ? $this->packages[$pkg]['prefix'] : $prefix;
        }
        $set= $this->addPackage($pkg, $path, $prefix, $namespacePrefix);
        $this->package= $set == true ? $pkg : $this->package;
        if ($set && is_string($prefix)) $this->config[vPDO::OPT_TABLE_PREFIX]= $prefix;
        return $set;
    }

    /**
     * Adds a model package and base class path for including classes and/or maps from.
     *
     * @param string $pkg A package name to use when looking up classes/maps in vPDO.
     * @param string $path The root path for looking up classes in this package.
     * @param string|null $prefix Provide a string to define a package-specific table_prefix.
     * @param string|null $namespacePrefix An optional namespace prefix for working with PSR-4.
     * @return bool
     */
    public function addPackage($pkg= '', $path= '', $prefix= null, $namespacePrefix= null) {
        $added= false;
        if (is_string($pkg) && !empty($pkg)) {
            if (!is_string($path) || empty($path)) {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Invalid path specified for package: {$pkg}; using default vpdo model path: " . VPDO_CORE_PATH . 'Om/');
                $path= VPDO_CORE_PATH . 'Om/';
            }
            if (!is_dir($path)) {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Path specified for package {$pkg} is not a valid or accessible directory: {$path}");
            } else {
                $prefix= !is_string($prefix) ? $this->config[vPDO::OPT_TABLE_PREFIX] : $prefix;
                if (!array_key_exists($pkg, $this->packages) || $this->packages[$pkg]['path'] !== $path || $this->packages[$pkg]['prefix'] !== $prefix) {
                    $this->packages[$pkg]= array('path' => $path, 'prefix' => $prefix);
                    $this->setPackageMeta($pkg, $path, $namespacePrefix);
                }
                $added= true;
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, 'addPackage called with an invalid package name.');
        }
        return $added;
    }

    /**
     * Adds metadata information about a package and loads the vPDO::$classMap.
     *
     * @param string $pkg A package name to use when looking up classes/maps in vPDO.
     * @param string $path The root path for looking up classes in this package.
     * @param string|null $namespacePrefix An optional namespace prefix for working with PSR-4.
     * @return bool
     */
    public function setPackageMeta($pkg, $path = '', $namespacePrefix= null) {
        $set = false;
        if (is_string($pkg) && !empty($pkg)) {
            $pkgPath = str_replace(array('.', '\\'), array('/', '/'), $pkg);
            $namespacePrefixPath = !empty($namespacePrefix) ? str_replace('\\', '/', $namespacePrefix) : '';
            if (!empty($namespacePrefixPath) && strpos($pkgPath, $namespacePrefixPath) === 0) {
                $pkgPath = substr($pkgPath, strlen($namespacePrefixPath));
            }
            $mapFile = $path . $pkgPath . '/metadata.' . $this->config['dbtype'] . '.php';
            if (file_exists($mapFile)) {
                $vpdo_meta_map = array();
                include $mapFile;
                if (!empty($vpdo_meta_map)) {
                    if (isset($vpdo_meta_map['version'])) {
                        if (version_compare($vpdo_meta_map['version'], '3.0', '>=')) {
                            $namespacePrefix = isset($vpdo_meta_map['namespacePrefix']) && !empty($vpdo_meta_map['namespacePrefix'])
                                ? $vpdo_meta_map['namespacePrefix'] . '\\'
                                : '';
                            self::getLoader()->addPsr4($namespacePrefix, $path);
                            $vpdo_meta_map = $vpdo_meta_map['class_map'];
                        }
                    }
                    foreach ($vpdo_meta_map as $className => $extends) {
                        if (!isset($this->classMap[$className])) {
                            $this->classMap[$className] = array();
                        }
                        $this->classMap[$className] = array_unique(array_merge($this->classMap[$className],$extends));
                    }
                    $set = true;
                }
            } else {
                $this->log(vPDO::LOG_LEVEL_WARN, "Could not load package metadata for package {$pkg}. Upgrade your model.");
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, 'setPackageMeta called with an invalid package name.');
        }
        return $set;
    }

    /**
     * Gets a list of derivative classes for the specified className.
     *
     * The specified className must be vPDOObject or a derivative class.
     *
     * @param string $className The name of the class to retrieve derivatives for.
     * @return array An array of derivative classes or an empty array.
     */
    public function getDescendants($className) {
        $descendants = array();
        if (isset($this->classMap[$className])) {
            $descendants = $this->classMap[$className];
            if ($descendants) {
                foreach ($descendants as $descendant) {
                    $descendants = array_merge($descendants, $this->getDescendants($descendant));
                }
            }
        }
        return $descendants;
    }

    public function getDriverClass($class) {
        if (strpos($class, '\\') !== false) {
            $paths = explode('\\', $class);
            $base = array_pop($paths);
            array_push($paths, $this->getOption('dbtype'), $base);
        } else {
            $paths = array($this->getOption('dbtype'), $class);
        }
        $driverClass = implode('\\', $paths);
        return class_exists($driverClass) ? $driverClass : false;
    }

    public function getPlatformClass($domainClass) {
        if (strpos($domainClass, '\\') !== false) {
            $exploded = explode('\\', ltrim($domainClass, '\\'));
            $slice = array_slice($exploded, -1);
            $class = $slice[0];
            $namespace = implode('\\', array_slice($exploded, 0, -1));
            if (!empty($namespace)) $namespace .= '\\';
            return "\\{$namespace}{$this->getOption('dbtype')}\\{$class}";
        } else {
            return "\\{$domainClass}_{$this->getOption('dbtype')}";
        }
    }

    /**
     * Загрузите класс по полному имени.
     *
     * $fqn должен быть в формате:
     *
     *    dir_a.dir_b.dir_c.classname
     *
     * который будет переведен в:
     *
     *    VPDO_CORE_PATH/Om/dir_a/dir_b/dir_c/dbtype/classname.class.php
     *
     * As of vPDO 3.0, the use of loadClass is only necessary to support BC
     * with older vPDO models. Auto-loading in models built with vPDO 3.0 or
     * later makes the use of this method obsolete.
     *
     * @param string $fqn The fully-qualified name of the class to load.
     * @param string $path An optional path to start the search from.
     * @param bool $ignorePkg True if currently loaded packages should be ignored.
     * @param bool $transient True if the class is not a persistent table class.
     *
     * @return string|boolean The actual classname if successful, or false if
     * not.
     * @deprecated since 3.0
     */
    public function loadClass($fqn, $path= '', $ignorePkg= false, $transient= false) {
        if (empty($fqn)) {
            $this->log(vPDO::LOG_LEVEL_ERROR, "No class specified for loadClass");
            return false;
        }
        $pos= strrpos($fqn, '.');
        if ($pos === false && empty($path) && !$ignorePkg && !$transient) {
            $driverClass = $this->getDriverClass($fqn);
            if ($driverClass !== false) {
                return $fqn;
            }
        } elseif (strpos($fqn, '\\') !== false && class_exists($fqn)) {
            return $fqn;
        }
        if (!$transient) {
            $typePos= strrpos($fqn, '_' . $this->config['dbtype']);
            if ($typePos !== false) {
                $fqn= substr($fqn, 0, $typePos);
            }
        }
        if ($pos === false) {
            $class= $fqn;
            if ($transient) {
                $fqn= strtolower($class);
            } else {
                $fqn= $this->config['dbtype'] . '.' . strtolower($class);
            }
        } else {
            $class= substr($fqn, $pos +1);
            if ($transient) {
                $fqn= substr($fqn, 0, $pos) . '.' . strtolower($class);
            } else {
                $fqn= substr($fqn, 0, $pos) . '.' . $this->config['dbtype'] . '.' . strtolower($class);
            }
        }
        // check if class exists
        if (!$transient && isset ($this->map[$class])) return $class;
        $included= class_exists($class, false);
        if ($included) {
            if ($transient || (!$transient && isset ($this->map[$class]))) {
                return $class;
            }
        }
        $classname= $class;
        if (!empty($path) || $ignorePkg) {
            $class= $this->_loadClass($class, $fqn, $included, $path, $transient);
        } elseif (isset ($this->packages[$this->package])) {
            $pqn= $this->package . '.' . $fqn;
            if (!$pkgClass= $this->_loadClass($class, $pqn, $included, $this->packages[$this->package]['path'], $transient)) {
                foreach ($this->packages as $pkg => $pkgDef) {
                    if ($pkg === $this->package) continue;
                    $pqn= $pkg . '.' . $fqn;
                    if ($pkgClass= $this->_loadClass($class, $pqn, $included, $pkgDef['path'], $transient)) {
                        break;
                    }
                }
            }
            $class= $pkgClass;
        } else {
            $class= false;
        }
        if ($class === false) {
            $this->log(vPDO::LOG_LEVEL_ERROR, "Could not load class: {$classname} from {$fqn}");
        }
        return $class;
    }

    protected function _loadClass($class, $fqn, $included= false, $path= '', $transient= false) {
        if (empty($path)) $path= VPDO_CORE_PATH;
        if (!$included) {
            /* turn to filesystem path and enforce all lower-case paths and filenames */
            $fqcn= str_replace('.', '/', $fqn) . '.class.php';
            /* include class */
            if (!file_exists($path . $fqcn)) return false;
            if (!$rt= include_once ($path . $fqcn)) {
                $this->log(vPDO::LOG_LEVEL_WARN, "Could not load class: {$class} from {$path}{$fqcn}");
                $class= false;
            }
        }
        if ($class && !$transient && !isset ($this->map[$class])) {
            $mapfile= strtr($fqn, '.', '/') . '.map.inc.php';
            if (file_exists($path . $mapfile)) {
                $vpdo_meta_map= array();
                $rt= include ($path . $mapfile);
                if (!$rt || !isset($vpdo_meta_map[$class])) {
                    $this->log(vPDO::LOG_LEVEL_WARN, "Could not load metadata map {$mapfile} for class {$class} from {$fqn}");
                } else {
                    if (!array_key_exists('fieldAliases', $vpdo_meta_map[$class])) {
                        $vpdo_meta_map[$class]['fieldAliases'] = array();
                    }
                    $this->map[$class] = $vpdo_meta_map[$class];
                }
            }
        }
        return $class;
    }

    /**
     * Get an vPDO configuration option value by key.
     *
     * @param string $key The option key.
     * @param array|null $options A set of options to override those from vPDO.
     * @param mixed|null $default An optional default value to return if no value is found.
     * @param bool $skipEmpty True if empty string values should be ignored.
     * @return mixed The configuration option value.
     */
    public function getOption($key, $options = null, $default = null, $skipEmpty = false) {
        $option = null;
        if (is_string($key) && !empty($key)) {
            $found = false;
            if (isset($options[$key])) {
                $found = true;
                $option = $options[$key];
            }

            if ((!$found || ($skipEmpty && $option === '')) && isset($this->config[$key])) {
                $found = true;
                $option = $this->config[$key];
            }

            if (!$found || ($skipEmpty && $option === ''))
                $option = $default;
        }
        else if (is_array($key)) {
            if (!is_array($option)) {
                $default = $option;
                $option = array();
            }
            foreach($key as $k) {
                $option[$k] = $this->getOption($k, $options, $default);
            }
        }
        else
            $option = $default;

        return $option;
    }

    /**
     * Sets an vPDO configuration option value.
     *
     * @param string $key The option key.
     * @param mixed $value A value to set for the given option key.
     */
    public function setOption($key, $value) {
        $this->config[$key]= $value;
    }

    /**
     * Call a static method from a valid package class with arguments.
     *
     * Will always search for database-specific class files first.
     *
     * @param string $class The name of a class to to get the static method from.
     * @param string $method The name of the method you want to call.
     * @param array $args An array of arguments for the method.
     * @param boolean $transient Indicates if the class has dbtype derivatives. Set to true if you
     * want to use on classes not derived from vPDOObject.
     * @return mixed|null The callback method's return value or null if no valid method is found.
     */
    public function call($class, $method, array $args = array(), $transient = false) {
        $return = null;
        $callback = '';
        if ($transient) {
            $className = $this->loadClass($class, '', false, true);
            if ($className) {
                $callback = array($className, $method);
            }
        } else {
            $className = $this->loadClass($class);
            if ($className) {
                $className = $this->getPlatformClass($className);
                $callback = array($className, $method);
            }
        }
        if (!empty($callback) && is_callable($callback)) {
            try {
                $return = $className::$method(...$args);
            } catch (\Exception $e) {
                $this->log(vPDO::LOG_LEVEL_ERROR, "An exception occurred calling {$className}::{$method}() - " . $e->getMessage());
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "{$class}::{$method}() is not a valid static method.");
        }
        return $return;
    }

    /**
     * Creates a new instance of a specified class.
     *
     * All new objects created with this method are transient until {@link
     * vPDOObject::save()} is called the first time and is reflected by the
     * {@link Om\vPDOObject::$_new} property.
     *
     * @template T of Om\vPDOObject
     * @param class-string<T> $className Name of the class to get a new instance of.
     * @param array $fields An associated array of field names/values to
     * populate the object with.
     * @return T|null A new instance of the specified class, or null if a
     * new object could not be instantiated.
     */
    public function newObject($className, $fields= array ()) {
        $instance= null;
        if ($className = $this->loadClass($className)) {
            $className = self::getPlatformClass($className);
            /** @var Om\vPDOObject $instance */
            if ($instance = new $className($this)) {
                if (is_array($fields) && !empty($fields)) {
                    $instance->fromArray($fields);
                }
            }
        }
        return $instance;
    }

    /**
     * Retrieves a single object instance by the specified criteria.
     *
     * The criteria can be a primary key value, and array of primary key values
     * (for multiple primary key objects) or an {@link vPDOCriteria} object. If
     * no $criteria parameter is specified, no class is found, or an object
     * cannot be located by the supplied criteria, null is returned.
     *
     * @uses vPDOObject::load()
     * @template T of Om\vPDOObject
     * @param class-string<T> $className Name of the class to get an instance of.
     * @param mixed $criteria Primary key of the record or a vPDOCriteria object.
     * @param mixed $cacheFlag If an integer value is provided, this specifies
     * the time to live in the object cache; if cacheFlag === false, caching is
     * ignored for the object and if cacheFlag === true, the object will live in
     * cache indefinitely.
     * @return T|null An instance of the class, or null if it could not be
     * instantiated.
    */
    public function getObject($className, $criteria= null, $cacheFlag= true) {
        $instance= null;
        $this->sanitizePKCriteria($className, $criteria);
        if ($criteria !== null) {
            $instance = $this->call($className, 'load', array(& $this, $className, $criteria, $cacheFlag));
        }
        return $instance;
    }

    /**
     * Retrieves a collection of vPDOObjects by the specified vPDOCriteria.
     *
     * @uses vPDOObject::loadCollection()
     * @template T of Om\vPDOObject
     * @param class-string<T> $className Name of the class to search for instances of.
     * @param object|array|string $criteria An vPDOCriteria object or an array
     * search expression.
     * @param mixed $cacheFlag If an integer value is provided, this specifies
     * the time to live in the result set cache; if cacheFlag === false, caching
     * is ignored for the collection and if cacheFlag === true, the objects will
     * live in cache until flushed by another process.
     * @return array<int, T> An array of class instances retrieved.
    */
    public function getCollection($className, $criteria= null, $cacheFlag= true) {
        return $this->call($className, 'loadCollection', array(& $this, $className, $criteria, $cacheFlag));
    }

    /**
     * Retrieves an iterable representation of a collection of vPDOObjects.
     *
     * @param string $className Name of the class to search for instances of.
     * @param mixed $criteria An vPDOCriteria object or representation.
     * @param bool $cacheFlag If an integer value is provided, this specifies
     * the time to live in the result set cache; if cacheFlag === false, caching
     * is ignored for the collection and if cacheFlag === true, the objects will
     * live in cache until flushed by another process.
     * @return vPDOIterator An iterable representation of a collection.
     */
    public function getIterator($className, $criteria= null, $cacheFlag= true) {
        return new vPDOIterator($this, array('class' => $className, 'criteria' => $criteria, 'cacheFlag' => $cacheFlag));
    }

    /**
     * Update field values across a collection of vPDOObjects.
     *
     * @param string $className Name of the class to update fields of.
     * @param array $set An associative array of field/value pairs representing the updates to make.
     * @param mixed $criteria An vPDOCriteria object or representation.
     * @return bool|int The number of instances affected by the update or false on failure.
     */
    public function updateCollection($className, array $set, $criteria= null) {
        $affected = false;
        if ($this->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            $query = $this->newQuery($className);
            if ($query && !empty($set)) {
                $query->command('UPDATE');
                $query->set($set);
                if (!empty($criteria)) $query->where($criteria);
                if ($query->prepare()) {
                    $affected = $this->exec($query->toSQL());
                    if ($affected === false) {
                        $this->log(vPDO::LOG_LEVEL_ERROR, "Error updating {$className} instances using query " . $query->toSQL(), '', __METHOD__, __FILE__, __LINE__);
                    } else {
                        if ($this->getOption(vPDO::OPT_CACHE_DB)) {
                            $relatedClasses = array($query->getTableClass());
                            $related = array_merge($this->getAggregates($className), $this->getComposites($className));
                            foreach ($related as $relatedAlias => $relatedMeta) {
                                $relatedClasses[] = $relatedMeta['class'];
                            }
                            $relatedClasses = array_unique($relatedClasses);
                            foreach ($relatedClasses as $relatedClass) {
                                $this->cacheManager->delete($relatedClass, array(
                                    vPDO::OPT_CACHE_KEY => $this->getOption('cache_db_key', null, 'db'),
                                    vPDO::OPT_CACHE_HANDLER => $this->getOption(vPDO::OPT_CACHE_DB_HANDLER, null, $this->getOption(vPDO::OPT_CACHE_HANDLER, null, 'vPDO\\Cache\\vPDOFileCache')),
                                    vPDO::OPT_CACHE_FORMAT => (integer) $this->getOption('cache_db_format', null, $this->getOption(vPDO::OPT_CACHE_FORMAT, null, Cache\vPDOCacheManager::CACHE_PHP)),
                                    vPDO::OPT_CACHE_PREFIX => $this->getOption('cache_db_prefix', null, Cache\vPDOCacheManager::CACHE_DIR),
                                    vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE => true
                                ));
                            }
                        }
                        $callback = $this->getOption(vPDO::OPT_CALLBACK_ON_SAVE);
                        if ($callback && is_callable($callback)) {
                            call_user_func($callback, array('className' => $className, 'criteria' => $query, 'object' => null));
                        }
                    }
                }
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "Could not get connection for writing data", '', __METHOD__, __FILE__, __LINE__);
        }
        return $affected;
    }

    /**
     * Remove an instance of the specified className by a supplied criteria.
     *
     * @param string $className The name of the class to remove an instance of.
     * @param mixed $criteria Valid vPDO criteria for selecting an instance.
     * @return boolean True if the instance is successfully removed.
     */
    public function removeObject($className, $criteria) {
        $removed= false;
        if ($this->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            if ($this->getCount($className, $criteria) === 1) {
                if ($query= $this->newQuery($className)) {
                    $query->command('DELETE');
                    $query->where($criteria);
                    if ($query->prepare()) {
                        if ($this->exec($query->toSQL()) !== 1) {
                            $this->log(vPDO::LOG_LEVEL_ERROR, "vPDO->removeObject - Error deleting {$className} instance using query " . $query->toSQL());
                        } else {
                            $removed= true;
                            if ($this->getOption(vPDO::OPT_CACHE_DB)) {
                                $this->cacheManager->delete(Cache\vPDOCacheManager::CACHE_DIR . $query->getAlias(), array(vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE => true));
                            }
                            $callback = $this->getOption(vPDO::OPT_CALLBACK_ON_REMOVE);
                            if ($callback && is_callable($callback)) {
                                call_user_func($callback, array('className' => $className, 'criteria' => $query));
                            }
                        }
                    }
                }
            } else {
                $this->log(vPDO::LOG_LEVEL_WARN, "vPDO->removeObject - {$className} instance to remove not found!");
                if ($this->getDebug() === true) $this->log(vPDO::LOG_LEVEL_DEBUG, "vPDO->removeObject - {$className} instance to remove not found using criteria " . print_r($criteria, true));
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "Could not get connection for writing data", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    /**
     * Remove a collection of instances by the supplied className and criteria.
     *
     * @param string $className The name of the class to remove a collection of.
     * @param mixed $criteria Valid vPDO criteria for selecting a collection.
     * @return boolean|integer False if the remove encounters an error, otherwise an integer value
     * representing the number of rows that were removed.
     */
    public function removeCollection($className, $criteria) {
        $removed= false;
        if ($this->getConnection(array(vPDO::OPT_CONN_MUTABLE => true))) {
            if ($query= $this->newQuery($className)) {
                $query->command('DELETE');
                $query->where($criteria);
                if ($query->prepare()) {
                    $removed= $this->exec($query->toSQL());
                    if ($removed === false) {
                        $this->log(vPDO::LOG_LEVEL_ERROR, "vPDO->removeCollection - Error deleting {$className} instances using query " . $query->toSQL());
                    } else {
                        if ($this->getOption(vPDO::OPT_CACHE_DB)) {
                            $this->cacheManager->delete(Cache\vPDOCacheManager::CACHE_DIR . $query->getAlias(), array(vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE => true));
                        }
                        $callback = $this->getOption(vPDO::OPT_CALLBACK_ON_REMOVE);
                        if ($callback && is_callable($callback)) {
                            call_user_func($callback, array('className' => $className, 'criteria' => $query));
                        }
                    }
                } else {
                    $this->log(vPDO::LOG_LEVEL_ERROR, "vPDO->removeCollection - Error preparing statement to delete {$className} instances using query: {$query->toSQL()}");
                }
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "Could not get connection for writing data", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    /**
     * Retrieves a count of vPDOObjects by the specified vPDOCriteria.
     *
     * @param string $className Class of vPDOObject to count instances of.
     * @param mixed $criteria Any valid vPDOCriteria object or expression.
     * @return integer The number of instances found by the criteria.
     */
    public function getCount($className, $criteria = null) {
        $count = 0;
        if ($query = $this->newQuery($className, $criteria)) {
            $stmt = null;
            $expr = '*';
            if ($pk = $this->getPK($className)) {
                if (!is_array($pk)) {
                    $pk = array($pk);
                }
                $expr = $this->getSelectColumns($className, $query->getAlias(), '', $pk);
            }
            if (isset($query->query['columns'])) {
                $query->query['columns'] = array();
            }
            if (!empty($query->query['groupby']) || !empty($query->query['having'])) {
                $query->select($expr);
                if ($query->prepare()) {
                    $countQuery = new vPDOCriteria($this, "SELECT COUNT(*) FROM ({$query->toSQL(false)}) cq", $query->bindings, $query->cacheFlag);
                    $stmt = $countQuery->prepare();
                }
            } else {
                $query->select(array("COUNT(DISTINCT {$expr})"));
                $stmt = $query->prepare();
            }
            if ($stmt && $stmt->execute()) {
                $count = intval($stmt->fetchColumn());
            }
        }
        return $count;
    }

    /**
     * Retrieves an vPDOObject instance with specified related objects.
     *
     * @uses vPDO::getCollectionGraph()
     * @template T of Om\vPDOObject
     * @param class-string<T> $className The name of the class to return an instance of.
     * @param string|array $graph A related object graph in array or JSON
     * format, e.g. array('relationAlias'=>array('subRelationAlias'=>array()))
     * or {"relationAlias":{"subRelationAlias":{}}}.  Note that the empty arrays
     * are necessary in order for the relation to be recognized.
     * @param mixed $criteria A valid vPDOCriteria instance or expression.
     * @param boolean|integer $cacheFlag Indicates if the result set should be
     * cached, and optionally for how many seconds.
     * @return T|null The object instance with related objects from the graph
     * hydrated, or null if no instance can be located by the criteria.
     */
    public function getObjectGraph($className, $graph, $criteria= null, $cacheFlag= true) {
        $object= null;
        $this->sanitizePKCriteria($className, $criteria);
        if ($collection= $this->getCollectionGraph($className, $graph, $criteria, $cacheFlag)) {
            if (!count($collection) === 1) {
                $this->log(vPDO::LOG_LEVEL_WARN, 'getObjectGraph criteria returned more than one instance.');
            }
            $object= reset($collection);
        }
        return $object;
    }

    /**
     * Retrieves a collection of vPDOObject instances with related objects.
     *
     * @uses vPDOQuery::bindGraph()
     * @template T of Om\vPDOObject
     * @param class-string<T> $className The name of the class to return a collection of.
     * @param string|array $graph A related object graph in array or JSON
     * format, e.g. array('relationAlias'=>array('subRelationAlias'=>array()))
     * or {"relationAlias":{"subRelationAlias":{}}}.  Note that the empty arrays
     * are necessary in order for the relation to be recognized.
     * @param mixed $criteria A valid vPDOCriteria instance or condition string.
     * @param boolean $cacheFlag Indicates if the result set should be cached.
     * @return array<int, T> An array of instances matching the criteria with related
     * objects from the graph hydrated.  An empty array is returned when no
     * matches are found.
     */
    public function getCollectionGraph($className, $graph, $criteria= null, $cacheFlag= true) {
        return $this->call($className, 'loadCollectionGraph', array(& $this, $className, $graph, $criteria, $cacheFlag));
    }

    /**
     * Execute a PDOStatement and get a single column value from the first row of the result set.
     *
     * @param \PDOStatement $stmt A prepared PDOStatement object ready to be executed.
     * @param null|integer $column 0-indexed number of the column you wish to retrieve from the row. If
     * null or no value is supplied, it fetches the first column.
     * @return mixed The value of the specified column from the first row of the result set, or null.
     */
    public function getValue($stmt, $column= null) {
        $value = null;
        if (is_object($stmt) && $stmt instanceof \PDOStatement) {
            $tstart = microtime(true);
            if ($stmt->execute()) {
                $this->queryTime += microtime(true) - $tstart;
                $this->executedQueries++;
                $value= $stmt->fetchColumn((int)$column);
                $stmt->closeCursor();
            } else {
                $this->queryTime += microtime(true) - $tstart;
                $this->executedQueries++;
                $this->log(vPDO::LOG_LEVEL_ERROR, "Error " . $stmt->errorCode() . " executing statement: \n" . print_r($stmt->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "No valid PDOStatement provided to getValue", '', __METHOD__, __FILE__, __LINE__);
        }
        return $value;
    }

    /**
     * Convert any valid criteria into an vPDOQuery instance.
     *
     * @todo Get criteria pre-defined in an {@link vPDOObject} class metadata
     * definition by name.
     *
     * @todo Define callback functions as an alternative to retreiving criteria
     * sql and/or bindings from the metadata.
     *
     * @param string $className The class to get predefined criteria for.
     * @param string $type The type of criteria to get (you can define any
     * type you want, but 'object' and 'collection' are the typical criteria
     * for retrieving single and multiple instances of an object).
     * @param boolean|integer $cacheFlag Indicates if the result is cached and
     * optionally for how many seconds.
     * @return Om\vPDOCriteria A criteria object or null if not found.
     */
    public function getCriteria($className, $type= null, $cacheFlag= true) {
        return $this->newQuery($className, $type, $cacheFlag);
    }

    /**
     * Validate and return the type of a specified criteria variable.
     *
     * @param mixed $criteria An vPDOCriteria instance or any valid criteria variable.
     * @return string|null The type of valid criteria passed, or null if the criteria is not valid.
     */
    public function getCriteriaType($criteria) {
        $type = gettype($criteria);
        if ($type === 'object') {
            $type = get_class($criteria);
            if (!$criteria instanceof Om\vPDOCriteria) {
                $this->log(vPDO::LOG_LEVEL_WARN, "Invalid criteria object of class {$type} encountered.", '', __METHOD__, __FILE__, __LINE__);
                $type = null;
            } elseif ($criteria instanceof Om\vPDOQuery) {
                $type = 'vPDOQuery';
            } else {
                $type = 'vPDOCriteria';
            }
        }
        return $type;
    }

    /**
     * Add criteria when requesting a derivative class row automatically.
     *
     * This applies class_key filtering for single-table inheritance queries and may
     * provide a convenient location for similar features in the future.
     *
     * @param string $className A valid vPDOObject derivative table class.
     * @param Om\vPDOQuery $criteria A valid vPDOQuery instance.
     * @return Om\vPDOQuery The vPDOQuery instance with derivative criteria added.
     */
    public function addDerivativeCriteria($className, $criteria) {
        if ($criteria instanceof Om\vPDOQuery && ($className = $this->loadClass($className)) && !isset($this->map[$className]['table'])) {
            if (isset($this->map[$className]['fields']['class_key']) && !empty($this->map[$className]['fields']['class_key'])) {
                $criteria->where(array('class_key' => $this->map[$className]['fields']['class_key']));
                if ($this->getDebug() === true) {
                    $this->log(vPDO::LOG_LEVEL_DEBUG, "#1: Automatically adding class_key criteria for derivative query of class {$className}");
                }
            } else {
                foreach ($this->getAncestry($className, false) as $ancestor) {
                    if (isset($this->map[$ancestor]['table']) && isset($this->map[$ancestor]['fields']['class_key'])) {
                        $criteria->where(array('class_key' => $className));
                        if ($this->getDebug() === true) {
                            $this->log(vPDO::LOG_LEVEL_DEBUG, "#2: Automatically adding class_key criteria for derivative query of class {$className} from base table class {$ancestor}");
                        }
                        break;
                    }
                }
            }
        }
        return $criteria;
    }

    /**
     * Gets the package name from a specified class name.
     *
     * @param string $className The name of the class to lookup the package for.
     * @return string The package the class belongs to.
     */
    public function getPackage($className) {
        $package= '';
        if ($className= $this->loadClass($className)) {
            if (isset($this->map[$className]['package'])) {
                $package= $this->map[$className]['package'];
            }
            if (!$package && $ancestry= $this->getAncestry($className, false)) {
                foreach ($ancestry as $ancestor) {
                    if (isset ($this->map[$ancestor]['package']) && ($package= $this->map[$ancestor]['package'])) {
                        break;
                    }
                }
            }
        }
        return $package;
    }

    /**
     * Get an alias for the specified model class to be used in SQL queries.
     *
     * @param string $className The fully-qualified class name to get an alias for.
     *
     * @return string The alias for the specified class.
     */
    public function getAlias($className) {
        $alias = $className;
        if (strpos($alias, '\\') !== false) {
            $namespace = explode('\\', $alias);
            $alias = array_pop($namespace);
        }

        return $alias;
    }

    /**
     * Load and return a named service class instance.
     *
     * @deprecated Use the service/DI container to access services. Will be removed in 3.1.
     *
     * @param string $name The variable name of the instance.
     * @param string $class The service class name.
     * @param string $path An optional root path to search for the class.
     * @param array $params An array of optional params to pass to the service
     * class constructor.
     * @return object|null A reference to the service class instance or null if
     * it could not be loaded.
     */
    public function getService($name, $class= '', $path= '', $params= array ()) {
        $service= null;
        if (!$this->services->has($name) || !is_object($this->services->get($name))) {
            if (empty ($class) && isset ($this->config[$name . '.class'])) {
                $class= $this->config[$name . '.class'];
            } elseif (empty ($class)) {
                $class= $name;
            }
            $className= $this->loadClass($class, $path, false, true);
            if (!empty($className)) {
                $service = new $className($this, $params);
                if ($service) {
                    $this->services->add($name, $service);
                    $this->$name= $this->services->get($name);
                }
            }
        }
        if ($this->services->has($name)) {
            $service = $this->services->get($name);
        } else {
            if ($this->getDebug() === true) {
                $this->log(vPDO::LOG_LEVEL_DEBUG, "Problem getting service {$name}, instance of class {$class}, from path {$path}, with params " . print_r($params, true));
            } else {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Problem getting service {$name}, instance of class {$class}, from path {$path}");
            }
        }
        return $service;
    }

    /**
     * Gets the actual run-time table name from a specified class name.
     *
     * @param string $className The name of the class to lookup a table name
     * for.
     * @param boolean $includeDb Qualify the table name with the database name.
     * @return string The table name for the class, or null if unsuccessful.
     */
    public function getTableName($className, $includeDb= false) {
        $table= null;
        if ($className= $this->loadClass($className)) {
            if (isset ($this->map[$className]['table'])) {
                $table= $this->map[$className]['table'];
                if (isset($this->map[$className]['package']) && isset($this->packages[$this->map[$className]['package']]['prefix'])) {
                    $table= $this->packages[$this->map[$className]['package']]['prefix'] . $table;
                } else {
                    $table= $this->getOption(vPDO::OPT_TABLE_PREFIX, null, '') . $table;
                }
            }
            if (!$table && $ancestry= $this->getAncestry($className, false)) {
                foreach ($ancestry as $ancestor) {
                    if (isset ($this->map[$ancestor]['table']) && $table= $this->map[$ancestor]['table']) {
                        if (isset($this->map[$ancestor]['package']) && isset($this->packages[$this->map[$ancestor]['package']]['prefix'])) {
                            $table= $this->packages[$this->map[$ancestor]['package']]['prefix'] . $table;
                        } else {
                            $table= $this->getOption(vPDO::OPT_TABLE_PREFIX, null, '') . $table;
                        }
                        break;
                    }
                }
            }
        }
        if ($table) {
            $table= $this->_getFullTableName($table, $includeDb);
            if ($this->getDebug() === true) $this->log(vPDO::LOG_LEVEL_DEBUG, 'Returning table name: ' . $table . ' for class: ' . $className);
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, 'Could not get table name for class: ' . $className);
        }
        return $table;
    }

    /**
     * Get the class which defines the table for a specified className.
     *
     * @param string $className The name of a class to determine the table class from.
     * @return null|string The name of a class defining the table for the specified className; null if not found.
     */
    public function getTableClass($className) {
        $tableClass= null;
        if ($className= $this->loadClass($className)) {
            if (isset ($this->map[$className]['table'])) {
                $tableClass= $className;
            }
            if (!$tableClass && $ancestry= $this->getAncestry($className, false)) {
                foreach ($ancestry as $ancestor) {
                    if (isset ($this->map[$ancestor]['table'])) {
                        $tableClass= $ancestor;
                        break;
                    }
                }
            }
        }
        if ($tableClass) {
            if ($this->getDebug() === true) {
                $this->log(vPDO::LOG_LEVEL_DEBUG, 'Returning table class: ' . $tableClass . ' for class: ' . $className);
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, 'Could not get table class for class: ' . $className);
        }
        return $tableClass;
    }

    /**
     * Gets the actual run-time table metadata from a specified class name.
     *
     * @param string $className The name of the class to lookup a table name
     * for.
     * @return string The table meta data for the class, or null if
     * unsuccessful.
     */
    public function getTableMeta($className) {
        $tableMeta= null;
        if ($className= $this->loadClass($className)) {
            if (isset ($this->map[$className]['tableMeta'])) {
                $tableMeta= $this->map[$className]['tableMeta'];
            }
            if (!$tableMeta && $ancestry= $this->getAncestry($className)) {
                foreach ($ancestry as $ancestor) {
                    if (isset ($this->map[$ancestor]['tableMeta'])) {
                        if ($tableMeta= $this->map[$ancestor]['tableMeta']) {
                            break;
                        }
                    }
                }
            }
        }
        return $tableMeta;
    }

    /**
     * Indicates the inheritance model for the vPDOObject class specified.
     *
     * @param string $className The class to determine the table inherit type from.
     * @return string single, multiple, or none
     */
    public function getInherit($className) {
        $inherit= false;
        if ($className= $this->loadClass($className)) {
            if (isset ($this->map[$className]['inherit'])) {
                $inherit= $this->map[$className]['inherit'];
            }
            if (!$inherit && $ancestry= $this->getAncestry($className, false)) {
                foreach ($ancestry as $ancestor) {
                    if (isset ($this->map[$ancestor]['inherit'])) {
                        $inherit= $this->map[$ancestor]['inherit'];
                        break;
                    }
                }
            }
        }
        if (!empty($inherit)) {
            if ($this->getDebug() === true) {
                $this->log(vPDO::LOG_LEVEL_DEBUG, 'Returning inherit: ' . $inherit . ' for class: ' . $className);
            }
        } else {
            $inherit= 'none';
        }
        return $inherit;
    }

    /**
     * Gets a list of fields (or columns) for an object by class name.
     *
     * This includes default values for each field and is used by the objects
     * themselves to build their initial attributes based on class inheritence.
     *
     * @param string $className The name of the class to lookup fields for.
     * @return array An array featuring field names as the array keys, and
     * default field values as the array values; empty array is returned if
     * unsuccessful.
     */
    public function getFields($className) {
        $fields= array ();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) - 1; $i >= 0; $i--) {
                    if (isset ($this->map[$ancestry[$i]]['fields'])) {
                        $fields= array_merge($fields, $this->map[$ancestry[$i]]['fields']);
                    }
                }
            }
            if ($this->getInherit($className) === 'single') {
                $descendants= $this->getDescendants($className);
                if ($descendants) {
                    foreach ($descendants as $descendant) {
                        $descendantClass= $this->loadClass($descendant);
                        if ($descendantClass && isset($this->map[$descendantClass]['fields'])) {
                            $fields= array_merge($fields, array_diff_key($this->map[$descendantClass]['fields'], $fields));
                        }
                    }
                }
            }
        }
        return $fields;
    }

    /**
     * Gets a list of field (or column) definitions for an object by class name.
     *
     * These definitions are used by the objects themselves to build their
     * own meta data based on class inheritance.
     *
     * @param string $className The name of the class to lookup fields meta data
     * for.
     * @param boolean $includeExtended If true, include meta from all derivative
     * classes in loaded packages.
     * @return array An array featuring field names as the array keys, and
     * arrays of metadata information as the array values; empty array is
     * returned if unsuccessful.
     */
    public function getFieldMeta($className, $includeExtended = false) {
        $fieldMeta= array ();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) - 1; $i >= 0; $i--) {
                    if (isset ($this->map[$ancestry[$i]]['fieldMeta'])) {
                        $fieldMeta= array_merge($fieldMeta, $this->map[$ancestry[$i]]['fieldMeta']);
                    }
                }
            }
            if ($includeExtended && $this->getInherit($className) === 'single') {
                $descendants= $this->getDescendants($className);
                if ($descendants) {
                    foreach ($descendants as $descendant) {
                        $descendantClass= $this->loadClass($descendant);
                        if ($descendantClass && isset($this->map[$descendantClass]['fieldMeta'])) {
                            $fieldMeta= array_merge($fieldMeta, array_diff_key($this->map[$descendantClass]['fieldMeta'], $fieldMeta));
                        }
                    }
                }
            }
        }
        return $fieldMeta;
    }

    /**
     * Gets a collection of field aliases for an object by class name.
     *
     * @param string $className The name of the class to lookup field aliases for.
     * @return array An array of field aliases with aliases as keys and actual field names as values.
     */
    public function getFieldAliases($className) {
        $fieldAliases= array ();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) - 1; $i >= 0; $i--) {
                    if (isset ($this->map[$ancestry[$i]]['fieldAliases'])) {
                        $fieldAliases= array_merge($fieldAliases, $this->map[$ancestry[$i]]['fieldAliases']);
                    }
                }
            }
            if ($this->getInherit($className) === 'single') {
                $descendants= $this->getDescendants($className);
                if ($descendants) {
                    foreach ($descendants as $descendant) {
                        $descendantClass= $this->loadClass($descendant);
                        if ($descendantClass && isset($this->map[$descendantClass]['fieldAliases'])) {
                            $fieldAliases= array_merge($fieldAliases, array_diff_key($this->map[$descendantClass]['fieldAliases'], $fieldAliases));
                        }
                    }
                }
            }
        }
        return $fieldAliases;
    }

    /**
     * Gets a set of validation rules defined for an object by class name.
     *
     * @param string $className The name of the class to lookup validation rules
     * for.
     * @return array An array featuring field names as the array keys, and
     * arrays of validation rule information as the array values; empty array is
     * returned if unsuccessful.
     */
    public function getValidationRules($className) {
        $rules= array();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) - 1; $i >= 0; $i--) {
                    if (isset($this->map[$ancestry[$i]]['validation']['rules'])) {
                        $rules= array_merge($rules, $this->map[$ancestry[$i]]['validation']['rules']);
                    }
                }
            }
            if ($this->getInherit($className) === 'single') {
                $descendants= $this->getDescendants($className);
                if ($descendants) {
                    foreach ($descendants as $descendant) {
                        $descendantClass= $this->loadClass($descendant);
                        if ($descendantClass && isset($this->map[$descendantClass]['validation']['rules'])) {
                            $rules= array_merge($rules, array_diff_key($this->map[$descendantClass]['validation']['rules'], $rules));
                        }
                    }
                }
            }
            if ($this->getDebug() === true) {
                $this->log(vPDO::LOG_LEVEL_DEBUG, "Returning validation rules: " . print_r($rules, true));
            }
        }
        return $rules;
    }

    /**
     * Get indices defined for a table class.
     *
     * @param string $className The name of the class to lookup indices for.
     * @return array An array of indices and their details for the specified class.
     */
    public function getIndexMeta($className) {
        $indices= array();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) -1; $i >= 0; $i--) {
                    if (isset($this->map[$ancestry[$i]]['indexes'])) {
                        $indices= array_merge($indices, $this->map[$ancestry[$i]]['indexes']);
                    }
                }
                if ($this->getInherit($className) === 'single') {
                    $descendants= $this->getDescendants($className);
                    if ($descendants) {
                        foreach ($descendants as $descendant) {
                            $descendantClass= $this->loadClass($descendant);
                            if ($descendantClass && isset($this->map[$descendantClass]['indexes'])) {
                                $indices= array_merge($indices, array_diff_key($this->map[$descendantClass]['indexes'], $indices));
                            }
                        }
                    }
                }
                if ($this->getDebug() === true) {
                    $this->log(vPDO::LOG_LEVEL_DEBUG, "Returning indices: " . print_r($indices, true));
                }
            }
        }
        return $indices;
    }

    /**
     * Gets the primary key field(s) for a class.
     *
     * @param string $className The name of the class to lookup the primary key
     * for.
     * @return mixed The name of the field representing a class instance primary
     * key, an array of key names for compound primary keys, or null if no
     * primary key is found or defined for the class.
     */
    public function getPK($className) {
        $pk= null;
        if (strcasecmp($className, 'vPDOObject') !== 0) {
            if ($actualClassName= $this->loadClass($className)) {
                if (isset ($this->map[$actualClassName]['indexes'])) {
                    foreach ($this->map[$actualClassName]['indexes'] as $k => $v) {
                        if (isset($v['primary']) && ($v['primary'] == true) && isset($v['columns'])) {
                            foreach ($v['columns'] as $field => $column) {
                                if (isset ($this->map[$actualClassName]['fieldMeta'][$field]['phptype'])) {
                                    $pk[$field] = $field;
                                }
                            }
                        }
                    }
                }
                if (isset ($this->map[$actualClassName]['fieldMeta'])) {
                    foreach ($this->map[$actualClassName]['fieldMeta'] as $k => $v) {
                        if (isset ($v['index']) && isset ($v['phptype']) && $v['index'] == 'pk') {
                            $pk[$k]= $k;
                        }
                    }
                }
                if ($ancestry= $this->getAncestry($actualClassName)) {
                    foreach ($ancestry as $ancestor) {
                        if ($ancestorClassName= $this->loadClass($ancestor)) {
                            if (isset ($this->map[$ancestorClassName]['indexes'])) {
                                foreach ($this->map[$ancestorClassName]['indexes'] as $k => $v) {
                                    if (isset ($this->map[$ancestorClassName]['fieldMeta'][$k]['phptype'])) {
                                        if (isset ($v['primary']) && $v['primary'] == true) {
                                            $pk[$k]= $k;
                                        }
                                    }
                                }
                            }
                            if (isset ($this->map[$ancestorClassName]['fieldMeta'])) {
                                foreach ($this->map[$ancestorClassName]['fieldMeta'] as $k => $v) {
                                    if (isset ($v['index']) && isset ($v['phptype']) && $v['index'] == 'pk') {
                                        $pk[$k]= $k;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($pk && count($pk) === 1) {
                    $pk= current($pk);
                }
            } else {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Could not load class {$className}");
            }
        }
        return $pk;
    }

    /**
     * Gets the type of primary key field for a class.
     *
     * @param string $className The name of the class to lookup the primary key
     * type for.
     * @param mixed $pk Optional specific PK column or columns to get type(s) for.
     * @return string The type of the field representing a class instance primary
     * key, or null if no primary key is found or defined for the class.
     */
    public function getPKType($className, $pk= false) {
        $pktype= null;
        if ($actualClassName= $this->loadClass($className)) {
            if (!$pk)
                $pk= $this->getPK($actualClassName);
            if (!is_array($pk))
                $pk= array($pk);
            $ancestry= $this->getAncestry($actualClassName, true);
            foreach ($pk as $_pk) {
                foreach ($ancestry as $parentClass) {
                    if (isset ($this->map[$parentClass]['fieldMeta'][$_pk]['phptype'])) {
                        $pktype[$_pk]= $this->map[$parentClass]['fieldMeta'][$_pk]['phptype'];
                        break;
                    }
                }
            }
            if (is_array($pktype) && count($pktype) == 1) {
                $pktype= reset($pktype);
            }
            elseif (empty($pktype)) {
                $pktype= null;
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "Could not load class {$className}!");
        }
        return $pktype;
    }

    /**
     * Gets a collection of aggregate foreign key relationship definitions.
     *
     * @param string $className The fully-qualified name of the class.
     * @return array An array of aggregate foreign key relationship definitions.
     */
    public function getAggregates($className) {
        $aggregates= array ();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) - 1; $i >= 0; $i--) {
                    if (isset ($this->map[$ancestry[$i]]['aggregates'])) {
                        $aggregates= array_merge($aggregates, $this->map[$ancestry[$i]]['aggregates']);
                    }
                }
            }
            if ($this->getInherit($className) === 'single') {
                $descendants= $this->getDescendants($className);
                if ($descendants) {
                    foreach ($descendants as $descendant) {
                        $descendantClass= $this->loadClass($descendant);
                        if ($descendantClass && isset($this->map[$descendantClass]['aggregates'])) {
                            $aggregates= array_merge($aggregates, array_diff_key($this->map[$descendantClass]['aggregates'], $aggregates));
                        }
                    }
                }
            }
        }
        return $aggregates;
    }

    /**
     * Gets a collection of composite foreign key relationship definitions.
     *
     * @param string $className The fully-qualified name of the class.
     * @return array An array of composite foreign key relationship definitions.
     */
    public function getComposites($className) {
        $composites= array ();
        if ($className= $this->loadClass($className)) {
            if ($ancestry= $this->getAncestry($className)) {
                for ($i= count($ancestry) - 1; $i >= 0; $i--) {
                    if (isset ($this->map[$ancestry[$i]]['composites'])) {
                        $composites= array_merge($composites, $this->map[$ancestry[$i]]['composites']);
                    }
                }
            }
            if ($this->getInherit($className) === 'single') {
                $descendants= $this->getDescendants($className);
                if ($descendants) {
                    foreach ($descendants as $descendant) {
                        $descendantClass= $this->loadClass($descendant);
                        if ($descendantClass && isset($this->map[$descendantClass]['composites'])) {
                            $composites= array_merge($composites, array_diff_key($this->map[$descendantClass]['composites'], $composites));
                        }
                    }
                }
            }
        }
        return $composites;
    }

    /**
     * Get a complete relation graph for an vPDOObject class.
     *
     * @param string $className A fully-qualified vPDOObject class name.
     * @param int $depth The depth to retrieve relations for the graph, defaults to 3.
     * @param array &$parents An array of parent classes to avoid traversing circular dependencies.
     * @param array &$visited An array of already visited classes to avoid traversing circular dependencies.
     * @return array An vPDOObject relation graph, or an empty array if no graph can be constructed.
     */
    public function getGraph($className, $depth= 3, &$parents = array(), &$visited = array()) {
        $graph = array();
        $className = $this->loadClass($className);
        if ($className && $depth > 0) {
            $depth--;
            $parents = array_merge($parents, $this->getAncestry($className));
            $parentsNested = array_unique($parents);
            $visitNested = array_merge($visited, array($className));
            $relations = array_merge($this->getAggregates($className), $this->getComposites($className));
            foreach ($relations as $alias => $relation) {
                if (in_array($relation['class'], $visited)) {
                    continue;
                }
                $childGraph = array();
                if ($depth > 0 && !in_array($relation['class'], $parents)) {
                    $childGraph = $this->getGraph($relation['class'], $depth, $parentsNested, $visitNested);
                }
                $graph[$alias] = $childGraph;
            }
            $visited[] = $className;
        }
        return $graph;
    }

    /**
     * Retrieves the complete ancestry for a class.
     *
     * @param string $className The name of the class.
     * @param bool $includeSelf Determines if the specified class should be
     * included in the resulting array.
     * @return array An array of string class names representing the class
     * hierarchy, or an empty array if unsuccessful.
     */
    public function getAncestry($className, $includeSelf= true) {
        $ancestry= array ();
        if ($actualClassName= $this->loadClass($className)) {
            $ancestor= $actualClassName;
            if ($includeSelf) {
                $ancestry[]= $actualClassName;
            }
            while ($ancestor= get_parent_class($ancestor)) {
                $ancestry[]= $ancestor;
            }
            if ($this->getDebug() === true) {
                $this->log(vPDO::LOG_LEVEL_DEBUG, "Returning ancestry for {$className}: " . print_r($ancestry, 1));
            }
        }
        return $ancestry;
    }

    /**
     * Gets select columns from a specific class for building a query.
     *
     * @uses vPDOObject::getSelectColumns()
     * @param string $className The name of the class to build the column list
     * from.
     * @param string $tableAlias An optional alias for the class table, to be
     * used in complex queries with multiple tables.
     * @param string $columnPrefix An optional string with which to prefix the
     * columns returned, to avoid name collisions in return columns.
     * @param array $columns An optional array of columns to include.
     * @param boolean $exclude If true, will exclude columns in the previous
     * parameter, instead of including them.
     * @return string A valid SQL string of column names for a SELECT statement.
     */
    public function getSelectColumns($className, $tableAlias= '', $columnPrefix= '', $columns= array (), $exclude= false) {
        return $this->call($className, 'getSelectColumns', array(&$this, $className, $tableAlias, $columnPrefix, $columns, $exclude));
    }

    /**
     * Gets an aggregate or composite relation definition from a class.
     *
     * @param string $parentClass The class from which the relation is defined.
     * @param string $alias The alias identifying the related class.
     * @return array The aggregate or composite definition details in an array
     * or null if no definition is found.
     */
    function getFKDefinition($parentClass, $alias) {
        $def= null;
        $parentClass= $this->loadClass($parentClass);
        if ($parentClass && $alias) {
            if ($aggregates= $this->getAggregates($parentClass)) {
                if (isset ($aggregates[$alias])) {
                    $def= $aggregates[$alias];
                    $def['type']= 'aggregate';
                }
            }
            if ($composites= $this->getComposites($parentClass)) {
                if (isset ($composites[$alias])) {
                    $def= $composites[$alias];
                    $def['type']= 'composite';
                }
            }
        }
        if ($def === null) {
            $this->log(vPDO::LOG_LEVEL_ERROR, 'No foreign key definition for parentClass: ' . $parentClass . ' using relation alias: ' . $alias);
        }
        return $def;
    }

    /**
     * Gets the version string of the schema the specified class was generated from.
     *
     * @param string $className The name of the class to get the model version from.
     * @return string The version string for the schema model the class was generated from.
     */
    public function getModelVersion($className) {
        $version = '1.0';
        $className= $this->loadClass($className);
        if ($className && isset($this->map[$className]['version'])) {
            $version= $this->map[$className]['version'];
        }
        return $version;
    }

    /**
     * OK!!!
     * Получает класс manager для этого соединения vPDO.
     *
     * Класс manager может выполнять такие операции, как создание или изменение
     * структуры таблиц, создание контейнеров данных, создание пользовательской сохраняемости
     * классы и другие расширенные операции, которые не нужно загружать
     * часто.
     *
     * @return Om\vPDOManager|null Экземпляр vPDOManager для подключения vPDO или null
     * если класс менеджера не может быть создан.
     */
    public function getManager() {
        if ($this->manager === null || !$this->manager instanceof Om\vPDOManager) {
            $managerClass = '\\vPDO\\Om\\' . $this->config['dbtype'] . '\\vPDOManager';
            $this->manager= new $managerClass($this);
            if (!$this->manager) {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Не удалось загрузить класс vPDOManager.");
            }
        }
        return $this->manager;
    }

    /**
     * OK!!!
     * Получает класс драйвера для этого pdo-соединения.
     *
     * Класс driver предоставляет базовые данные и операции для конкретного драйвера базы данных.
     *
     * @return Om\vPDODriver|null Экземпляр vPDODriver для подключения vPDO или null
     * если класс драйвера не может быть создан.
     */
    public function getDriver() {
        if ($this->driver === null || !$this->driver instanceof Om\vPDODriver) {
            $driverClass = '\\vPDO\\Om\\' . $this->config['dbtype'] . '\\vPDODriver';
            $this->driver= new $driverClass($this);
            if (!$this->driver) {
                $this->log(vPDO::LOG_LEVEL_ERROR, "Не удалось загрузить класс драйвера vPDO для {$this->config['dbtype']} PDO-драйвера.");
            }
        }
        return $this->driver;
    }

    /**
     * OK!!!
     * Возвращает абсолютный путь к каталогу кэша.
     *
     * @return string Полный путь к каталогу кэша.
     */
    public function getCachePath() {
        if (!$this->cachePath) {
            if ($this->getCacheManager()) {
                $this->cachePath= $this->cacheManager->getCachePath();
            }
        }
        return $this->cachePath;
    }

    /**
     * OK!!!
     * Получает экземпляр vPDOCacheManager.
     *
     * Этот класс отвечает за обработку всех типов операций кэширования для ядра vPDO.
     *
     * @param string $class Необязательное имя производного класса vPDOCacheManager.
     * @param array $options Массив параметров для экземпляра cache manager; 
     * допустимые параметры включают:
     * - path = Необязательный корневой путь для поиска $class.
     * - ignorePkg = Если значение равно false и вы не указываете путь, вы можете просмотреть пользовательский vPDOCacheManager
     * производные в заявленных упаковках.
     * @return Cache\vPDOCacheManager vPDOCacheManager для этого экземпляра vPDO.
     */
    public function getCacheManager($class= 'vPDO\\Cache\\vPDOCacheManager', $options = array('path' => VPDO_CORE_PATH, 'ignorePkg' => true)) {
        if ($this->cacheManager === null || !is_object($this->cacheManager) || !($this->cacheManager instanceof $class)) {
            if ($this->cacheManager= new $class($this, $options)) {
                $this->_cacheEnabled= true;
            }
        }
        return $this->cacheManager;
    }

    /**
     * OK!!!
     * Возвращает состояние отладки для экземпляра vPDO.
     *
     * @return boolean|integer Текущее состояние отладки для экземпляра, true для on,
     * false для off.
     */
    public function getDebug() {
        return $this->_debug;
    }

    /**
     * OK!!!
     * Устанавливает состояние отладки для экземпляра vPDO.
     *
     * @param boolean|integer $v Статус отладки, true для вкл., false для выкл. или действительный
     * уровень error_reporting для PHP.
     */
    public function setDebug($v= true) {
        $this->_debug= $v;
    }

    /**
     * OK!!!
     * Устанавливает состояние уровня ведения журнала для экземпляра vPDO.
     *
     * @param integer $level Уровень ведения журнала, на который нужно переключиться.
     * @return integer Предыдущий уровень журнала.
     */
    public function setLogLevel($level= vPDO::LOG_LEVEL_FATAL) {
        $oldLevel = $this->logLevel;
        $this->logLevel= intval($level);
        return $oldLevel;
    }

    /**
     * OK!!!
     * @return integer Текущий уровень журнала.
     */
    public function getLogLevel() {
        return $this->logLevel;
    }

    /**
     * OK!!!
     * Устанавливает целевое значение журнала для вызовов vPDO::_log().
     *
     * Допустимые целевые значения включают:
     * <ul>
     * <li>'ECHO': Возвращает выходные данные в стандартный вывод.</li>
     * <li>'HTML': Возвращает выходные данные в стандартный вывод с форматированием HTML.</li>
     * <li>'FILE': Отправляет выходные данные в файл журнала.</li>
     * <li>Массив, содержащий по крайней мере один элемент с совпадающим ключом 'target'
     * один из допустимых целевых объектов журнала, перечисленных выше. Для 'target' => 'FILE'
     * вы можете указать второй элемент с ключом 'options' с помощью другого
     * ассоциативный массив с одним или обоими элементами 'filename' и
     * 'filepath'</li>
     * </ul>
     *
     * @param string $target Идентификатор, указывающий цель ведения журнала.
     * @return mixed Предыдущая цель журнала.
     */
    public function setLogTarget($target= 'ECHO') {
        $oldTarget = $this->logTarget;
        $this->logTarget= $target;
        return $oldTarget;
    }

    /**
     * OK!!!
     * @return integer Текущий уровень журнала.
     */
    public function getLogTarget() {
        return $this->logTarget;
    }

    /**
     * OK!!!
     * Зарегистрируйте сообщение с подробной информацией о том, где и когда происходит событие.
     *
     * @param integer $level Уровень зарегистрированного сообщения.
     * @param string $msg Сообщение для регистрации.
     * @param string $target Цель ведения журнала.
     * @param string $def Имя определяющей структуры (например, класса) для
     * помогите определить источник сообщения.
     * @param string $file Имя файла, в котором произошло событие журнала.
     * @param string $line Номер строки, помогающий определить источник события
     * внутри указанного файла.
     */
    public function log($level, $msg, $target= '', $def= '', $file= '', $line= '') {
        $this->_log($level, $msg, $target, $def, $file, $line);
    }

    /**
     * OK!!!
     * Зарегистрируйте сообщение в соответствии с уровнем и целью.
     *
     * @param integer $level Уровень зарегистрированного сообщения.
     * @param string $msg Сообщение для регистрации.
     * @param string $target Цель ведения журнала.
     * @param string $def Имя определяющей структуры (например, класса) для
     * помогите определить источник сообщения.
     * @param string $file Имя файла, в котором произошло событие журнала.
     * @param string $line Номер строки, помогающий определить источник события
     * внутри указанного файла.
     */
    protected function _log($level, $msg, $target= '', $def= '', $file= '', $line= '') {
        if ($level !== vPDO::LOG_LEVEL_FATAL && $level > $this->logLevel && $this->_debug !== true) {
            return;
        }
        if (empty ($target)) {
            $target = $this->logTarget;
        }
        $targetOptions = array();
        if (is_array($target)) {
            if (isset($target['options'])) $targetOptions =& $target['options'];
            $target = isset($target['target']) ? $target['target'] : 'ECHO';
        }
        if (empty($file)) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            } elseif (version_compare(phpversion(), '5.3.6', '>=')) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            } else {
                $backtrace = debug_backtrace();
            }
            if ($backtrace && isset($backtrace[2])) {
                $file = $backtrace[2]['file'];
                $line = $backtrace[2]['line'];
            }
        }
        if (empty($file) && isset($_SERVER['SCRIPT_NAME'])) {
            $file = $_SERVER['SCRIPT_NAME'];
        }
        if ($level === vPDO::LOG_LEVEL_FATAL) {
            while (ob_get_level() && @ob_end_flush()) {}
            exit ('[' . date('Y-m-d H:i:s') . '] (' . $this->_getLogLevel($level) . $def . $file . $line . ') ' . $msg . "\n" . ($this->getDebug() === true ? '<pre>' . "\n" . print_r(debug_backtrace(), true) . "\n" . '</pre>' : ''));
        }
        if ($this->_debug === true || $level <= $this->logLevel) {
            @ob_start();
            if (!empty ($def)) {
                $def= " in {$def}";
            }
            if (!empty ($file)) {
                $file= " @ {$file}";
            }
            if (!empty ($line)) {
                $line= " : {$line}";
            }
            switch ($target) {
                case 'HTML' :
                    echo '<h5>[' . date('Y-m-d H:i:s') . '] (' . $this->_getLogLevel($level) . $def . $file . $line . ')</h5><pre>' . $msg . '</pre>' . "\n";
                    break;
                default :
                    echo '[' . date('Y-m-d H:i:s') . '] (' . $this->_getLogLevel($level) . $def . $file . $line . ') ' . $msg . "\n";
            }
            $content= @ob_get_contents();
            @ob_end_clean();
            if ($target=='FILE' && $this->getCacheManager()) {
                $filename = isset($targetOptions['filename']) ? $targetOptions['filename'] : 'error.log';
                $filepath = isset($targetOptions['filepath']) ? $targetOptions['filepath'] : $this->getCachePath() . Cache\vPDOCacheManager::LOG_DIR;
                $this->cacheManager->writeFile($filepath . $filename, $content, 'a');
            } elseif ($target=='ARRAY' && isset($targetOptions['var']) && is_array($targetOptions['var'])) {
                $targetOptions['var'][] = $content;
            } elseif ($target=='ARRAY_EXTENDED' && isset($targetOptions['var']) && is_array($targetOptions['var'])) {
                $targetOptions['var'][] = array(
                    'content' => $content,
                    'level' => $this->_getLogLevel($level),
                    'msg' => $msg,
                    'def' => $def,
                    'file' => $file,
                    'line' => $line
                );
            } else {
                echo $content;
            }
        }
    }

    /**
     * OK!!!
     * Возвращает сокращенную обратную трассировку отладочной информации.
     *
     * Эта функция возвращает только поля, возвращаемые с помощью vPDOObject::toArray()
     * в экземплярах vPDOObject и просто имя класса для других объектов, чтобы
     * уменьшить количество возвращаемой ненужной информации.
     *
     * @return array Сокращенный обратный путь.
     */
    public function getDebugBacktrace() {
        $backtrace= array ();
        foreach (debug_backtrace() as $levelKey => $levelElement) {
            foreach ($levelElement as $traceKey => $traceElement) {
                // if ($traceKey == 'object' && $traceElement instanceof Om\vPDOObject) {
                //     $backtrace[$levelKey][$traceKey]= $traceElement->toArray('', true);
                // } else
                if ($traceKey == 'object') {
                    $backtrace[$levelKey][$traceKey]= get_class($traceElement);
                } else {
                    $backtrace[$levelKey][$traceKey]= $traceElement;
                }
            }
        }
        return $backtrace;
    }

    /**
     * OK!!!
     * Получает уровень ведения журнала в виде строкового представления.
     *
     * @param integer $level Уровень ведения журнала, для которого извлекается строка.
     * @return string Строковое представление допустимого уровня ведения журнала.
     */
    protected function _getLogLevel($level) {
        switch ($level) {
            case vPDO::LOG_LEVEL_DEBUG :
                $levelText= 'DEBUG';
                break;
            case vPDO::LOG_LEVEL_INFO :
                $levelText= 'INFO';
                break;
            case vPDO::LOG_LEVEL_WARN :
                $levelText= 'WARN';
                break;
            case vPDO::LOG_LEVEL_ERROR :
                $levelText= 'ERROR';
                break;
            default :
                $levelText= 'FATAL';
        }
        return $levelText;
    }

    /**
     * Escapes the provided string using the platform-specific escape character.
     *
     * Different database engines escape string literals in SQL using different characters. For example, this is used to
     * escape column names that might match a reserved string for that SQL interpreter. To write database agnostic
     * queries with vPDO, it is highly recommend to escape any database or column names in any native SQL strings used.
     *
     * @param string $string A string to escape using the platform-specific escape characters.
     * @return string The string escaped with the platform-specific escape characters.
     */
    public function escape($string) {
        $string = trim($string, $this->_escapeCharOpen . $this->_escapeCharClose);
        return $this->_escapeCharOpen . $string . $this->_escapeCharClose;
    }

    /**
     * Use to insert a literal string into a SQL query without escaping or quoting.
     *
     * @param string $string A string to return as a literal, unescaped and unquoted.
     * @return string The string with any escape or quote characters trimmed.
     */
    public function literal($string) {
        $string = trim($string, $this->_escapeCharOpen . $this->_escapeCharClose . $this->_quoteChar);
        return $string;
    }

    /**
     * Adds the table prefix, and optionally database name, to a given table.
     *
     * @param string $baseTableName The table name as specified in the object
     * model.
     * @param boolean $includeDb Qualify the table name with the database name.
     * @return string The fully-qualified and quoted table name for the
     */
    private function _getFullTableName($baseTableName, $includeDb= false) {
        $fqn= '';
        if (!empty ($baseTableName)) {
            if ($includeDb) {
                $fqn .= $this->escape($this->config['dbname']) . '.';
            }
            $fqn .= $this->escape($baseTableName);
        }
        return $fqn;
    }

    /**
     * OK!!!
     * Анализирует DSN и возвращает массив сведений о соединении.
     *
     * @static
     * @param string $string DSN для анализа.
     * @return array Массив сведений о подключении из DSN.
     * @todo Пусть этот метод обрабатывает все методы спецификации DSN как обработанные
     * с помощью последней собственной реализации PDO.
     */
    public static function parseDSN($string) {
        $result= array ();
        $pos= strpos($string, ':');
        $result['dbtype']= strtolower(substr($string, 0, $pos));
        $parameters= explode(';', substr($string, ($pos +1)));
        for ($a= 0, $b= count($parameters); $a < $b; $a++) {
            $tmp= explode('=', $parameters[$a]);
            if (count($tmp) == 2) {
                $result[strtolower(trim($tmp[0]))]= trim($tmp[1]);
            } else {
                $result['dbname']= trim($parameters[$a]);
            }
        }
        if (!isset($result['dbname']) && isset($result['database'])) {
            $result['dbname'] = $result['database'];
        }
        if (!isset($result['host']) && isset($result['server'])) {
            $result['host'] = $result['server'];
        }
        return $result;
    }

    /**
     * Retrieves a result array from the object cache.
     *
     * @param string|Om\vPDOCriteria $signature A unique string or vPDOCriteria object
     * that represents the query identifying the result set.
     * @param string $class An optional classname the result represents.
     * @param array $options Various cache options.
     * @return array|string|null A PHP array or JSON object representing the
     * result set, or null if no cache representation is found.
     */
    public function fromCache($signature, $class= '', $options= array()) {
        $result= null;
        if ($this->getOption(vPDO::OPT_CACHE_DB, $options)) {
            if ($signature && $this->getCacheManager()) {
                $sig= '';
                $sigKey= array();
                $sigHash= '';
                $sigClass= empty($class) || !is_string($class) ? '' : $class;
                if (is_object($signature)) {
                    if ($signature instanceof Om\vPDOCriteria) {
                        if ($signature instanceof Om\vPDOQuery) {
                            $signature->construct();
                            if (empty($sigClass)) $sigClass= $signature->getTableClass();
                        }
                        $sigKey= array ($signature->sql, $signature->bindings);
                    }
                }
                elseif (is_string($signature)) {
                    if ($exploded= explode('_', $signature)) {
                        $class= reset($exploded);
                        if (empty($sigClass) || $sigClass !== $class) {
                            $sigClass= $class;
                        }
                        if (empty($sigKey)) {
                            while ($key= next($exploded)) {
                                $sigKey[]= $key;
                            }
                        }
                    }
                }
                if (empty($sigClass)) $sigClass= '__sqlResult';
                if ($sigClass && $sigKey) {
                    $sigHash= md5($this->toJSON($sigKey));
                    $sig= implode('/', array ($sigClass, $sigHash));
                }
                if (is_string($sig) && !empty($sig)) {
                    $result= $this->cacheManager->get($sig, array(
                        vPDO::OPT_CACHE_KEY => $this->getOption('cache_db_key', $options, 'db'),
                        vPDO::OPT_CACHE_HANDLER => $this->getOption(vPDO::OPT_CACHE_DB_HANDLER, $options, $this->getOption(vPDO::OPT_CACHE_HANDLER, $options, 'vPDO\\Cache\\vPDOFileCache')),
                        vPDO::OPT_CACHE_FORMAT => (integer) $this->getOption('cache_db_format', null, $this->getOption(vPDO::OPT_CACHE_FORMAT, null, Cache\vPDOCacheManager::CACHE_PHP)),
                        'cache_prefix' => $this->getOption('cache_db_prefix', $options, Cache\vPDOCacheManager::CACHE_DIR),
                    ));
                    if ($this->getDebug() === true) {
                        if (!$result) {
                            $this->log(vPDO::LOG_LEVEL_DEBUG, 'No cache item found for class ' . $sigClass . ' with signature ' . Cache\vPDOCacheManager::CACHE_DIR . $sig);
                        } else {
                            $this->log(vPDO::LOG_LEVEL_DEBUG, 'Loaded cache item for class ' . $sigClass . ' with signature ' . Cache\vPDOCacheManager::CACHE_DIR . $sig);
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Places a result set in the object cache.
     *
     * @param string|Om\vPDOCriteria $signature A unique string or vPDOCriteria object
     * representing the object.
     * @param object $object An object to place a representation of in the cache.
     * @param integer $lifetime An optional number of seconds the cached result
     * will remain valid, with 0 meaning it will remain valid until replaced or
     * removed.
     * @param array $options Various cache options.
     * @return boolean Indicates if the object was successfully cached.
     */
    public function toCache($signature, $object, $lifetime= 0, $options = array()) {
        $result= false;
        if ($this->getCacheManager()) {
            if ($this->getOption(vPDO::OPT_CACHE_DB, $options)) {
                if ($lifetime === true) {
                    $lifetime = 0;
                }
                elseif (!$lifetime && $this->getOption(vPDO::OPT_CACHE_DB_EXPIRES, $options, 0)) {
                    $lifetime= intval($this->getOption(vPDO::OPT_CACHE_DB_EXPIRES, $options, 0));
                }
                $sigKey= array();
                $sigClass= '';
                $sigGraph= $this->getOption(vPDO::OPT_CACHE_DB_SIG_GRAPH, $options, array());
                if (is_object($signature)) {
                    if ($signature instanceof Om\vPDOCriteria) {
                        if ($signature instanceof Om\vPDOQuery) {
                            $signature->construct();
                            if (empty($sigClass)) $sigClass = $signature->getTableClass();
                        }
                        $sigKey= array($signature->sql, $signature->bindings);
                    }
                }
                elseif (is_string($signature)) {
                    $exploded= explode('_', $signature);
                    if ($exploded && count($exploded) >= 2) {
                        $class= reset($exploded);
                        if (empty($sigClass) || $sigClass !== $class) {
                            $sigClass= $class;
                        }
                        if (empty($sigKey)) {
                            while ($key= next($exploded)) {
                                $sigKey[]= $key;
                            }
                        }
                    }
                }
                if (empty($sigClass)) {
                    // if ($object instanceof Om\vPDOObject) {
                    //     $sigClass= $object->_class;
                    // } else {
                        $sigClass= $this->getOption(vPDO::OPT_CACHE_DB_SIG_CLASS, $options, '__sqlResult');
                    // }
                }
                if (empty($sigKey) && is_string($signature)) $sigKey= $signature;
                // if (empty($sigKey) && $object instanceof Om\vPDOObject) $sigKey= $object->getPrimaryKey();
                if ($sigClass && $sigKey) {
                    $sigHash= md5($this->toJSON(is_array($sigKey) ? $sigKey : array($sigKey)));
                    $sig= implode('/', array ($sigClass, $sigHash));
                    if (is_string($sig)) {
                        if ($this->getOption('modified', $options, false)) {
                            // if (empty($sigGraph) && $object instanceof Om\vPDOObject) {
                            //     $sigGraph = array_merge(array($object->_class => array('class' => $object->_class)), $object->_aggregates, $object->_composites);
                            // }
                            if (!empty($sigGraph)) {
                                foreach ($sigGraph as $gAlias => $gMeta) {
                                    $gClass = $gMeta['class'];
                                    $removed= $this->cacheManager->delete($gClass, array_merge($options, array(
                                        vPDO::OPT_CACHE_KEY => $this->getOption('cache_db_key', $options, 'db'),
                                        vPDO::OPT_CACHE_HANDLER => $this->getOption(vPDO::OPT_CACHE_DB_HANDLER, $options, $this->getOption(vPDO::OPT_CACHE_HANDLER, $options, 'vPDO\\Cache\\vPDOFileCache')),
                                        vPDO::OPT_CACHE_FORMAT => (integer) $this->getOption('cache_db_format', $options, $this->getOption(vPDO::OPT_CACHE_FORMAT, $options, Cache\vPDOCacheManager::CACHE_PHP)),
                                        vPDO::OPT_CACHE_EXPIRES => (integer) $this->getOption(vPDO::OPT_CACHE_DB_EXPIRES, null, $this->getOption(vPDO::OPT_CACHE_EXPIRES, null, 0)),
                                        vPDO::OPT_CACHE_PREFIX => $this->getOption('cache_db_prefix', $options, Cache\vPDOCacheManager::CACHE_DIR),
                                        vPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE => true
                                    )));
                                    if ($this->getDebug() === true) {
                                        $this->log(vPDO::LOG_LEVEL_DEBUG, "Removing all cache objects of class {$gClass}: " . ($removed ? 'successful' : 'failed'));
                                    }
                                }
                            }
                        }
                        $cacheOptions = array_merge($options, array(
                            vPDO::OPT_CACHE_KEY => $this->getOption('cache_db_key', $options, 'db'),
                            vPDO::OPT_CACHE_HANDLER => $this->getOption(vPDO::OPT_CACHE_DB_HANDLER, $options, $this->getOption(vPDO::OPT_CACHE_HANDLER, $options, 'vPDO\\Cache\\vPDOFileCache')),
                            vPDO::OPT_CACHE_FORMAT => (integer) $this->getOption('cache_db_format', $options, $this->getOption(vPDO::OPT_CACHE_FORMAT, $options, Cache\vPDOCacheManager::CACHE_PHP)),
                            vPDO::OPT_CACHE_EXPIRES => (integer) $this->getOption(vPDO::OPT_CACHE_DB_EXPIRES, null, $this->getOption(vPDO::OPT_CACHE_EXPIRES, null, 0)),
                            vPDO::OPT_CACHE_PREFIX => $this->getOption('cache_db_prefix', $options, Cache\vPDOCacheManager::CACHE_DIR)
                        ));
                        $result= $this->cacheManager->set($sig, $object, $lifetime, $cacheOptions);
                        // if ($result && $object instanceof Om\vPDOObject) {
                            // if ($this->getDebug() === true) {
                            //     $this->log(vPDO::LOG_LEVEL_DEBUG, "vPDO->toCache() successfully cached object with signature " . Cache\vPDOCacheManager::CACHE_DIR . $sig);
                            // }
                        // }
                        if (!$result) {
                            $this->log(vPDO::LOG_LEVEL_WARN, "vPDO->toCache() could not cache object with signature " . Cache\vPDOCacheManager::CACHE_DIR . $sig);
                        }
                    }
                } else {
                    $this->log(vPDO::LOG_LEVEL_ERROR, "Object sent toCache() has an invalid signature.");
                }
            }
        } else {
            $this->log(vPDO::LOG_LEVEL_ERROR, "Attempt to send a non-object to toCache().");
        }
        return $result;
    }

    /**
     * OK!!!
     * Преобразует массив PHP в строку, закодированную в формате JSON.
     *
     * @param array $array Массив PHP для преобразования.
     *
     * @throws vPDOException Если json_encode недоступен.
     * @return string Представление исходного массива в формате JSON.
     */
    public function toJSON($array) {
        $encoded= '';
        if (is_array ($array)) {
            if (!function_exists('json_encode')) {
                throw new vPDOException();
            } else {
                $encoded= json_encode($array);
            }
        }
        return $encoded;
    }

    /**
     * OK!!!
     * Преобразует исходную строку JSON в эквивалентное представление PHP.
     *
     * @param string $src Исходная строка в формате JSON.
     * @param boolean $asArray Указывает, должен ли результат обрабатывать объекты как
     * ассоциативные массивы; поскольку все ассоциативные массивы JSON являются объектами, значение по умолчанию
     * верно. Установите значение false, чтобы объекты JSON возвращались как объекты PHP.
     *
     * @throws vPDOException Если json_decode недоступен.
     * @return mixed PHP-представление исходного файла JSON.
     */
    public function fromJSON($src, $asArray= true) {
        $decoded= '';
        if ($src) {
            if (!function_exists('json_decode')) {
                throw new vPDOException();
            } else {
                $decoded= json_decode($src, $asArray);
            }
        }
        return $decoded;
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-begintransaction.php
     */
    public function beginTransaction() {
        if (!$this->connect(null, array(vPDO::OPT_CONN_MUTABLE => true))) {
            return false;
        }
        return $this->pdo->beginTransaction();
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-commit.php
     */
    public function commit() {
        if (!$this->connect(null, array(vPDO::OPT_CONN_MUTABLE => true))) {
            return false;
        }
        return $this->pdo->commit();
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-exec.php
     */
    public function exec($query) {
        if (!$this->connect(null, array(vPDO::OPT_CONN_MUTABLE => true))) {
            return false;
        }
        $tstart= microtime(true);
        $return= $this->pdo->exec($query);
        $this->queryTime += microtime(true) - $tstart;
        $this->executedQueries++;
        return $return;
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-errorcode.php
     */
    public function errorCode() {
        if (!$this->connect()) {
            return false;
        }
        return $this->pdo->errorCode();
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-errorinfo.php
     */
    public function errorInfo() {
        if (!$this->connect()) {
            return false;
        }
        return $this->pdo->errorInfo();
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-getattribute.php
     */
    public function getAttribute($attribute) {
        if (!$this->connect()) {
            return false;
        }
        return $this->pdo->getAttribute($attribute);
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-lastinsertid.php
     */
    public function lastInsertId() {
        if (!$this->connect()) {
            return false;
        }
        return $this->pdo->lastInsertId();
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-prepare.php
     */
    public function prepare($statement, $driver_options= array ()) {
        if (!$this->connect()) {
            return false;
        }
        return $this->pdo->prepare($statement, $driver_options);
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-query.php
     */
    public function query($query) {
        if (!$this->connect()) {
            return false;
        }
        $tstart= microtime(true);
        $return= $this->pdo->query($query);
        $this->queryTime += microtime(true) - $tstart;
        $this->executedQueries++;
        return $return;
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-quote.php
     */
    public function quote($string, $parameter_type= \PDO::PARAM_STR) {
        if (!$this->connect()) {
            return false;
        }
        $quoted = $this->pdo->quote($string, $parameter_type);
        switch ($parameter_type) {
            case \PDO::PARAM_STR:
                $quoted = trim($quoted);
                break;
            case \PDO::PARAM_INT:
                $quoted = trim($quoted);
                $quoted = (integer) trim($quoted, "'");
                break;
            default:
                break;
        }
        return $quoted;
    }

    /**
     * OK!!!
     * @see http://php.net/manual/en/function.pdo-rollback.php
     */
    public function rollBack() {
        if (!$this->connect(null, array(vPDO::OPT_CONN_MUTABLE => true))) {
            return false;
        }
        return $this->pdo->rollBack();
    }

    /**
     * @see http://php.net/manual/en/function.pdo-setattribute.php
     */
    public function setAttribute($attribute, $value) {
        if (!$this->connect()) {
            return false;
        }
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * Создает новый vPDOQuery для указанного класса vPDOObject.
     *
     * @param string $class Класс, для которого нужно создать vPDOQuery.
     * @param mixed $criteria Любое допустимое выражение критерия vPDO.
     * @param boolean|integer $cacheFlag Указывает, следует ли кэшировать результат
     * и необязательно на сколько секунд (если передано целое число, большее 0).
     * @return Om\vPDOQuery Результирующий экземпляр vPDOQuery или false в случае неудачи.
     */
    public function newQuery($class, $criteria= null, $cacheFlag= true) {
        $vpdoQueryClass= '\\vPDO\\Om\\' . $this->config['dbtype'] . '\\vPDOQuery';
        if ($query= new $vpdoQueryClass($this, $class, $criteria)) {
            $query->cacheFlag= $cacheFlag;
        }
        return $query;
    }

    /**
     * Разбивает строку на указанный символ, игнорируя экранированное содержимое.
     *
     * @static
     * @param string $char Символ для разделения содержимого тега.
     * @param string $str Строка, с которой нужно работать.
     * @param string $escToken Символ, используемый для окружения экранированного содержимого; все
     * содержимое в пределах пары этих токенов будет проигнорировано операцией разделения
     * @param integer $limit Ограничьте количество результатов. Значение по умолчанию равно 0, что означает 
     * без ограничений. Обратите внимание, что установка ограничения равным 1 вернет только содержимое
     * вплоть до первого экземпляра разделяемого символа и отбросит
     * оставшуюся часть строки.
     * @return array Массив результатов операции разделения или пустой массив.
     */
    public static function escSplit($char, $str, $escToken = '`', $limit = 0) {
        $split= array();
        $charPos = strpos($str, $char);
        if ($charPos !== false) {
            if ($charPos === 0) {
                $searchPos = 1;
                $startPos = 1;
            } else {
                $searchPos = 0;
                $startPos = 0;
            }
            $escOpen = false;
            $strlen = strlen($str);
            for ($i = $startPos; $i <= $strlen; $i++) {
                if ($i == $strlen) {
                    $tmp= trim(substr($str, $searchPos));
                    if (!empty($tmp)) $split[]= $tmp;
                    break;
                }
                if ($str[$i] == $escToken) {
                    $escOpen = $escOpen == true ? false : true;
                    continue;
                }
                if (!$escOpen && $str[$i] == $char) {
                    $tmp= trim(substr($str, $searchPos, $i - $searchPos));
                    if (!empty($tmp)) {
                        $split[]= $tmp;
                        if ($limit > 0 && count($split) >= $limit) {
                            break;
                        }
                    }
                    $searchPos = $i + 1;
                }
            }
        } else {
            $split[]= trim($str);
        }
        return $split;
    }

    /**
     * Анализирует привязки параметров в подготовленных инструкциях SQL.
     *
     * @param string $sql Подготовленный SQL-оператор для разбора привязок в нем.
     * @param array $bindings Массив привязок параметров, которые будут использоваться для замен.
     * @return string Заменен SQL с привязывающими заполнителями.
     */
    public function parseBindings($sql, $bindings) {
        if (!empty($sql) && !empty($bindings)) {
            $bound = array();
            foreach ($bindings as $k => $param) {
                if (!is_array($param)) {
                    $v= $param;
                    $type= $this->getPDOType($param);
                    $bindings[$k]= array(
                        'value' => $v,
                        'type' => $type
                    );
                } else {
                    $v= $param['value'];
                    $type= $param['type'];
                }
                if (!$v) {
                    switch ($type) {
                        case \PDO::PARAM_INT:
                            $v= '0';
                            break;
                        case \PDO::PARAM_BOOL:
                            $v= '0';
                            break;
                        default:
                            break;
                    }
                }
                if ($type > 0) {
                    $v= $this->quote($v, $type);
                } else {
                    $v= 'NULL';
                }
                if (!is_int($k) || substr($k, 0, 1) === ':') {
                    $pattern= '/' . $k . '\b/';
                    $bound[$pattern] = str_replace(array('\\', '$'), array('\\\\', '\$'), $v);
                } else {
                    $pattern = '/(\?)(\b)?/';
                    $sql = preg_replace($pattern, ':' . $k . '$2', $sql, 1);
                    $bound['/:' . $k . '\b/'] = str_replace(array('\\', '$'), array('\\\\', '\$'), $v);
                }
            }
            if ($this->getDebug() === true) {
                $this->log(vPDO::LOG_LEVEL_DEBUG, "{$sql}\n" . print_r($bound, true));
            }
            if (!empty($bound)) {
                $sql= preg_replace(array_keys($bound), array_values($bound), $sql);
            }
        }
        return $sql;
    }

    /**
     * Получите соответствующую константу типа PDO::PARAM_ из значения PHP.
     *
     * @param mixed $value Любое скалярное или нулевое значение PHP
     * @return int|null
     */
    public function getPDOType($value) {
        $type= null;
        if (is_null($value)) $type= \PDO::PARAM_NULL;
        elseif (is_scalar($value)) {
            if (is_int($value)) $type= \PDO::PARAM_INT;
            else $type= \PDO::PARAM_STR;
        }
        return $type;
    }

    /**
     * Очистите критерии, которые, как ожидается, будут представлять значения первичного ключа.
     *
     * @param string $className Название класса.
     * @param mixed  &$criteria Ссылка на используемые критерии.
     */
    protected function sanitizePKCriteria($className, &$criteria) {
        if (is_scalar($criteria)) {
            $pkType = $this->getPKType($className);
            if (is_string($pkType)) {
                $pk = $this->getPK($className);
                switch ($pkType) {
                    case 'int':
                    case 'integer':
                        if (!is_int($criteria) && (string)(int)$criteria !== (string)$criteria) {
                            $criteria = [$pk => null];
                            break;
                        }
                        $criteria = [$pk => (int)$criteria];
                        break;
                    case 'string':
                        $criteria = [$pk => (string)$criteria];
                        break;
                }
            } elseif (is_array($pkType)) {
                $criteria = null;
            }
        }
    }
}