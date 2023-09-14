<?php
/**
 * This file is part of the vPDO package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vPDO;

use Yoast\PHPUnitPolyfills\TestCases\XTestCase;

abstract class TestCase extends XTestCase
{
    /**
     * @var vPDO A static vPDO fixture.
     */
    public static $fixture = null;
    /**
     * @var array A static configuration array.
     */
    public static $properties = array();

    /**
     * @var vPDO An vPDO instance for this TestCase.
     */
    public $vpdo = null;

    /**
     * Setup static properties when loading the test cases.
     *
     * @beforeClass
     */
    public static function setUpFixturesBeforeClass()
    {
        self::$properties = include(__DIR__ . '/../properties.inc.php');
    }

    /**
     * Grab a persistent instance of the vPDO class to share sample model data
     * across multiple tests and test suites.
     *
     * @param bool $new Indicate if a new singleton should be created
     *
     * @return vPDO An vPDO object instance.
     */
    public static function &getInstance($new = false)
    {
        if ($new || !is_object(self::$fixture)) {
            $driver = self::$properties['vpdo_driver'];
            $vpdo = vPDO::getInstance(null, self::$properties["{$driver}_array_options"]);
            if (is_object($vpdo)) {
                $logLevel = array_key_exists('logLevel', self::$properties)
                    ? self::$properties['logLevel']
                    : vPDO::LOG_LEVEL_WARN;
                $logTarget = array_key_exists('logTarget', self::$properties)
                    ? self::$properties['logTarget']
                    : (php_sapi_name() === 'cli' ? 'ECHO' : 'HTML');
                $vpdo->setLogLevel($logLevel);
                $vpdo->setLogTarget($logTarget);
                self::$fixture = $vpdo;
            }
        }
        return self::$fixture;
    }

    /**
     * Set up the vPDO fixture for each test case.
     *
     * @before
     */
    public function setUpFixtures()
    {
        $this->vpdo = self::getInstance(true);
        $this->vpdo->setPackage('vPDO\\Test\\Sample', self::$properties['vpdo_test_path'] . 'model/');
    }

    /**
     * Tear down the vPDO fixture after each test case.
     *
     * @after
     */
    public function tearDownFixtures()
    {
        if (is_object($this->vpdo->pdo)) {
            $this->vpdo->pdo = null;
        }
        
        $this->vpdo = null;
    }
}
