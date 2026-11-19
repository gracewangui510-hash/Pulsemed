<?php
session_start();

require_once "../database.php";

/* =====================================================
   ADMIN ACCESS PROTECTION
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

/* =====================================================
   SECURITY AND HELPER FUNCTIONS
===================================================== */

if (empty($_SESSION["contact_messages_csrf"])) {
    $_SESSION["contact_messages_csrf"] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$notice = "";
$error = "";

/* =====================================================
   PROCESS STATUS UPDATES AND DELETIONS
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["contact_messages_csrf"], $token)
    ) {
        $error = "Security validation failed. Refresh the page and try again.";

    } else {

        $action = $_POST["action"] ?? "";
        $message_id = filter_var(
            $_POST["message_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$message_id || $message_id < 1) {
            $error = "Invalid message selected.";

        } elseif ($action === "update_status") {

            $status = $_POST["status"] ?? "";

            $allowed_statuses = [
                "New",
                "In Progress",
                "Resolved"
            ];

            if (!in_array($status, $allowed_statuses, true)) {
                $error = "Invalid message status.";

            } else {
                $stmt = $conn->prepare(
                    "UPDATE contact_messages
                     SET status = ?
                     WHERE id = ?"
                );

                if ($stmt) {
                    $stmt->bind_param("si", $status, $message_id);

                    if ($stmt->execute()) {
                        $notice = "Message status updated successfully.";
                    } else {
                        $error = "Unable to update the message status.";
                    }

                    $stmt->close();

                } else {
                    $error = "Unable to prepare the database query.";
                }
            }

        } elseif ($action === "delete_message") {

            $stmt = $conn->prepare(
                "DELETE FROM contact_messages WHERE id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("i", $message_id);

                if ($stmt->execute()) {
                    $notice = $stmt->affected_rows > 0
                        ? "Message deleted successfully."
                        : "The selected message was not found.";
                } else {
                    $error = "Unable to delete the message.";
                }

                $stmt->close();

            } else {
                $error = "Unable to prepare the database query.";
            }

        } else {
            $error = "Unknown action.";
        }
    }
}

/* =====================================================
   SEARCH MESSAGES
===================================================== */

$search = trim($_GET["search"] ?? "");

$messages = [];
$total_messages = 0;
$new_messages = 0;
$progress_messages = 0;
$resolved_messages = 0;

/* =====================================================
   LOAD MESSAGE STATISTICS
===================================================== */

$stats_sql = "
    SELECT
        COUNT(*) AS total_messages,
        COALESCE(SUM(status = 'New'), 0) AS new_messages,
        COALESCE(SUM(status = 'In Progress'), 0) AS progress_messages,
        COALESCE(SUM(status = 'Resolved'), 0) AS resolved_messages
    FROM contact_messages
";

$stats_result = $conn->query($stats_sql);

if ($stats_result) {
    $stats = $stats_result->fetch_assoc();

    $total_messages = (int)$stats["total_messages"];
    $new_messages = (int)$stats["new_messages"];
    $progress_messages = (int)$stats["progress_messages"];
    $resolved_messages = (int)$stats["resolved_messages"];

    $stats_result->free();

} else {
    $error = "Unable to load message statistics. Check the contact_messages table.";
}

/* =====================================================
   LOAD CONTACT MESSAGES
===================================================== */

if ($search !== "") {

    $sql = "
        SELECT
            id,
            customer_name,
            customer_email,
            customer_phone,
            subject,
            message,
            status,
            created_at
        FROM contact_messages
        WHERE customer_name LIKE ?
           OR customer_email LIKE ?
           OR subject LIKE ?
        ORDER BY created_at DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $term = "%" . $search . "%";

        $stmt->bind_param("sss", $term, $term, $term);

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }

            $result->free();

        } else {
            $error = "Unable to retrieve contact messages.";
        }

        $stmt->close();

    } else {
        $error = "Unable to prepare the message search.";
    }

} else {

    $sql = "
        SELECT
            id,
            customer_name,
            customer_email,
            customer_phone,
            subject,
            message,
            status,
            created_at
        FROM contact_messages
        ORDER BY created_at DESC
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        $result->free();

    } else {
        $error = "Unable to retrieve contact messages.";
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

    <title>Contact Messages | PulseMed Essentials</title>

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
            --green: #24a77b;
            --background: #eef8f6;
            --text: #123f46;
            --muted: #70858a;
            --border: #e2ecea;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        .sidebar {
            width: 250px;
            position: fixed;
            inset: 0 auto 0 0;
            background: #fff;
            padding: 28px 18px;
            border-right: 1px solid var(--border);
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 28px;
            border-bottom: 1px solid var(--border);
        }

        .brand img {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 17px;
            font-weight: 800;
            line-height: 1.2;
        }

        .brand-name span {
            display: block;
            color: var(--green);
        }

        .nav-title {
            margin: 28px 12px 12px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.4px;
        }

        .side-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 14px;
            margin-bottom: 6px;
            border-radius: 10px;
            color: #5b7479;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .side-link i {
            font-size: 18px;
        }

        .side-link:hover,
        .side-link.active {
            background: #e9f7f5;
            color: var(--primary);
        }

        .side-link.logout {
            color: #c74b54;
        }

        .main {
            margin-left: 250px;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .eyebrow {
            color: var(--green);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            margin: 6px 0;
            font-size: 29px;
            font-weight: 800;
        }

        .subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .admin-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            background: #e2f5ee;
            color: var(--primary);
            border-radius: 50%;
            font-size: 20px;
        }

        .stat-card {
            height: 100%;
            background: white;
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 22px;
            box-shadow: 0 4px 18px rgba(18, 63, 70, .025);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            background: #e7f5f3;
            color: var(--primary);
            border-radius: 12px;
            font-size: 21px;
        }

        .stat-number {
            margin-top: 17px;
            font-size: 29px;
            font-weight: 800;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .panel {
            margin-top: 27px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 17px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            padding: 22px;
            border-bottom: 1px solid var(--border);
        }

        .panel-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-form input {
            width: 260px;
            max-width: 100%;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 10px 12px;
            outline-color: var(--primary);
        }

        .btn-primary-custom {
            background: var(--primary);
            color: #fff;
            border: 0;
            border-radius: 9px;
            padding: 10px 15px;
            font-weight: 700;
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: #fff;
        }

        .table-responsive {
            padding: 0 18px 18px;
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            padding: 17px 12px;
            background: #f7fbfa;
            color: #6b8286;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .7px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 17px 12px;
            border-color: #edf3f2;
            font-size: 13px;
        }

        .customer-name {
            font-weight: 800;
            color: var(--text);
        }

        .small-muted {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .subject-text {
            max-width: 200px;
            font-weight: 700;
        }

        .message-preview {
            max-width: 250px;
            color: #70858a;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-new {
            background: #fff3d7;
            color: #986500;
        }

        .status-progress {
            background: #e4f0ff;
            color: #245c9a;
        }

        .status-resolved {
            background: #e1f6eb;
            color: #18784b;
        }

        .status-form {
            min-width: 145px;
        }

        .status-form select {
            margin-bottom: 7px;
            border-color: var(--border);
            border-radius: 8px;
            padding: 7px;
            font-size: 12px;
        }

        .btn-small {
            padding: 7px 10px;
            border: 0;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-save {
            background: #e4f4ef;
            color: #087f8c;
        }

        .btn-delete {
            background: #fff0f0;
            color: #bf414b;
        }

        .btn-email {
            background: #eaf3ff;
            color: #275f9b;
        }

        .empty-state {
            padding: 65px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state i {
            display: block;
            margin-bottom: 15px;
            color: #9eb8b7;
            font-size: 42px;
        }

        .empty-state h3 {
            color: var(--text);
            font-size: 18px;
            font-weight: 800;
        }

        @media (max-width: 1000px) {
            .sidebar {
                width: 75px;
                padding: 20px 10px;
            }

            .brand {
                justify-content: center;
                padding: 0 0 20px;
            }

            .brand img {
                width: 42px;
                height: 42px;
            }

            .brand-name,
            .nav-title,
            .side-link span {
                display: none;
            }

            .side-link {
                justify-content: center;
                padding: 13px 8px;
            }

            .main {
                margin-left: 75px;
                padding: 25px;
            }
        }

        @media (max-width: 600px) {
            .main {
                padding: 16px;
            }

            .topbar {
                align-items: flex-start;
            }

            .topbar h1 {
                font-size: 23px;
            }

            .admin-chip {
                display: none;
            }

            .search-form {
                width: 100%;
            }

            .search-form input {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<!-- =================================================
     SIDEBAR
================================================== -->

<aside class="sidebar">

    <div class="brand">
        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essentials logo"
        >

        <div class="brand-name">
            PulseMed
            <span>Essentials</span>
        </div>
    </div>

    <div class="nav-title">MAIN MENU</div>

    <nav>
        <a class="side-link" href="dashboard.php">
            <i class="bi bi-grid"></i>
            <span>Dashboard</span>
        </a>

        <a class="side-link" href="products.php">
            <i class="bi bi-box-seam"></i>
            <span>Products</span>
        </a>

        <a class="side-link" href="orders.php">
            <i class="bi bi-bag-check"></i>
            <span>Orders</span>
        </a>

        <a class="side-link" href="users.php">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>

        <a class="side-link" href="reports.php">
            <i class="bi bi-bar-chart"></i>
            <span>Reports</span>
        </a>

        <a class="side-link active" href="contact_messages.php">
            <i class="bi bi-envelope"></i>
            <span>Contact Messages</span>
        </a>

        <a class="side-link" href="profile.php">
            <i class="bi bi-person-circle"></i>
            <span>Profile</span>
        </a>

        <a class="side-link logout" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </nav>

</aside>

<!-- =================================================
     MAIN CONTENT
================================================== -->

<main class="main">

    <header class="topbar">
        <div>
            <div class="eyebrow">Customer Support Centre</div>

            <h1>Contact Messages</h1>

            <p class="subtitle">
                Review and manage customer enquiries.
            </p>
        </div>

        <div class="admin-chip">
            <div class="admin-avatar">
                <i class="bi bi-person-check"></i>
            </div>

            <div>
                Administrator
                <div class="small-muted">
                    PulseMed Essentials
                </div>
            </div>
        </div>
    </header>

    <?php if ($notice !== ""): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <?= e($notice) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->

    <div class="row g-3">

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total Messages</span>
                    <div class="stat-icon">
                        <i class="bi bi-envelope-paper"></i>
                    </div>
                </div>

                <div class="stat-number">
                    <?= $total_messages ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">New Messages</span>
                    <div class="stat-icon">
                        <i class="bi bi-bell"></i>
                    </div>
                </div>

                <div class="stat-number">
                    <?= $new_messages ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">In Progress</span>
                    <div class="stat-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>

                <div class="stat-number">
                    <?= $progress_messages ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Resolved</span>
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>

                <div class="stat-number">
                    <?= $resolved_messages ?>
                </div>
            </div>
        </div>

    </div>

    <!-- MESSAGES PANEL -->

    <section class="panel">

        <div class="panel-header">

            <div>
                <h2>Customer Enquiries</h2>

                <p class="subtitle mt-1">
                    <?= count($messages) ?> message(s) displayed
                </p>
            </div>

            <form
                class="search-form"
                method="GET"
                action="contact_messages.php"
            >
                <input
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search name, email or subject..."
                    aria-label="Search messages"
                >

                <button class="btn-primary-custom" type="submit">
                    <i class="bi bi-search"></i>
                    Search
                </button>

                <?php if ($search !== ""): ?>
                    <a
                        class="btn btn-light"
                        href="contact_messages.php"
                    >
                        Clear
                    </a>
                <?php endif; ?>
            </form>

        </div>

        <?php if (count($messages) > 0): ?>

            <div class="table-responsive">

                <table class="table">

                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Subject & Message</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($messages as $msg): ?>

                        <?php
                            $status_class = "status-new";

                            if ($msg["status"] === "In Progress") {
                                $status_class = "status-progress";
                            } elseif ($msg["status"] === "Resolved") {
                                $status_class = "status-resolved";
                            }
                        ?>

                        <tr>

                            <td>
                                <div class="customer-name">
                                    <?= e($msg["customer_name"]) ?>
                                </div>

                                <span class="small-muted">
                                    <?= e($msg["customer_email"]) ?>
                                </span>

                                <?php if (!empty($msg["customer_phone"])): ?>
                                    <span class="small-muted">
                                        <?= e($msg["customer_phone"]) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="subject-text">
                                    <?= e($msg["subject"]) ?>
                                </div>

                                <div class="message-preview mt-2">
                                    <?= e(mb_strimwidth(
                                        $msg["message"],
                                        0,
                                        110,
                                        "..."
                                    )) ?>
                                </div>

                                <button
                                    type="button"
                                    class="btn-small btn-email mt-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#messageModal<?= (int)$msg["id"] ?>"
                                >
                                    <i class="bi bi-eye"></i>
                                    Read Message
                                </button>
                            </td>

                            <td>
                                <?= e(date(
                                    "d M Y",
                                    strtotime($msg["created_at"])
                                )) ?>

                                <span class="small-muted">
                                    <?= e(date(
                                        "h:i A",
                                        strtotime($msg["created_at"])
                                    )) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status-badge <?= e($status_class) ?>">
                                    <?= e($msg["status"]) ?>
                                </span>
                            </td>

                            <td>

                                <form
                                    class="status-form"
                                    method="POST"
                                    action="contact_messages.php<?= $search !== "" ? "?search=" . rawurlencode($search) : "" ?>"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION["contact_messages_csrf"]) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?= (int)$msg["id"] ?>"
                                    >

                                    <select
                                        class="form-select"
                                        name="status"
                                        aria-label="Message status"
                                    >
                                        <option
                                            value="New"
                                            <?= $msg["status"] === "New" ? "selected" : "" ?>
                                        >
                                            New
                                        </option>

                                        <option
                                            value="In Progress"
                                            <?= $msg["status"] === "In Progress" ? "selected" : "" ?>
                                        >
                                            In Progress
                                        </option>

                                        <option
                                            value="Resolved"
                                            <?= $msg["status"] === "Resolved" ? "selected" : "" ?>
                                        >
                                            Resolved
                                        </option>
                                    </select>

                                    <button
                                        type="submit"
                                        class="btn-small btn-save"
                                    >
                                        <i class="bi bi-check2"></i>
                                        Update
                                    </button>

                                </form>

                                <a
                                    class="btn-small btn-email d-inline-block text-decoration-none mt-2"
                                    href="mailto:<?= e($msg["customer_email"]) ?>?subject=<?= rawurlencode("Re: " . $msg["subject"]) ?>"
                                >
                                    <i class="bi bi-reply"></i>
                                    Reply
                                </a>

                                <form
                                    method="POST"
                                    action="contact_messages.php<?= $search !== "" ? "?search=" . rawurlencode($search) : "" ?>"
                                    class="mt-2"
                                    onsubmit="return confirm('Are you sure you want to permanently delete this message?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION["contact_messages_csrf"]) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete_message"
                                    >

                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?= (int)$msg["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-small btn-delete"
                                    >
                                        <i class="bi bi-trash"></i>
                                        Delete
                                    </button>
                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <i class="bi bi-envelope-open"></i>

                <h3>
                    <?= $search !== "" ? "No matching messages found" : "No enquiries yet" ?>
                </h3>

                <p>
                    <?= $search !== ""
                        ? "Try searching using a different name, email address, or subject."
                        : "Customer enquiries submitted through the Contact Centre will appear here." ?>
                </p>

            </div>

        <?php endif; ?>

    </section>

    <!-- MESSAGE DETAILS MODALS -->

    <?php foreach ($messages as $msg): ?>

        <div
            class="modal fade"
            id="messageModal<?= (int)$msg["id"] ?>"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered modal-lg">

                <div class="modal-content border-0">

                    <div class="modal-header">
                        <div>
                            <div class="eyebrow">Customer Enquiry</div>

                            <h5 class="modal-title fw-bold mt-1">
                                <?= e($msg["subject"]) ?>
                            </h5>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                        ></button>
                    </div>

                    <div class="modal-body p-4">

                        <div class="mb-3">
                            <strong>Customer:</strong>
                            <?= e($msg["customer_name"]) ?>
                        </div>

                        <div class="mb-3">
                            <strong>Email:</strong>
                            <?= e($msg["customer_email"]) ?>
                        </div>

                        <?php if (!empty($msg["customer_phone"])): ?>
                            <div class="mb-3">
                                <strong>Phone:</strong>
                                <?= e($msg["customer_phone"]) ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <strong>Received:</strong>
                            <?= e($msg["created_at"]) ?>
                        </div>

                        <hr>

                        <h6 class="fw-bold">Message</h6>

                        <div
                            class="p-3 rounded"
                            style="background:#f3f9f7;white-space:pre-wrap;overflow-wrap:anywhere;"
                        ><?= e($msg["message"]) ?></div>

                    </div>

                    <div class="modal-footer">

                        <a
                            class="btn btn-primary"
                            href="mailto:<?= e($msg["customer_email"]) ?>?subject=<?= rawurlencode("Re: " . $msg["subject"]) ?>"
                        >
                            <i class="bi bi-reply"></i>
                            Reply by Email
                        </a>

                        <button
                            type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal"
                        >
                            Close
                        </button>

                    </div>

                </div>

            </div>
        </div>

    <?php endforeach; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>