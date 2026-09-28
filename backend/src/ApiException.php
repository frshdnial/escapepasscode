<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/** An error that is safe to show to the client. */
final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}
