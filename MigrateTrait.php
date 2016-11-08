<?php

namespace dee\console;

use Yii;
use yii\helpers\ArrayHelper;
use yii\console\Exception;
use yii\helpers\VarDumper;
use yii\helpers\FileHelper;
use yii\helpers\Console;

/**
 * Description of MigrateTrait
 *
 * @property array $migrationNamespaces
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
            foreach ($this->migrationNamespaces as $namespace) {
                $path = str_replace('/', DIRECTORY_SEPARATOR, Yii::getAlias('@' . str_replace('\\', '/', $namespace)));
                $this->_paths[$namespace] = $path;
            }
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

            foreach ($directories as $namespace => $dir) {
                if ($dir && is_dir($dir)) {
                    $handle = opendir($dir);
                    while (($file = readdir($handle)) !== false) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        $path = $dir . DIRECTORY_SEPARATOR . $file;
                        if (preg_match('/^(m(\d{6}_\d{6})\D.*?)\.php$/is', $file, $matches) && is_file($path)) {
                            $class = (is_int($namespace) ? '' : $namespace . '\\') . $matches[1];
                            $this->_migrationFiles[$class] = $path;
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
        $history = parent::getMigrationHistory($limit);
        foreach ($history as $class => $time) {
            if ($this->isExcept($class)) {
                unset($history[$class]);
            }
        }
        return $history;
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
                    if (preg_match('/^m?(\d{6}_\d{6})(\D.*?)?$/is', $version, $matches)) {
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
        foreach ($this->getMigrationHistory(null) as $class => $time) {
            $applied[trim($class,'\\')] = true;
        }

        $migrations = [];
        foreach ($this->getMigrationFiles() as $class => $time) {
            if (!isset($applied[$class]) && !$this->isExcept($class)) {
                $migrations[] = $class;
            }
        }
        ksort($migrations);
        return $migrations;
    }

    protected function isExcept($class)
    {
        foreach ($this->getExcepts() as $version) {
            if (strpos($class, $version) !== false) {
                return true;
            }
        }
        return false;
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
        if (is_numeric($limit)) {
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
        if (is_numeric($limit) || $limit === 'all') {
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
        if (is_numeric($limit) || $limit === 'all') {
            return parent::actionRedo($limit);
        } else {
            return $this->actionPartialRedo($limit);
        }
    }

    /**
     *
     * @param string $part
     * @param string $versions
     * @return array
     */
    public function getVersions($part, $versions = 'all')
    {
        if ($part === 'new') {
            $migrations = $this->getNewMigrations();
        } elseif ($part === 'history') {
            $migrations = array_keys($this->getMigrationHistory(null));
        } else {
            $this->stdout("\nUnknown part '{$part}'.\n", Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        }
        if ($versions === 'all') {
            return array_values($migrations);
        }
        $versions = preg_split('/\s*,\s*/', $versions);
        $result = [];
        foreach ($versions as $version) {
            $matches = [];
            if (preg_match('/^m?(\d{6}_\d{6})(\D.*?)?$/is', $version, $matches)) {
                foreach ($migrations as $migration) {
                    if (strpos($migration, $matches[1]) !== false) {
                        $result[] = $migration;
                        break;
                    }
                }
            }
        }
        return $result;
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
        $migrations = $this->getVersions('new', $version);
        if (empty($migrations)) {
            $this->stdout("No new migrations to be applied.\n", Console::FG_GREEN);
            return self::EXIT_CODE_NORMAL;
        }
        $n = count($migrations);
        $this->stdout("Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);

        foreach ($migrations as $migration) {
            $this->stdout("\t$migration\n");
        }
        $this->stdout("\n");

        $applied = 0;
        if ($this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')) {
            foreach ($migrations as $migration) {
                if (!$this->migrateUp($migration)) {
                    $this->stdout("\n$applied from $n " . ($applied === 1 ? 'migration was' : 'migrations were') . " applied.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                    return self::EXIT_CODE_ERROR;
                }
                $applied++;
            }

            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " applied.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated up successfully.\n", Console::FG_GREEN);
        }
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
        $migrations = $this->getVersions('history', $version);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n", Console::FG_GREEN);
            return self::EXIT_CODE_NORMAL;
        }
        $n = count($migrations);
        $this->stdout("Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be referted:\n", Console::FG_YELLOW);

        foreach ($migrations as $migration) {
            $this->stdout("\t$migration\n");
        }
        $this->stdout("\n");

        $reverted = 0;
        if ($this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')) {
            foreach ($migrations as $migration) {
                if (!$this->migrateDown($migration)) {
                    $this->stdout("\n$reverted from $n " . ($reverted === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                    return self::EXIT_CODE_ERROR;
                }
                $reverted++;
            }
            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated down successfully.\n", Console::FG_GREEN);
        }
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
        $migrations = $this->getVersions('history', $version);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n", Console::FG_GREEN);
            return self::EXIT_CODE_NORMAL;
        }

        $n = count($migrations);
        $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n", Console::FG_YELLOW);
        foreach ($migrations as $migration) {
            $this->stdout("\t$migration\n");
        }
        $this->stdout("\n");

        if ($this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')) {
            foreach ($migrations as $migration) {
                if (!$this->migrateDown($migration)) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                    return self::EXIT_CODE_ERROR;
                }
            }
            foreach (array_reverse($migrations) as $migration) {
                if (!$this->migrateUp($migration)) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                    return self::EXIT_CODE_ERROR;
                }
            }
            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " redone.\n", Console::FG_GREEN);
            $this->stdout("\nMigration redone successfully.\n", Console::FG_GREEN);
        }
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
