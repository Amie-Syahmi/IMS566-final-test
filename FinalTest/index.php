<?php
require_once 'config.php';
requireLogin();

// Initialize variables
$filter_status = $_GET['status'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build base query
$query = "SELECT a.*, c.title AS category_title 
          FROM applications a 
          LEFT JOIN categories c ON a.category_id = c.id 
          WHERE 1=1";

// Add filters to query
$params = [];
if (!empty($filter_status)) {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_category)) {
    $query .= " AND a.category_id = ?";
    $params[] = $filter_category;
}

if (!empty($search_query)) {
    $query .= " AND (a.title LIKE ? OR a.review LIKE ? OR a.author LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY a.posted_date DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$applications = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get categories for filter dropdown
$categories = [];
$category_result = mysqli_query($conn, "SELECT * FROM categories");
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = $row;
}

// Calculate average ratings for each application
foreach ($applications as &$app) {
    $app_id = $app['id'];
    $rating_query = "SELECT AVG(rating) as avg_rating FROM comments WHERE application_id = $app_id";
    $rating_result = mysqli_query($conn, $rating_query);
    $rating_data = mysqli_fetch_assoc($rating_result);
    $app['avg_rating'] = round($rating_data['avg_rating'], 1);
}
unset($app);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <a href="logout.php">logout</a>
</head>
<body class="bg-light-custom">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-star me-2"></i>App Reviews
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3 text-white">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Header Section -->
        <div class="text-center mb-5">
            <h1 class="display-4 text-purple">
                <i class="fas fa-mobile-alt me-3"></i>Application Reviews
            </h1>
            <p class="lead text-muted">Discover and review amazing applications</p>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-md-6">
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New Review
                </a>
            </div>
            <div class="col-md-6 text-end">
                <a href="export.php" class="btn btn-purple">
                    <i class="fas fa-file-pdf me-2"></i>Export to PDF
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="index.php">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">
                                <i class="fas fa-search me-2"></i>Search
                            </label>
                            <input type="search" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Search applications...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="status" class="form-label">
                                <i class="fas fa-toggle-on me-2"></i>Status
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $filter_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="category" class="form-label">
                                <i class="fas fa-tags me-2"></i>Category
                            </label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo $filter_category == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="w-100">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Applications Grid -->
        <?php if (empty($applications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No applications found</h4>
                <p class="text-muted">Try adjusting your search criteria or create a new review.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($applications as $app): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <?php if (!empty($app['image'])): ?>
                                <img src="<?php echo htmlspecialchars($app['image_dir']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($app['title']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title"><?php echo htmlspecialchars($app['title']); ?></h5>
                                    <span class="badge bg-<?php echo strtolower($app['status']) === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo $app['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($app['category_title'] ?? 'Uncategorized'); ?>
                                    </small>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($app['author']); ?>
                                    </small>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($app['posted_date'])); ?>
                                    </small>
                                    <small class="text-warning ms-2">
                                        <i class="fas fa-star me-1"></i>
                                        <?php echo $app['avg_rating']; ?>
                                    </small>
                                </div>
                                
                                <p class="card-text">
                                    <?php echo htmlspecialchars(substr($app['review'], 0, 150)) . '...'; ?>
                                </p>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="d-flex gap-2">
                                    <a href="view.php?id=<?php echo $app['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="edit.php?id=<?php echo $app['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <a href="delete.php?id=<?php echo $app['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this review?')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>