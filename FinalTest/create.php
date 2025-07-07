<?php
require_once 'config.php';
requireLogin();

// Initialize variables
$title = $review = $author = $category_id = $status = '';
$rating = 0;
$errors = [];

// Fetch categories for dropdown
$categories = [];
$category_query = "SELECT * FROM categories";
$category_result = mysqli_query($conn, $category_query);
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
    
    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Please select a valid rating (1-5 stars)';
    }
    
    // Handle file upload (optional)
    $image = '';
    $image_dir = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $unique_name = uniqid() . '.' . $file_ext;
        $target_file = $target_dir . $unique_name;
        
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
        if (!in_array($file_ext, $allowed_types)) {
            $errors['image'] = 'Only JPG, JPEG, PNG & GIF files are allowed';
        }
        
        if (empty($errors['image'])) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $unique_name;
                $image_dir = $target_file;
            } else {
                $errors['image'] = 'Error uploading image';
            }
        }
    }
    
    // Insert data if no errors
    if (empty($errors)) {
        $current_time = date('Y-m-d H:i:s');
        $query = "INSERT INTO applications (category_id, posted_date, author, title, review, image, image_dir, status, created, modified) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isssssssss', $category_id, $current_time, $author, $title, $review, $image, $image_dir, $status, $current_time, $current_time);
        
        if (mysqli_stmt_execute($stmt)) {
            $application_id = mysqli_insert_id($conn);
            
            // Insert comment with rating
            $comment_query = "INSERT INTO comments (application_id, name, comment, rating, status, created, modified) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)";
            $comment_stmt = mysqli_prepare($conn, $comment_query);
            $comment_text = "Initial review with rating";
            mysqli_stmt_bind_param($comment_stmt, 'ississs', $application_id, $author, $comment_text, $rating, $status, $current_time, $current_time);
            mysqli_stmt_execute($comment_stmt);
            
            header('Location: index.php');
            exit();
        } else {
            $errors['database'] = 'Error saving application: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Application Review</title>
    <style>
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
            cursor: pointer;
        }
        .image-upload:hover {
            border-color: #aaa;
            background-color: #f9f9f9;
        }
        #file-input {
            display: none;
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
        #file-info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
        #image-preview {
            margin-top: 15px;
            text-align: center;
        }
        #preview {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create New Application Review</h1>
        
        <?php if (!empty($errors['database'])): ?>
            <div class="error"><?php echo $errors['database']; ?></div>
        <?php endif; ?>
        
        <form action="create.php" method="POST" enctype="multipart/form-data">
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
                    <input type="radio" id="star5" name="rating" value="5" <?php echo $rating === 5 ? 'checked' : ''; ?>>
                    <label for="star5">★</label>
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
                        <input type="radio" id="active" name="status" value="1" <?php echo $status === '1' ? 'checked' : ''; ?>>
                        <label for="active" style="font-weight: normal; margin-left: 5px;">Active</label>
                    </div>
                    <div class="status-option">
                        <input type="radio" id="inactive" name="status" value="0" <?php echo $status === '0' ? 'checked' : ''; ?>>
                        <label for="inactive" style="font-weight: normal; margin-left: 5px;">Inactive</label>
                    </div>
                </div>
                <?php if (!empty($errors['status'])): ?>
                    <div class="error"><?php echo $errors['status']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Application Image (Optional)</label>
                <div class="image-upload" onclick="document.getElementById('image').click()">
                    <p>Click to upload an image (JPG, PNG, GIF - max 2MB)</p>
                    <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                </div>
                <div id="file-info"></div>
                <div id="image-preview" style="display: none;">
                    <img id="preview" src="#" alt="Preview">
                </div>
                <?php if (!empty($errors['image'])): ?>
                    <div class="error"><?php echo $errors['image']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Create Review</button>
            </div>
        </form>
    </div>

    <script>
        // File info display
        document.getElementById('image').addEventListener('change', function(e) {
            const fileInfo = document.getElementById('file-info');
            const preview = document.getElementById('image-preview');
            
            if (this.files.length > 0) {
                const file = this.files[0];
                fileInfo.innerHTML = `Selected file: <strong>${file.name}</strong> (${Math.round(file.size / 1024)} KB)`;
                
                // Show preview if image
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.style.display = 'block';
                        document.getElementById('preview').src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                }
            } else {
                fileInfo.innerHTML = '';
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>