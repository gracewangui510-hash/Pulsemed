<?php
session_start();
require_once "../database.php";

/* =========================================================
   ADMIN ACCESS PROTECTION
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION["user_role"] !== "admin") {
    header("Location: ../customer/dashboard.php");
    exit();
}

$admin_id = $_SESSION["user_id"];

$message = "";
$error = "";

/* =========================================================
   UPDATE PROFILE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* Validate name */
    if (empty($name)) {
        $error = "Please enter your name.";
    }

    /* Validate email */
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }

    /* Check password if entered */
    elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "The passwords do not match.";
    }

    else {

        /* Check whether email belongs to another user */
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
        );

        $check->bind_param("si", $email, $admin_id);
        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error = "That email address is already being used.";

        } else {

            /* Update password if provided */
            if (!empty($new_password)) {

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET name = ?, email = ?, password = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "sssi",
                    $name,
                    $email,
                    $hashed_password,
                    $admin_id
                );

            } else {

                /* Update name and email only */
                $stmt = $conn->prepare(
                    "UPDATE users
                     SET name = ?, email = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "ssi",
                    $name,
                    $email,
                    $admin_id
                );
            }

            if ($stmt->execute()) {

                /* Update session information */
                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;

                $message = "Profile updated successfully.";

            } else {

                $error = "Unable to update your profile.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

/* =========================================================
   GET CURRENT ADMIN DETAILS
========================================================= */

$stmt = $conn->prepare(
    "SELECT id, name, email, role
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $admin_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$admin = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Profile | PulseMed</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f9;
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
            background: #0f766e;
            color: white;
            padding: 25px 18px;
        }

        .logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-icon {
            width: 58px;
            height: 58px;
            background: white;
            color: #0f766e;
            border-radius: 50%;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 27px;
            font-weight: bold;
        }

        .logo h2 {
            margin-top: 10px;
            font-size: 21px;
        }

        .logo p {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px 15px;
            margin-bottom: 7px;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .menu a:hover,
        .menu a.active {
            background: rgba(255,255,255,0.16);
        }

        .menu span {
            margin-right: 10px;
        }

        /* =================================================
           MAIN CONTENT
        ================================================= */

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .topbar h1 {
            font-size: 27px;
            color: #173b3a;
        }

        .topbar p {
            color: #718096;
            margin-top: 5px;
            font-size: 14px;
        }

        .back-btn {
            text-decoration: none;
            background: white;
            color: #0f766e;
            border: 1px solid #d7e4e5;
            padding: 10px 17px;
            border-radius: 7px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #eef8f7;
        }

        /* =================================================
           PROFILE CARD
        ================================================= */

        .profile-container {
            max-width: 850px;
            margin: auto;
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .profile-header {
            background: #0f766e;
            color: white;
            padding: 35px;
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .avatar {
            width: 85px;
            height: 85px;
            background: white;
            color: #0f766e;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 34px;
            font-weight: bold;
        }

        .profile-header h2 {
            font-size: 23px;
            margin-bottom: 6px;
        }

        .profile-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .profile-body {
            padding: 35px;
        }

        .section-title {
            font-size: 18px;
            color: #173b3a;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5eeee;
            padding-bottom: 12px;
        }

        /* =================================================
           ALERTS
        ================================================= */

        .success {
            background: #e7f7ed;
            color: #17663b;
            border: 1px solid #b9e5c9;
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .error {
            background: #fff0f0;
            color: #a52a2a;
            border: 1px solid #f1c1c1;
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* =================================================
           FORM
        ================================================= */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
            color: #37474f;
        }

        .form-group input {
            width: 100%;
            padding: 13px;
            border: 1px solid #d5dfe1;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 2px rgba(15,118,110,0.08);
        }

        .role-box {
            background: #f3f8f8;
            border: 1px solid #dce9e9;
            padding: 13px;
            border-radius: 7px;
            color: #0f766e;
            font-size: 14px;
            font-weight: bold;
        }

        .password-note {
            color: #7a8790;
            font-size: 12px;
            margin-top: 6px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .save-btn {
            border: none;
            background: #0f766e;
            color: white;
            padding: 13px 25px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .save-btn:hover {
            background: #0b625c;
        }

        .cancel-btn {
            text-decoration: none;
            background: #eef2f3;
            color: #455a64;
            padding: 13px 25px;
            border-radius: 7px;
            font-size: 14px;
        }

        .cancel-btn:hover {
            background: #e1e7e8;
        }

        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .menu a {
                margin-bottom: 0;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .profile-header {
                padding: 25px;
            }

            .profile-body {
                padding: 25px;
            }

        }

    </style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
====================================================== -->

<div class="sidebar">

    <div class="logo">

        <div class="logo-icon">
            +
        </div>

        <h2>PulseMed</h2>

        <p>Administrator Panel</p>

    </div>


    <div class="menu">

        <a href="dashboard.php">
            <span>▣</span> Dashboard
        </a>

        <a href="users.php">
            <span>👥</span> Users
        </a>

        <a href="products.php">
            <span>▦</span> Products
        </a>

        <a href="orders.php">
            <span>🛒</span> Orders
        </a>

        <a href="reports.php">
            <span>▤</span> Reports
        </a>

        <a href="profile.php" class="active">
            <span>👤</span> My Profile
        </a>

        <a href="../logout.php">
            <span>↪</span> Logout
        </a>

    </div>

</div>


<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<div class="main">

    <div class="topbar">

        <div>

            <h1>My Profile</h1>

            <p>
                Manage your administrator account information.
            </p>

        </div>

        <a href="dashboard.php" class="back-btn">
            ← Back to Dashboard
        </a>

    </div>


    <div class="profile-container">

        <!-- PROFILE HEADER -->

        <div class="profile-header">

            <div class="avatar">

                <?php
                echo strtoupper(
                    substr($admin["name"], 0, 1)
                );
                ?>

            </div>

            <div>

                <h2>
                    <?php
                    echo htmlspecialchars($admin["name"]);
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars($admin["email"]);
                    ?>
                </p>

            </div>

        </div>


        <!-- PROFILE BODY -->

        <div class="profile-body">

            <?php if (!empty($message)): ?>

                <div class="success">
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php endif; ?>


            <?php if (!empty($error)): ?>

                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>


            <h3 class="section-title">
                Account Information
            </h3>


            <form method="POST"
                  action="profile.php">


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php
                            echo htmlspecialchars($admin["name"]);
                        ?>"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php
                            echo htmlspecialchars($admin["email"]);
                        ?>"
                        required
                    >

                </div>


                <!-- ROLE -->

                <div class="form-group">

                    <label>
                        Account Role
                    </label>

                    <div class="role-box">
                        Administrator
                    </div>

                </div>


                <h3 class="section-title">
                    Change Password
                </h3>


                <!-- NEW PASSWORD -->

                <div class="form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Enter new password"
                    >

                    <div class="password-note">
                        Leave this field empty if you do not want to
                        change your password.
                    </div>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                    >

                </div>


                <!-- BUTTONS -->

                <div class="buttons">

                    <button
                        type="submit"
                        class="save-btn">
                        Save Changes
                    </button>

                    <a
                        href="dashboard.php"
                        class="cancel-btn">
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>