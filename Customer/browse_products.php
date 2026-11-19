<?php

session_start();
require_once "../database.php";

/* =========================================================
   CUSTOMER ACCESS PROTECTION
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION["user_name"] ?? "Customer";

/* =========================================================
   SEARCH AND CATEGORY FILTER
   ========================================================= */

$search   = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");

/* =========================================================
   FETCH CATEGORIES
   ========================================================= */

$category_sql = "
    SELECT DISTINCT category
    FROM products
    WHERE category IS NOT NULL
      AND category != ''
    ORDER BY category ASC
";

$category_result = $conn->query($category_sql);

/* =========================================================
   FETCH PRODUCTS
   ========================================================= */

$sql = "
    SELECT id, name, category, description, price, stock, image
    FROM products
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        AND (
            name LIKE ?
            OR category LIKE ?
            OR description LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

if ($category !== "") {

    $sql .= " AND category = ?";

    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

$product_count = $result->num_rows;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Browse Products | PulseMed Essential</title>

<style>

/* =========================================================
   GENERAL
   ========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5faf8;
    color: #263238;
}

/* =========================================================
   NAVBAR
   ========================================================= */

.navbar {
    width: 100%;
    height: 78px;
    padding: 0 6%;

    background: #79b4db;

    display: flex;
    align-items: center;
    justify-content: space-between;

    box-shadow: 0 2px 12px rgba(0,0,0,0.08);

    position: sticky;
    top: 0;
    z-index: 1000;
}

.brand {
    display: flex;
    align-items: center;
    text-decoration: none;
}

.brand img {
    width: 52px;
    height: 52px;
    object-fit: contain;
    margin-right: 10px;
}

.brand-name {
    font-size: 20px;
    font-weight: bold;
    color: #087f5b;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
}

.nav-links a {
    text-decoration: none;
    color: #455a64;
    font-size: 14px;
    font-weight: 600;
}

.nav-links a:hover,
.nav-links a.active {
    color: #087f5b;
}

.logout {
    background: #087f5b;
    color: white !important;
    padding: 10px 17px;
    border-radius: 7px;
}

.logout:hover {
    background: #05694b !important;
}

/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {
    background: linear-gradient(
        135deg,
        #087f5b,
        #0aa875
    );

    color: white;

    padding: 50px 6%;
}

.page-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
}

.page-header p {
    font-size: 15px;
}

/* =========================================================
   MAIN CONTAINER
   ========================================================= */

.container {
    width: 88%;
    max-width: 1300px;
    margin: 35px auto 60px;
}

/* =========================================================
   WELCOME MESSAGE
   ========================================================= */

.welcome {
    margin-bottom: 25px;
}

.welcome h2 {
    font-size: 23px;
    color: #263238;
}

.welcome p {
    color: #78909c;
    margin-top: 6px;
}

/* =========================================================
   SEARCH BOX
   ========================================================= */

.search-box {
    background: #5ca1b6;

    padding: 22px;

    border-radius: 12px;

    box-shadow: 0 3px 15px rgba(0,0,0,0.07);

    margin-bottom: 30px;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr 230px 110px 100px;
    gap: 12px;
}

.search-form input,
.search-form select {
    height: 45px;

    border: 1px solid #d5e0dc;

    border-radius: 7px;

    padding: 0 13px;

    font-size: 14px;

    outline: none;
}

.search-form input:focus,
.search-form select:focus {
    border-color: #087f5b;
}

.search-btn {
    border: none;

    border-radius: 7px;

    background: #087f5b;

    color: white;

    font-weight: bold;

    cursor: pointer;
}

.search-btn:hover {
    background: #05694b;
}

.clear-btn {
    border: 1px solid #087f5b;

    border-radius: 7px;

    background: white;

    color: #087f5b;

    text-decoration: none;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 14px;

    font-weight: bold;
}

.clear-btn:hover {
    background: #eefaf5;
}

/* =========================================================
   PRODUCTS HEADER
   ========================================================= */

.products-heading {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}

.products-heading h2 {
    font-size: 22px;
}

.product-count {
    color: #78909c;
    font-size: 14px;
}

/* =========================================================
   PRODUCTS GRID
   ========================================================= */

.products-grid {
    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 24px;
}

/* =========================================================
   PRODUCT CARD
   ========================================================= */

.product-card {
    background: white;

    border-radius: 13px;

    overflow: hidden;

    box-shadow: 0 4px 15px rgba(0,0,0,0.07);

    transition: 0.3s;
}

.product-card:hover {
    transform: translateY(-5px);

    box-shadow: 0 8px 22px rgba(0,0,0,0.11);
}

/* =========================================================
   PRODUCT IMAGE
   ========================================================= */

.product-image {
    height: 210px;

    background: #70b197;

    position: relative;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    width: 70px;
    height: 70px;

    border-radius: 50%;

    background: #5dad93;

    color: #087f5b;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

    font-weight: bold;
}

/* =========================================================
   STOCK BADGE
   ========================================================= */

.stock-badge {
    position: absolute;

    top: 12px;
    right: 12px;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.in-stock {
    background: #e2f7eb;
    color: #087f5b;
}

.low-stock {
    background: #fff3d0;
    color: #9a6a00;
}

.out-stock {
    background: #fde8e8;
    color: #c62828;
}

/* =========================================================
   PRODUCT INFORMATION
   ========================================================= */

.product-info {
    padding: 20px;
}

.category {
    display: inline-block;

    background: #5db3c9;

    color: #087f5b;

    padding: 5px 9px;

    border-radius: 5px;

    font-size: 11px;

    font-weight: bold;

    margin-bottom: 10px;
}

.product-info h3 {
    font-size: 18px;

    margin-bottom: 9px;
}

.description {
    color: #78909c;

    font-size: 13px;

    line-height: 1.5;

    height: 40px;

    overflow: hidden;

    margin-bottom: 15px;
}

.product-bottom {
    border-top: 1px solid #edf1f0;

    padding-top: 15px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.price {
    color: #087f5b;

    font-size: 18px;

    font-weight: bold;
}

.stock-text {
    color: #78909c;

    font-size: 12px;
}

/* =========================================================
   ORDER BUTTON
   ========================================================= */

.order-btn {
    display: block;

    width: 100%;

    text-align: center;

    margin-top: 15px;

    padding: 11px;

    background: #087f5b;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-size: 14px;

    font-weight: bold;

    transition: 0.3s;
}

.order-btn:hover {
    background: #05694b;
}

.out-of-stock-btn {
    display: block;

    width: 100%;

    text-align: center;

    margin-top: 15px;

    padding: 11px;

    background: #527c99;

    color: #506a77;

    border-radius: 7px;

    font-size: 14px;

    font-weight: bold;
}

/* =========================================================
   NO PRODUCTS
   ========================================================= */

.no-products {
    background: white;

    border-radius: 12px;

    padding: 65px 20px;

    text-align: center;

    box-shadow: 0 3px 15px rgba(0,0,0,0.06);
}

.no-products-icon {
    width: 70px;
    height: 70px;

    margin: 0 auto 18px;

    border-radius: 50%;

    background: #e8f7f1;

    color: #087f5b;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

    font-weight: bold;
}

.no-products h3 {
    margin-bottom: 8px;
}

.no-products p {
    color: #78909c;

    font-size: 14px;
}

/* =========================================================
   FOOTER
   ========================================================= */

footer {
    background: #063f30;

    color: white;

    text-align: center;

    padding: 25px 15px;
}

footer p {
    font-size: 13px;

    opacity: 0.9;
}

/* =========================================================
   RESPONSIVE DESIGN
   ========================================================= */

@media (max-width: 1100px) {

    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .search-form {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 800px) {

    .navbar {
        height: auto;

        padding: 15px 5%;

        flex-direction: column;

        gap: 15px;
    }

    .nav-links {
        flex-wrap: wrap;

        justify-content: center;
    }

    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 550px) {

    .products-grid {
        grid-template-columns: 1fr;
    }

    .search-form {
        grid-template-columns: 1fr;
    }

    .container {
        width: 92%;
    }

    .products-heading {
        flex-direction: column;

        align-items: flex-start;

        gap: 8px;
    }

}

</style>

</head>

<body>


<!-- =========================================================
     NAVIGATION
     ========================================================= -->

<nav class="navbar">

    <a href="dashboard.php" class="brand">

        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essential Logo"
        >

        <span class="brand-name">
            PulseMed Essential
        </span>

    </a>


    <div class="nav-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="browse_products.php" class="active">
            Browse Products
        </a>

        <a href="my_orders.php">
            My Orders
        </a>

        <a href="profile.php">
            Profile
        </a>

        <a href="logout.php" class="logout">
            Logout
        </a>

    </div>

</nav>


<!-- =========================================================
     PAGE HEADER
     ========================================================= -->

<section class="page-header">

    <h1>
        Browse Products
    </h1>

    <p>
        Find the medical and healthcare products you need.
    </p>

</section>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<div class="container">


    <div class="welcome">

        <h2>
            Welcome,
            <?php echo htmlspecialchars($user_name); ?>
        </h2>

        <p>
            Browse our available products and place your order.
        </p>

    </div>


    <!-- =====================================================
         SEARCH AND FILTER
         ===================================================== -->

    <div class="search-box">

        <form
            method="GET"
            action="browse_products.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search by product name..."
                value="<?php echo htmlspecialchars($search); ?>"
            >


            <select name="category">

                <option value="">
                    All Categories
                </option>

                <?php if ($category_result): ?>

                    <?php while ($cat = $category_result->fetch_assoc()): ?>

                        <option
                            value="<?php echo htmlspecialchars($cat["category"]); ?>"
                            <?php
                            echo (
                                $category === $cat["category"]
                            )
                            ? "selected"
                            : "";
                            ?>
                        >
                            <?php
                            echo htmlspecialchars(
                                $cat["category"]
                            );
                            ?>
                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>


            <button
                type="submit"
                class="search-btn"
            >
                Search
            </button>


            <a
                href="browse_products.php"
                class="clear-btn"
            >
                Clear
            </a>

        </form>

    </div>


    <!-- =====================================================
         PRODUCTS HEADING
         ===================================================== -->

    <div class="products-heading">

        <h2>
            Available Products
        </h2>

        <span class="product-count">

            <?php echo $product_count; ?>

            product<?php
                echo ($product_count == 1)
                    ? ""
                    : "s";
            ?>

        </span>

    </div>


    <!-- =====================================================
         PRODUCTS
         ===================================================== -->

    <?php if ($product_count > 0): ?>

        <div class="products-grid">


            <?php while ($product = $result->fetch_assoc()): ?>

                <?php

                $stock = (int)$product["stock"];

                if ($stock <= 0) {

                    $stock_class = "out-stock";
                    $stock_text = "Out of stock";

                } elseif ($stock <= 5) {

                    $stock_class = "low-stock";
                    $stock_text = "Low stock";

                } else {

                    $stock_class = "in-stock";
                    $stock_text = "In stock";

                }

                ?>


                <div class="product-card">


                    <!-- PRODUCT IMAGE -->

                    <div class="product-image">

                        <?php if (!empty($product["image"])): ?>

                            <img
                                src="../assets/images/products/<?php echo htmlspecialchars($product["image"]); ?>"
                                alt="<?php echo htmlspecialchars($product["name"]); ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >

                            <div
                                class="no-image"
                                style="display:none;"
                            >
                                +
                            </div>

                        <?php else: ?>

                            <div class="no-image">
                                +
                            </div>

                        <?php endif; ?>


                        <span
                            class="stock-badge <?php echo $stock_class; ?>"
                        >
                            <?php echo $stock_text; ?>
                        </span>

                    </div>


                    <!-- PRODUCT INFORMATION -->

                    <div class="product-info">


                        <span class="category">

                            <?php

                            echo htmlspecialchars(
                                $product["category"]
                                ?: "General"
                            );

                            ?>

                        </span>


                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $product["name"]
                            );

                            ?>

                        </h3>


                        <p class="description">

                            <?php

                            $description =
                                trim(
                                    $product["description"]
                                    ?? ""
                                );

                            if ($description === "") {

                                $description =
                                    "Quality healthcare product from PulseMed Essential.";

                            }

                            echo htmlspecialchars(
                                $description
                            );

                            ?>

                        </p>


                        <div class="product-bottom">

                            <span class="price">

                                KSh

                                <?php

                                echo number_format(
                                    (float)$product["price"],
                                    2
                                );

                                ?>

                            </span>


                            <span class="stock-text">

                                <?php echo $stock; ?>

                                available

                            </span>

                        </div>


                        <!-- =================================================
                             PLACE ORDER BUTTON
                             ================================================= -->

                        <?php if ($stock > 0): ?>

                            <a
                                href="place_order.php?product_id=<?php echo (int)$product["id"]; ?>"
                                class="order-btn"
                            >
                                Place Order
                            </a>

                        <?php else: ?>

                            <span class="out-of-stock-btn">
                                Out of Stock
                            </span>

                        <?php endif; ?>


                    </div>

                </div>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <!-- =================================================
             NO PRODUCTS FOUND
             ================================================= -->

        <div class="no-products">

            <div class="no-products-icon">
                +
            </div>

            <h3>
                No Products Found
            </h3>

            <p>
                Try another product name or category.
            </p>

        </div>


    <?php endif; ?>


</div>


<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer>

    <p>

        &copy; <?php echo date("Y"); ?>

        PulseMed Essential.

        All Rights Reserved.

    </p>

</footer>


<script src="/assets/js/pulsemed-animations.js?v=1" defer></script>
</body>

</html>

<?php

$stmt->close();
$conn->close();

?>