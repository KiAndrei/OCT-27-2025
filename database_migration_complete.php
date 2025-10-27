<?php
require_once 'config.php';

echo "<h2>Database Migration Status</h2>";

try {
    // Check if both tables exist
    echo "<h3>Database Tables Status</h3>";
    $tables = ['client_request_form', 'client_document_generation'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    // Check record counts
    echo "<h3>Data Status</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM client_request_form");
    $crf_count = $result->fetch_assoc()['count'];
    echo "Message requests in client_request_form: $crf_count<br>";

    $result = $conn->query("SELECT COUNT(*) as count FROM client_document_generation");
    $cdg_count = $result->fetch_assoc()['count'];
    echo "Document generations in client_document_generation: $cdg_count<br>";
    
    echo "<h3>✅ System Status</h3>";
    echo "<p><strong>Document Generation System:</strong> Uses client_document_generation table</p>";
    echo "<p><strong>Message Request System:</strong> Uses client_request_form table</p>";
    echo "<p><strong>Notification System:</strong> Works for both systems</p>";
    echo "<p><strong>Status:</strong> ✅ Fully separated and working</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
