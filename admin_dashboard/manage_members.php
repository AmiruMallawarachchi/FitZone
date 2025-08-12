<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle member status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $member_id = (int)$_POST['member_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE members SET membership_status = ? WHERE member_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $member_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Member status updated successfully";
    } else {
        $_SESSION['error'] = "Error updating member status";
    }
    
    header('Location: manage_members.php');
    exit();
}

// Handle member deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_member'])) {
    $member_id = (int)$_POST['member_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get user_id for this member
        $query = "SELECT user_id FROM members WHERE member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();
        $user_id = $member['user_id'];
        
        // Delete member's bookings
        $query = "DELETE FROM class_bookings WHERE member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        // Delete member's appointments
        $query = "DELETE FROM appointments WHERE member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        // Delete member's subscriptions
        $query = "DELETE FROM member_subscriptions WHERE member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        // Delete member's queries
        $query = "DELETE FROM queries WHERE member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        // Delete member
        $query = "DELETE FROM members WHERE member_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        
        // Delete user account
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Member deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting member: " . $e->getMessage();
    }
    
    header('Location: manage_members.php');
    exit();
}

// Fetch all members
$query = "SELECT m.*, u.email, u.full_name, u.created_at 
          FROM members m 
          JOIN users u ON m.user_id = u.id 
          ORDER BY u.full_name";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - Admin Dashboard</title>
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
            padding: 20px;
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
        }

        .page-title {
            font-size: 32px;
        }

        .members-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .members-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .members-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
        }

        .members-table td {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .members-table tr:hover {
            background: #f8f9fa;
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

        .btn {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #f39c12;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .no-members {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-members i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .no-members h3 {
            font-size: 1.5em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-members p {
            color: #666;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .members-table {
                overflow-x: auto;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <?php 
            require_once 'admin_nav.php';
            echo getAdminNav('manage_members.php');
            ?>
        </div>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Members</h1>
                <a href="add_member.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Member
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
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="search-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="Search members...">
            </div>

            <div class="members-table">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Username</th>
                                <th>Join Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($member = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($member['email']); ?></div>
                                        <div><?php echo isset($member['phone']) && $member['phone'] !== null ? htmlspecialchars($member['phone']) : 'N/A'; ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($member['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $member['membership_status']; ?>">
                                            <?php echo ucfirst($member['membership_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($member['membership_status'] === 'active'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                    <input type="hidden" name="status" value="inactive">
                                                    <button type="submit" name="update_status" class="btn btn-warning">
                                                        <i class="fas fa-pause"></i> Deactivate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" name="update_status" class="btn btn-success">
                                                        <i class="fas fa-play"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this member? This action cannot be undone.');">
                                                <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                <button type="submit" name="delete_member" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-members">
                        <i class="fas fa-users"></i>
                        <h3>No Members Found</h3>
                        <p>There are no members registered in the system yet.</p>
                        <a href="add_member.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Member
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.members-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 