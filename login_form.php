<?php
session_start();
@include 'config.php';
require_once 'audit_logger.php';
require_once 'security_monitor.php';

// Prevent caching of login form
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is already logged in - redirect to appropriate dashboard
if (isset($_SESSION['admin_name']) && $_SESSION['user_type'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
} elseif (isset($_SESSION['attorney_name']) && $_SESSION['user_type'] === 'attorney') {
    header('Location: attorney_dashboard.php');
    exit();
} elseif (isset($_SESSION['employee_name']) && $_SESSION['user_type'] === 'employee') {
    header('Location: employee_dashboard.php');
    exit();
} elseif (isset($_SESSION['client_name']) && $_SESSION['user_type'] === 'client') {
    header('Location: client_dashboard.php');
    exit();
}

// Handle forgot password steps
if (isset($_POST['forgot_email_submit'])) {
    $email = mysqli_real_escape_string($conn, $_POST['forgot_email']);
    
    // Email validation - accepts any valid email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: login_form.php");
        exit();
    }
    
    // Check if user exists
    $select = "SELECT * FROM user_form WHERE email = ?";
    $stmt = mysqli_prepare($conn, $select);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Generate OTP
        require_once __DIR__ . '/vendor/autoload.php';
        $otp = rand(100000, 999999);
        $_SESSION['forgot_password_data'] = [
            'email' => $email,
            'otp' => $otp,
            'otp_expires' => time() + 300, // 5 minutes
            'step' => 'verify_otp'
        ];
        
        // Send OTP email
        require_once 'send_password_reset_otp.php';
        send_password_reset_otp($email, $otp);
        $_SESSION['show_forgot_modal'] = true;
    } else {
        $_SESSION['error'] = "No account found with this email address!";
        header("Location: login_form.php");
        exit();
    }
}

// Handle OTP verification
if (isset($_POST['forgot_otp_submit']) && isset($_SESSION['forgot_password_data'])) {
    $input_otp = $_POST['forgot_otp'] ?? '';
    $forgot_data = $_SESSION['forgot_password_data'];
    
    if (time() > $forgot_data['otp_expires']) {
        $_SESSION['error'] = 'OTP expired. Please try again.';
        unset($_SESSION['forgot_password_data']);
        unset($_SESSION['show_forgot_modal']);
    } elseif ($input_otp == $forgot_data['otp']) {
        // OTP verified, move to password reset step
        $_SESSION['forgot_password_data']['step'] = 'reset_password';
        $_SESSION['show_forgot_modal'] = true;
    } else {
        $_SESSION['error'] = 'Invalid OTP. Please check your email and try again.';
        $_SESSION['show_forgot_modal'] = true; // Keep modal open
    }
}

// Handle password reset
if (isset($_POST['forgot_reset_submit']) && isset($_SESSION['forgot_password_data'])) {
    $pass = $_POST['new_password'];
    $cpass = $_POST['confirm_password'];
    $email = $_SESSION['forgot_password_data']['email'];
    
    // Password requirements check
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\])[A-Za-z\d!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\]{8,}$/', $pass)) {
        $_SESSION['error'] = "Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one allowed special character (! @ # $ % ^ & * ( ) _ + - = { } [ ] : ; \" ' < > , . ? / ~ ` | \\).";
        header("Location: login_form.php");
        exit();
    }
    
    // Password match check
    if ($pass != $cpass) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: login_form.php");
        exit();
    }
    
    // Update password in database
    $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
    $update_query = "UPDATE user_form SET password = ?, login_attempts = 0, account_locked = 0, lockout_until = NULL WHERE email = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $email);
    
    if (mysqli_stmt_execute($stmt)) {
        unset($_SESSION['forgot_password_data']);
        unset($_SESSION['show_forgot_modal']);
        $_SESSION['success'] = 'Password reset successful! You can now login with your new password.';
        header('Location: login_form.php');
        exit();
    } else {
        $_SESSION['error'] = 'Password reset failed. Please try again.';
        header("Location: login_form.php");
        exit();
    }
}

