<?php
/**
 * User Registration Color Assignment Hook
 * Automatically assigns colors when new users are registered
 */

require_once 'config.php';
require_once 'color_manager.php';

/**
 * Assign colors to new user during registration
 */
function assignColorsToNewUser($userId, $userType) {
    global $conn;
    
    $colorManager = new ColorManager($conn);
    return $colorManager->assignUserColors($userId, $userType);
}

/**
 * Free colors when user is deleted
 */
function freeColorsOnUserDeletion($userId) {
    global $conn;
    
    $colorManager = new ColorManager($conn);
    return $colorManager->freeUserColors($userId);
}

/**
 * Initialize colors for existing users who don't have colors assigned
 */
function initializeExistingUserColors() {
    global $conn;
    
    $colorManager = new ColorManager($conn);
    
    // Get all admin and attorney users without colors
    $stmt = $conn->prepare("
        SELECT uf.id, uf.user_type 
        FROM user_form uf
        LEFT JOIN user_colors uc ON uf.id = uc.user_id AND uc.is_active = 1
        WHERE uf.user_type IN ('admin', 'attorney') 
        AND uc.user_id IS NULL
        ORDER BY uf.user_type, uf.id
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assigned = 0;
    while ($row = $result->fetch_assoc()) {
        $colors = $colorManager->assignUserColors($row['id'], $row['user_type']);
        if ($colors) {
            $assigned++;
            error_log("Assigned colors to user {$row['id']} ({$row['user_type']}): {$colors['color_name']}");
        }
    }
    
    return $assigned;
}

/**
 * Get color assignment for schedule events
 */
function getEventColors($attorneyId, $attorneyUserType) {
    $colors = getUserColors($attorneyId);
    
    if ($colors) {
        return [
            'schedule_card_color' => $colors['schedule_card_color'],
            'calendar_event_color' => $colors['calendar_event_color'],
            'color_name' => $colors['color_name']
        ];
    }
    
    // Fallback colors if no assignment found
    return [
        'schedule_card_color' => '#D3D3D3',
        'calendar_event_color' => '#696969',
        'color_name' => 'Default Gray'
    ];
}

/**
 * Generate JavaScript color configuration
 */
function generateColorConfig() {
    $allColors = getAllActiveColors();
    
    $config = [
        'users' => [],
        'colorMap' => []
    ];
    
    foreach ($allColors as $user) {
        $config['users'][] = [
            'id' => $user['user_id'],
            'name' => $user['name'],
            'type' => $user['user_type'],
            'scheduleCardColor' => $user['schedule_card_color'],
            'calendarEventColor' => $user['calendar_event_color'],
            'colorName' => $user['color_name']
        ];
        
        $config['colorMap'][$user['user_id']] = [
            'scheduleCardColor' => $user['schedule_card_color'],
            'calendarEventColor' => $user['calendar_event_color'],
            'colorName' => $user['color_name']
        ];
    }
    
    return $config;
}

/**
 * Get CSS for dynamic color coding
 */
function generateColorCSS() {
    $allColors = getAllActiveColors();
    $css = '';
    
    foreach ($allColors as $user) {
        $userId = $user['user_id'];
        $scheduleColor = $user['schedule_card_color'];
        $calendarColor = $user['calendar_event_color'];
        
        // Schedule card colors
        $css .= "
        .event-card[data-user-id=\"{$userId}\"] {
            /* Left border color removed */
            background: linear-gradient(135deg, " . hexToRgba($scheduleColor, 0.08) . " 0%, " . hexToRgba($scheduleColor, 0.15) . " 100%) !important;
        }
        
        .fc-event[data-user-id=\"{$userId}\"] {
            background-color: {$calendarColor} !important;
            border-color: {$calendarColor} !important;
        }
        ";
    }
    
    return $css;
}

/**
 * Convert hex color to rgba
 */
function hexToRgba($hex, $alpha = 1) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    return "rgba({$r}, {$g}, {$b}, {$alpha})";
}

// Auto-initialize colors for existing users if this file is included
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    ensureUserHasColors($_SESSION['user_id'], $_SESSION['user_type']);
}

?>
