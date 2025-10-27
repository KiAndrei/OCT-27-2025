<?php
require_once 'config.php';

echo "=== Updating Attorney Names with 'Atty.' Prefix ===\n\n";

// Get all attorney accounts (both attorney and admin_attorney)
$stmt = $conn->prepare("SELECT id, name, user_type FROM user_form WHERE user_type IN ('attorney', 'admin_attorney') ORDER BY id ASC");
$stmt->execute();
$result = $stmt->get_result();

$updated_count = 0;
$skipped_count = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $current_name = $row['name'];
    $user_type = $row['user_type'];
    
    // Check if name already has "Atty." prefix
    if (stripos($current_name, 'Atty.') === 0) {
        echo "Skipping (already has prefix): ID {$id} | Name: '{$current_name}'\n";
        $skipped_count++;
        continue;
    }
    
    // Add "Atty." prefix
    $new_name = 'Atty. ' . $current_name;
    
    // Update in database
    $update_stmt = $conn->prepare("UPDATE user_form SET name = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_name, $id);
    
    if ($update_stmt->execute()) {
        echo "Updated: ID {$id} | Old: '{$current_name}' | New: '{$new_name}'\n";
        $updated_count++;
    } else {
        echo "ERROR updating ID {$id}: " . $update_stmt->error . "\n";
    }
    
    $update_stmt->close();
}

echo "\n=== Summary ===\n";
echo "Total attorneys found: " . ($updated_count + $skipped_count) . "\n";
echo "Updated: {$updated_count}\n";
echo "Skipped (already had prefix): {$skipped_count}\n";
echo "\nDone!\n";

$stmt->close();
?>

