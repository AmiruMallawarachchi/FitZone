<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$errors = [];
$success = false;

// Get trainer ID from URL
$trainer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($trainer_id <= 0) {
    $_SESSION['error'] = "Invalid trainer ID";
    header('Location: manage_trainers.php');
    exit();
}

// Fetch trainer data
$query = "SELECT t.trainer_id, t.user_id, t.specialization, t.experience_years, t.status, 
                 u.id as user_id, u.email, u.full_name, u.profile_image 
          FROM trainers t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.trainer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$result = $stmt->get_result();
$trainer = $result->fetch_assoc();

if (!$trainer) {
    $_SESSION['error'] = "Trainer not found";
    header('Location: manage_trainers.php');
    exit();
}

// Set default values for null fields
$trainer['email'] = $trainer['email'] ?? '';
$trainer['specialization'] = $trainer['specialization'] ?? '';
$trainer['experience_years'] = $trainer['experience_years'] ?? 0;
$trainer['status'] = $trainer['status'] ?? 'active';
$trainer['full_name'] = $trainer['full_name'] ?? '';
$trainer['profile_image'] = $trainer['profile_image'] ?? '';
$trainer['user_id'] = $trainer['user_id'] ?? 0;

// Debug information
error_log("Trainer Data: " . print_r($trainer, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = trim($_POST['email']);
    $specialization = trim($_POST['specialization']);
    $years_experience = (int)$_POST['years_experience'];
    $status = $_POST['status'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Debug form data
    error_log("Form Data: " . print_r($_POST, true));

    // Handle profile image upload
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "../uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check file type
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array($file_extension, $allowed_types)) {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } else {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $profile_image = "uploads/profiles/" . $new_filename;
                
                // Delete old profile image if exists
                if (!empty($trainer['profile_image']) && file_exists("../" . $trainer['profile_image'])) {
                    unlink("../" . $trainer['profile_image']);
                }
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }

    // Validate input
    if (empty($email)) $errors[] = "Email is required";
    if (empty($specialization)) $errors[] = "Specialization is required";
    if ($years_experience < 0) $errors[] = "Years of experience cannot be negative";
    if (empty($username)) $errors[] = "Username is required";
    if (!empty($password) && $password !== $confirm_password) $errors[] = "Passwords do not match";

    // Check if email is already taken by another user
    $check_email_query = "SELECT id FROM users WHERE email = ? AND id != ?";
    $check_stmt = $conn->prepare($check_email_query);
    $check_stmt->bind_param("si", $email, $trainer['user_id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error = "Email is already taken by another user.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update users table
            if (!empty($password)) {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                if ($profile_image) {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, profile_image = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $username, $email, $hashed_password, $profile_image, $trainer['user_id']);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $email, $hashed_password, $trainer['user_id']);
                }
            } else {
                if ($profile_image) {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, profile_image = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $email, $profile_image, $trainer['user_id']);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $username, $email, $trainer['user_id']);
                }
            }
            $stmt->execute();
            
            // Update trainers table with trainer-specific information
            $stmt = $conn->prepare("UPDATE trainers SET specialization = ?, experience_years = ?, status = ? WHERE trainer_id = ?");
            $stmt->bind_param("sisi", $specialization, $years_experience, $status, $trainer_id);
            $stmt->execute();
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = "Trainer updated successfully!";
            header("Location: manage_trainers.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating trainer: " . $e->getMessage();
            error_log("Update Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Trainer - FitZone Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8em;
            color: var(--dark);
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
            background: #357abd;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .error-list {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .error-list ul {
            margin-left: 20px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .password-note {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .current-image {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--primary);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8em;
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
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_members.php" class="nav-item">
                <i class="fas fa-users"></i>
                Manage Members
            </a>
            <a href="manage_trainers.php" class="nav-item active">
                <i class="fas fa-dumbbell"></i>
                Manage Trainers
            </a>
            <a href="manage_membership.php" class="nav-item">
                <i class="fas fa-id-card"></i>
                Manage Memberships
            </a>
            <a href="manage_classes.php" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                Manage Classes
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Edit Trainer</h1>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-list">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($trainer['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($trainer['specialization']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="years_experience">Years of Experience</label>
                            <input type="number" id="years_experience" name="years_experience" value="<?php echo (int)$trainer['experience_years']; ?>" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Full Name</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($trainer['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo $trainer['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $trainer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password">
                            <div class="password-note">Leave blank to keep current password</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>

                        <div class="form-group">
                            <label for="profile_image">Profile Picture</label>
                            <?php if (!empty($trainer['profile_image'])): ?>
                                <div class="current-image">
                                    <img src="../<?php echo htmlspecialchars($trainer['profile_image']); ?>" 
                                         alt="Current Profile" 
                                         class="profile-preview">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeProfileImage()">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                            <small class="form-text text-muted">Leave empty to keep current image. Recommended size: 500x500px. Supported formats: JPG, JPEG, PNG, GIF</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Trainer</button>
                        <a href="manage_trainers.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function removeProfileImage() {
            if (confirm('Are you sure you want to remove the profile picture?')) {
                // Add a hidden input to indicate image removal
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_profile_image';
                input.value = '1';
                document.querySelector('form').appendChild(input);
                
                // Hide the current image preview
                document.querySelector('.current-image').style.display = 'none';
            }
        }
    </script>
</body>
</html> 