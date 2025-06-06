<?php
require_once 'config.php';

$categoryFilter = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query based on filters
$query = "
    SELECT i.*, u.username, u.profile_pic, 
           (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as primary_image
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.status = 'available'
";

$params = [];

if ($categoryFilter !== 'all') {
    $query .= " AND i.category = ?";
    $params[] = $categoryFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$query .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h1>Browse Items</h1>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 10px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <select id="categoryFilter" name="category" class="form-control">
                        <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                        <option value="clothing" <?= $categoryFilter === 'clothing' ? 'selected' : '' ?>>Clothing</option>
                        <option value="books" <?= $categoryFilter === 'books' ? 'selected' : '' ?>>Books</option>
                        <option value="electronics" <?= $categoryFilter === 'electronics' ? 'selected' : '' ?>>Electronics</option>
                        <option value="furniture" <?= $categoryFilter === 'furniture' ? 'selected' : '' ?>>Furniture</option>
                        <option value="toys" <?= $categoryFilter === 'toys' ? 'selected' : '' ?>>Toys</option>
                        <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <button type="submit" class="btn">Filter</button>
            </form>
            
            <form method="GET" style="display: flex; gap: 10px; width: 50%;">
                <div class="form-group" style="margin-bottom: 0; flex-grow: 1;">
                    <input type="text" name="search" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                <button type="submit" class="btn">Search</button>
                <?php if (!empty($searchQuery) || $categoryFilter !== 'all'): ?>
                    <a href="browse.php" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($items)): ?>
            <div class="card" style="padding: 30px; text-align: center;">
                <h3>No items found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <a href="browse.php" class="btn" style="margin-top: 15px;">Browse All Items</a>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($items as $item): ?>
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
        <?php endif; ?>
    </main>
    
    <?php include 'footer.php'; ?>
    <script src="main.js"></script>
</body>
</html>