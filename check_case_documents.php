<?php
require_once 'config.php';

echo "Checking case_documents table...\n\n";

// Check if case_documents table exists
$result = $conn->query('SHOW TABLES LIKE "case_documents"');
if ($result->num_rows > 0) {
    echo "✅ case_documents table exists\n";
    
    // Check table structure
    $result = $conn->query('DESCRIBE case_documents');
    echo "Table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "❌ case_documents table missing\n";
    echo "Creating case_documents table...\n";
    
    $sql = "CREATE TABLE case_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        category VARCHAR(100) NOT NULL,
        uploaded_by INT NOT NULL,
        file_size INT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        description TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (case_id) REFERENCES attorney_cases(id),
        FOREIGN KEY (uploaded_by) REFERENCES user_form(id)
    )";
    
    if ($conn->query($sql)) {
        echo "✅ case_documents table created successfully\n";
    } else {
        echo "❌ Error creating case_documents table: " . $conn->error . "\n";
    }
}

// Check if get_case_documents.php exists
if (file_exists('get_case_documents.php')) {
    echo "✅ get_case_documents.php exists\n";
} else {
    echo "❌ get_case_documents.php missing\n";
}
?>
