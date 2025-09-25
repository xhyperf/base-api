<?php

namespace XHyperf\BaseApi;

use Hyperf\Stringable\Str;

use function Hyperf\Support\make;

/**
 * 通用API基类
 */
class BaseApi
{
    /**
     * 默认入口标识
     */
    protected const string DEFAULT_ENTRY = 'default';

    protected static array $container = [];

    public static function __callStatic(string $name, array $args)
    {
        $name  = ucfirst($name);
        $entry = $args[0] ?? static::DEFAULT_ENTRY;

        if (isset(static::$container[$name][$entry])) {
            return static::$container[$name][$entry];
        }

        return static::$container[$name][$entry] = make(Str::beforeLast(static::class, '\\') . '\\Api\\' . $name, compact('entry'));
    }
}