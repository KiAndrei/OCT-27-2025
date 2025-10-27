<?php
require_once 'config.php';

echo "Setting up document upload database tables...\n\n";

try {
    // Create attorney_documents table
    $sql = "CREATE TABLE IF NOT EXISTS attorney_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        category VARCHAR(100) NOT NULL,
        uploaded_by INT NOT NULL,
        case_id INT NOT NULL,
        file_size INT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        description TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES user_form(id),
        FOREIGN KEY (case_id) REFERENCES attorney_cases(id)
    )";
    
    if ($conn->query($sql)) {
        echo "✅ attorney_documents table created successfully\n";
    } else {
        echo "❌ Error creating attorney_documents table: " . $conn->error . "\n";
    }
    
    // Create admin_documents table
    $sql = "CREATE TABLE IF NOT EXISTS admin_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        category VARCHAR(100) NOT NULL,
        uploaded_by INT NOT NULL,
        case_id INT NOT NULL,
        file_size INT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        description TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES user_form(id),
        FOREIGN KEY (case_id) REFERENCES attorney_cases(id)
    )";
    
    if ($conn->query($sql)) {
        echo "✅ admin_documents table created successfully\n";
    } else {
        echo "❌ Error creating admin_documents table: " . $conn->error . "\n";
    }
    
    // Create uploads directories
    $directories = ['uploads', 'uploads/attorney', 'uploads/admin', 'uploads/client'];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0777, true)) {
                echo "✅ Created directory: $dir\n";
            } else {
                echo "❌ Failed to create directory: $dir\n";
            }
        } else {
            echo "✅ Directory already exists: $dir\n";
        }
    }
    
    echo "\n🎉 Database setup complete!\n";
    echo "You can now test the upload functionality.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
