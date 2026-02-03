<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Exception;
use Throwable;

class HostingException extends Exception
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        protected array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get additional context about the exception
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create an exception with context
     *
     * @param array<string, mixed> $context
     */
    public static function withContext(string $message, array $context = [], int $code = 0, ?Throwable $previous = null): static
    {
        return new static($message, $code, $previous, $context);
    }
}
