<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get swap requests where user is the requestee (their items are being requested)
$stmt = $pdo->prepare("
    SELECT sr.request_id, sr.status, sr.created_at, sr.updated_at,
           ri.item_id as requested_item_id, ri.title as requested_item_title,
           oi.item_id as offered_item_id, oi.title as offered_item_title,
           u.user_id as requester_id, u.username as requester_username, u.profile_pic as requester_profile_pic
    FROM swap_requests sr
    JOIN items ri ON sr.requested_item_id = ri.item_id
    JOIN items oi ON sr.offered_item_id = oi.item_id
    JOIN users u ON sr.requester_id = u.user_id
    WHERE ri.user_id = ?
    ORDER BY sr.updated_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$receivedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get swap requests where user is the requester (they initiated the request)
$stmt = $pdo->prepare("
    SELECT sr.request_id, sr.status, sr.created_at, sr.updated_at,
           ri.item_id as requested_item_id, ri.title as requested_item_title,
           oi.item_id as offered_item_id, oi.title as offered_item_title,
           u.user_id as requestee_id, u.username as requestee_username, u.profile_pic as requestee_profile_pic
    FROM swap_requests sr
    JOIN items ri ON sr.requested_item_id = ri.item_id
    JOIN items oi ON sr.offered_item_id = oi.item_id
    JOIN users u ON ri.user_id = u.user_id
    WHERE sr.requester_id = ?
    ORDER BY sr.updated_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$sentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle request actions (accept/reject/cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    // Verify the user has permission to act on this request
    $stmt = $pdo->prepare("
        SELECT sr.request_id, ri.user_id as requestee_id, sr.requester_id
        FROM swap_requests sr
        JOIN items ri ON sr.requested_item_id = ri.item_id
        WHERE sr.request_id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    
    if ($request) {
        if ($action === 'accept' && $request['requestee_id'] == $_SESSION['user_id']) {
            // Accept the swap request
            $pdo->beginTransaction();
            
            try {
                // Update request status
                $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'accepted' WHERE request_id = ?");
                $stmt->execute([$requestId]);
                
                // Mark both items as swapped
                $stmt = $pdo->prepare("UPDATE items SET status = 'swapped' WHERE item_id IN 
                    (SELECT requested_item_id FROM swap_requests WHERE request_id = ?) OR 
                    item_id IN (SELECT offered_item_id FROM swap_requests WHERE request_id = ?)");
                $stmt->execute([$requestId, $requestId]);
                
                // Create transaction record
                $stmt = $pdo->prepare("INSERT INTO transactions (swap_request_id) VALUES (?)");
                $stmt->execute([$requestId]);
                
                // Update eco credits for both users
                $stmt = $pdo->prepare("UPDATE users SET eco_credits = eco_credits + 10 WHERE user_id = ? OR user_id = ?");
                $stmt->execute([$_SESSION['user_id'], $request['requester_id']]);
                
                $pdo->commit();
                $actionSuccess = "Swap request accepted successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $actionError = "Error processing swap: " . $e->getMessage();
            }
        } elseif ($action === 'reject' && $request['requestee_id'] == $_SESSION['user_id']) {
            // Reject the swap request
            $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'rejected' WHERE request_id = ?");
            $stmt->execute([$requestId]);
            
            // Mark requested item as available again
            $stmt = $pdo->prepare("UPDATE items SET status = 'available' WHERE item_id IN 
                (SELECT requested_item_id FROM swap_requests WHERE request_id = ?)");
            $stmt->execute([$requestId]);
            
            $actionSuccess = "Swap request rejected.";
        } elseif ($action === 'cancel' && $request['requester_id'] == $_SESSION['user_id']) {
            // Cancel the swap request
            $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'rejected' WHERE request_id = ?");
            $stmt->execute([$requestId]);
            
            // Mark requested item as available again
            $stmt = $pdo->prepare("UPDATE items SET status = 'available' WHERE item_id IN 
                (SELECT requested_item_id FROM swap_requests WHERE request_id = ?)");
            $stmt->execute([$requestId]);
            
            $actionSuccess = "Swap request canceled.";
        } elseif ($action === 'complete' && ($request['requestee_id'] == $_SESSION['user_id'] || $request['requester_id'] == $_SESSION['user_id'])) {
            // Complete the swap (rate the transaction)
            $rating = $_POST['rating'];
            $comment = $_POST['comment'] ?? '';
            
            if ($rating >= 1 && $rating <= 5) {
                // Determine which user is being rated
                $ratedUserId = ($_SESSION['user_id'] == $request['requester_id']) ? $request['requestee_id'] : $request['requester_id'];
                
                // Update transaction with rating
                if ($_SESSION['user_id'] == $request['requester_id']) {
                    $stmt = $pdo->prepare("UPDATE transactions SET requester_rating = ? WHERE swap_request_id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE transactions SET requestee_rating = ? WHERE swap_request_id = ?");
                }
                $stmt->execute([$rating, $requestId]);
                
                // Update user's average rating
                $stmt = $pdo->prepare("
                    UPDATE users u
                    SET u.rating = (
                        SELECT AVG(
                            CASE 
                                WHEN t.requestee_rating IS NOT NULL AND t.requester_id = u.user_id THEN t.requestee_rating
                                WHEN t.requester_rating IS NOT NULL AND ri.user_id = u.user_id THEN t.requester_rating
                                ELSE NULL
                            END
                        )
                        FROM transactions t
                        JOIN swap_requests sr ON t.swap_request_id = sr.request_id
                        JOIN items ri ON sr.requested_item_id = ri.item_id
                        WHERE (sr.requester_id = u.user_id OR ri.user_id = u.user_id)
                    )
                    WHERE u.user_id = ?
                ");
                $stmt->execute([$ratedUserId]);
                
                $actionSuccess = "Thank you for your rating!";
            } else {
                $actionError = "Please provide a valid rating (1-5).";
            }
        }
    }
    
    // Refresh requests after action
    header("Location: swap_requests.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swap Requests - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h1>Swap Requests</h1>
        
        <?php if (isset($actionSuccess)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($actionSuccess) ?></div>
        <?php elseif (isset($actionError)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($actionError) ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
            <section>
                <h2>Received Requests</h2>
                <?php if (empty($receivedRequests)): ?>
                    <p>You have no received swap requests.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($receivedRequests as $request): ?>
                            <div class="card" style="padding: 15px;">
                                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                    <img src="<?= htmlspecialchars($request['requester_profile_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($request['requester_username']) ?>" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <div>
                                        <strong><?= htmlspecialchars($request['requester_username']) ?></strong> wants to swap:
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                        <p><strong>Your Item:</strong></p>
                                        <p><?= htmlspecialchars($request['requested_item_title']) ?></p>
                                        <a href="item.php?id=<?= $request['requested_item_id'] ?>" class="btn btn-outline" style="padding: 5px 10px; margin-top: 5px;">View</a>
                                    </div>
                                    <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                        <p><strong>Their Offer:</strong></p>
                                        <p><?= htmlspecialchars($request['offered_item_title']) ?></p>
                                        <a href="item.php?id=<?= $request['offered_item_id'] ?>" class="btn btn-outline" style="padding: 5px 10px; margin-top: 5px;">View</a>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <small>Requested <?= date('M j, Y', strtotime($request['created_at'])) ?></small>
                                    <span style="padding: 3px 8px; border-radius: 4px; background-color: 
                                        <?= $request['status'] === 'pending' ? '#FFF3E0' : 
                                           ($request['status'] === 'accepted' ? '#E8F5E9' : '#FFEBEE') ?>;">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </div>
                                
                                <?php if ($request['status'] === 'pending'): ?>
                                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn">Accept</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-outline">Reject</button>
                                        </form>
                                        <a href="messages.php?request=<?= $request['request_id'] ?>" class="btn btn-outline">Message</a>
                                    </div>
                                <?php elseif ($request['status'] === 'accepted'): ?>
                                    <div style="margin-top: 15px;">
                                        <p>Swap accepted! Coordinate with <?= htmlspecialchars($request['requester_username']) ?> to complete the exchange.</p>
                                        <a href="messages.php?request=<?= $request['request_id'] ?>" class="btn">Continue Conversation</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            
            <section>
                <h2>Sent Requests</h2>
                <?php if (empty($sentRequests)): ?>
                    <p>You have no sent swap requests.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($sentRequests as $request): ?>
                            <div class="card" style="padding: 15px;">
                                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                    <img src="<?= htmlspecialchars($request['requestee_profile_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($request['requestee_username']) ?>" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <div>
                                        You requested to swap with <strong><?= htmlspecialchars($request['requestee_username']) ?></strong>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                        <p><strong>Their Item:</strong></p>
                                        <p><?= htmlspecialchars($request['requested_item_title']) ?></p>
                                        <a href="item.php?id=<?= $request['requested_item_id'] ?>" class="btn btn-outline" style="padding: 5px 10px; margin-top: 5px;">View</a>
                                    </div>
                                    <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                        <p><strong>Your Offer:</strong></p>
                                        <p><?= htmlspecialchars($request['offered_item_title']) ?></p>
                                        <a href="item.php?id=<?= $request['offered_item_id'] ?>" class="btn btn-outline" style="padding: 5px 10px; margin-top: 5px;">View</a>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <small>Sent <?= date('M j, Y', strtotime($request['created_at'])) ?></small>
                                    <span style="padding: 3px 8px; border-radius: 4px; background-color: 
                                        <?= $request['status'] === 'pending' ? '#FFF3E0' : 
                                           ($request['status'] === 'accepted' ? '#E8F5E9' : '#FFEBEE') ?>;">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </div>
                                
                                <?php if ($request['status'] === 'pending'): ?>
                                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-outline">Cancel Request</button>
                                        </form>
                                        <a href="messages.php?request=<?= $request['request_id'] ?>" class="btn btn-outline">Message</a>
                                    </div>
                                <?php elseif ($request['status'] === 'accepted'): ?>
                                    <div style="margin-top: 15px;">
                                        <p>Your swap request was accepted! Coordinate with <?= htmlspecialchars($request['requestee_username']) ?> to complete the exchange.</p>
                                        <a href="messages.php?request=<?= $request['request_id'] ?>" class="btn">Continue Conversation</a>
                                    </div>
                                <?php elseif ($request['status'] === 'rejected'): ?>
                                    <div style="margin-top: 15px;">
                                        <p>Your swap request was rejected.</p>
                                        <a href="browse.php" class="btn">Browse Other Items</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>