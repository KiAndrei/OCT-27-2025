<?php
require_once 'config.php';

echo "Checking database tables...\n";

// Check attorney_documents table
$result = $conn->query('SHOW TABLES LIKE "attorney_documents"');
if ($result->num_rows > 0) {
    echo "✅ attorney_documents table exists\n";
    
    // Check table structure
    $result = $conn->query('DESCRIBE attorney_documents');
    echo "Table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "❌ attorney_documents table missing\n";
}

// Check admin_documents table
$result = $conn->query('SHOW TABLES LIKE "admin_documents"');
if ($result->num_rows > 0) {
    echo "✅ admin_documents table exists\n";
} else {
    echo "❌ admin_documents table missing\n";
}

// Check uploads directory
if (is_dir('uploads')) {
    echo "✅ uploads directory exists\n";
} else {
    echo "❌ uploads directory missing\n";
}

if (is_dir('uploads/attorney')) {
    echo "✅ uploads/attorney directory exists\n";
} else {
    echo "❌ uploads/attorney directory missing\n";
}

if (is_dir('uploads/admin')) {
    echo "✅ uploads/admin directory exists\n";
} else {
    echo "❌ uploads/admin directory missing\n";
}
?>
