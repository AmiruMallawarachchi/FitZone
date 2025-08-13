<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'trainer') {
    header('Location: ../login.php');
    exit();
}

// Get trainer ID
$query = "SELECT trainer_id FROM trainers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$trainer = $result->fetch_assoc();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $capacity = (int)$_POST['capacity'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $price = (float)$_POST['price'];
    $schedule_time = $_POST['schedule_time'];
    $status = $_POST['status'];

    // Validate input
    if (empty($name)) {
        $errors[] = "Class name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than 0";
    }
    
    if ($duration_minutes <= 0) {
        $errors[] = "Duration must be greater than 0";
    }
    
    if ($price < 0) {
        $errors[] = "Price cannot be negative";
    }
    
    if (empty($schedule_time)) {
        $errors[] = "Schedule time is required";
    }

    // If no errors, proceed with insertion
    if (empty($errors)) {
        try {
            // Insert into classes table
            $query = "INSERT INTO classes (name, description, trainer_id, capacity, duration_minutes, price, schedule_time, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssiiidss", $name, $description, $trainer['trainer_id'], $capacity, $duration_minutes, $price, $schedule_time, $status);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Class added successfully!";
                header('Location: manage_classes.php');
                exit();
            } else {
                $errors[] = "Failed to add class. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Class - Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="trainer_styles.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h3 i {
            color: #3498db;
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
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.2);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
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
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }
        
        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee;
            color: #c0392b;
            border: 1px solid #fcc;
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
                <h1 class="page-title">Add New Class</h1>
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

            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Class Name</label>
                                <input type="text" id="name" name="name" class="form-control" required
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <div class="help-text">Describe the class, its benefits, and what participants can expect</div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-clock"></i> Schedule & Duration</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="schedule_time">Schedule Time</label>
                                <input type="datetime-local" id="schedule_time" name="schedule_time" class="form-control" required
                                       value="<?php echo isset($_POST['schedule_time']) ? htmlspecialchars($_POST['schedule_time']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="duration_minutes">Duration (minutes)</label>
                                <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="15" step="15" required
                                       value="<?php echo isset($_POST['duration_minutes']) ? htmlspecialchars($_POST['duration_minutes']) : '60'; ?>">
                                <div class="help-text">Minimum 15 minutes, in 15-minute increments</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-users"></i> Capacity & Pricing</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="capacity">Maximum Capacity</label>
                                <input type="number" id="capacity" name="capacity" class="form-control" min="1" required
                                       value="<?php echo isset($_POST['capacity']) ? htmlspecialchars($_POST['capacity']) : '10'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required
                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '0.00'; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Class
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
        // Set minimum datetime to current time
        const scheduleTimeInput = document.getElementById('schedule_time');
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        scheduleTimeInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
    </script>
</body>
</html> 