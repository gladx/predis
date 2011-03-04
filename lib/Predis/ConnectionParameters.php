<?php

namespace Predis;

class ConnectionParameters {
    private $_parameters;
    private static $_sharedOptions;

    public function __construct($parameters = null) {
        $parameters = $parameters ?: array();
        $this->_parameters = is_array($parameters)
            ? $this->filter($parameters)
            : $this->parseURI($parameters);
    }

    private static function paramsExtractor($params, $kv) {
        @list($k, $v) = explode('=', $kv);
        $params[$k] = $v;
        return $params;
    }

    private static function getSharedOptions() {
        if (isset(self::$_sharedOptions)) {
            return self::$_sharedOptions;
        }

        $optEmpty   = new Options\Option();
        $optBoolean = new Options\CustomOption(array(
            'validate' => function($value) { return (bool) $value; },
            'default'  => function() { return false; },
        ));

        self::$_sharedOptions = array(
            'scheme' => new Options\CustomOption(array(
                'default'  => function() { return 'tcp'; },
            )),
            'host' => new Options\CustomOption(array(
                'default'  => function() { return '127.0.0.1'; },
            )),
            'port' => new Options\CustomOption(array(
                'validate' => function($value) { return (int) $value; },
                'default'  => function() { return 6379; },
            )),
            'path' => $optEmpty,
            'database' => $optEmpty,
            'password' => $optEmpty,
            'connection_async' => $optBoolean,
            'connection_persistent' => $optBoolean,
            'connection_timeout' => new Options\CustomOption(array(
                'default'  => function() { return 5; },
            )),
            'read_write_timeout' => $optEmpty,
            'alias' => $optEmpty,
            'weight' => $optEmpty,
        );

        return self::$_sharedOptions;
    }

    public static function define($name, Options\IOption $option) {
        self::getSharedOptions();
        self::$_sharedOptions[$name] = $option;
    }

    public static function undefine($name) {
        self::getSharedOptions();
        unset(self::$_sharedOptions[$name]);
    }

    protected function parseURI($uri) {
        if (!is_string($uri)) {
            throw new \InvalidArgumentException('URI must be a string');
        }
        if (stripos($uri, 'unix') === 0) {
            // Hack to support URIs for UNIX sockets with minimal effort.
            $uri = str_ireplace('unix:///', 'unix://localhost/', $uri);
        }
        $parsed = @parse_url($uri);
        if ($parsed == false || !isset($parsed['host'])) {
            throw new ClientException("Invalid URI: $uri");
        }
        if (array_key_exists('query', $parsed)) {
            $query  = explode('&', $parsed['query']);
            $parsed = array_reduce($query, 'self::paramsExtractor', $parsed);
        }
        return $this->filter($parsed);
    }

    protected function filter($parameters) {
        $handlers = self::getSharedOptions();
        foreach ($parameters as $parameter => $value) {
            if (isset($handlers[$parameter])) {
                $parameters[$parameter] = $handlers[$parameter]($value);
            }
        }
        return $parameters;
    }

    private function tryInitializeValue($parameter) {
        if (isset(self::$_sharedOptions[$parameter])) {
            $value = self::$_sharedOptions[$parameter]->getDefault();
            $this->_parameters[$parameter] = $value;
            return $value;
        }
    }

    public function __get($parameter) {
        if (isset($this->_parameters[$parameter])) {
            return $this->_parameters[$parameter];
        }
        return $this->tryInitializeValue($parameter);
    }

    public function __isset($parameter) {
        if (isset($this->_parameters[$parameter])) {
            return true;
        }
        $value = $this->tryInitializeValue($parameter);
        return isset($value);
    }
}
