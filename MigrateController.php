<?php

namespace dee\console;

use Yii;
use yii\helpers\ArrayHelper;
use yii\console\Exception;
use yii\helpers\VarDumper;
use yii\console\controllers\MigrateController as BaseMigrateController;

/**
 * MigrateController
 * Use at application config
 * 
 * ~~~
 * 'controlerMap' => [
 *     'migrate' => [
 *         'class' => 'dee\console\MigrateController',
 *         'migrationLookup' => [
 *             '@yii/rbac/migrations',
 *             '@mdm/autonumber/migrations',
 *             '@mdm/upload/migrations',
 *         ]
 *     ]
 * ]
 * ~~~
 * Or simply add `migrationLookup` to application params
 * ~~~
 * // file config/params.php
 *
 * return [
 *     'dee.migration.path' => [
 *         '@yii/rbac/migrations',
 *         '@mdm/autonumber/migrations',
 *         '@mdm/upload/migrations',
 *     ]
 * ];
 * ~~~
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class MigrateController extends BaseMigrateController
{
    /**
     * @var array
     */
    public $migrationLookup = [];

    /**
     * @var string 
     */
    public $extraFile = '@runtime/dee-migration-path.php';

    /**
     *
     * @var array
     */
    private $_paths;

    /**
     * @var array
     */
    private $_migrationFiles;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $this->getDirectories();
        return parent::beforeAction($action);
    }

    /**
     * @return array all directories
     */
    protected function getDirectories()
    {
        if ($this->_paths === null) {
            $paths = ArrayHelper::getValue(Yii::$app->params, 'dee.migration.path', []);
            $paths = array_merge($paths, $this->migrationLookup);
            $extra = !empty($this->extraFile) && is_file($this->extraFile = Yii::getAlias($this->extraFile)) ?
                require($this->extraFile) : [];

            $paths = array_merge($extra, $paths);
            $p = [];
            foreach ($paths as $path) {
                $p[Yii::getAlias($path, false)] = true;
            }
            unset($p[false]);
            $currentPath = Yii::getAlias($this->migrationPath);
            if (!isset($p[$currentPath])) {
                $p[$currentPath] = true;
                if (!empty($this->extraFile)) {
                    $extra[] = $this->migrationPath;
                    file_put_contents($this->extraFile, "<?php\nreturn " . VarDumper::export($extra) . ";\n", LOCK_EX);
                }
            }
            $this->_paths = array_keys($p);
        }
        return $this->_paths;
    }

    /**
     * List of migration class at all entire path
     * @return array
     */
    protected function getMigrationFiles()
    {
        if ($this->_migrationFiles === null) {
            $this->_migrationFiles = [];
            $directories = $this->getDirectories();

            foreach ($directories as $dir) {
                if ($dir && is_dir($dir)) {
                    $handle = opendir($dir);
                    while (($file = readdir($handle)) !== false) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        $path = $dir . DIRECTORY_SEPARATOR . $file;
                        if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file($path)) {
                            $this->_migrationFiles[$matches[1]] = $path;
                        }
                    }
                    closedir($handle);
                }
            }

            ksort($this->_migrationFiles);
        }

        return $this->_migrationFiles;
    }

    /**
     * @inheritdoc
     */
    protected function createMigration($class)
    {
        $file = $this->getMigrationFiles()[$class];
        require_once($file);

        return new $class(['db' => $this->db]);
    }

    /**
     * @inheritdoc
     */
    protected function getNewMigrations()
    {
        $applied = [];
        foreach ($this->getMigrationHistory(null) as $version => $time) {
            $applied[substr($version, 1, 13)] = true;
        }

        $migrations = [];
        foreach ($this->getMigrationFiles() as $version => $path) {
            if (!isset($applied[substr($version, 1, 13)])) {
                $migrations[] = $version;
            }
        }

        return $migrations;
    }

    /**
     * Upgrades the application by applying new migration.
     * For example,
     *
     * ~~~
     * yii migrate/partial-up 101129_185401                      # using timestamp
     * yii migrate/partial-up m101129_185401_create_user_table   # using full name
     * ~~~
     *
     * @param string $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * @return int CLI exit code
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function actionPartialUp($version)
    {
        $originalVersion = $version;
        if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
            $version = 'm' . $matches[1];
        } else {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).");
        }

        $migrations = $this->getNewMigrations();
        foreach ($migrations as $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($this->confirm("Apply the $migration migration?")) {
                    if (!$this->migrateUp($migration)) {
                        echo "\nMigration failed.\n";

                        return self::EXIT_CODE_ERROR;
                    }
                    return self::EXIT_CODE_NORMAL;
                }
                return;
            }
        }
        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * Downgrades the application by reverting old migration.
     * For example,
     *
     * ~~~
     * yii migrate/partial-down 101129_185401                      # using timestamp
     * yii migrate/partial-down m101129_185401_create_user_table   # using full name
     * ~~~
     *
     * @param string $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * @return int CLI exit code
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function actionPartialDown($version)
    {
        $originalVersion = $version;
        if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
            $version = 'm' . $matches[1];
        } else {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).");
        }

        $migrations = array_keys($this->getMigrationHistory(null));
        foreach ($migrations as $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($this->confirm("Revert the $migration migration?")) {
                    if (!$this->migrateDown($migration)) {
                        echo "\nMigration failed.\n";

                        return self::EXIT_CODE_ERROR;
                    }
                    return self::EXIT_CODE_NORMAL;
                }
                return;
            }
        }
        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * Redoes partial migration.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ~~~
     * yii migrate/partial-redo 101129_185401                      # using timestamp
     * yii migrate/partial-redo m101129_185401_create_user_table   # using full name
     * ~~~
     *
     * @param string $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * @return int CLI exit code
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function actionPartialRedo($version)
    {
        $originalVersion = $version;
        if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
            $version = 'm' . $matches[1];
        } else {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).");
        }

        $migrations = array_keys($this->getMigrationHistory(null));
        foreach ($migrations as $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($this->confirm("Redo the $migration migration?")) {
                    if (!$this->migrateDown($migration)) {
                        echo "\nMigration failed.\n";

                        return self::EXIT_CODE_ERROR;
                    }
                    if (!$this->migrateUp($migration)) {
                        echo "\nMigration failed.\n";

                        return self::EXIT_CODE_ERROR;
                    }
                    return self::EXIT_CODE_NORMAL;
                }
                return;
            }
        }
        throw new Exception("Unable to find the version '$originalVersion'.");
    }
}
