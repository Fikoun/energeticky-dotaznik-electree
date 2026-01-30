<?php
/**
 * Raynet Exception Classes
 */

namespace Raynet;

/**
 * Base exception for Raynet API errors
 */
class RaynetException extends \Exception
{
    protected int $httpCode;
    
    public function __construct(string $message, int $httpCode = 0, ?\Throwable $previous = null)
    {
        $this->httpCode = $httpCode;
        parent::__construct($message, $httpCode, $previous);
    }
    
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}

/**
 * Exception for rate limit errors
 */
class RaynetRateLimitException extends RaynetException
{
    private ?int $remaining;
    private ?string $resetTime;
    
    public function __construct(string $message, ?int $remaining = null, ?string $resetTime = null)
    {
        $this->remaining = $remaining;
        $this->resetTime = $resetTime;
        parent::__construct($message, 429);
    }
    
    public function getRemaining(): ?int
    {
        return $this->remaining;
    }
    
    public function getResetTime(): ?string
    {
        return $this->resetTime;
    }
}

/**
 * Exception for validation errors
 */
class RaynetValidationException extends RaynetException
{
    private array $errors;
    
    public function __construct(string $message, array $errors = [])
    {
        $this->errors = $errors;
        parent::__construct($message, 400);
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
