<?php

namespace dee\console;
use yii\caching\Cache;
use yii\di\Instance;

/**
 * Description of Request
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class Request extends \yii\console\Request
{
    const REGEX = '~\{([\w._-]+):?([^\}]+)?\}~x';
    const DEFAULT_REGEX = '[^/]+';

    /**
     *
     * @var array
     */
    public $rules = [];
    /**
     *
     * @var Cache
     */
    public $cache;
    /**
     *
     * @var array
     */
    private $_routes;

    /**
     * @inheritdoc
     */
    public function resolve()
    {
        $this->prepare();
        list($path, $params) = parent::resolve();

        if (!empty($this->_routes)) {
            if (isset($this->_routes['static'][$path])) {
                list($route, $_params) = $this->_routes['static'][$path];
                $params += $_params;
                return [$route, $params];
            }
            foreach ($this->_routes['var'] as $regex => $data) {
                if (preg_match($regex, $path, $matches)) {
                    list($route, $_params, $varNames) = $data;
                    $p = [];
                    foreach ($varNames as $n => $varName) {
                        $p['{'.$varName.'}'] = $matches[$n];
                        $_params[$varName] = $matches[$n];
                    }
                    $route = strtr($route, $p);
                    $params += $_params;
                    return [$route, $params];
                }
            }
        }
        return [$path, $params];
    }


    /**
     * Prepare routing
     */
    protected function prepare()
    {
        if (empty($this->rules)) {
            $this->_routes = [];
            return;
        }

        if ($this->cache) {
            $this->cache = Instance::ensure($this->cache, Cache::className());
        }
        if ($this->_routes === null) {
            if (!$this->cache || ($this->_routes = $this->cache->get([__CLASS__, $this->rules])) === false) {
                $this->_routes = [];

                foreach ($this->rules as $pattern => $route) {
                    if (is_array($route)) {
                        $params = array_slice($route, 1);
                        $route = $route[0];
                    } else {
                        $params = [];
                    }
                    $this->parse($pattern, $route, $params);
                }

                if ($this->cache) {
                    $this->cache->set([__CLASS__, $this->rules], $this->_routes);
                }
            }
        }
    }

    /**
     * Parses a route string that does not contain optional segments.
     */
    protected function parse($pattern, $route, $params)
    {
        $pattern = ltrim($pattern, '/');
        if (preg_match_all(self::REGEX, $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            $regex = '~^';
            $variables = [];
            $offset = 0;
            $i = 1;
            foreach ($matches as $match) {
                if ($match[0][1] > $offset) {
                    $regex .= preg_quote(substr($pattern, $offset, $match[0][1] - $offset), '~');
                }
                $variables['d' . $i] = $match[1][0];
                $_regex = isset($match[2][0]) ? $match[2][0] : self::DEFAULT_REGEX;
                $regex .= "(?P<d{$i}>$_regex)";
                $offset = $match[0][1] + strlen($match[0][0]);
                $i++;
            }

            if ($offset != strlen($pattern)) {
                $regex .= preg_quote(substr($pattern, $offset), '~');
            }
            $regex .= '$~';
            $this->_routes['var'][$regex] = [$route, $params, $variables];
        } else {
            $this->_routes['static'][$pattern] = [$route, $params];
        }
    }
}
