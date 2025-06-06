<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: browse.php");
    exit();
}

$itemId = $_GET['id'];

// Get item details
$stmt = $pdo->prepare("
    SELECT i.*, u.user_id, u.username, u.profile_pic, u.rating as user_rating
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.item_id = ?
");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: browse.php");
    exit();
}

// Get item images
$stmt = $pdo->prepare("SELECT * FROM item_images WHERE item_id = ? ORDER BY is_primary DESC");
$stmt->execute([$itemId]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's other items for swap
$stmt = $pdo->prepare("
    SELECT i.*, 
           (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as primary_image
    FROM items i
    WHERE i.user_id = ? AND i.item_id != ? AND i.status = 'available'
    ORDER BY RAND()
    LIMIT 4
");
$stmt->execute([$item['user_id'], $itemId]);
$otherItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if logged in user can request a swap
$canRequest = isset($_SESSION['user_id']) && $_SESSION['user_id'] != $item['user_id'] && $item['status'] === 'available';

// Handle swap request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_swap']) && $canRequest && isset($_POST['offered_item'])) {
        $offeredItemId = $_POST['offered_item'];
        
        // Verify the offered item belongs to the requester
        $stmt = $pdo->prepare("SELECT user_id FROM items WHERE item_id = ?");
        $stmt->execute([$offeredItemId]);
        $offeredItem = $stmt->fetch();
        
        if ($offeredItem && $offeredItem['user_id'] == $_SESSION['user_id']) {
            // Create swap request
            $stmt = $pdo->prepare("
                INSERT INTO swap_requests (requested_item_id, offered_item_id, requester_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$itemId, $offeredItemId, $_SESSION['user_id']]);
            $requestId = $pdo->lastInsertId();
            
            // Update item status to pending
            $stmt = $pdo->prepare("UPDATE items SET status = 'pending' WHERE item_id = ?");
            $stmt->execute([$itemId]);
            
            // Create initial message
            if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
                $messageText = trim($_POST['message']);
                $stmt = $pdo->prepare("
                    INSERT INTO messages (swap_request_id, sender_id, message_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$requestId, $_SESSION['user_id'], $messageText]);
            }
            
            $_SESSION['success'] = "Swap request sent successfully!";
            header("Location: swap_requests.php");
            exit();
        }
    }
}