// Handle closing forgot password modal
if (isset($_POST['close_forgot_modal'])) {
    unset($_SESSION['show_forgot_modal']);
    unset($_SESSION['forgot_password_data']);
    exit();
}

// Handle regular login
if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = $_POST['password'];

    // Check database connection
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Prepare the query to avoid SQL injection - include first_login field
    $select = "SELECT *, first_login FROM user_form WHERE email = ?";
    $stmt = mysqli_prepare($conn, $select);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Check if account is locked
        if ($row['account_locked'] == 1) {
            // Show password reset modal instead of old lockout message
            $_SESSION['show_account_locked_modal'] = true;
            $_SESSION['locked_email'] = $email;
            $_SESSION['error'] = "Account temporarily locked due to multiple failed login attempts. Please reset your password to regain access.";
            header("Location: login_form.php");
            exit();
        }

        if (password_verify($pass, $row['password'])) {
            // Reset login attempts on successful login
            $reset_attempts = "UPDATE user_form SET login_attempts = 0, last_failed_login = NULL, last_login = NOW() WHERE email = ?";
            $reset_stmt = mysqli_prepare($conn, $reset_attempts);
            mysqli_stmt_bind_param($reset_stmt, "s", $email);
            mysqli_stmt_execute($reset_stmt);

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_type'] = $row['user_type'];
            $_SESSION['last_activity'] = time();
            
            // AUDIT LOGGING: Log successful login
            logAuditAction($row['id'], $row['name'], $row['user_type'], 'User Login', 'Authentication', 'User logged in successfully');

            // Check if this is a first-time login for employee, attorney, or client
            if (($row['user_type'] == 'employee' || $row['user_type'] == 'attorney' || $row['user_type'] == 'client') && 
                isset($row['first_login']) && $row['first_login'] == 1) {
                
                // Set session flag to show first-time login modal on dashboard
                $_SESSION['show_first_login_modal'] = true;
                $_SESSION['first_login_user_id'] = $row['id'];
                $_SESSION['first_login_user_type'] = $row['user_type'];
                $_SESSION['first_login_user_name'] = $row['name'];
            }

            if ($row['user_type'] == 'admin') {
                $_SESSION['admin_name'] = $row['name'];
                header('Location: admin_dashboard.php');
                exit();
            } elseif ($row['user_type'] == 'attorney') {
                $_SESSION['attorney_name'] = $row['name'];
                header('Location: attorney_dashboard.php');
                exit();
            } elseif ($row['user_type'] == 'employee') {
                $_SESSION['employee_name'] = $row['name'];
                header('Location: employee_dashboard.php');
                exit();
            } else {
                $_SESSION['client_name'] = $row['name'];
                $_SESSION['fresh_login'] = true; // Flag to show data privacy modal
                header('Location: client_dashboard.php');
                exit();
            }
        } else {
            // Increment failed login attempts
            $attempts = $row['login_attempts'] + 1;
            $lockout_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            if ($attempts >= 4) {
                // Lock the account in database AND show password reset modal
                $lock_query = "UPDATE user_form SET login_attempts = ?, last_failed_login = NOW(), account_locked = 1, lockout_until = ? WHERE email = ?";
                $lock_stmt = mysqli_prepare($conn, $lock_query);
                mysqli_stmt_bind_param($lock_stmt, "iss", $attempts, $lockout_time, $email);
                mysqli_stmt_execute($lock_stmt);
                
                // Show password reset modal
                $_SESSION['show_account_locked_modal'] = true;
                $_SESSION['locked_email'] = $email;
                
                // Log the account lockout as HIGH PRIORITY security event
                global $auditLogger;
                $auditLogger->logAction(
                    0, // system user
                    'System', 
                    'system', 
                    'Account Locked - Password Reset Required', 
                    'Security', 
                    "Account locked for email: $email after $attempts failed attempts - Password reset required", 
                    'failed', 
                    'high' // HIGH PRIORITY!
                );
                
                $_SESSION['error'] = "Account temporarily locked due to multiple failed login attempts. Please reset your password to regain access.";
            } else {
                // Update failed attempts
                $update_query = "UPDATE user_form SET login_attempts = ?, last_failed_login = NOW() WHERE email = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "is", $attempts, $email);
                mysqli_stmt_execute($update_stmt);
                
                // Log failed login attempt as MEDIUM priority
                global $auditLogger;
                $auditLogger->logAction(
                    0, // system user
                    'System', 
                    'system', 
                    'Failed Login Attempt', 
                    'Security', 
                    "Failed login attempt for email: $email (Attempt $attempts/4)", 
                    'failed', 
                    'medium' // MEDIUM priority for failed attempts
                );
                
                $remaining_attempts = 4 - $attempts;
                $_SESSION['error'] = "Incorrect email or password! {$remaining_attempts} attempts remaining before account lockout.";
            }
            
            header("Location: login_form.php");
            exit();
        }
    } else {
        // Log attempt to login with non-existent account
        global $auditLogger;
        $auditLogger->logAction(
            0, // system user
            'System', 
            'system', 
            'Invalid Login Attempt', 
            'Security', 
            "Login attempt with non-existent email: $email", 
            'failed', 
            'medium' // MEDIUM priority for invalid accounts
        );
        
        $_SESSION['error'] = "Account does not exist!";
        header("Location: login_form.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background: #f5f5f5;
        }

        .left-container {
            width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            padding: 50px;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        }

        .title-container {
            display: flex;
            align-items: center;
            position: absolute;
            top: 20px;
            left: 30px;
        }

        .title-container img {
            width: 45px;
            height: 45px;
            margin-right: 8px;
        }

        .title {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 1px;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
        }

        .header-container img {
            width: 50px;
            height: 50px;
        }

        .law-office-title {
            text-align: center;
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            font-family: "Playfair Display", serif;
            letter-spacing: 1.8px;
            text-shadow: 0 3px 8px rgba(0, 0, 0, 0.5);
            line-height: 1.2;
        }

        .form-header {
            font-size: 28px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
            color: #ffffff;
        }

        .form-container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .form-container label {
            font-size: 14px;
            font-weight: 500;
            display: block;
            margin: 15px 0 5px;
            color: #ffffff;
            text-align: left;
        }

        .form-container input {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: none;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            background: transparent;
            color: #ffffff;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-container input:focus {
            border-bottom: 2px solid #ffffff;
        }

        .form-container input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-container i:hover {
            color: #ffffff;
        }

        .form-container .form-btn {
            background: #ffffff;
            color: #5D0E26;
            border: none;
            cursor: pointer;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .form-container .form-btn:hover {
            background: #f8f8f8;
            color: #8B1538;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .form-links {
            display: flex;
            justify-content: flex-start;
            margin-top: 10px;
        }

        .form-links a {
            font-size: 14px;
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: #f0f0f0;
        }

        .right-container {
            width: 55%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #5D0E26;
            text-align: center;
            padding: 50px;
            background: #ffffff;
            background-image: url('images/atty2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            backdrop-filter: blur(5px);
            position: relative;
        }

        .right-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/atty2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(3px);
            z-index: -1;
        }

        /* Professional Modal Alert System */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(93, 14, 38, 0.85);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }

        .modal-alert {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 0;
            max-width: 450px;
            width: 90%;
            position: relative;
            animation: modalSlideIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            overflow: hidden;
        }

        .modal-alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
        }

        .modal-header {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            padding: 25px 30px 20px;
            text-align: center;
            position: relative;
        }

        .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .modal-subtitle {
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
            margin: 0;
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-message {
            font-size: 18px;
            font-weight: 600;
            color: #5D0E26;
            margin: 0 0 12px 0;
            line-height: 1.4;
        }

        .modal-subtext {
            font-size: 14px;
            color: #666;
            margin: 0 0 25px 0;
            line-height: 1.5;
        }

        .modal-button {
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            border: none;
            padding: 14px 35px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
            min-width: 120px;
        }

        .modal-button:hover {
            background: linear-gradient(135deg, #8B1538, #5D0E26);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
        }

        .modal-button:active {
            transform: translateY(0);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Legacy error popup for backward compatibility */
        .error-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff6b6b;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            z-index: 1000;
            width: 90%;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translate(-50%, -20px);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }

        @keyframes slideInFromLeft {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .modal-slide-in {
            animation: slideInFromLeft 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .error-popup p {
            margin: 0;
            font-size: 14px;
        }

        .error-popup button {
            background: white;
            border: none;
            padding: 8px 15px;
            color: #ff6b6b;
            font-weight: 500;
            margin-top: 10px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .error-popup button:hover {
            background: #f0f0f0;
        }

        .register-box h1 {
            font-size: 48px;
            font-weight: 700;
            color: #5D0E26;
            margin-bottom: 20px;
            line-height: 1.3;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .mirror-shine {
            position: relative;
            display: inline-block;
            background: linear-gradient(
                90deg,
                #5D0E26 0%,
                #5D0E26 45%,
                #ffffff 50%,
                #5D0E26 55%,
                #5D0E26 100%
            );
            background-size: 200% 100%;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: mirrorShine 3s ease-in-out infinite;
        }

        @keyframes mirrorShine {
            0% {
                background-position: -100% 0;
            }
            100% {
                background-position: 100% 0;
            }
        }

        .register-btn {
            display: inline-block;
            background: linear-gradient(135deg, #5D0E26, #8B1538);
            color: white;
            text-decoration: none;
            padding: 18px 40px;
            font-size: 20px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.4);
        }

        .register-btn:hover {
            background: linear-gradient(135deg, #8B1538, #5D0E26);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.5);
        }

        @media (max-width: 1024px) {
            .left-container {
                width: 50%;
            }

            .right-container {
                width: 50%;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .left-container, .right-container {
                width: 100%;
                padding: 40px 20px;
            }

            .law-office-title {
                font-size: 38px;
            }

            .form-header {
                font-size: 24px;
            }

            .register-box h1 {
                font-size: 40px;
            }
        }

        @media (max-width: 480px) {
            .title {
                font-size: 20px;
            }

            .law-office-title {
                font-size: 32px;
            }

            .form-header {
                font-size: 22px;
            }

            .form-container input {
                font-size: 14px;
                padding: 10px 12px;
            }

            .form-container .form-btn {
                padding: 12px;
                font-size: 15px;
            }

            .register-box h1 {
                font-size: 32px;
            }

            .register-btn {
                padding: 16px 32px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['error']) && !isset($_SESSION['show_forgot_modal'])): ?>
        <div class="modal-overlay" id="errorModal">
            <div class="modal-alert">
                <div class="modal-header">
                    <h2 class="modal-title">Opiña Law Office</h2>
                    <p class="modal-subtitle">Authentication Notice</p>
                </div>
                <div class="modal-body">
                    <p class="modal-message"><?php echo $_SESSION['error']; ?></p>
                    <?php if (strpos($_SESSION['error'], 'Account does not exist') !== false): ?>
                        <p class="modal-subtext">Please register an account before logging in.</p>
                    <?php elseif (strpos($_SESSION['error'], 'Incorrect email or password') !== false): ?>
                        <p class="modal-subtext">Please check your credentials and try again.</p>
                    <?php elseif (strpos($_SESSION['error'], 'Account is locked') !== false): ?>
                        <p class="modal-subtext">Your account has been temporarily locked for security reasons.</p>
                    <?php elseif (strpos($_SESSION['error'], 'Access denied') !== false): ?>
                        <p class="modal-subtext">Please contact the administrator for assistance.</p>
                    <?php else: ?>
                        <p class="modal-subtext">Please try again or contact support if the problem persists.</p>
                    <?php endif; ?>
                    <button class="modal-button" onclick="closeModal()">OK</button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal-overlay" id="successModal">
            <div class="modal-alert">
                <div class="modal-header">
                    <h2 class="modal-title">Opiña Law Office</h2>
                    <p class="modal-subtitle">Success</p>
                </div>
                <div class="modal-body">
                    <p class="modal-message"><?php echo $_SESSION['success']; ?></p>
                    <p class="modal-subtext">You can now proceed with your login.</p>
                    <button class="modal-button" onclick="closeModal()">OK</button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="left-container">
        <div class="title-container">
            <img src="images/logo.jpg" alt="Logo">
            <div class="title">LawOffice.</div>
            <!-- Back to Home Button -->
            <a href="index.php" style="position: absolute; top: 80px; right: 30px; display: inline-block; background: rgba(255, 255, 255, 0.2); color: #ffffff; text-decoration: none; padding: 10px; border-radius: 50%; font-size: 16px; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.3); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.transform='translateY(0)'" title="Back to Home">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>

        <div class="header-container">
            <h1 class="law-office-title">Opiña Law<br>Office</h1>
            <img src="images/justice.png" alt="Attorney Icon">
        </div>

        <div class="form-container">
            <h2 class="form-header">Login</h2>

            <form action="" method="post">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required placeholder="Enter your email">

                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" name="password" id="password" required placeholder="Enter your password">
                    <i class="fas fa-eye" id="togglePassword"></i>
                </div>

                <div class="form-links">
                    <a href="#" onclick="showForgotPasswordModal(); return false;">Forgot Password?</a>
                </div>

                <input type="submit" name="submit" value="Login" class="form-btn">
            </form>
        </div>
    </div>

    <div class="right-container">
        <div class="register-box">
            <h1 class="mirror-shine">Don't have an account?</h1>
        </div>
        <a href="register_form.php" class="register-btn">Register Now</a>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            let passwordField = document.getElementById('password');
            let icon = this;
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Prevent spaces in email and password inputs
        ['email','password'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('keydown', function(e){ if (e.key === ' ') e.preventDefault(); });
            el.addEventListener('input', function(){ this.value = this.value.replace(/\s+/g,''); });
        });

        function closeModal() {
            const modals = document.querySelectorAll('.modal-overlay');
            modals.forEach(modal => {
                modal.style.display = 'none';
            });
        }

        // Legacy functions for backward compatibility
        function closePopup() {
            closeModal();
        }

        function closeSuccessPopup() {
            closeModal();
        }

        // Function to show validation modal for client-side errors
        function showValidationModal(title, message, subtext) {
            // Remove any existing validation modal
            const existingModal = document.getElementById('validationModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Create modal HTML
            const modalHTML = `
                <div class="modal-overlay" id="validationModal">
                    <div class="modal-alert">
                        <div class="modal-header">
                            <h2 class="modal-title">Opiña Law Office</h2>
                            <p class="modal-subtitle">${title}</p>
                        </div>
                        <div class="modal-body">
                            <p class="modal-message">${message}</p>
                            <p class="modal-subtext">${subtext}</p>
                            <button class="modal-button" onclick="closeModal()">OK</button>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        function showForgotPasswordModal() {
            const modal = document.getElementById('forgotPasswordModal');
            const modalContent = document.getElementById('modalContent');
            
            modal.style.display = 'flex';
            modalContent.classList.add('modal-slide-in');
            resetForgotPasswordModal();
            
            // Pre-fill email if coming from account locked modal
            <?php if (isset($_SESSION['locked_email'])): ?>
            const emailInput = document.querySelector('input[name="forgot_email"]');
            if (emailInput) {
                emailInput.value = '<?= htmlspecialchars($_SESSION['locked_email']) ?>';
            }
            <?php unset($_SESSION['locked_email']); ?>
            <?php endif; ?>
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'close_forgot_modal=1'
            });
        }

        function resetForgotPasswordModal() {
            // Show step 1 (email entry)
            document.getElementById('forgotStep1').style.display = 'block';
            document.getElementById('forgotStep2').style.display = 'none';
            document.getElementById('forgotStep3').style.display = 'none';
            
            // Clear all form fields
            document.querySelectorAll('#forgotPasswordModal input').forEach(input => input.value = '');
        }

        function toggleForgotPassword(fieldId, iconId) {
            let passwordField = document.getElementById(fieldId);
            let icon = document.getElementById(iconId);
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Account Locked Modal Functions
        function showAccountLockedModal() {
            const modal = document.getElementById('accountLockedModal');
            modal.style.display = 'flex';
        }

        function closeAccountLockedModal() {
            const modal = document.getElementById('accountLockedModal');
            modal.style.display = 'none';
        }
    </script>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(93, 14, 38, 0.8); display: none; align-items: center; justify-content: center; z-index: 10000;">
        <div id="modalContent" style="background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); padding: 40px 35px; max-width: 480px; width: 90%; position: relative;">
            
            <!-- Modal Header -->
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="color: #5D0E26; margin-bottom: 15px; font-size: 32px; font-weight: 700; font-family: 'Playfair Display', serif; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">Opiña Law Office</h2>
                <h3 id="modalTitle" style="color: #5D0E26; margin-bottom: 20px; font-size: 22px; font-weight: 600;">Reset Password</h3>
            </div>

            <!-- Step 1: Email Entry -->
            <div id="forgotStep1" style="display: block;">
                <div style="color: #666; margin-bottom: 25px; text-align: center; font-size: 15px; line-height: 1.5;">
                    Enter your registered email address to receive a verification code.
                </div>
                
                <form method="post" style="margin-bottom: 20px;">
                    <div style="margin-bottom: 25px;">
                        <input type="email" name="forgot_email" placeholder="Enter your email address" required 
                               pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$" title="Email must be a valid @gmail.com address only"
                               style="width: 100%; padding: 15px; font-size: 16px; border: 2px solid #e0e0e0; border-radius: 8px; outline: none; transition: all 0.3s ease; background: #f9f9f9;"
                               onfocus="this.style.borderColor='#5D0E26'; this.style.background='#fff';"
                               onblur="this.style.borderColor='#e0e0e0'; this.style.background='#f9f9f9';">
                    </div>
                    <button type="submit" name="forgot_email_submit" 
                            style="width: 100%; background: linear-gradient(135deg, #5D0E26, #8B1538); color: #fff; border: none; padding: 16px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.4);"
                            onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(93, 14, 38, 0.5)';"
                            onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(93, 14, 38, 0.4)';">
                        Send Verification Code
                    </button>
                </form>
            </div>

            <!-- Step 2: OTP Verification -->
            <div id="forgotStep2" style="display: none;">
                <div style="color: #666; margin-bottom: 25px; text-align: center; font-size: 15px; line-height: 1.5;">
                    Enter the 6-digit verification code sent to<br><strong id="forgotEmailDisplay" style="color: #5D0E26;"></strong>
                </div>
                
                <?php if (isset($_SESSION['error']) && isset($_SESSION['show_forgot_modal'])): ?>
                    <div style="background: #ff6b6b; color: white; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);">
                        <?php echo $_SESSION['error']; ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <form method="post" style="margin-bottom: 20px;">
                    <div style="margin-bottom: 25px;">
                        <input type="text" name="forgot_otp" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit code" required autofocus 
                               style="width: 100%; padding: 15px; font-size: 18px; border: 2px solid #e0e0e0; border-radius: 8px; outline: none; transition: all 0.3s ease; text-align: center; letter-spacing: 3px; font-weight: 600; background: #f9f9f9;"
                               onfocus="this.style.borderColor='#5D0E26'; this.style.background='#fff';"
                               onblur="this.style.borderColor='#e0e0e0'; this.style.background='#f9f9f9';">
                    </div>
                    <button type="submit" name="forgot_otp_submit" 
                            style="width: 100%; background: linear-gradient(135deg, #5D0E26, #8B1538); color: #fff; border: none; padding: 16px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.4);"
                            onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(93, 14, 38, 0.5)';"
                            onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(93, 14, 38, 0.4)';">
                        Verify Code
                    </button>
                </form>
            </div>

            <!-- Step 3: Password Reset -->
            <div id="forgotStep3" style="display: none;">
                <div style="color: #666; margin-bottom: 25px; text-align: center; font-size: 15px; line-height: 1.5;">
                    Create a new secure password for your account<br><strong id="forgotEmailDisplay2" style="color: #5D0E26;"></strong>
                </div>
                
                <form method="post" style="margin-bottom: 20px;" onsubmit="return validateForgotPasswordForm()">
                    <div style="margin-bottom: 20px; position: relative;">
                        <input type="password" name="new_password" id="forgotNewPassword" placeholder="Enter new password" required 
                               style="width: 100%; padding: 15px 45px 15px 15px; font-size: 16px; border: 2px solid #e0e0e0; border-radius: 8px; outline: none; transition: all 0.3s ease; background: #f9f9f9;"
                               onfocus="this.style.borderColor='#5D0E26'; this.style.background='#fff';"
                               onblur="this.style.borderColor='#e0e0e0'; this.style.background='#f9f9f9';">
                        <i class="fas fa-eye" id="toggleForgotNewPassword" onclick="toggleForgotPassword('forgotNewPassword', 'toggleForgotNewPassword')" 
                           style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666; cursor: pointer;"></i>
                    </div>
                    
                    <div style="margin-bottom: 20px; position: relative;">
                        <input type="password" name="confirm_password" id="forgotConfirmPassword" placeholder="Confirm new password" required 
                               style="width: 100%; padding: 15px 45px 15px 15px; font-size: 16px; border: 2px solid #e0e0e0; border-radius: 8px; outline: none; transition: all 0.3s ease; background: #f9f9f9;"
                               onfocus="this.style.borderColor='#5D0E26'; this.style.background='#fff';"
                               onblur="this.style.borderColor='#e0e0e0'; this.style.background='#f9f9f9';">
                        <i class="fas fa-eye" id="toggleForgotConfirmPassword" onclick="toggleForgotPassword('forgotConfirmPassword', 'toggleForgotConfirmPassword')" 
                           style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666; cursor: pointer;"></i>
                    </div>
                    
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 12px; color: #856404;">
                        <strong>Password Requirements:</strong><br>
                        • At least 8 characters<br>
                        • Include uppercase and lowercase letters<br>
                        • Include at least one number<br>
                        • Include at least one special character<br>
                        • Allowed: ! @ # $ % ^ & * ( ) _ + - = { } [ ] : ; " ' < > , . ? / ~ ` | \
                    </div>
                    
                    <button type="submit" name="forgot_reset_submit" 
                            style="width: 100%; background: linear-gradient(135deg, #5D0E26, #8B1538); color: #fff; border: none; padding: 16px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.4);"
                            onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(93, 14, 38, 0.5)';"
                            onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(93, 14, 38, 0.4)';">
                        Reset Password
                    </button>
                </form>
            </div>

            <!-- Close Button -->
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="closeForgotPasswordModal()" style="background: none; border: none; color: #5D0E26; text-decoration: none; font-size: 14px; font-weight: 500; cursor: pointer; padding: 5px;">
                    ← Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Account Locked Modal -->
    <div id="accountLockedModal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(93, 14, 38, 0.8); display: none; align-items: center; justify-content: center; z-index: 10001;">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); padding: 40px 35px; max-width: 480px; width: 90%; position: relative; text-align: center;">
            
            <!-- Modal Header -->
            <div style="margin-bottom: 30px;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #dc3545, #c82333); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-lock" style="color: white; font-size: 32px;"></i>
                </div>
                <h2 style="color: #5D0E26; margin-bottom: 15px; font-size: 28px; font-weight: 700;">Account Temporarily Locked</h2>
                <p style="color: #666; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
                    Your account has been temporarily locked due to multiple failed login attempts.<br>
                    <strong>Please reset your password to regain access.</strong>
                </p>
            </div>

            <!-- Modal Actions -->
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button onclick="showForgotPasswordModal(); closeAccountLockedModal();" 
                        style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);">
                    <i class="fas fa-key" style="margin-right: 8px;"></i>
                    Reset Password
                </button>
                <button onclick="closeAccountLockedModal();" 
                        style="background: #6c757d; color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                    <i class="fas fa-times" style="margin-right: 8px;"></i>
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript to handle modal steps -->
    <?php if (isset($_SESSION['show_account_locked_modal'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showAccountLockedModal();
        });
    </script>
    <?php unset($_SESSION['show_account_locked_modal']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['show_forgot_modal']) && isset($_SESSION['forgot_password_data'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('forgotPasswordModal');
            const modalContent = document.getElementById('modalContent');
            const step = '<?= $_SESSION['forgot_password_data']['step'] ?>';
            const email = '<?= htmlspecialchars($_SESSION['forgot_password_data']['email']) ?>';
            
            modal.style.display = 'flex';
            modalContent.classList.add('modal-slide-in');
            
            if (step === 'verify_otp') {
                // Show OTP verification step
                document.getElementById('forgotStep1').style.display = 'none';
                document.getElementById('forgotStep2').style.display = 'block';
                document.getElementById('forgotStep3').style.display = 'none';
                document.getElementById('modalTitle').textContent = 'Verify Your Email';
                document.getElementById('forgotEmailDisplay').textContent = email;
                
                // Focus on OTP input after animation
                setTimeout(() => {
                    const otpInput = document.querySelector('input[name="forgot_otp"]');
                    if (otpInput) otpInput.focus();
                }, 600); // Wait for slide animation to complete
                
            } else if (step === 'reset_password') {
                // Show password reset step
                document.getElementById('forgotStep1').style.display = 'none';
                document.getElementById('forgotStep2').style.display = 'none';
                document.getElementById('forgotStep3').style.display = 'block';
                document.getElementById('modalTitle').textContent = 'Create New Password';
                document.getElementById('forgotEmailDisplay2').textContent = email;
                
                // Focus on new password input after animation
                setTimeout(() => {
                    const passwordInput = document.getElementById('forgotNewPassword');
                    if (passwordInput) passwordInput.focus();
                }, 600); // Wait for slide animation to complete
            }
        });
        
        function validateForgotPasswordForm() {
            var pass = document.getElementById('forgotNewPassword').value;
            var cpass = document.getElementById('forgotConfirmPassword').value;
            var requirements = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\])[A-Za-z\d!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\]{8,}$/;
            if (!requirements.test(pass)) {
                showValidationModal('Password Requirements Not Met', 'Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one allowed special character (! @ # $ % ^ & * ( ) _ + - = { } [ ] : ; " \' < > , . ? / ~ ` | \\ ).', 'Please ensure your password meets all requirements.');
                return false;
            }
            if (pass !== cpass) {
                showValidationModal('Password Mismatch', 'Confirm password does not match the password.', 'Please ensure both passwords are identical.');
                return false;
            }
            return true;
        }
    </script>
    <?php endif; ?>

    <script src="https://kit.fontawesome.com/cc86d7b31d.js" crossorigin="anonymous"></script>
    
    <script>
        // Prevent back button access to login form when already logged in
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache, check if user is logged in
                fetch('check_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'check_login_status'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.logged_in) {
                        // User is logged in, redirect to appropriate dashboard
                        window.location.href = data.dashboard_url;
                    }
                })
                .catch(error => {
                    console.log('Session check failed:', error);
                });
            }
        });
    </script>
</body>
</html>
