<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $duration_months = intval($_POST['duration_months']);
    $features = isset($_POST['features']) ? json_encode(array_map('trim', $_POST['features'])) : '[]';
    $status = $_POST['status'];

    // Validate input
    $errors = [];
    if (empty($name)) $errors[] = "Name is required";
    if (empty($description)) $errors[] = "Description is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    if ($duration_months <= 0) $errors[] = "Duration must be greater than 0";
    if ($features === '[]') $errors[] = "At least one feature is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO memberships (name, description, price, duration_months, features, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdiss", $name, $description, $price, $duration_months, $features, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Membership plan added successfully.";
            header("Location: manage_membership.php");
            exit();
        } else {
            $errors[] = "Error adding membership plan: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Membership Plan - FitZone Admin</title>
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

        .dashboard {
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

        .header {
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title i {
            font-size: 24px;
            color: var(--primary);
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
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .features-container {
            margin-top: 10px;
        }

        .feature-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .feature-input input {
            flex: 1;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .preview-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 30px;
            display: none;
        }

        .preview-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-align: center;
        }

        .preview-body {
            padding: 20px;
        }

        .preview-features {
            margin-top: 20px;
        }

        .preview-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #555;
        }

        .preview-feature i {
            color: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>FitZone Admin</h2>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
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
                <a href="manage_membership.php" class="nav-item active">
                    <i class="fas fa-id-card"></i>
                    Manage Membership
                </a>
                <a href="manage_shop.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Manage Shop
                </a>
                <a href="manage_classes.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    Manage Classes
                </a>
                <a href="manage_vlog.php" class="nav-item">
                    <i class="fas fa-video"></i>
                    Manage Vlog
                </a>
                <a href="manage_queries.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    Manage Queries
                </a>
                <a href="../logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
        <div class="main-content">
            <div class="header">
                <div class="page-title">
                    <i class="fas fa-plus-circle"></i>
                    <h1>Add New Membership Plan</h1>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="" id="membershipForm">
                    <div class="form-group">
                        <label for="name">Plan Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (Rs.)</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="duration_months">Duration (months)</label>
                        <input type="number" id="duration_months" name="duration_months" class="form-control" min="1" value="<?php echo isset($_POST['duration_months']) ? htmlspecialchars($_POST['duration_months']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Features</label>
                        <div class="features-container" id="featuresContainer">
                            <div class="feature-input">
                                <input type="text" name="features[]" class="form-control" placeholder="Enter a feature" required>
                                <button type="button" class="btn btn-danger" onclick="removeFeature(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addFeature()" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add Feature
                        </button>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Plan
                        </button>
                        <a href="manage_membership.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <div class="preview-card" id="previewCard">
                <div class="preview-header">
                    <h3 id="previewName"></h3>
                    <div id="previewPrice"></div>
                    <div id="previewDuration"></div>
                </div>
                <div class="preview-body">
                    <p id="previewDescription"></p>
                    <div class="preview-features" id="previewFeatures"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addFeature() {
            const container = document.getElementById('featuresContainer');
            const featureInput = document.createElement('div');
            featureInput.className = 'feature-input';
            featureInput.innerHTML = `
                <input type="text" name="features[]" class="form-control" placeholder="Enter a feature" required>
                <button type="button" class="btn btn-danger" onclick="removeFeature(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(featureInput);
        }

        function removeFeature(button) {
            const container = document.getElementById('featuresContainer');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }

        // Preview functionality
        const form = document.getElementById('membershipForm');
        const previewCard = document.getElementById('previewCard');

        form.addEventListener('input', updatePreview);

        function updatePreview() {
            const name = document.getElementById('name').value;
            const description = document.getElementById('description').value;
            const price = document.getElementById('price').value;
            const duration = document.getElementById('duration_months').value;
            const features = Array.from(document.getElementsByName('features[]')).map(input => input.value);

            if (name && description && price && duration && features.some(f => f)) {
                document.getElementById('previewName').textContent = name;
                document.getElementById('previewPrice').textContent = `$${parseFloat(price).toFixed(2)}`;
                document.getElementById('previewDuration').textContent = `${duration} months`;
                document.getElementById('previewDescription').textContent = description;

                const featuresContainer = document.getElementById('previewFeatures');
                featuresContainer.innerHTML = features
                    .filter(f => f.trim())
                    .map(feature => `
                        <div class="preview-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>${feature}</span>
                        </div>
                    `).join('');

                previewCard.style.display = 'block';
            } else {
                previewCard.style.display = 'none';
            }
        }
    </script>
</body>
</html> 