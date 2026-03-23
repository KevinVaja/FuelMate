(() => {
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const loaderTitle = () => document.querySelector(".page-loader__title");
    const loaderCopy = () => document.querySelector(".page-loader__copy");

    const setLoaderMessage = (
        title = "Loading your route",
        copy = "Preparing the next screen...",
    ) => {
        const titleElement = loaderTitle();
        const copyElement = loaderCopy();

        if (titleElement) {
            titleElement.textContent = title;
        }

        if (copyElement) {
            copyElement.textContent = copy;
        }
    };

    const samePageAnchor = (url) =>
        url.pathname === window.location.pathname &&
        url.search === window.location.search &&
        Boolean(url.hash);

    const shouldTransitionLink = (link) => {
        const href = link.getAttribute("href");

        if (!href || href.startsWith("#")) {
            return false;
        }

        if (link.hasAttribute("download") || link.dataset.noTransition !== undefined) {
            return false;
        }

        if ((link.target || "").toLowerCase() === "_blank" || link.hasAttribute("data-bs-toggle")) {
            return false;
        }

        let url;

        try {
            url = new URL(href, window.location.href);
        } catch (error) {
            return false;
        }

        if (["mailto:", "tel:", "javascript:"].includes(url.protocol)) {
            return false;
        }

        if (url.origin !== window.location.origin || samePageAnchor(url)) {
            return false;
        }

        return true;
    };

    const initSidebar = () => {
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("overlay");

        window.toggleSidebar = () => {
            if (!sidebar || !overlay) {
                return;
            }

            sidebar.classList.toggle("show");
            overlay.classList.toggle("show");
        };

        if (!sidebar || !overlay) {
            return;
        }

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                sidebar.classList.remove("show");
                overlay.classList.remove("show");
            }
        });

        document.querySelectorAll(".sidebar .nav-link").forEach((link) => {
            link.addEventListener("click", () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove("show");
                    overlay.classList.remove("show");
                }
            });
        });
    };

    const elevateModals = () => {
        document.querySelectorAll(".modal").forEach((modal) => {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    };

    const revealTargets = () => {
        const selectors = [
            "[data-reveal]",
            ".page-content > *",
            ".hero-grid > *",
            ".section-intro",
            ".hero-panel",
            ".feature-card",
            ".service-card",
            ".process-card",
            ".quote-card",
            ".info-card",
            ".contact-form-card",
            ".page-hero__content",
            ".page-hero__card",
            ".auth-spotlight",
            ".auth-container",
            ".auth-card",
            ".stat-card",
            ".dashboard-chart-card",
            ".quick-link-card",
            ".table-responsive",
            ".page-content .card",
        ];

        return Array.from(new Set(
            selectors.flatMap((selector) => Array.from(document.querySelectorAll(selector))),
        )).filter((element) => !element.closest(".modal"));
    };

    const initScrollReveal = () => {
        const targets = revealTargets();

        if (!targets.length) {
            return;
        }

        targets.forEach((element, index) => {
            if (!element.hasAttribute("data-reveal")) {
                const revealMode = element.classList.contains("hero-panel") || element.classList.contains("auth-spotlight")
                    ? "scale"
                    : "up";

                element.setAttribute("data-reveal", revealMode);
            }

            element.style.setProperty("--reveal-delay", `${Math.min(index % 6, 5) * 70}ms`);

            if (prefersReducedMotion || !("IntersectionObserver" in window)) {
                element.setAttribute("data-reveal-state", "visible");
            } else {
                element.setAttribute("data-reveal-state", "pending");
            }
        });

        if (prefersReducedMotion || !("IntersectionObserver" in window)) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.setAttribute("data-reveal-state", "visible");
                    observer.unobserve(entry.target);
                });
            },
            {
                threshold: 0.18,
                rootMargin: "0px 0px -10% 0px",
            },
        );

        targets.forEach((element) => observer.observe(element));
    };

    const initPageTransitions = () => {
        if (!prefersReducedMotion) {
            document.addEventListener("click", (event) => {
                if (
                    event.defaultPrevented ||
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey
                ) {
                    return;
                }

                const link = event.target.closest("a[href]");

                if (!link || !shouldTransitionLink(link)) {
                    return;
                }

                event.preventDefault();
                const label = (link.textContent || "").replace(/\s+/g, " ").trim();

                if (label) {
                    setLoaderMessage(`Opening ${label}`, "Loading the next page...");
                }

                document.body.classList.add("is-transitioning");

                window.setTimeout(() => {
                    window.location.assign(link.href);
                }, 280);
            });
        }

        window.addEventListener("pageshow", () => {
            document.body.classList.remove("is-transitioning");
            document.body.classList.add("is-loaded");
            setLoaderMessage();
        });
    };

    document.addEventListener("DOMContentLoaded", () => {
        setLoaderMessage();
        elevateModals();
        initSidebar();
        initScrollReveal();
        initPageTransitions();

        window.setTimeout(() => {
            document.body.classList.add("is-loaded");
        }, prefersReducedMotion ? 0 : 240);
    });
})();
