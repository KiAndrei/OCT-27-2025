<?php
require_once 'config.php';

try {
    // Create announcements table
    $sql = "CREATE TABLE IF NOT EXISTS `announcements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `description` text NOT NULL,
        `image_path` varchar(500) NOT NULL,
        `created_by` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `created_by` (`created_by`),
        FOREIGN KEY (`created_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql)) {
        echo "Announcements table created successfully!<br>";
    } else {
        echo "Error creating announcements table: " . $conn->error . "<br>";
    }
    
    // Create uploads/announcements directory
    $upload_dir = 'uploads/announcements/';
    if (!file_exists($upload_dir)) {
        if (mkdir($upload_dir, 0755, true)) {
            echo "Uploads directory created successfully!<br>";
        } else {
            echo "Error creating uploads directory<br>";
        }
    } else {
        echo "Uploads directory already exists<br>";
    }
    
    echo "<br><a href='employee_dashboard.php'>Go to Employee Dashboard</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
