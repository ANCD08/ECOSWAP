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

// Get user's active items
$stmt = $pdo->prepare("
    SELECT i.*, 
           (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as primary_image
    FROM items i
    WHERE i.user_id = ? AND i.status = 'available'
    ORDER BY i.created_at DESC
    LIMIT 4
");
$stmt->execute([$_SESSION['user_id']]);
$userItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending swap requests
$stmt = $pdo->prepare("
    SELECT sr.request_id, sr.status, 
           ri.title as requested_item_title, 
           oi.title as offered_item_title,
           u.username as requester_username,
           u.profile_pic as requester_profile_pic
    FROM swap_requests sr
    JOIN items ri ON sr.requested_item_id = ri.item_id
    JOIN items oi ON sr.offered_item_id = oi.item_id
    JOIN users u ON sr.requester_id = u.user_id
    WHERE ri.user_id = ? AND sr.status = 'pending'
    ORDER BY sr.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent messages
$stmt = $pdo->prepare("
    SELECT m.*, sr.request_id, 
           CASE 
               WHEN sr.requester_id = ? THEN u2.username
               ELSE u1.username
           END as other_user,
           CASE 
               WHEN sr.requester_id = ? THEN u2.profile_pic
               ELSE u1.profile_pic
           END as other_user_pic
    FROM messages m
    JOIN swap_requests sr ON m.swap_request_id = sr.request_id
    JOIN items ri ON sr.requested_item_id = ri.item_id
    JOIN items oi ON sr.offered_item_id = oi.item_id
    JOIN users u1 ON ri.user_id = u1.user_id
    JOIN users u2 ON oi.user_id = u2.user_id
    WHERE (sr.requester_id = ? OR ri.user_id = ?)
    ORDER BY m.sent_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, sr.request_id,
           ri.title as requested_item_title,
           oi.title as offered_item_title,
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
    WHERE (sr.requester_id = ? OR ri.user_id = ?)
    ORDER BY t.completed_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($user['profile_pic'] ?? 'images/default_profile.jpg') ?>" alt="Profile Picture" class="profile-pic">
            <div class="profile-info">
                <h2>Welcome back, <?= htmlspecialchars($user['username']) ?>!</h2>
                <p><?= htmlspecialchars($user['bio'] ?? 'No bio yet') ?></p>
                <div class="eco-credits">Eco Credits: <?= $user['eco_credits'] ?></div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
            <!-- Your Items Section -->
            <section>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Your Active Listings</h2>
                    <a href="add_item.php" class="btn">Add New Item</a>
                </div>
                
                <?php if (empty($userItems)): ?>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <p>You don't have any active listings.</p>
                        <a href="add_item.php" class="btn">List Your First Item</a>
                    </div>
                <?php else: ?>
                    <div class="grid" style="grid-template-columns: 1fr 1fr;">
                        <?php foreach ($userItems as $item): ?>
                            <div class="card">
                                <img src="<?= htmlspecialchars($item['primary_image'] ?? 'images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="card-img" style="height: 150px;">
                                <div class="card-body">
                                    <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                                        <a href="item.php?id=<?= $item['item_id'] ?>" class="btn" style="padding: 5px 10px;">View</a>
                                        <a href="edit_item.php?id=<?= $item['item_id'] ?>" class="btn btn-outline" style="padding: 5px 10px;">Edit</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="my_items.php" class="btn" style="margin-top: 15px; display: block; text-align: center;">View All Your Items</a>
                <?php endif; ?>
            </section>
            
            <!-- Pending Requests Section -->
            <section>
                <h2>Pending Swap Requests</h2>
                <?php if (empty($pendingRequests)): ?>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <p>You have no pending swap requests.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($pendingRequests as $request): ?>
                            <div class="card" style="padding: 15px;">
                                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                    <img src="<?= htmlspecialchars($request['requester_profile_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($request['requester_username']) ?>" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <div>
                                        <strong><?= htmlspecialchars($request['requester_username']) ?></strong> wants your <strong><?= htmlspecialchars($request['requested_item_title']) ?></strong>
                                    </div>
                                </div>
                                <p>Offering: <?= htmlspecialchars($request['offered_item_title']) ?></p>
                                <div style="display: flex; gap: 10px; margin-top: 10px;">
                                    <a href="swap_requests.php" class="btn" style="padding: 5px 10px;">View Request</a>
                                    <a href="messages.php?request=<?= $request['request_id'] ?>" class="btn btn-outline" style="padding: 5px 10px;">Message</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="swap_requests.php" class="btn" style="margin-top: 15px; display: block; text-align: center;">View All Requests</a>
                <?php endif; ?>
            </section>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
            <!-- Recent Messages Section -->
            <section>
                <h2>Recent Messages</h2>
                <?php if (empty($recentMessages)): ?>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <p>You have no recent messages.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($recentMessages as $message): ?>
                            <div class="card" style="padding: 15px;">
                                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                    <img src="<?= htmlspecialchars($message['other_user_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($message['other_user']) ?>" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <div>
                                        <strong><?= htmlspecialchars($message['other_user']) ?></strong>
                                        <p style="margin: 0; font-size: 0.9em;"><?= date('M j, g:i a', strtotime($message['sent_at'])) ?></p>
                                    </div>
                                </div>
                                <p style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($message['message_text']) ?></p>
                                <a href="messages.php?request=<?= $message['request_id'] ?>" class="btn" style="padding: 5px 10px; margin-top: 10px;">View Conversation</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="messages.php" class="btn" style="margin-top: 15px; display: block; text-align: center;">View All Messages</a>
                <?php endif; ?>
            </section>
            
            <!-- Recent Transactions Section -->
            <section>
                <h2>Recent Swaps</h2>
                <?php if (empty($recentTransactions)): ?>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <p>You have no recent swaps.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="card" style="padding: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>Swap with <?= htmlspecialchars($transaction['other_user']) ?></strong>
                                    <span><?= date('M j', strtotime($transaction['completed_at'])) ?></span>
                                </div>
                                <p style="margin: 10px 0;">Your item: <?= htmlspecialchars($transaction['offered_item_title']) ?></p>
                                <p>Their item: <?= htmlspecialchars($transaction['requested_item_title']) ?></p>
                                <?php if (!$transaction['requester_rating'] || !$transaction['requestee_rating']): ?>
                                    <a href="transactions.php?id=<?= $transaction['transaction_id'] ?>" class="btn" style="padding: 5px 10px; margin-top: 10px;">Rate This Swap</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="transactions.php" class="btn" style="margin-top: 15px; display: block; text-align: center;">View All Transactions</a>
                <?php endif; ?>
            </section>
        </div>
        
        <!-- Quick Stats Section -->
        <div class="card" style="margin-top: 30px; padding: 20px;">
            <h2>Your EcoSwap Stats</h2>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center; margin-top: 20px;">
                <div>
                    <h3><?= count($userItems) ?></h3>
                    <p>Active Listings</p>
                </div>
                <div>
                    <h3><?= count($pendingRequests) ?></h3>
                    <p>Pending Requests</p>
                </div>
                <div>
                    <h3><?= count($recentTransactions) ?></h3>
                    <p>Completed Swaps</p>
                </div>
                <div>
                    <h3><?= $user['eco_credits'] ?></h3>
                    <p>Eco Credits</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Section -->
        <div style="margin-top: 30px;">
            <h2>Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                <a href="browse.php" class="btn" style="text-align: center; padding: 15px;">Browse Items</a>
                <a href="add_item.php" class="btn" style="text-align: center; padding: 15px;">Add New Item</a>
                <a href="profile.php" class="btn" style="text-align: center; padding: 15px;">Edit Profile</a>
                <a href="messages.php" class="btn" style="text-align: center; padding: 15px;">View Messages</a>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>