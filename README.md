# vPDO O/RB v1

[![Build Status](https://github.com/modxcms/xpdo/workflows/CI/badge.svg?branch=3.x)](https://github.com/modxcms/xpdo/workflows/CI/badge.svg?branch=3.x)

vPDO - это сверхлегкая объектно-реляционная мостовая библиотека для PHP. Это автономная библиотека, и ее можно использовать с любым фреймворком или контейнером DI.

## Установка

vPDO можно установить в ваш проект с помощью composer:

    composer require rutim/vpdo


## Использование

Класс `\vPDO\vPDO` является основной точкой доступа к фреймворку. Предоставьте массив конфигурации, описывающий соединения, которые вы хотите установить при создании экземпляра класса.

```php
require __DIR__ . '/../vendor/autoload.php';

$xpdoMySQL = \vPDO\vPDO::getInstance('aMySQLDatabase', [
    \vPDO\vPDO::OPT_CACHE_PATH => __DIR__ . '/../cache/',
    \vPDO\vPDO::OPT_HYDRATE_FIELDS => true,
    \vPDO\vPDO::OPT_HYDRATE_RELATED_OBJECTS => true,
    \vPDO\vPDO::OPT_HYDRATE_ADHOC_FIELDS => true,
    \vPDO\vPDO::OPT_CONNECTIONS => [
        [
            'dsn' => 'mysql:host=localhost;dbname=xpdotest;charset=utf8',
            'username' => 'test',
            'password' => 'test',
            'options' => [
                \vPDO\vPDO::OPT_CONN_MUTABLE => true,
            ],
            'driverOptions' => [],
        ],
    ],
]);

$xpdoSQLite = \vPDO\vPDO::getInstance('aSQLiteDatabase', [
    \vPDO\vPDO::OPT_CACHE_PATH => __DIR__ . '/../cache/',
    \vPDO\vPDO::OPT_HYDRATE_FIELDS => true,
    \vPDO\vPDO::OPT_HYDRATE_RELATED_OBJECTS => true,
    \vPDO\vPDO::OPT_HYDRATE_ADHOC_FIELDS => true,
    \vPDO\vPDO::OPT_CONNECTIONS => [
        [
            'dsn' => 'sqlite:path/to/a/database',
            'username' => '',
            'password' => '',
            'options' => [
                \vPDO\vPDO::OPT_CONN_MUTABLE => true,
            ],
            'driverOptions' => [],
        ],
    ],
]);
```
