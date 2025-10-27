<?php
/**
 * Database Migration for Enhanced Case Modal System
 * Adds missing columns and tables for case-specific document management
 */

require_once 'config.php';

echo "<h2>Database Migration for Enhanced Case Modal System</h2>";

try {
    // 1. Add case_id column to attorney_documents table if it doesn't exist
    echo "<p>1. Adding case_id column to attorney_documents table...</p>";
    $result = $conn->query("SHOW COLUMNS FROM attorney_documents LIKE 'case_id'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE attorney_documents ADD COLUMN case_id INT(11) DEFAULT NULL AFTER uploaded_by");
        $conn->query("ALTER TABLE attorney_documents ADD COLUMN description TEXT DEFAULT NULL AFTER category");
        echo "<span style='color: green;'>✓ Added case_id and description columns to attorney_documents</span><br>";
    } else {
        echo "<span style='color: blue;'>→ case_id column already exists in attorney_documents</span><br>";
    }

    // 2. Create admin_documents table if it doesn't exist
    echo "<p>2. Creating admin_documents table...</p>";
    $result = $conn->query("SHOW TABLES LIKE 'admin_documents'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE admin_documents (
            id INT(11) NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            uploaded_by INT(11) NOT NULL,
            case_id INT(11) DEFAULT NULL,
            file_size BIGINT(20) DEFAULT NULL,
            file_type VARCHAR(50) DEFAULT NULL,
            upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            KEY idx_uploaded_by (uploaded_by),
            KEY idx_case_id (case_id),
            KEY idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        echo "<span style='color: green;'>✓ Created admin_documents table</span><br>";
    } else {
        echo "<span style='color: blue;'>→ admin_documents table already exists</span><br>";
    }

    // 3. Create client_documents table if it doesn't exist
    echo "<p>3. Creating client_documents table...</p>";
    $result = $conn->query("SHOW TABLES LIKE 'client_documents'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE client_documents (
            id INT(11) NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            uploaded_by INT(11) NOT NULL,
            case_id INT(11) DEFAULT NULL,
            file_size BIGINT(20) DEFAULT NULL,
            file_type VARCHAR(50) DEFAULT NULL,
            upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            KEY idx_uploaded_by (uploaded_by),
            KEY idx_case_id (case_id),
            KEY idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        echo "<span style='color: green;'>✓ Created client_documents table</span><br>";
    } else {
        echo "<span style='color: blue;'>→ client_documents table already exists</span><br>";
    }

    // 4. Add indexes to attorney_documents for better performance
    echo "<p>4. Adding indexes to attorney_documents...</p>";
    $indexes = [
        "ALTER TABLE attorney_documents ADD INDEX idx_case_id (case_id)",
        "ALTER TABLE attorney_documents ADD INDEX idx_category (category)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $conn->query($index);
        } catch (Exception $e) {
            // Index might already exist, ignore error
        }
    }
    echo "<span style='color: green;'>✓ Added indexes to attorney_documents</span><br>";

    // 5. Create attorney_document_activity table if it doesn't exist
    echo "<p>5. Creating attorney_document_activity table...</p>";
    $result = $conn->query("SHOW TABLES LIKE 'attorney_document_activity'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE attorney_document_activity (
            id INT(11) NOT NULL AUTO_INCREMENT,
            document_id INT(11) NOT NULL,
            action VARCHAR(50) NOT NULL,
            user_id INT(11) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            activity_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            KEY idx_document_id (document_id),
            KEY idx_user_id (user_id),
            KEY idx_activity_date (activity_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        echo "<span style='color: green;'>✓ Created attorney_document_activity table</span><br>";
    } else {
        echo "<span style='color: blue;'>→ attorney_document_activity table already exists</span><br>";
    }

    echo "<h3 style='color: green;'>✓ Database migration completed successfully!</h3>";
    echo "<p>The enhanced case modal system is now ready to use.</p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Migration failed:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}

$conn->close();
?>
