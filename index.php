<!DOCTYPE html>
<html>
<head>
    <title>PHP + MySQL CI/CD Demo</title>
    <style>
        body { font-family: Arial; margin: 50px; text-align: center; }
        .container { background: #f0f0f0; padding: 30px; border-radius: 10px; }
        input, button { padding: 10px; margin: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PHP + MySQL Pipeline Demo</h1>
        
        <?php
        // Include database connection
        require_once 'db-connect.php';
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
            $message = htmlspecialchars($_POST['message']);
            $stmt = $pdo->prepare("INSERT INTO messages (message, created_at) VALUES (?, NOW())");
            $stmt->execute([$message]);
            echo "<p style='color:green'>Message saved!</p>";
        }
        
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Fetch messages
        $stmt = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <form method="POST">
            <input type="text" name="message" placeholder="Enter your message" required>
            <button type="submit">Save</button>
        </form>
        
        <h3>Saved Messages:</h3>
        <?php if (count($messages) > 0): ?>
            <ul>
            <?php foreach ($messages as $msg): ?>
                <li><?php echo htmlspecialchars($msg['message']); ?> 
                    <small>(<?php echo $msg['created_at']; ?>)</small>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No messages yet. Add one above!</p>
        <?php endif; ?>
        
        <hr>
        <p>Deployed automatically via Jenkins CI/CD Pipeline</p>
        <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>