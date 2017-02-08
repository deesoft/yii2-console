<?php

namespace dee\console;

use Yii;
use yii\console\Controller;
use Symfony\Component\Process\Process;
use yii\helpers\FileHelper;

/**
 * Description of SchedulerController
 *
 * @property string $scriptFile
 * @property array $mapping
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class SchedulerController extends Controller
{
    /**
     *
     * @var array
     * ```php
     * [
     *     'migrate/up --interactive=0' => '@daily',
     * ]
     * ```
     */
    public $commands = [];
    /**
     * @var string|array
     */
    public $cron = 'dee\console\CronExpression';
    /**
     *
     * @var bool execute job as asynchronous
     */
    public $asynchron = true;

    public $timeout = 60;
    /**
     * @var string
     */
    private $_scriptFile;

    /**
     *
     */
    public function actionIndex($debug = false)
    {
        $scriptFile = $this->scriptFile;
        $cwd = dirname($scriptFile);
        $log = Yii::getAlias('@runtime/scheduler') . date('/Ym/d') . '.log';
        FileHelper::createDirectory(dirname($log), 0777);

        /* @var $cron CronExpression */
        $cron = Yii::createObject($this->cron);
        $routes = [];
        foreach ($this->commands as $route => $expression) {
            if (is_int($route)) {
                $route = $expression;
                $expression = true;
            }
            if ($cron->isDue($expression)) {
                $routes[] = $route;
                $command = PHP_BINARY . " $scriptFile $route 2>&1 >>$log";
                $process = new Process($command, $cwd, null, null, $this->timeout);
                if ($this->asynchron) {
                    $process->start();
                    sleep(1);
                } else {
                    $process->run();
                }
            }
        }
        if ($debug) {
            echo date('Y-m-d H:i:s => [') . implode(', ', $routes) . "]\n";
        }
    }

    /**
     * @return string
     */
    public function getScriptFile()
    {
        if ($this->_scriptFile === null) {
            $cmd = $_SERVER['argv'][0];
            if (strncmp($cmd, './', 2) === 0) {
                $cmd = substr($cmd, 2);
            }
            if (strncmp($cmd, '/', 1) === 0) {
                $this->_scriptFile = $cmd;
            } else {
                $this->_scriptFile = getcwd() . '/' . $cmd;
            }
        }
        return $this->_scriptFile;
    }

    /**
     * @param string $value
     */
    public function setScriptFile($value)
    {
        if ($value) {
            $this->_scriptFile = realpath(Yii::getAlias($value));
        } else {
            $this->_scriptFile = null;
        }
    }
}
