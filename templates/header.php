<?php
// This file assumes that a session has been started and user data is available
// Ensure BASE_URL is defined (usually in config/db.php which is included by init.php)
if (!defined('BASE_URL')) {
    // Attempt to define it relative to the current file if not already defined
    // This might need adjustment based on your actual file structure
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    // Assuming 'bst8' is the root directory relative to the document root
    define('BASE_URL', $protocol . '://' . $host . '/bst8/');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'سیستم مدیریت تولید'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.rtl.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- *** NEW Jalali Datepicker CSS *** -->
    <link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">

    <!-- Google Font (Vazirmatn) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); // Cache busting ?>">

    <!-- Chart.js library (Include here for availability in page scripts) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body class="bg-light">

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <!-- The main link now points to the dashboard -->
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard.php">سیستم مدیریت</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#top-navbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="top-navbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0"></ul>
      <div class="d-flex align-items-center text-white">
        <?php if (isset($_SESSION['username'])): ?>
            <span class="me-3">
                <i class="bi bi-person-circle"></i> خوش آمدید, <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> خروج
            </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid p-3 p-md-4">
<!-- Main content of the page starts here -->
