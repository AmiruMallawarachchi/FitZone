<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'trainer') {
    header("Location: ../login.php");
    exit();
}

// Check if profile_image column exists in trainers table, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM trainers LIKE 'profile_image'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE trainers ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
}

// Get trainer ID from the trainers table
$query = "SELECT t.*, u.full_name, u.email 
          FROM trainers t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$trainer = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $specialization = trim($_POST['specialization']);
    $experience_years = (int)$_POST['experience_years'];
    $bio = trim($_POST['bio']);
    $availability = trim($_POST['availability']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($specialization)) {
        $errors[] = "Specialization is required";
    }
    
    if ($experience_years < 0) {
        $errors[] = "Experience years cannot be negative";
    }
    
    if (empty($bio)) {
        $errors[] = "Bio is required";
    }
    
    // Handle profile image upload
    $profile_image = $trainer['profile_image']; // Keep existing image by default
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $errors[] = "File size too large. Maximum size is 5MB.";
        } else {
            $upload_dir = '../uploads/profile_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'trainer_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                // Delete old profile image if exists
                if (!empty($trainer['profile_image']) && file_exists('../' . $trainer['profile_image'])) {
                    unlink('../' . $trainer['profile_image']);
                }
                
                $profile_image = 'uploads/profile_images/' . $new_filename;
                
                // Update the profile_image in the trainers table
                $update_image_query = "UPDATE trainers SET profile_image = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_image_query);
                $update_stmt->bind_param("si", $profile_image, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    // Refresh trainer data to get the updated profile image
                    $query = "SELECT t.*, u.full_name, u.email 
                              FROM trainers t 
                              JOIN users u ON t.user_id = u.id 
                              WHERE t.user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $trainer = $result->fetch_assoc();
                    
                    $_SESSION['success'] = "Profile image updated successfully";
                } else {
                    $errors[] = "Failed to update profile image in database";
                }
            } else {
                $errors[] = "Failed to upload profile image";
            }
        }
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $full_name, $email, $_SESSION['user_id']);
            $stmt->execute();
            
            // Update trainers table
            $stmt = $conn->prepare("UPDATE trainers SET specialization = ?, experience_years = ?, bio = ?, availability = ? WHERE user_id = ?");
            $stmt->bind_param("sissi", $specialization, $experience_years, $bio, $availability, $_SESSION['user_id']);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Profile updated successfully";
            
            // Refresh trainer data
            $query = "SELECT t.*, u.full_name, u.email 
                      FROM trainers t 
                      JOIN users u ON t.user_id = u.id 
                      WHERE t.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $trainer = $result->fetch_assoc();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="trainer_styles.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-image-container {
            position: relative;
            width: 150px;
            height: 150px;
        }
        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #3498db;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: #f8f9fa;
        }
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .profile-image-placeholder {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 48px;
        }
        .profile-image-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #3498db;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1;
        }
        .profile-image-upload:hover {
            background: #2980b9;
            transform: scale(1.1);
        }
        .profile-image-upload i {
            color: white;
            font-size: 18px;
        }
        .profile-info {
            flex: 1;
        }
        .profile-name {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .profile-email {
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        .profile-stats {
            display: flex;
            gap: 20px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #3498db;
        }
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
        }
        .form-section {
            background: #fff;
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
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
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
            <a href="manage_classes.php" class="nav-item">
                <i class="fas fa-dumbbell"></i> Classes
            </a>
            <a href="profile.php" class="nav-item active">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">My Profile</h1>
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

            <div class="profile-container">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="profile-header">
                        <div class="profile-image-container">
                            <div class="profile-image">
                                <?php if (!empty($trainer['profile_image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($trainer['profile_image']); ?>" alt="Profile Image" id="profile-preview">
                                <?php else: ?>
                                    <div class="profile-image-placeholder" id="profile-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label for="profile_image" class="profile-image-upload">
                                <i class="fas fa-camera"></i>
                                <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif" style="display: none;">
                            </label>
                        </div>
                        <div class="profile-info">
                            <h2 class="profile-name"><?php echo htmlspecialchars($trainer['full_name']); ?></h2>
                            <div class="profile-email"><?php echo htmlspecialchars($trainer['email']); ?></div>
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $trainer['experience_years'] ?? '0'; ?></div>
                                    <div class="stat-label">Years Experience</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $trainer['specialization'] ?? 'Not Set'; ?></div>
                                    <div class="stat-label">Specialization</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-user-edit"></i> Personal Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($trainer['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($trainer['email']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" class="form-control" 
                                       value="<?php echo htmlspecialchars($trainer['specialization'] ?? ''); ?>" required>
                                <div class="help-text">e.g., Yoga, CrossFit, Personal Training</div>
                            </div>
                            <div class="form-group">
                                <label for="experience_years">Years of Experience</label>
                                <input type="number" id="experience_years" name="experience_years" class="form-control" min="0" 
                                       value="<?php echo htmlspecialchars($trainer['experience_years'] ?? '0'); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="availability">Availability</label>
                            <input type="text" id="availability" name="availability" class="form-control" 
                                   value="<?php echo htmlspecialchars($trainer['availability'] ?? ''); ?>" 
                                   placeholder="Example: Mon-Fri 9AM-5PM, Sat 10AM-2PM" required>
                            <div class="help-text">Specify your available hours for appointments and classes</div>
                        </div>
                        <div class="form-group">
                            <label for="bio">Professional Bio</label>
                            <textarea id="bio" name="bio" class="form-control" rows="4" required><?php echo htmlspecialchars($trainer['bio'] ?? ''); ?></textarea>
                            <div class="help-text">Tell clients about your experience, expertise, and training philosophy</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Preview profile image before upload
        document.getElementById('profile_image').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const profileImage = document.querySelector('.profile-image img');
                    const placeholder = document.querySelector('.profile-image-placeholder');
                    
                    if (profileImage) {
                        profileImage.src = e.target.result;
                        profileImage.style.display = 'block';
                    } else if (placeholder) {
                        placeholder.innerHTML = `<img src="${e.target.result}" alt="Profile Image" id="profile-preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    }
                }
                
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html> 