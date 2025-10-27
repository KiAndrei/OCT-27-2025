<?php
/**
 * Audit Logger - Comprehensive logging system for all user actions
 * Tracks admin, attorney, employee, and client activities
 */

require_once 'config.php';

class AuditLogger {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Log user action to audit trail
     */
    public function logAction($userId, $userName, $userType, $action, $module, $description = '', $status = 'success', $priority = 'low', $additionalData = null) {
        try {
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $this->conn->prepare("
                INSERT INTO audit_trail (user_id, user_name, user_type, action, module, description, ip_address, user_agent, status, priority, additional_data, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $additionalDataJson = $additionalData ? json_encode($additionalData) : null;
            
            $stmt->bind_param("issssssssss", 
                $userId, $userName, $userType, $action, $module, $description, 
                $ipAddress, $userAgent, $status, $priority, $additionalDataJson
            );
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log login attempts
     */
    public function logLogin($userId, $userName, $userType, $status = 'success') {
        $priority = $status === 'success' ? 'low' : 'high';
        $description = $status === 'success' ? 
            ucfirst($userType) . ' user logged in successfully' : 
            'Failed login attempt for ' . $userType . ' user';
            
        return $this->logAction($userId, $userName, $userType, 'User Login', 'Authentication', $description, $status, $priority);
    }
    
    /**
     * Log logout
     */
    public function logLogout($userId, $userName, $userType) {
        return $this->logAction($userId, $userName, $userType, 'User Logout', 'Authentication', ucfirst($userType) . ' user logged out');
    }
    
    /**
     * Log document operations
     */
    public function logDocumentAction($userId, $userName, $userType, $action, $fileName, $status = 'success') {
        $priority = $status === 'success' ? 'medium' : 'high';
        $description = ucfirst($action) . ' document: ' . $fileName;
        
        return $this->logAction($userId, $userName, $userType, 'Document ' . $action, 'Document Management', $description, $status, $priority);
    }
    
    /**
     * Log case operations
     */
    public function logCaseAction($userId, $userName, $userType, $action, $caseDetails, $status = 'success') {
        $priority = $status === 'success' ? 'medium' : 'high';
        $description = ucfirst($action) . ' case: ' . $caseDetails;
        
        return $this->logAction($userId, $userName, $userType, 'Case ' . $action, 'Case Management', $description, $status, $priority);
    }
    
    /**
     * Log user management operations
     */
    public function logUserManagement($userId, $userName, $userType, $action, $targetUser, $status = 'success') {
        $priority = $status === 'success' ? 'medium' : 'high';
        $description = ucfirst($action) . ' user: ' . $targetUser;
        
        return $this->logAction($userId, $userName, $userType, 'User ' . $action, 'User Management', $description, $status, $priority);
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($userId, $userName, $userType, $action, $description, $priority = 'high') {
        return $this->logAction($userId, $userName, $userType, $action, 'Security', $description, 'warning', $priority);
    }
    
    /**
     * Log system events
     */
    public function logSystemEvent($action, $description, $status = 'success', $priority = 'medium') {
        return $this->logAction(0, 'System', 'system', $action, 'System', $description, $status, $priority);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Get audit trail data with filters
     * 
     * SECURITY NOTE: user_id filter is MANDATORY for data isolation.
     * Without it, users could see audit logs from other users.
     * Always include user_id in filters for user-specific queries.
     */
    public function getAuditTrail($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        // User ID filter - CRITICAL for data isolation
        if (!empty($filters['user_id'])) {
            $whereClause .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        // User type filter
        if (!empty($filters['user_type']) && $filters['user_type'] !== 'all') {
            $whereClause .= " AND user_type = ?";
            $params[] = $filters['user_type'];
            $types .= "s";
        }
        
        // Module filter
        if (!empty($filters['module']) && $filters['module'] !== 'all') {
            $whereClause .= " AND module = ?";
            $params[] = $filters['module'];
            $types .= "s";
        }
        
        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        // Priority filter
        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $whereClause .= " AND priority = ?";
            $params[] = $filters['priority'];
            $types .= "s";
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause .= " AND (user_name LIKE ? OR action LIKE ? OR description LIKE ? OR module LIKE ?)";
            $searchTerm = "%" . $filters['search'] . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ssss";
        }
        
        $sql = "SELECT * FROM audit_trail $whereClause ORDER BY timestamp DESC";
        
        // Add pagination support
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get audit trail: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of audit trail records with filters
     */
    public function getAuditTrailCount($filters = []) {
        $whereClause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        // User ID filter - CRITICAL for data isolation
        if (!empty($filters['user_id'])) {
            $whereClause .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        // User type filter
        if (!empty($filters['user_type']) && $filters['user_type'] !== 'all') {
            $whereClause .= " AND user_type = ?";
            $params[] = $filters['user_type'];
            $types .= "s";
        }
        
        // Module filter
        if (!empty($filters['module']) && $filters['module'] !== 'all') {
            $whereClause .= " AND module = ?";
            $params[] = $filters['module'];
            $types .= "s";
        }
        
        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        // Priority filter
        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $whereClause .= " AND priority = ?";
            $params[] = $filters['priority'];
            $types .= "s";
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause .= " AND (user_name LIKE ? OR action LIKE ? OR description LIKE ? OR module LIKE ?)";
            $searchTerm = "%" . $filters['search'] . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ssss";
        }
        
        $sql = "SELECT COUNT(*) as total FROM audit_trail $whereClause";
        
        try {
            $stmt = $this->conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_assoc()['total'];
        } catch (Exception $e) {
            error_log("Failed to get audit trail count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStats() {
        try {
            $stats = [];
            
            // Total actions today
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM audit_trail WHERE DATE(timestamp) = CURDATE()");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['today_total'] = $result->fetch_assoc()['count'];
            
            // Actions by user type today
            $stmt = $this->conn->prepare("
                SELECT user_type, COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() 
                GROUP BY user_type
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['by_user_type'] = [];
            while ($row = $result->fetch_assoc()) {
                $stats['by_user_type'][$row['user_type']] = $row['count'];
            }
            
            // Actions by module today
            $stmt = $this->conn->prepare("
                SELECT module, COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() 
                GROUP BY module
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['by_module'] = [];
            while ($row = $result->fetch_assoc()) {
                $stats['by_module'][$row['module']] = $row['count'];
            }
            
            // Security events today
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() AND module = 'Security'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['security_events'] = $result->fetch_assoc()['count'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get audit stats: " . $e->getMessage());
            return [];
        }
    }
}

// Create global instance
$auditLogger = new AuditLogger($conn);

// Helper function to log actions easily
function logAuditAction($userId, $userName, $userType, $action, $module, $description = '', $status = 'success', $priority = 'low', $additionalData = null) {
    global $auditLogger;
    return $auditLogger->logAction($userId, $userName, $userType, $action, $module, $description, $status, $priority, $additionalData);
}
?>
