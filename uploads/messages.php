<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the swap request ID from URL if provided
$requestId = $_GET['request'] ?? null;

// Get all active swap requests involving the current user
$stmt = $pdo->prepare("
    SELECT sr.request_id, 
           CASE 
               WHEN sr.requester_id = ? THEN u2.username
               ELSE u1.username
           END as other_user,
           CASE 
               WHEN sr.requester_id = ? THEN u2.profile_pic
               ELSE u1.profile_pic
           END as other_user_pic,
           CASE 
               WHEN sr.requester_id = ? THEN ri.title
               ELSE oi.title
           END as their_item,
           CASE 
               WHEN sr.requester_id = ? THEN oi.title
               ELSE ri.title
           END as your_item
    FROM swap_requests sr
    JOIN items ri ON sr.requested_item_id = ri.item_id
    JOIN items oi ON sr.offered_item_id = oi.item_id
    JOIN users u1 ON ri.user_id = u1.user_id
    JOIN users u2 ON oi.user_id = u2.user_id
    WHERE (sr.requester_id = ? OR ri.user_id = ?)
    AND sr.status IN ('pending', 'accepted')
    ORDER BY sr.updated_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If a specific request is selected, get its messages
$messages = [];
$currentConversation = null;

if ($requestId) {
    // Verify the user is part of this conversation
    $validRequest = false;
    foreach ($conversations as $conv) {
        if ($conv['request_id'] == $requestId) {
            $validRequest = true;
            $currentConversation = $conv;
            break;
        }
    }
    
    if ($validRequest) {
        // Get messages for this request
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.profile_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.swap_request_id = ?
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$requestId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE swap_request_id = ? AND sender_id != ?
        ");
        $stmt->execute([$requestId, $_SESSION['user_id']]);
        
        // Handle new message submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
            $messageText = trim($_POST['message']);
            
            if (!empty($messageText)) {
                $stmt = $pdo->prepare("
                    INSERT INTO messages (swap_request_id, sender_id, message_text)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$requestId, $_SESSION['user_id'], $messageText]);
                
                // Update the swap request's updated_at timestamp
                $stmt = $pdo->prepare("
                    UPDATE swap_requests 
                    SET updated_at = CURRENT_TIMESTAMP 
                    WHERE request_id = ?
                ");
                $stmt->execute([$requestId]);
                
                // Redirect to prevent form resubmission
                header("Location: messages.php?request=$requestId");
                exit();
            }
        }
    } else {
        $requestId = null; // Invalid request ID
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Ecoswap</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h1>Messages</h1>
        
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px; margin-top: 30px;">
            <!-- Conversation list -->
            <div class="card" style="padding: 15px; max-height: 80vh; overflow-y: auto;">
                <h2>Conversations</h2>
                
                <?php if (empty($conversations)): ?>
                    <p>You have no active conversations.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 10px;">
                        <?php foreach ($conversations as $conv): ?>
                            <a href="messages.php?request=<?= $conv['request_id'] ?>" style="text-decoration: none; color: inherit;">
                                <div style="padding: 10px; border-radius: 5px; background-color: <?= $conv['request_id'] == $requestId ? '#E8F5E9' : 'transparent' ?>;">
                                    <div style="display: flex; align-items: center;">
                                        <img src="<?= htmlspecialchars($conv['other_user_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($conv['other_user']) ?>" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                        <div>
                                            <strong><?= htmlspecialchars($conv['other_user']) ?></strong>
                                            <p style="font-size: 0.9em; margin: 0;">Swap: <?= htmlspecialchars($conv['their_item']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Message area -->
            <div class="card" style="padding: 20px; display: flex; flex-direction: column; max-height: 80vh;">
                <?php if ($requestId && $currentConversation): ?>
                    <div style="display: flex; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
                        <img src="<?= htmlspecialchars($currentConversation['other_user_pic'] ?? 'images/default_profile.jpg') ?>" alt="<?= htmlspecialchars($currentConversation['other_user']) ?>" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;">
                        <div>
                            <h2><?= htmlspecialchars($currentConversation['other_user']) ?></h2>
                            <p>Swap: <?= htmlspecialchars($currentConversation['their_item']) ?> for <?= htmlspecialchars($currentConversation['your_item']) ?></p>
                        </div>
                    </div>
                    
                    <div class="message-container">
                        <?php if (empty($messages)): ?>
                            <p style="text-align: center; color: #777;">No messages yet. Start the conversation!</p>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message <?= $message['sender_id'] == $_SESSION['user_id'] ? 'message-sent' : 'message-received' ?>">
                                    <p><?= htmlspecialchars($message['message_text']) ?></p>
                                    <div class="message-info">
                                        <?= date('M j, g:i a', strtotime($message['sent_at'])) ?>
                                        <?php if ($message['sender_id'] == $_SESSION['user_id'] && $message['is_read']): ?>
                                            ✓✓
                                        <?php elseif ($message['sender_id'] == $_SESSION['user_id']): ?>
                                            ✓
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="message-form">
                        <input type="text" name="message" class="message-input" placeholder="Type your message..." required>
                        <button type="submit" class="btn">Send</button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px;">
                        <h3>Select a conversation</h3>
                        <p>Choose a swap request from the list to view and send messages</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>