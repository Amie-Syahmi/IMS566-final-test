<?php

require_once 'config.php';
requireLogin();

require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$app_id = $_GET['id'];

// Get application details
$app_query = "SELECT a.*, 
              (SELECT rating FROM comments WHERE application_id = a.id LIMIT 1) AS rating
              FROM applications a 
              WHERE a.id = ?";
$app_stmt = mysqli_prepare($conn, $app_query);
mysqli_stmt_bind_param($app_stmt, 'i', $app_id);
mysqli_stmt_execute($app_stmt);
$app_result = mysqli_stmt_get_result($app_stmt);
$application = mysqli_fetch_assoc($app_result);

if (!$application) {
    header('Location: index.php');
    exit();
}

// Initialize variables
$title = $application['title'];
$review = $application['review'];
$author = $application['author'];
$category_id = $application['category_id'];
$status = $application['status'];
$rating = $application['rating'] ?? 0;
$errors = [];

// Fetch categories for dropdown
$categories = [];
$category_result = mysqli_query($conn, "SELECT * FROM categories");
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = trim($_POST['title']);
    $review = trim($_POST['review']);
    $author = trim($_POST['author']);
    $category_id = $_POST['category_id'];
    $status = $_POST['status'];
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    
    if (empty($title)) {
        $errors['title'] = 'Application title is required';
    }
    
    if (empty($review)) {
        $errors['review'] = 'Review is required';
    }
    
    if (empty($author)) {
        $errors['author'] = 'Author name is required';
    }
    
    if ($rating < 1 || $rating > 4) {
        $errors['rating'] = 'Please select a rating';
    }
    
    // Handle file upload if new image is provided
    $image = $application['image'];
    $image_dir = $application['image_dir'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = basename($_FILES['image']['name']);
        $target_file = $target_dir . uniqid() . '_' . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check === false) {
            $errors['image'] = 'File is not an image';
        }
        
        // Check file size (max 2MB)
        if ($_FILES['image']['size'] > 2000000) {
            $errors['image'] = 'Image is too large (max 2MB)';
        }
        
        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            $errors['image'] = 'Only JPG, JPEG, PNG & GIF files are allowed';
        }
        
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image if it exists
                if (!empty($application['image_dir']) && file_exists($application['image_dir'])) {
                    unlink($application['image_dir']);
                }
                
                $image = $file_name;
                $image_dir = $target_file;
            } else {
                $errors['image'] = 'Error uploading image';
            }
        }
    }
    
    // Update data if no errors
    if (empty($errors)) {
        $current_time = date('Y-m-d H:i:s');
        $query = "UPDATE applications SET 
                  category_id = ?, 
                  author = ?, 
                  title = ?, 
                  review = ?, 
                  image = ?, 
                  image_dir = ?, 
                  status = ?, 
                  modified = ? 
                  WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isssssssi', $category_id, $author, $title, $review, $image, $image_dir, $status, $current_time, $app_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Update comment rating
            $comment_query = "UPDATE comments SET 
                             rating = ?, 
                             modified = ? 
                             WHERE application_id = ? 
                             LIMIT 1";
            $comment_stmt = mysqli_prepare($conn, $comment_query);
            mysqli_stmt_bind_param($comment_stmt, 'isi', $rating, $current_time, $app_id);
            mysqli_stmt_execute($comment_stmt);
            
            header('Location: view.php?id=' . $app_id);
            exit();
        } else {
            $errors['database'] = 'Error updating application: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application Review</title>
    <style>
        /* Same styles as create.php */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 120px;
            resize: vertical;
        }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            padding: 0 5px;
        }
        .rating input:checked ~ label {
            color: #ffc107;
        }
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffc107;
        }
        .image-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .image-upload:hover {
            border-color: #aaa;
        }
        .current-image {
            max-width: 200px;
            max-height: 150px;
            margin-bottom: 10px;
        }
        .status-options {
            display: flex;
            gap: 15px;
        }
        .status-option {
            display: flex;
            align-items: center;
        }
        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-back {
            background-color: #6c757d;
            margin-right: 10px;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Application Review</h1>
        
        <a href="view.php?id=<?php echo $app_id; ?>" class="btn btn-back">Cancel</a>
        
        <?php if (!empty($errors['database'])): ?>
            <div class="error"><?php echo $errors['database']; ?></div>
        <?php endif; ?>
        
        <form action="edit.php?id=<?php echo $app_id; ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Application Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>">
                <?php if (!empty($errors['title'])): ?>
                    <div class="error"><?php echo $errors['title']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="author">Author Name</label>
                <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($author); ?>">
                <?php if (!empty($errors['author'])): ?>
                    <div class="error"><?php echo $errors['author']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="review">Review</label>
                <textarea id="review" name="review"><?php echo htmlspecialchars($review); ?></textarea>
                <?php if (!empty($errors['review'])): ?>
                    <div class="error"><?php echo $errors['review']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Rating</label>
                <div class="rating">
                    <input type="radio" id="star4" name="rating" value="4" <?php echo $rating === 4 ? 'checked' : ''; ?>>
                    <label for="star4">★</label>
                    <input type="radio" id="star3" name="rating" value="3" <?php echo $rating === 3 ? 'checked' : ''; ?>>
                    <label for="star3">★</label>
                    <input type="radio" id="star2" name="rating" value="2" <?php echo $rating === 2 ? 'checked' : ''; ?>>
                    <label for="star2">★</label>
                    <input type="radio" id="star1" name="rating" value="1" <?php echo $rating === 1 ? 'checked' : ''; ?>>
                    <label for="star1">★</label>
                </div>
                <?php if (!empty($errors['rating'])): ?>
                    <div class="error"><?php echo $errors['rating']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['category_id'])): ?>
                    <div class="error"><?php echo $errors['category_id']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <div class="status-options">
                    <div class="status-option">
                        <input type="radio" id="active" name="status" value="Active" <?php echo $status === 'Active' ? 'checked' : ''; ?>>
                        <label for="active" style="font-weight: normal; margin-left: 5px;">Active</label>
                    </div>
                    <div class="status-option">
                        <input type="radio" id="inactive" name="status" value="Inactive" <?php echo $status === 'Inactive' ? 'checked' : ''; ?>>
                        <label for="inactive" style="font-weight: normal; margin-left: 5px;">Inactive</label>
                    </div>
                </div>
                <?php if (!empty($errors['status'])): ?>
                    <div class="error"><?php echo $errors['status']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Application Image</label>
                <?php if (!empty($application['image'])): ?>
                    <div>
                        <p>Current Image:</p>
                        <img src="<?php echo htmlspecialchars($application['image_dir']); ?>" class="current-image">
                    </div>
                <?php endif; ?>
                <div class="image-upload">
                    <p>Drag & drop a new image here or click to browse</p>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                <?php if (!empty($errors['image'])): ?>
                    <div class="error"><?php echo $errors['image']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Update Review</button>
            </div>
        </form>
    </div>
</body>
</html>