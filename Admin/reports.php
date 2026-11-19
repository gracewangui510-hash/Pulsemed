<?php
session_start();
require_once __DIR__ . "/../database.php";

mysqli_report(MYSQLI_REPORT_OFF);

/* ADMIN ACCESS */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}
if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../customer/dashboard.php");
    exit();
}
$admin_name = $_SESSION["user_name"] ?? "Administrator";

function reportCount(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (int)($row["total"] ?? 0);
}
function reportMoney(mysqli $conn, string $sql): float {
    $result = $conn->query($sql);
    if (!$result) return 0.0;
    $row = $result->fetch_assoc();
    return (float)($row["total"] ?? 0);
}
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }

/* ORDER REPORTS - no payments table is used */
$total_orders = reportCount($conn, "SELECT COUNT(*) AS total FROM orders");
$completed_orders = reportCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status='Completed'");
$pending_orders = reportCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status='Pending'");
$processing_orders = reportCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status='Processing'");
$cancelled_orders = reportCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status='Cancelled'");
$total_sales = reportMoney($conn, "SELECT COALESCE(SUM(total_amount),0) AS total FROM orders WHERE status='Completed'");

/* PRODUCT / INVENTORY REPORTS */
$total_products = reportCount($conn, "SELECT COUNT(*) AS total FROM products");
$total_stock = reportCount($conn, "SELECT COALESCE(SUM(stock),0) AS total FROM products");
$low_stock = reportCount($conn, "SELECT COUNT(*) AS total FROM products WHERE stock > 0 AND stock <= 10");
$out_of_stock = reportCount($conn, "SELECT COUNT(*) AS total FROM products WHERE stock <= 0");

/* USER REPORTS */
$total_users = reportCount($conn, "SELECT COUNT(*) AS total FROM users");
$total_customers = reportCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role='customer'");
$total_admins = reportCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role='admin'");

