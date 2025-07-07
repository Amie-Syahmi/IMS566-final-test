<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$app_id = $_GET['id'];

// Get application details to delete image file
$app_query = "SELECT image_dir FROM applications WHERE id = ?";
$app_stmt = mysqli_prepare($conn, $app_query);
mysqli_stmt_bind_param($app_stmt, 'i', $app_id);
mysqli_stmt_execute($app_stmt);
$app_result = mysqli_stmt_get_result($app_stmt);
$application = mysqli_fetch_assoc($app_result);

if ($application) {
    $image_path = $application['image_dir'];

    mysqli_begin_transaction($conn);

    try {
        // Delete related comments (if applicable)
        $delete_comments_query = "DELETE FROM comments WHERE application_id = ?";
        $delete_comments_stmt = mysqli_prepare($conn, $delete_comments_query);
        mysqli_stmt_bind_param($delete_comments_stmt, 'i', $app_id);
        mysqli_stmt_execute($delete_comments_stmt);

        // Delete the application
        $delete_app_query = "DELETE FROM applications WHERE id = ?";
        $delete_app_stmt = mysqli_prepare($conn, $delete_app_query);
        mysqli_stmt_bind_param($delete_app_stmt, 'i', $app_id);
        mysqli_stmt_execute($delete_app_stmt);

        // Delete image file if exists
        if (!empty($image_path) && file_exists($image_path)) {
            unlink($image_path);
        }

        // Commit transaction
        mysqli_commit($conn);

        // Redirect after successful deletion
        header('Location: index.php');
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "Failed to delete: " . $e->getMessage();
    }
} else {
    // ID not found in database
    header('Location: index.php');
    exit();
}
