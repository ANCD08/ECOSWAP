<?php
require_once 'uploads/config.php';

// Fetch featured items
$stmt = $pdo->prepare("
    SELECT i.*, u.username, u.profile_pic, 
           (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as primary_image
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.status = 'available'
    ORDER BY i.created_at DESC
    LIMIT 8
");
$stmt->execute();
$featuredItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ecoswap - Sustainable Swapping</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'uploads/header.php'; ?>
    
    <main class="container">
        <section class="hero">
            <h1>Swap, Don't Shop!</h1>
            <p>Join our community to exchange items you no longer need for things you want. It's sustainable, economical, and fun!</p>
            <a href="browse.php" class="btn">Browse Items</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="uploads/login.php" class="btn btn-outline">Login</a>
            <?php endif; ?>
        </section>
        
        <h2>Recently Added Items</h2>
        <div class="grid">
            <?php foreach ($featuredItems as $item): ?>
                <div class="card item-card" data-category="<?= htmlspecialchars($item['category']) ?>">
                    <img src="<?= htmlspecialchars($item['primary_image'] ?? 'images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-img">
                    <div class="card-body">
                        <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                        <p class="card-text"><?= htmlspecialchars(substr($item['description'], 0, 100)) ?>...</p>
                        <div class="item-meta">
                            <span class="condition condition-<?= str_replace(' ', '_', $item['condition']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $item['condition'])) ?>
                            </span>
                            <span><?= htmlspecialchars($item['category']) ?></span>
                        </div>
                        <a href="item.php?id=<?= $item['item_id'] ?>" class="btn">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    
    <?php include 'uploads/footer.php'; ?>
    <script src="main.js"></script>
</body>
</html>