<?php
require_once '../config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if item ID is provided
if (!isset($_GET['id'])) {
    header("Location: my_items.php");
    exit();
}

$itemId = $_GET['id'];

// Get item details
$stmt = $pdo->prepare("
    SELECT i.*
    FROM items i
    WHERE i.item_id = ? AND i.user_id = ?
");
$stmt->execute([$itemId, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: my_items.php");
    exit();
}

// Get item images
$stmt = $pdo->prepare("SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC");
$stmt->execute([$itemId]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $condition = $_POST['condition'];
    $status = $_POST['status'];
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    // Only allow status change if no pending requests
    if ($status !== $item['status'] && $item['status'] === 'pending') {
        $errors[] = "Cannot change status while swap request is pending";
    }
    
    if (empty($errors)) {
        // Update item in database
        $stmt = $pdo->prepare("
            UPDATE items 
            SET title = ?, description = ?, category = ?, condition = ?, status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE item_id = ?
        ");
        $stmt->execute([$title, $description, $category, $condition, $status, $itemId]);
        
        // Handle image deletions
        if (isset($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $imageId) {
                // Verify the image belongs to this item
                $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE image_id = ? AND item_id = ?");
                $stmt->execute([$imageId, $itemId]);
                $image = $stmt->fetch();
                
                if ($image) {
                    // Delete file from server
                    if (file_exists($image['image_path'])) {
                        unlink($image['image_path']);
                    }
                    
                    // Delete record from database
                    $stmt = $pdo->prepare("DELETE FROM item_images WHERE image_id = ?");
                    $stmt->execute([$imageId]);
                }
            }
        }
        
        // Handle new image uploads
        if (!empty($_FILES['new_images']['name'][0])) {
            $uploadDir = '../uploads/item_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Check if we need to set a new primary image
            $hasPrimary = false;
            foreach ($images as $img) {
                if ($img['is_primary'] && !in_array($img['image_id'], $_POST['delete_images'] ?? [])) {
                    $hasPrimary = true;
                    break;
                }
            }
            
            foreach ($_FILES['new_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileExt = pathinfo($_FILES['new_images']['name'][$key], PATHINFO_EXTENSION);
                    $fileName = 'item_' . $itemId . '_' . uniqid() . '.' . $fileExt;
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        // Insert image into database
                        $isPrimary = !$hasPrimary;
                        $hasPrimary = $hasPrimary || $isPrimary;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO item_images (item_id, image_path, is_primary)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$itemId, $uploadPath, $isPrimary]);
                    }
                }
            }
        }
        
        // Handle primary image change
        if (isset($_POST['primary_image']) && !in_array($_POST['primary_image'], $_POST['delete_images'] ?? [])) {
            // First reset all images to not primary
            $stmt = $pdo->prepare("UPDATE item_images SET is_primary = FALSE WHERE item_id = ?");
            $stmt->execute([$itemId]);
            
            // Then set the selected one as primary
            $stmt = $pdo->prepare("UPDATE item_images SET is_primary = TRUE WHERE image_id = ? AND item_id = ?");
            $stmt->execute([$_POST['primary_image'], $itemId]);
        }
        
        $success = true;
        
        // Refresh item data
        $stmt = $pdo->prepare("SELECT * FROM items WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC");
        $stmt->execute([$itemId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include '../header.php'; ?>
    
    <main class="container">
        <h1>Edit Item</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>Item updated successfully!</p>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="card" style="padding: 20px; margin-top: 20px;">
            <div class="form-group">
                <label for="title">Item Title</label>
                <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($item['title']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="5" required><?= htmlspecialchars($item['description']) ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control" required>
                        <option value="clothing" <?= $item['category'] === 'clothing' ? 'selected' : '' ?>>Clothing</option>
                        <option value="books" <?= $item['category'] === 'books' ? 'selected' : '' ?>>Books</option>
                        <option value="electronics" <?= $item['category'] === 'electronics' ? 'selected' : '' ?>>Electronics</option>
                        <option value="furniture" <?= $item['category'] === 'furniture' ? 'selected' : '' ?>>Furniture</option>
                        <option value="toys" <?= $item['category'] === 'toys' ? 'selected' : '' ?>>Toys</option>
                        <option value="other" <?= $item['category'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition" class="form-control" required>
                        <option value="new" <?= $item['condition'] === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="like_new" <?= $item['condition'] === 'like_new' ? 'selected' : '' ?>>Like New</option>
                        <option value="good" <?= $item['condition'] === 'good' ? 'selected' : '' ?>>Good</option>
                        <option value="fair" <?= $item['condition'] === 'fair' ? 'selected' : '' ?>>Fair</option>
                        <option value="poor" <?= $item['condition'] === 'poor' ? 'selected' : '' ?>>Poor</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="available" <?= $item['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?> <?= $item['status'] === 'pending' ? '' : 'disabled' ?>>Pending</option>
                    <option value="swapped" <?= $item['status'] === 'swapped' ? 'selected' : '' ?> <?= $item['status'] === 'swapped' ? '' : 'disabled' ?>>Swapped</option>
                </select>
                <?php if ($item['status'] === 'pending'): ?>
                    <small style="color: orange;">Status cannot be changed while swap is pending</small>
                <?php elseif ($item['status'] === 'swapped'): ?>
                    <small style="color: gray;">Status cannot be changed after swap is completed</small>
                <?php endif; ?>
            </div>
            
            <!-- Current Images -->
            <div class="form-group">
                <label>Current Images</label>
                <?php if (empty($images)): ?>
                    <p>No images uploaded yet</p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 10px;">
                        <?php foreach ($images as $image): ?>
                            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px; text-align: center;">
                                <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Item image" style="width: 100%; height: 100px; object-fit: contain;">
                                <div style="margin-top: 10px;">
                                    <label style="display: flex; align-items: center; justify-content: center;">
                                        <input type="radio" name="primary_image" value="<?= $image['image_id'] ?>" <?= $image['is_primary'] ? 'checked' : '' ?> style="margin-right: 5px;">
                                        Primary
                                    </label>
                                    <label style="display: flex; align-items: center; justify-content: center; margin-top: 5px;">
                                        <input type="checkbox" name="delete_images[]" value="<?= $image['image_id'] ?>" style="margin-right: 5px;">
                                        Delete
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- New Images -->
            <div class="form-group">
                <label for="new_images">Add More Images</label>
                <input type="file" id="new_images" name="new_images[]" class="form-control" multiple accept="image/*">
                <small>You can upload up to 5 additional images (max 5MB each)</small>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" class="btn">Save Changes</button>
                <a href="item.php?id=<?= $itemId ?>" class="btn btn-outline">Cancel</a>
                <a href="my_items.php" class="btn btn-outline">Back to My Items</a>
            </div>
        </form>
    </main>
    
    <?php include '../footer.php'; ?>
</body>
</html>