<?php
/**
 * UserActivityTracker - Sledování aktivity uživatelů
 * Měří čas v minutách/hodinách a počet formulářů
 */

class UserActivityTracker {
    private $pdo;
    private $useDatabase;
    
    public function __construct() {
        $this->initDatabase();
    }
    
    private function initDatabase() {
        try {
            require_once __DIR__ . '/../config/database.php';
            $this->pdo = getDbConnection();
            $this->useDatabase = true;
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->useDatabase = false;
        }
    }
    
    /**
     * Zaznamenat aktivitu uživatele
     */
    public function logActivity($userId, $activityType, $description = null, $sessionDuration = null, $metadata = null) {
        if (!$this->useDatabase) return false;
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity 
                (user_id, activity_type, activity_description, session_duration_minutes, ip_address, user_agent, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $metadataJson = $metadata ? json_encode($metadata) : null;
            
            $stmt->execute([
                $userId,
                $activityType,
                $description,
                $sessionDuration,
                $ipAddress,
                $userAgent,
                $metadataJson
            ]);
            
            // Aktualizovat denní statistiky
            $this->updateDailyStats($userId, $activityType, $sessionDuration);
            
            return true;
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zaznamenat přihlášení uživatele
     */
    public function logLogin($userId) {
        // Zaznamenat aktivitu
        $this->logActivity($userId, 'login', 'Uživatel se přihlásil');
        
        // Aktualizovat last_login v users tabulce
        if ($this->useDatabase) {
            try {
                $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$userId]);
                
                // Přepočítat login streak
                $this->calculateLoginStreak($userId);
            } catch (PDOException $e) {
                error_log("Error updating login time: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Zaznamenat odhlášení s celkovou dobou sezení
     */
    public function logLogout($userId, $sessionDurationMinutes) {
        $this->logActivity($userId, 'logout', 'Uživatel se odhlásil', $sessionDurationMinutes);
        
        // Aktualizovat celkový čas přihlášení
        if ($this->useDatabase) {
            try {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET total_login_time_minutes = total_login_time_minutes + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$sessionDurationMinutes, $userId]);
            } catch (PDOException $e) {
                error_log("Error updating total login time: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Zaznamenat aktivitu s formulářem
     */
    public function logFormActivity($userId, $formId, $action, $description = null) {
        $activityType = 'form_' . $action; // form_create, form_edit, form_submit, form_view
        $fullDescription = $description ?: "Akce '$action' s formulářem #$formId";
        
        $metadata = [
            'form_id' => $formId,
            'action' => $action
        ];
        
        $this->logActivity($userId, $activityType, $fullDescription, null, $metadata);
    }
    
    /**
     * Získat aktivitu uživatele za posledních N dní
     */
    public function getUserActivity($userId, $days = 30) {
        if (!$this->useDatabase) {
            return $this->getMockUserActivity($userId, $days);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as activity_count,
                    SUM(CASE WHEN activity_type = 'login' THEN 1 ELSE 0 END) as logins,
                    SUM(COALESCE(session_duration_minutes, 0)) as total_minutes,
                    GROUP_CONCAT(DISTINCT activity_type) as activity_types
                FROM user_activity 
                WHERE user_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user activity: " . $e->getMessage());
            return $this->getMockUserActivity($userId, $days);
        }
    }
    
    /**
     * Získat formuláře uživatele s pokročilým filtrováním
     */
    public function getUserForms($userId, $page = 1, $limit = 10, $search = '', $statusFilter = '') {
        if (!$this->useDatabase) {
            return $this->getMockUserForms($userId, $page, $limit, $search, $statusFilter);
        }
        
        try {
            $offset = ($page - 1) * $limit;
            $searchQuery = "%$search%";
            
            // Podmínky pro vyhledávání a filtrování
            $whereConditions = ["user_id = ?"];
            $params = [$userId];
            
            if (!empty($search)) {
                $whereConditions[] = "(title LIKE ? OR contact_name LIKE ? OR form_id LIKE ?)";
                $params[] = $searchQuery;
                $params[] = $searchQuery;
                $params[] = $searchQuery;
            }
            
            if (!empty($statusFilter)) {
                $whereConditions[] = "status = ?";
                $params[] = $statusFilter;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Dotaz pro formuláře
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    form_id,
                    title,
                    status,
                    contact_name,
                    company_name,
                    completion_time_minutes,
                    created_at,
                    submitted_at,
                    updated_at
                FROM forms 
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dotaz pro celkový počet
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM forms 
                WHERE $whereClause
            ");
            
            $countParams = array_slice($params, 0, -2); // Odstranit limit a offset
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'forms' => $forms,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting user forms: " . $e->getMessage());
            return $this->getMockUserForms($userId, $page, $limit, $search, $statusFilter);
        }
    }
    
    /**
     * Vypočítat login streak uživatele
     */
    public function calculateLoginStreak($userId) {
        if (!$this->useDatabase) return 0;
        
        try {
            // Zavolat uloženou proceduru pro výpočet streak
            $stmt = $this->pdo->prepare("CALL CalculateLoginStreak(?)");
            $stmt->execute([$userId]);
            
            // Získat aktualizovaný streak
            $stmt = $this->pdo->prepare("SELECT login_streak FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['login_streak'] : 0;
        } catch (PDOException $e) {
            error_log("Error calculating login streak: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Získat detailní statistiky uživatele
     */
    public function getUserStats($userId) {
        if (!$this->useDatabase) {
            return $this->getMockUserStats($userId);
        }
        
        try {
            // Základní informace o uživateli
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.*,
                    COALESCE(f.total_forms, 0) as total_forms,
                    COALESCE(f.forms_draft, 0) as forms_draft,
                    COALESCE(f.forms_submitted, 0) as forms_submitted,
                    COALESCE(f.forms_confirmed, 0) as forms_confirmed,
                    COALESCE(f.forms_completed, 0) as forms_completed,
                    CASE 
                        WHEN COALESCE(f.total_forms, 0) > 0 
                        THEN ROUND((COALESCE(f.forms_confirmed, 0) * 100.0 / COALESCE(f.total_forms, 0)), 2)
                        ELSE 0 
                    END as success_rate,
                    DATEDIFF(NOW(), u.created_at) as days_since_registration,
                    CASE 
                        WHEN u.last_activity IS NOT NULL 
                        THEN DATEDIFF(NOW(), u.last_activity) 
                        ELSE NULL 
                    END as days_since_last_activity
                FROM users u
                LEFT JOIN (
                    SELECT 
                        user_id,
                        COUNT(*) as total_forms,
                        COUNT(CASE WHEN status = 'draft' THEN 1 END) as forms_draft,
                        COUNT(CASE WHEN status = 'submitted' THEN 1 END) as forms_submitted,
                        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as forms_confirmed,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as forms_completed
                    FROM forms 
                    WHERE user_id = ?
                    GROUP BY user_id
                ) f ON u.id = f.user_id
                WHERE u.id = ?
            ");
            
            $stmt->execute([$userId, $userId]);
            $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userStats) {
                throw new Exception("Uživatel nenalezen");
            }
            
            // Poslední aktivita
            $stmt = $this->pdo->prepare("
                SELECT activity_type, activity_description, created_at
                FROM user_activity 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $userStats['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Statistiky za poslední měsíc vs předchozí měsíc
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as forms_last_30_days,
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 60 DAY) 
                            AND DATE(created_at) < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as forms_previous_30_days
                FROM forms 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $monthlyComparison = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($monthlyComparison['forms_previous_30_days'] > 0) {
                $userStats['forms_change'] = round((
                    ($monthlyComparison['forms_last_30_days'] - $monthlyComparison['forms_previous_30_days']) 
                    / $monthlyComparison['forms_previous_30_days']
                ) * 100, 1);
            } else {
                $userStats['forms_change'] = $monthlyComparison['forms_last_30_days'] > 0 ? '+100' : '0';
            }
            
            return $userStats;
            
        } catch (PDOException $e) {
            error_log("Error getting user stats: " . $e->getMessage());
            return $this->getMockUserStats($userId);
        }
    }
    
    // Private helper methods
    
    private function updateDailyStats($userId, $activityType, $sessionDuration) {
        if (!$this->useDatabase) return;
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_daily_stats 
                (user_id, date, login_count, total_time_minutes, page_views, forms_created, forms_edited, forms_submitted, forms_viewed)
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    login_count = login_count + VALUES(login_count),
                    total_time_minutes = total_time_minutes + VALUES(total_time_minutes),
                    page_views = page_views + VALUES(page_views),
                    forms_created = forms_created + VALUES(forms_created),
                    forms_edited = forms_edited + VALUES(forms_edited),
                    forms_submitted = forms_submitted + VALUES(forms_submitted),
                    forms_viewed = forms_viewed + VALUES(forms_viewed)
            ");
            
            $loginCount = ($activityType === 'login') ? 1 : 0;
            $timeMinutes = $sessionDuration ?: 0;
            $pageViews = ($activityType === 'page_view') ? 1 : 0;
            $formsCreated = ($activityType === 'form_create') ? 1 : 0;
            $formsEdited = ($activityType === 'form_edit') ? 1 : 0;
            $formsSubmitted = ($activityType === 'form_submit') ? 1 : 0;
            $formsViewed = ($activityType === 'form_view') ? 1 : 0;
            
            $stmt->execute([
                $userId, $loginCount, $timeMinutes, $pageViews, 
                $formsCreated, $formsEdited, $formsSubmitted, $formsViewed
            ]);
            
        } catch (PDOException $e) {
            error_log("Error updating daily stats: " . $e->getMessage());
        }
    }
    
    private function getClientIp() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return 'unknown';
    }
    
    // Mock data methods for fallback
    
    private function getMockUserActivity($userId, $days) {
        $activity = [];
        for ($i = 0; $i < min($days, 30); $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $activity[] = [
                'date' => $date,
                'activity_count' => rand(1, 10),
                'logins' => rand(0, 3),
                'total_minutes' => rand(30, 240),
                'activity_types' => 'login,form_view,page_view'
            ];
        }
        return $activity;
    }
    
    private function getMockUserForms($userId, $page, $limit, $search, $statusFilter) {
        $mockForms = [
            [
                'id' => 1,
                'form_id' => 'FORM_001',
                'title' => 'Bateriový systém pro rodinný dům',
                'status' => 'completed',
                'contact_name' => 'Test Uživatel',
                'company_name' => null,
                'completion_time_minutes' => 25,
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-4 days')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-4 days'))
            ],
            [
                'id' => 2,
                'form_id' => 'FORM_002',
                'title' => 'Solární instalace s baterií',
                'status' => 'submitted',
                'contact_name' => 'Test Uživatel',
                'company_name' => null,
                'completion_time_minutes' => 15,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ]
        ];
        
        return [
            'forms' => array_slice($mockForms, ($page-1) * $limit, $limit),
            'total' => count($mockForms),
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil(count($mockForms) / $limit)
        ];
    }
    
    private function getMockUserStats($userId) {
        return [
            'id' => $userId,
            'name' => 'Test Uživatel',
            'email' => 'test@temp.local',
            'role' => 'user',
            'phone' => '+420 987 654 321',
            'address' => 'Praha 2, Náměstí Míru 5',
            'ico' => null,
            'dic' => null,
            'company_name' => null,
            'total_forms' => 7,
            'forms_draft' => 2,
            'forms_submitted' => 3,
            'forms_confirmed' => 2,
            'forms_completed' => 0,
            'success_rate' => 71.43,
            'login_streak' => 7,
            'total_login_time_minutes' => 240,
            'last_login' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'last_activity' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'days_since_registration' => 45,
            'days_since_last_activity' => 0,
            'forms_change' => '+15.2',
            'recent_activities' => [
                [
                    'activity_type' => 'form_edit',
                    'activity_description' => 'Editace formuláře FORM_002',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
                ],
                [
                    'activity_type' => 'login',
                    'activity_description' => 'Uživatel se přihlásil',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ]
            ]
        ];
    }
}
?>
