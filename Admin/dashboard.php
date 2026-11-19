<?php
session_start();
require_once "../database.php";

/* ADMIN ACCESS PROTECTION */
if (!isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION["user_name"] ?? "Administrator";

/* DASHBOARD STATISTICS */
$customer_query = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'");
$total_customers = $customer_query ? (int)$customer_query->fetch_assoc()["total"] : 0;

$product_query = $conn->query("SELECT COUNT(*) AS total FROM products");
$total_products = $product_query ? (int)$product_query->fetch_assoc()["total"] : 0;

$order_query = $conn->query("SELECT COUNT(*) AS total FROM orders");
$total_orders = $order_query ? (int)$order_query->fetch_assoc()["total"] : 0;

$sales_query = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status != 'Cancelled'");
$total_sales = $sales_query ? (float)$sales_query->fetch_assoc()["total"] : 0;

/* RECENT ORDERS */
$recent_orders = $conn->query(
    "SELECT orders.id, orders.total_amount, orders.status, orders.order_date, users.name
     FROM orders
     LEFT JOIN users ON orders.user_id = users.id
     ORDER BY orders.id DESC
     LIMIT 6"
);

/* LOW STOCK PRODUCTS */
$low_stock = $conn->query(
    "SELECT id, name, stock FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | PulseMed Essentials</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary:#087f8c;--primary-dark:#066873;--green:#24a77b;--dark:#123f46;--text:#536b71;--muted:#87999d;--background:#f4f9f8;--white:#fff;--border:#e3edeb;--danger:#dc3545}
body{font-family:"Inter","Segoe UI",Arial,sans-serif;background:var(--background);color:var(--dark)}
a{text-decoration:none}
.dashboard{min-height:100vh;display:flex}
.sidebar{width:250px;min-height:100vh;position:fixed;left:0;top:0;padding:25px 17px;background:linear-gradient(180deg,#087f8c,#066c76 55%,#145d62);color:white;z-index:100}
.brand{display:flex;align-items:center;gap:10px;color:white;padding:0 10px 30px;border-bottom:1px solid rgba(255,255,255,.13);margin-bottom:25px}
.brand-logo{width:42px;height:42px;object-fit:contain;border-radius:10px;background:white;padding:4px}
.brand-text{font-size:1.05rem;font-weight:800}.brand-text span{color:#baf3df}
.nav-title{padding:0 12px;margin-bottom:10px;color:rgba(255,255,255,.5);font-size:.67rem;text-transform:uppercase;letter-spacing:1.3px;font-weight:700}
.nav-menu{display:flex;flex-direction:column;gap:5px}
.nav-link{display:flex;align-items:center;gap:13px;padding:12px 13px;border-radius:10px;color:rgba(255,255,255,.78);font-size:.82rem;font-weight:500;transition:.2s}
.nav-link:hover,.nav-link.active{color:white;background:rgba(255,255,255,.14)}
.nav-icon{width:27px;height:27px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:7px;background:rgba(255,255,255,.1);font-size:.78rem}
.sidebar-bottom{position:absolute;left:17px;right:17px;bottom:22px}
.logout{display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;color:rgba(255,255,255,.8);font-size:.82rem;background:rgba(0,0,0,.1)}
.logout:hover{background:rgba(255,255,255,.12);color:white}
.main{width:calc(100% - 250px);margin-left:250px;min-height:100vh}
.topbar{min-height:76px;padding:12px 35px;display:flex;align-items:center;justify-content:space-between;gap:15px;background:white;border-bottom:1px solid var(--border)}
.page-title h1{font-size:1.25rem;font-weight:800}.page-title p{margin-top:3px;color:var(--muted);font-size:.72rem}
.admin-profile{display:flex;align-items:center;gap:11px}
.admin-avatar{width:39px;height:39px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--green));color:white;font-size:.8rem;font-weight:700}
.admin-info strong{display:block;font-size:.77rem}.admin-info span{color:var(--muted);font-size:.66rem}
.content{padding:32px 35px}.welcome{margin-bottom:28px}.welcome h2{font-size:1.65rem;font-weight:800;margin-bottom:6px}.welcome p{color:var(--muted);font-size:.82rem}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:28px}
.stat-card{padding:21px;background:white;border:1px solid var(--border);border-radius:15px;display:flex;align-items:center;justify-content:space-between;gap:10px;transition:.25s;min-width:0}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(20,70,76,.07)}
.stat-label{color:var(--muted);font-size:.7rem;margin-bottom:6px}.stat-number{font-size:1.45rem;font-weight:800;overflow-wrap:anywhere}
.stat-icon{width:45px;height:45px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:13px;background:#edf8f6;color:var(--primary);font-size:.9rem;font-weight:800}
.section-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}.section-heading h3{font-size:.9rem;font-weight:800}.section-heading span{color:var(--muted);font-size:.68rem}
.dashboard-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:20px;margin-bottom:20px}
.panel{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden;min-width:0}
.panel-header{padding:19px 21px;display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid var(--border)}
.panel-header h3{font-size:.9rem;font-weight:800}.view-all{color:var(--primary);font-size:.7rem;font-weight:700;white-space:nowrap}
.table-wrapper{overflow-x:auto}table{width:100%;border-collapse:collapse}
th{padding:13px 16px;text-align:left;color:#8a999c;font-size:.65rem;text-transform:uppercase;letter-spacing:.6px;font-weight:700;background:#fbfdfd;white-space:nowrap}
td{padding:14px 16px;border-top:1px solid #edf2f1;color:#52696e;font-size:.73rem}td strong{color:var(--dark)}
.status{display:inline-block;padding:5px 9px;border-radius:20px;font-size:.62rem;font-weight:700;white-space:nowrap}
.status-pending{color:#996c00;background:#fff5d9}.status-processing{color:#087f8c;background:#e5f7f7}
.status-completed,.status-delivered{color:#147a4f;background:#e4f7ee}.status-cancelled{color:#b52d38;background:#fde9eb}
.stock-list{padding:8px 20px 15px}.stock-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 0;border-bottom:1px solid #edf2f1}
.stock-item:last-child{border-bottom:none}.stock-name{font-size:.75rem;font-weight:600;overflow-wrap:anywhere}.stock-id{color:var(--muted);font-size:.63rem;margin-top:3px}
.stock-number{min-width:38px;padding:5px 7px;text-align:center;border-radius:7px;color:#b52d38;background:#fde9eb;font-size:.65rem;font-weight:800}
.empty{padding:35px 20px;text-align:center;color:var(--muted);font-size:.75rem}
.quick-actions{margin-top:20px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}
.quick-action{padding:17px;background:white;border:1px solid var(--border);border-radius:13px;color:var(--dark);transition:.2s}
.quick-action:hover{border-color:#b9d8d5;transform:translateY(-2px)}
.quick-action-icon{width:35px;height:35px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;border-radius:9px;background:#edf8f6;color:var(--primary);font-weight:800}
.quick-action strong{display:block;font-size:.73rem}.quick-action span{display:block;margin-top:3px;color:var(--muted);font-size:.63rem}
@media(max-width:1200px){.quick-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:1100px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.dashboard-grid{grid-template-columns:1fr}}
@media(max-width:800px){.sidebar{width:70px;padding:20px 10px}.brand{justify-content:center;padding:0 0 20px}.brand-text,.nav-title,.nav-link span:not(.nav-icon),.logout span{display:none}.brand-logo{width:40px;height:40px}.nav-link{justify-content:center;padding:12px}.sidebar-bottom{left:10px;right:10px}.logout{justify-content:center}.main{width:calc(100% - 70px);margin-left:70px}.topbar{padding:12px 20px}.content{padding:25px 20px}}
@media(max-width:600px){.stats{grid-template-columns:1fr}.admin-info{display:none}.welcome h2{font-size:1.4rem}.quick-actions{grid-template-columns:1fr 1fr}.stat-number{font-size:1.4rem}}
@media(max-width:420px){.quick-actions{grid-template-columns:1fr}.topbar{min-height:68px}.content{padding:22px 14px}}
</style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <a href="dashboard.php" class="brand">
            <img src="../assets/images/pulsemed-logo.png" alt="PulseMed Essentials Logo" class="brand-logo">
            <div class="brand-text">PulseMed <span>Essential</span></div>
        </a>
        <div class="nav-title">Main Menu</div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-link active"><span class="nav-icon">⌂</span><span>Dashboard</span></a>
            <a href="products.php" class="nav-link"><span class="nav-icon">+</span><span>Products</span></a>
            <a href="orders.php" class="nav-link"><span class="nav-icon">≡</span><span>Orders</span></a>
            <a href="users.php" class="nav-link"><span class="nav-icon">●</span><span>Customers</span></a>
            <a href="reports.php" class="nav-link"><span class="nav-icon">▥</span><span>Reports</span></a>
            <!-- Contact Messages added to the sidebar -->
            <a href="contact_messages.php" class="nav-link"><span class="nav-icon">✉</span><span>Contact Messages</span></a>
            <a href="profile.php" class="nav-link"><span class="nav-icon">◯</span><span>Profile</span></a>
        </nav>
        <div class="sidebar-bottom">
            <a href="../logout.php" class="logout"><span class="nav-icon">←</span><span>Logout</span></a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="page-title"><h1>Dashboard</h1><p>PulseMed Essentials administration</p></div>
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo htmlspecialchars(strtoupper(substr($admin_name, 0, 1)), ENT_QUOTES, "UTF-8"); ?></div>
                <div class="admin-info">
                    <strong><?php echo htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8"); ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <section class="content">
            <div class="welcome">
                <h2>Welcome back, <?php echo htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8"); ?> 👋</h2>
                <p>Here's what's happening with your medical supplies store today.</p>
            </div>

            <div class="stats">
                <div class="stat-card"><div><div class="stat-label">Total Customers</div><div class="stat-number"><?php echo number_format($total_customers); ?></div></div><div class="stat-icon">●</div></div>
                <div class="stat-card"><div><div class="stat-label">Total Products</div><div class="stat-number"><?php echo number_format($total_products); ?></div></div><div class="stat-icon">+</div></div>
                <div class="stat-card"><div><div class="stat-label">Total Orders</div><div class="stat-number"><?php echo number_format($total_orders); ?></div></div><div class="stat-icon">≡</div></div>
                <div class="stat-card"><div><div class="stat-label">Total Sales</div><div class="stat-number" style="font-size:1.1rem;">KSh <?php echo number_format($total_sales, 2); ?></div></div><div class="stat-icon">KSh</div></div>
            </div>

            <div class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header"><h3>Recent Orders</h3><a href="orders.php" class="view-all">View All →</a></div>
                    <div class="table-wrapper">
                        <table>
                            <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                    <?php
                                    $status = $order["status"] ?? "Pending";
                                    $status_class = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $status));
                                    $order_date = !empty($order["order_date"]) ? date("d M Y", strtotime($order["order_date"])) : "—";
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$order["id"]; ?></strong></td>
                                        <td><?php echo htmlspecialchars($order["name"] ?? "Guest Customer", ENT_QUOTES, "UTF-8"); ?></td>
                                        <td>KSh <?php echo number_format((float)$order["total_amount"], 2); ?></td>
                                        <td><span class="status status-<?php echo htmlspecialchars($status_class, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, "UTF-8"); ?></span></td>
                                        <td><?php echo htmlspecialchars($order_date, ENT_QUOTES, "UTF-8"); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5"><div class="empty">No orders have been placed yet.</div></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h3>Low Stock</h3><a href="products.php" class="view-all">Manage →</a></div>
                    <div class="stock-list">
                    <?php if ($low_stock && $low_stock->num_rows > 0): ?>
                        <?php while ($product = $low_stock->fetch_assoc()): ?>
                            <div class="stock-item">
                                <div>
                                    <div class="stock-name"><?php echo htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8"); ?></div>
                                    <div class="stock-id">Product #<?php echo (int)$product["id"]; ?></div>
                                </div>
                                <div class="stock-number"><?php echo (int)$product["stock"]; ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty">✓ All products have sufficient stock.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section-heading"><h3>Quick Actions</h3><span>Manage your store</span></div>
            <div class="quick-actions">
                <a href="products.php" class="quick-action"><div class="quick-action-icon">+</div><strong>Manage Products</strong><span>Add and update medical supplies</span></a>
                <a href="orders.php" class="quick-action"><div class="quick-action-icon">≡</div><strong>Manage Orders</strong><span>View and update customer orders</span></a>
                <a href="users.php" class="quick-action"><div class="quick-action-icon">●</div><strong>Customers</strong><span>View customer records</span></a>
                <a href="reports.php" class="quick-action"><div class="quick-action-icon">▥</div><strong>View Reports</strong><span>Review sales and store reports</span></a>
            </div>
        </section>
    </main>
</div>
</body>
</html>