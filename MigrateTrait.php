<?php

namespace dee\console;

use Yii;
use yii\helpers\ArrayHelper;
use yii\console\Exception;
use yii\helpers\VarDumper;
use yii\helpers\FileHelper;

/**
 * Description of MigrateTrait
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.2
 */
trait MigrateTrait
{
    /**
     * @var string Excluded version to be applied, down or redo.
     */
    public $excepts;
    /**
     * @var array
     */
    public $migrationLookup = [];
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
            $paths = ArrayHelper::getValue(Yii::$app->params, $this->paramVar, []);
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
                    FileHelper::createDirectory(dirname($this->extraFile));
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
    protected function getMigrationHistory($limit)
    {
        $historiy = parent::getMigrationHistory($limit);
        $excepts = $this->getExcepts();
        foreach ($historiy as $version => $time) {
            if (isset($excepts[substr($version, 1, 13)])) {
                unset($historiy[$version]);
            }
        }
        return $historiy;
    }

    /**
     * Excepted version to be applied
     */
    protected function getExcepts()
    {
        if (!is_array($this->excepts)) {
            $excepts = [];
            if (!empty($this->excepts)) {
                foreach (preg_split('/\s*,\s*/', $this->excepts) as $version) {
                    $matches = null;
                    if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
                        $excepts[$matches[1]] = $matches[1];
                    }
                }
            }
            $this->excepts = $excepts;
        }
        return $this->excepts;
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
        $excepts = $this->getExcepts();
        foreach ($this->getMigrationFiles() as $version => $path) {
            if (!isset($applied[substr($version, 1, 13)]) && !isset($excepts[substr($version, 1, 13)])) {
                $migrations[] = $version;
            }
        }

        return $migrations;
    }

    /**
     * Upgrades the application by applying new migrations.
     * For example,
     *
     * ```
     * yii migrate     # apply all new migrations
     * yii migrate 3   # apply the first 3 new migrations
     * yii migrate 101129_185401                      # apply only one specific migration
     * yii migrate m101129_185401_create_user_table   # using full name
     * ```
     *
     * @param integer $limit the number of new migrations or specific version to be applied.
     * If 0, it means applying all available new migrations.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionUp($limit = 0)
    {
        if (is_int($limit)) {
            return parent::actionUp($limit);
        } else {
            return $this->actionPartialUp($limit);
        }
    }

    /**
     * Downgrades the application by reverting old migrations.
     * For example,
     *
     * ```
     * yii migrate/down     # revert the last migration
     * yii migrate/down 3   # revert the last 3 migrations
     * yii migrate/down all # revert all migrations
     * yii migrate/down 101129_185401                      # revert specific migration
     * yii migrate/down m101129_185401_create_user_table   # using full name
     * ```
     *
     * @param integer $limit the number of migrations or specific version to be reverted. Defaults to 1,
     * meaning the last applied migration will be reverted.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionDown($limit = 1)
    {
        if (is_integer($limit) || $limit === 'all') {
            return parent::actionDown($limit);
        } else {
            return $this->actionPartialDown($limit);
        }
    }

    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * yii migrate/redo     # redo the last applied migration
     * yii migrate/redo 3   # redo the last 3 applied migrations
     * yii migrate/redo all # redo all migrations
     * yii migrate/redo 101129_185401                      # redo specific migration
     * yii migrate/redo m101129_185401_create_user_table   # using full name
     * ```
     *
     * @param integer $limit the number of migrations or specific version to be redone. Defaults to 1,
     * meaning the last applied migration will be redone.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionRedo($limit = 1)
    {
        if (is_integer($limit) || $limit === 'all') {
            return parent::actionRedo($limit);
        } else {
            return $this->actionPartialRedo($limit);
        }
    }

    /**
     * Upgrades the application by applying new migration.
     * For example,
     *
     * ```
     * yii migrate/partial-up 101129_185401                      # using timestamp
     * yii migrate/partial-up m101129_185401_create_user_table   # using full name
     * ```
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
     * ```
     * yii migrate/partial-down 101129_185401                      # using timestamp
     * yii migrate/partial-down m101129_185401_create_user_table   # using full name
     * ```
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
     * ```
     * yii migrate/partial-redo 101129_185401                      # using timestamp
     * yii migrate/partial-redo m101129_185401_create_user_table   # using full name
     * ```
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

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID), $actionID === 'create' ? [] : ['excepts']
        );
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'e' => 'excepts',
        ]);
    }
}
