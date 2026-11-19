<?php
session_start();
require_once "../database.php";

/* =====================================================
   1. ADMIN ACCESS PROTECTION
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

/* =====================================================
   2. GENERAL SETTINGS
===================================================== */

mysqli_report(MYSQLI_REPORT_OFF);

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];
$upload_dir = __DIR__ . "/../assets/images/products/";
$upload_url = "../assets/images/products/";

$allowed_categories = [
    "Orthopedic",
    "Laboratory",
    "Surgical",
    "Other"
];

$errors = [];
$success_message = "";
$admin_name = $_SESSION["user_name"] ?? "Administrator";

/* =====================================================
   3. HELPER FUNCTIONS
===================================================== */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function redirect_products($query = "")
{
    $url = "products.php";

    if ($query !== "") {
        $url .= "?" . $query;
    }

    header("Location: " . $url);
    exit();
}

function verify_csrf_token()
{
    if (
        !isset($_POST["csrf_token"], $_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            (string)$_POST["csrf_token"]
        )
    ) {
        throw new RuntimeException(
            "Your session verification failed. Refresh the page and try again."
        );
    }
}

function save_product_image($file, $upload_dir)
{
    if (
        !isset($file) ||
        !isset($file["error"]) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            "The image upload failed. Please select the image again."
        );
    }

    if ($file["size"] > 5 * 1024 * 1024) {
        throw new RuntimeException(
            "The product image must not exceed 5 MB."
        );
    }

    if (!is_uploaded_file($file["tmp_name"])) {
        throw new RuntimeException("Invalid image upload.");
    }

    $image_info = @getimagesize($file["tmp_name"]);

    if ($image_info === false) {
        throw new RuntimeException(
            "Please select a valid image file."
        );
    }

    $mime_types = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    $mime = $image_info["mime"] ?? "";

    if (!isset($mime_types[$mime])) {
        throw new RuntimeException(
            "Only JPG, PNG, and WEBP images are allowed."
        );
    }

    if (
        !is_dir($upload_dir) &&
        !mkdir($upload_dir, 0755, true)
    ) {
        throw new RuntimeException(
            "Could not create the product images folder."
        );
    }

    if (!is_writable($upload_dir)) {
        throw new RuntimeException(
            "The product images folder is not writable."
        );
    }

    $filename = "product_" .
        bin2hex(random_bytes(12)) .
        "." . $mime_types[$mime];

    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        throw new RuntimeException(
            "Could not save the product image."
        );
    }

    return $filename;
}

function remove_product_image($filename, $upload_dir)
{
    if (empty($filename)) {
        return;
    }

    // Only remove a filename from the product images folder.
    $safe_filename = basename($filename);
    $path = $upload_dir . $safe_filename;

    if (is_file($path)) {
        @unlink($path);
    }
}

