<?php 
session_start();

require_once __DIR__ . "/database.php";

/*
|--------------------------------------------------------------------------
| PULSEMED ESSENTIALS - PRODUCT SHOP
|--------------------------------------------------------------------------
| Database: pulsemed_db
| Product images: assets/images/products/
| Customers can browse and add items to cart without logging in.
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_OFF);

$contact_phone = "254769861381";
$whatsapp_number = "254769861381";
$contact_email = "pulsemedessentials1@gmail.com";

$phone_link = preg_replace('/[^0-9]/', '', $contact_phone);
$whatsapp_link = preg_replace('/[^0-9]/', '', $whatsapp_number);

$upload_directory = __DIR__ . "/assets/images/products/";
$image_url_directory = "assets/images/products/";

$cart = $_SESSION["cart"] ?? [];

if (empty($_SESSION["contact_csrf_token"])) {
    $_SESSION["contact_csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| PRODUCT IMAGE HELPER
|--------------------------------------------------------------------------
| Database image values are filenames such as:
| product_1790419040_6ab7a0607e692.jpeg
|--------------------------------------------------------------------------
*/

function getProductImage($imageName, $productName = "Product")
{
    $imageName = trim((string) $imageName);

    $placeholder = "assets/images/pulsemed-logo.png";

    if ($imageName === "") {
        return $placeholder;
    }

    // Prevent directory traversal.
    $safeFileName = basename($imageName);

    // Main expected image folder.
    $mainPath = __DIR__ . "/assets/images/products/" . $safeFileName;

    if (is_file($mainPath)) {
        return "assets/images/products/" . rawurlencode($safeFileName);
    }

    /*
     * Compatibility with older product records.
     * Some existing images may be stored directly in assets/images.
     */
    $legacyPath = __DIR__ . "/assets/images/" . $safeFileName;

    if (is_file($legacyPath)) {
        return "assets/images/" . rawurlencode($safeFileName);
    }

    /*
     * Compatibility with images stored in uploads/products.
     */
    $uploadsPath = __DIR__ . "/uploads/products/" . $safeFileName;

    if (is_file($uploadsPath)) {
        return "uploads/products/" . rawurlencode($safeFileName);
    }

    /*
     * Compatibility with images stored in uploads.
     */
    $uploadsRootPath = __DIR__ . "/uploads/" . $safeFileName;

    if (is_file($uploadsRootPath)) {
        return "uploads/" . rawurlencode($safeFileName);
    }

    return $placeholder;
}

