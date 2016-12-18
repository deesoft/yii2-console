<?php

namespace dee\console;

use Yii;
use Cron\CronExpression;
use yii\console\Controller;
use Symfony\Component\Process\Process;
use yii\helpers\FileHelper;

/**
 * Description of SchedulerController
 *
 * @property string $scriptFile
 * @property array $commands
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class SchedulerController extends Controller
{
    /**
     *
     * @var array
     */
    public $commands = [];
    /**
     *
     * @var string
     */
    private $_scriptFile;
    /**
     * @var array
     */
    private $_mapping = [
        '@minutes' => '* * * * *',
        '@fiveMinutes' => '*/5 * * * *',
        '@tenMinutes' => '*/10 * * * *',
    ];

    /**
     *
     */
    public function actionIndex()
    {
        $scriptFile = $this->scriptFile;
        $cwd = dirname($scriptFile);
        $log = Yii::getAlias('@runtime/scheduler') . date('/Ym/d') . '.log';
        FileHelper::createDirectory(dirname($log), 0777);
        foreach ($this->commands as $route => $expression) {
            if (is_int($route)) {
                $route = $expression;
                $expression = true;
            } elseif (isset($this->_mapping[$expression])) {
                $expression = $this->_mapping[$expression];
            }
            if ($expression === true || CronExpression::factory($expression)->isDue()) {
                $command = PHP_BINARY . " $scriptFile $route 2>&1 >>$log";
                $process = new Process($command, $cwd);
                $process->start();
            }
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

    /**
     *
     * @return array
     */
    public function getMapping()
    {
        return $this->_mapping;
    }

    /**
     *
     * @param array $values
     */
    public function setMapping(array $values)
    {
        foreach ($values as $key => $value) {
            $this->_mapping[$key] = $value;
        }
    }
}
