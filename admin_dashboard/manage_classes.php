<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle class deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // First, get the image URL to delete the file
    $query = "SELECT image_url FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $class = $result->fetch_assoc();
    
    // Delete the class
    $query = "DELETE FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        // Delete the image file if it exists and is not the default image
        if ($class && $class['image_url'] && $class['image_url'] !== 'assets/images/default-class.jpg' && file_exists('../' . $class['image_url'])) {
            unlink('../' . $class['image_url']);
        }
        $_SESSION['success'] = "Class deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting class: " . $conn->error;
    }
    
    header("Location: manage_classes.php");
    exit();
}

// Handle status update
if (isset($_POST['class_id']) && isset($_POST['status'])) {
    $class_id = (int)$_POST['class_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE classes SET status = ? WHERE class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $class_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Class status updated successfully";
    } else {
        $_SESSION['error'] = "Error updating class status";
    }
    
    header('Location: manage_classes.php');
    exit();
}

// Fetch all classes with trainer information
$query = "SELECT c.*, t.trainer_id, u.full_name as trainer_name 
          FROM classes c 
          LEFT JOIN trainers t ON c.trainer_id = t.trainer_id 
          LEFT JOIN users u ON t.user_id = u.id 
          ORDER BY c.class_id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - Admin Dashboard</title>
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

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .class-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }

        .class-image {
            height: 200px;
            overflow: hidden;
        }

        .class-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .class-details {
            padding: 20px;
            flex: 1;
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .class-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: 1.2em;
        }

        .description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .class-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
        }

        .info-item i {
            color: var(--primary);
        }

        .class-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .meta-item i {
            color: var(--secondary);
        }

        .class-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status.active {
            background: #e3fcef;
            color: #00a854;
        }

        .status.inactive {
            background: #fff3f3;
            color: #f5222d;
        }

        .status.completed {
            background: #e6f7ff;
            color: #1890ff;
        }

        .status.cancelled {
            background: #f5f5f5;
            color: #8c8c8c;
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

        .no-classes {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-classes i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .no-classes h3 {
            font-size: 1.5em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-classes p {
            color: #666;
            margin-bottom: 20px;
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

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .classes-grid {
                grid-template-columns: 1fr;
            }

            .class-info, .class-meta {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <?php 
            require_once 'admin_nav.php';
            echo getAdminNav('manage_classes.php');
            ?>
        </div>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Classes</h1>
                <a href="add_class.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Class
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
                <input type="text" class="search-input" id="searchInput" placeholder="Search classes...">
            </div>

            <div class="classes-grid">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($class = $result->fetch_assoc()): ?>
                        <div class="class-card">
                            <div class="class-image">
                                <?php if (!empty($class['image_url'])): ?>
                                    <img src="../<?php echo htmlspecialchars($class['image_url']); ?>" alt="<?php echo htmlspecialchars($class['name']); ?>">
                                <?php else: ?>
                                    <img src="../assets/images/default-class.jpg" alt="Default Class Image">
                                <?php endif; ?>
                            </div>
                            <div class="class-details">
                                <div class="class-header">
                                    <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                                    <span class="status <?php echo $class['status']; ?>">
                                        <?php echo ucfirst($class['status']); ?>
                                    </span>
                                </div>
                                <p class="description"><?php echo htmlspecialchars($class['description']); ?></p>
                                <div class="class-info">
                                    <div class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo (int)$class['duration_minutes']; ?> minutes</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-users"></i>
                                        <span>Capacity: <?php echo (int)$class['capacity']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-rupee-sign"></i>
                                        <span>Rs. <?php echo number_format((float)$class['price'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="class-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo htmlspecialchars($class['schedule_time']); ?></span>
                                    </div>
                                    <?php if (!empty($class['trainer_name'])): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-user-tie"></i>
                                            <span><?php echo htmlspecialchars($class['trainer_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="class-actions">
                                <a href="edit_class.php?id=<?php echo $class['class_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="manage_classes.php?delete_id=<?php echo $class['class_id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this class?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-classes">
                        <i class="fas fa-chalkboard"></i>
                        <h3>No Classes Found</h3>
                        <p>There are no classes in the system.</p>
                        <a href="add_class.php" class="btn btn-primary">Add New Class</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const cards = document.querySelectorAll('.class-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 