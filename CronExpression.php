<?php

namespace dee\console;

use Yii;
use yii\base\BaseObject;

/**
 * Description of CronExpression
 *
 * @property int $time
 * @property array $mapping
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class CronExpression extends BaseObject
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
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            return false;
        }
        foreach ($parts as $i => $part) {
            $valid = false;
            foreach (explode(',', $part) as $p) {
                $valid = $i == 4 ? $this->testWeek($p, $this->_parts[$i]) : $this->test($p, $this->_parts[$i]);
                if ($valid) {
                    break;
                }
            }
            if (!$valid) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $part
     * @param int $current 
     * @return boolean
     */
    protected function test($part, $current)
    {
        if (preg_match(self::PART_REGEX, $part, $matches)) {
            if (empty($matches[3])) { // without step
                if (empty($matches[2])) { // without range
                    return $matches[1] == '*' || $matches[1] == $current;
                } else {
                    list($from, $to) = explode('-', $matches[1]);
                    return $from <= $current && $current <= $to;
                }
            } else { // with step
                $step = int_val(substr($matches[3], 1));
                if ($step == 0) {
                    return false;
                }
                if (empty($matches[2])) { // without range
                    $from = $matches[1] == '*' ? 0 : $matches[1];
                    return $current >= $from && ($current - $from) % $step == 0;
                } else {
                    list($from, $to) = explode('-', $matches[1]);
                    return $from <= $current && $current <= $to && ($current - $from) % $step == 0;
                }
            }
        }
        return false;
    }

    /**
     * @param string $part
     * @param int $current
     * @return boolean
     */
    protected function testWeek($part, $current)
    {
        if (preg_match(self::PART_REGEX, $part, $matches)) {
            if (empty($matches[3])) { // without step
                if (empty($matches[2])) { // without range
                    return $matches[1] == '*' || $matches[1] == $current || ($matches[1] == 7 && $current == 0);
                } else {
                    list($from, $to) = explode('-', $matches[1]);
                    if ($to < $from) {
                        $to += 7;
                    }
                    return ($from <= $current && $current <= $to) ||
                        ($from <= ($current + 7) && ($current + 7) <= $to);
                }
            } else { // with step
                $step = int_val(substr($matches[3], 1));
                if ($step == 0) {
                    return false;
                }
                if (empty($matches[2])) { // without range
                    $from = $matches[1] == '*' ? 0 : $matches[1];
                    return ($current >= $from && ($current - $from) % $step == 0) ||
                        ($current == 0 && 7 >= $from && (7 - $from) % $step == 0);
                } else {
                    list($from, $to) = explode('-', $matches[1]);
                    if ($to < $from) {
                        $to += 7;
                    }
                    return ($from <= $current && $current <= $to && ($current - $from) % $step == 0) ||
                        ($from <= $current + 7 && $current + 7 <= $to && ($current + 7 - $from) % $step == 0);
                }
            }
        }
        return false;
    }
}
