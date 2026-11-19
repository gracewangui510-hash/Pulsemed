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
   GET PRODUCT ID
   ========================================================= */
$product_id = 0;

if (isset($_GET["product_id"])) {
    $product_id = (int) $_GET["product_id"];
} elseif (isset($_POST["product_id"])) {
    $product_id = (int) $_POST["product_id"];
}

if ($product_id <= 0) {
    header("Location: browse_products.php");
    exit();
}

/* =========================================================
   FETCH PRODUCT
   ========================================================= */
$stmt = $conn->prepare("
    SELECT id, name, category, description, price, stock, image
    FROM products
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    header("Location: browse_products.php");
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

$error = "";
$success = "";
$order_id = 0;

/* =========================================================
   PROCESS ORDER
   ========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $quantity = (int) ($_POST["quantity"] ?? 0);

    /* -----------------------------------------------------
       VALIDATE QUANTITY
       ----------------------------------------------------- */
    if ($quantity <= 0) {

        $error = "Please enter a valid quantity.";

    } elseif ($quantity > (int) $product["stock"]) {

        $error = "Only " . (int) $product["stock"] .
                 " item(s) are currently available.";

    } else {

        $price = (float) $product["price"];
        $subtotal = $price * $quantity;

        /* -------------------------------------------------
           START DATABASE TRANSACTION
           ------------------------------------------------- */
        $conn->begin_transaction();

        try {

            /* ---------------------------------------------
               DOUBLE-CHECK STOCK
               --------------------------------------------- */
            $stock_stmt = $conn->prepare("
                SELECT stock
                FROM products
                WHERE id = ?
                FOR UPDATE
            ");

            $stock_stmt->bind_param("i", $product_id);
            $stock_stmt->execute();

            $stock_result = $stock_stmt->get_result();
            $stock_data = $stock_result->fetch_assoc();

            $stock_stmt->close();

            if (!$stock_data) {
                throw new Exception("Product no longer exists.");
            }

            $current_stock = (int) $stock_data["stock"];

            if ($quantity > $current_stock) {
                throw new Exception(
                    "Sorry, only " . $current_stock .
                    " item(s) are available."
                );
            }

            /* ---------------------------------------------
               CREATE ORDER
               --------------------------------------------- */
            $order_stmt = $conn->prepare("
                INSERT INTO orders
                (user_id, total_amount, status)
                VALUES (?, ?, 'Pending')
            ");

            $order_stmt->bind_param(
                "id",
                $user_id,
                $subtotal
            );

            if (!$order_stmt->execute()) {
                throw new Exception("Unable to create the order.");
            }

            $order_id = $conn->insert_id;

            $order_stmt->close();

            /* ---------------------------------------------
               CREATE ORDER ITEM
               --------------------------------------------- */
            $item_stmt = $conn->prepare("
                INSERT INTO order_items
                (order_id, product_id, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");

            $item_stmt->bind_param(
                "iiidd",
                $order_id,
                $product_id,
                $quantity,
                $price,
                $subtotal
            );

            if (!$item_stmt->execute()) {
                throw new Exception("Unable to save order item.");
            }

            $item_stmt->close();

            /* ---------------------------------------------
               REDUCE PRODUCT STOCK
               --------------------------------------------- */
            $update_stmt = $conn->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
                AND stock >= ?
            ");

            $update_stmt->bind_param(
                "iii",
                $quantity,
                $product_id,
                $quantity
            );

            if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                throw new Exception("Unable to update product stock.");
            }

            $update_stmt->close();

            /* ---------------------------------------------
               CREATE PENDING PAYMENT RECORD
               --------------------------------------------- */
            $payment_method = "M-Pesa";
            $payment_status = "Pending";

            $payment_stmt = $conn->prepare("
                INSERT INTO payments
                (
                    order_id,
                    user_id,
                    amount,
                    payment_method,
                    payment_status
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $payment_stmt->bind_param(
                "iidss",
                $order_id,
                $user_id,
                $subtotal,
                $payment_method,
                $payment_status
            );

            if (!$payment_stmt->execute()) {
                throw new Exception("Unable to create payment record.");
            }

            $payment_stmt->close();

            /* ---------------------------------------------
               COMMIT EVERYTHING
               --------------------------------------------- */
            $conn->commit();

            $success = "Your order has been placed successfully.";

            /* Update displayed stock */
            $product["stock"] = $current_stock - $quantity;

        } catch (Exception $e) {

            /* ---------------------------------------------
               ROLLBACK IF ANYTHING FAILS
               --------------------------------------------- */
            $conn->rollback();

            $order_id = 0;
            $error = $e->getMessage();
        }
    }
}

/* =========================================================
   CALCULATE DISPLAY TOTAL
   ========================================================= */
$display_price = (float) $product["price"];
$available_stock = (int) $product["stock"];
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Place Order - PulseMed Essential</title>

    <style>

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
            color: #455a64;
            font-size: 14px;
        }

        .welcome strong {
            color: #087f5b;
        }

        .back-btn {
            text-decoration: none;
            background: #087f5b;
            color: white;
            padding: 10px 17px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #05694b;
        }

        /* =================================================
           MAIN
           ================================================= */

        .container {
            width: 92%;
            max-width: 1050px;
            margin: 45px auto;
        }

        .page-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-title h1 {
            color: #087f5b;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #607d8b;
            font-size: 15px;
        }

        /* =================================================
           ALERTS
           ================================================= */

        .alert {
            padding: 15px 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        /* =================================================
           ORDER CARD
           ================================================= */

        .order-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        /* =================================================
           PRODUCT SECTION
           ================================================= */

        .product-section {
            border-right: 1px solid #e8eeee;
            padding-right: 35px;
        }

        .product-image {
            width: 100%;
            height: 270px;
            object-fit: contain;
            background: #f8faf9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .no-image {
            width: 100%;
            height: 270px;
            background: #f0f4f2;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #90a4ae;
            margin-bottom: 22px;
            font-size: 15px;
        }

        .category {
            display: inline-block;
            background: #e8f5f1;
            color: #087f5b;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .product-name {
            font-size: 25px;
            color: #263238;
            margin-bottom: 10px;
        }

        .description {
            color: #607d8b;
            line-height: 1.6;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .price {
            color: #087f5b;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stock {
            font-size: 14px;
            color: #546e7a;
        }

        /* =================================================
           ORDER FORM
           ================================================= */

        .form-section {
            padding: 5px;
        }

        .form-section h2 {
            color: #263238;
            font-size: 22px;
            margin-bottom: 8px;
        }

        .form-subtitle {
            color: #78909c;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #455a64;
            margin-bottom: 8px;
        }

        .quantity-input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cfd8d5;
            border-radius: 7px;
            font-size: 16px;
            outline: none;
        }

        .quantity-input:focus {
            border-color: #087f5b;
            box-shadow: 0 0 0 3px rgba(8, 127, 91, 0.1);
        }

        /* =================================================
           SUMMARY
           ================================================= */

        .summary {
            background: #f5f9f7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            color: #546e7a;
            font-size: 14px;
        }

        .summary-row.total {
            border-top: 1px solid #dce5e1;
            margin-top: 10px;
            padding-top: 15px;
            color: #263238;
            font-size: 19px;
            font-weight: bold;
        }

        .total-value {
            color: #087f5b;
        }

        /* =================================================
           PAYMENT NOTE
           ================================================= */

        .payment-note {
            background: #fff8e1;
            border: 1px solid #ffe0b2;
            color: #795548;
            padding: 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 22px;
        }

        /* =================================================
           BUTTONS
           ================================================= */

        .place-btn {
            width: 100%;
            border: none;
            background: #087f5b;
            color: white;
            padding: 14px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .place-btn:hover {
            background: #05694b;
        }

        .place-btn:disabled {
            background: #b0bec5;
            cursor: not-allowed;
        }

        .after-order {
            text-align: center;
            margin-top: 18px;
        }

        .orders-btn {
            display: inline-block;
            text-decoration: none;
            background: #e8f5f1;
            color: #087f5b;
            padding: 11px 20px;
            border-radius: 7px;
            font-weight: bold;
            font-size: 14px;
        }

        .orders-btn:hover {
            background: #d7eee7;
        }

        /* =================================================
           FOOTER
           ================================================= */

        footer {
            text-align: center;
            padding: 30px 20px;
            color: #78909c;
            font-size: 13px;
        }

        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 800px) {

            header {
                padding: 13px 20px;
            }

            .welcome {
                display: none;
            }

            .container {
                width: 94%;
                margin: 30px auto;
            }

            .order-card {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .product-section {
                border-right: none;
                border-bottom: 1px solid #e8eeee;
                padding-right: 0;
                padding-bottom: 30px;
            }

            .product-image,
            .no-image {
                height: 220px;
            }
        }

        @media (max-width: 500px) {

            .brand-logo {
                width: 125px;
            }

            .back-btn {
                padding: 8px 12px;
                font-size: 12px;
            }

            .order-card {
                padding: 20px;
            }

            .product-name {
                font-size: 21px;
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

        <a href="browse_products.php" class="back-btn">
            ← Browse Products
        </a>

    </div>

</header>


<!-- =====================================================
     MAIN CONTENT
     ===================================================== -->

<main class="container">

    <div class="page-title">

        <h1>Place Your Order</h1>

        <p>
            Review the product and select the quantity you want to order.
        </p>

    </div>


    <!-- ERROR MESSAGE -->

    <?php if (!empty($error)): ?>

        <div class="alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- SUCCESS MESSAGE -->

    <?php if (!empty($success)): ?>

        <div class="alert success">

            <?php echo htmlspecialchars($success); ?>

            <?php if ($order_id > 0): ?>

                <strong>
                    Order #<?php echo $order_id; ?>
                </strong>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <div class="order-card">


        <!-- =================================================
             PRODUCT INFORMATION
             ================================================= -->

        <section class="product-section">

            <?php if (!empty($product["image"])): ?>

                <img
                    src="../assets/images/products/<?php echo htmlspecialchars($product["image"]); ?>"
                    alt="<?php echo htmlspecialchars($product["name"]); ?>"
                    class="product-image"
                    onerror="this.style.display='none'; document.getElementById('imageFallback').style.display='flex';"
                >

                <div
                    id="imageFallback"
                    class="no-image"
                    style="display:none;"
                >
                    Product image unavailable
                </div>

            <?php else: ?>

                <div class="no-image">
                    No Product Image
                </div>

            <?php endif; ?>


            <span class="category">

                <?php echo htmlspecialchars($product["category"]); ?>

            </span>


            <h2 class="product-name">

                <?php echo htmlspecialchars($product["name"]); ?>

            </h2>


            <?php if (!empty($product["description"])): ?>

                <p class="description">

                    <?php echo nl2br(htmlspecialchars($product["description"])); ?>

                </p>

            <?php endif; ?>


            <div class="price">

                KSh
                <?php echo number_format($display_price, 2); ?>

            </div>


            <div class="stock">

                Available stock:
                <strong>
                    <?php echo $available_stock; ?>
                </strong>

            </div>

        </section>


        <!-- =================================================
             ORDER FORM
             ================================================= -->

        <section class="form-section">

            <h2>Order Details</h2>

            <p class="form-subtitle">
                Enter the number of items you would like to order.
            </p>


            <?php if ($available_stock > 0 && empty($success)): ?>

                <form method="POST" action="place_order.php">

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?php echo (int) $product["id"]; ?>"
                    >


                    <div class="form-group">

                        <label for="quantity">
                            Quantity
                        </label>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            class="quantity-input"
                            min="1"
                            max="<?php echo $available_stock; ?>"
                            value="1"
                            required
                        >

                    </div>


                    <!-- ORDER SUMMARY -->

                    <div class="summary">

                        <div class="summary-row">

                            <span>
                                Unit Price
                            </span>

                            <strong>
                                KSh
                                <?php echo number_format($display_price, 2); ?>
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Quantity
                            </span>

                            <strong id="summaryQuantity">
                                1
                            </strong>

                        </div>


                        <div class="summary-row total">

                            <span>
                                Total
                            </span>

                            <span class="total-value">
                                KSh
                                <span id="totalAmount">
                                    <?php echo number_format($display_price, 2); ?>
                                </span>
                            </span>

                        </div>

                    </div>


                    <div class="payment-note">

                        <strong>Payment:</strong>
                        Your order will initially be marked as
                        <strong>Pending</strong>.
                        You can proceed to the payment section after
                        placing your order.

                    </div>


                    <button
                        type="submit"
                        class="place-btn"
                        id="placeOrderButton"
                    >
                        Place Order
                    </button>

                </form>


            <?php elseif (!empty($success)): ?>

                <div class="after-order">

                    <a
                        href="my_orders.php"
                        class="orders-btn"
                    >
                        View My Orders
                    </a>

                </div>


            <?php else: ?>

                <div class="alert error">
                    This product is currently out of stock.
                </div>

            <?php endif; ?>

        </section>

    </div>

</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer>

    © <?php echo date("Y"); ?>
    PulseMed Essential.
    All Rights Reserved.

</footer>


<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>

    const quantityInput =
        document.getElementById("quantity");

    const summaryQuantity =
        document.getElementById("summaryQuantity");

    const totalAmount =
        document.getElementById("totalAmount");

    const placeOrderButton =
        document.getElementById("placeOrderButton");


    const unitPrice =
        <?php echo json_encode($display_price); ?>;


    if (quantityInput) {

        quantityInput.addEventListener("input", function () {

            let quantity =
                parseInt(this.value) || 1;

            const maxQuantity =
                parseInt(this.max);

            if (quantity < 1) {
                quantity = 1;
            }

            if (quantity > maxQuantity) {
                quantity = maxQuantity;
            }

            this.value = quantity;

            const total =
                unitPrice * quantity;

            summaryQuantity.textContent =
                quantity;

            totalAmount.textContent =
                total.toLocaleString(
                    "en-KE",
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );

        });

    }


    if (placeOrderButton) {

        placeOrderButton.addEventListener("click", function () {

            if (quantityInput && quantityInput.value > 0) {

                this.innerHTML =
                    "Placing Order...";

            }

        });

    }

</script>

</body>
</html>