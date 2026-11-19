<?php
session_start();

require_once "database.php";

$error = "";
$email = "";

/*
|--------------------------------------------------------------------------
| REDIRECT ALREADY LOGGED-IN ADMINISTRATORS
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION["user_id"]) &&
    isset($_SESSION["user_role"]) &&
    $_SESSION["user_role"] === "admin"
) {
    header("Location: admin/dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email address and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | FIND USER
        |--------------------------------------------------------------------------
        */

        $sql = "SELECT id, name, email, password, role
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Unable to process login. Please try again.";

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {

                $user = $result->fetch_assoc();

                $stored_password = $user["password"];
                $password_valid = false;
                $needs_password_upgrade = false;

                /*
                |--------------------------------------------------------------------------
                | VERIFY PASSWORD
                |--------------------------------------------------------------------------
                */

                if (password_verify($password, $stored_password)) {

                    $password_valid = true;

                    if (password_needs_rehash(
                        $stored_password,
                        PASSWORD_DEFAULT
                    )) {
                        $needs_password_upgrade = true;
                    }

                } elseif (
                    !password_get_info($stored_password)["algo"] &&
                    hash_equals($stored_password, $password)
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | SUPPORT OLD PLAIN-TEXT PASSWORDS TEMPORARILY
                    | A successful login upgrades the password to a secure hash.
                    |--------------------------------------------------------------------------
                    */

                    $password_valid = true;
                    $needs_password_upgrade = true;
                }

                /*
                |--------------------------------------------------------------------------
                | NORMALIZE ROLE
                |--------------------------------------------------------------------------
                */

                $role = strtolower(trim($user["role"] ?? ""));

                if (
                    $role === "administrator" ||
                    $role === "adminstrator"
                ) {
                    $role = "admin";
                }

                /*
                |--------------------------------------------------------------------------
                | ALLOW ADMINISTRATORS ONLY
                |--------------------------------------------------------------------------
                */

                if ($password_valid && $role === "admin") {

                    /*
                    |--------------------------------------------------------------------------
                    | UPGRADE PASSWORD STORAGE WHEN NECESSARY
                    |--------------------------------------------------------------------------
                    */

                    if ($needs_password_upgrade) {

                        $new_hash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $update = $conn->prepare(
                            "UPDATE users SET password = ? WHERE id = ?"
                        );

                        if ($update) {
                            $update->bind_param(
                                "si",
                                $new_hash,
                                $user["id"]
                            );

                            $update->execute();
                            $update->close();
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | START ADMIN SESSION
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = (int) $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = "admin";

                    $stmt->close();

                    header("Location: admin/dashboard.php");
                    exit();

                } elseif ($password_valid && $role !== "admin") {

                    $error = "Customer accounts are not required. Please visit our shop to browse products and place your order.";

                } else {

                    $error = "Incorrect email address or password.";
                }

            } else {

                $error = "Incorrect email address or password.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrator Login | PulseMed Essentials</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #eef8f6, #e8f1ff);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .login-container {
            width: 100%;
            max-width: 1050px;
            min-height: 600px;
            background: #ffffff;
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(18, 63, 70, 0.12);
        }

        .welcome-panel {
            background: linear-gradient(145deg, #066873, #087f8c, #24a77b);
            color: #ffffff;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .welcome-panel h1 {
            font-size: 35px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 20px;
        }

        .welcome-panel p {
            font-size: 15px;
            line-height: 1.9;
            color: rgba(255, 255, 255, 0.9);
        }

        .welcome-badge {
            display: inline-block;
            width: fit-content;
            padding: 9px 15px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 28px;
        }

        .welcome-footer {
            margin-top: 45px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-panel {
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .brand-name {
            font-size: 23px;
            font-weight: 800;
            color: #123f46;
            margin-bottom: 5px;
        }

        .brand-description {
            font-size: 12px;
            color: #70818a;
        }

        .form-title {
            font-size: 27px;
            font-weight: 800;
            color: #123f46;
            margin-bottom: 8px;
        }

        .form-subtitle {
            color: #718096;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .form-label {
            color: #29434a;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .form-control {
            height: 51px;
            border: 1px solid #dce7e8;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 13px;
        }

        .form-control:focus {
            border-color: #087f8c;
            box-shadow: 0 0 0 3px rgba(8, 127, 140, 0.1);
        }

        .input-group .form-control {
            border-radius: 10px 0 0 10px;
        }

        .password-toggle {
            border: 1px solid #dce7e8;
            border-left: none;
            background: #ffffff;
            border-radius: 0 10px 10px 0;
            padding: 0 15px;
            color: #087f8c;
            font-size: 12px;
            font-weight: 700;
        }

        .login-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 15px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            background: linear-gradient(135deg, #087f8c, #24a77b);
            margin-top: 10px;
            transition: 0.2s;
        }

        .login-btn:hover {
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(8, 127, 140, 0.2);
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        .security-note {
            background: #f0f9f6;
            color: #087f8c;
            padding: 13px;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.7;
            margin-top: 22px;
        }

        .bottom-links {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            line-height: 2;
        }

        .bottom-links a {
            color: #087f8c;
            text-decoration: none;
            font-weight: 700;
        }

        .bottom-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .login-container {
                grid-template-columns: 1fr;
                max-width: 480px;
            }

            .welcome-panel {
                padding: 35px 28px;
            }

            .welcome-panel h1 {
                font-size: 27px;
            }

            .welcome-footer {
                margin-top: 20px;
            }

            .form-panel {
                padding: 35px 25px;
            }
        }
    </style>

</head>

<body>

<div class="login-container">

    <!-- WELCOME PANEL -->

    <div class="welcome-panel">

        <span class="welcome-badge">
            ADMINISTRATOR ACCESS
        </span>

        <h1>
            Welcome Back to PulseMed Essentials
        </h1>

        <p>
            Sign in to manage medical supplies, monitor stock levels,
            review customer orders and access your business reports
            from one secure dashboard.
        </p>

        <p>
            Customers can browse available products and prices and
            prepare their orders without creating an account.
        </p>

        <div class="welcome-footer">
            &copy; <?php echo date("Y"); ?>
            PulseMed Essentials. All rights reserved.
        </div>

    </div>


    <!-- LOGIN FORM -->

    <div class="form-panel">

        <div class="logo-section">

            <img
                src="assets/images/pulsemed-logo.png"
                alt="PulseMed Essentials Logo"
                class="logo"
            >

            <div class="brand-name">
                PulseMed Essentials
            </div>

            <div class="brand-description">
                Medical Supplies and Healthcare Solutions
            </div>

        </div>


        <h2 class="form-title">
            Administrator Login
        </h2>

        <p class="form-subtitle">
            Sign in securely to manage products, orders, stock levels
            and sales reports.
        </p>


        <?php if (!empty($error)): ?>

            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="login.php">

            <div class="mb-3">

                <label for="email" class="form-label">
                    Administrator Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="Enter your administrator email"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="mb-3">

                <label for="password" class="form-label">
                    Password
                </label>

                <div class="input-group">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                    >
                        SHOW
                    </button>

                </div>

            </div>


            <button type="submit" class="login-btn">
                Sign In
            </button>

        </form>

        <div class="bottom-links">

            <a href="shop.php">
                Browse Medical Products
            </a>

            <br>

            <a href="index.php">
                &larr; Back to Homepage
            </a>

        </div>

    </div>

</div>


<script>
function togglePassword() {

    const passwordInput = document.getElementById("password");
    const toggleButton = document.querySelector(".password-toggle");

    if (passwordInput.type === "password") {

        passwordInput.type = "text";
        toggleButton.textContent = "HIDE";

    } else {

        passwordInput.type = "password";
        toggleButton.textContent = "SHOW";

    }
}
</script>

</body>
</html>
