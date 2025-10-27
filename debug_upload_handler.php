<?php
// Test the upload handler directly
require_once 'session_manager.php';
require_once 'config.php';

echo "Testing enhanced_document_upload.php...\n\n";

// Simulate POST request
$_POST['case_id'] = '8';
$_FILES['documents'] = [
    'name' => ['test.pdf'],
    'type' => ['application/pdf'],
    'size' => [1024],
    'tmp_name' => ['/tmp/test'],
    'error' => [0]
];

// Simulate session
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';

echo "POST data: " . json_encode($_POST) . "\n";
echo "FILES data: " . json_encode($_FILES) . "\n";
echo "Session data: " . json_encode($_SESSION) . "\n\n";

// Test if files exist
$files_to_check = [
    'enhanced_document_upload.php',
    'document_upload_permissions.php',
    'session_manager.php',
    'config.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

echo "\n";

// Test database connection
try {
    $result = $conn->query("SELECT 1");
    echo "✅ Database connection works\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Test case_documents table
try {
    $result = $conn->query("SELECT COUNT(*) FROM case_documents");
    echo "✅ case_documents table accessible\n";
} catch (Exception $e) {
    echo "❌ case_documents table error: " . $e->getMessage() . "\n";
}
?>
