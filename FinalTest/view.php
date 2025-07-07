<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$app_id = $_GET['id'];

// Get application details
$app_query = "SELECT a.*, c.title AS category_title 
              FROM applications a 
              LEFT JOIN categories c ON a.category_id = c.id 
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

// Get average rating
$rating_query = "SELECT AVG(rating) as avg_rating FROM comments WHERE application_id = ?";
$rating_stmt = mysqli_prepare($conn, $rating_query);
mysqli_stmt_bind_param($rating_stmt, 'i', $app_id);
mysqli_stmt_execute($rating_stmt);
$rating_result = mysqli_stmt_get_result($rating_stmt);
$rating_data = mysqli_fetch_assoc($rating_result);
$avg_rating = round($rating_data['avg_rating'], 1);

// Get comments
$comments_query = "SELECT * FROM comments WHERE application_id = ? ORDER BY created DESC";
$comments_stmt = mysqli_prepare($conn, $comments_query);
mysqli_stmt_bind_param($comments_stmt, 'i', $app_id);
mysqli_stmt_execute($comments_stmt);
$comments_result = mysqli_stmt_get_result($comments_stmt);
$comments = mysqli_fetch_all($comments_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($application['title']); ?> - Details</title>
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
            margin-bottom: 20px;
        }
        .app-meta {
            color: #666;
            margin-bottom: 15px;
        }
        .app-image {
            max-width: 100%;
            max-height: 300px;
            margin-bottom: 15px;
        }
        .app-review {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .rating {
            color: #ffc107;
            font-weight: bold;
            font-size: 18px;
        }
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .comments {
            margin-top: 30px;
        }
        .comment {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .comment-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
        }
        .comment-rating {
            color: #ffc107;
            font-weight: bold;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-back {
            background-color: #6c757d;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn btn-back">Back to List</a>
        <a href="edit.php?id=<?php echo $application['id']; ?>" class="btn" style="background-color: #ffc107;">Edit</a>
        
        <h1><?php echo htmlspecialchars($application['title']); ?></h1>
        
        <div class="app-meta">
            <span>Category: <?php echo htmlspecialchars($application['category_title'] ?? 'Uncategorized'); ?></span> | 
            <span>Author: <?php echo htmlspecialchars($application['author']); ?></span> | 
            <span>Posted: <?php echo date('M d, Y H:i', strtotime($application['posted_date'])); ?></span> | 
            <span class="rating">Average Rating: <?php echo $avg_rating; ?> ★</span> | 
            <span class="status status-<?php echo strtolower($application['status']); ?>"><?php echo $application['status']; ?></span>
        </div>
        
        <?php if (!empty($application['image'])): ?>
            <img src="<?php echo htmlspecialchars($application['image_dir']); ?>" alt="<?php echo htmlspecialchars($application['title']); ?>" class="app-image">
        <?php endif; ?>
        
        <div class="app-review">
            <?php echo nl2br(htmlspecialchars($application['review'])); ?>
        </div>
        
        <div class="comments">
            <h2>Comments (<?php echo count($comments); ?>)</h2>
            
            <?php if (empty($comments)): ?>
                <p>No comments yet.</p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-meta">
                            <span><strong><?php echo htmlspecialchars($comment['name']); ?></strong></span>
                            <span class="comment-rating"><?php echo $comment['rating']; ?> ★</span>
                        </div>
                        <div class="comment-text">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </div>
                        <div class="comment-meta" style="margin-top: 10px; margin-bottom: 0;">
                            <span><?php echo date('M d, Y H:i', strtotime($comment['created'])); ?></span>
                            <span class="status status-<?php echo strtolower($comment['status']); ?>"><?php echo $comment['status']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>