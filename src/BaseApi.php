<?php

namespace XHyperf\BaseApi;

use Hyperf\Stringable\Str;

use function Hyperf\Support\make;

/**
 * 通用API基类
 */
class BaseApi
{
    protected static array $container = [];

    public static function __callStatic(string $name, array $args)
    {
        $name = ucfirst($name);

        if (isset(static::$container[$name])) {
            return static::$container[$name];
        }

        return static::$container[$name] = make(Str::beforeLast(static::class, '\\') . '\\Api\\' . $name);
    }
}