<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $condition = $_POST['condition'];
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($_FILES['images']['name'][0])) {
        $errors[] = "At least one image is required";
    }
    
    if (empty($errors)) {
        // Insert item into database
        $stmt = $pdo->prepare("INSERT INTO items (user_id, title, description, category, item_condition) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $title, $description, $category, $condition]);
        $itemId = $pdo->lastInsertId();
        
        // Handle image uploads
        $uploadDir = 'uploads/item_images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $primarySet = false;
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileExt = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                $fileName = 'item_' . $itemId . '_' . uniqid() . '.' . $fileExt;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $uploadPath)) {
                    // Insert image into database
                    $isPrimary = !$primarySet;
                    $primarySet = $primarySet || $isPrimary;
                    
                    $stmt = $pdo->prepare("INSERT INTO item_images (item_id, image_path, is_primary) VALUES (?, ?, ?)");
                    $stmt->execute([$itemId, $uploadPath, $isPrimary]);
                }
            }
        }
        
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h1>Add New Item for Swap</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>Item added successfully!</p>
                <p><a href="item.php?id=<?= $itemId ?>" class="btn">View Item</a> or <a href="add_item.php" class="btn btn-outline">Add Another</a></p>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="card" style="padding: 20px; margin-top: 20px;">
                <div class="form-group">
                    <label for="title">Item Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="5" required></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <option value="clothing">Clothing</option>
                            <option value="books">Books</option>
                            <option value="electronics">Electronics</option>
                            <option value="furniture">Furniture</option>
                            <option value="toys">Toys</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="condition">Condition</label>
                        <select id="condition" name="condition" class="form-control" required>
                            <option value="">Select condition</option>
                            <option value="new">New</option>
                            <option value="like_new">Like New</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="images">Upload Images (First image will be primary)</label>
                    <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*" required>
                    <small>Upload at least one image of your item (max 5)</small>
                </div>
                
                <button type="submit" class="btn">List Item</button>
            </form>
        <?php endif; ?>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>