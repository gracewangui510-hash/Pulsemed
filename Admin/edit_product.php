<?php
session_start();
require_once "../database.php";

/* =========================================
   ADMIN ACCESS PROTECTION
========================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

/* =========================================
   HELPER FUNCTION
========================================= */

function clean($value)
{
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, "UTF-8");
}

/* =========================================
   VALIDATE PRODUCT ID
========================================= */

if (!isset($_GET["id"]) || !ctype_digit((string)$_GET["id"])) {
    header("Location: products.php?error=invalid_id");
    exit();
}

$product_id = (int) $_GET["id"];

/* =========================================
   FETCH PRODUCT
========================================= */

$stmt = $conn->prepare(
    "SELECT id, name, category, description, price, stock, image
     FROM products
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to prepare product query.");
}

$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    header("Location: products.php?error=product_not_found");
    exit();
}

/* =========================================
   DEFAULT VALUES
========================================= */

$name = $product["name"];
$category = $product["category"];
$description = $product["description"] ?? "";
$price = $product["price"];
$stock = $product["stock"];
$image = $product["image"] ?? "";

$errors = [];

/* =========================================
   PROCESS PRODUCT UPDATE
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $stock = trim($_POST["stock"] ?? "");

    /* Validate name */
    if ($name === "") {
        $errors[] = "Product name is required.";
    }

    /* Validate category */
    $allowed_categories = [
        "Medical Equipment",
        "Medical Supplies",
        "Laboratory",
        "Surgical",
        "Personal Care",
        "Other"
    ];

    if (!in_array($category, $allowed_categories, true)) {
        $errors[] = "Please select a valid product category.";
    }

    /* Validate description */
    if (strlen($description) > 5000) {
        $errors[] = "Description must not exceed 5000 characters.";
    }

    /* Validate price */
    if (
        $price === "" ||
        !is_numeric($price) ||
        (float)$price < 0
    ) {
        $errors[] = "Enter a valid price of zero or more.";
    }

    /* Validate stock */
    if (
        $stock === "" ||
        filter_var($stock, FILTER_VALIDATE_INT) === false ||
        (int)$stock < 0
    ) {
        $errors[] = "Stock must be a whole number of zero or more.";
    }

    /* =====================================
       HANDLE IMAGE UPLOAD
    ===================================== */

    $new_image_name = $product["image"];
    $uploaded_image_path = null;
    $old_image_path = null;

    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {

            $errors[] = "The image could not be uploaded. Please try again.";

        } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

            $errors[] = "The image must be 5 MB or smaller.";

        } else {

            $image_tmp = $_FILES["image"]["tmp_name"];

            /* Check actual image content */
            $image_info = @getimagesize($image_tmp);

            if ($image_info === false) {

                $errors[] = "Please upload a valid image file.";

            } else {

                $allowed_mime_types = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                $mime_type = $image_info["mime"] ?? "";

                if (!isset($allowed_mime_types[$mime_type])) {

                    $errors[] = "Only JPG, PNG, and WEBP images are allowed.";

                } else {

                    $upload_dir = "../assets/images/products/";

                    if (
                        !is_dir($upload_dir) &&
                        !mkdir($upload_dir, 0755, true)
                    ) {
                        $errors[] = "Unable to create the product image folder.";

                    } else {

                        $extension = $allowed_mime_types[$mime_type];

                        $new_image_name =
                            "product_" .
                            $product_id . "_" .
                            bin2hex(random_bytes(8)) . "." .
                            $extension;

                        $destination = $upload_dir . $new_image_name;

                        if (move_uploaded_file($image_tmp, $destination)) {

                            $uploaded_image_path = $destination;

                            if (!empty($product["image"])) {

                                $old_image_path =
                                    $upload_dir . basename($product["image"]);
                            }

                        } else {

                            $errors[] = "Unable to save the uploaded image.";
                        }
                    }
                }
            }
        }
    }

    /* =====================================
       UPDATE DATABASE
    ===================================== */

    if (empty($errors)) {
        $price_value = (float)$price;
        $stock_value = (int)$stock;

        $stmt = $conn->prepare(
            "UPDATE products
             SET name = ?,
                 category = ?,
                 description = ?,
                 price = ?,
                 stock = ?,
                 image = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            $errors[] = "Unable to prepare the product update.";
        } else {
            /*
             * Parameter types:
             * name=s, category=s, description=s,
             * price=d, stock=i, image=s, product_id=i.
             */
            $stmt->bind_param(
                "sssdisi",
                $name,
                $category,
                $description,
                $price_value,
                $stock_value,
                $new_image_name,
                $product_id
            );

            if ($stmt->execute()) {
                $stmt->close();

                /* Remove the old image only after a successful update. */
                if (
                    $uploaded_image_path !== null &&
                    $old_image_path !== null &&
                    is_file($old_image_path)
                ) {
                    @unlink($old_image_path);
                }

                header("Location: products.php?success=updated");
                exit();
            } else {
                $errors[] = "Failed to update the product: " . $stmt->error;
                $stmt->close();

                /* Remove the newly uploaded image if the update failed. */
                if (
                    $uploaded_image_path !== null &&
                    is_file($uploaded_image_path)
                ) {
                    @unlink($uploaded_image_path);
                }
            }
        }
    }

    /* Reload original image if update failed */
    if (!empty($errors)) {
        $image = $product["image"] ?? "";
    }
}

