<?php
require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';
$admin_id = $_SESSION['user_id'];

// Fetch all cases with client and attorney information
$cases = [];
$sql = "SELECT ac.*, 
        c.name as client_name, 
        a.name as attorney_name,
        ac.created_at as date_filed,
        ac.attorney_id
        FROM attorney_cases ac 
        LEFT JOIN user_form c ON ac.client_id = c.id 
        LEFT JOIN user_form a ON ac.attorney_id = a.id 
        ORDER BY ac.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
}

echo "<h1>Debug Admin Cases Array</h1>";
echo "<h2>Admin ID: " . $admin_id . "</h2>";
echo "<h2>Cases Count: " . count($cases) . "</h2>";

echo "<h3>Raw Cases Array:</h3>";
echo "<pre>";
print_r($cases);
echo "</pre>";

echo "<h3>JSON Encoded Cases:</h3>";
echo "<pre>";
echo json_encode($cases, JSON_PRETTY_PRINT);
echo "</pre>";

echo "<h3>Individual Case Analysis:</h3>";
foreach ($cases as $index => $case) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<h4>Case #" . ($index + 1) . " (ID: " . $case['id'] . ")</h4>";
    echo "<p><strong>Title:</strong> " . htmlspecialchars($case['title']) . "</p>";
    echo "<p><strong>Attorney ID:</strong> " . ($case['attorney_id'] ?? 'NULL') . "</p>";
    echo "<p><strong>Attorney Name:</strong> " . htmlspecialchars($case['attorney_name'] ?? 'NULL') . "</p>";
    echo "<p><strong>Client Name:</strong> " . htmlspecialchars($case['client_name'] ?? 'NULL') . "</p>";
    echo "<p><strong>Assigned to Admin:</strong> " . (($case['attorney_id'] == $admin_id) ? 'YES' : 'NO') . "</p>";
    echo "</div>";
}
?>
