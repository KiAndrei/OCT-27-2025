<?php
/**
 * Security Monitor - Advanced security event detection and logging
 * Automatically monitors and logs security threats in real-time
 */

require_once 'config.php';
require_once 'audit_logger.php';

class SecurityMonitor {
    private $conn;
    private $auditLogger;
    
    // Security thresholds
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes
    private $suspiciousIPs = [];
    private $blockedFileTypes = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar'];
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLogger = new AuditLogger($conn);
    }
    
    /**
     * Monitor login attempts and detect security threats
     */
    public function monitorLogin($email, $password, $userId = null, $userName = null, $userType = null) {
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check if IP is already blocked
        if ($this->isIPBlocked($ipAddress)) {
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Blocked Login Attempt',
                "Login blocked from blocked IP: $ipAddress",
                'critical'
            );
            return ['success' => false, 'message' => 'Access temporarily blocked due to security violations'];
        }
        
        // Check for suspicious user agent
        if ($this->isSuspiciousUserAgent($userAgent)) {
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Suspicious User Agent',
                "Suspicious user agent detected: $userAgent",
                'high'
            );
        }
        
        // Check for rapid login attempts
        if ($this->isRapidLoginAttempt($ipAddress)) {
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Rapid Login Attempts',
                "Rapid login attempts detected from IP: $ipAddress",
                'high'
            );
        }
        
        return ['success' => true, 'message' => 'Login monitoring completed'];
    }
    
    /**
     * Monitor failed login attempts
     */
    public function monitorFailedLogin($email, $userId = null, $userName = null, $userType = null) {
        $ipAddress = $this->getClientIP();
        
        // Increment failed login count
        $this->incrementFailedLogins($ipAddress, $email);
        
        // Check if threshold exceeded
        $failedCount = $this->getFailedLoginCount($ipAddress, $email);
        
        if ($failedCount >= $this->maxLoginAttempts) {
            // Block IP temporarily
            $this->blockIP($ipAddress, $this->lockoutDuration);
            
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Account Lockout',
                "IP $ipAddress blocked due to $failedCount failed login attempts for email: $email",
                'critical'
            );
            
            return ['blocked' => true, 'message' => 'Too many failed attempts. IP temporarily blocked.'];
        }
        
        $this->logSecurityEvent(
            $userId, $userName, $userType,
            'Failed Login Attempt',
            "Failed login attempt from IP: $ipAddress for email: $email (Attempt $failedCount/$this->maxLoginAttempts)",
            'medium'
        );
        
        return ['blocked' => false, 'message' => 'Login failed'];
    }
    
    /**
     * Monitor file uploads for security threats
     */
    public function monitorFileUpload($fileName, $fileType, $userId, $userName, $userType) {
        $ipAddress = $this->getClientIP();
        $securityIssues = [];
        
        // Check file extension
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($extension, $this->blockedFileTypes)) {
            $securityIssues[] = "Blocked file type: .$extension";
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Blocked File Upload',
                "Attempted to upload blocked file type: .$extension - $fileName",
                'high'
            );
        }
        
        // Check for suspicious file names
        if ($this->isSuspiciousFileName($fileName)) {
            $securityIssues[] = "Suspicious file name detected";
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Suspicious File Name',
                "Suspicious file name detected: $fileName",
                'medium'
            );
        }
        
        // Check file size (if too large)
        if (isset($_FILES['file']['size']) && $_FILES['file']['size'] > 50 * 1024 * 1024) { // 50MB
            $securityIssues[] = "File size exceeds limit";
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Oversized File Upload',
                "Attempted to upload oversized file: $fileName",
                'medium'
            );
        }
        
        if (!empty($securityIssues)) {
            return ['secure' => false, 'issues' => $securityIssues];
        }
        
        // Log successful secure upload
        $this->logSecurityEvent(
            $userId, $userName, $userType,
            'Secure File Upload',
            "Secure file upload completed: $fileName",
            'low'
        );
        
        return ['secure' => true, 'issues' => []];
    }
    
    /**
     * Monitor access violations
     */
    public function monitorAccessViolation($userId, $userName, $userType, $attemptedAccess, $requiredRole) {
        $ipAddress = $this->getClientIP();
        
        $this->logSecurityEvent(
            $userId, $userName, $userType,
            'Access Violation',
            "User attempted to access '$attemptedAccess' (Required: $requiredRole, User: $userType)",
            'high'
        );
        
        // Increment violation count
        $this->incrementAccessViolations($userId);
        
        // Check if user should be flagged
        $violationCount = $this->getAccessViolationCount($userId);
        if ($violationCount >= 3) {
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'User Flagged',
                "User flagged due to multiple access violations ($violationCount)",
                'critical'
            );
        }
    }
    
    /**
     * Monitor suspicious activities
     */
    public function monitorSuspiciousActivity($userId, $userName, $userType, $activity, $details) {
        $ipAddress = $this->getClientIP();
        
        // Check for unusual patterns
        if ($this->isUnusualActivity($userId, $activity)) {
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Unusual Activity',
                "Unusual activity detected: $activity - $details",
                'medium'
            );
        }
        
        // Check for bulk operations
        if ($this->isBulkOperation($activity)) {
            $this->logSecurityEvent(
                $userId, $userName, $userType,
                'Bulk Operation',
                "Bulk operation detected: $activity - $details",
                'medium'
            );
        }
    }
    
    /**
     * Get security statistics
     */
    public function getSecurityStats() {
        try {
            $stats = [];
            
            // Security events today
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() AND module = 'Security'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['security_events_today'] = $result->fetch_assoc()['count'];
            
            // Critical security events today
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() 
                AND module = 'Security' 
                AND priority = 'critical'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['critical_events_today'] = $result->fetch_assoc()['count'];
            
            // Blocked IPs
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() 
                AND action LIKE '%Blocked%'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['blocked_attempts_today'] = $result->fetch_assoc()['count'];
            
            // Failed logins
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM audit_trail 
                WHERE DATE(timestamp) = CURDATE() 
                AND action LIKE '%Failed Login%'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['failed_logins_today'] = $result->fetch_assoc()['count'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get security stats: " . $e->getMessage());
            return [];
        }
    }
    
    // Private helper methods
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
    
    private function isIPBlocked($ip) {
        // Check if IP is in blocked list (implement with your preferred method)
        return in_array($ip, $this->suspiciousIPs);
    }
    
    private function isSuspiciousUserAgent($userAgent) {
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isRapidLoginAttempt($ip) {
        // Check for multiple login attempts within short time
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM audit_trail 
            WHERE ip_address = ? 
            AND action LIKE '%Login%' 
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        return $count > 10; // More than 10 attempts per minute
    }
    
    private function incrementFailedLogins($ip, $email) {
        // Implement failed login tracking (you can use a separate table or session)
        $_SESSION['failed_logins'][$ip][$email] = ($_SESSION['failed_logins'][$ip][$email] ?? 0) + 1;
    }
    
    private function getFailedLoginCount($ip, $email) {
        return $_SESSION['failed_logins'][$ip][$email] ?? 0;
    }
    
    private function blockIP($ip, $duration) {
        // Implement IP blocking (you can use a separate table)
        $this->suspiciousIPs[] = $ip;
    }
    
    private function isSuspiciousFileName($fileName) {
        $suspiciousPatterns = [
            'hack', 'virus', 'malware', 'exploit', 'backdoor', 'rootkit', 'trojan'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($fileName, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isUnusualActivity($userId, $activity) {
        // Check if this is unusual for this user
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM audit_trail 
            WHERE user_id = ? 
            AND action = ? 
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("is", $userId, $activity);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        return $count > 50; // More than 50 of same action per hour
    }
    
    private function isBulkOperation($activity) {
        $bulkPatterns = [
            'bulk', 'mass', 'batch', 'multiple', 'all', 'delete all', 'update all'
        ];
        
        foreach ($bulkPatterns as $pattern) {
            if (stripos($activity, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function incrementAccessViolations($userId) {
        $_SESSION['access_violations'][$userId] = ($_SESSION['access_violations'][$userId] ?? 0) + 1;
    }
    
    private function getAccessViolationCount($userId) {
        return $_SESSION['access_violations'][$userId] ?? 0;
    }
    
    private function logSecurityEvent($userId, $userName, $userType, $action, $description, $priority) {
        $this->auditLogger->logSecurityEvent($userId, $userName, $userType, $action, $description, $priority);
    }
}

// Create global instance
$securityMonitor = new SecurityMonitor($conn);

// Helper functions for easy use
function monitorLoginSecurity($email, $password, $userId = null, $userName = null, $userType = null) {
    global $securityMonitor;
    return $securityMonitor->monitorLogin($email, $password, $userId, $userName, $userType);
}

function monitorFailedLoginSecurity($email, $userId = null, $userName = null, $userType = null) {
    global $securityMonitor;
    return $securityMonitor->monitorFailedLogin($email, $userId, $userName, $userType);
}

function monitorFileUploadSecurity($fileName, $fileType, $userId, $userName, $userType) {
    global $securityMonitor;
    return $securityMonitor->monitorFileUpload($fileName, $fileType, $userId, $userName, $userType);
}

function monitorAccessViolation($userId, $userName, $userType, $attemptedAccess, $requiredRole) {
    global $securityMonitor;
    return $securityMonitor->monitorAccessViolation($userId, $userName, $userType, $attemptedAccess, $requiredRole);
}

function getSecurityStatistics() {
    global $securityMonitor;
    return $securityMonitor->getSecurityStats();
}
?>
