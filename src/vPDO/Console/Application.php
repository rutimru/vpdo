<?php
namespace vPDO\Console;

use vPDO\Console\Command\ParseSchema;
use vPDO\Console\Command\WriteSchema;

class Application extends \Symfony\Component\Console\Application
{
    protected static $name = 'vPDO Console';
    protected static $version = '1.0.0';

    public function __construct(){
        parent::__construct(self::$name, self::$version);
    }

    public function loadCommands()
    {
        $this->add(new ParseSchema());
        $this->add(new WriteSchema());
    }
}