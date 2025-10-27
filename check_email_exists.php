<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        echo json_encode(['exists' => false, 'message' => '']);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['exists' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT name FROM user_form WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode([
            'exists' => true, 
            'message' => 'Email already exists',
            'user_name' => $user['name']
        ]);
    } else {
        echo json_encode(['exists' => false, 'message' => 'Email is available']);
    }
} else {
    echo json_encode(['exists' => false, 'message' => 'Invalid request']);
}
?>
