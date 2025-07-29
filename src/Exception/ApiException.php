<?php

namespace XHyperf\BaseApi\Exception;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, protected array|string $origin = [])
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array|string 获取原始响应内容
     */
    public function getOrigin(): array|string
    {
        return $this->origin;
    }
}