<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'trainer') {
    header("Location: ../login.php");
    exit();
}

// Get trainer ID from the trainers table
$query = "SELECT trainer_id FROM trainers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$trainer_data = $result->fetch_assoc();
$trainer_id = $trainer_data['trainer_id'];

// Get class ID from URL
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch class details
$stmt = $conn->prepare("SELECT * FROM classes WHERE class_id = ? AND trainer_id = ?");
$stmt->bind_param("ii", $class_id, $trainer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Class not found or you don't have permission to edit it";
    header("Location: manage_classes.php");
    exit();
}

$class = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $schedule_time = $_POST['schedule_time'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $capacity = (int)$_POST['capacity'];
    $status = $_POST['status'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Class name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($schedule_time)) {
        $errors[] = "Schedule time is required";
    } else {
        // Check if the schedule time is in the future (unless status is cancelled)
        $schedule_timestamp = strtotime($schedule_time);
        if ($schedule_timestamp < time() && $status !== 'cancelled') {
            $errors[] = "Schedule time must be in the future";
        }
    }
    
    if ($duration_minutes <= 0) {
        $errors[] = "Duration must be greater than 0";
    }
    
    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than 0";
    }
    
    // If no errors, update the class
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE classes SET name = ?, description = ?, schedule_time = ?, duration_minutes = ?, capacity = ?, status = ? WHERE class_id = ? AND trainer_id = ?");
        $stmt->bind_param("sssiissi", $name, $description, $schedule_time, $duration_minutes, $capacity, $status, $class_id, $trainer_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Class updated successfully";
            header("Location: manage_classes.php");
            exit();
        } else {
            $errors[] = "Failed to update class: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Class - Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="trainer_styles.css">
    <style>
        .class-form {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-section {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            flex: 1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
        }
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-in_progress { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e8f5e9; color: #388e3c; }
        .status-cancelled { background: #ffebee; color: #d32f2f; }
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #fee;
            color: #c0392b;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Trainer Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_appointments.php" class="nav-item">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="manage_classes.php" class="nav-item active">
                <i class="fas fa-dumbbell"></i> Classes
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Edit Class</h1>
                <div class="header-actions">
                    <a href="manage_classes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Classes
                    </a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="class-form">
                <form method="POST" action="">
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                        <div class="form-group">
                            <label for="name">Class Name</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($class['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($class['description']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-clock"></i> Schedule & Capacity</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="schedule_time">Schedule Time</label>
                                <input type="datetime-local" id="schedule_time" name="schedule_time" class="form-control" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($class['schedule_time'])); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration_minutes">Duration (minutes)</label>
                                <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="1" 
                                       value="<?php echo isset($class['duration_minutes']) ? (int)$class['duration_minutes'] : '60'; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="capacity">Capacity</label>
                                <input type="number" id="capacity" name="capacity" class="form-control" min="1" 
                                       value="<?php echo $class['capacity']; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-toggle-on"></i> Status</h3>
                        <div class="form-group">
                            <label for="status">Class Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="scheduled" <?php echo $class['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="in_progress" <?php echo $class['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $class['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $class['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Class
                        </button>
                        <a href="manage_classes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Set min datetime to current time
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            document.getElementById('schedule_time').min = minDateTime;

            // Add status badge styling
            const statusSelect = document.getElementById('status');
            const statusBadge = document.createElement('span');
            statusBadge.className = 'status-badge';
            statusSelect.parentNode.insertBefore(statusBadge, statusSelect.nextSibling);

            function updateStatusBadge() {
                const status = statusSelect.value;
                statusBadge.className = 'status-badge status-' + status;
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
            }

            statusSelect.addEventListener('change', updateStatusBadge);
            updateStatusBadge();
        });
    </script>
</body>
</html> 