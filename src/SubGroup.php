<?php

namespace XHyperf\BaseApi;

use Psr\Container\ContainerInterface;

class SubGroup
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    /**
     * @param string $name
     * @param array  $args
     * @return mixed
     * @throws
     */
    public function __call(string $name, array $args)
    {
        $name = ucfirst($name);

        return $this->container->get(static::class . '\\' . $name);
    }
}