<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Update profile if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $bio = $_POST['bio'];
    
    // Handle profile picture upload
    $profilePic = $user['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profile_pics/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $fileName = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExt;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadPath)) {
            // Delete old profile pic if it exists and isn't the default
            if ($profilePic && $profilePic !== 'images/default_profile.jpg' && file_exists($profilePic)) {
                unlink($profilePic);
            }
            $profilePic = $uploadPath;
        }
    }
    
    // Update user in database
    $stmt = $pdo->prepare("UPDATE users SET username = ?, bio = ?, profile_pic = ? WHERE user_id = ?");
    $stmt->execute([$username, $bio, $profilePic, $_SESSION['user_id']]);
    
    // Update session username
    $_SESSION['username'] = $username;
    
    $success = "Profile updated successfully!";
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($user['profile_pic'] ?? 'images/default_profile.jpg') ?>" alt="Profile Picture" class="profile-pic">
            <div class="profile-info">
                <h2><?= htmlspecialchars($user['username']) ?></h2>
                <p><?= htmlspecialchars($user['email']) ?></p>
                <div class="eco-credits">Eco Credits: <?= $user['eco_credits'] ?></div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" style="margin-top: 30px;">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" class="form-control" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="profile_pic">Profile Picture</label>
                <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
            </div>
            
            <button type="submit" class="btn">Update Profile</button>
        </form>
        
        <div style="margin-top: 40px;">
            <h3>Account Settings</h3>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <a href="change_password.php" class="btn btn-outline">Change Password</a>
                <a href="delete_account.php" class="btn btn-outline" style="background-color: #f44336; color: white;">Delete Account</a>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>