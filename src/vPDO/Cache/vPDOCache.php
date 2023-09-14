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
 * Абстрактный класс, который определяет методы, которые должен реализовать поставщик кэша.
 *
 * @package vPDO\Cache
 */
abstract class vPDOCache {
    /** @var vPDO */
    public $vpdo= null;
    protected $options= array();
    protected $key= '';
    protected $initialized= false;

    public function __construct(& $vpdo, $options = array()) {
        $this->vpdo= & $vpdo;
        $this->options= $options;
        $this->key = $this->getOption(vPDO::OPT_CACHE_KEY, $options, 'default');
    }

    /**
     * Указывает, был ли этот экземпляр кэша vPDO правильно инициализирован.
     *
     * @return boolean true, если реализация была успешно инициализирована.
     */
    public function isInitialized() {
        return (boolean) $this->initialized;
    }

    /**
     * Получите параметр из предоставленных опций, параметров кэширования или конфигурации vpdo.
     *
     * @param string $key Уникальный идентификатор для опции.
     * @param array $options Набор явных параметров для переопределения параметров из vPDO или vPDOCache
     * внедрение.
     * @param mixed $default Необязательное значение по умолчанию, возвращаемое, если значение не найдено.
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
                $option = $this->vpdo->cacheManager->getOption($key, null, $default);
            }
        }
        return $option;
    }

    /**
     * Получите фактический ключ кэша, который будет использовать реализация.
     *
     * @param string $key Идентификатор, используемый приложением.
     * @param array $options Дополнительные опции для работы.
     * @return string Идентификатор с любыми префиксами, специфичными для реализации, или другими
     * примененные преобразования.
     */
    public function getCacheKey($key, $options = array()) {
        $prefix = $this->getOption('cache_prefix', $options);
        if (!empty($prefix)) $key = $prefix . $key;
        $key = str_replace('\\', '/', $key);
        return $this->key . '/' . $key;
    }

    /**
     * Добавляет значение в кэш.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий устанавливаемый элемент.
     * @param mixed $var Ссылка на переменную PHP, представляющую элемент.
     * @param integer $expire Количество секунд, в течение которых истекает срок действия переменной.
     * @param array $options Дополнительные опции для работы.
     * @return boolean true в случае успеха
     */
    abstract public function add($key, $var, $expire= 0, $options= array());

    /**
     * Sets a value in the cache.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий устанавливаемый элемент.
     * @param mixed $var Ссылка на переменную PHP, представляющую элемент.
     * @param integer $expire Количество секунд, в течение которых истекает срок действия переменной.
     * @param array $options Дополнительные опции для работы.
     * @return boolean true в случае успеха
     */
    abstract public function set($key, $var, $expire= 0, $options= array());

    /**
     * Replaces a value in the cache.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий устанавливаемый элемент.
     * @param mixed $var Ссылка на переменную PHP, представляющую элемент.
     * @param integer $expire Количество секунд, в течение которых истекает срок действия переменной.
     * @param array $options Дополнительные опции для работы.
     * @return boolean true в случае успеха
     */
    abstract public function replace($key, $var, $expire= 0, $options= array());

    /**
     * Deletes a value from the cache.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий удаляемый элемент.
     * @param array $options Дополнительные опции для работы.
     * @return boolean true в случае успеха
     */
    abstract public function delete($key, $options= array());

    /**
     * Gets a value from the cache.
     *
     * @access public
     * @param string $key Уникальный ключ, идентифицирующий элемент для извлечения.
     * @param array $options Дополнительные опции для работы.
     * @return mixed Значение, извлеченное из кэша.
     */
    public function get($key, $options= array()) {}

    /**
     * Flush all values from the cache.
     *
     * @access public
     * @param array $options Дополнительные опции для работы.
     * @return boolean true в случае успеха
     */
    abstract public function flush($options= array());
}
