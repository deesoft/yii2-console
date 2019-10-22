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
    const PART_REGEX = '/^(\*|\d+(-\d+)?)(\/\d+)?$/';
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
            $parts = preg_split('/\s+/', trim($expression));
            if (count($parts) !== 5) {
                return false;
            }
            foreach ($parts as $i => $part) {
                $valid = false;
                foreach (explode(',', $part) as $p) {
                    if ($this->test($i, $p)) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid && $i == 4 && $this->_parts[4] == 0) {
                    $this->_parts[4] = 7;
                    foreach (explode(',', $part) as $p) {
                        if ($this->test($i, $p)) {
                            $valid = true;
                            break;
                        }
                    }
                    $this->_parts[4] = 0;
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
        if (preg_match(self::PART_REGEX, $part, $matches)){
            if (empty($matches[3]) { // without step
                if (empty($matches[2]) { // without range
                    return $matches[1] == '*' || $matches[1] == $current;
                } else {
                    list($from, $to) = explode('-', $matches[1]);
                    return $from <= $current && $current <= $to;
                }
            } else { // with step
                $step = int_val(substr($matches[3], 1));
                if (empty($matches[2]) { // without range
                    $from = $matches[1] == '*' ? 0 : $matches[1];
                    return $current >= $from && ($current - $from) % $step == 0;
                } else {
                    list($from, $to) = explode('-', $matches[1]);
                    return $from <= $current && $current <= $to  && ($current - $from) % $step == 0;
                }
            }
        }
    }
}
