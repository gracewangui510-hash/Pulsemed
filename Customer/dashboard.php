<?php

session_start();
require_once "../database.php";

/* =========================================================
   CUSTOMER ACCESS PROTECTION
========================================================= */

/* Check whether user is logged in */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

/* Get and normalize session role */
$session_role = strtolower(trim($_SESSION["user_role"] ?? ""));

/* Allow customers only */
if ($session_role !== "customer") {

    /* If admin is trying to access customer dashboard */
    if ($session_role === "admin") {
        header("Location: ../admin/dashboard.php");
        exit();
    }

    /* Unknown or invalid role */
    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit();
}


/* =========================================================
   GET LOGGED-IN CUSTOMER
========================================================= */

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT id, name, email, phone, role, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user_result = $stmt->get_result();

if ($user_result->num_rows !== 1) {

    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit();
}

$user = $user_result->fetch_assoc();

$stmt->close();


/* =========================================================
   VERIFY DATABASE ROLE
========================================================= */

$db_role = strtolower(trim($user["role"] ?? ""));

if ($db_role !== "customer") {

    if ($db_role === "admin") {
        $_SESSION["user_role"] = "admin";
        header("Location: ../admin/dashboard.php");
        exit();
    }

    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit();
}


/* =========================================================
   UPDATE SESSION INFORMATION
========================================================= */