/* =====================================================
   4. PROCESS ADD AND DELETE ACTIONS
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {
        verify_csrf_token();

        $action = $_POST["action"] ?? "";

        /* ---------------------------------------------
           ADD PRODUCT
        --------------------------------------------- */

        if ($action === "add") {

    $name = trim($_POST["name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price_input = trim($_POST["price"] ?? "");
    $stock_input = trim($_POST["stock"] ?? "");

    // Validate product name
    if ($name === "" || strlen($name) > 150) {
        throw new RuntimeException(
            "Enter a product name of up to 150 characters."
        );
    }

    // Validate category
    if (!in_array($category, $allowed_categories, true)) {
        throw new RuntimeException(
            "Please select a valid product category."
        );
    }

    // Validate description
    if (strlen($description) > 5000) {
        throw new RuntimeException(
            "The description must not exceed 5000 characters."
        );
    }

    // Validate price
    if (
        $price_input === "" ||
        !is_numeric($price_input) ||
        !is_finite((float)$price_input) ||
        (float)$price_input < 0
    ) {
        throw new RuntimeException(
            "Enter a valid price of zero or more."
        );
    }

    // Validate stock quantity
    if (
        $stock_input === "" ||
        filter_var($stock_input, FILTER_VALIDATE_INT) === false ||
        (int)$stock_input < 0
    ) {
        throw new RuntimeException(
            "Stock must be a whole number of zero or more."
        );
    }

    $price = (float)$price_input;
    $stock = (int)$stock_input;
    $image = null;

    // Save optional product image
    $image = save_product_image(
        $_FILES["image"] ?? null,
        $upload_dir
    );

    // Prepare the INSERT query only once
    $stmt = $conn->prepare(
        "INSERT INTO products
            (name, category, description, price, stock, image)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        remove_product_image($image, $upload_dir);

        throw new RuntimeException(
            "Database error: " . $conn->error
        );
    }

    // s = string, d = decimal/double, i = integer
    $stmt->bind_param(
        "sssdis",
        $name,
        $category,
        $description,
        $price,
        $stock,
        $image
    );

    // Execute the query
    if (!$stmt->execute()) {

        $database_error = $stmt->error;

        $stmt->close();

        remove_product_image($image, $upload_dir);

        error_log(
            "PulseMed product insertion failed: " .
            $database_error
        );

        throw new RuntimeException(
            "Could not add the product. Database details: " .
            $database_error
        );
    }

    $stmt->close();

    // Redirect to prevent duplicate submission on refresh
    redirect_products("success=added");
}
        /* ---------------------------------------------
           DELETE PRODUCT
        --------------------------------------------- */

        elseif ($action === "delete") {

            $product_id = filter_var(
                $_POST["product_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$product_id || $product_id < 1) {
                throw new RuntimeException(
                    "Invalid product selected."
                );
            }

            // Get the current image before deleting the product.
            $stmt = $conn->prepare(
                "SELECT image FROM products WHERE id = ? LIMIT 1"
            );

            if (!$stmt) {
                throw new RuntimeException(
                    "Could not prepare the product query."
                );
            }

            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stmt->bind_result($image_to_delete);

            $found = $stmt->fetch();
            $stmt->close();

            if (!$found) {
                throw new RuntimeException(
                    "The selected product was not found."
                );
            }

            $stmt = $conn->prepare(
                "DELETE FROM products WHERE id = ?"
            );

            if (!$stmt) {
                throw new RuntimeException(
                    "Could not prepare the product deletion query."
                );
            }

            $stmt->bind_param("i", $product_id);

            if (!$stmt->execute()) {
                $stmt->close();

                throw new RuntimeException(
                    "Could not delete the product. Check whether it is linked to an existing order."
                );
            }

            $deleted = $stmt->affected_rows;
            $stmt->close();

            if ($deleted > 0) {
                remove_product_image($image_to_delete, $upload_dir);
            }

            redirect_products("success=deleted");
        }

        else {
            throw new RuntimeException(
                "Invalid action requested."
            );
        }

    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();

    } catch (Throwable $e) {
        $errors[] = "An unexpected error occurred. Please check your database and try again.";
    }
}

/* =====================================================
   5. SUCCESS MESSAGES
===================================================== */

if (isset($_GET["success"])) {
    switch ($_GET["success"]) {
        case "added":
            $success_message = "Product added successfully.";
            break;

        case "updated":
            $success_message = "Product updated successfully.";
            break;

        case "deleted":
            $success_message = "Product deleted successfully.";
            break;
    }
}

/* =====================================================
   6. SEARCH AND FILTER PRODUCTS
===================================================== */

$search = trim($_GET["search"] ?? "");
$category_filter = trim($_GET["category"] ?? "");

if (!in_array($category_filter, $allowed_categories, true)) {
    $category_filter = "";
}

$search_term = "%" . $search . "%";

if ($search !== "" && $category_filter !== "") {

    $stmt = $conn->prepare(
        "SELECT id, name, category, description, price, stock, image
         FROM products
         WHERE (name LIKE ? OR description LIKE ?)
           AND category = ?
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Could not prepare the product search.");
    }

    $stmt->bind_param(
        "sss",
        $search_term,
        $search_term,
        $category_filter
    );

} elseif ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT id, name, category, description, price, stock, image
         FROM products
         WHERE name LIKE ? OR description LIKE ?
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Could not prepare the product search.");
    }

    $stmt->bind_param("ss", $search_term, $search_term);

} elseif ($category_filter !== "") {

    $stmt = $conn->prepare(
        "SELECT id, name, category, description, price, stock, image
         FROM products
         WHERE category = ?
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Could not prepare the product search.");
    }

    $stmt->bind_param("s", $category_filter);

} else {

    $stmt = $conn->prepare(
        "SELECT id, name, category, description, price, stock, image
         FROM products
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Could not prepare the product list.");
    }
}

