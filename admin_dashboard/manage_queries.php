<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle query status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $query_id = $_POST['query_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'reply') {
            $reply = trim($_POST['reply']);
            if (!empty($reply)) {
                $stmt = $conn->prepare("UPDATE queries SET reply = ?, reply_date = NOW(), status = 'replied' WHERE query_id = ?");
                $stmt->bind_param("si", $reply, $query_id);
                $stmt->execute();
            }
        } elseif ($action === 'close') {
            $stmt = $conn->prepare("UPDATE queries SET status = 'closed' WHERE query_id = ?");
            $stmt->bind_param("i", $query_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        $error = "Error updating query: " . $e->getMessage();
    }
}

// Fetch all queries
$queries = [];
try {
    $query = "SELECT q.*, u.full_name, u.email 
              FROM queries q 
              LEFT JOIN users u ON q.member_id = u.id 
              ORDER BY q.query_date DESC";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $queries[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Error fetching queries: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Queries - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b6b;
            --secondary: #4ecdc4;
            --dark: #2c3e50;
            --light: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            color: var(--primary);
            font-size: 24px;
        }

        .nav-menu {
            margin-top: 30px;
        }

        .nav-item {
            padding: 15px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item.active {
            background: var(--primary);
        }

        .nav-item i {
            width: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease;
        }

        .header h1 {
            color: var(--dark);
            font-size: 1.8rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header h1 i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .queries-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .queries-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .queries-header h1 {
            color: var(--dark);
            font-size: 2rem;
            margin: 0;
        }

        .query-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            transform: translateY(20px) rotateX(10deg);
            animation: cardAppear 0.8s ease forwards;
            transform-style: preserve-3d;
        }

        .query-card:nth-child(1) { animation-delay: 0.1s; }
        .query-card:nth-child(2) { animation-delay: 0.2s; }
        .query-card:nth-child(3) { animation-delay: 0.3s; }
        .query-card:nth-child(4) { animation-delay: 0.4s; }
        .query-card:nth-child(5) { animation-delay: 0.5s; }
        .query-card:nth-child(6) { animation-delay: 0.6s; }

        .query-card:hover {
            transform: translateY(-8px) rotateX(0deg) scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .query-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .query-info {
            display: flex;
            gap: 1.2rem;
            align-items: center;
        }

        .query-user {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: rgba(255, 107, 107, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .query-user i {
            color: var(--primary);
            font-size: 1rem;
        }

        .query-date {
            color: #666;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .query-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-replied {
            background: rgba(78, 205, 196, 0.1);
            color: var(--secondary);
        }

        .status-closed {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .query-content {
            margin-bottom: 1.5rem;
        }

        .query-subject {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .query-subject i {
            color: var(--primary);
            font-size: 1rem;
        }

        .query-message {
            color: #555;
            line-height: 1.6;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
            font-size: 0.95rem;
        }

        .query-reply {
            background: #f8f9fa;
            padding: 1.2rem;
            border-radius: 12px;
            margin-top: 1.2rem;
            border: 1px solid rgba(78, 205, 196, 0.2);
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }

        .reply-date {
            color: #666;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .reply-content {
            color: #333;
            line-height: 1.6;
            padding: 0.8rem;
            background: white;
            border-radius: 8px;
            border-left: 3px solid var(--secondary);
            font-size: 0.95rem;
        }

        .query-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.2rem;
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
        }

        .reply-btn {
            background: linear-gradient(135deg, var(--secondary), #3dbeb6);
            color: white;
        }

        .close-btn {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .action-btn:active {
            transform: translateY(-1px);
        }

        .reply-form {
            margin-top: 1.2rem;
            padding-top: 1.2rem;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .reply-form textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #eee;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            resize: vertical;
            min-height: 100px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .reply-form textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(78, 205, 196, 0.1);
        }

        .no-queries {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .no-queries i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1.2rem;
            animation: float 3s ease-in-out infinite;
        }

        .no-queries h3 {
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            color: var(--dark);
        }

        .no-queries p {
            font-size: 1rem;
            color: #666;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px) rotateX(10deg);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotateX(0deg);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .query-card {
                padding: 1.2rem;
            }

            .query-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }

            .query-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <?php 
            require_once 'admin_nav.php';
            echo getAdminNav('manage_queries.php');
            ?>
        </div>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-comments"></i> Manage Queries</h1>
            </div>

            <div class="queries-container">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($queries)): ?>
                    <div class="no-queries">
                        <i class="fas fa-inbox"></i>
                        <h3>No Queries Found</h3>
                        <p>There are no queries to display at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($queries as $query): ?>
                        <div class="query-card">
                            <div class="query-header">
                                <div class="query-info">
                                    <div class="query-user">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?php echo htmlspecialchars($query['full_name'] ?? 'Guest User'); ?></span>
                                    </div>
                                    <div class="query-date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M d, Y H:i', strtotime($query['query_date'])); ?>
                                    </div>
                                </div>
                                <div class="query-status status-<?php echo $query['status']; ?>">
                                    <i class="fas fa-<?php echo $query['status'] === 'pending' ? 'clock' : ($query['status'] === 'replied' ? 'check-circle' : 'times-circle'); ?>"></i>
                                    <?php echo ucfirst($query['status']); ?>
                                </div>
                            </div>

                            <div class="query-content">
                                <div class="query-subject">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($query['subject']); ?>
                                </div>
                                <div class="query-message">
                                    <?php echo nl2br(htmlspecialchars($query['message'])); ?>
                                </div>
                            </div>

                            <?php if ($query['status'] === 'replied'): ?>
                                <div class="query-reply">
                                    <div class="reply-header">
                                        <div class="reply-date">
                                            <i class="fas fa-reply"></i>
                                            Replied on <?php echo date('M d, Y H:i', strtotime($query['reply_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="reply-content">
                                        <?php echo nl2br(htmlspecialchars($query['reply'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($query['status'] !== 'closed'): ?>
                                <div class="query-actions">
                                    <?php if ($query['status'] === 'pending'): ?>
                                        <form method="POST" class="reply-form">
                                            <input type="hidden" name="query_id" value="<?php echo $query['query_id']; ?>">
                                            <input type="hidden" name="action" value="reply">
                                            <textarea name="reply" placeholder="Type your reply here..." required></textarea>
                                            <button type="submit" class="action-btn reply-btn">
                                                <i class="fas fa-paper-plane"></i> Send Reply
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="query_id" value="<?php echo $query['query_id']; ?>">
                                        <input type="hidden" name="action" value="close">
                                        <button type="submit" class="action-btn close-btn">
                                            <i class="fas fa-times-circle"></i> Close Query
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 