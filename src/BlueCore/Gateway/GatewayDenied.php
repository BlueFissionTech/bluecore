<?php

namespace BlueFission\BlueCore\Gateway;

use BlueFission\Num;
use RuntimeException;
use Throwable;

class GatewayDenied extends RuntimeException
{
    private int $statusCode;

    public function __construct(
        string $message = 'Gateway denied request dispatch.',
        int $statusCode = 403,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = Num::int($statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
