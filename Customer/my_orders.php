<?php
session_start();
require_once "../database.php";

/* =========================================================
   CUSTOMER LOGIN CHECK
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION["user_role"] ?? "") !== "customer") {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Customer";


/* =========================================================
   FETCH CUSTOMER ORDERS
   ========================================================= */

$sql = "
    SELECT
        o.id AS order_id,
        o.total_amount,
        o.status AS order_status,
        o.order_date,

        oi.id AS item_id,
        oi.quantity,
        oi.price,
        oi.subtotal,

        p.name AS product_name,
        p.category AS product_category,
        p.image AS product_image,

        pay.payment_method,
        pay.transaction_code,
        pay.payment_status,
        pay.payment_date

    FROM orders o

    INNER JOIN order_items oi
        ON o.id = oi.order_id

    INNER JOIN products p
        ON oi.product_id = p.id

    LEFT JOIN payments pay
        ON o.id = pay.order_id

    WHERE o.user_id = ?

    ORDER BY o.order_date DESC, oi.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();


/* =========================================================
   COUNT ORDERS
   ========================================================= */

$total_orders = count($orders);

$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;

foreach ($orders as $order) {

    if ($order["order_status"] === "Pending") {
        $pending_orders++;
    }

    if ($order["order_status"] === "Completed") {
        $completed_orders++;
    }

    if ($order["order_status"] === "Cancelled") {
        $cancelled_orders++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Orders - PulseMed Essential</title>


    <style>

        /* =================================================
           GENERAL
           ================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f8f7;
            color: #263238;
        }


        /* =================================================
           HEADER
           ================================================= */

        header {
            background: #ffffff;
            border-bottom: 1px solid #e1e8e5;

            padding: 15px 40px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            position: sticky;
            top: 0;

            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .brand-logo {
            width: 155px;
            max-height: 65px;
            object-fit: contain;
        }


        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .welcome {
            color: #607d8b;
            font-size: 14px;
        }

        .welcome strong {
            color: #087f5b;
        }


        .nav-btn {
            text-decoration: none;
            background: #087f5b;
            color: white;

            padding: 10px 17px;

            border-radius: 7px;

            font-size: 14px;
            font-weight: bold;

            transition: 0.3s;
        }

        .nav-btn:hover {
            background: #05694b;
        }


        /* =================================================
           MAIN CONTAINER
           ================================================= */

        .container {
            width: 92%;
            max-width: 1200px;

            margin: 40px auto;
        }


        /* =================================================
           PAGE TITLE
           ================================================= */

        .page-title {
            margin-bottom: 30px;
        }

        .page-title h1 {
            color: #087f5b;
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-title p {
            color: #607d8b;
            font-size: 15px;
        }


        /* =================================================
           STATISTICS
           ================================================= */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 30px;
        }


        .stat-card {
            background: white;

            padding: 22px;

            border-radius: 10px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.06);

            border-left: 4px solid #087f5b;
        }

        .stat-card h3 {
            color: #78909c;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .stat-card strong {
            color: #263238;
            font-size: 25px;
        }


        /* =================================================
           ORDERS CONTAINER
           ================================================= */

        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }


        /* =================================================
           ORDER CARD
           ================================================= */

        .order-card {
            background: #ffffff;

            border-radius: 12px;

            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.06);

            overflow: hidden;
        }


        /* =================================================
           ORDER HEADER
           ================================================= */

        .order-header {
            background: #f8faf9;

            padding: 18px 22px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            border-bottom: 1px solid #e6ece9;
        }


        .order-number {
            font-size: 16px;
            font-weight: bold;
            color: #263238;
        }

        .order-date {
            margin-top: 5px;
            color: #78909c;
            font-size: 13px;
        }


        /* =================================================
           STATUS BADGES
           ================================================= */

        .status {
            display: inline-block;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        .status-pending {
            background: #fff8e1;
            color: #f57f17;
        }

        .status-processing {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }


        /* =================================================
           ORDER BODY
           ================================================= */

        .order-body {
            padding: 22px;
        }


        .product-row {
            display: flex;

            align-items: center;

            gap: 18px;

            padding-bottom: 20px;

            border-bottom: 1px solid #edf1ef;

            margin-bottom: 18px;
        }


        .product-image {
            width: 90px;
            height: 90px;

            object-fit: contain;

            padding: 8px;

            background: #f7faf8;

            border-radius: 8px;
        }


        .no-image {
            width: 90px;
            height: 90px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #f0f4f2;

            color: #90a4ae;

            border-radius: 8px;

            font-size: 11px;

            text-align: center;
        }


        .product-info {
            flex: 1;
        }


        .product-info h3 {
            color: #263238;

            font-size: 17px;

            margin-bottom: 6px;
        }


        .product-category {
            color: #087f5b;

            font-size: 12px;

            font-weight: bold;

            margin-bottom: 7px;
        }


        .product-details {
            color: #607d8b;

            font-size: 13px;

            line-height: 1.6;
        }


        .product-subtotal {
            text-align: right;
        }

        .product-subtotal span {
            display: block;

            color: #78909c;

            font-size: 12px;

            margin-bottom: 5px;
        }

        .product-subtotal strong {
            color: #087f5b;

            font-size: 17px;
        }


        /* =================================================
           ORDER FOOTER
           ================================================= */

        .order-footer {
            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            flex-wrap: wrap;
        }


        .payment-info {
            font-size: 13px;

            color: #607d8b;

            line-height: 1.7;
        }


        .payment-info strong {
            color: #455a64;
        }


        .payment-status {
            font-weight: bold;
        }

        .payment-paid {
            color: #2e7d32;
        }

        .payment-pending {
            color: #f57f17;
        }

        .payment-failed {
            color: #c62828;
        }

        .payment-refunded {
            color: #6a1b9a;
        }


        .total-box {
            text-align: right;
        }

        .total-label {
            color: #78909c;

            font-size: 13px;

            margin-bottom: 5px;
        }

        .total-amount {
            color: #087f5b;

            font-size: 23px;

            font-weight: bold;
        }


        /* =================================================
           PAYMENT BUTTON
           ================================================= */

        .payment-btn {
            display: inline-block;

            text-decoration: none;

            background: #087f5b;

            color: white;

            padding: 10px 17px;

            border-radius: 7px;

            font-size: 13px;

            font-weight: bold;

            margin-top: 10px;

            transition: 0.3s;
        }

        .payment-btn:hover {
            background: #05694b;
        }


        /* =================================================
           EMPTY ORDERS
           ================================================= */

        .empty-orders {
            background: white;

            border-radius: 12px;

            padding: 60px 30px;

            text-align: center;

            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.06);
        }


        .empty-icon {
            font-size: 55px;

            margin-bottom: 18px;

            opacity: 0.7;
        }


        .empty-orders h2 {
            color: #455a64;

            margin-bottom: 10px;
        }


        .empty-orders p {
            color: #78909c;

            font-size: 14px;

            margin-bottom: 25px;
        }


        .browse-btn {
            display: inline-block;

            text-decoration: none;

            background: #087f5b;

            color: white;

            padding: 12px 20px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: bold;
        }

        .browse-btn:hover {
            background: #05694b;
        }


        /* =================================================
           FOOTER
           ================================================= */

        footer {
            text-align: center;

            padding: 35px 20px;

            color: #78909c;

            font-size: 13px;
        }


        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 850px) {

            header {
                padding: 13px 20px;
            }

            .welcome {
                display: none;
            }

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .product-row {
                align-items: flex-start;
            }

        }


        @media (max-width: 600px) {

            .container {
                width: 94%;

                margin: 28px auto;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .order-header {
                flex-direction: column;

                align-items: flex-start;
            }

            .product-row {
                flex-wrap: wrap;
            }

            .product-subtotal {
                width: 100%;

                text-align: left;
            }

            .order-footer {
                flex-direction: column;

                align-items: flex-start;
            }

            .total-box {
                text-align: left;
            }

            .brand-logo {
                width: 125px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     HEADER
     ===================================================== -->

<header>

    <a href="dashboard.php" class="brand">

        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essential Logo"
            class="brand-logo"
        >

    </a>


    <div class="header-right">

        <div class="welcome">

            Welcome,
            <strong>
                <?php echo htmlspecialchars($user_name); ?>
            </strong>

        </div>


        <a
            href="browse_products.php"
            class="nav-btn"
        >
            Browse Products
        </a>

    </div>

</header>


<!-- =====================================================
     MAIN
     ===================================================== -->

<main class="container">


    <!-- PAGE TITLE -->

    <div class="page-title">

        <h1>My Orders</h1>

        <p>
            View and track all your PulseMed Essential orders.
        </p>

    </div>


    <!-- =================================================
         STATISTICS
         ================================================= -->

    <div class="stats">


        <div class="stat-card">

            <h3>Total Orders</h3>

            <strong>
                <?php echo $total_orders; ?>
            </strong>

        </div>


        <div class="stat-card">

            <h3>Pending</h3>

            <strong>
                <?php echo $pending_orders; ?>
            </strong>

        </div>


        <div class="stat-card">

            <h3>Completed</h3>

            <strong>
                <?php echo $completed_orders; ?>
            </strong>

        </div>


        <div class="stat-card">

            <h3>Cancelled</h3>

            <strong>
                <?php echo $cancelled_orders; ?>
            </strong>

        </div>

    </div>


    <!-- =================================================
         ORDERS
         ================================================= -->

    <?php if (empty($orders)): ?>


        <div class="empty-orders">

            <div class="empty-icon">
                🛒
            </div>


            <h2>
                No Orders Yet
            </h2>


            <p>
                You have not placed any orders yet.
                Browse our available medical products to get started.
            </p>


            <a
                href="browse_products.php"
                class="browse-btn"
            >
                Browse Products
            </a>

        </div>


    <?php else: ?>


        <div class="orders-container">


            <?php foreach ($orders as $order): ?>


                <div class="order-card">


                    <!-- ORDER HEADER -->

                    <div class="order-header">


                        <div>

                            <div class="order-number">

                                Order #
                                <?php echo (int) $order["order_id"]; ?>

                            </div>


                            <div class="order-date">

                                Placed on
                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime($order["order_date"])
                                );
                                ?>

                            </div>

                        </div>


                        <?php

                        $status_class = "status-pending";

                        if ($order["order_status"] === "Processing") {
                            $status_class = "status-processing";
                        }

                        if ($order["order_status"] === "Completed") {
                            $status_class = "status-completed";
                        }

                        if ($order["order_status"] === "Cancelled") {
                            $status_class = "status-cancelled";
                        }

                        ?>


                        <span
                            class="status <?php echo $status_class; ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $order["order_status"]
                            );
                            ?>

                        </span>


                    </div>


                    <!-- ORDER BODY -->

                    <div class="order-body">


                        <!-- PRODUCT -->

                        <div class="product-row">


                            <?php if (!empty($order["product_image"])): ?>

                                <img
                                    src="../assets/images/products/<?php echo htmlspecialchars($order["product_image"]); ?>"
                                    alt="<?php echo htmlspecialchars($order["product_name"]); ?>"
                                    class="product-image"
                                >

                            <?php else: ?>

                                <div class="no-image">
                                    No Image
                                </div>

                            <?php endif; ?>


                            <div class="product-info">

                                <h3>
                                    <?php
                                    echo htmlspecialchars(
                                        $order["product_name"]
                                    );
                                    ?>
                                </h3>


                                <div class="product-category">

                                    <?php
                                    echo htmlspecialchars(
                                        $order["product_category"]
                                    );
                                    ?>

                                </div>


                                <div class="product-details">

                                    Quantity:
                                    <strong>
                                        <?php echo (int) $order["quantity"]; ?>
                                    </strong>

                                    <br>

                                    Unit Price:
                                    <strong>
                                        KSh
                                        <?php
                                        echo number_format(
                                            (float) $order["price"],
                                            2
                                        );
                                        ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="product-subtotal">

                                <span>
                                    Subtotal
                                </span>


                                <strong>

                                    KSh
                                    <?php
                                    echo number_format(
                                        (float) $order["subtotal"],
                                        2
                                    );
                                    ?>

                                </strong>

                            </div>


                        </div>


                        <!-- ORDER FOOTER -->

                        <div class="order-footer">


                            <!-- PAYMENT INFORMATION -->

                            <div class="payment-info">

                                <strong>
                                    Payment
                                </strong>

                                <br>


                                Method:

                                <?php
                                echo htmlspecialchars(
                                    $order["payment_method"]
                                    ?? "Not selected"
                                );
                                ?>


                                <br>


                                Status:

                                <?php

                                $payment_status =
                                    $order["payment_status"]
                                    ?? "Pending";

                                $payment_class =
                                    "payment-pending";

                                if ($payment_status === "Paid") {
                                    $payment_class =
                                        "payment-paid";
                                }

                                if ($payment_status === "Failed") {
                                    $payment_class =
                                        "payment-failed";
                                }

                                if ($payment_status === "Refunded") {
                                    $payment_class =
                                        "payment-refunded";
                                }

                                ?>


                                <span
                                    class="payment-status <?php echo $payment_class; ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $payment_status
                                    );
                                    ?>

                                </span>


                                <?php if (!empty($order["transaction_code"])): ?>

                                    <br>

                                    Transaction:

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $order["transaction_code"]
                                        );
                                        ?>
                                    </strong>

                                <?php endif; ?>


                                <?php if ($payment_status !== "Paid"
                                    && $order["order_status"] !== "Cancelled"): ?>

                                    <br>

                                    <a
                                        href="payment.php?order_id=<?php echo (int) $order["order_id"]; ?>"
                                        class="payment-btn"
                                    >
                                        Make Payment
                                    </a>

                                <?php endif; ?>

                            </div>


                            <!-- TOTAL -->

                            <div class="total-box">

                                <div class="total-label">
                                    Order Total
                                </div>


                                <div class="total-amount">

                                    KSh
                                    <?php
                                    echo number_format(
                                        (float) $order["total_amount"],
                                        2
                                    );
                                    ?>

                                </div>

                            </div>


                        </div>


                    </div>

                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer>

    © <?php echo date("Y"); ?>
    PulseMed Essential.
    All Rights Reserved.

</footer>


<script src="/assets/js/pulsemed-animations.js?v=1" defer></script>
</body>

</html>