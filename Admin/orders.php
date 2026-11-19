<?php
session_start();
require_once "../database.php";

/* =========================================================
   ADMIN ACCESS
   ========================================================= */
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit();
}

if (empty($_SESSION["orders_csrf_token"])) {
    $_SESSION["orders_csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["orders_csrf_token"];
$admin_name = $_SESSION["user_name"] ?? "Administrator";

/* =========================================================
   UPDATE ORDER STATUS / DELETE ORDER (POST + CSRF)
   ========================================================= */
$action_message = "";
$action_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted_token = $_POST["csrf_token"] ?? "";
    if (!is_string($submitted_token) || !hash_equals($csrf_token, $submitted_token)) {
        $action_error = "Security check failed. Please refresh and try again.";
    } else {
        $action = $_POST["action"] ?? "";
        $order_id = filter_var($_POST["order_id"] ?? null, FILTER_VALIDATE_INT);

        if ($order_id && $order_id > 0 && $action === "update_status") {
            $status = trim((string)($_POST["status"] ?? ""));
            $allowed_statuses = ["Pending", "Processing", "Completed", "Cancelled"];
            if (in_array($status, $allowed_statuses, true)) {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $status, $order_id);
                    if ($stmt->execute() && $stmt->affected_rows >= 0) {
                        $action_message = "Order status updated successfully.";
                    } else {
                        $action_error = "Unable to update this order.";
                    }
                    $stmt->close();
                } else {
                    $action_error = "Unable to prepare the order update.";
                }
            } else {
                $action_error = "Please select a valid order status.";
            }
        } elseif ($order_id && $order_id > 0 && $action === "delete_order") {
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $order_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $action_message = "Order deleted successfully.";
                } else {
                    $action_error = "Order not found or could not be deleted.";
                }
                $stmt->close();
            } else {
                $action_error = "Unable to prepare the order deletion.";
            }
        } else {
            $action_error = "Invalid order action.";
        }
    }
}

/* =========================================================
   ORDER STATISTICS
   ========================================================= */

// Total orders
$total_orders = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders"
);

if ($result) {
    $row = $result->fetch_assoc();
    $total_orders = intval($row["total"]);
}


// Pending orders
$pending_orders = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $pending_orders = intval($row["total"]);
}


// Processing orders
$processing_orders = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Processing'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $processing_orders = intval($row["total"]);
}


// Completed orders
$completed_orders = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Completed'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $completed_orders = intval($row["total"]);
}


/* =========================================================
   FETCH ORDERS
   ========================================================= */

$orders = [];

$query = "
    SELECT
        orders.id,
        orders.user_id,
        orders.total_amount,
        orders.status,
        orders.order_date,
        users.name AS customer_name,
        users.email AS customer_email
    FROM orders
    LEFT JOIN users
        ON orders.user_id = users.id
    ORDER BY orders.id DESC
";

