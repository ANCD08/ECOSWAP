<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    $errors = [];
    
    if (empty($email) || empty($username) || empty($password) || empty($confirmPassword)) {
        $errors[] = "All fields are required";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Check if email or username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $errors[] = "Email or username already exists";
    }
    
    if (empty($errors)) {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$email, $username, $passwordHash]);
        
        // Get new user ID
        $userId = $pdo->lastInsertId();
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        
        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <div class="card" style="max-width: 500px; margin: 50px auto;">
            <div class="card-body">
                <h2>Create Your Account</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form id="signupForm" method="POST">
                    <div class="form-group">
                        <label for="signupEmail">Email</label>
                        <input type="email" id="signupEmail" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupUsername">Username</label>
                        <input type="text" id="signupUsername" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signupPassword">Password</label>
                        <div style="display: flex;">
                            <input type="password" id="signupPassword" name="password" class="form-control" required>
                            <button type="button" class="toggle-password" style="margin-left: 5px;">👁️</button>
                        </div>
                        <small>Must be at least 8 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <div style="display: flex;">
                            <input type="password" id="confirmPassword" name="confirm_password" class="form-control" required>
                            <button type="button" class="toggle-password" style="margin-left: 5px;">👁️</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Sign Up</button>
                </form>
                
                <p style="margin-top: 20px;">Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    <script src="auth.js"></script>
</body>
</html>