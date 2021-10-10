<?php

/**
 * 
 */
namespace src;

/**
 * 
 */
final class RouterHttp {

    /**
     * 
     */
    private $patterns = [];
    private $status_codes = [];
    private $Response = null;

    /**
     * 
     */
    public function __construct(?RouterHttp\Response\Template $Template = null) {
        $this->Response = new RouterHttp\Response($Template);
    }

    /**
     * 
     */
    public function __unset(string $name) {
        return false;
    }

    /**
     * 
     */
    public function __get(string $name) {
        return $this->{$name};
    }

    /**
     * if methods params is numeric (http status codes) then the callback will be stored as a status code route 
     */
    public function map(string $methods, ?string $pattern, \Closure $callback): void {
        if (is_numeric($methods))
            $this->status_codes[$methods] = $callback;
        else
            $this->patterns[$pattern] = [
                'methods' => array_map('trim', explode('|', strtoupper($methods))), 
                'callback' => $callback,
            ];
    }

    /**
     * 
     */
    public function exit(int $code): void {
        if (($callback = $this->status_codes[$code] ?? false) && is_callable($callback))
            $callback->call($this);
        else
            http_response_code($code);
    }

    /**
     * every regex match must have it's arg in the callback
     * the args in the callback can be a class name
     * to match everything use pattern: '/^([a-z0-9\-]{3,})$/i' and as function arg: <string> $slug 
     * 
     * usage:
     * 1) $RouterHttp->map('get', '/^([a-z0-9\-]{3,})$/i', function(string $slug) { ... });
     * 2) $RouterHttp->map('get', '/^online/article/([a-z0-9\-]{1,19})/([0-9]{1,9})$/i', function(string $category, int product_id) { ... });
     * 3) $RouterHttp->map('get', '/^profile/([a-z0-9\-]{3,})$/i', function(Profile $Profile) { ... });
     *   3.1) class Profile(string $username) { ... }
     */
    public function listen(?string $uri = null, ?string $method = null, string $prefix = null) {
        $uri = $uri ?: preg_replace('/\?(.*)?/is', null, substr($_SERVER['REQUEST_URI'], 1));
        $uri = $prefix ? substr($uri, strlen($prefix)) : $uri;
        $method = strtoupper(trim($method) ?: $_SERVER['REQUEST_METHOD']);

        foreach(array_filter($this->patterns, function($m) use ($method) { return in_array($method, $m['methods']); }) as $pattern => ['methods' => $methods, 'callback' => $callback]) {
            if (!($is_regex_pattern = preg_match('/^\/.+\/[a-z]*$/i', $pattern)) && $uri == $pattern)
                return $callback->call($this);
            else if ($is_regex_pattern && preg_match_all($pattern, $uri, $args, \PREG_SET_ORDER)) {
                $args = array_splice($args[0], 1);
                $params = (new \ReflectionFunction($callback))->getParameters();
        
                // if expected args are greater than passed params
                if (count(array_filter($params, function($p) { return !$p->isOptional(); })) > count($args))
                    return $this->exit(501);

                foreach ($params as $i => $param) {
                    if (!isset($args[$i]))
                        $args[$i] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;

                    if ($args[$i] && $ReflectionClass = $param->getClass())
                        $args[$i] = $ReflectionClass->newInstanceArgs(count($params) == 1 ? $args : [$args[$i]]);
                    else if ($args[$i] && $param->hasType() && !@settype($args[$i], $param->getType()->getName()))
                        return $this->exit(501);
                }
                
                return $callback->call($this, ...$args);
            }
        }

        return $this->exit(404);
    }
}
?>