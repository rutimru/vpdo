<?php
/**
 * Этот файл является частью пакета vPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace vPDO\Om\mysql;

use vPDO\vPDO;

/**
 * Предоставляет абстракцию драйвера mysql для экземпляра vPDO.
 *
 * Это базовые метаданные и методы, используемые во всей платформе. vPDODriver - драйвер vPDODriver
 * реализации класса специфичны для драйвера PDO, и этот экземпляр
 * реализован для mysql.
 *
 * @package vPDO\Om\mysql
 */
class vPDODriver extends \vPDO\Om\vPDODriver {
    public $quoteChar = "'";
    public $escapeOpenChar = '`';
    public $escapeCloseChar = '`';
    public $_currentTimestamps= array (
        'CURRENT_TIMESTAMP',
        'CURRENT_TIMESTAMP()',
        'NOW()',
        'LOCALTIME',
        'LOCALTIME()',
        'LOCALTIMESTAMP',
        'LOCALTIMESTAMP()',
        'SYSDATE()'
    );
    public $_currentDates= array (
        'CURDATE()',
        'CURRENT_DATE',
        'CURRENT_DATE()'
    );
    public $_currentTimes= array (
        'CURTIME()',
        'CURRENT_TIME',
        'CURRENT_TIME()'
    );

    /**
     * Получите экземпляр mysql vPDODriver.
     *
     * @param vPDO &$vpdo Ссылка на конкретный экземпляр vPDO.
     */
    function __construct(vPDO &$vpdo) {
        parent :: __construct($vpdo);
        $this->dbtypes['integer']= array('/INT/i');
        $this->dbtypes['boolean']= array('/^BOOL/i');
        $this->dbtypes['float']= array('/^DEC/i','/^NUMERIC$/i','/^FLOAT$/i','/^DOUBLE/i','/^REAL/i');
        $this->dbtypes['string']= array('/CHAR/i','/TEXT/i','/^ENUM$/i','/^SET$/i','/^TIME$/i','/^YEAR$/i');
        $this->dbtypes['timestamp']= array('/^TIMESTAMP$/i');
        $this->dbtypes['datetime']= array('/^DATETIME$/i');
        $this->dbtypes['date']= array('/^DATE$/i');
        $this->dbtypes['binary']= array('/BINARY/i','/BLOB/i');
        $this->dbtypes['bit']= array('/^BIT$/i');
    }
}