// Get user's available items for swap request form
$userItemsForSwap = [];
if ($canRequest) {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as primary_image
        FROM items i
        WHERE i.user_id = ? AND i.status = 'available'
        ORDER BY i.title
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userItemsForSwap = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <p><?= htmlspecialchars($_SESSION['success']) ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="item-detail">
            <div class="item-gallery">
                <?php if (!empty($images)): ?>
                    <img src="<?= htmlspecialchars($images[0]['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="main-image">
                    <?php foreach (array_slice($images, 1) as $image): ?>
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="thumbnail">
                    <?php endforeach; ?>
                <?php else: ?>
                    <img src="images/placeholder.jpg" alt="No image available" class="main-image">
                <?php endif; ?>
            </div>
            
            <div class="item-info">
                <h1><?= htmlspecialchars($item['title']) ?></h1>
                <div class="item-meta">
                    <span class="condition condition-<?= str_replace(' ', '_', $item['condition']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $item['condition'])) ?>
                    </span>
                    <span><?= ucfirst($item['category']) ?></span>
                    <span>Listed <?= date('M j, Y', strtotime($item['created_at'])) ?></span>
                </div>
                
                <h3 style="margin-top: 20px;">Description</h3>
                <p style="white-space: pre-wrap;"><?= htmlspecialchars($item['description']) ?></p>
                
                <div class="user-card">
                    <img src="<?= htmlspecialchars($item['profile_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($item['username']) ?>">
                    <div>
                        <h4>Listed by <?= htmlspecialchars($item['username']) ?></h4>
                        <div class="user-rating">
                            <?= str_repeat('★', round($item['user_rating'])) . str_repeat('☆', 5 - round($item['user_rating'])) ?>
                        </div>
                        <a href="profile.php?id=<?= $item['user_id'] ?>" class="btn btn-outline" style="margin-top: 10px; padding: 5px 10px;">View Profile</a>
                    </div>
                </div>
                
                <?php if ($canRequest): ?>
                    <button id="requestSwapBtn" class="btn" style="margin-top: 20px; width: 100%;">Request Swap</button>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <p style="margin-top: 20px; text-align: center;">
                        <a href="login.php?redirect=item.php?id=<?= $itemId ?>" class="btn">Login to Request Swap</a>
                    </p>
                <?php elseif ($item['status'] !== 'available'): ?>
                    <p style="margin-top: 20px; text-align: center; color: var(--error-color);">
                        This item is no longer available for swap.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($otherItems)): ?>
            <section style="margin-top: 50px;">
                <h2>Other Items from <?= htmlspecialchars($item['username']) ?></h2>
                <div class="grid">
                    <?php foreach ($otherItems as $otherItem): ?>
                        <div class="card">
                            <img src="<?= htmlspecialchars($otherItem['primary_image'] ?? 'images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($otherItem['title']) ?>" class="card-img">
                            <div class="card-body">
                                <h3 class="card-title"><?= htmlspecialchars($otherItem['title']) ?></h3>
                                <p class="card-text"><?= htmlspecialchars(substr($otherItem['description'], 0, 50)) ?>...</p>
                                <a href="item.php?id=<?= $otherItem['item_id'] ?>" class="btn">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    
    <!-- Swap Request Modal -->
    <div id="swapModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="card" style="background-color: white; margin: 5% auto; padding: 20px; width: 80%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <span class="close-modal" style="float: right; font-size: 28px; cursor: pointer;">&times;</span>
            <h2>Request Swap</h2>
            <p>You're requesting to swap your item for "<?= htmlspecialchars($item['title']) ?>"</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Select an item to offer:</label>
                    <?php if (empty($userItemsForSwap)): ?>
                        <p>You don't have any items available for swap.</p>
                        <p><a href="add_item.php" class="btn">Add an Item</a></p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <?php foreach ($userItemsForSwap as $swapItem): ?>
                                <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="radio" name="offered_item" value="<?= $swapItem['item_id'] ?>" required style="margin-right: 10px;">
                                        <img src="<?= htmlspecialchars($swapItem['primary_image'] ?? 'images/placeholder.jpg') ?>" alt="<?= htmlspecialchars($swapItem['title']) ?>" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                        <span><?= htmlspecialchars($swapItem['title']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-group" style="margin-top: 20px;">
                            <label for="swapMessage">Add a message (optional):</label>
                            <textarea id="swapMessage" name="message" class="form-control" rows="3" placeholder="Tell the owner why you want to swap..."></textarea>
                        </div>
                        
                        <input type="hidden" name="request_swap" value="1">
                        <button type="submit" class="btn" style="margin-top: 20px;">Send Swap Request</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    <script src="main.js"></script>
    <script>
        // Enhanced modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const requestSwapBtn = document.getElementById('requestSwapBtn');
            const swapModal = document.getElementById('swapModal');
            const closeModal = document.querySelector('.close-modal');
            
            if (requestSwapBtn && swapModal) {
                requestSwapBtn.addEventListener('click', function() {
                    swapModal.style.display = 'block';
                });
                
                closeModal.addEventListener('click', function() {
                    swapModal.style.display = 'none';
                });
                
                window.addEventListener('click', function(e) {
                    if (e.target === swapModal) {
                        swapModal.style.display = 'none';
                    }
                });
            }
            
            // Handle thumbnail clicks for gallery
            const thumbnails = document.querySelectorAll('.thumbnail');
            const mainImage = document.querySelector('.main-image');
            
            if (thumbnails.length && mainImage) {
                thumbnails.forEach(thumb => {
                    thumb.addEventListener('click', function() {
                        mainImage.src = this.src;
                    });
                });
            }
        });
    </script>
</body>
</html>