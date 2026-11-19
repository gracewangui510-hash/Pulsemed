(function () {
    "use strict";

    if (window.pulseMedAnimationsLoaded) return;
    window.pulseMedAnimationsLoaded = true;

    const style = document.createElement("style");

    style.textContent = `
        :root {
            --pm-charcoal: #172b26;
            --pm-green: #087f45;
            --pm-green-dark: #056536;
            --pm-green-light: #e8f5ed;
            --pm-white: #ffffff;
        }

        /* Subtle heading readability improvements */
        h1, h2, h3, h4, h5, h6 {
            text-rendering: optimizeLegibility;
        }

        /* Scroll reveal: elements remain visible unless JS is ready */
        .pm-reveal {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity .6s ease, transform .6s ease;
        }

        .pm-reveal.pm-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .pm-reveal-left {
            opacity: 0;
            transform: translateX(-22px);
            transition: opacity .6s ease, transform .6s ease;
        }

        .pm-reveal-left.pm-visible {
            opacity: 1;
            transform: translateX(0);
        }

        .pm-delay-1 { transition-delay: .08s; }
        .pm-delay-2 { transition-delay: .16s; }
        .pm-delay-3 { transition-delay: .24s; }
        .pm-delay-4 { transition-delay: .32s; }

        /* Restrained card hover effects */
        .card, .product-card, .service-card, .feature-box, .info-box {
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .card:hover, .product-card:hover,
        .service-card:hover, .feature-box:hover, .info-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(23, 43, 38, .12);
        }

        /* Product and content image zoom */
        .product-img-wrap img, .about-img img, .feature-img img {
            transition: transform .4s ease;
        }

        .product-img-wrap:hover img,
        .about-img:hover img,
        .feature-img:hover img {
            transform: scale(1.04);
        }

        /* Do not recolour buttons; only add a subtle interaction */
        .btn, button, a.button, input[type="submit"] {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .btn:hover, button:hover, a.button:hover,
        input[type="submit"]:hover {
            box-shadow: 0 5px 14px rgba(23, 43, 38, .14);
        }

        /* Footer links retain their original colours */
        footer a {
            transition: opacity .2s ease, padding-left .2s ease;
        }

        footer a:hover {
            opacity: .82;
        }

        /* Back-to-top control */
        #pm-back-to-top {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 1050;
            width: 44px;
            height: 44px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            background: var(--pm-green);
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity .25s ease, transform .25s ease, visibility .25s ease;
            box-shadow: 0 4px 14px rgba(23, 43, 38, .22);
        }

        #pm-back-to-top.pm-show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        #pm-back-to-top:focus-visible {
            outline: 3px solid #f2c94c;
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            .pm-reveal, .pm-reveal-left,
            .card, .product-card, .service-card,
            .feature-box, .info-box,
            .product-img-wrap img, .about-img img,
            .feature-img img, #pm-back-to-top {
                transition: none !important;
                transform: none !important;
                animation: none !important;
            }

            .pm-reveal, .pm-reveal-left {
                opacity: 1 !important;
            }
        }

        @media (max-width: 576px) {
            #pm-back-to-top {
                right: 14px;
                bottom: 14px;
            }
        }
    `;

    document.head.appendChild(style);

    function init() {
        const reduceMotion = window.matchMedia &&
            window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        /* Scroll reveal */
        if (!reduceMotion && "IntersectionObserver" in window) {
            const candidates = document.querySelectorAll(
                "main h2, main h3, main .card, main .product-card, " +
                "main .service-card, main .feature-box, main .info-box, " +
                "main .about-text, main .contact-card"
            );

            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("pm-visible");
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.12,
                rootMargin: "0px 0px -30px 0px"
            });

            candidates.forEach(function (element, index) {
                if (element.closest(".carousel, .hero, .banner")) return;

                element.classList.add(
                    element.matches("h2, h3") ? "pm-reveal-left" : "pm-reveal"
                );

                const delay = Math.min((index % 4) + 1, 4);
                element.classList.add("pm-delay-" + delay);
                observer.observe(element);
            });
        }

        /* Sticky navigation shadow */
        const nav = document.querySelector(
            "header .navbar, header nav, .navbar"
        );

        if (nav) {
            const updateNav = function () {
                nav.style.boxShadow = window.scrollY > 60
                    ? "0 4px 18px rgba(23, 43, 38, .12)"
                    : "";
            };

            window.addEventListener("scroll", updateNav, { passive: true });
            updateNav();
        }

        /* Back-to-top button */
        const backToTop = document.createElement("button");
        backToTop.id = "pm-back-to-top";
        backToTop.type = "button";
        backToTop.textContent = "↑";
        backToTop.setAttribute("aria-label", "Back to top");
        document.body.appendChild(backToTop);

        const updateBackToTop = function () {
            backToTop.classList.toggle("pm-show", window.scrollY > 400);
        };

        window.addEventListener("scroll", updateBackToTop, { passive: true });
        updateBackToTop();

        backToTop.addEventListener("click", function () {
            window.scrollTo({
                top: 0,
                behavior: reduceMotion ? "auto" : "smooth"
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
