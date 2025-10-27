<?php
/**
 * Color Management System for Role-based Schedule Color Coding
 * Handles color assignment, management, and consistency across devices
 */

require_once 'config.php';

class ColorManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get user colors for a specific user
     */
    public function getUserColors($userId) {
        $stmt = $this->conn->prepare("
            SELECT schedule_card_color, calendar_event_color, color_name 
            FROM user_colors 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [
                'schedule_card_color' => $row['schedule_card_color'],
                'calendar_event_color' => $row['calendar_event_color'],
                'color_name' => $row['color_name']
            ];
        }
        
        return null;
    }
    
    /**
     * Assign colors to a new user
     */
    public function assignUserColors($userId, $userType) {
        // Check if user already has colors assigned
        if ($this->getUserColors($userId)) {
            return $this->getUserColors($userId);
        }
        
        if ($userType === 'admin') {
            // Admin always gets fixed colors
            return $this->assignAdminColors($userId);
        } elseif ($userType === 'attorney') {
            // Attorney gets sequential or random colors
            return $this->assignAttorneyColors($userId);
        }
        
        // Other user types don't get colors
        return null;
    }
    
    /**
     * Assign admin colors (fixed)
     */
    private function assignAdminColors($userId) {
        $scheduleColor = '#E6B0AA';  // Light Maroon
        $calendarColor = '#800000';   // Maroon
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_colors (user_id, user_type, schedule_card_color, calendar_event_color, color_name) 
            VALUES (?, 'admin', ?, ?, 'Admin Maroon')
        ");
        $stmt->bind_param('iss', $userId, $scheduleColor, $calendarColor);
        $stmt->execute();
        
        return [
            'schedule_card_color' => $scheduleColor,
            'calendar_event_color' => $calendarColor,
            'color_name' => 'Admin Maroon'
        ];
    }
    
    /**
     * Assign attorney colors (sequential for 4 attorneys only)
     */
    private function assignAttorneyColors($userId) {
        // Get count of existing attorneys (excluding admin) - only count actual attorneys
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attorney_count 
            FROM user_colors uc
            JOIN user_form uf ON uc.user_id = uf.id
            WHERE uf.user_type = 'attorney' AND uc.is_active = 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $attorneyCount = $row['attorney_count'];
        
        if ($attorneyCount == 0) {
            // First attorney gets Light Blue / Sky Blue
            return $this->assignSpecificColors($userId, '#ADD8E6', '#87CEEB', 'Attorney Sky Blue');
        } elseif ($attorneyCount == 1) {
            // Second attorney gets Light Green / Green
            return $this->assignSpecificColors($userId, '#90EE90', '#008000', 'Attorney Light Green');
        } elseif ($attorneyCount == 2) {
            // Third attorney gets Light Pink / Hot Pink
            return $this->assignSpecificColors($userId, '#FFB6C1', '#FF69B4', 'Attorney Light Pink');
        } elseif ($attorneyCount == 3) {
            // Fourth attorney gets Beige / Tan
            return $this->assignSpecificColors($userId, '#F5F5DC', '#D2B48C', 'Attorney Beige');
        } elseif ($attorneyCount == 4) {
            // Fifth attorney gets Slate Gray / Dark Slate Gray
            return $this->assignSpecificColors($userId, '#708090', '#2F4F4F', 'Attorney Slate Gray');
        } else {
            // No more attorneys expected (only 5 attorneys + 1 admin = 6 total)
            return $this->assignSpecificColors($userId, '#D3D3D3', '#696969', 'Default Gray');
        }
    }
    
    /**
     * Assign specific colors to a user
     */
    private function assignSpecificColors($userId, $scheduleColor, $calendarColor, $colorName) {
        $stmt = $this->conn->prepare("
            INSERT INTO user_colors (user_id, user_type, schedule_card_color, calendar_event_color, color_name) 
            VALUES (?, 'attorney', ?, ?, ?)
        ");
        $stmt->bind_param('isss', $userId, $scheduleColor, $calendarColor, $colorName);
        $stmt->execute();
        
        return [
            'schedule_card_color' => $scheduleColor,
            'calendar_event_color' => $calendarColor,
            'color_name' => $colorName
        ];
    }
    
    /**
     * Assign random colors from available pool
     */
    private function assignRandomColors($userId) {
        // Get available colors
        $stmt = $this->conn->prepare("
            SELECT schedule_card_color, calendar_event_color, color_name 
            FROM available_colors 
            WHERE is_available = 1 
            AND schedule_card_color NOT IN (
                SELECT schedule_card_color FROM user_colors WHERE is_active = 1
            )
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $this->assignSpecificColors($userId, $row['schedule_card_color'], $row['calendar_event_color'], $row['color_name']);
        }
        
        // Fallback to default colors if no available colors
        return $this->assignSpecificColors($userId, '#D3D3D3', '#696969', 'Default Gray');
    }
    
    /**
     * Free colors when user is deleted
     */
    public function freeUserColors($userId) {
        $stmt = $this->conn->prepare("
            UPDATE user_colors 
            SET is_active = 0, freed_at = NOW() 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        return $stmt->affected_rows > 0;
    }
    
    /**
     * Get all active user colors for JavaScript
     */
    public function getAllActiveColors() {
        $stmt = $this->conn->prepare("
            SELECT uf.id, uf.name, uf.user_type, uc.schedule_card_color, uc.calendar_event_color, uc.color_name
            FROM user_form uf
            LEFT JOIN user_colors uc ON uf.id = uc.user_id AND uc.is_active = 1
            WHERE uf.user_type IN ('admin', 'attorney')
            ORDER BY uf.user_type, uf.id
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $colors = [];
        while ($row = $result->fetch_assoc()) {
            // If user doesn't have colors, try to assign them
            if (!$row['schedule_card_color'] || !$row['calendar_event_color']) {
                $assignedColors = $this->assignUserColors($row['id'], $row['user_type']);
                if ($assignedColors) {
                    $row['schedule_card_color'] = $assignedColors['schedule_card_color'];
                    $row['calendar_event_color'] = $assignedColors['calendar_event_color'];
                    $row['color_name'] = $assignedColors['color_name'];
                }
            }
            
            $colors[] = [
                'user_id' => $row['id'],
                'name' => $row['name'],
                'user_type' => $row['user_type'],
                'schedule_card_color' => $row['schedule_card_color'],
                'calendar_event_color' => $row['calendar_event_color'],
                'color_name' => $row['color_name']
            ];
        }
        
        return $colors;
    }
    
    /**
     * Ensure user has colors assigned (for existing users)
     */
    public function ensureUserHasColors($userId, $userType) {
        $colors = $this->getUserColors($userId);
        
        if (!$colors && in_array($userType, ['admin', 'attorney'])) {
            return $this->assignUserColors($userId, $userType);
        }
        
        return $colors;
    }
    
    /**
     * Get color statistics
     */
    public function getColorStats() {
        $stats = [];
        
        // Count active colors by type
        $stmt = $this->conn->prepare("
            SELECT user_type, COUNT(*) as count 
            FROM user_colors 
            WHERE is_active = 1 
            GROUP BY user_type
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats[$row['user_type']] = $row['count'];
        }
        
        // Count available colors
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as available_count 
            FROM available_colors 
            WHERE is_available = 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stats['available'] = $row['available_count'];
        
        return $stats;
    }
}

// Global function to get user colors easily
function getUserColors($userId) {
    global $conn;
    $colorManager = new ColorManager($conn);
    return $colorManager->getUserColors($userId);
}

// Global function to ensure user has colors
function ensureUserHasColors($userId, $userType) {
    global $conn;
    $colorManager = new ColorManager($conn);
    return $colorManager->ensureUserHasColors($userId, $userType);
}

// Global function to get all active colors
function getAllActiveColors() {
    global $conn;
    $colorManager = new ColorManager($conn);
    return $colorManager->getAllActiveColors();
}

?>
