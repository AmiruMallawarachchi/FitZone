<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get class ID from URL
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($class_id <= 0) {
    $_SESSION['error'] = "Invalid class ID";
    header('Location: manage_classes.php');
    exit();
}

// Fetch class data
$query = "SELECT c.*, t.trainer_id, u.full_name as trainer_name 
          FROM classes c 
          LEFT JOIN trainers t ON c.trainer_id = t.trainer_id 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE c.class_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();

if (!$class) {
    $_SESSION['error'] = "Class not found";
    header('Location: manage_classes.php');
    exit();
}

// Fetch active trainers for the dropdown
$query = "SELECT t.trainer_id, u.full_name 
          FROM trainers t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.status = 'active' 
          ORDER BY u.full_name";
$trainers_result = $conn->query($query);

// Set default values for form fields
$class_name = $class['name'] ?? '';
$description = $class['description'] ?? '';
$duration = $class['duration_minutes'] ?? 60;
$capacity = $class['capacity'] ?? 20;
$price = $class['price'] ?? 0;
$schedule = $class['schedule_time'] ?? '';
$trainer_id = $class['trainer_id'] ?? null;
$status = $class['status'] ?? 'active';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name']);
    $description = trim($_POST['description']);
    $duration = (int)$_POST['duration'];
    $capacity = (int)$_POST['capacity'];
    $price = (float)$_POST['price'];
    $schedule = trim($_POST['schedule']);
    $trainer_id = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;
    $status = $_POST['status'] ?? 'active'; // Default to active if not set
    
    // Validate inputs
    if (empty($class_name)) {
        $errors[] = "Class name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if ($duration <= 0) {
        $errors[] = "Duration must be greater than 0";
    }
    
    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than 0";
    }
    
    if ($price < 0) {
        $errors[] = "Price cannot be negative";
    }
    
    if (empty($schedule)) {
        $errors[] = "Schedule is required";
    }
    
    // Validate status
    $valid_statuses = ['active', 'cancelled', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'active'; // Default to active if invalid status
    }
    
    if (empty($errors)) {
        // Handle image upload
        $image_url = $class['image_url']; // Keep existing image by default
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/classes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $errors[] = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
            } else {
                $new_filename = uniqid('class_') . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    // Delete old image if it exists and is not the default
                    if ($image_url && $image_url !== 'assets/images/default-class.jpg' && file_exists('../' . $image_url)) {
                        unlink('../' . $image_url);
                    }
                    $image_url = 'assets/images/classes/' . $new_filename;
                } else {
                    $errors[] = "Failed to upload image";
                }
            }
        }
        
        if (empty($errors)) {
            // Update class
            $query = "UPDATE classes 
                     SET name = ?, description = ?, image_url = ?, duration_minutes = ?, 
                         capacity = ?, price = ?, schedule_time = ?, trainer_id = ?, status = ? 
                     WHERE class_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssiidsisi", $class_name, $description, $image_url, $duration, 
                             $capacity, $price, $schedule, $trainer_id, $status, $class_id);
            
            // Debug: Print the status value
            error_log("Updating class status to: " . $status);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Class updated successfully";
                header("Location: manage_classes.php");
                exit();
            } else {
                $errors[] = "Error updating class: " . $conn->error;
                // Debug: Print the SQL error
                error_log("SQL Error: " . $conn->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Class - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b6b;
            --secondary: #4ecdc4;
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
            background: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: var(--primary);
            font-size: 1.5em;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: #f0f2f5;
        }

        .nav-item.active {
            background: var(--primary);
            color: white;
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        textarea.form-control {
            height: 100px;
            resize: vertical;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_members.php" class="nav-item">
                <i class="fas fa-users"></i>
                Manage Members
            </a>
            <a href="manage_trainers.php" class="nav-item">
                <i class="fas fa-dumbbell"></i>
                Manage Trainers
            </a>
            <a href="manage_membership.php" class="nav-item">
                <i class="fas fa-id-card"></i>
                Manage Memberships
            </a>
            <a href="manage_classes.php" class="nav-item active">
                <i class="fas fa-chalkboard-teacher"></i>
                Manage Classes
            </a>
            <a href="manage_appointments.php" class="nav-item">
                <i class="fas fa-calendar-check"></i>
                Manage Appointments
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Edit Class</h1>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="class_name">Class Name</label>
                        <input type="text" id="class_name" name="class_name" class="form-control" 
                               value="<?php echo htmlspecialchars($class_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($description); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="class_image">Class Image</label>
                        <?php if (!empty($class['image_url'])): ?>
                            <div class="current-image">
                                <img src="../<?php echo htmlspecialchars($class['image_url']); ?>" alt="Current Class Image" style="max-width: 200px; margin-bottom: 10px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        <small class="form-text text-muted">Recommended size: 800x600px. Max file size: 2MB</small>
                    </div>

                    <div class="form-group">
                        <label for="duration">Duration (minutes)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" 
                               value="<?php echo (int)$duration; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" min="1" 
                               value="<?php echo (int)$capacity; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (Rs.)</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" 
                               value="<?php echo (float)$price; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="schedule">Schedule</label>
                        <input type="text" id="schedule" name="schedule" class="form-control" 
                               placeholder="e.g., Monday, Wednesday, Friday at 10:00 AM" 
                               value="<?php echo htmlspecialchars($schedule); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="trainer_id">Trainer (Optional)</label>
                        <select id="trainer_id" name="trainer_id" class="form-control">
                            <option value="">Select a trainer</option>
                            <?php while ($trainer = $trainers_result->fetch_assoc()): ?>
                                <option value="<?php echo $trainer['trainer_id']; ?>" 
                                    <?php echo ($trainer_id == $trainer['trainer_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trainer['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="manage_classes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 