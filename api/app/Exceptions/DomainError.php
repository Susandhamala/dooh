<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An expected business-rule failure. Rendered as the API envelope with $status.
 */
class DomainError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly ?array $errors = null)
    {
        parent::__construct($message);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public static function unauthenticated(string $message): self
    {
        return new self($message, 401);
    }
}
