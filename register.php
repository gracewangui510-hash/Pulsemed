<?php

require_once "database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* ================= VALIDATION ================= */

    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = "Please fill in all required fields.";

    }

    elseif (!preg_match("/^[a-zA-Z ]+$/", $name)) {

        $error = "Full name should contain letters and spaces only.";

    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }

    elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Phone number must contain exactly 10 digits.";

    }

    elseif (strlen($password) < 8) {

        $error = "Password must contain at least 8 characters.";

    }

    elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    }

    elseif (!isset($_POST["terms"])) {

        $error = "Please accept the terms and conditions.";

    }

    else {

        /* ================= CHECK EMAIL ================= */

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = "An account with this email already exists.";

        }

        else {

            /* ================= HASH PASSWORD ================= */

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /* ================= INSERT USER ================= */

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, email, phone, password, role)
                VALUES (?, ?, ?, ?, 'customer')"
            );

            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $phone,
                $hashed_password
            );

            if ($stmt->execute()) {

                $success =
                    "Registration successful! You can now log in.";

                $_POST = [];

            }

            else {

                $error =
                    "Registration failed. Please try again.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Account | PulseMed Essential</title>

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">


    <style>

        /* =====================================================
           GENERAL
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #087f8c;
            --primary-dark: #06733c;
            --green: #24a77b;
            --dark: #123f46;
            --text: #536b71;
            --muted: #87999d;
            --border: #dce9e8;
            --background: #eef8f6;
            --white: #ffffff;
            --danger: #c0392b;
            --success: #198754;
        }

        body {
            min-height: 100vh;

            font-family:
                "Inter",
                "Segoe UI",
                Arial,
                sans-serif;

            color: var(--dark);

            background:
                radial-gradient(
                    circle at 5% 5%,
                    rgba(8, 127, 140, 0.13),
                    transparent 27%
                ),
                radial-gradient(
                    circle at 95% 95%,
                    rgba(36, 167, 123, 0.13),
                    transparent 28%
                ),
                linear-gradient(
                    135deg,
                    #f5fcfb,
                    #e9f7f4
                );
        }

        button,
        input {
            font-family: inherit;
        }

        a {
            text-decoration: none;
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .page {
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 40px 20px;

            position: relative;
            overflow: hidden;
        }


        /* =====================================================
           DECORATIVE BACKGROUND
        ===================================================== */

        .circle {
            position: absolute;

            border-radius: 50%;

            pointer-events: none;
        }

        .circle-one {
            width: 380px;
            height: 380px;

            top: -190px;
            right: -120px;

            border:
                1px solid
                rgba(8, 127, 140, 0.10);
        }

        .circle-two {
            width: 280px;
            height: 280px;

            bottom: -150px;
            left: -100px;

            border:
                1px solid
                rgba(36, 167, 123, 0.12);
        }


        /* =====================================================
           MAIN CARD
        ===================================================== */

        .register-container {

            width: 100%;
            max-width: 1080px;

            display: grid;

            grid-template-columns:
                0.9fr 1.1fr;

            background: var(--white);

            border-radius: 28px;

            overflow: hidden;

            position: relative;
            z-index: 2;

            box-shadow:
                0 30px 80px
                rgba(23, 73, 79, 0.13);
        }


        /* =====================================================
           LEFT BRAND PANEL
        ===================================================== */

        .brand-panel {

            position: relative;

            padding: 55px 45px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            color: white;

            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    #088c3f,
                    #067806 52%,
                    #24a72b
                );
        }

        .brand-panel::before {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            border-radius: 50%;

            top: -130px;
            right: -120px;

            border:
                1px solid
                rgba(255, 255, 255, 0.15);
        }

        .brand-panel::after {
            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            border-radius: 50%;

            bottom: -100px;
            left: -100px;

            background:
                rgba(255, 255, 255, 0.06);
        }


        /* =====================================================
           BRAND LOGO
        ===================================================== */

        .brand-logo {

            display: flex;
            align-items: center;

            gap: 11px;

            color: white;

            font-size: 1.3rem;
            font-weight: 800;

            position: relative;
            z-index: 2;
        }

        /* ACTUAL PULSEMED LOGO */

        .brand-logo-image {

            width: 44px;
            height: 44px;

            object-fit: contain;

            border-radius: 10px;

            background:
                rgba(255, 255, 255, 0.1);

            padding: 4px;

            display: block;
        }

        .brand-logo span {
            color: #e8ffd9;
        }


        /* =====================================================
           BRAND CONTENT
        ===================================================== */

        .brand-content {

            position: relative;
            z-index: 2;

            margin-top: 70px;
        }

        .brand-content h2 {

            font-size: 2.45rem;

            line-height: 1.15;

            font-weight: 800;

            letter-spacing: -1px;

            margin-bottom: 18px;
        }

        .brand-content h2 span {
            color: #bff6e4;
        }

        .brand-content p {

            max-width: 390px;

            color:
                rgba(255, 255, 255, 0.8);

            font-size: 0.94rem;

            line-height: 1.8;
        }


        /* =====================================================
           MEDICAL LOGO
        ===================================================== */

        .medical-symbol {

            width: 120px;
            height: 120px;

            margin: 38px 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 32px;

            background:
                rgba(255, 255, 255, 0.12);

            border:
                1px solid
                rgba(255,255,255,0.18);

            box-shadow:
                0 20px 40px
                rgba(0, 0, 0, 0.1);

            overflow: hidden;
        }

        .medical-symbol img {

            width: 88px;
            height: 88px;

            object-fit: contain;

            display: block;
        }


        /* =====================================================
           BRAND FEATURES
        ===================================================== */

        .brand-features {

            display: flex;
            flex-direction: column;

            gap: 13px;

            position: relative;
            z-index: 2;
        }

        .brand-feature {

            display: flex;
            align-items: center;

            gap: 12px;

            color:
                rgba(255,255,255,0.90);

            font-size: 0.83rem;
        }

        .feature-check {

            width: 24px;
            height: 24px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.16);

            font-size: 0.72rem;
        }


        /* =====================================================
           RIGHT FORM PANEL
        ===================================================== */

        .form-panel {

            padding: 50px 55px;

            background: white;
        }


        /* =====================================================
           FORM HEADER
        ===================================================== */

        .form-header {
            margin-bottom: 28px;
        }

        .form-header .small-title {

            color: var(--primary);

            text-transform: uppercase;

            font-size: 0.72rem;

            font-weight: 800;

            letter-spacing: 1.6px;

            margin-bottom: 9px;
        }

        .form-header h1 {

            color: var(--dark);

            font-size: 2rem;

            font-weight: 800;

            letter-spacing: -0.7px;

            margin-bottom: 7px;
        }

        .form-header p {

            color: var(--muted);

            font-size: 0.86rem;
        }


        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {

            display: flex;
            align-items: center;

            gap: 10px;

            padding: 12px 14px;

            border-radius: 11px;

            margin-bottom: 20px;

            font-size: 0.82rem;
        }

        .alert-icon {

            width: 24px;
            height: 24px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            flex-shrink: 0;

            font-weight: 800;
        }

        .error-alert {

            color: var(--danger);

            background: #fff3f1;

            border:
                1px solid
                #f2d4d0;
        }

        .error-alert .alert-icon {
            background: #f7d9d5;
        }

        .success-alert {

            color: var(--success);

            background: #effaf5;

            border:
                1px solid
                #ccebdd;
        }

        .success-alert .alert-icon {
            background: #d3f1e1;
        }


        /* =====================================================
           FORM GRID
        ===================================================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 17px 16px;
        }

        .full-width {
            grid-column: 1 / -1;
        }


        /* =====================================================
           FORM GROUP
        ===================================================== */

        .form-group {
            margin-bottom: 2px;
        }

        .form-group label {

            display: block;

            color: #355a46;

            font-size: 0.79rem;

            font-weight: 700;

            margin-bottom: 7px;
        }


        /* =====================================================
           INPUT
        ===================================================== */

        .input-wrapper {
            position: relative;
        }

        .input-icon {

            position: absolute;

            left: 13px;
            top: 50%;

            transform:
                translateY(-50%);

            width: 27px;
            height: 27px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            background: #edf8f1;

            color: var(--primary);

            font-size: 0.82rem;
            font-weight: 800;

            pointer-events: none;
        }

        .input-wrapper input {

            width: 100%;

            height: 48px;

            padding:
                0 43px;

            border:
                1.5px solid
                var(--border);

            border-radius: 11px;

            outline: none;

            background: #fbfdfc;

            color: var(--dark);

            font-size: 0.82rem;

            transition:
                0.25s ease;
        }

        .input-wrapper input::placeholder {
            color: #a5b5ae;
        }

        .input-wrapper input:hover {
            border-color: #b9d8c8;
        }

        .input-wrapper input:focus {

            background: white;

            border-color:
                var(--primary);

            box-shadow:
                0 0 0 4px
                rgba(8,127,140,0.08);
        }


        /* =====================================================
           SHOW PASSWORD
        ===================================================== */

        .show-password {

            position: absolute;

            right: 10px;
            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: transparent;

            color: var(--primary);

            font-size: 0.72rem;

            font-weight: 700;

            cursor: pointer;
        }

        .show-password:hover {
            color: var(--primary-dark);
        }


        /* =====================================================
           HELP
        ===================================================== */

        .input-help {

            display: block;

            margin-top: 5px;

            color: #93a2a5;

            font-size: 0.68rem;
        }


        /* =====================================================
           TERMS
        ===================================================== */

        .terms {

            margin:
                19px 0 20px;
        }

        .terms label {

            display: flex;
            align-items: flex-start;

            gap: 9px;

            color: #728883;

            font-size: 0.76rem;

            line-height: 1.5;

            cursor: pointer;
        }

        .terms input {

            width: 16px;
            height: 16px;

            margin-top: 1px;

            accent-color:
                var(--primary);

            flex-shrink: 0;
        }

        .terms a {

            color: var(--primary);

            font-weight: 700;
        }


        /* =====================================================
           REGISTER BUTTON
        ===================================================== */

        .register-button {

            width: 100%;

            height: 51px;

            display: flex;
            align-items: center;
            justify-content: center;

            gap: 10px;

            border: none;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--green)
                );

            color: white;

            font-size: 0.87rem;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 10px 23px
                rgba(8, 140, 74, 0.2);

            transition:
                0.25s ease;
        }

        .register-button span {

            font-size: 1.15rem;

            transition:
                transform 0.25s ease;
        }

        .register-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 15px 30px
                rgba(8, 140, 59, 0.25);
        }

        .register-button:hover span {
            transform:
                translateX(4px);
        }


        /* =====================================================
           BOTTOM LINKS
        ===================================================== */

        .login-text {

            text-align: center;

            margin-top: 21px;

            color: #939c8a;

            font-size: 0.78rem;
        }

        .login-text a {

            color: var(--primary);

            font-weight: 700;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        .back-home {

            display: block;

            text-align: center;

            margin-top: 14px;

            color: #7f907d;

            font-size: 0.75rem;

            transition:
                0.2s ease;
        }

        .back-home:hover {
            color: var(--primary);
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .register-container {

                max-width: 650px;

                grid-template-columns:
                    1fr;
            }

            .brand-panel {

                padding: 35px;

                min-height: 300px;
            }

            .brand-content {

                margin-top: 35px;
            }

            .brand-content h2 {

                font-size: 2rem;
            }

            .medical-symbol {
                display: none;
            }

            .brand-features {
                margin-top: 30px;
            }

            .form-panel {
                padding: 40px;
            }
        }


        @media (max-width: 600px) {

            .page {
                padding: 20px 12px;
            }

            .register-container {
                border-radius: 20px;
            }

            .brand-panel {
                padding: 30px 25px;
            }

            .brand-content h2 {
                font-size: 1.8rem;
            }

            .form-panel {
                padding: 32px 23px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .full-width {
                grid-column: auto;
            }

            .form-header h1 {
                font-size: 1.65rem;
            }
        }

    </style>

</head>


<body>


<div class="page">

    <!-- Decorative background -->

    <div class="circle circle-one"></div>
    <div class="circle circle-two"></div>


    <div class="register-container">


        <!-- =================================================
             LEFT BRAND PANEL
        ================================================== -->

        <section class="brand-panel">


            <!-- ACTUAL PULSEMED LOGO -->

            <a href="index.php"
               class="brand-logo">

                <img
                    src="assets/images/pulsemed-logo.png"
                    alt="PulseMed Essential Logo"
                    class="brand-logo-image"
                >

                PulseMed
                <span>Essential</span>

            </a>


            <div class="brand-content">

                <h2>

                    Your Health.
                    <br>

                    <span>Our Essential.</span>

                </h2>


                <p>

                    Create your PulseMed Essential account
                    and enjoy a convenient way to access
                    quality medical supplies.

                </p>


                <!-- ACTUAL PULSEMED LOGO -->

                <div class="medical-symbol">

                    <img
                        src="assets/images/pulsemed-logo.png"
                        alt="PulseMed Essential"
                    >

                </div>


                <div class="brand-features">

                    <div class="brand-feature">

                        <span class="feature-check">
                            ✓
                        </span>

                        Quality medical supplies

                    </div>


                    <div class="brand-feature">

                        <span class="feature-check">
                            ✓
                        </span>

                        Convenient online ordering

                    </div>


                    <div class="brand-feature">

                        <span class="feature-check">
                            ✓
                        </span>

                        Affordable healthcare products

                    </div>

                </div>

            </div>


        </section>


        <!-- =================================================
             RIGHT REGISTRATION FORM
        ================================================== -->

        <section class="form-panel">


            <div class="form-header">

                <div class="small-title">
                    Create Account
                </div>

                <h1>
                    Join PulseMed Essential
                </h1>

                <p>
                    Enter your details to create your account.
                </p>

            </div>


            <!-- ERROR MESSAGE -->

            <?php if (!empty($error)): ?>

                <div class="alert error-alert">

                    <span class="alert-icon">
                        !
                    </span>

                    <span>
                        <?php
                        echo htmlspecialchars($error);
                        ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- SUCCESS MESSAGE -->

            <?php if (!empty($success)): ?>

                <div class="alert success-alert">

                    <span class="alert-icon">
                        ✓
                    </span>

                    <span>
                        <?php
                        echo htmlspecialchars($success);
                        ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- REGISTRATION FORM -->

            <form method="POST"
                  action="register.php">


                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-group full-width">

                        <label for="name">
                            Full Name
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                ●
                            </span>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                placeholder="Enter your full name"
                                value="<?php
                                echo htmlspecialchars(
                                    $_POST['name'] ?? ''
                                );
                                ?>"
                                autocomplete="name"
                                required
                            >

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                @
                            </span>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="example@gmail.com"
                                value="<?php
                                echo htmlspecialchars(
                                    $_POST['email'] ?? ''
                                );
                                ?>"
                                autocomplete="email"
                                required
                            >

                        </div>

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                #
                            </span>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                placeholder="07XXXXXXXX"
                                maxlength="10"
                                value="<?php
                                echo htmlspecialchars(
                                    $_POST['phone'] ?? ''
                                );
                                ?>"
                                autocomplete="tel"
                                required
                            >

                        </div>

                        <small class="input-help">
                            Enter exactly 10 digits.
                        </small>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                *
                            </span>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword(
                                    'password',
                                    this
                                )"
                            >
                                Show
                            </button>

                        </div>

                        <small class="input-help">
                            Minimum 8 characters.
                        </small>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                *
                            </span>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm your password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword(
                                    'confirm_password',
                                    this
                                )"
                            >
                                Show
                            </button>

                        </div>

                    </div>


                </div>


                <!-- TERMS -->

                <div class="terms">

                    <label>

                        <input
                            type="checkbox"
                            name="terms"
                            <?php
                            echo isset($_POST['terms'])
                                ? 'checked'
                                : '';
                            ?>
                        >

                        <span>

                            I agree to the
                            <a href="#">
                                Terms and Conditions
                            </a>
                            of PulseMed Essential.

                        </span>

                    </label>

                </div>


                <!-- REGISTER BUTTON -->

                <button
                    type="submit"
                    class="register-button"
                >

                    Create Account

                    <span>
                        →
                    </span>

                </button>


            </form>


            <!-- LOGIN -->

            <div class="login-text">

                Already have an account?

                <a href="login.php">
                    Sign In
                </a>

            </div>


            <!-- HOME -->

            <a href="index.php"
               class="back-home">

                ← Back to Home

            </a>


        </section>


    </div>

</div>


<script>

/* =========================================================
   SHOW / HIDE PASSWORD
========================================================= */

function togglePassword(fieldId, button) {

    const field =
        document.getElementById(fieldId);

    if (field.type === "password") {

        field.type = "text";

        button.textContent = "Hide";

    } else {

        field.type = "password";

        button.textContent = "Show";
    }
}


/* =========================================================
   PHONE NUMBER
   Allow numbers only
========================================================= */

const phone =
    document.getElementById("phone");

if (phone) {

    phone.addEventListener(
        "input",
        function () {

            this.value =
                this.value.replace(/\D/g, "");

        }
    );
}

</script>


</body>
</html>