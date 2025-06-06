<?php
require_once '../config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$query = "
    SELECT i.*, 
           (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as primary_image
    FROM items i
    WHERE i.user_id = ?
";

$params = [$_SESSION['user_id']];

switch ($filter) {
    case 'available':
        $query .= " AND i.status = 'available'";
        break;
    case 'pending':
        $query .= " AND i.status = 'pending'";
        break;
    case 'swapped':
        $query .= " AND i.status = 'swapped'";
        break;
    // 'all' shows everything
}

$query .= " ORDER BY i.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Items - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include '../header.php'; ?>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>My Items</h1>
            <a href="add_item.php" class="btn">Add New Item</a>
        </div>
        
        <!-- Filter tabs -->
        <div style="display: flex; gap: 10px; margin: 20px 0;">
            <a href="?filter=all" class="btn btn-outline <?= $filter === 'all' ? 'active' : '' ?>" style="<?= $filter === 'all' ? 'background-color: var(--primary-color); color: white;' : '' ?>">All Items</a>
            <a href="?filter=available" class="btn btn-outline <?= $filter === 'available' ? 'active' : '' ?>" style="<?= $filter === 'available' ? 'background-color: var(--primary-color); color: white;' : '' ?>">Available</a>
            <a href="?filter=pending" class="btn btn-outline <?= $filter === 'pending' ? 'active' : '' ?>" style="<?= $filter === 'pending' ? 'background-color: var(--primary-color); color: white;' : '' ?>">Pending</a>
            <a href="?filter=swapped" class="btn btn-outline <?= $filter === 'swapped' ? 'active' : '' ?>" style="<?= $filter === 'swapped' ? 'background-color: var(--primary-color); color: white;' : '' ?>">Swapped</a>
        </div>
        
        <?php if (empty($items)): ?>
            <div class="card" style="padding: 30px; text-align: center;">
                <h3>No items found</h3>
                <p>You haven't listed any items yet or no items match your filter.</p>
                <a href="add_item.php" class="btn">List Your First Item</a>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($items as $item): ?>
                    <div class="card">
                        <img src="<?= htmlspecialchars($item['primary_image'] ?? '../images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-img">
                        <div class="card-body">
                            <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                            <div class="item-meta">
                                <span class="condition condition-<?= str_replace(' ', '_', $item['condition']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $item['condition'])) ?>
                                </span>
                                <span><?= ucfirst($item['category']) ?></span>
                                <span style="color: <?= $item['status'] === 'available' ? 'green' : ($item['status'] === 'pending' ? 'orange' : 'gray') ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <a href="item.php?id=<?= $item['item_id'] ?>" class="btn" style="flex-grow: 1; text-align: center;">View</a>
                                <?php if ($item['status'] === 'available'): ?>
                                    <a href="edit_item.php?id=<?= $item['item_id'] ?>" class="btn btn-outline" style="flex-grow: 1; text-align: center;">Edit</a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($item['status'] === 'pending'): ?>
                                <?php
                                // Get swap request info for pending items
                                $stmt = $pdo->prepare("
                                    SELECT sr.request_id, u.username, u.profile_pic, oi.title as offered_title
                                    FROM swap_requests sr
                                    JOIN users u ON sr.requester_id = u.user_id
                                    JOIN items oi ON sr.offered_item_id = oi.item_id
                                    WHERE sr.requested_item_id = ? AND sr.status = 'pending'
                                ");
                                $stmt->execute([$item['item_id']]);
                                $request = $stmt->fetch();
                                ?>
                                
                                <?php if ($request): ?>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                        <p style="font-size: 0.9em;">Pending swap with <?= htmlspecialchars($request['username']) ?></p>
                                        <p style="font-size: 0.9em;">Offering: <?= htmlspecialchars($request['offered_title']) ?></p>
                                        <a href="swap_requests.php" class="btn btn-outline" style="width: 100%; margin-top: 10px; padding: 5px;">View Request</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($item['status'] === 'swapped'): ?>
                                <?php
                                // Get transaction info for swapped items
                                $stmt = $pdo->prepare("
                                    SELECT t.*, 
                                           CASE 
                                               WHEN sr.requester_id = ? THEN u2.username
                                               ELSE u1.username
                                           END as other_user
                                    FROM transactions t
                                    JOIN swap_requests sr ON t.swap_request_id = sr.request_id
                                    JOIN items ri ON sr.requested_item_id = ri.item_id
                                    JOIN items oi ON sr.offered_item_id = oi.item_id
                                    JOIN users u1 ON ri.user_id = u1.user_id
                                    JOIN users u2 ON oi.user_id = u2.user_id
                                    WHERE (sr.requested_item_id = ? OR sr.offered_item_id = ?)
                                ");
                                $stmt->execute([$_SESSION['user_id'], $item['item_id'], $item['item_id']]);
                                $transaction = $stmt->fetch();
                                ?>
                                
                                <?php if ($transaction): ?>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                        <p style="font-size: 0.9em;">Swapped with <?= htmlspecialchars($transaction['other_user']) ?></p>
                                        <p style="font-size: 0.9em;">Completed on <?= date('M j, Y', strtotime($transaction['completed_at'])) ?></p>
                                        <a href="transactions.php?id=<?= $transaction['transaction_id'] ?>" class="btn btn-outline" style="width: 100%; margin-top: 10px; padding: 5px;">View Details</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include '../footer.php'; ?>
</body>
</html>