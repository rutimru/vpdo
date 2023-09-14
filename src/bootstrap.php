<?php
/**
 * Этот файл является частью пакета vPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

$includeIfReadable = function($file)
{
    return is_readable($file) ? include $file : false;
};

if ((!$loader = $includeIfReadable(__DIR__.'/../vendor/autoload.php')) && (!$loader = $includeIfReadable(__DIR__.'/../../../autoload.php'))) {
    echo 'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -sS https://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL;
    exit(1);
}

return $loader;