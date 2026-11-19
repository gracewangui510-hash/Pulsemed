<?php
session_start();

/* =========================================================
   PULSEMED ESSENTIALS CONTACT DETAILS
   Replace the sample phone number with the seller's number.
========================================================= */

$contact_phone = "254769861381";
$whatsapp_number = "254769861381";
$contact_email = "pulsemedessentials1@gmail.com";

$phone_link = preg_replace('/[^0-9]/', '', $contact_phone);
$whatsapp_link = preg_replace('/[^0-9]/', '', $whatsapp_number);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PulseMed Essentials | Quality Medical Supplies</title>

    <meta
        name="description"
        content="PulseMed Essentials provides convenient access to essential medical supplies."
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        /* =====================================================
           GENERAL
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            font-family: "Inter", sans-serif;
            background: #9cb1a6;
            color: #173b35;
        }

        a {
            text-decoration: none;
        }

        .section-label {
            color: #087f55;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .section-title {
            color: #173b35;
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .section-description {
            max-width: 650px;
            margin: 0 auto 45px;
            color: #5eac96;
            line-height: 1.8;
            font-size: 15px;
        }

        .primary-button {
            display: inline-block;
            color: white;
            background: linear-gradient(135deg, #afc0ba, #889993);
            padding: 13px 23px;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            transition: 0.3s ease;
        }

        .primary-button:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(8, 127, 85, 0.20);
        }


        /* =====================================================
           NAVIGATION
        ===================================================== */

        .navbar {
            background: rgba(175, 198, 199, 0.98);
            border-bottom: 1px solid #e5efeb;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
            padding: 9px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
        }

        .nav-logo {
            width: 68px;
            height: 68px;
            object-fit: contain;
            display: block;
        }

        .navbar-nav {
            align-items: center;
        }

        .navbar-nav .nav-link {
            color: #385650;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 13px !important;
            border-radius: 8px;
            transition: 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: #087f55;
            background: #9fb4ac;
        }

        .navbar-nav .login-link {
            color: #087f55;
            border: 1px solid #087f55;
            margin-left: 8px;
        }

        .navbar-nav .register-link {
            color: white;
            background: linear-gradient(135deg, #afbbb7, #c2d3cd);
            margin-left: 8px;
        }

        .navbar-nav .register-link:hover {
            color: white;
        }


        /* =====================================================
           HERO SECTION
        ===================================================== */

        .hero {
            min-height: 620px;
            display: flex;
            align-items: center;
            padding: 75px 0;

            background:
                radial-gradient(
                    circle at 85% 20%,
                    rgba(139, 151, 147, 0.13),
                    transparent 30%
                ),
                whitesmoke;
        }

        .hero-content {
            padding-right: 20px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #9ab9ac;
            color: #a7d8d8;
            border-radius: 30px;
            padding: 9px 16px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .hero-badge-dot {
            width: 8px;
            height: 8px;
            background: #7fbcc0;
            border-radius: 50%;
        }

        .hero h1 {
            color: #173b35;
            font-size: clamp(40px, 5vw, 59px);
            line-height: 1.12;
            font-weight: 800;
            margin-bottom: 22px;
        }

        .hero h1 span {
            color: #087f55;
        }

        .hero-description {
            max-width: 580px;
            color: #9cb8af;
            font-size: 16px;
            line-height: 1.9;
            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
        }

        .secondary-button {
            display: inline-block;
            color: #d1e4dd;
            background: whitesmoke;
            border: 1px solid #cfe4db;
            padding: 12px 23px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            transition: 0.3s ease;
        }

        .secondary-button:hover {
            color: #7cbdb9;
            border-color: #87c0c2;
        }


        /* =====================================================
           HERO COMPANY LOGO
        ===================================================== */

        .hero-visual {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-card {
            width: 390px;
            height: 390px;
            background: whitesmoke;
            border: 1px solid #81bfca;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            box-shadow: 0 25px 65px rgba(22, 77, 62, 0.12);
        }

        .hero-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }


        /* =====================================================
           TRUST STRIP
        ===================================================== */

        .trust-strip {
            background: whitesmoke;
            padding: 25px 0;
            border-bottom: 1px solid #4e8f77;
        }

        .trust-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #86d1bb;
            font-size: 14px;
            font-weight: 600;
            padding: 8px;
        }

        .trust-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #c9dad2;
            color: #087f55;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }


        /* =====================================================
           SERVICES
        ===================================================== */

        .services {
            background: whitesmoke;
            padding: 85px 0;
        }

        .service-card {
            height: 100%;
            background: #a0b9ad;
            border: 1px solid #e3eee9;
            border-radius: 17px;
            padding: 32px 25px;
            text-align: center;
            transition: 0.3s ease;
        }

        .service-card:hover {
            background: whitesmoke;
            transform: translateY(-6px);
            box-shadow: 0 17px 38px rgba(28, 81, 66, 0.09);
        }

        .service-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 20px;
            border-radius: 16px;
            background: #808b87;
            color: #087f55;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: 800;
        }

        .service-card h3 {
            color: #23483f;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .service-card p {
            color: #69bea6;
            font-size: 13px;
            line-height: 1.8;
            margin-bottom: 0;
        }


        /* =====================================================
           ABOUT
        ===================================================== */

        .about {
            background: #9ab9ae;
            padding: 85px 0;
        }

        .about-box {
            background: whitesmoke;
            border-radius: 23px;
            padding: 48px;
            box-shadow: 0 15px 45px rgba(22, 77, 62, 0.06);
        }

        .about h2 {
            color: #173b35;
            font-size: 35px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .about-text {
            color: #687b75;
            line-height: 1.9;
            font-size: 14px;
        }

        .about-point {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #405a53;
            font-size: 14px;
            font-weight: 600;
            margin-top: 17px;
        }

        .check {
            width: 25px;
            height: 25px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #bae7d4;
            color: #087f55;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .about-logo {
            width: 260px;
            height: 260px;
            object-fit: contain;
        }


        /* =====================================================
           CONTACT SECTION
        ===================================================== */

        .contact-section {
            background: whitesmoke;
            padding: 85px 0;
        }

        .contact-card {
            height: 100%;
            background: #b4dbc9;
            border: 1px solid #238353;
            border-radius: 17px;
            padding: 30px 22px;
            text-align: center;
            transition: 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 35px rgba(22, 77, 62, 0.09);
        }

        .contact-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #a7e4d0;
            color: #087f55;
            border-radius: 17px;
            font-size: 25px;
            font-weight: 800;
        }

        .contact-card h3 {
            color: #3f806f;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .contact-card p {
            color: #64ceb0;
            font-size: 13px;
            line-height: 1.8;
            min-height: 47px;
        }

        .contact-detail {
            display: block;
            color: #087f55;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: anywhere;
            margin: 12px 0 18px;
        }

        .contact-note {
            color: #55bea1;
            font-size: 12px;
            margin-top: 25px;
            line-height: 1.7;
        }


        /* =====================================================
           CALL TO ACTION
        ===================================================== */

        .cta {
            background: linear-gradient(135deg, #9aeed2, #9ce0c9);
            color: bluegreen;
            padding: 75px 0;
        }

        .cta h2 {
            font-size: 35px;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .cta p {
            max-width: 650px;
            margin: 0 auto 27px;
            font-size: 14px;
            line-height: 1.8;
            opacity: 0.92;
        }

        .cta-button {
            display: inline-block;
            background: whitesmoke;
            color: #087f55;
            padding: 13px 28px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            transition: 0.3s ease;
        }

        .cta-button:hover {
            color: #087f55;
            transform: translateY(-2px);
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #c4dbe5;
            color:teal;
            padding: 48px 0 20px;
        }

        .footer-logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
            background: teal;
            border-radius: 12px;
            padding: 3px;
            margin-bottom: 13px;
        }

        .footer-brand {
            color: teal;
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .footer-description {
            color: #53a088;
            max-width: 420px;
            font-size: 13px;
            line-height: 1.8;
        }

        .footer-title {
            color: teal;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 11px;
        }

        .footer-links a {
            color: #6da0a7;
            font-size: 13px;
            transition: 0.3s ease;
        }

        .footer-links a:hover {
            color: teal;
        }

        .copyright {
            border-top: 1px solid rgba(102, 173, 124, 0.1);
            margin-top: 32px;
            padding-top: 18px;
            text-align: center;
            color: #46b48e;
            font-size: 12px;
        }




        /* =====================================================
           MEDICAL IMAGE CAROUSEL
        ===================================================== */
        .medical-showcase {
            background: #f3fbf7;
            padding: 58px 0 68px;
        }
        .medical-carousel {
            max-width: 1180px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 24px;
            box-shadow: 0 22px 55px rgba(18, 65, 49, 0.18);
        }
        .medical-carousel .carousel-item {
            height: 430px;
            position: relative;
            background: #174d3b;
        }
        .medical-carousel .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .medical-carousel .carousel-item::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(8, 45, 35, .82) 0%, rgba(8, 45, 35, .48) 48%, rgba(8, 45, 35, .08) 100%);
        }
        .medical-carousel .carousel-caption {
            z-index: 2;
            right: auto;
            left: 8%;
            bottom: 0;
            top: 0;
            width: min(560px, 78%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            text-align: left;
            padding: 25px 0 45px;
        }
        .carousel-eyebrow {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 30px;
            background: rgba(79, 170, 155, 0.18);
            border: 1px solid rgba(255,255,255,.35);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .medical-carousel .carousel-caption h2 {
            color: #fff;
            font-size: clamp(30px, 4vw, 48px);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 16px;
            text-shadow: 0 2px 12px rgba(0,0,0,.18);
        }
        .medical-carousel .carousel-caption p {
            color: rgba(255,255,255,.92);
            font-size: 15px;
            line-height: 1.8;
            max-width: 480px;
            margin-bottom: 25px;
        }
        .carousel-shop-button {
            display: inline-block;
            padding: 13px 23px;
            border-radius: 9px;
            background: #59a2a5;
            color: #087f55;
            font-size: 13px;
            font-weight: 800;
            transition: .25s ease;
        }
        .carousel-shop-button:hover {
            color: #fff;
            background: #4fad8c;
            transform: translateY(-2px);
        }
        .medical-carousel .carousel-control-prev,
        .medical-carousel .carousel-control-next {
            z-index: 3;
            width: 7%;
        }
        .medical-carousel .carousel-indicators { z-index: 4; }
        .medical-carousel .carousel-indicators [data-bs-target] {
            width: 25px;
            height: 4px;
            border: 0;
            border-radius: 8px;
        }
        .showcase-note {
            color: #658078;
            font-size: 12px;
            margin-top: 14px;
            text-align: center;
        }


        /* =====================================================
           RESPONSIVE DESIGN
        ===================================================== */

        @media (max-width: 991px) {

            .hero {
                padding: 60px 0;
            }

            .hero-content {
                text-align: center;
                padding-right: 0;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-visual {
                margin-top: 45px;
            }

            .logo-card {
                width: 330px;
                height: 330px;
            }

            .navbar-nav {
                align-items: stretch;
                padding-top: 12px;
            }

            .navbar-nav .nav-link {
                margin: 3px 0;
            }

            .navbar-nav .login-link,
            .navbar-nav .register-link {
                margin-left: 0;
                text-align: center;
            }
        }

        @media (max-width: 576px) {

            .nav-logo {
                width: 55px;
                height: 55px;
            }

            .hero {
                padding: 45px 0;
            }

            .hero h1 {
                font-size: 37px;
            }

            .hero-description {
                font-size: 14px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-buttons a {
                text-align: center;
                width: 100%;
            }

            .logo-card {
                width: 275px;
                height: 275px;
                border-radius: 22px;
                padding: 16px;
            }

            .section-title {
                font-size: 29px;
            }

            .about-box {
                padding: 28px 20px;
            }

            .about h2 {
                font-size: 29px;
            }

            .about-logo {
                width: 215px;
                height: 215px;
            }

            .cta h2 {
                font-size: 29px;
            }

            .contact-card {
                padding: 27px 20px;
            }

            .medical-showcase {
                padding: 38px 0 45px;
            }

            .medical-carousel {
                border-radius: 16px;
            }

            .medical-carousel .carousel-item {
                height: 390px;
            }

            .medical-carousel .carousel-caption {
                left: 8%;
                width: 80%;
                padding-bottom: 38px;
            }

            .medical-carousel .carousel-caption p {
                font-size: 13px;
                line-height: 1.65;
            }

            .medical-carousel .carousel-caption h2 {
                font-size: 29px;
            }

            .medical-carousel .carousel-control-prev,
            .medical-carousel .carousel-control-next {
                width: 10%;
            }
        }

    </style>

</head>

<body>

<!-- =========================================================
     NAVIGATION
========================================================= -->

<nav class="navbar navbar-expand-lg sticky-top">

    <div class="container">

        <a class="navbar-brand" href="index.php">

            <img
                src="assets/images/pulsemed-logo.png"
                alt="PulseMed Essentials Logo"
                class="nav-logo"
            >

        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">

            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="shop.php">Shop Products</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#services">Services</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#about">About Us</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact Us</a>
                </li>

            </ul>

        </div>

    </div>

</nav>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-7">

                <div class="hero-content">

                    <div class="hero-badge">
                        <span class="hero-badge-dot"></span>
                        Your Medical Supplies Partner
                    </div>

                    <h1>
                        Quality Medical Supplies
                        <span>Made Accessible.</span>
                    </h1>

                    <p class="hero-description">

                        PulseMed Essentials provides convenient
                        access to essential medical supplies for
                        individuals, healthcare professionals and
                        institutions.

                    </p>

                    <div class="hero-buttons">

                        <a href="shop.php" class="primary-button">
                            Explore Products
                        </a>

                        <a href="#contact" class="secondary-button">
                            Contact Us
                        </a>

                    </div>

                </div>

            </div>

            <div class="col-lg-5">

                <div class="hero-visual">

                    <div class="logo-card">

                        <img
                            src="assets/images/pulsemed-logo.png"
                            alt="PulseMed Essentials Company Logo"
                            class="hero-logo"
                        >

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     ATTRACTIVE MEDICAL IMAGE CAROUSEL
========================================================= -->
<section class="medical-showcase" aria-label="Featured medical supplies">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-label">Discover PulseMed</div>
            <h2 class="section-title">Essentials for Better Care</h2>
            <p class="section-description mb-0">Explore medical equipment and everyday healthcare essentials for clinics, professionals and home care.</p>
        </div>

        <div id="pulseMedCarousel" class="carousel slide carousel-fade medical-carousel" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#pulseMedCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#pulseMedCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#pulseMedCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>

            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=1800&q=85" alt="Healthcare professional preparing medical equipment" fetchpriority="high">
                    <div class="carousel-caption">
                        <span class="carousel-eyebrow">Quality you can trust</span>
                        <h2>Medical Essentials for Every Step of Care</h2>
                        <p>Find practical healthcare supplies for clinics, healthcare professionals and everyday care needs.</p>
                        <a href="shop.php" class="carousel-shop-button">Explore Our Products &nbsp; →</a>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=1800&q=85" alt="Healthcare professional in a modern clinical setting" loading="lazy">
                    <div class="carousel-caption">
                        <span class="carousel-eyebrow">Supporting healthcare teams</span>
                        <h2>Supplies That Help You Care with Confidence</h2>
                        <p>Browse useful medical equipment and supplies with convenient access to product information and seller support.</p>
                        <a href="shop.php" class="carousel-shop-button">Browse Medical Supplies &nbsp; →</a>
                    </div>
                </div>

                <div class="carousel-item">
                    <img src="https://images.unsplash.com/photo-1584515933487-779824d29309?auto=format&fit=crop&w=1800&q=85" alt="Medical care professional ready to assist a patient" loading="lazy">
                    <div class="carousel-caption">
                        <span class="carousel-eyebrow">Care made convenient</span>
                        <h2>Your Trusted Partner for Healthcare Supplies</h2>
                        <p>Need help choosing an item? Explore available products and contact our seller for assistance with prices and stock.</p>
                        <a href="#contact" class="carousel-shop-button">Contact Us &nbsp; →</a>
                    </div>
                </div>
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#pulseMedCarousel" data-bs-slide="prev" aria-label="Previous slide">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#pulseMedCarousel" data-bs-slide="next" aria-label="Next slide">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
        </div>
        <p class="showcase-note">Images load from Unsplash, so an internet connection is needed to display these carousel pictures.</p>
    </div>
</section>


<!-- =========================================================
     TRUST STRIP
========================================================= -->

<section class="trust-strip">

    <div class="container">

        <div class="row">

            <div class="col-md-4">

                <div class="trust-item">
                    <span class="trust-icon">✓</span>
                    Quality Products
                </div>

            </div>

            <div class="col-md-4">

                <div class="trust-item">
                    <span class="trust-icon">✓</span>
                    Competitive Prices
                </div>

            </div>

            <div class="col-md-4">

                <div class="trust-item">
                    <span class="trust-icon">✓</span>
                    Convenient Ordering
                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     SERVICES
========================================================= -->

<section class="services" id="services">

    <div class="container">

        <div class="text-center">

            <div class="section-label">Our Services</div>

            <h2 class="section-title">Healthcare Made Simpler</h2>

            <p class="section-description">

                Our platform helps customers discover medical
                supplies and contact our seller for assistance.

            </p>

        </div>

        <div class="row g-4">

            <div class="col-md-4">

                <div class="service-card">

                    <div class="service-icon">✓</div>

                    <h3>Quality Products</h3>

                    <p>

                        Explore essential medical supplies for
                        healthcare, home care and professional use.

                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="service-card">

                    <div class="service-icon">K</div>

                    <h3>Affordable Prices</h3>

                    <p>

                        Browse available products and enquire
                        about prices and stock availability.

                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="service-card">

                    <div class="service-icon">→</div>

                    <h3>Easy Ordering</h3>

                    <p>

                        Contact our seller for assistance with
                        products, purchases and order enquiries.

                    </p>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     ABOUT US
========================================================= -->

<section class="about" id="about">

    <div class="container">

        <div class="about-box">

            <div class="row align-items-center">

                <div class="col-lg-7">

                    <div class="section-label">
                        About Our Company
                    </div>

                    <h2>Your Health. Our Essentials.</h2>

                    <p class="about-text">

                        PulseMed Essentials is a medical supply
                        platform focused on making essential
                        healthcare products easier to access.
                        Customers can browse products and contact
                        the seller for more information about
                        availability, prices and orders.

                    </p>

                    <div class="about-point">
                        <span class="check">✓</span>
                        Convenient access to product information
                    </div>

                    <div class="about-point">
                        <span class="check">✓</span>
                        Direct contact with our seller
                    </div>

                    <div class="about-point">
                        <span class="check">✓</span>
                        Customer-focused assistance
                    </div>

                </div>

                <div class="col-lg-5">

                    <div class="text-center mt-4 mt-lg-0">

                        <img
                            src="assets/images/pulsemed-logo.png"
                            alt="PulseMed Essentials Company Logo"
                            class="about-logo"
                        >

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     CONTACT US
========================================================= -->

<section class="contact-section" id="contact">

    <div class="container">

        <div class="text-center">

            <div class="section-label">Get In Touch</div>

            <h2 class="section-title">Contact Us</h2>

            <p class="section-description">

                Have a question about a product, price, stock
                availability or order? Contact PulseMed Essentials
                using any of the options below.

            </p>

        </div>

        <div class="row g-4 justify-content-center">

            <!-- PHONE -->

            <div class="col-md-4">

                <div class="contact-card">

                    <div class="contact-icon">☎</div>

                    <h3>Call Us</h3>

                    <p>

                        Speak to Us directly about
                        products and orders.

                    </p>

                    <?php if ($phone_link !== "254700000000" && $phone_link !== ""): ?>

                        <span class="contact-detail">
                            <?php echo htmlspecialchars($contact_phone); ?>
                        </span>

                        <a
                            class="primary-button"
                            href="tel:<?php echo htmlspecialchars($phone_link); ?>"
                        >
                            Call Now
                        </a>

                    <?php else: ?>

                        <span class="contact-detail">
                            Phone number to be added
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <!-- WHATSAPP -->

            <div class="col-md-4">

                <div class="contact-card">

                    <div class="contact-icon">W</div>

                    <h3>WhatsApp</h3>

                    <p>

                        Send a message to ask about products,
                        prices or order assistance.

                    </p>

                    <?php if ($whatsapp_link !== "254700000000" && $whatsapp_link !== ""): ?>

                        <span class="contact-detail">
                            Chat with Us
                        </span>

                        <a
                            class="primary-button"
                            href="https://wa.me/<?php echo htmlspecialchars($whatsapp_link); ?>?text=<?php echo rawurlencode('Hello PulseMed Essentials, I would like to enquire about your medical supplies.'); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Chat on WhatsApp
                        </a>

                    <?php else: ?>

                        <span class="contact-detail">
                            WhatsApp number to be added
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <!-- EMAIL -->

            <div class="col-md-4">

                <div class="contact-card">

                    <div class="contact-icon">@</div>

                    <h3>Email the Administrator</h3>

                    <p>

                        Send an enquiry about products, purchases
                        or assistance using email.

                    </p>

                    <span class="contact-detail">
                        <?php echo htmlspecialchars($contact_email); ?>
                    </span>

                    <a
                        class="primary-button"
                        href="mailto:<?php echo htmlspecialchars($contact_email); ?>?subject=<?php echo rawurlencode('PulseMed Essentials Enquiry'); ?>"
                    >
                        Send Email
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     CALL TO ACTION
========================================================= -->

<section class="cta">

    <div class="container text-center">

        <h2>Need Medical Supplies?</h2>

        <p>

            Explore PulseMed Essentials and contact Us
            for information about available products and prices.

        </p>

        <a href="shop.php" class="cta-button">
            Browse Products
        </a>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <div class="container">

        <div class="row g-4">

            <!-- COMPANY -->

            <div class="col-lg-5">

                <img
                    src="assets/images/pulsemed-logo.png"
                    alt="PulseMed Essentials Logo"
                    class="footer-logo"
                >

                <div class="footer-brand">
                    PulseMed Essentials
                </div>

                <p class="footer-description">

                    Making essential medical supplies easier
                    to discover through a convenient digital
                    platform.

                </p>

            </div>


            <!-- QUICK LINKS -->

            <div class="col-6 col-lg-3">

                <div class="footer-title">Quick Links</div>

                <ul class="footer-links">

                    <li>
                        <a href="index.php">Home</a>
                    </li>

                    <li>
                        <a href="shop.php">Shop Products</a>
                    </li>

                    <li>
                        <a href="#services">Services</a>
                    </li>

                    <li>
                        <a href="#about">About Us</a>
                    </li>

                    <li>
                        <a href="#contact">Contact Us</a>
                    </li>

                </ul>

            </div>


            <!-- ACCOUNT AND CONTACT -->

            <div class="col-6 col-lg-4">

                <div class="footer-title">Shopping & Contact</div>

                <ul class="footer-links">

                    <li>
                        <a href="shop.php">Browse Products</a>
                    </li>

                    <li>
                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                            Email Administrator
                        </a>
                    </li>

                    <li>
                        <a href="#contact">Contact Us</a>
                    </li>

                </ul>

            </div>

        </div>


        <div class="copyright">

            &copy; <?php echo date("Y"); ?>
            PulseMed Essentials. All Rights Reserved.

        </div>

    </div>

</footer>


<!-- =========================================================
     BOOTSTRAP JAVASCRIPT
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
