<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch active trainers for the dropdown
$query = "SELECT t.trainer_id, u.full_name 
          FROM trainers t 
          JOIN users u ON t.user_id = u.id 
          ORDER BY u.full_name";
$trainers_result = $conn->query($query);

// Ensure your SELECT query includes duration_minutes and schedule_time
$query = "SELECT name, description, trainer_id, capacity, duration_minutes AS duration, schedule_time AS schedule, status 
          FROM classes";
$result = $conn->query($query);

// Fetch data and ensure keys exist
while ($row = $result->fetch_assoc()) {
    $class_name = htmlspecialchars($row['name'] ?? '');
    $description = htmlspecialchars($row['description'] ?? '');
    $duration = $row['duration'] ?? 0; // Default to 0 if undefined
    $capacity = $row['capacity'] ?? 0; // Default to 0 if undefined
    $schedule = htmlspecialchars($row['schedule'] ?? ''); // Default to empty string if undefined
    $status = htmlspecialchars($row['status'] ?? '');
}

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
    if (!in_array($status, ['active', 'cancelled', 'completed'])) {
        $status = 'active'; // Default to active if invalid status
    }
    
    if (empty($errors)) {
        // Handle image upload
        $image_url = 'assets/images/default-class.jpg'; // Default image
        
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
                    $image_url = 'assets/images/classes/' . $new_filename;
                } else {
                    $errors[] = "Failed to upload image";
                }
            }
        }
        
        if (empty($errors)) {
            // Insert new class
            $query = "INSERT INTO classes (name, description, image_url, duration_minutes, 
                                         capacity, price, schedule_time, trainer_id, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssiidsss", $class_name, $description, $image_url, $duration, 
                             $capacity, $price, $schedule, $trainer_id, $status);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Class added successfully";
                header("Location: manage_classes.php");
                exit();
            } else {
                $errors[] = "Error adding class: " . $conn->error;
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
    <title>Add New Class - Admin Dashboard</title>
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
                <h1 class="page-title">Add New Class</h1>
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
                               value="<?php echo isset($_POST['class_name']) ? htmlspecialchars($_POST['class_name']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image">Class Image</label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        <small class="form-text text-muted">Recommended size: 800x600px. Max file size: 2MB</small>
                    </div>

                    <div class="form-group">
                        <label for="duration">Duration (minutes)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" 
                               value="<?php echo isset($_POST['duration']) ? (int)$_POST['duration'] : '60'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" min="1" 
                               value="<?php echo isset($_POST['capacity']) ? (int)$_POST['capacity'] : '20'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (Rs.)</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" 
                               value="<?php echo isset($_POST['price']) ? (float)$_POST['price'] : '0.00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="schedule">Schedule</label>
                        <input type="text" id="schedule" name="schedule" class="form-control" 
                               placeholder="e.g., Monday, Wednesday, Friday at 10:00 AM" 
                               value="<?php echo isset($_POST['schedule']) ? htmlspecialchars($_POST['schedule']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="trainer_id">Trainer (Optional)</label>
                        <select id="trainer_id" name="trainer_id" class="form-control">
                            <option value="">Select a trainer</option>
                            <?php while ($trainer = $trainers_result->fetch_assoc()): ?>
                                <option value="<?php echo $trainer['trainer_id']; ?>" 
                                    <?php echo (isset($_POST['trainer_id']) && $_POST['trainer_id'] == $trainer['trainer_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trainer['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" selected>Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
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
</body>
</html>