/* =========================================
   DISPLAY IMAGE PATH
========================================= */

$image_url = "";

if (!empty($image)) {
    $image_url = "../assets/images/products/" . rawurlencode(basename($image));
}

$current_admin_name = $_SESSION["user_name"] ?? "Administrator";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Product | PulseMed Essentials</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --primary: #087f8c;
            --primary-dark: #066873;
            --green: #24a77b;
            --background: #f2f8f7;
            --text: #183b40;
            --muted: #71858a;
            --border: #e0ece9;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 28px 18px;
            overflow-y: auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 30px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 25px;
        }

        .logo-section img {
            width: 48px;
            height: 48px;
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
            font-size: 11px;
        }

        .menu-label {
            font-size: 10px;
            font-weight: 800;
            color: #9aabad;
            letter-spacing: 1.5px;
            padding: 0 12px;
            margin-bottom: 12px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            margin-bottom: 6px;
            border-radius: 10px;
            text-decoration: none;
            color: #61777b;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .sidebar a:hover,
        .sidebar a.active {
            color: var(--primary-dark);
            background: #e8f7f3;
        }

        .sidebar a.active {
            font-weight: 800;
        }

        .icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar a.logout {
            color: #d84c4c;
            margin-top: 20px;
        }

        .main {
            margin-left: 250px;
            padding: 28px 35px 45px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .topbar h2 {
            font-size: 25px;
            font-weight: 800;
            margin: 0 0 6px;
        }

        .topbar p {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 15px;
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dff5ed;
            color: var(--primary-dark);
            border-radius: 50%;
            font-weight: 800;
        }

        .admin-badge strong {
            display: block;
            font-size: 12px;
        }

        .admin-badge small {
            display: block;
            font-size: 10px;
            color: var(--muted);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }

        .back-link:hover {
            color: var(--green);
        }

        .form-card {
            max-width: 950px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(18, 63, 70, 0.04);
        }

        .form-heading {
            padding-bottom: 22px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }

        .form-heading h4 {
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border: 1px solid #dce8e5;
            border-radius: 9px;
            font-size: 13px;
            padding: 11px 13px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(8, 127, 140, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .image-preview {
            width: 145px;
            height: 130px;
            border: 1px dashed #cbded9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f7fbfa;
            margin-bottom: 12px;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }

        .no-image {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            padding: 12px;
        }

        .help-text {
            display: block;
            color: var(--muted);
            font-size: 11px;
            margin-top: 8px;
        }

        .btn-primary-custom {
            background: var(--primary);
            border: none;
            color: white;
            border-radius: 9px;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 700;
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dce8e5;
            background: white;
            color: #5d7377;
            border-radius: 9px;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #f4f9f8;
            color: var(--text);
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                padding: 24px 20px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar a {
                display: inline-flex;
                margin-right: 3px;
                padding: 9px;
            }

            .menu-label {
                margin-top: 15px;
            }

            .main {
                margin-left: 0;
                padding: 20px 14px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">

    <div class="logo-section">
        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essentials Logo"
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

<!-- MAIN CONTENT -->
<main class="main">

    <div class="topbar">

        <div>
            <h2>Edit Product</h2>
            <p>Update product information, pricing, stock, and image.</p>
        </div>

        <div class="admin-badge">
            <div class="admin-avatar">
                <?= strtoupper(substr($current_admin_name, 0, 1)); ?>
            </div>

            <div>
                <strong><?= clean($current_admin_name); ?></strong>
                <small>Administrator</small>
            </div>
        </div>

    </div>

    <a href="products.php" class="back-link">
        ← Back to Products
    </a>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= clean($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="form-card">

        <div class="form-heading">
            <h4>Product Information</h4>
            <p>
                Editing product ID:
                <strong>#<?= (int)$product_id; ?></strong>
            </p>
        </div>

        <form
            action="edit_product.php?id=<?= (int)$product_id; ?>"
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="row g-4">

                <div class="col-md-6">

                    <label for="name" class="form-label">
                        Product Name *
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        value="<?= clean($name); ?>"
                        maxlength="150"
                        required
                    >

                </div>

                <div class="col-md-6">

                    <label for="category" class="form-label">
                        Product Category *
                    </label>

                    <select
                        id="category"
                        name="category"
                        class="form-select"
                        required
                    >

                        <option value="">Select Category</option>

                        <?php
                        foreach (
                            [
                                "Medical Equipment",
                                "Medical Supplies",
                                "Laboratory",
                                "Surgical",
                                "Personal Care",
                                "Other"
                            ] as $option
                        ):
                        ?>

                            <option
                                value="<?= clean($option); ?>"
                                <?= $category === $option ? "selected" : ""; ?>
                            >
                                <?= clean($option); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

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
                    ><?= clean($description); ?></textarea>

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
                        value="<?= clean($price); ?>"
                        min="0"
                        step="0.01"
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
                        value="<?= clean($stock); ?>"
                        min="0"
                        step="1"
                        required
                    >

                </div>

                <div class="col-12">

                    <label class="form-label">
                        Current Product Image
                    </label>

                    <div class="image-preview">

                        <?php if ($image_url !== ""): ?>

                            <img
                                src="<?= clean($image_url); ?>"
                                alt="Current product image"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                No product image uploaded
                            </div>

                        <?php endif; ?>

                    </div>

                    <label for="image" class="form-label">
                        Replace Product Image (Optional)
                    </label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <small class="help-text">
                        Accepted formats: JPG, PNG, WEBP. Maximum size: 5 MB.
                        Leave this field empty to keep the current image.
                    </small>

                    <div class="image-preview mt-3" id="newImagePreview" style="display:none;">
                        <img id="newImage" src="" alt="New product image preview">
                    </div>

                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex flex-wrap gap-3">

                <button type="submit" class="btn btn-primary-custom">
                    Save Product Changes
                </button>

                <a href="products.php" class="btn-cancel">
                    Cancel
                </a>

            </div>

        </form>

    </section>

</main>

<script>
document.getElementById("image").addEventListener("change", function () {
    const file = this.files[0];
    const preview = document.getElementById("newImagePreview");
    const previewImage = document.getElementById("newImage");

    if (!file) {
        preview.style.display = "none";
        previewImage.src = "";
        return;
    }

    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG, or WEBP image.");
        this.value = "";
        preview.style.display = "none";
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("The image must be 5 MB or smaller.");
        this.value = "";
        preview.style.display = "none";
        return;
    }

    previewImage.src = URL.createObjectURL(file);
    preview.style.display = "flex";
});
</script>

</body>
</html>