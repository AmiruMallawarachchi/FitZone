<?php
session_start();
require_once '../config.php';
require_once 'admin_nav.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Create vlog_likes table if it doesn't exist
$create_likes_table = "CREATE TABLE IF NOT EXISTS vlog_likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    vlog_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vlog_id) REFERENCES vlogs(vlog_id)
)";
$conn->query($create_likes_table);

// Handle vlog deletion
if (isset($_POST['delete_vlog'])) {
    $vlog_id = (int)$_POST['vlog_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get file paths before deletion
        $query = "SELECT video_url, thumbnail_url FROM vlogs WHERE vlog_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $vlog_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vlog = $result->fetch_assoc();
        
        // Delete vlog from database
        $query = "DELETE FROM vlogs WHERE vlog_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $vlog_id);
        $stmt->execute();
        
        // Delete files if they exist
        if ($vlog) {
            if (!empty($vlog['video_url']) && file_exists('../' . $vlog['video_url'])) {
                unlink('../' . $vlog['video_url']);
            }
            if (!empty($vlog['thumbnail_url']) && file_exists('../' . $vlog['thumbnail_url'])) {
                unlink('../' . $vlog['thumbnail_url']);
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Vlog deleted successfully";
        header('Location: manage_vlogs.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting vlog: " . $e->getMessage();
    }
}

// Fetch all vlogs with trainer information and engagement metrics
$query = "SELECT v.*, u.full_name as trainer_name,
          v.views as view_count
          FROM vlogs v 
          LEFT JOIN trainers t ON v.trainer_id = t.trainer_id 
          LEFT JOIN users u ON t.user_id = u.id
          GROUP BY v.vlog_id
          ORDER BY v.upload_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vlogs - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #50c878;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --success: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
            color: var(--dark);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background: #f0f2f5;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .page-title {
            font-size: 32px;
            color: var(--dark);
            position: relative;
            padding-bottom: 10px;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary);
            animation: slideIn 0.5s ease-out forwards;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #357abd;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .vlogs-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .vlogs-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .vlogs-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
        }

        .vlogs-table td {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .vlogs-table tr:hover {
            background: #f8f9fa;
        }

        .vlog-thumbnail {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            transition: transform 0.3s ease;
        }

        .vlog-thumbnail:hover {
            transform: scale(1.05);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }

        .stats-cell {
            text-align: center;
            color: #666;
        }
        
        .stats-cell i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        .stats-cell .fa-eye {
            color: var(--secondary);
        }
        
        .stats-cell .fa-heart {
            color: #ff4757;
        }

        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .category-badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <?php echo getAdminNav('manage_vlogs.php'); ?>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Vlogs</h1>
                <a href="add_vlog.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Vlog
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="vlogs-table">
                <table>
                    <thead>
                        <tr>
                            <th>Thumbnail</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Trainer</th>
                            <th>Upload Date</th>
                            <th>Views</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($vlog = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($vlog['thumbnail_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($vlog['thumbnail_url']); ?>" 
                                             alt="Vlog thumbnail" 
                                             class="vlog-thumbnail">
                                    <?php else: ?>
                                        <div class="no-thumbnail">No thumbnail</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($vlog['title']); ?></td>
                                <td>
                                    <span class="category-badge">
                                        <?php echo htmlspecialchars($vlog['category'] ?? 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($vlog['trainer_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($vlog['upload_date'])); ?></td>
                                <td class="stats-cell">
                                    <i class="fas fa-eye"></i>
                                    <?php echo number_format($vlog['view_count'] ?? 0); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $vlog['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($vlog['status']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="edit_vlog.php?id=<?php echo $vlog['vlog_id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this vlog?');">
                                        <input type="hidden" name="vlog_id" value="<?php echo $vlog['vlog_id']; ?>">
                                        <button type="submit" name="delete_vlog" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 