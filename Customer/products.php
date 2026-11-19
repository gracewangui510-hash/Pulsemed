<?php
session_start();
require_once "../database.php";

/*
|--------------------------------------------------------------------------
| CUSTOMER ACCESS PROTECTION
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "customer") {
    header("Location: ../login.php");
    exit();
}

$user_name = $_SESSION["user_name"] ?? "Customer";

/*
|--------------------------------------------------------------------------
| SEARCH AND CATEGORY FILTER
|--------------------------------------------------------------------------
*/
$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");

/*
|--------------------------------------------------------------------------
| FETCH CATEGORIES
|--------------------------------------------------------------------------
*/
$category_sql = "
    SELECT DISTINCT category
    FROM products
    WHERE category IS NOT NULL
      AND category != ''
    ORDER BY category ASC
";

$category_result = $conn->query($category_sql);

/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT id, name, category, description, price, stock, image
    FROM products
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (name LIKE ? OR category LIKE ? OR description LIKE ?)";

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Products | PulseMed Essential</title>

    <style>

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
           NAVIGATION BAR
           ========================================================= */

        .navbar {
            width: 100%;
            background: #ffffff;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
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
            font-size: 21px;
            font-weight: bold;
            color: #087f5b;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-links a {
            text-decoration: none;
            color: #455a64;
            font-size: 15px;
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #087f5b;
        }

        .logout {
            background: #087f5b;
            color: white !important;
            padding: 10px 18px;
            border-radius: 8px;
        }

        .logout:hover {
            background: #05694b;
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
            padding: 55px 6%;
        }

        .page-header h1 {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .page-header p {
            font-size: 16px;
            opacity: 0.95;
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
           SEARCH AREA
           ========================================================= */

        .filter-box {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.07);
            margin-bottom: 30px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 230px 120px 100px;
            gap: 12px;
            align-items: center;
        }

        .filter-form input,
        .filter-form select {
            width: 100%;
            height: 45px;
            border: 1px solid #d8e2df;
            border-radius: 7px;
            padding: 0 14px;
            font-size: 14px;
            outline: none;
        }

        .filter-form input:focus,
        .filter-form select:focus {
            border-color: #087f5b;
        }

        .search-btn {
            height: 45px;
            border: none;
            background: #087f5b;
            color: white;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .search-btn:hover {
            background: #05694b;
        }

        .clear-btn {
            height: 45px;
            border: 1px solid #087f5b;
            background: white;
            color: #087f5b;
            border-radius: 7px;
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
           RESULTS HEADER
           ========================================================= */

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .results-header h2 {
            color: #263238;
            font-size: 22px;
        }

        .result-count {
            color: #607d8b;
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

        .product-card {
            background: white;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.11);
        }

        /* =========================================================
           PRODUCT IMAGE
           ========================================================= */

        .product-image {
            height: 210px;
            background: #eef8f4;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
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
            background: #d8eee6;
            color: #087f5b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
        }

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
            background: #e3f7ec;
            color: #087f5b;
        }

        .low-stock {
            background: #fff4d6;
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
            color: #087f5b;
            background: #e8f7f1;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .product-info h3 {
            font-size: 18px;
            margin-bottom: 9px;
            color: #263238;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #edf1f0;
            padding-top: 15px;
        }

        .price {
            color: #087f5b;
            font-size: 19px;
            font-weight: bold;
        }

        .stock-text {
            font-size: 12px;
            color: #78909c;
        }

        /* =========================================================
           NO PRODUCTS
           ========================================================= */

        .no-products {
            background: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.06);
        }

        .no-products-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            background: #e8f7f1;
            color: #087f5b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
        }

        .no-products h3 {
            margin-bottom: 8px;
            color: #37474f;
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
            margin-top: 50px;
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

            .filter-form {
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
                gap: 15px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-header h1 {
                font-size: 28px;
            }

        }

        @media (max-width: 550px) {

            .products-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .container {
                width: 92%;
            }

            .results-header {
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

        <a href="products.php" class="active">
            Products
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
        Medical Products
    </h1>

    <p>
        Browse our available medical and healthcare products.
    </p>

</section>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<div class="container">

    <!-- SEARCH AND FILTER -->

    <div class="filter-box">

        <form
            method="GET"
            action="products.php"
            class="filter-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search products..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <select name="category">

                <option value="">
                    All Categories
                </option>

                <?php if ($category_result && $category_result->num_rows > 0): ?>

                    <?php while ($cat = $category_result->fetch_assoc()): ?>

                        <option
                            value="<?php echo htmlspecialchars($cat["category"]); ?>"
                            <?php echo ($category === $cat["category"]) ? "selected" : ""; ?>
                        >
                            <?php echo htmlspecialchars($cat["category"]); ?>
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
                href="products.php"
                class="clear-btn"
            >
                Clear
            </a>

        </form>

    </div>


    <!-- RESULTS HEADER -->

    <div class="results-header">

        <h2>
            Available Products
        </h2>

        <span class="result-count">
            <?php echo $product_count; ?>
            product<?php echo ($product_count !== 1) ? "s" : ""; ?> found
        </span>

    </div>


    <!-- =====================================================
         PRODUCTS
         ===================================================== -->

    <?php if ($product_count > 0): ?>

        <div class="products-grid">

            <?php while ($product = $result->fetch_assoc()): ?>

                <?php

                $stock = (int) $product["stock"];

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


                        <span class="stock-badge <?php echo $stock_class; ?>">

                            <?php echo $stock_text; ?>

                        </span>

                    </div>


                    <!-- PRODUCT INFORMATION -->

                    <div class="product-info">

                        <span class="category">

                            <?php
                            echo htmlspecialchars(
                                $product["category"] ?: "General"
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
                                trim($product["description"] ?? "");

                            echo htmlspecialchars(
                                $description !== ""
                                    ? $description
                                    : "Quality healthcare product from PulseMed Essential."
                            );

                            ?>

                        </p>


                        <div class="product-bottom">

                            <span class="price">

                                KSh
                                <?php
                                echo number_format(
                                    (float) $product["price"],
                                    2
                                );
                                ?>

                            </span>


                            <span class="stock-text">

                                <?php echo $stock; ?> available

                            </span>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <!-- NO PRODUCTS -->

        <div class="no-products">

            <div class="no-products-icon">
                +
            </div>

            <h3>
                No Products Found
            </h3>

            <p>
                No products match your search or selected category.
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