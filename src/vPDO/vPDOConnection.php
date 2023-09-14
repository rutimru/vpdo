<?php
/**
 * Этот файл является частью пакета vPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace vPDO;

use PDO;
use PDOException;
use Exception;

/**
 * Представляет собой уникальное PDO-соединение, управляемое vPDO.
 *
 * @package vPDO
 */
class vPDOConnection {
    /**
     * @var vPDO Ссылка на действительный экземпляр vPDO.
     */
    public $vpdo = null;
    /**
     * @var array Множество параметров конфигурации для этого подключения.
     */
    public $config = array();

    /**
     * @var \PDO Объект PDO, представленный экземпляром vPDOConnection.
     */
    public $pdo = null;
    /**
     * @var boolean Указывает, можно ли выполнить запись в это соединение.
     */
    private $_mutable = true;

    /**
     * Создайте новый экземпляр vPDOConnection.
     *
     * @param vPDO $vpdo Ссылка на действительный экземпляр vPDO для присоединения.
     * @param string $dsn Строка, представляющая строку подключения DSN.
     * @param string $username Учетные данные пользователя базы данных.
     * @param string $password Учетные данные для пароля базы данных.
     * @param array $options Множество опций vPDO для подключения.
     * @param array $driverOptions Множество опций драйвера PDO для подключения.
     */
    public function __construct(vPDO &$vpdo, $dsn, $username= '', $password= '', $options= array(), $driverOptions= array()) {
        $this->vpdo =& $vpdo;
        if (is_array($this->vpdo->config)) $options= array_merge($this->vpdo->config, $options);
        if (!isset($options[vPDO::OPT_TABLE_PREFIX])) $options[vPDO::OPT_TABLE_PREFIX]= '';
        $this->config= array_merge($options, vPDO::parseDSN($dsn));
        $this->config['dsn']= $dsn;
        $this->config['username']= $username;
        $this->config['password']= $password;
        $driverOptions = is_array($driverOptions) ? $driverOptions : array();
        if (array_key_exists('driverOptions', $this->config) && is_array($this->config['driverOptions'])) {
            $driverOptions = $driverOptions + $this->config['driverOptions'];
        }
        $this->config['driverOptions']= $driverOptions;
        if (array_key_exists(vPDO::OPT_CONN_MUTABLE, $this->config)) {
            $this->_mutable= (boolean) $this->config[vPDO::OPT_CONN_MUTABLE];
        }
    }

    /**
     * Указывает, можно ли выполнить запись в соединение, например : INSERT/UPDATE/DELETE.
     *
     * @return bool True, если в соединение можно записать данные.
     */
    public function isMutable() {
        return $this->_mutable;
    }

    /**
     * Фактически установите соединение для этого экземпляра через PDO.
     *
     * @param array $driverOptions Множество опций драйвера PDO для подключения.
     * @return bool True если соединение установлено успешно.
     */
    public function connect($driverOptions = array()) {
        if ($this->pdo === null) {
            if (is_array($driverOptions) && !empty($driverOptions)) {
                $this->config['driverOptions']= $driverOptions + $this->config['driverOptions'];
            }
            try {
                $this->pdo= new PDO($this->config['dsn'], $this->config['username'], $this->config['password'], $this->config['driverOptions']);
            } catch (PDOException $xe) {
                $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, $xe->getMessage(), '', __METHOD__, __FILE__, __LINE__);
                return false;
            } catch (Exception $e) {
                $this->vpdo->log(vPDO::LOG_LEVEL_ERROR, $e->getMessage(), '', __METHOD__, __FILE__, __LINE__);
                return false;
            }

            $connected= (is_object($this->pdo));
            if ($connected) {
                $connectFile = VPDO_CORE_PATH . 'om/' . $this->config['dbtype'] . '/connect.inc.php';
                if (!empty($this->config['connect_file']) && file_exists($this->config['connect_file'])) {
                    $connectFile = $this->config['connect_file'];
                }
                if (file_exists($connectFile)) include ($connectFile);
            }
            if (!$connected) {
                $this->pdo= null;
            }
        }
        $connected= is_object($this->pdo);
        return $connected;
    }

    /**
     * Получите набор параметров для этого экземпляра vPDOConnection.
     *
     * @param string $key Ключ параметра, для которого нужно получить значение.
     * @param array|null $options Необязательный набор опций для рассмотрения.
     * @param mixed $default Значение по умолчанию, которое будет использоваться, если опция не найдена.
     * @return mixed Значение параметра.
     */
    public function getOption($key, $options = null, $default = null) {
        if (is_array($options)) {
            $options = array_merge($this->config, $options);
        } else {
            $options = $this->config;
        }
        return $this->vpdo->getOption($key, $options, $default);
    }
}
