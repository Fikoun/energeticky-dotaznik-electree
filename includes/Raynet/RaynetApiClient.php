<?php
/**
 * Raynet API HTTP Client
 * 
 * Handles low-level HTTP communication with Raynet CRM API.
 * Includes authentication, rate limiting, and error handling.
 */

namespace Raynet;

class RaynetApiClient
{
    private const BASE_URL = 'https://app.raynet.cz/api/v2';
    
    private string $username;
    private string $apiKey;
    private string $instanceName;
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;
    
    // Rate limit tracking
    private ?int $rateLimitRemaining = null;
    private ?string $rateLimitReset = null;
    
    public function __construct(
        string $username,
        string $apiKey,
        string $instanceName,
        int $timeout = 30,
        int $retryAttempts = 3,
        int $retryDelay = 2
    ) {
        $this->username = $username;
        $this->apiKey = $apiKey;
        $this->instanceName = $instanceName;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;
    }
    
    /**
     * Create client from config file
     */
    public static function fromConfig(string $configPath = null): self
    {
        $configPath = $configPath ?? dirname(__DIR__, 2) . '/config/raynet.php';
        
        if (!file_exists($configPath)) {
            throw new RaynetException("Raynet config file not found: $configPath");
        }
        
        $config = require $configPath;
        
        return new self(
            $config['username'] ?? '',
            $config['api_key'] ?? '',
            $config['instance_name'] ?? '',
            $config['timeout'] ?? 30,
            $config['retry_attempts'] ?? 3,
            $config['retry_delay'] ?? 2
        );
    }
    
    /**
     * GET request
     */
    public function get(string $endpoint, array $params = []): ?array
    {
        $url = self::BASE_URL . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->request('GET', $url);
    }
    
    /**
     * PUT request (create)
     */
    public function put(string $endpoint, array $data): array
    {
        $url = self::BASE_URL . $endpoint;
        return $this->request('PUT', $url, $data);
    }
    
    /**
     * POST request (update)
     */
    public function post(string $endpoint, array $data): array
    {
        $url = self::BASE_URL . $endpoint;
        return $this->request('POST', $url, $data);
    }
    
    /**
     * DELETE request
     */
    public function delete(string $endpoint): bool
    {
        $url = self::BASE_URL . $endpoint;
        $this->request('DELETE', $url);
        return true;
    }
    
    /**
     * Execute HTTP request with retry logic
     */
    private function request(string $method, string $url, ?array $data = null): ?array
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $this->retryAttempts) {
            $attempt++;
            
            try {
                return $this->executeRequest($method, $url, $data);
            } catch (RaynetRateLimitException $e) {
                $lastException = $e;
                error_log("Raynet rate limit hit, attempt $attempt/{$this->retryAttempts}. Waiting {$this->retryDelay}s...");
                sleep($this->retryDelay);
            }
        }
        
        throw $lastException ?? new RaynetException("Request failed after {$this->retryAttempts} attempts");
    }
    
    /**
     * Execute single HTTP request
     */
    private function executeRequest(string $method, string $url, ?array $data = null): ?array
    {
        $headers = [
            'Authorization: Basic ' . base64_encode("{$this->username}:{$this->apiKey}"),
            'X-Instance-Name: ' . $this->instanceName,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeout,
                'ignore_errors' => true // Allow reading response body on errors
            ]
        ];
        
        if ($data !== null && in_array($method, ['PUT', 'POST'])) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        
        error_log("Raynet API Request: $method $url");
        if ($data) {
            error_log("Raynet API Request Body: " . json_encode($data));
        }
        
        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        
        $this->parseRateLimitHeaders($responseHeaders);
        $statusCode = $this->parseStatusCode($responseHeaders);
        
        error_log("Raynet API Response: HTTP $statusCode");
        
        if ($response !== false) {
            error_log("Raynet API Response Body: " . substr($response, 0, 500));
        }
        
        // Handle rate limiting
        if ($statusCode === 429) {
            throw new RaynetRateLimitException(
                "Rate limit exceeded. Reset at: {$this->rateLimitReset}",
                $this->rateLimitRemaining,
                $this->rateLimitReset
            );
        }
        
        // Handle not found
        if ($statusCode === 404) {
            return null;
        }
        
        // Handle errors
        if ($statusCode >= 400) {
            $errorBody = $response ? json_decode($response, true) : null;
            $errorMessage = $errorBody['message'] ?? "HTTP Error $statusCode";
            throw new RaynetException("Raynet API error: $errorMessage (HTTP $statusCode)", $statusCode);
        }
        
        // Parse response
        if ($response === false || $response === '') {
            return $method === 'DELETE' ? [] : null;
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RaynetException("Failed to parse Raynet API response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Parse HTTP status code from response headers
     */
    private function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }
    
    /**
     * Parse rate limit headers
     */
    private function parseRateLimitHeaders(array $headers): void
    {
        foreach ($headers as $header) {
            if (stripos($header, 'X-Ratelimit-Remaining:') === 0) {
                $this->rateLimitRemaining = (int) trim(substr($header, 22));
            } elseif (stripos($header, 'X-Ratelimit-Reset:') === 0) {
                $this->rateLimitReset = trim(substr($header, 18));
            }
        }
    }
    
    /**
     * Get remaining rate limit
     */
    public function getRateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }
    
    /**
     * Check if credentials are configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->username) 
            && !empty($this->apiKey) 
            && !empty($this->instanceName)
            && $this->username !== 'your-email@company.cz';
    }
}
