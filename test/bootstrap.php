<?php
/**
 * This file is part of the xPDO package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

error_reporting(-1);

$loader = require __DIR__ . '/../src/bootstrap.php';
$loader->add('vPDO\\Legacy', __DIR__);
$loader->add('vPDO\\Test', __DIR__);

require __DIR__ . '/vPDO/TestCase.php';
