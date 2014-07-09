<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Developer Tools                                                |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2014 Phalcon Team (http://www.phalconphp.com)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file docs/LICENSE.txt.                        |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Andres Gutierrez <andres@phalconphp.com>                      |
  |          Eduar Carvajal <eduar@phalconphp.com>                         |
  +------------------------------------------------------------------------+
*/

namespace Phalcon;

use Phalcon\Script\Color;
use Phalcon\Version\Item as VersionItem;
use Phalcon\Mvc\Model\Migration as ModelMigration;

class Migrations
{

    /**
     * Generate migrations
     *
     * @param $options
     *
     * @throws \Exception
     */
    public static function generate($options)
    {

        $path = $options['directory'];
        $tableName = $options['tableName'];
        $databaseName = $options['databaseName'];
        $exportData = $options['exportData'];
        $migrationsDir = $options['migrationsDir'];
        $originalVersion = $options['originalVersion'];
        $force = $options['force'];
        $config = $options['config'];

        if ($migrationsDir && !file_exists($migrationsDir)) {
            mkdir($migrationsDir);
        }

        // Find all DB sections
        if ($databaseName == 'all') {
            $sections = array_filter(array_keys(get_object_vars($config)), function($section) { return substr($section, -2) == 'DB'; });
        } else {
            $sections = [$databaseName];
        }

        foreach ($sections as $sectionName)
        {
            ModelMigration::setup($config->$sectionName);

            if (!file_exists($migrationsDir.'/'.$sectionName)) {
                mkdir($migrationsDir.'/'.$sectionName);
            }

            ModelMigration::setMigrationPath($migrationsDir);
            if ($tableName == 'all') {
                $migrations = ModelMigration::generateAll($exportData);
                foreach ($migrations as $tName => $migration) {
                    file_put_contents($migrationsDir.'/'.$sectionName.'/'.$tName.'.php', '<?php '.PHP_EOL.PHP_EOL.$migration);
                }
            } else {
                $migration = ModelMigration::generate($tableName, $exportData);
                file_put_contents($migrationsDir.'/'.$sectionName.'/'.$tableName.'.php', '<?php '.PHP_EOL.PHP_EOL.$migration);
            }

            if ( self::isConsole() ) {

                print Color::success('Migration was successfully generated').PHP_EOL;
            }
        }
    }

    /**
     * Check if the script is running on Console mode
     *
     * @return boolean
     */
    public static function isConsole()
    {
        return !isset($_SERVER['SERVER_SOFTWARE']);
    }

    /**
     * Run migrations
     */
    public static function run($options)
    {

        $path = $options['directory'];
        $migrationsDir = $options['migrationsDir'];
        $config = $options['config'];

        if (isset($options['tableName'])) {
            $tableName = $options['tableName'];
        } else {
            $tableName = 'all';
        }

        if (isset($options['databaseName'])) {
            $databaseName = $options['databaseName'];
        } else {
            $databaseName = 'all';
        }

        if (!file_exists($migrationsDir)) {
            throw new \Phalcon\Mvc\Model\Exception('Migrations directory could not found');
        }

        $versions = array();
        $iterator = new \DirectoryIterator($migrationsDir);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (preg_match('/[a-z0-9](\.[a-z0-9]+)+/', $fileinfo->getFilename(), $matches)) {
                    $versions[] = new VersionItem($matches[0], 3);
                }
            }
        }

        if ($databaseName == 'all') {
            // Find all DB sections
            $sections = array_filter(array_keys(get_object_vars($config)), function($section) { return substr($section, -2) == 'DB'; });
        } else {
            $sections = [$databaseName];
        }

        if (empty($sections)) {
            throw new \Exception("Cannot load database configuration");
        }

        foreach ($sections as $sectionName)
        {
            ModelMigration::setup($config->$sectionName);

            ModelMigration::setMigrationPath($migrationsDir.'/'.$sectionName);

                if ($tableName == 'all') {
                    if (file_exists($migrationsDir.'/'.$sectionName)) {
                        $iterator = new \DirectoryIterator($migrationsDir.'/'.$sectionName);
                        foreach ($iterator as $fileinfo) {
                            if ($fileinfo->isFile()) {
                                if (preg_match('/\.php$/', $fileinfo->getFilename())) {
                                    \Phalcon\Mvc\Model\Migration::migrateFile($migrationsDir.'/'.$sectionName.'/'.$fileinfo->getFilename());
                                }
                            }
                        }
                    }
                } else {
                    $migrationPath = $migrationsDir.'/'.$sectionName.'/'.$tableName.'.php';
                    if (file_exists($migrationPath)) {
                        ModelMigration::migrateFile($migrationPath);
                    } else {
                        throw new ScriptException('Migration class was not found '.$migrationPath);
                    }
                }
                print Color::success('Database was successfully migrated').PHP_EOL;
        }
    }

}
