<?php

namespace dee\console;

use Yii;
use yii\base\Object;

/**
 * Description of CronExpression
 *
 * @property int $time
 * @property array $mapping
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class CronExpression extends Object
{
    /**
     *
     * @var int 
     */
    private $_time;
    /**
     *
     * @var array
     */
    private $_parts;
    /**
     * @var array
     */
    private $_mapping = [
        '@minutes' => true,
        '@fiveMinutes' => '*/5 * * * *',
        '@tenMinutes' => '*/10 * * * *',
        '@sunday' => '0 0 * * 0',
        '@monday' => '0 0 * * 1',
        '@tuesday' => '0 0 * * 2',
        '@wednesday' => '0 0 * * 3',
        '@thursday' => '0 0 * * 4',
        '@friday' => '0 0 * * 5',
        '@saturday' => '0 0 * * 6',
        '@weekday' => '0 0 * * 1-5',
        '@weekend' => '0 0 * * 0,6',
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@hourly' => '0 * * * *'
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->_time === null) {
            $this->_time = time();
            $this->_parts = array_map(function($v) {
                return (int) $v;
            }, explode(' ', date('i H d m w')));
        }
    }

    /**
     *
     * @return int
     */
    public function getTime()
    {
        return $this->_time;
    }

    /**
     *
     * @param int $value
     */
    public function setTime($value)
    {
        $this->_time = $value;
        $this->_parts = array_map(function($v) {
            return (int) $v;
        }, explode(' ', date('i H d m w', $value)));
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

    /**
     *
     * @param string $expression
     * @return boolean
     */
    public function isDue($expression)
    {
        if (isset($this->_mapping[$expression])) {
            $expression = $this->_mapping[$expression];
        }
        if ($expression === true) {
            return true;
        }
        try {
            $parts = explode(' ', $expression);
            foreach ($parts as $i => $part) {
                $valid = false;
                foreach (explode(',', $part) as $p) {
                    if ($this->test($i, $p)) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid) {
                    return false;
                }
            }
            return true;
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * @param int $i
     * @param string $part
     * @return boolean
     */
    private function test($i, $part)
    {
        if (!isset($this->_parts[$i])) {
            return false;
        }
        $current = $this->_parts[$i];
        if ($part === '*' || $part == $current) {
            return true;
        }
        if (strpos($part, '/') === false) {
            $range = explode('-', $part, 2);
            if (empty($range[1])) {
                return false;
            }
            $valid = $current >= $range[0] && $current <= $range[1];
            if ($valid) {
                return true;
            }
            return $i == 4 && $current == 0 && 7 >= $range[0] && 7 <= $range[1];
        }

        // step time
        $parts = explode('/', $part, 2);
        if (empty($parts[1]) || (int) $parts[1] <= 0) {
            return false;
        }
        list($part, $step) = $parts;
        if ($part === '*' || $part == '0') {
            return (int) ($current % $step) == 0;
        }
        $range = explode('-', $part, 2);
        $offset = $range[0];
        $to = isset($range[1]) ? $range[1] : $current;
        $valid = $current >= $offset && $current <= $to && (int) (($current - $offset) % $step) == 0;
        if ($valid) {
            return true;
        }
        return $i == 4 && $current == 0 && 7 >= $offset && 7 <= $to && (int) ((7 - $offset) % $step) == 0;
    }
}
