<?php
require_once 'config.php';

echo "=== CURRENT ATTORNEYS IN DATABASE ===\n\n";

$stmt = $conn->prepare("SELECT id, name, user_type FROM user_form WHERE user_type IN ('attorney', 'admin') ORDER BY id ASC");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | Name: '{$row['name']}' | Type: {$row['user_type']}\n";
}

echo "\n=== CURRENT EVENTS WITH ATTORNEY NAMES ===\n\n";

$stmt = $conn->prepare("SELECT DISTINCT uf.name as attorney_name, uf.user_type FROM case_schedules cs LEFT JOIN user_form uf ON cs.attorney_id = uf.id WHERE cs.attorney_id IS NOT NULL ORDER BY uf.name");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "Attorney: '{$row['attorney_name']}' | Type: {$row['user_type']}\n";
}
?>
