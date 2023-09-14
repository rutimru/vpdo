<?php
/**
 * This file is part of the vPDO package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use vPDO\vPDO;

$properties['xpdo_test_path'] = dirname(__FILE__) . '/';

/* mysql */
$properties['mysql_string_dsn_test']= 'mysql:host=127.0.0.1;dbname=xpdotest;charset=utf8';
$properties['mysql_string_dsn_nodb']= 'mysql:host=127.0.0.1;charset=utf8';
$properties['mysql_string_dsn_error']= 'mysql:host= nonesuchhost;dbname=nonesuchdb';
$properties['mysql_string_username']= 'root';
$properties['mysql_string_password']= '';
$properties['mysql_array_driverOptions']= [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT];
$properties['mysql_array_options']= array(
    vPDO::OPT_CACHE_PATH => $properties['xpdo_test_path'] .'cache/',
    vPDO::OPT_HYDRATE_FIELDS => true,
    vPDO::OPT_HYDRATE_RELATED_OBJECTS => true,
    vPDO::OPT_HYDRATE_ADHOC_FIELDS => true,
    vPDO::OPT_CONN_INIT => array(vPDO::OPT_CONN_MUTABLE => true),
    vPDO::OPT_CONNECTIONS => array(
        array(
            'dsn' => $properties['mysql_string_dsn_test'],
            'username' => $properties['mysql_string_username'],
            'password' => $properties['mysql_string_password'],
            'options' => array(
                vPDO::OPT_CONN_MUTABLE => true,
            ),
            'driverOptions' => $properties['mysql_array_driverOptions'],
        ),
    ),
);

/* PHPUnit test config */
$properties['xpdo_driver']= getenv('TEST_DRIVER');
$properties['logLevel']= vPDO::LOG_LEVEL_INFO;
$properties['logTarget']= php_sapi_name() === 'cli' ? 'ECHO' : 'HTML';
//$properties['debug']= -1;

return $properties;
