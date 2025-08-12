<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle trainer status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $trainer_id = (int)$_POST['trainer_id'];
    $status = $_POST['status'];
    
    // Check if the status column exists in the trainers table
    $check_column_query = "SHOW COLUMNS FROM trainers LIKE 'status'";
    $column_result = $conn->query($check_column_query);
    
    if ($column_result->num_rows > 0) {
        // The status column exists, update it
        $query = "UPDATE trainers SET status = ? WHERE trainer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $status, $trainer_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Trainer status updated successfully";
        } else {
            $_SESSION['error'] = "Error updating trainer status";
        }
    } else {
        // The status column doesn't exist, add it
        $add_column_query = "ALTER TABLE trainers ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'";
        if ($conn->query($add_column_query)) {
            // Now update the status
            $query = "UPDATE trainers SET status = ? WHERE trainer_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $status, $trainer_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Trainer status updated successfully";
            } else {
                $_SESSION['error'] = "Error updating trainer status";
            }
        } else {
            $_SESSION['error'] = "Error adding status column to trainers table";
        }
    }
    
    header('Location: manage_trainers.php');
    exit();
}

// Handle trainer deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trainer'])) {
    $trainer_id = (int)$_POST['trainer_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete trainer's classes
        $query = "DELETE FROM classes WHERE trainer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $trainer_id);
        $stmt->execute();
        
        // Delete trainer's appointments
        $query = "DELETE FROM appointments WHERE trainer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $trainer_id);
        $stmt->execute();
        
        // Delete trainer's user account
        $query = "DELETE u FROM users u 
                  JOIN trainers t ON u.id = t.user_id 
                  WHERE t.trainer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $trainer_id);
        $stmt->execute();
        
        // Delete trainer
        $query = "DELETE FROM trainers WHERE trainer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $trainer_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Trainer deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting trainer: " . $e->getMessage();
    }
    
    header('Location: manage_trainers.php');
    exit();
}

// Fetch all trainers with their user information
$query = "SELECT t.*, u.full_name, u.email, u.profile_image, 
          COALESCE(t.specialization, 'Not specified') as specialization,
          COALESCE(t.experience_years, 0) as experience
          FROM trainers t 
          JOIN users u ON t.user_id = u.id 
          ORDER BY u.full_name";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trainers - Admin Dashboard</title>
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

        .trainers-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .trainers-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .trainers-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
        }

        .trainers-table td {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .trainers-table tr:hover {
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

        .no-trainers {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-trainers i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .no-trainers h3 {
            font-size: 1.5em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-trainers p {
            color: #666;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .trainer-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--primary);
        }

        .no-thumbnail {
            width: 50px;
            height: 50px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            border: 2px solid #ddd;
        }

        .no-thumbnail i {
            font-size: 24px;
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

            .trainers-table {
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
            echo getAdminNav('manage_trainers.php');
            ?>
        </div>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Trainers</h1>
                <a href="add_trainer.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Trainer
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
                <input type="text" class="search-input" id="searchInput" placeholder="Search trainers...">
            </div>

            <div class="trainers-table">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Experience</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($trainer = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($trainer['profile_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($trainer['profile_image']); ?>" 
                                                 alt="Trainer Profile" 
                                                 class="trainer-thumbnail">
                                        <?php else: ?>
                                            <div class="no-thumbnail">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trainer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($trainer['specialization']); ?></td>
                                    <td><?php echo htmlspecialchars($trainer['experience']); ?> years</td>
                                    <td>
                                        <span class="status-badge <?php echo $trainer['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo ucfirst($trainer['status']); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="edit_trainer.php?id=<?php echo $trainer['trainer_id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this trainer?');">
                                            <input type="hidden" name="trainer_id" value="<?php echo $trainer['trainer_id']; ?>">
                                            <button type="submit" name="delete_trainer" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-trainers">
                        <i class="fas fa-user-tie"></i>
                        <h3>No Trainers Found</h3>
                        <p>There are no trainers registered in the system yet.</p>
                        <a href="add_trainer.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Trainer
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
            const rows = document.querySelectorAll('.trainers-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 