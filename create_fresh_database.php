<?php
// FRESH DATABASE CREATOR
// This creates a completely clean database structure

require_once 'config.php';

echo "Creating fresh database structure...\n";

try {
    // Drop existing tables if they exist
    echo "1. Dropping existing tables...\n";
    $conn->query("DROP TABLE IF EXISTS `client_attorney_messages`");
    $conn->query("DROP TABLE IF EXISTS `client_employee_messages`");
    $conn->query("DROP TABLE IF EXISTS `client_attorney_conversations`");
    $conn->query("DROP TABLE IF EXISTS `client_employee_conversations`");
    $conn->query("DROP TABLE IF EXISTS `client_attorney_assignments`");
    echo "   ✓ Dropped existing tables\n";
    
    // Create client_attorney_assignments table
    echo "2. Creating client_attorney_assignments table...\n";
    $conn->query("CREATE TABLE `client_attorney_assignments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `client_id` int(11) NOT NULL,
        `employee_id` int(11) NOT NULL,
        `attorney_id` int(11) NOT NULL,
        `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `status` enum('Active','Completed','Cancelled') DEFAULT 'Active',
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `attorney_id` (`attorney_id`),
        KEY `employee_id` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=1");
    echo "   ✓ Created client_attorney_assignments\n";
    
    // Create client_attorney_conversations table
    echo "3. Creating client_attorney_conversations table...\n";
    $conn->query("CREATE TABLE `client_attorney_conversations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `assignment_id` int(11) NOT NULL,
        `client_id` int(11) NOT NULL,
        `attorney_id` int(11) NOT NULL,
        `conversation_status` enum('Active','Completed','Closed') DEFAULT 'Active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `assignment_id` (`assignment_id`),
        KEY `client_id` (`client_id`),
        KEY `attorney_id` (`attorney_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=1");
    echo "   ✓ Created client_attorney_conversations\n";
    
    // Create client_employee_conversations table
    echo "4. Creating client_employee_conversations table...\n";
    $conn->query("CREATE TABLE `client_employee_conversations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `request_form_id` int(11) NOT NULL,
        `client_id` int(11) NOT NULL,
        `employee_id` int(11) NOT NULL,
        `conversation_status` enum('Active','Completed','Closed') DEFAULT 'Active',
        `concern_identified` tinyint(1) DEFAULT 0,
        `concern_description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `request_form_id` (`request_form_id`),
        KEY `client_id` (`client_id`),
        KEY `employee_id` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=1");
    echo "   ✓ Created client_employee_conversations\n";
    
    // Create client_attorney_messages table
    echo "5. Creating client_attorney_messages table...\n";
    $conn->query("CREATE TABLE `client_attorney_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `conversation_id` int(11) NOT NULL,
        `sender_id` int(11) NOT NULL,
        `sender_type` enum('client','attorney') NOT NULL,
        `message` text NOT NULL,
        `is_read` tinyint(1) DEFAULT 0,
        `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `conversation_id` (`conversation_id`),
        KEY `sender_id` (`sender_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=1");
    echo "   ✓ Created client_attorney_messages\n";
    
    // Create client_employee_messages table
    echo "6. Creating client_employee_messages table...\n";
    $conn->query("CREATE TABLE `client_employee_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `conversation_id` int(11) NOT NULL,
        `sender_id` int(11) NOT NULL,
        `sender_type` enum('client','employee') NOT NULL,
        `message` text NOT NULL,
        `is_read` tinyint(1) DEFAULT 0,
        `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `conversation_id` (`conversation_id`),
        KEY `sender_id` (`sender_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=1");
    echo "   ✓ Created client_employee_messages\n";
    
    // Insert sample data
    echo "7. Inserting sample data...\n";
    $conn->query("INSERT INTO `client_attorney_assignments` (`id`, `client_id`, `employee_id`, `attorney_id`, `assigned_at`, `status`) VALUES
    (1, 134, 130, 20, '2025-01-01 10:00:00', 'Active')");
    
    $conn->query("INSERT INTO `client_attorney_conversations` (`id`, `assignment_id`, `client_id`, `attorney_id`, `conversation_status`, `created_at`, `updated_at`) VALUES
    (1, 1, 134, 20, 'Active', '2025-01-01 10:00:00', '2025-01-01 10:00:00')");
    
    $conn->query("INSERT INTO `client_employee_conversations` (`id`, `request_form_id`, `client_id`, `employee_id`, `conversation_status`, `concern_identified`, `concern_description`, `created_at`, `updated_at`) VALUES
    (1, 24, 134, 130, 'Active', 0, NULL, '2025-01-01 10:00:00', '2025-01-01 10:00:00')");
    
    echo "   ✓ Inserted sample data\n";
    
    echo "\n✅ Fresh database structure created successfully!\n";
    echo "📋 Tables created:\n";
    echo "   - client_attorney_assignments\n";
    echo "   - client_attorney_conversations\n";
    echo "   - client_employee_conversations\n";
    echo "   - client_attorney_messages\n";
    echo "   - client_employee_messages\n";
    echo "\n🎯 Ready for testing!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