/*
|--------------------------------------------------------------------------
| ADD PRODUCT TO CART
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "add_to_cart") {

        $product_id = filter_input(
            INPUT_POST,
            "product_id",
            FILTER_VALIDATE_INT
        );

        $quantity = filter_input(
            INPUT_POST,
            "quantity",
            FILTER_VALIDATE_INT
        );

        $quantity = ($quantity && $quantity > 0) ? $quantity : 1;

        if (!$product_id || $product_id < 1) {

            $error = "Invalid product selected.";

        } else {

            $stmt = $conn->prepare(
                "SELECT id, name, price, stock
                 FROM products
                 WHERE id = ?
                 LIMIT 1"
            );

            if ($stmt) {

                $stmt->bind_param("i", $product_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $product = $result->fetch_assoc();

                $stmt->close();

                if (!$product) {

                    $error = "This product could not be found.";

                } elseif ((int) $product["stock"] <= 0) {

                    $error = "Sorry, this product is currently out of stock.";

                } else {

                    $current_quantity = isset($_SESSION["cart"][$product_id])
                        ? (int) $_SESSION["cart"][$product_id]
                        : 0;

                    $new_quantity = $current_quantity + $quantity;

                    if ($new_quantity > (int) $product["stock"]) {

                        $error = "Only "
                            . (int) $product["stock"]
                            . " units are currently available.";

                    } else {

                        $_SESSION["cart"][$product_id] = $new_quantity;

                        $message = htmlspecialchars(
                            $product["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) . " added to your cart.";

                    }
                }

            } else {

                $error = "Unable to add the product. Please check your database.";

            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CART QUANTITY
    |--------------------------------------------------------------------------
    */

    elseif ($action === "update_cart") {

        $product_id = filter_input(
            INPUT_POST,
            "product_id",
            FILTER_VALIDATE_INT
        );

        $quantity = filter_input(
            INPUT_POST,
            "quantity",
            FILTER_VALIDATE_INT
        );

        if (!$product_id || $product_id < 1) {

            $error = "Invalid product selected.";

        } elseif (!$quantity || $quantity < 1) {

            unset($_SESSION["cart"][$product_id]);

            $message = "Product removed from your cart.";

        } else {

            $stmt = $conn->prepare(
                "SELECT stock FROM products WHERE id = ? LIMIT 1"
            );

            if ($stmt) {

                $stmt->bind_param("i", $product_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $product = $result->fetch_assoc();

                $stmt->close();

                if (!$product || (int) $product["stock"] < 1) {

                    unset($_SESSION["cart"][$product_id]);

                    $message = "The unavailable product was removed from your cart.";

                } elseif ($quantity > (int) $product["stock"]) {

                    $_SESSION["cart"][$product_id] = (int) $product["stock"];

                    $error = "Your quantity was adjusted to the available stock.";

                } else {

                    $_SESSION["cart"][$product_id] = $quantity;

                    $message = "Cart updated successfully.";

                }

            } else {

                $error = "Unable to update your cart.";

            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE PRODUCT FROM CART
    |--------------------------------------------------------------------------
    */

    elseif ($action === "remove_from_cart") {

        $product_id = filter_input(
            INPUT_POST,
            "product_id",
            FILTER_VALIDATE_INT
        );

        if ($product_id && isset($_SESSION["cart"][$product_id])) {

            unset($_SESSION["cart"][$product_id]);

            $message = "Product removed from your cart.";

        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE CONTACT CENTRE ENQUIRY
    |--------------------------------------------------------------------------
    */

    elseif ($action === "contact_enquiry") {

        $csrfToken = (string) ($_POST["csrf_token"] ?? "");
        $customerName = trim((string) ($_POST["customer_name"] ?? ""));
        $customerEmail = trim((string) ($_POST["customer_email"] ?? ""));
        $customerPhone = trim((string) ($_POST["customer_phone"] ?? ""));
        $subject = trim((string) ($_POST["subject"] ?? ""));
        $enquiryMessage = trim((string) ($_POST["enquiry_message"] ?? ""));

        if (!hash_equals((string) $_SESSION["contact_csrf_token"], $csrfToken)) {
            $error = "Your form session expired. Please refresh the page and try again.";
        } elseif ($customerName === "" || mb_strlen($customerName) > 100) {
            $error = "Please enter your name (up to 100 characters).";
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($customerEmail) > 190) {
            $error = "Please enter a valid email address.";
        } elseif ($customerPhone !== "" && !preg_match('/^[0-9+() .-]{7,25}$/', $customerPhone)) {
            $error = "Please enter a valid phone number, or leave it blank.";
        } elseif ($subject === "" || mb_strlen($subject) > 150) {
            $error = "Please enter an enquiry subject (up to 150 characters).";
        } elseif ($enquiryMessage === "" || mb_strlen($enquiryMessage) > 5000) {
            $error = "Please enter your message (up to 5,000 characters).";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO contact_messages (customer_name, customer_email, customer_phone, subject, message, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'New', NOW())"
            );

            if (!$stmt) {
                $error = "The enquiry service is temporarily unavailable. Please contact us through WhatsApp or email.";
            } else {
                $stmt->bind_param("sssss", $customerName, $customerEmail, $customerPhone, $subject, $enquiryMessage);
                if ($stmt->execute()) {
                    $message = "Thank you, " . $customerName . ". Your enquiry has been sent successfully. Our team will get back to you.";
                    $_SESSION["contact_csrf_token"] = bin2hex(random_bytes(32));
                } else {
                    $error = "We could not save your enquiry. Please try again or contact us through WhatsApp.";
                }
                $stmt->close();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CART
    |--------------------------------------------------------------------------
    */

    elseif ($action === "clear_cart") {

        $_SESSION["cart"] = [];

        $message = "Your cart has been cleared.";

    }

    /*
     * Redirect after POST to prevent accidental repeat submissions.
     */
    $_SESSION["shop_message"] = $message;
    $_SESSION["shop_error"] = $error;

    header("Location: shop.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| DISPLAY ONE-TIME NOTIFICATIONS
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["shop_message"])) {
    $message = $_SESSION["shop_message"];
    unset($_SESSION["shop_message"]);
}

if (isset($_SESSION["shop_error"])) {
    $error = $_SESSION["shop_error"];
    unset($_SESSION["shop_error"]);
}

/*
|--------------------------------------------------------------------------
| SEARCH PRODUCTS
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");

/* Only show the four agreed shop categories. Older/unknown categories
   are displayed and filtered under Others without changing database data. */
$categories = ["Orthopaedic", "Laboratory", "Surgical", "Others"];
$valid_categories = $categories;
if (!in_array($category, $valid_categories, true)) {
    $category = "";
}

$products = [];
$database_error = "";

$normalisedCategorySql = "CASE
    WHEN LOWER(TRIM(category)) IN ('orthopaedic', 'orthopedic') THEN 'Orthopaedic'
    WHEN LOWER(TRIM(category)) = 'laboratory' THEN 'Laboratory'
    WHEN LOWER(TRIM(category)) = 'surgical' THEN 'Surgical'
    ELSE 'Others'
END";

/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

$sql = "SELECT id, name, $normalisedCategorySql AS category,
               description, price, stock, image
        FROM products
        WHERE 1 = 1";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (
        name LIKE ?
        OR category LIKE ?
        OR description LIKE ?
    )";

    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if ($category !== "") {
    $sql .= " AND ($normalisedCategorySql) = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    } else {
        $database_error = "Unable to retrieve products.";
    }

    $stmt->close();
} else {
    $database_error = "Unable to prepare the product query. Please check your products table.";
}

/*
|--------------------------------------------------------------------------
| FETCH CART PRODUCTS
|--------------------------------------------------------------------------
*/

$cart_items = [];
$cart_total = 0;
$cart_count = 0;

if (!empty($_SESSION["cart"])) {

    foreach ($_SESSION["cart"] as $product_id => $quantity) {

        $product_id = (int) $product_id;
        $quantity = (int) $quantity;

        if ($product_id < 1 || $quantity < 1) {
            continue;
        }

        $stmt = $conn->prepare(
            "SELECT id, name, price, stock, image
             FROM products
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            continue;
        }

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $item = $result->fetch_assoc();

        $stmt->close();

        if (!$item) {
            unset($_SESSION["cart"][$product_id]);
            continue;
        }

        $item["quantity"] = $quantity;
        $item["subtotal"] = (float) $item["price"] * $quantity;

        $cart_total += $item["subtotal"];
        $cart_count += $quantity;

        $cart_items[] = $item;
    }
}

$cart_count = (int) $cart_count;

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Shop Products | PulseMed Essentials</title>

    <meta
        name="description"
        content="Browse medical equipment, healthcare supplies and laboratory products at PulseMed Essentials."
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #087f8c;
            --primary-dark: #066873;
            --cream: #24a77b;
            --soda: #123f46;
            --background: #eef8f6;
            --text: #314b4d;
            --muted: #728587;
            --cyberteal: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        a {
            text-decoration: none;
        }

        .navbar {
            background: var(--white);
            box-shadow: 0 3px 18px rgba(18, 63, 70, 0.07);
            padding: 14px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 21px;
            font-weight: 800;
            color: var(--dark);
        }

        .navbar-brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .navbar-brand span {
            color: var(--green);
        }

        .nav-link {
            color: var(--dark);
            font-weight: 600;
            margin: 0 5px;
        }

        .nav-link:hover {
            color: var(--primary);
        }

        .cart-button {
            background: var(--primary);
            color: var(--cyberteal);
            border-radius: 25px;
            padding: 10px 18px;
            font-weight: 700;
            border: none;
        }

        .cart-button:hover {
            background: var(--primary-dark);
            color: var(--bluegreen);
        }

        .hero {
            background: linear-gradient(
                120deg,
                rgba(8, 127, 140, 0.97),
                rgba(36, 167, 123, 0.94)
            );
            padding: 65px 0;
            color: white;
        }

        .hero h1 {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 800;
            line-height: 1.2;
        }

        .hero p {
            max-width: 600px;
            font-size: 17px;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.94);
        }

        .hero-badge {
            display: inline-block;
            background: var(--bluegreen);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 30px;
            padding: 8px 15px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 700;
        }

        .hero-button {
            display: inline-block;
            background: var(--bluegreen);
            color: var(--primary-dark);
            border-radius: 30px;
            padding: 13px 23px;
            font-weight: 800;
            margin-top: 12px;
        }

        .hero-button:hover {
            background: #e9fffa;
            color: var(--dark);
        }

        .feature-strip {
            background: var(--claygreen);
            padding: 22px 0;
            border-bottom: 1px solid #e2eeeb;
        }

        .feature-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 8px;
        }

        .feature-item i {
            font-size: 25px;
            color: var(--primary);
        }

        .feature-item strong {
            display: block;
            color: var(--dark);
            font-size: 14px;
        }

        .feature-item small {
            color: var(--muted);
        }

        .section-heading {
            margin-bottom: 28px;
        }

        .section-heading h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
        }

        .section-heading p {
            color: var(--muted);
        }

        .shop-section {
            padding: 55px 0;
        }

        .search-panel {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 5px 22px rgba(18, 63, 70, 0.05);
            margin-bottom: 30px;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 10px;
            border-color: #dce9e6;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(8, 127, 140, 0.1);
        }

        .search-button {
            min-height: 46px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0 22px;
            font-weight: 700;
        }

        .search-button:hover {
            background: var(--cyberteal);
        }

        .product-card {
            height: 100%;
            overflow: hidden;
            border: 1px solid #e6efed;
            border-radius: 17px;
            background: white;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            box-shadow: 0 5px 18px rgba(18, 63, 70, 0.035);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 13px 28px rgba(18, 63, 70, 0.11);
        }

        .product-image-wrap {
            position: relative;
            padding: 12px;
            background: #ffffff;
            border-bottom: 1px solid #edf2f1;
        }

        .product-image {
            display: block;
            width: 100%;
            height: 220px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 10px;
        }

        .stock-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #e4f7ed;
            color: #16734d;
            font-size: 11px;
            font-weight: 800;
            padding: 6px 10px;
            border-radius: 20px;
        }

        .stock-badge.out-of-stock {
            background: #ffe9e9;
            color: #b42318;
        }

        .product-body {
            padding: 18px;
        }

        .product-category {
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 18px;
            line-height: 1.4;
            font-weight: 800;
            color: var(--dark);
            margin: 8px 0;
        }

        .product-description {
            min-height: 42px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--muted);
        }

        .product-price {
            color: var(--primary-dark);
            font-size: 23px;
            font-weight: 800;
            margin: 15px 0;
        }

        .add-cart-form {
            display: flex;
            gap: 8px;
        }

        .quantity-input {
            width: 65px;
            min-width: 65px;
            text-align: center;
            padding: 8px;
        }

        .add-cart-button {
            flex: 1;
            border: none;
            border-radius: 9px;
            padding: 10px;
            background: var(--primary);
            color: white;
            font-size: 13px;
            font-weight: 800;
        }

        .add-cart-button:hover {
            background: var(--primary-dark);
        }

        .add-cart-button:disabled {
            background: #a9b8b7;
            cursor: not-allowed;
        }

        .cart-section {
            padding: 55px 0;
            background: #ffffff;
        }

        .cart-panel {
            background: #f8fbfa;
            border: 1px solid #e2eeeb;
            border-radius: 16px;
            overflow: hidden;
        }

        .cart-heading {
            padding: 20px;
            background: #eaf7f3;
            border-bottom: 1px solid #dcece7;
        }

        .cart-heading h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 800;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px;
            border-bottom: 1px solid #e8efed;
        }

        .cart-item img {
            width: 75px;
            height: 75px;
            object-fit: contain;
            border-radius: 10px;
            background: white;
            border: 1px solid #e5eeeb;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-details strong {
            display: block;
            color: var(--dark);
        }

        .cart-item-details small {
            color: var(--muted);
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .cart-total {
            padding: 20px;
            background: white;
        }

        .cart-total h4 {
            color: var(--primary-dark);
            font-weight: 800;
        }

        .contact-section {
            padding: 55px 0;
            background: var(--background);
        }

        .contact-card {
            height: 100%;
            background: white;
            border: 1px solid #e3efec;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
        }

        .contact-icon {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border-radius: 15px;
            background: #e7f7f2;
            color: var(--primary);
            font-size: 25px;
        }

        .contact-card h5 {
            color: var(--dark);
            font-weight: 800;
        }

        .contact-card p {
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .contact-link {
            display: inline-block;
            color: var(--primary);
            font-weight: 800;
        }

        .notice {
            border-radius: 10px;
        }

        .empty-state {
            background: white;
            border: 1px dashed #cddfda;
            border-radius: 16px;
            padding: 50px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 45px;
            color: var(--primary);
        }

        .empty-state h4 {
            margin-top: 12px;
            color: var(--dark);
            font-weight: 800;
        }

        .footer {
            padding: 35px 0;
            background: var(--dark);
            color: #d7e7e5;
        }

        .footer strong {
            color: white;
        }

        .footer a {
            color: #d7e7e5;
        }

        .footer a:hover {
            color: white;
        }


        /* =====================================================
           MEDICAL IMAGE CAROUSEL
        ===================================================== */
        .medical-carousel {
            background: #dff1ec;
            padding: 28px 0 34px;
        }

        .medical-carousel .carousel {
            overflow: hidden;
            border-radius: 22px;
            box-shadow: 0 18px 42px rgba(18, 63, 70, 0.16);
        }

        .medical-carousel .carousel-item {
            height: 430px;
            position: relative;
            background: #123f46;
        }

        .medical-carousel .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .medical-carousel .carousel-item::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                90deg,
                rgba(8, 49, 55, 0.88) 0%,
                rgba(8, 49, 55, 0.60) 48%,
                rgba(8, 49, 55, 0.08) 100%
            );
            pointer-events: none;
        }

        .medical-carousel .carousel-caption {
            z-index: 2;
            left: 8%;
            right: auto;
            bottom: 0;
            top: 0;
            width: min(570px, 75%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            text-align: left;
            padding: 35px 0 55px;
        }

        .carousel-eyebrow {
            display: inline-block;
            margin-bottom: 15px;
            padding: 8px 13px;
            border-radius: 30px;
            color: #fff;
            background: rgba(36, 167, 123, 0.92);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .medical-carousel .carousel-caption h2 {
            max-width: 560px;
            margin-bottom: 16px;
            color: #fff;
            font-size: clamp(30px, 4vw, 49px);
            line-height: 1.12;
            font-weight: 800;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.2);
        }

        .medical-carousel .carousel-caption p {
            max-width: 490px;
            margin-bottom: 22px;
            color: rgba(255, 255, 255, 0.93);
            font-size: 16px;
            line-height: 1.75;
        }

        .carousel-shop-button {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 22px;
            border-radius: 30px;
            color: #075b5e;
            background: #fff;
            font-weight: 800;
            transition: transform .2s ease, background .2s ease;
        }

        .carousel-shop-button:hover {
            color: #075b5e;
            background: #e6fff6;
            transform: translateY(-2px);
        }

        .medical-carousel .carousel-control-prev,
        .medical-carousel .carousel-control-next {
            z-index: 3;
            width: 7%;
        }

        .medical-carousel .carousel-control-prev-icon,
        .medical-carousel .carousel-control-next-icon {
            width: 42px;
            height: 42px;
            padding: 10px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.24);
            background-size: 55%;
            backdrop-filter: blur(4px);
        }

        .medical-carousel .carousel-indicators {
            z-index: 4;
            margin-bottom: 18px;
        }

        .medical-carousel .carousel-indicators [data-bs-target] {
            width: 10px;
            height: 10px;
            border: 0;
            border-radius: 50%;
            margin: 0 5px;
        }

        @media (max-width: 767px) {

            .hero {
                padding: 45px 0;
            }

            .feature-item {
                justify-content: flex-start;
            }

            .section-heading h2 {
                font-size: 27px;
            }

            .product-image {
                height: 190px;
            }

            .cart-item {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .cart-item-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .medical-carousel {
                padding: 18px 0 24px;
            }

            .medical-carousel .carousel {
                border-radius: 15px;
            }

            .medical-carousel .carousel-item {
                height: 390px;
            }

            .medical-carousel .carousel-item img {
                object-position: 58% center;
            }

            .medical-carousel .carousel-item::after {
                background: linear-gradient(
                    90deg,
                    rgba(8, 49, 55, 0.88) 0%,
                    rgba(8, 49, 55, 0.62) 75%,
                    rgba(8, 49, 55, 0.30) 100%
                );
            }

            .medical-carousel .carousel-caption {
                left: 8%;
                right: 8%;
                width: auto;
                padding: 25px 0 45px;
            }

            .medical-carousel .carousel-caption h2 {
                font-size: 30px;
            }

            .medical-carousel .carousel-caption p {
                font-size: 14px;
                line-height: 1.6;
            }

            .medical-carousel .carousel-control-prev,
            .medical-carousel .carousel-control-next {
                width: 10%;
            }

        }

    </style>

</head>

<body>

<!-- NAVIGATION -->

<nav class="navbar navbar-expand-lg sticky-top">

    <div class="container">

        <a class="navbar-brand" href="index.php">

            <img
                src="assets/images/pulsemed-logo.png"
                alt="PulseMed Essentials Logo"
            >

            <div>
                PulseMed <span>Essentials</span>
            </div>

        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="mainNavigation"
        >

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        Home
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#products">
                        Products
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#contact">
                        Contact Centre
                    </a>
                </li>

                <li class="nav-item ms-lg-3 mt-2 mt-lg-0">

                    <a class="cart-button d-inline-block" href="#cart">

                        <i class="bi bi-cart3"></i>

                        Cart

                        <span class="badge text-bg-light">
                            <?= (int) $cart_count ?>
                        </span>

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>


<!-- HERO SECTION -->

<section class="hero">

    <div class="container">

        <div class="row align-items-center g-4">

            <div class="col-lg-8">

                <span class="hero-badge">
                    <i class="bi bi-heart-pulse-fill"></i>
                    YOUR HEALTHCARE SUPPLY PARTNER
                </span>

                <h1>
                    Quality Medical Supplies,<br>
                    Delivered with Care.
                </h1>

                <p>
                    Discover medical equipment, laboratory products,
                    protective supplies and healthcare essentials
                    in one convenient place.
                </p>

                <a href="#products" class="hero-button">
                    Explore Products
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

        </div>

    </div>

</section>



<!-- ATTRACTIVE MEDICAL IMAGE CAROUSEL -->
<section class="medical-carousel" aria-label="Featured medical supplies">
    <div class="container">
        <div id="pulseMedCarousel" class="carousel slide carousel-fade"
             data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#pulseMedCarousel" data-bs-slide-to="0"
                        class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#pulseMedCarousel" data-bs-slide-to="1"
                        aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#pulseMedCarousel" data-bs-slide-to="2"
                        aria-label="Slide 3"></button>
            </div>

            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&amp;fit=crop&amp;w=1800&amp;q=85"
                         alt="Healthcare professional preparing medical supplies"
                         fetchpriority="high">
                    <div class="carousel-caption">
                        <span class="carousel-eyebrow"><i class="bi bi-heart-pulse-fill me-1"></i> PulseMed Essentials</span>
                        <h2>Medical Essentials You Can Count On</h2>
                        <p>Explore medical equipment and healthcare supplies selected to support everyday care and professional needs.</p>
                        <a href="#products" class="carousel-shop-button">Shop Medical Supplies <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&amp;fit=crop&amp;w=1800&amp;q=85"
                         alt="Healthcare professional in a clinical environment"
                         loading="lazy">
                    <div class="carousel-caption">
                        <span class="carousel-eyebrow"><i class="bi bi-shield-check me-1"></i> Care with Confidence</span>
                        <h2>Supporting Healthcare, One Essential at a Time</h2>
                        <p>Find useful supplies for clinics, care settings and personal healthcare needs in one convenient catalogue.</p>
                        <a href="#products" class="carousel-shop-button">Browse Products <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="https://images.unsplash.com/photo-1583911860205-72f8ac8ddcbe?auto=format&amp;fit=crop&amp;w=1800&amp;q=85"
                         alt="Medical equipment arranged in a healthcare setting"
                         loading="lazy">
                    <div class="carousel-caption">
                        <span class="carousel-eyebrow"><i class="bi bi-clipboard2-pulse me-1"></i> Equipment &amp; Diagnostics</span>
                        <h2>Find the Supplies Your Work Needs</h2>
                        <p>Browse diagnostic tools, laboratory items, protective supplies and other healthcare essentials.</p>
                        <a href="#products" class="carousel-shop-button">View Our Catalogue <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#pulseMedCarousel"
                    data-bs-slide="prev" aria-label="Previous slide">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#pulseMedCarousel"
                    data-bs-slide="next" aria-label="Next slide">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</section>

<!-- FEATURES -->

<section class="feature-strip">

    <div class="container">

        <div class="row g-3">

            <div class="col-md-4">

                <div class="feature-item">

                    <i class="bi bi-shield-check"></i>

                    <div>
                        <strong>Healthcare Essentials</strong>
                        <small>Products for healthcare needs</small>
                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-item">

                    <i class="bi bi-cart-check"></i>

                    <div>
                        <strong>Convenient Shopping</strong>
                        <small>Browse without creating an account</small>
                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-item">

                    <i class="bi bi-headset"></i>

                    <div>
                        <strong>Contact Centre</strong>
                        <small>Reach us for product enquiries</small>
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- NOTIFICATIONS -->

<div class="container mt-4">

    <?php if ($message !== ""): ?>

        <div class="alert alert-success notice alert-dismissible fade show">

            <i class="bi bi-check-circle-fill"></i>

            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-danger notice alert-dismissible fade show">

            <i class="bi bi-exclamation-circle-fill"></i>

            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($database_error !== ""): ?>

        <div class="alert alert-danger notice">

            <?= htmlspecialchars($database_error, ENT_QUOTES, "UTF-8") ?>

        </div>

    <?php endif; ?>

</div>


<!-- PRODUCTS SECTION -->

<section class="shop-section" id="products">

    <div class="container">

        <div class="section-heading text-center">

            <span class="product-category">
                OUR PRODUCT CATALOGUE
            </span>

            <h2 class="mt-2">
                Medical Products & Supplies
            </h2>

            <p>
                Browse our available products and add your selections to the cart.
            </p>

        </div>


        <!-- SEARCH -->

        <form method="GET" action="shop.php" class="search-panel">

            <div class="row g-3 align-items-center">

                <div class="col-lg-6">

                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Search products, categories..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, "UTF-8") ?>"
                    >

                </div>

                <div class="col-lg-3">

                    <select
                        name="category"
                        class="form-select"
                    >

                        <option value="">All Categories</option>

                        <?php foreach ($categories as $cat): ?>

                            <option
                                value="<?= htmlspecialchars($cat, ENT_QUOTES, "UTF-8") ?>"
                                <?= $category === $cat ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($cat, ENT_QUOTES, "UTF-8") ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-lg-3 d-grid">

                    <button type="submit" class="search-button">

                        <i class="bi bi-search"></i>
                        Search Products

                    </button>

                </div>

            </div>

        </form>


        <!-- PRODUCT GRID -->

        <?php if (!empty($products)): ?>

            <div class="row g-4">

                <?php foreach ($products as $product): ?>

                    <?php

                    $product_id = (int) $product["id"];
                    $stock = (int) $product["stock"];

                    $imageUrl = getProductImage(
                        $product["image"] ?? "",
                        $product["name"]
                    );

                    $description = trim(
                        (string) ($product["description"] ?? "")
                    );

                    if ($description === "") {
                        $description = "Quality healthcare product from PulseMed Essentials.";
                    }

                    ?>

                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">

                        <div class="product-card">

                            <div class="product-image-wrap">

                                <img
                                    src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, "UTF-8") ?>"
                                    alt="<?= htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8") ?>"
                                    class="product-image"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='assets/images/pulsemed-logo.png';"
                                >

                                <?php if ($stock > 0): ?>

                                    <span class="stock-badge">
                                        <i class="bi bi-check-circle"></i>
                                        In Stock
                                    </span>

                                <?php else: ?>

                                    <span class="stock-badge out-of-stock">
                                        Out of Stock
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="product-body">

                                <div class="product-category">

                                    <?= htmlspecialchars(
                                        $product["category"] ?? "Medical Supplies",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>


                                <h3 class="product-name">

                                    <?= htmlspecialchars(
                                        $product["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </h3>


                                <p class="product-description">

                                    <?= htmlspecialchars(
                                        $description,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </p>


                                <div class="product-price">

                                    KSh
                                    <?= number_format(
                                        (float) $product["price"],
                                        2
                                    ) ?>

                                </div>


                                <form
                                    method="POST"
                                    action="shop.php#products"
                                    class="add-cart-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="add_to_cart"
                                    >

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= $product_id ?>"
                                    >

                                    <input
                                        type="number"
                                        name="quantity"
                                        value="1"
                                        min="1"
                                        max="<?= max(1, $stock) ?>"
                                        class="form-control quantity-input"
                                        aria-label="Quantity"
                                        <?= $stock < 1 ? "disabled" : "" ?>
                                    >

                                    <button
                                        type="submit"
                                        class="add-cart-button"
                                        <?= $stock < 1 ? "disabled" : "" ?>
                                    >

                                        <i class="bi bi-cart-plus"></i>

                                        <?= $stock < 1 ? "Unavailable" : "Add to Cart" ?>

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <i class="bi bi-search"></i>

                <h4>No products found</h4>

                <p class="text-muted">
                    Try a different product name or category.
                </p>

                <a href="shop.php#products" class="search-button d-inline-block pt-2 pb-2">
                    View All Products
                </a>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- CART SECTION -->

<section class="cart-section" id="cart">

    <div class="container">

        <div class="section-heading">

            <span class="product-category">
                YOUR SELECTIONS
            </span>

            <h2 class="mt-2">
                <i class="bi bi-cart3"></i>
                Shopping Cart
            </h2>

            <p>
                Review your selected products and quantities.
            </p>

        </div>


        <div class="cart-panel">

            <div class="cart-heading">

                <h3>
                    <i class="bi bi-bag-check"></i>
                    Your Cart
                    <span class="badge text-bg-success">
                        <?= (int) $cart_count ?> <?= $cart_count === 1 ? "item" : "items" ?>
                    </span>
                </h3>

            </div>


            <?php if (!empty($cart_items)): ?>

                <?php foreach ($cart_items as $item): ?>

                    <div class="cart-item">

                        <img
                            src="<?= htmlspecialchars(
                                getProductImage($item["image"] ?? ""),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            alt="<?= htmlspecialchars($item["name"], ENT_QUOTES, "UTF-8") ?>"
                            onerror="this.onerror=null;this.src='assets/images/pulsemed-logo.png';"
                        >

                        <div class="cart-item-details">

                            <strong>

                                <?= htmlspecialchars(
                                    $item["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </strong>

                            <small>
                                KSh <?= number_format((float) $item["price"], 2) ?> each
                            </small>

                            <small class="d-block text-success mt-1">
                                <i class="bi bi-check-circle-fill"></i>
                                <?= (int) $item["stock"] ?> in stock
                            </small>

                            <div class="fw-bold mt-1">

                                Subtotal:
                                KSh <?= number_format((float) $item["subtotal"], 2) ?>

                            </div>

                        </div>


                        <div class="cart-item-actions">

                            <form
                                method="POST"
                                action="shop.php#cart"
                                class="d-flex align-items-center gap-2"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_cart"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $item["id"] ?>"
                                >

                                <input
                                    type="number"
                                    name="quantity"
                                    value="<?= (int) $item["quantity"] ?>"
                                    min="1"
                                    max="<?= max(1, (int) $item["stock"]) ?>"
                                    class="form-control quantity-input"
                                    aria-label="Update quantity"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-success btn-sm"
                                    title="Update quantity"
                                >

                                    <i class="bi bi-arrow-repeat"></i>

                                </button>

                            </form>


                            <form
                                method="POST"
                                action="shop.php#cart"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="remove_from_cart"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $item["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger btn-sm"
                                    title="Remove product"
                                >

                                    <i class="bi bi-trash"></i>

                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>


                <div class="cart-total">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                        <div>

                            <span class="text-muted">
                                Estimated cart total
                            </span>

                            <h4 class="mb-0">

                                KSh <?= number_format($cart_total, 2) ?>

                            </h4>

                        </div>


                        <div class="d-flex gap-2 flex-wrap">

                            <form
                                method="POST"
                                action="shop.php#cart"
                                onsubmit="return confirm('Clear all products from your cart?');"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="clear_cart"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                >

                                    <i class="bi bi-trash"></i>
                                    Clear Cart

                                </button>

                            </form>


                            <button
                                type="button"
                                class="btn btn-success"
                                id="whatsappOrderButton"
                            >
                                <i class="bi bi-whatsapp"></i>
                                Send Order via WhatsApp
                            </button>

                            <a href="#products" class="btn btn-outline-success">
                                <i class="bi bi-plus-circle"></i>
                                Continue Shopping
                            </a>

                        </div>

                    </div>

                    <p class="text-muted small mt-3 mb-0">

                        This cart is a selection list only. Your order is not
                        submitted and payment is not processed until you contact
                        the team and confirm the order.

                    </p>

                </div>

            <?php else: ?>

                <div class="empty-state m-3">

                    <i class="bi bi-cart-x"></i>

                    <h4>Your cart is empty</h4>

                    <p class="text-muted">
                        Browse the products above and add items to your cart.
                    </p>

                    <a href="#products" class="search-button d-inline-block pt-2 pb-2">
                        Browse Products
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- CONTACT CENTRE -->

<section class="contact-section" id="contact">

    <div class="container">

        <div class="section-heading text-center">

            <span class="product-category">
                WE ARE HERE TO HELP
            </span>

            <h2 class="mt-2">
                Contact Centre
            </h2>

            <p>
                Contact PulseMed Essentials for enquiries and order confirmation.
            </p>

        </div>


        <div class="row g-4">

            <div class="col-md-4">

                <div class="contact-card">

                    <div class="contact-icon">

                        <i class="bi bi-telephone"></i>

                    </div>

                    <h5>Call Us</h5>

                    <p>
                        Speak to our contact centre.
                    </p>

                    <a
                        class="contact-link"
                        href="tel:+<?= htmlspecialchars($phone_link, ENT_QUOTES, "UTF-8") ?>"
                    >

                        <?= htmlspecialchars(
                            $contact_phone,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </a>

                </div>

            </div>


            <div class="col-md-4">

                <div class="contact-card">

                    <div class="contact-icon">

                        <i class="bi bi-whatsapp"></i>

                    </div>

                    <h5>WhatsApp</h5>

                    <p>
                        Ask about products and confirm your cart.
                    </p>

                    <a
                        class="contact-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        href="https://wa.me/<?= htmlspecialchars($whatsapp_link, ENT_QUOTES, "UTF-8") ?>"
                    >

                        Chat on WhatsApp

                    </a>

                </div>

            </div>


            <div class="col-md-4">

                <div class="contact-card">

                    <div class="contact-icon">

                        <i class="bi bi-envelope"></i>

                    </div>

                    <h5>Email Us</h5>

                    <p>
                        Send us your product enquiry.
                    </p>

                    <a
                        class="contact-link"
                        href="mailto:<?= htmlspecialchars($contact_email, ENT_QUOTES, "UTF-8") ?>"
                    >

                        <?= htmlspecialchars(
                            $contact_email,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </a>

                </div>

            </div>

        </div>

        <!-- CUSTOMER ENQUIRY FORM -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-9">
                <div class="contact-card text-start">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="contact-icon m-0"><i class="bi bi-chat-square-text"></i></div>
                        <div>
                            <h5 class="mb-1">Send Us an Enquiry</h5>
                            <p class="mb-0">Complete this form and our team will review your message.</p>
                        </div>
                    </div>

                    <form method="POST" action="shop.php#contact" class="mt-4">
                        <input type="hidden" name="action" value="contact_enquiry">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["contact_csrf_token"], ENT_QUOTES, "UTF-8") ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label fw-semibold">Your name *</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="100" autocomplete="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="customer_email" class="form-label fw-semibold">Email address *</label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email" maxlength="190" autocomplete="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="customer_phone" class="form-label fw-semibold">Phone number *</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" maxlength="25" autocomplete="tel" placeholder="e.g. 07XXXXXXXX" required>
                            </div>
                            <div class="col-md-6">
                                <label for="subject" class="form-label fw-semibold">Enquiry subject *</label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Choose a subject</option>
                                    <option value="Product availability">Product availability</option>
                                    <option value="Price enquiry">Price enquiry</option>
                                    <option value="Order and delivery">Order and delivery</option>
                                    <option value="Bulk purchase">Bulk purchase</option>
                                    <option value="Other enquiry">Other enquiry</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="enquiry_message" class="form-label fw-semibold">Your message *</label>
                                <textarea class="form-control" id="enquiry_message" name="enquiry_message" rows="5" maxlength="5000" placeholder="Tell us how we can help..." required></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <small class="text-muted">Fields marked * are required.</small>
                                <button type="submit" class="search-button">
                                    <i class="bi bi-send me-1"></i> Submit Enquiry
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

</section>


<!-- FOOTER -->

<footer class="footer">

    <div class="container">

        <div class="row align-items-center g-3">

            <div class="col-md-7">

                <strong>PulseMed Essentials</strong>

                <p class="mb-0 mt-1 small">

                    Your healthcare supply partner.

                </p>

            </div>

            <div class="col-md-5 text-md-end">

                <a href="index.php">Home</a>

                <span class="mx-2">|</span>

                <a href="#products">Products</a>

                <span class="mx-2">|</span>

                <a href="#contact">Contact</a>

            </div>

        </div>

        <hr class="border-secondary my-4">

        <div class="small text-center">

            &copy; <?= date("Y") ?> PulseMed Essentials.
            All rights reserved.

        </div>

    </div>

</footer>


<script>
/* Build a WhatsApp order message from the current cart. */
document.addEventListener("DOMContentLoaded", function () {
    const orderButton = document.getElementById("whatsappOrderButton");
    if (!orderButton) return;

    const cartItems = <?= json_encode(array_map(function ($item) {
        return [
            "name" => (string) $item["name"],
            "quantity" => (int) $item["quantity"],
            "price" => (float) $item["price"],
            "subtotal" => (float) $item["subtotal"]
        ];
    }, $cart_items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const cartTotal = <?= json_encode((float) $cart_total) ?>;
    const whatsappNumber = <?= json_encode($whatsapp_link) ?>;

    orderButton.addEventListener("click", function () {
        if (!cartItems.length) {
            alert("Your cart is empty. Please add a product first.");
            return;
        }

        let message = "Hello PulseMed Essentials, I would like to place an order:\n\n";
        cartItems.forEach(function (item, index) {
            message += (index + 1) + ". " + item.name + " x " + item.quantity +
                " — KSh " + item.subtotal.toLocaleString("en-KE", {minimumFractionDigits: 2, maximumFractionDigits: 2}) + "\n";
        });
        message += "\nEstimated total: KSh " + cartTotal.toLocaleString("en-KE", {minimumFractionDigits: 2, maximumFractionDigits: 2});
        message += "\n\nPlease confirm availability, delivery charges and payment instructions. Thank you.";
        window.open("https://wa.me/" + whatsappNumber + "?text=" + encodeURIComponent(message), "_blank", "noopener");
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>