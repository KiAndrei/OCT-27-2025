<?php
/**
 * Color Configuration API Endpoint
 * Returns color configuration for JavaScript consumption
 */

require_once 'config.php';
require_once 'color_manager.php';

header('Content-Type: application/json');

try {
    // Get all active colors
    $allColors = getAllActiveColors();
    
    $config = [
        'users' => [],
        'colorMap' => [],
        'timestamp' => time()
    ];
    
    foreach ($allColors as $user) {
        // Skip users with null or invalid colors
        if (!$user['schedule_card_color'] || !$user['calendar_event_color']) {
            error_log("Skipping user {$user['user_id']} with invalid colors");
            continue;
        }
        
        $userConfig = [
            'id' => $user['user_id'],
            'name' => $user['name'],
            'type' => $user['user_type'],
            'scheduleCardColor' => $user['schedule_card_color'],
            'calendarEventColor' => $user['calendar_event_color'],
            'colorName' => $user['color_name']
        ];
        
        $config['users'][] = $userConfig;
        $config['colorMap'][$user['user_id']] = $userConfig;
    }
    
    // Add color statistics
    $colorManager = new ColorManager($conn);
    $stats = $colorManager->getColorStats();
    $config['stats'] = $stats;
    
    echo json_encode($config, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load color configuration',
        'message' => $e->getMessage()
    ]);
}
?>
