<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['pending_registration'])) {
    header('Location: register_form.php');
    exit();
}
$pending = $_SESSION['pending_registration'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = $_POST['otp'] ?? '';
    if (time() > $pending['otp_expires']) {
        $error = 'OTP expired. Please register again.';
        unset($_SESSION['pending_registration']);
    } elseif ($input_otp == $pending['otp']) {
        // Insert user
        $hashed_password = password_hash($pending['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO user_form(name, email, phone_number, password, user_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $pending['name'], $pending['email'], $pending['phone'], $hashed_password, $pending['user_type']);
        if ($stmt->execute()) {
            unset($_SESSION['pending_registration']);
            echo '<script>alert("Registration successful! You can now login."); window.location="login_form.php";</script>';
            exit();
        } else {
            $error = 'Registration failed. Please try again.';
        }
    } else {
        $error = 'Invalid OTP. Please check your email and try again.';
    }
}
?>
<?php if ($pending): ?>
<div class="otp-modal" id="otpModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(44,0,0,0.18);display:flex;align-items:center;justify-content:center;z-index: 9999;">
    <div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);padding:36px 32px;max-width:450px;width:100%;position:relative;">
        <div style="text-align:center;margin-bottom:30px;">
            <h2 style="color:#800000;margin-bottom:10px;font-size:28px;font-weight:600;">Opiña Law Office</h2>
            <h3 style="color:#2c3e50;margin-bottom:20px;font-size:20px;font-weight:500;">Verify Your Email</h3>
        </div>
        <div style="color:#555;margin-bottom:20px;text-align:center;font-size:14px;">
            Enter the 6-digit OTP sent to <b><?= htmlspecialchars($pending['email']) ?></b>
        </div>
        <?php if ($error): ?>
        <div style="color:#e74c3c;margin-bottom:15px;text-align:center;font-size:13px;background:#ffe6e6;padding:10px;border-radius:6px;border-left:4px solid #e74c3c;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <form method="post">
            <div style="margin-bottom:20px;">
                <input type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="Enter OTP" required autofocus style="width:100%;padding:12px 15px;font-size:15px;border:2px solid #e0e0e0;border-radius:8px;outline:none;transition:all 0.3s ease;text-align:center;letter-spacing:2px;font-weight:600;">
            </div>
            <button type="submit" name="verify_otp" style="width:100%;background:linear-gradient(90deg, #800000 60%, #a94442 100%);color:#fff;border:none;padding:14px;border-radius:8px;font-size:16px;font-weight:500;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                Verify OTP
            </button>
        </form>
        <div style="text-align:center;margin-top:20px;">
            <a href="register_form.php" style="color:#800000;text-decoration:none;font-size:14px;font-weight:500;">Back to Registration</a>
        </div>
    </div>
</div>
<?php endif; ?>
</body>
</html> 