$_SESSION["user_id"] = $user["id"];
$_SESSION["user_name"] = $user["name"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["user_role"] = "customer";

$user_name = $user["name"];
$user_email = $user["email"];


/* =========================================================
   CUSTOMER STATISTICS
========================================================= */

/* Total orders */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$total_orders = $result->fetch_assoc()["total"] ?? 0;

$stmt->close();


/* Pending orders */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE user_id = ?
      AND status IN ('Pending', 'Processing')
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$pending_orders = $result->fetch_assoc()["total"] ?? 0;

$stmt->close();


/* Completed orders */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE user_id = ?
      AND status = 'Completed'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$completed_orders = $result->fetch_assoc()["total"] ?? 0;

$stmt->close();


/* Pending payments */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM payments
    WHERE user_id = ?
      AND payment_status = 'Pending'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$pending_payments = $result->fetch_assoc()["total"] ?? 0;

$stmt->close();


/* =========================================================
   AVAILABLE PRODUCTS
========================================================= */

$product_query = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE stock > 0
");

$total_products = 0;

if ($product_query) {
    $total_products = $product_query->fetch_assoc()["total"] ?? 0;
}


/* =========================================================
   FEATURED PRODUCTS
========================================================= */

$featured_products = $conn->query("
    SELECT
        id,
        name,
        category,
        description,
        price,
        stock,
        image
    FROM products
    WHERE stock > 0
    ORDER BY id DESC
    LIMIT 6
");


/* =========================================================
   RECENT ORDERS
========================================================= */

$recent_orders = $conn->prepare("
    SELECT
        orders.id,
        orders.total_amount,
        orders.status,
        orders.order_date,
        payments.payment_status
    FROM orders
    LEFT JOIN payments
        ON orders.id = payments.order_id
    WHERE orders.user_id = ?
    ORDER BY orders.id DESC
    LIMIT 5
");

$recent_orders->bind_param("i", $user_id);
$recent_orders->execute();

$recent_orders_result = $recent_orders->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Customer Dashboard | PulseMed Essential</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #16574d;
            color: #156358;
        }

        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {
            background: #53243e;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 45px;
            border-bottom: 1px solid #632036;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 22px;
            font-weight: bold;
            color: #1e9650;
        }

        .brand-name span {
            color: #328c08;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-links a {
            text-decoration: none;
            color: #63cafa;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-links a:hover {
            color: #088c85;
        }

        .nav-links .active {
            color: #087f8c;
        }

        .logout-btn {
            background: #53162a;
            color: white !important;
            padding: 10px 18px;
            border-radius: 7px;
        }

        .logout-btn:hover {
            background: #3b0c1a !important;
        }


        /* =====================================================
           MAIN CONTAINER
        ===================================================== */

        .container {
            max-width: 1250px;
            margin: auto;
            padding: 35px 25px;
        }


        /* =====================================================
           WELCOME SECTION
        ===================================================== */

        .welcome-section {
            background: linear-gradient(
                135deg,
                #088c1e,
                #088c59
            );

            color: white;

            padding: 35px;

            border-radius: 15px;

            margin-bottom: 30px;

            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .welcome-section h1 {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .welcome-section p {
            font-size: 15px;
            opacity: 0.95;
        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .stats-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 35px;
        }

        .stat-card {
            background: white;

            border-radius: 12px;

            padding: 25px;

            display: flex;

            align-items: center;

            gap: 18px;

            box-shadow:
                0 5px 18px rgba(146, 53, 76, 0.06);

            border: 1px solid #e7f0ed;
        }

        .stat-icon {
            width: 55px;
            height: 55px;

            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 24px;

            background: #e7f8f0;

            color: #087f8c;
        }

        .stat-info h3 {
            font-size: 25px;
            color: #263238;
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: 13px;
            color: #78909c;
        }


        /* =====================================================
           SECTION HEADER
        ===================================================== */

        .section-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 21px;
            color: #336d46;
        }

        .view-all {
            text-decoration: none;

            color: #3d8c08;

            font-size: 14px;

            font-weight: bold;
        }

        .view-all:hover {
            text-decoration: underline;
        }


        /* =====================================================
           PRODUCT GRID
        ===================================================== */

        .products-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 22px;

            margin-bottom: 40px;
        }

        .product-card {
            background: white;

            border-radius: 12px;

            overflow: hidden;

            border: 1px solid #4e9e2e;

            box-shadow:
                0 5px 18px rgba(196, 74, 155, 0.05);

            transition: 0.3s;
        }

        .product-card:hover {
            transform: translateY(-4px);

            box-shadow:
                0 10px 25px rgba(109, 21, 72, 0.09);
        }

        .product-image {
            width: 100%;
            height: 180px;

            background: #268b6a;

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

        .product-placeholder {
            font-size: 50px;
            color: #087f8c;
        }

        .product-content {
            padding: 20px;
        }

        .product-category {
            font-size: 12px;

            color: #088c59;

            font-weight: bold;

            margin-bottom: 7px;

            text-transform: uppercase;
        }

        .product-content h3 {
            font-size: 17px;

            margin-bottom: 8px;

            color: #263238;
        }

        .product-description {
            color: #78909c;

            font-size: 13px;

            line-height: 1.5;

            min-height: 40px;

            margin-bottom: 15px;
        }

        .product-bottom {
            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .price {
            font-size: 18px;

            font-weight: bold;

            color: #087f8c;
        }

        .stock {
            font-size: 12px;

            color: #088c59;
        }

        .order-btn {
            display: inline-block;

            background: #339c67;

            color: white;

            padding: 9px 14px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 12px;

            font-weight: bold;

            margin-top: 15px;

            transition: 0.3s;
        }

        .order-btn:hover {
            background: #48b118;
        }


        /* =====================================================
           RECENT ORDERS
        ===================================================== */

        .orders-card {
            background: white;

            border-radius: 12px;

            padding: 25px;

            border: 1px solid #659b28;

            box-shadow:
                0 5px 18px rgba(177, 63, 152, 0.05);

            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 650px;
        }

        th {
            text-align: left;

            padding: 14px;

            font-size: 13px;

            color: #2c7397;

            background: #f5faf9;

            border-bottom: 1px solid #e5eeee;
        }

        td {
            padding: 15px 14px;

            font-size: 13px;

            border-bottom: 1px solid #4e6429;

            color: #455a64;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status {
            display: inline-block;

            padding: 6px 11px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;
        }

        .status.pending {
            background: #fff4d6;
            color: #9a7200;
        }

        .status.processing {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status.completed {
            background: #e4f7ed;
            color: #16804c;
        }

        .status.cancelled {
            background: #fdeaea;
            color: #c0392b;
        }

        .payment-paid {
            color: #16804c;
            font-weight: bold;
        }

        .payment-pending {
            color: #b07a00;
            font-weight: bold;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-state {
            text-align: center;

            padding: 40px 20px;

            color: #78909c;
        }

        .empty-state-icon {
            font-size: 45px;

            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 14px;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            background: #6e1d5a;

            border-top: 1px solid #24583b;

            text-align: center;

            padding: 22px;

            margin-top: 40px;

            color: #78909c;

            font-size: 13px;
        }


        /* =====================================================
           RESPONSIVE DESIGN
        ===================================================== */

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .products-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .navbar {
                padding: 0 25px;
            }

            .nav-links {
                gap: 15px;
            }
        }


        @media (max-width: 700px) {

            .navbar {
                height: auto;

                padding: 15px 20px;

                flex-direction: column;

                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;

                justify-content: center;
            }

            .container {
                padding: 25px 15px;
            }

            .welcome-section {
                padding: 25px;
            }

            .welcome-section h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     NAVIGATION BAR
========================================================= -->

<nav class="navbar">

    <div class="logo-area">

        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essential Logo"
            class="logo"
        >

        <div class="brand-name">
            PulseMed <span>Essential</span>
        </div>

    </div>


    <div class="nav-links">

        <a
            href="dashboard.php"
            class="active"
        >
            Dashboard
        </a>

        <a href="browse_products.php">
            Browse Products
        </a>

        <a href="my_orders.php">
            My Orders
        </a>

        <a href="profile.php">
            Profile
        </a>

        <!-- IMPORTANT:
             logout.php is in the PROJECT ROOT,
             therefore use ../logout.php
        -->

        <a
            href="../logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</nav>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="container">


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <section class="welcome-section">

        <h1>
            Welcome, <?php echo htmlspecialchars($user_name); ?> 👋
        </h1>

        <p>
            Welcome to your PulseMed Essential customer dashboard.
            Manage your orders, browse medical products and track
            your purchases easily.
        </p>

    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats-grid">


        <!-- Total Orders -->

        <div class="stat-card">

            <div class="stat-icon">
                🛒
            </div>

            <div class="stat-info">

                <h3>
                    <?php echo (int) $total_orders; ?>
                </h3>

                <p>
                    Total Orders
                </p>

            </div>

        </div>


        <!-- Pending Orders -->

        <div class="stat-card">

            <div class="stat-icon">
                ⏳
            </div>

            <div class="stat-info">

                <h3>
                    <?php echo (int) $pending_orders; ?>
                </h3>

                <p>
                    Pending Orders
                </p>

            </div>

        </div>


        <!-- Completed Orders -->

        <div class="stat-card">

            <div class="stat-icon">
                ✓
            </div>

            <div class="stat-info">

                <h3>
                    <?php echo (int) $completed_orders; ?>
                </h3>

                <p>
                    Completed Orders
                </p>

            </div>

        </div>


        <!-- Pending Payments -->

        <div class="stat-card">

            <div class="stat-icon">
                💳
            </div>

            <div class="stat-info">

                <h3>
                    <?php echo (int) $pending_payments; ?>
                </h3>

                <p>
                    Pending Payments
                </p>

            </div>

        </div>

    </section>



    <!-- =====================================================
         FEATURED PRODUCTS
    ====================================================== -->

    <div class="section-header">

        <h2>
            Featured Products
        </h2>

        <a
            href="browse_products.php"
            class="view-all"
        >
            View All Products →
        </a>

    </div>


    <section class="products-grid">

        <?php if ($featured_products && $featured_products->num_rows > 0): ?>

            <?php while ($product = $featured_products->fetch_assoc()): ?>

                <div class="product-card">


                    <!-- Product Image -->

                    <div class="product-image">

                        <?php

                        $image = trim($product["image"] ?? "");

                        $image_path =
                            "../assets/images/products/" . $image;

                        ?>

                        <?php if (
                            !empty($image) &&
                            file_exists(
                                __DIR__ .
                                "/../assets/images/products/" .
                                $image
                            )
                        ): ?>

                            <img
                                src="<?php echo htmlspecialchars($image_path); ?>"
                                alt="<?php echo htmlspecialchars($product["name"]); ?>"
                            >

                        <?php else: ?>

                            <div class="product-placeholder">
                                🏥
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- Product Information -->

                    <div class="product-content">

                        <div class="product-category">

                            <?php
                            echo htmlspecialchars(
                                $product["category"] ?? "Medical Product"
                            );
                            ?>

                        </div>


                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $product["name"]
                            );
                            ?>

                        </h3>


                        <p class="product-description">

                            <?php

                            $description =
                                $product["description"] ?? "";

                            if (strlen($description) > 80) {

                                $description =
                                    substr(
                                        $description,
                                        0,
                                        80
                                    ) . "...";
                            }

                            echo htmlspecialchars($description);

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


                            <span class="stock">

                                <?php
                                echo (int) $product["stock"];
                                ?>
                                in stock

                            </span>

                        </div>


                        <a
                            href="place_order.php?product_id=<?php echo (int) $product["id"]; ?>"
                            class="order-btn"
                        >
                            Place Order
                        </a>

                    </div>

                </div>

            <?php endwhile; ?>


        <?php else: ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    📦
                </div>

                <p>
                    No products are currently available.
                </p>

            </div>

        <?php endif; ?>

    </section>



    <!-- =====================================================
         RECENT ORDERS
    ====================================================== -->

    <div class="section-header">

        <h2>
            Recent Orders
        </h2>

        <a
            href="my_orders.php"
            class="view-all"
        >
            View All Orders →
        </a>

    </div>


    <div class="orders-card">

        <?php if (
            $recent_orders_result &&
            $recent_orders_result->num_rows > 0
        ): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Order ID
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Total Amount
                        </th>

                        <th>
                            Order Status
                        </th>

                        <th>
                            Payment
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php while (
                        $order =
                        $recent_orders_result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                #<?php
                                echo (int) $order["id"];
                                ?>
                            </td>


                            <td>

                                <?php

                                echo !empty($order["order_date"])
                                    ? date(
                                        "d M Y",
                                        strtotime(
                                            $order["order_date"]
                                        )
                                    )
                                    : "N/A";

                                ?>

                            </td>


                            <td>

                                <strong>

                                    KSh
                                    <?php

                                    echo number_format(
                                        (float)
                                        $order["total_amount"],
                                        2
                                    );

                                    ?>

                                </strong>

                            </td>


                            <td>

                                <?php

                                $status =
                                    strtolower(
                                        trim(
                                            $order["status"] ?? ""
                                        )
                                    );

                                $status_class =
                                    "pending";

                                if (
                                    $status === "completed"
                                ) {
                                    $status_class =
                                        "completed";

                                } elseif (
                                    $status === "processing"
                                ) {
                                    $status_class =
                                        "processing";

                                } elseif (
                                    $status === "cancelled"
                                ) {
                                    $status_class =
                                        "cancelled";
                                }

                                ?>

                                <span
                                    class="status <?php echo $status_class; ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $order["status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php

                                $payment_status =
                                    strtolower(
                                        trim(
                                            $order[
                                                "payment_status"
                                            ] ?? ""
                                        )
                                    );

                                if (
                                    $payment_status ===
                                    "paid"
                                ):

                                ?>

                                    <span
                                        class="payment-paid"
                                    >
                                        Paid
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="payment-pending"
                                    >
                                        <?php

                                        echo htmlspecialchars(
                                            $order[
                                                "payment_status"
                                            ] ?? "Pending"
                                        );

                                        ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>


        <?php else: ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    🛍️
                </div>

                <p>
                    You have not placed any orders yet.
                </p>

                <a
                    href="browse_products.php"
                    class="order-btn"
                >
                    Browse Products
                </a>

            </div>

        <?php endif; ?>

    </div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <p>
        &copy;
        <?php echo date("Y"); ?>
        PulseMed Essential.
        All Rights Reserved.
    </p>

</footer>


</body>
</html>

<?php

/* Close recent orders statement */

if ($recent_orders) {
    $recent_orders->close();
}

?>