<?php
namespace vPDO\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use vPDO\vPDO;
use vPDO\vPDOException;

final class WriteSchema extends Command
{
    protected function configure()
    {
        $this
            ->setName('write-schema')
            ->setDescription("Generate an XML schema from existing database tables.")
            ->addArgument(
                'platform',
                InputArgument::REQUIRED,
                'The PDO platform being targeted, e.g. mysql, sqlite, etc.'
            )
            ->addArgument(
                'schema_file',
                InputArgument::REQUIRED,
                'The path and filename to generate the XML schema to'
            )
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'The package name (aka PHP namespace) for the schema'
            )
            ->addArgument(
                'base_class',
                InputArgument::OPTIONAL,
                'An optional base_class to use for the generated schema objects; default is vPDO\Om\vPDOObject',
                ''
            )
            ->addArgument(
                'table_prefix',
                InputArgument::OPTIONAL,
                'An optional table_prefix to override one specified for the vPDO configuration',
                ''
            )
            ->addOption(
                'restrict_prefix',
                'r',
                InputOption::VALUE_NONE,
                'If set to 1, only tables that match the table_prefix will be included in the schema'
            )
            ->addOption(
                'config',
                'C',
                InputOption::VALUE_REQUIRED,
                'A path to a config file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $platform = strtolower($input->getArgument('platform'));
        if (!in_array($platform, self::$platforms)) {
            $output->writeln("fatal: no valid platform specified");
            return Command::FAILURE;
        }

        $properties = $this->loadConfig($output, $input->getOption('config'));
        if ($properties === false) {
            $output->writeln('fatal: no valid configuration file could be loaded');
            return Command::FAILURE;
        }

        $schema = $input->getArgument('schema_file');
        if (!is_writable(dirname($schema))) {
            $output->writeln("fatal: schema location should be in an existing writable directory");
            return Command::FAILURE;
        }

        $package = $input->getArgument('package');
        if (empty($package)) {
            $output->writeln('fatal: no package specified');
            return Command::FAILURE;
        }

        $baseClass = $input->getArgument('base_class');
        $tablePrefix = $input->getArgument('table_prefix');
        $restrictPrefix = (bool)$input->getOption('restrict_prefix');

        try {
            $xpdo = vPDO::getInstance('generator', $properties["{$platform}_array_options"]);
        } catch (vPDOException $e) {
            $output->writeln('fatal: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $generator = $xpdo->getManager()->getGenerator();
        $generated = $generator->writeSchema(
            $schema,
            $package,
            $baseClass,
            $tablePrefix,
            $restrictPrefix
        );

        return $generated ? Command::SUCCESS : Command::FAILURE;
    }
}
