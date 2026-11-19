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
    header("Location: ../admin/dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

$success = "";
$error = "";

/* =========================================================
   FETCH CUSTOMER DETAILS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT id, name, email, phone, password, role, created_at
    FROM users
    WHERE id = ? AND role = 'customer'
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$user = $result->fetch_assoc();

$stmt->close();

/* =========================================================
   UPDATE PROFILE
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* -----------------------------------------------------
       VALIDATE NAME
       ----------------------------------------------------- */

    if ($name === "") {

        $error = "Please enter your full name.";

    } elseif (!preg_match("/^[A-Za-z ]+$/", $name)) {

        $error = "Name must contain letters and spaces only.";

    }

    /* -----------------------------------------------------
       VALIDATE EMAIL
       ----------------------------------------------------- */

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }

    /* -----------------------------------------------------
       VALIDATE PHONE
       ----------------------------------------------------- */

    elseif ($phone !== "" && !preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Phone number must contain exactly 10 digits.";

    }

    /* -----------------------------------------------------
       CHECK EMAIL
       ----------------------------------------------------- */

    else {

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();

        $email_result = $stmt->get_result();

        if ($email_result->num_rows > 0) {

            $error = "That email address is already being used.";

        }

        $stmt->close();
    }

    /* -----------------------------------------------------
       PASSWORD VALIDATION
       ----------------------------------------------------- */

    if ($error === "" && $new_password !== "") {

        if (strlen($new_password) < 6) {

            $error = "New password must be at least 6 characters long.";

        } elseif ($new_password !== $confirm_password) {

            $error = "New password and confirmation password do not match.";

        }
    }

    /* =====================================================
       UPDATE DATABASE
       ===================================================== */

    if ($error === "") {

        if ($new_password !== "") {

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                UPDATE users
                SET name = ?, email = ?, phone = ?, password = ?
                WHERE id = ? AND role = 'customer'
            ");

            $stmt->bind_param(
                "ssssi",
                $name,
                $email,
                $phone,
                $hashed_password,
                $user_id
            );

        } else {

            $stmt = $conn->prepare("
                UPDATE users
                SET name = ?, email = ?, phone = ?
                WHERE id = ? AND role = 'customer'
            ");

            $stmt->bind_param(
                "sssi",
                $name,
                $email,
                $phone,
                $user_id
            );
        }

        if ($stmt->execute()) {

            /* Update session information */

            $_SESSION["user_name"] = $name;
            $_SESSION["user_email"] = $email;

            $success = "Your profile has been updated successfully.";

            /* Refresh displayed information */

            $user["name"] = $name;
            $user["email"] = $email;
            $user["phone"] = $phone;

        } else {

            $error = "Unable to update your profile. Please try again.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile | PulseMed Essential</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
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
            background: #073b4c;
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
        }

        .brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-logo {
            width: 125px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 10px;
            background: white;
            padding: 6px;
            border-radius: 10px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
        }

        .brand-subtitle {
            font-size: 12px;
            opacity: .75;
            margin-top: 4px;
        }

        .menu {
            list-style: none;
        }

        .menu li {
            margin-bottom: 7px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 15px;
            color: #eaf7f5;
            text-decoration: none;
            border-radius: 8px;
            transition: .2s;
            font-size: 14px;
        }

        .menu a:hover,
        .menu a.active {
            background: #0b6577;
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        /* =================================================
           MAIN
           ================================================= */

        .main {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 28px;
            color: #073b4c;
        }

        .page-title p {
            color: #718096;
            margin-top: 6px;
            font-size: 14px;
        }

        .welcome {
            background: white;
            padding: 11px 17px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            font-size: 14px;
        }

        /* =================================================
           PROFILE LAYOUT
           ================================================= */

        .profile-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            max-width: 1000px;
        }

        /* =================================================
           PROFILE CARD
           ================================================= */

        .profile-card {
            background: white;
            border-radius: 14px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0,0,0,.06);
            height: fit-content;
        }

        .profile-avatar {
            width: 95px;
            height: 95px;
            border-radius: 50%;
            background: #0b6577;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 18px;
            font-size: 38px;
            font-weight: bold;
        }

        .profile-card h2 {
            color: #073b4c;
            font-size: 20px;
            margin-bottom: 7px;
        }

        .profile-role {
            display: inline-block;
            background: #e0f5ef;
            color: #08785e;
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .member-info {
            border-top: 1px solid #edf0f2;
            padding-top: 18px;
            text-align: left;
        }

        .member-info p {
            font-size: 13px;
            color: #718096;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .member-info strong {
            color: #37474f;
        }

        /* =================================================
           FORM CARD
           ================================================= */

        .form-card {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 3px 15px rgba(0,0,0,.06);
        }

        .form-card h2 {
            color: #073b4c;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .form-description {
            color: #718096;
            font-size: 13px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            color: #37474f;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d6e0df;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: .2s;
        }

        .form-group input:focus {
            border-color: #0b6577;
            box-shadow: 0 0 0 3px rgba(11,101,119,.08);
        }

        .password-section {
            border-top: 1px solid #edf0f2;
            margin-top: 25px;
            padding-top: 22px;
        }

        .password-section h3 {
            color: #073b4c;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .password-section p {
            color: #718096;
            font-size: 12px;
            margin-bottom: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* =================================================
           BUTTON
           ================================================= */

        .save-btn {
            border: none;
            background: #0b6577;
            color: white;
            padding: 13px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-top: 5px;
            transition: .2s;
        }

        .save-btn:hover {
            background: #084b5a;
        }

        /* =================================================
           MESSAGES
           ================================================= */

        .success-message {
            background: #e1f7ea;
            color: #14733f;
            border: 1px solid #bde8ce;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .error-message {
            background: #ffe8e8;
            color: #a42626;
            border: 1px solid #f3c4c4;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        /* =================================================
           FOOTER
           ================================================= */

        .footer {
            text-align: center;
            color: #8a98a5;
            font-size: 12px;
            padding: 30px 0 10px;
        }

        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 900px) {

            .profile-container {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 750px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <img
            src="../assets/images/pulsemed-logo.png"
            alt="PulseMed Essential Logo"
            class="brand-logo"
        >

        <div class="brand-name">
            PulseMed Essential
        </div>

        <div class="brand-subtitle">
            Medical Supplies
        </div>

    </div>


    <ul class="menu">

        <li>
            <a href="dashboard.php">

                <span class="menu-icon">▣</span>

                Dashboard

            </a>
        </li>


        <li>
            <a href="browse_products.php">

                <span class="menu-icon">▤</span>

                Browse Products

            </a>
        </li>


        <li>
            <a href="place_order.php">

                <span class="menu-icon">🛒</span>

                Place Order

            </a>
        </li>


        <li>
            <a href="my_orders.php">

                <span class="menu-icon">📦</span>

                My Orders

            </a>
        </li>


        <li>
            <a href="payments.php">

                <span class="menu-icon">💳</span>

                Payments

            </a>
        </li>


        <li>
            <a href="profile.php" class="active">

                <span class="menu-icon">👤</span>

                My Profile

            </a>
        </li>


        <li>
            <a href="logout.php">

                <span class="menu-icon">↪</span>

                Logout

            </a>
        </li>

    </ul>

</aside>


<!-- =====================================================
     MAIN CONTENT
     ===================================================== -->

<main class="main">


    <div class="topbar">

        <div class="page-title">

            <h1>My Profile</h1>

            <p>
                Manage your PulseMed Essential account information.
            </p>

        </div>


        <div class="welcome">

            Welcome,
            <strong>
                <?php
                echo htmlspecialchars($user["name"]);
                ?>
            </strong>

        </div>

    </div>


    <div class="profile-container">


        <!-- =============================================
             PROFILE INFORMATION CARD
             ============================================= -->

        <div class="profile-card">

            <div class="profile-avatar">

                <?php

                $first_letter = strtoupper(
                    substr($user["name"], 0, 1)
                );

                echo htmlspecialchars($first_letter);

                ?>

            </div>


            <h2>

                <?php
                echo htmlspecialchars($user["name"]);
                ?>

            </h2>


            <span class="profile-role">
                CUSTOMER
            </span>


            <div class="member-info">

                <p>

                    <strong>Email:</strong><br>

                    <?php
                    echo htmlspecialchars($user["email"]);
                    ?>

                </p>


                <p>

                    <strong>Phone:</strong><br>

                    <?php
                    echo htmlspecialchars(
                        $user["phone"] ?: "Not provided"
                    );
                    ?>

                </p>


                <p>

                    <strong>Account Created:</strong><br>

                    <?php

                    echo date(
                        "d M Y",
                        strtotime($user["created_at"])
                    );

                    ?>

                </p>

            </div>

        </div>


        <!-- =============================================
             EDIT PROFILE FORM
             ============================================= -->

        <div class="form-card">

            <h2>
                Edit Profile
            </h2>

            <p class="form-description">
                Update your personal information below.
            </p>


            <?php if ($success !== ""): ?>

                <div class="success-message">

                    <?php
                    echo htmlspecialchars($success);
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <div class="error-message">

                    <?php
                    echo htmlspecialchars($error);
                    ?>

                </div>

            <?php endif; ?>


            <form method="POST"
                  action="profile.php"
                  onsubmit="return validateForm();">


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
                        echo htmlspecialchars($user["name"]);
                        ?>"
                        placeholder="Enter your full name"
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
                        echo htmlspecialchars($user["email"]);
                        ?>"
                        placeholder="example@gmail.com"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?php
                        echo htmlspecialchars($user["phone"] ?? "");
                        ?>"
                        placeholder="0712345678"
                        maxlength="10"
                    >

                </div>


                <!-- PASSWORD -->

                <div class="password-section">

                    <h3>
                        Change Password
                    </h3>

                    <p>
                        Leave these fields blank if you do not want
                        to change your password.
                    </p>


                    <div class="form-row">


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

                        </div>


                        <div class="form-group">

                            <label for="confirm_password">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm new password"
                            >

                        </div>

                    </div>

                </div>


                <button
                    type="submit"
                    class="save-btn">

                    Save Changes

                </button>

            </form>

        </div>

    </div>


    <div class="footer">

        © <?php echo date("Y"); ?>
        PulseMed Essential.
        Medical Supplies Management System.

    </div>


</main>


<script>

    /* =====================================================
       CLIENT-SIDE VALIDATION
       ===================================================== */

    function validateForm() {

        const name =
            document.getElementById("name").value.trim();

        const phone =
            document.getElementById("phone").value.trim();

        const newPassword =
            document.getElementById("new_password").value;

        const confirmPassword =
            document.getElementById("confirm_password").value;


        /* Name validation */

        const namePattern = /^[A-Za-z ]+$/;

        if (!namePattern.test(name)) {

            alert(
                "Full name must contain letters and spaces only."
            );

            return false;
        }


        /* Phone validation */

        if (phone !== "") {

            const phonePattern = /^[0-9]{10}$/;

            if (!phonePattern.test(phone)) {

                alert(
                    "Phone number must contain exactly 10 digits."
                );

                return false;
            }
        }


        /* Password validation */

        if (newPassword !== "") {

            if (newPassword.length < 6) {

                alert(
                    "New password must be at least 6 characters long."
                );

                return false;
            }


            if (newPassword !== confirmPassword) {

                alert(
                    "New password and confirmation password do not match."
                );

                return false;
            }

        }


        return true;

    }

</script>

</body>

</html>