<?php
/**
 * Application Logger
 * 
 * General-purpose logging utility for the entire application.
 * Logs to database with support for different types and levels.
 */

class Logger
{
    private PDO $pdo;
    private ?int $userId;
    private string|int|null $formId;
    
    // Log levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    // Log types
    const TYPE_RAYNET = 'raynet';
    const TYPE_AUTH = 'auth';
    const TYPE_API = 'api';
    const TYPE_FORM = 'form';
    const TYPE_SYSTEM = 'system';
    const TYPE_USER = 'user';
    
    public function __construct(PDO $pdo, ?int $userId = null, string|int|null $formId = null)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->formId = $formId;
    }
    
    /**
     * Log a message
     */
    public function log(string $type, string $level, string $message, ?array $context = null): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO logs (type, level, message, context, user_id, form_id, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $type,
                $level,
                $message,
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                $this->userId,
                $this->formId,
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            // Fallback to PHP error log if database logging fails
            error_log("Logger failed: {$e->getMessage()} - Original message: {$message}");
            return false;
        }
    }
    
    /**
     * Log debug message
     */
    public function debug(string $type, string $message, ?array $context = null): bool
    {
        return $this->log($type, self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info(string $type, string $message, ?array $context = null): bool
    {
        return $this->log($type, self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning(string $type, string $message, ?array $context = null): bool
    {
        return $this->log($type, self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error(string $type, string $message, ?array $context = null): bool
    {
        return $this->log($type, self::LEVEL_ERROR, $message, $context);
        
        // Also log to PHP error log for critical visibility
        error_log("[{$type}] {$message}" . ($context ? ' - Context: ' . json_encode($context) : ''));
    }
    
    /**
     * Log critical message
     */
    public function critical(string $type, string $message, ?array $context = null): bool
    {
        $result = $this->log($type, self::LEVEL_CRITICAL, $message, $context);
        
        // Always log critical errors to PHP error log
        error_log("[CRITICAL][{$type}] {$message}" . ($context ? ' - Context: ' . json_encode($context) : ''));
        
        return $result;
    }
    
    /**
     * Get logs by type
     */
    public function getLogs(
        string $type,
        ?string $level = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM logs WHERE type = ?";
        $params = [$type];
        
        if ($level) {
            $sql .= " AND level = ?";
            $params[] = $level;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get error count by type
     */
    public function getErrorCount(string $type, ?string $since = null): int
    {
        $sql = "SELECT COUNT(*) FROM logs WHERE type = ? AND level IN ('error', 'critical')";
        $params = [$type];
        
        if ($since) {
            $sql .= " AND created_at >= ?";
            $params[] = $since;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get recent errors
     */
    public function getRecentErrors(string $type, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM logs 
            WHERE type = ? AND level IN ('error', 'critical')
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$type, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clear old logs
     */
    public function clearOldLogs(string $type, int $daysOld = 30): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM logs 
            WHERE type = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$type, $daysOld]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Set user ID for subsequent logs
     */
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    
    /**
     * Set form ID for subsequent logs
     */
    public function setFormId(?int $formId): self
    {
        $this->formId = $formId;
        return $this;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return null;
    }
    
    /**
     * Create logger instance from config
     */
    public static function create(?int $userId = null, ?int $formId = null): self
    {
        require_once __DIR__ . '/../config/database.php';
        $pdo = getDbConnection();
        return new self($pdo, $userId, $formId);
    }
}
