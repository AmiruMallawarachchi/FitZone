<?php
session_start();
require_once '../config.php';

// Increase upload limits for large video files
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Check if status column exists in trainers table
$check_column_query = "SHOW COLUMNS FROM trainers LIKE 'status'";
$column_result = $conn->query($check_column_query);

if ($column_result->num_rows === 0) {
    // Add status column if it doesn't exist
    $add_column_query = "ALTER TABLE trainers ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'";
    if (!$conn->query($add_column_query)) {
        $errors[] = "Error adding status column to trainers table: " . $conn->error;
    }
}

// Fetch active trainers for the dropdown
$trainers_query = "SELECT t.trainer_id, u.full_name 
                  FROM trainers t 
                  JOIN users u ON t.user_id = u.id 
                  ORDER BY u.full_name";
$trainers_result = $conn->query($trainers_query);

if (!$trainers_result) {
    $errors[] = "Error fetching trainers: " . $conn->error;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $trainer_id = $_POST['trainer_id'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Handle thumbnail upload if provided
    $thumbnail_url = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $target_dir = "../uploads/thumbnails/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["thumbnail"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check file type
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array($file_extension, $allowed_types)) {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } else {
            if (move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $target_file)) {
                $thumbnail_url = "uploads/thumbnails/" . $new_filename;
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($trainer_id)) {
        $error = "Please fill in all required fields";
    } else {
        // Insert vlog into database
        $query = "INSERT INTO vlogs (title, description, category, video_url, thumbnail_url, trainer_id, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssss", $title, $description, $category, $_POST['video_url'], $thumbnail_url, $trainer_id, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Vlog added successfully";
            header('Location: manage_vlogs.php');
            exit();
        } else {
            $error = "Error adding vlog: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vlog - FitZone Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #50c878;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --danger: #e74c3c;
            --warning: #f1c40f;
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
            color: var(--dark);
            font-size: 24px;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>FitZone Admin</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_members.php" class="nav-item">
                <i class="fas fa-users"></i> Manage Members
            </a>
            <a href="manage_trainers.php" class="nav-item">
                <i class="fas fa-dumbbell"></i> Manage Trainers
            </a>
            <a href="manage_membership.php" class="nav-item">
                <i class="fas fa-id-card"></i> Manage Memberships
            </a>
            <a href="manage_classes.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i> Manage Classes
            </a>
            <a href="manage_vlogs.php" class="nav-item active">
                <i class="fas fa-video"></i> Manage Vlogs
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Add New Vlog</h1>
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
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Workouts" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Workouts') ? 'selected' : ''; ?>>Workouts</option>
                            <option value="Nutrition" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Nutrition') ? 'selected' : ''; ?>>Nutrition</option>
                            <option value="Tips & Tricks" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Tips & Tricks') ? 'selected' : ''; ?>>Tips & Tricks</option>
                            <option value="Success Stories" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Success Stories') ? 'selected' : ''; ?>>Success Stories</option>
                            <option value="Others" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Others') ? 'selected' : ''; ?>>Others</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="video_url">Video URL</label>
                        <input type="url" id="video_url" name="video_url" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="trainer_id">Trainer</label>
                        <select id="trainer_id" name="trainer_id" class="form-control">
                            <option value="">Select Trainer</option>
                            <?php 
                            if ($trainers_result && $trainers_result->num_rows > 0) {
                                while ($trainer = $trainers_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $trainer['trainer_id']; ?>">
                                    <?php echo htmlspecialchars($trainer['full_name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            } else {
                                echo '<option value="" disabled>No active trainers found</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="thumbnail">Thumbnail Image (Optional)</label>
                        <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                        <small class="form-text text-muted">Recommended size: 1280x720px. Supported formats: JPG, JPEG, PNG, GIF</small>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Vlog
                        </button>
                        <a href="manage_vlogs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 