/* Recent orders: LEFT JOIN keeps orders visible even when they were placed without an account */
$recent_orders = $conn->query("SELECT o.id, o.total_amount, o.status, o.order_date, u.name, u.email
    FROM orders o LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC LIMIT 10");

$product_stock = $conn->query("SELECT id, name,
    CASE
        WHEN LOWER(TRIM(category)) = 'orthopaedic' THEN 'Orthopaedic'
        WHEN LOWER(TRIM(category)) = 'laboratory' THEN 'Laboratory'
        WHEN LOWER(TRIM(category)) = 'surgical' THEN 'Surgical'
        ELSE 'Others'
    END AS category, price, stock
    FROM products ORDER BY stock ASC, name ASC");

/* Keep the report limited to the four approved categories.
   Existing products with old/blank categories are grouped under Others. */
$category_report = $conn->query("SELECT category, COUNT(*) AS product_count,
    COALESCE(SUM(total_stock),0) AS total_stock
    FROM (
        SELECT CASE
            WHEN LOWER(TRIM(category)) = 'orthopaedic' THEN 'Orthopaedic'
            WHEN LOWER(TRIM(category)) = 'laboratory' THEN 'Laboratory'
            WHEN LOWER(TRIM(category)) = 'surgical' THEN 'Surgical'
            ELSE 'Others'
        END AS category, stock AS total_stock
        FROM products
    ) AS categorised_products
    GROUP BY category
    ORDER BY FIELD(category, 'Orthopaedic', 'Laboratory', 'Surgical', 'Others')");

$current_year = (int)date("Y");
$monthly_sales = array_fill(1, 12, 0.0);
$stmt = $conn->prepare("SELECT MONTH(order_date) AS month_number, COALESCE(SUM(total_amount),0) AS total
    FROM orders WHERE status='Completed' AND YEAR(order_date)=? GROUP BY MONTH(order_date)");
if ($stmt) {
    $stmt->bind_param("i", $current_year);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $monthly_sales[(int)$row["month_number"]] = (float)$row["total"];
    }
    $stmt->close();
}
$month_names = [1=>"January",2=>"February",3=>"March",4=>"April",5=>"May",6=>"June",7=>"July",8=>"August",9=>"September",10=>"October",11=>"November",12=>"December"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports | PulseMed Essentials</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#eef8f6;color:#243b40}.sidebar{position:fixed;inset:0 auto 0 0;width:245px;background:#123f46;color:#fff;padding:24px 15px;overflow-y:auto}.brand{text-align:center;margin-bottom:28px}.brand-logo{width:125px;max-width:100%;height:auto;background:#fff;border-radius:10px;padding:6px;margin-bottom:10px}.brand-name{font-weight:700;font-size:19px}.brand-subtitle{font-size:12px;opacity:.8;margin-top:5px}.menu{list-style:none;padding:0;margin:0}.menu li{margin:6px 0}.menu a{display:block;color:#eaf7f5;text-decoration:none;padding:12px 14px;border-radius:8px;font-size:14px}.menu a:hover,.menu a.active{background:#087f8c}.main{margin-left:245px;padding:28px;max-width:1600px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:24px}.topbar h1{margin:0;color:#123f46;font-size:28px}.subtitle{color:#71868a;font-size:14px;margin-top:7px}.admin-info{background:#fff;border-radius:10px;padding:12px 15px;box-shadow:0 3px 12px #123f4610;font-size:13px}.section-title{margin:28px 0 14px;color:#123f46;font-size:20px}.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}.stat-card,.box{background:#fff;border-radius:12px;padding:19px;box-shadow:0 3px 14px #123f4610}.stat-card{border-left:4px solid #087f8c}.stat-label{font-size:13px;color:#6f8589;margin-bottom:10px}.stat-value{font-size:25px;font-weight:700;color:#123f46;overflow-wrap:anywhere}.stat-note{font-size:12px;color:#8a9a9d;margin-top:7px}.box{margin-top:18px;overflow-x:auto}.box-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.box h2{font-size:18px;margin:0;color:#123f46}.btn{background:#087f8c;color:white;border:0;border-radius:7px;padding:10px 15px;cursor:pointer;font-weight:600}.btn:hover{background:#066873}table{width:100%;border-collapse:collapse;min-width:640px;font-size:13px}th,td{text-align:left;padding:12px 11px;border-bottom:1px solid #e8efee}th{background:#f0f8f6;color:#24545a}tbody tr:hover{background:#f8fbfa}.status{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:bold;background:#eaf4f3;color:#24545a}.status.completed{background:#e0f6e9;color:#177245}.status.pending{background:#fff3d7;color:#8a5b00}.status.processing{background:#e5f0ff;color:#2458a6}.status.cancelled{background:#fde8e7;color:#a52c2c}.monthly-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.month-item{background:#f4f9f8;padding:13px;border-radius:8px;display:flex;justify-content:space-between;gap:10px;font-size:13px}.month-item strong{color:#087f8c}.footer{text-align:center;color:#829396;font-size:12px;padding:28px 0 8px}.empty{text-align:center;color:#7d8d90;padding:22px}.print-only{display:none}@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.monthly-list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.sidebar{position:static;width:100%;height:auto}.brand-logo{width:90px}.menu{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.main{margin:0;padding:18px}.topbar{align-items:flex-start;flex-direction:column}.stats-grid{grid-template-columns:1fr 1fr}.monthly-list{grid-template-columns:1fr}}@media(max-width:420px){.stats-grid{grid-template-columns:1fr}.stat-value{font-size:23px}}@media print{.sidebar,.admin-info,.btn{display:none!important}.main{margin:0;padding:0;max-width:none}.box,.stat-card{box-shadow:none;border:1px solid #ddd;break-inside:avoid}.print-only{display:block}.stats-grid{grid-template-columns:repeat(3,1fr)}body{background:white}.box{overflow:visible}table{min-width:0;font-size:10px}th,td{padding:7px}}
</style></head><body>
<aside class="sidebar"><div class="brand"><img src="../assets/images/pulsemed-logo.png" alt="PulseMed Essential Logo" class="brand-logo"><div class="brand-name">PulseMed Essentials</div><div class="brand-subtitle">Medical Supplies Management</div></div>
<ul class="menu"><li><a href="dashboard.php">▣ &nbsp; Dashboard</a></li><li><a href="products.php">▤ &nbsp; Products</a></li><li><a href="orders.php">🛒 &nbsp; Orders</a></li><li><a href="users.php">👥 &nbsp; Users</a></li><li><a href="reports.php" class="active">📊 &nbsp; Reports</a></li><li><a href="profile.php">👤 &nbsp; Profile</a></li><li><a href="logout.php">↪ &nbsp; Logout</a></li></ul></aside>
<main class="main"><div class="topbar"><div><h1>Business Reports</h1><p class="subtitle">Orders, sales, products, inventory and user summaries.</p></div><div class="admin-info">Administrator: <strong><?= h($admin_name) ?></strong></div></div>
<h2 class="section-title">Orders & Sales</h2><div class="stats-grid">
<div class="stat-card"><div class="stat-label">Completed Order Sales</div><div class="stat-value">KSh <?= number_format($total_sales,2) ?></div><div class="stat-note">Completed orders only</div></div>
<div class="stat-card"><div class="stat-label">Total Orders</div><div class="stat-value"><?= number_format($total_orders) ?></div><div class="stat-note">All recorded orders</div></div>
<div class="stat-card"><div class="stat-label">Completed Orders</div><div class="stat-value"><?= number_format($completed_orders) ?></div></div>
<div class="stat-card"><div class="stat-label">Pending Orders</div><div class="stat-value"><?= number_format($pending_orders) ?></div></div>
<div class="stat-card"><div class="stat-label">Processing Orders</div><div class="stat-value"><?= number_format($processing_orders) ?></div></div>
<div class="stat-card"><div class="stat-label">Cancelled Orders</div><div class="stat-value"><?= number_format($cancelled_orders) ?></div></div></div>
<h2 class="section-title">Product & Inventory Summary</h2><div class="stats-grid">
<div class="stat-card"><div class="stat-label">Total Products</div><div class="stat-value"><?= number_format($total_products) ?></div></div>
<div class="stat-card"><div class="stat-label">Units in Stock</div><div class="stat-value"><?= number_format($total_stock) ?></div></div>
<div class="stat-card"><div class="stat-label">Low Stock Products</div><div class="stat-value"><?= number_format($low_stock) ?></div><div class="stat-note">1–10 units remaining</div></div>
<div class="stat-card"><div class="stat-label">Out of Stock</div><div class="stat-value"><?= number_format($out_of_stock) ?></div></div></div>
<h2 class="section-title">User Summary</h2><div class="stats-grid">
<div class="stat-card"><div class="stat-label">All Users</div><div class="stat-value"><?= number_format($total_users) ?></div></div>
<div class="stat-card"><div class="stat-label">Users Accounts</div><div class="stat-value"><?= number_format($total_customers) ?></div></div>
<div class="stat-card"><div class="stat-label">Administrator Accounts</div><div class="stat-value"><?= number_format($total_admins) ?></div></div></div>
<div class="box"><div class="box-header"><h2>Monthly Completed Sales — <?= $current_year ?></h2><button class="btn" onclick="window.print()">Print Reports</button></div><div class="monthly-list"><?php foreach($month_names as $number=>$month): ?><div class="month-item"><span><?= h($month) ?></span><strong>KSh <?= number_format($monthly_sales[$number],2) ?></strong></div><?php endforeach; ?></div></div>
<div class="box"><div class="box-header"><h2>Recent Orders</h2><button class="btn" onclick="window.print()">Print</button></div><table><thead><tr><th>Order ID</th><th>Customer</th><th>Email</th><th>Amount</th><th>Status</th><th>Order Date</th></tr></thead><tbody>
<?php if($recent_orders && $recent_orders->num_rows): while($order=$recent_orders->fetch_assoc()): $status=strtolower(str_replace(' ','-',(string)($order['status']??''))); ?><tr><td>#<?= (int)$order['id'] ?></td><td><?= h($order['name'] ?: 'Guest customer') ?></td><td><?= h($order['email'] ?: '—') ?></td><td>KSh <?= number_format((float)$order['total_amount'],2) ?></td><td><span class="status <?= h($status) ?>"><?= h($order['status'] ?: 'Unknown') ?></span></td><td><?= !empty($order['order_date']) && strtotime($order['order_date']) ? date('d M Y H:i',strtotime($order['order_date'])) : '—' ?></td></tr><?php endwhile; else: ?><tr><td colspan="6" class="empty">No orders are available yet.</td></tr><?php endif; ?></tbody></table></div>
<div class="box"><div class="box-header"><h2>Inventory by Category</h2></div><table><thead><tr><th>Category</th><th>Number of Products</th><th>Total Stock Units</th></tr></thead><tbody><?php if($category_report && $category_report->num_rows): while($category=$category_report->fetch_assoc()): ?><tr><td><?= h($category['category']) ?></td><td><?= number_format((int)$category['product_count']) ?></td><td><?= number_format((int)$category['total_stock']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="empty">No category information available.</td></tr><?php endif; ?></tbody></table></div>
<div class="box"><div class="box-header"><h2>Product Stock Details</h2></div><table><thead><tr><th>Product ID</th><th>Product</th><th>Category</th><th>Unit Price</th><th>Stock</th><th>Availability</th></tr></thead><tbody><?php if($product_stock && $product_stock->num_rows): while($product=$product_stock->fetch_assoc()): ?><tr><td><?= (int)$product['id'] ?></td><td><?= h($product['name']) ?></td><td><?= h($product['category'] ?: 'Uncategorised') ?></td><td>KSh <?= number_format((float)$product['price'],2) ?></td><td><?= number_format((int)$product['stock']) ?></td><td><?php if((int)$product['stock']<=0): ?><span class="status cancelled">Out of stock</span><?php elseif((int)$product['stock']<=10): ?><span class="status pending">Low stock</span><?php else: ?><span class="status completed">In stock</span><?php endif; ?></td></tr><?php endwhile; else: ?><tr><td colspan="6" class="empty">No products are available.</td></tr><?php endif; ?></tbody></table></div>
<div class="footer">© <?= date('Y') ?> PulseMed Essentials · Business and Inventory Reports</div></main></body></html>