$result = $conn->query($query);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Orders - PulseMed Admin</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f8f7;
            color: #263238;
        }


        /* =================================================
           SIDEBAR
           ================================================= */

        .sidebar {

            position: fixed;

            left: 0;
            top: 0;

            width: 250px;
            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #075e54,
                    #0a7668
                );

            color: white;

            padding: 25px 18px;

            z-index: 1000;

            overflow-y: auto;
        }


        .logo-section {

            text-align: center;

            padding-bottom: 25px;

            margin-bottom: 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,0.15);
        }


        .logo {

            width: 70px;
            height: 70px;

            object-fit: contain;

            background: white;

            border-radius: 14px;

            padding: 7px;

            margin-bottom: 10px;
        }


        .logo-section h2 {

            font-size: 20px;

            margin-bottom: 4px;
        }


        .logo-section p {

            font-size: 12px;

            opacity: 0.8;
        }


        .menu-title {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            color:
                rgba(255,255,255,0.65);

            margin:
                20px 12px 10px;
        }


        .nav-link {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 13px 14px;

            margin-bottom: 5px;

            color: white;

            text-decoration: none;

            border-radius: 9px;

            font-size: 14px;

            transition: 0.2s;
        }


        .nav-link:hover {

            background:
                rgba(255,255,255,0.12);
        }


        .nav-link.active {

            background: white;

            color: #075e54;

            font-weight: bold;
        }


        .nav-icon {

            width: 22px;

            text-align: center;

            font-size: 17px;
        }


        /* =================================================
           MAIN CONTENT
           ================================================= */

        .main {

            margin-left: 250px;

            min-height: 100vh;

            padding: 30px;
        }


        /* =================================================
           HEADER
           ================================================= */

        .top-header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 28px;
        }


        .page-title h1 {

            font-size: 28px;

            color: #164e46;

            margin-bottom: 6px;
        }


        .page-title p {

            color: #718096;

            font-size: 14px;
        }


        .admin-badge {

            background: white;

            padding: 10px 16px;

            border-radius: 10px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,0.06);

            color: #075e54;

            font-weight: bold;

            font-size: 14px;
        }


        /* =================================================
           ALERTS
           ================================================= */

        .alert {

            padding: 13px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        .alert-success {

            background: #e7f7ee;

            color: #17663c;

            border:
                1px solid #b9e6ca;
        }


        .alert-error {

            background: #fdeaea;

            color: #a52828;

            border:
                1px solid #f2bcbc;
        }


        /* =================================================
           STATISTICS
           ================================================= */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 25px;
        }


        .stat-card {

            background: white;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            border-left:
                4px solid #0a7668;
        }


        .stat-card.pending {
            border-left-color: #d89b18;
        }


        .stat-card.processing {
            border-left-color: #3686c9;
        }


        .stat-card.completed {
            border-left-color: #269653;
        }



        .stat-label {

            color: #718096;

            font-size: 13px;

            margin-bottom: 9px;
        }


        .stat-number {

            font-size: 25px;

            font-weight: bold;

            color: #243b3a;
        }


        /* =================================================
           CONTENT CARD
           ================================================= */

        .content-card {

            background: white;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            overflow: hidden;
        }


        .card-header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            padding: 20px 22px;

            border-bottom:
                1px solid #edf1f1;
        }


        .card-header h2 {

            font-size: 19px;

            color: #164e46;
        }


        .search-box {

            width: 270px;

            padding: 10px 13px;

            border:
                1px solid #d9e2e1;

            border-radius: 8px;

            outline: none;

            font-size: 13px;
        }


        .search-box:focus {

            border-color: #0a7668;

            box-shadow:
                0 0 0 3px
                rgba(10,118,104,0.08);
        }


        /* =================================================
           TABLE
           ================================================= */

        .table-container {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 900px;
        }


        th {

            background: #f7faf9;

            padding: 14px 15px;

            text-align: left;

            font-size: 12px;

            color: #667a78;

            text-transform:
                uppercase;

            letter-spacing: 0.4px;
        }


        td {

            padding: 15px;

            border-top:
                1px solid #edf1f1;

            font-size: 13px;

            vertical-align: middle;
        }


        tr:hover td {

            background: #fbfdfc;
        }


        .order-id {

            font-weight: bold;

            color: #075e54;
        }


        .customer-name {

            font-weight: bold;

            color: #263c39;

            margin-bottom: 3px;
        }


        .customer-email {

            color: #8a9897;

            font-size: 11px;
        }


        .amount {

            font-weight: bold;

            color: #164e46;
        }


        /* =================================================
           STATUS
           ================================================= */

        .status {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;
        }


        .status-pending {

            background: #fff5d8;

            color: #966d00;
        }


        .status-processing {

            background: #e3f0ff;

            color: #23639a;
        }


        .status-completed {

            background: #e4f7eb;

            color: #237543;
        }


        .status-cancelled {

            background: #fde8e8;

            color: #a32e2e;
        }


        /* =================================================
           STATUS FORM
           ================================================= */

        .status-form {

            display: flex;

            gap: 6px;

            align-items: center;
        }


        .status-select {

            border:
                1px solid #d9e2e1;

            border-radius: 6px;

            padding: 7px;

            font-size: 11px;

            background: white;

            outline: none;
        }


        .update-btn {

            border: none;

            background: #075e54;

            color: white;

            padding: 7px 10px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 11px;
        }


        .update-btn:hover {

            background: #064d45;
        }


        /* =================================================
           DELETE BUTTON
           ================================================= */

        .delete-btn {

            display: inline-block;

            text-decoration: none;

            background: #fbe8e8;

            color: #b52d2d;

            padding: 7px 10px;

            border-radius: 6px;

            font-size: 11px;

            margin-left: 4px;
        }


        .delete-btn:hover {

            background: #f7d4d4;
        }


        /* =================================================
           EMPTY STATE
           ================================================= */

        .empty-state {

            text-align: center;

            padding: 60px 20px;

            color: #83918f;
        }


        .empty-icon {

            font-size: 42px;

            margin-bottom: 12px;
        }


        .empty-state h3 {

            color: #536563;

            margin-bottom: 6px;
        }


        /* =================================================
           FOOTER
           ================================================= */

        .footer {

            text-align: center;

            padding: 25px 0 10px;

            color: #8a9897;

            font-size: 12px;
        }


        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 1200px) {

            .stats-grid {

                grid-template-columns:
                    repeat(3, 1fr);
            }
        }


        @media (max-width: 850px) {

            .sidebar {

                width: 210px;
            }

            .main {

                margin-left: 210px;

                padding: 20px;
            }

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .top-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }
        }


        @media (max-width: 600px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;
            }

            .main {

                margin-left: 0;

                padding: 15px;
            }

            .stats-grid {

                grid-template-columns: 1fr;
            }

            .search-box {

                width: 100%;
            }

            .card-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 12px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="sidebar">

    <div class="logo-section">

        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Logo"
            class="logo"
        >

        <h2>PulseMed</h2>

        <p>Admin Panel</p>

    </div>


    <div class="menu-title">
        Main Menu
    </div>


    <a href="dashboard.php" class="nav-link">

        <span class="nav-icon">▣</span>

        Dashboard

    </a>


    <a href="products.php" class="nav-link">

        <span class="nav-icon">▦</span>

        Products

    </a>


    <a href="orders.php" class="nav-link active">

        <span class="nav-icon">🛒</span>

        Orders

    </a>


    <a href="users.php" class="nav-link">

        <span class="nav-icon">♙</span>

        Users

    </a>

    <a href="reports.php" class="nav-link">

        <span class="nav-icon">▤</span>

        Reports

    </a>


    <div class="menu-title">
        Account
    </div>


    <a href="profile.php" class="nav-link">

        <span class="nav-icon">⚙</span>

        Profile

    </a>


    <a
        href="logout.php"
        class="nav-link"
        onclick="return confirm('Are you sure you want to logout?');"
    >

        <span class="nav-icon">↪</span>

        Logout

    </a>