$stmt->execute();
$products_result = $stmt->get_result();

$products = [];

while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

/* =====================================================
   7. PRODUCT SUMMARY STATISTICS
===================================================== */

$stats = [
    "total" => 0,
    "stock_units" => 0,
    "low_stock" => 0,
    "out_of_stock" => 0
];

$stats_result = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(stock), 0) AS stock_units,
        COALESCE(SUM(CASE WHEN stock <= 10 AND stock > 0 THEN 1 ELSE 0 END), 0) AS low_stock,
        COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock
     FROM products"
);

if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Products | PulseMed Essentials</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    />

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    />

    <style>
        :root {
            --primary: #087f8c;
            --primary-dark: #066873;
            --green: #24a77b;
            --background: #eef8f6;
            --text: #123f46;
            --muted: #75898d;
            --border: #e0ece9;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        .sidebar {
            width: 245px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            background: white;
            border-right: 1px solid var(--border);
            padding: 25px 17px;
            z-index: 100;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 25px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }

        .logo-section img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .logo-text h5 {
            font-size: 15px;
            font-weight: 800;
            margin: 0;
            color: var(--primary-dark);
        }

        .logo-text small {
            color: var(--muted);
            font-size: 10px;
        }

        .menu-label {
            padding: 0 12px;
            margin-bottom: 12px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.3px;
            color: #98aaad;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            margin-bottom: 5px;
            border-radius: 9px;
            color: #60777b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #e7f6f1;
            color: var(--primary-dark);
        }

        .sidebar a.active {
            font-weight: 800;
        }

        .sidebar .icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar a.logout {
            margin-top: 18px;
            color: #d34b4b;
        }

        .main {
            margin-left: 245px;
            padding: 28px 30px 45px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 27px;
        }

        .topbar h2 {
            margin: 0 0 7px;
            font-size: 25px;
            font-weight: 800;
        }

        .topbar p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 13px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .admin-avatar {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 37px;
            height: 37px;
            border-radius: 50%;
            background: #dcf5ec;
            color: var(--primary-dark);
            font-weight: 800;
        }

        .admin-badge strong {
            display: block;
            font-size: 12px;
        }

        .admin-badge small {
            color: var(--muted);
            font-size: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 19px;
            min-width: 0;
        }

        .stat-card .stat-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 11px;
        }

        .stat-card .stat-number {
            font-size: 27px;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-card .stat-note {
            margin-top: 7px;
            font-size: 10px;
            color: var(--muted);
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .panel-heading {
            padding: 21px 23px;
            border-bottom: 1px solid var(--border);
        }

        .panel-heading h4 {
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 6px;
        }

        .panel-heading p {
            font-size: 11px;
            color: var(--muted);
            margin: 0;
        }

        .panel-body {
            padding: 23px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            min-height: 43px;
            border: 1px solid #dce8e5;
            border-radius: 9px;
            font-size: 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(8, 127, 140, 0.1);
        }

        textarea.form-control {
            min-height: 95px;
        }

        .btn-primary-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 17px;
            border: 0;
            border-radius: 9px;
            background: var(--primary);
            color: white;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
        }

        .btn-light-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: white;
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .filter-row .search-field {
            flex: 1 1 220px;
        }

        .filter-row .category-field {
            flex: 0 1 205px;
        }

        .filter-row .form-control,
        .filter-row .form-select {
            width: 100%;
        }

        .table-responsive {
            width: 100%;
        }

        .table {
            margin: 0;
            vertical-align: middle;
        }

        .table thead th {
            background: #f7fbfa;
            color: #72868a;
            border-bottom: 1px solid var(--border);
            padding: 14px 13px;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.4px;
        }

        .table tbody td {
            padding: 14px 13px;
            border-bottom: 1px solid #edf3f1;
            font-size: 12px;
        }

        .table tbody tr:last-child td {
            border-bottom: 0;
        }

        .product-image {
            width: 55px;
            height: 55px;
            object-fit: contain;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f7fbfa;
            padding: 4px;
        }

        .product-name {
            font-weight: 700;
            color: var(--text);
            max-width: 230px;
            overflow-wrap: anywhere;
        }

        .product-description {
            max-width: 240px;
            color: var(--muted);
            font-size: 10px;
            margin-top: 5px;
            line-height: 1.5;
        }

        .category-pill {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            background: #eaf5f2;
            color: #247b6c;
            font-size: 10px;
            font-weight: 700;
        }

        .stock-pill {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .stock-good {
            background: #e4f7ec;
            color: #23824f;
        }

        .stock-low {
            background: #fff1d9;
            color: #a96b0b;
        }

        .stock-out {
            background: #fde7e7;
            color: #bd3838;
        }

        .price {
            font-weight: 800;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .btn-edit,
        .btn-delete {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 800;
            text-decoration: none;
            border: 0;
        }

        .btn-edit {
            background: #e5f3f8;
            color: #176e87;
        }

        .btn-edit:hover {
            background: #d5ebf2;
            color: #12596d;
        }

        .btn-delete {
            background: #fff0f0;
            color: #c33d3d;
        }

        .btn-delete:hover {
            background: #fbdede;
        }

        .empty-state {
            padding: 45px 20px !important;
            text-align: center;
            color: var(--muted);
        }

        .empty-state strong {
            display: block;
            color: var(--text);
            margin-bottom: 8px;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 10px;
        }

        .alert {
            border-radius: 10px;
            font-size: 12px;
        }

        .image-preview {
            display: none;
            width: 100px;
            height: 90px;
            margin-top: 10px;
            padding: 5px;
            object-fit: contain;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 18px;
            }

            .logo-section {
                padding-bottom: 15px;
                margin-bottom: 15px;
            }

            .sidebar a {
                display: inline-flex;
                padding: 9px 10px;
                margin-right: 3px;
            }

            .main {
                margin-left: 0;
                padding: 22px 15px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 9px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-card .stat-number {
                font-size: 22px;
            }

            .panel-body {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<!-- =================================================
     SIDEBAR
================================================== -->

<aside class="sidebar">

    <div class="logo-section">
        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essentials logo"
        >

        <div class="logo-text">
            <h5>PulseMed</h5>
            <small>Essentials Admin</small>
        </div>
    </div>

    <div class="menu-label">MAIN MENU</div>

    <a href="dashboard.php">
        <span class="icon">▦</span>
        Dashboard
    </a>

    <a href="products.php" class="active">
        <span class="icon">▣</span>
        Products
    </a>

    <a href="orders.php">
        <span class="icon">▤</span>
        Orders
    </a>

    <a href="users.php">
        <span class="icon">♙</span>
        Users
    </a>

    <a href="reports.php">
        <span class="icon">▥</span>
        Reports
    </a>

    <div class="menu-label mt-4">ACCOUNT</div>

    <a href="profile.php">
        <span class="icon">⚙</span>
        Profile
    </a>

    <a href="../logout.php" class="logout">
        <span class="icon">⇥</span>
        Logout
    </a>

</aside>

<!-- =================================================
     MAIN CONTENT
================================================== -->

<main class="main">

    <div class="topbar">

        <div>
            <h2>Manage Products</h2>
            <p>Add, edit, search, and manage PulseMed medical products.</p>
        </div>

        <div class="admin-badge">
            <div class="admin-avatar">
                <?= h(strtoupper(substr($admin_name, 0, 1))); ?>
            </div>

            <div>
                <strong><?= h($admin_name); ?></strong>
                <small>Administrator</small>
            </div>
        </div>

    </div>

    <!-- SUCCESS MESSAGE -->

    <?php if ($success_message !== ""): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= h($success_message); ?>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    <?php endif; ?>

    <!-- ERROR MESSAGES -->

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Action unsuccessful</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error); ?></li>
                <?php endforeach; ?>
            </ul>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    <?php endif; ?>

    <!-- =================================================
         SUMMARY CARDS
    ================================================== -->

    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-label">TOTAL PRODUCTS</div>
            <div class="stat-number">
                <?= number_format((int)$stats["total"]); ?>
            </div>
            <div class="stat-note">Products registered in the system</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">TOTAL STOCK UNITS</div>
            <div class="stat-number">
                <?= number_format((int)$stats["stock_units"]); ?>
            </div>
            <div class="stat-note">Units currently recorded in stock</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">LOW STOCK</div>
            <div class="stat-number">
                <?= number_format((int)$stats["low_stock"]); ?>
            </div>
            <div class="stat-note">Products with 1–10 units remaining</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">OUT OF STOCK</div>
            <div class="stat-number">
                <?= number_format((int)$stats["out_of_stock"]); ?>
            </div>
            <div class="stat-note">Products with no units available</div>
        </div>

    </section>

    <!-- =================================================
         ADD PRODUCT FORM
    ================================================== -->

    <section class="panel">

        <div class="panel-heading">
            <h4>+ Add New Product</h4>
            <p>Enter the product details to add it to your online shop.</p>
        </div>

        <div class="panel-body">

            <form
                action="products.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <input type="hidden" name="action" value="add">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($csrf_token); ?>"
                >

                <div class="row g-3">

                    <div class="col-md-6">
                        <label for="name" class="form-label">
                            Product Name *
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            maxlength="150"
                            placeholder="Enter product name"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="category" class="form-label">
                            Category *
                        </label>

                        <select
                            id="category"
                            name="category"
                            class="form-select"
                            required
                        >
                            <option value="">Select category</option>

                            <?php foreach ($allowed_categories as $category): ?>
                                <option value="<?= h($category); ?>">
                                    <?= h($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="price" class="form-label">
                            Price (KSh) *
                        </label>

                        <input
                            type="number"
                            id="price"
                            name="price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            placeholder="e.g. 1500.00"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="stock" class="form-label">
                            Available Stock *
                        </label>

                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            class="form-control"
                            min="0"
                            step="1"
                            placeholder="Enter quantity"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">
                            Product Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            maxlength="5000"
                            placeholder="Describe the product..."
                        ></textarea>
                    </div>

                    <div class="col-md-7">
                        <label for="image" class="form-label">
                            Product Image (Optional)
                        </label>

                        <input
                            type="file"
                            id="image"
                            name="image"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <small class="help-text">
                            JPG, PNG, or WEBP. Maximum image size: 5 MB.
                        </small>

                        <img
                            id="imagePreview"
                            class="image-preview"
                            alt="Selected product image preview"
                        >
                    </div>

                    <div class="col-12 pt-2">
                        <button type="submit" class="btn-primary-custom">
                            + Add Product
                        </button>

                        <button
                            type="reset"
                            class="btn-light-custom"
                            id="resetForm"
                        >
                            Clear Form
                        </button>
                    </div>

                </div>

            </form>

        </div>
    </section>

    <!-- =================================================
         SEARCH AND FILTER
    ================================================== -->

    <section class="panel">

        <div class="panel-heading">
            <h4>Product Inventory</h4>
            <p>
                Search products, check prices and stock, or edit product details.
            </p>
        </div>

        <div class="panel-body">

            <form
                action="products.php"
                method="GET"
                class="filter-row"
            >

                <div class="search-field">
                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search by product name or description..."
                        value="<?= h($search); ?>"
                    >
                </div>

                <div class="category-field">
                    <select
                        name="category"
                        class="form-select"
                    >
                        <option value="">All Categories</option>

                        <?php foreach ($allowed_categories as $category): ?>
                            <option
                                value="<?= h($category); ?>"
                                <?= $category_filter === $category ? "selected" : ""; ?>
                            >
                                <?= h($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary-custom">
                    Search
                </button>

                <a href="products.php" class="btn-light-custom">
                    Reset
                </a>

            </form>

        </div>

        <!-- =================================================
             PRODUCTS TABLE
        ================================================== -->

        <div class="table-responsive">

            <table class="table">

                <thead>
                    <tr>
                        <th>IMAGE</th>
                        <th>PRODUCT DETAILS</th>
                        <th>CATEGORY</th>
                        <th>PRICE</th>
                        <th>STOCK</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($products)): ?>

                        <tr>
                            <td colspan="6" class="empty-state">
                                <strong>No products found</strong>

                                <?php if ($search !== "" || $category_filter !== ""): ?>
                                    Try changing your search or category filter.
                                <?php else: ?>
                                    Use the form above to add your first product.
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <?php
                            $stock = (int)$product["stock"];

                            if ($stock === 0) {
                                $stock_class = "stock-out";
                                $stock_label = "Out of stock";
                            } elseif ($stock <= 10) {
                                $stock_class = "stock-low";
                                $stock_label = "Low stock";
                            } else {
                                $stock_class = "stock-good";
                                $stock_label = "In stock";
                            }

                            $image_name = basename(
                                (string)($product["image"] ?? "")
                            );

                            $image_file = $upload_dir . $image_name;

                            $image_exists =
                                $image_name !== "" &&
                                is_file($image_file);

                            $description = trim(
                                (string)($product["description"] ?? "")
                            );
                            ?>

                            <tr>

                                <td>
                                    <?php if ($image_exists): ?>

                                        <img
                                            src="<?= h($upload_url . rawurlencode($image_name)); ?>"
                                            alt="<?= h($product["name"]); ?>"
                                            class="product-image"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <div class="product-image d-flex align-items-center justify-content-center">
                                            <span style="font-size: 21px; color: #9ab5b0;">
                                                +
                                            </span>
                                        </div>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="product-name">
                                        <?= h($product["name"]); ?>
                                    </div>

                                    <div class="product-description">
                                        <?php if ($description !== ""): ?>
                                            <?= h(mb_strimwidth($description, 0, 95, "...")); ?>
                                        <?php else: ?>
                                            No description provided
                                        <?php endif; ?>
                                    </div>

                                    <small class="text-muted">
                                        ID: #<?= (int)$product["id"]; ?>
                                    </small>
                                </td>

                                <td>
                                    <span class="category-pill">
                                        <?= h($product["category"]); ?>
                                    </span>
                                </td>

                                <td class="price">
                                    KSh <?= number_format((float)$product["price"], 2); ?>
                                </td>

                                <td>
                                    <span class="stock-pill <?= h($stock_class); ?>">
                                        <?= $stock; ?> — <?= h($stock_label); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="action-buttons">

                                        <a
                                            href="edit_product.php?id=<?= (int)$product["id"]; ?>"
                                            class="btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="products.php"
                                            method="POST"
                                            class="delete-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= h($csrf_token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= (int)$product["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-delete"
                                            >
                                                Delete
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="panel-heading">
            <p>
                Showing <?= count($products); ?>
                product<?= count($products) === 1 ? "" : "s"; ?>
                in this view.
            </p>
        </div>

    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* Image preview before submitting the add-product form */
const imageInput = document.getElementById("image");
const imagePreview = document.getElementById("imagePreview");
const resetForm = document.getElementById("resetForm");

imageInput.addEventListener("change", function () {
    const file = this.files[0];

    imagePreview.style.display = "none";
    imagePreview.removeAttribute("src");

    if (!file) {
        return;
    }

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {
        alert("Please choose a JPG, PNG, or WEBP image.");
        this.value = "";
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("The image must not exceed 5 MB.");
        this.value = "";
        return;
    }

    imagePreview.src = URL.createObjectURL(file);
    imagePreview.style.display = "block";
});

resetForm.addEventListener("click", function () {
    imagePreview.style.display = "none";
    imagePreview.removeAttribute("src");
});

/* Confirm before deleting a product */

document.querySelectorAll(".delete-form").forEach(function (form) {
    form.addEventListener("submit", function (event) {
        const confirmed = confirm(
            "Are you sure you want to delete this product? This action cannot be undone."
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
});
</script>

</body>
</html>