</aside>



<!-- =====================================================
     MAIN
     ===================================================== -->

<main class="main">


    <div class="top-header">

        <div class="page-title">

            <h1>Orders</h1>

            <p>
                Manage and monitor customer orders.
            </p>

        </div>


        <div class="admin-badge">

            🛡 <?php echo htmlspecialchars($admin_name); ?>

        </div>

    </div>



    <!-- ALERTS -->
    <?php if ($action_message !== ""): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($action_message); ?></div>
    <?php endif; ?>
    <?php if ($action_error !== ""): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($action_error); ?></div>
    <?php endif; ?>

    <!-- =================================================
         STATISTICS
         ================================================= -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-label">
                Total Orders
            </div>

            <div class="stat-number">
                <?php echo $total_orders; ?>
            </div>

        </div>


        <div class="stat-card pending">

            <div class="stat-label">
                Pending
            </div>

            <div class="stat-number">
                <?php echo $pending_orders; ?>
            </div>

        </div>


        <div class="stat-card processing">

            <div class="stat-label">
                Processing
            </div>

            <div class="stat-number">
                <?php echo $processing_orders; ?>
            </div>

        </div>


        <div class="stat-card completed">

            <div class="stat-label">
                Completed
            </div>

            <div class="stat-number">
                <?php echo $completed_orders; ?>
            </div>

        </div>


    </div>



    <!-- =================================================
         ORDERS TABLE
         ================================================= -->

    <section class="content-card">


        <div class="card-header">

            <h2>Customer Orders</h2>


            <input
                type="text"
                id="searchOrders"
                class="search-box"
                placeholder="Search orders..."
                onkeyup="searchOrders()"
            >

        </div>



        <div class="table-container">


            <?php if (count($orders) > 0): ?>

                <table id="ordersTable">

                    <thead>

                        <tr>

                            <th>
                                Order ID
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Order Date
                            </th>

                            <th>
                                Update Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($orders as $order): ?>

                        <?php

                        $status = $order["status"] ?? "Pending";

                        $status_class =
                            strtolower($status);

                        $customer_name =
                            !empty($order["customer_name"])
                            ? $order["customer_name"]
                            : "Unknown Customer";

                        $customer_email =
                            !empty($order["customer_email"])
                            ? $order["customer_email"]
                            : "No email";

                        ?>

                        <tr>


                            <td>

                                <span class="order-id">

                                    #<?php
                                    echo htmlspecialchars(
                                        $order["id"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <div class="customer-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $customer_name
                                    );
                                    ?>

                                </div>

                                <div class="customer-email">

                                    <?php
                                    echo htmlspecialchars(
                                        $customer_email
                                    );
                                    ?>

                                </div>

                            </td>


                            <td>

                                <span class="amount">

                                    KSh
                                    <?php
                                    echo number_format(
                                        $order["total_amount"],
                                        2
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="status status-<?php
                                    echo htmlspecialchars(
                                        $status_class
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $status
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php

                                echo date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $order["order_date"]
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <form
                                    method="POST"
                                    class="status-form"
                                >
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?php
                                        echo intval(
                                            $order["id"]
                                        );
                                        ?>"
                                    >


                                    <select
                                        name="status"
                                        class="status-select"
                                    >

                                        <option
                                            value="Pending"
                                            <?php
                                            echo $status === "Pending"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Pending
                                        </option>


                                        <option
                                            value="Processing"
                                            <?php
                                            echo $status === "Processing"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Processing
                                        </option>


                                        <option
                                            value="Completed"
                                            <?php
                                            echo $status === "Completed"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Completed
                                        </option>


                                        <option
                                            value="Cancelled"
                                            <?php
                                            echo $status === "Cancelled"
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Cancelled
                                        </option>

                                    </select>


                                    <button
                                        type="submit"
                                        class="update-btn"
                                    >
                                        Update
                                    </button>

                                </form>

                            </td>


                            <td>

                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this order? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete_order">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order["id"]; ?>">
                                    <button type="submit" class="delete-btn" style="border:0;cursor:pointer;">Delete</button>
                                </form>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>


            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        🛒
                    </div>

                    <h3>
                        No Orders Found
                    </h3>

                    <p>
                        Customer orders will appear here
                        when they are placed.
                    </p>

                </div>

            <?php endif; ?>


        </div>

    </section>



    <div class="footer">

        © <?php echo date("Y"); ?>
        PulseMed. All Rights Reserved.

    </div>


</main>



<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>

function searchOrders() {

    const input =
        document.getElementById("searchOrders");

    const filter =
        input.value.toLowerCase();

    const table =
        document.getElementById("ordersTable");

    if (!table) {
        return;
    }

    const rows =
        table
        .getElementsByTagName("tr");


    for (let i = 1; i < rows.length; i++) {

        const row =
            rows[i];

        const text =
            row.textContent.toLowerCase();


        if (text.indexOf(filter) > -1) {

            row.style.display = "";

        } else {

            row.style.display = "none";

        }

    }

}

</script>


</body>

</html>