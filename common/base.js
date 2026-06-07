document.addEventListener("DOMContentLoaded", () => {
    initHeaderScroll();
    initOldMobileMenu();
    initNewHeaderDrawer();
    initTheme();
    initReveal();
});

/* =========================
   Header Scroll
========================= */
function initHeaderScroll() {
    const header = document.querySelector(".site-header");

    const updateHeader = () => {
        if (!header) return;
        header.classList.toggle("scrolled", window.scrollY > 16);
    };

    updateHeader();
    window.addEventListener("scroll", updateHeader);
}

/* =========================
   Old Mobile Menu Support
   旧ヘッダー用の保険
========================= */
function initOldMobileMenu() {
    const menuButton = document.getElementById("menuButton");
    const mobileNav = document.getElementById("mobileNav");

    if (!menuButton || !mobileNav) return;

    menuButton.addEventListener("click", () => {
        mobileNav.classList.toggle("open");
    });

    mobileNav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => {
            mobileNav.classList.remove("open");
        });
    });
}

/* =========================
   New Header Drawer
========================= */
function initNewHeaderDrawer() {
    const menuToggle = document.getElementById("menuToggle");
    const drawer = document.getElementById("headerDrawer");
    const backdrop = document.getElementById("drawerBackdrop");
    const closeButton = document.getElementById("drawerClose");

    if (!menuToggle || !drawer || !backdrop) return;

    const openDrawer = () => {
        menuToggle.classList.add("is-open");
        drawer.classList.add("is-open");
        backdrop.classList.add("is-open");

        menuToggle.setAttribute("aria-expanded", "true");
        drawer.setAttribute("aria-hidden", "false");

        document.body.classList.add("drawer-open");
    };

    const closeDrawer = () => {
        menuToggle.classList.remove("is-open");
        drawer.classList.remove("is-open");
        backdrop.classList.remove("is-open");

        menuToggle.setAttribute("aria-expanded", "false");
        drawer.setAttribute("aria-hidden", "true");

        document.body.classList.remove("drawer-open");
    };

    menuToggle.addEventListener("click", () => {
        if (drawer.classList.contains("is-open")) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    backdrop.addEventListener("click", closeDrawer);

    document.addEventListener("click", (event) => {
    if (!drawer.classList.contains("is-open")) return;

    const clickedInsideDrawer = drawer.contains(event.target);
    const clickedMenuButton = menuToggle.contains(event.target);

    if (!clickedInsideDrawer && !clickedMenuButton) {
        closeDrawer();
    }
    });

    if (closeButton) {
        closeButton.addEventListener("click", closeDrawer);
    }

    drawer.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", closeDrawer);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeDrawer();
        }
    });
}

/* =========================
   Theme
   旧 themeToggle と 新 themeSwitch 両対応
========================= */
function initTheme() {
    const oldThemeToggle = document.getElementById("themeToggle");
    const newThemeSwitch = document.getElementById("themeSwitch");

    const savedTheme = localStorage.getItem("hc-theme");
    const prefersDark = window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches;

    const initialTheme = savedTheme || (prefersDark ? "dark" : "light");

    applyTheme(initialTheme);

    if (oldThemeToggle) {
        oldThemeToggle.addEventListener("click", () => {
            const nextTheme = document.body.classList.contains("dark-theme") ? "light" : "dark";
            applyTheme(nextTheme);
            localStorage.setItem("hc-theme", nextTheme);
        });
    }

    if (newThemeSwitch) {
        newThemeSwitch.addEventListener("click", () => {
            const nextTheme = document.body.classList.contains("dark-theme") ? "light" : "dark";
            applyTheme(nextTheme);
            localStorage.setItem("hc-theme", nextTheme);
        });
    }

    function applyTheme(theme) {
        document.body.classList.remove("light-theme", "dark-theme");

        if (theme === "dark") {
            document.body.classList.add("dark-theme");

            if (newThemeSwitch) {
                newThemeSwitch.setAttribute("aria-pressed", "true");
            }
        } else {
            document.body.classList.add("light-theme");

            if (newThemeSwitch) {
                newThemeSwitch.setAttribute("aria-pressed", "false");
            }
        }

        updateOldThemeButton();
    }

    function updateOldThemeButton() {
        if (!oldThemeToggle) return;

        const isDark = document.body.classList.contains("dark-theme");
        const icon = oldThemeToggle.querySelector(".theme-icon");
        const text = oldThemeToggle.querySelector(".theme-text");

        if (icon) icon.textContent = isDark ? "☀️" : "🌙";
        if (text) text.textContent = isDark ? "Light" : "Dark";
    }
}

/* =========================
   Reveal Animation
   visible / is-visible 両対応
========================= */
function initReveal() {
    const revealTargets = document.querySelectorAll(".reveal");

    if (!revealTargets.length) return;

    const showTarget = (target) => {
        target.classList.add("visible");
        target.classList.add("is-visible");
    };

    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    showTarget(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12,
        });

        revealTargets.forEach((target) => observer.observe(target));
    } else {
        revealTargets.forEach(showTarget);
    }
}
/* Header notification async read + outside close */
(() => {
    if (window.__hcHeaderNotificationAsyncReady) {
        return;
    }

    window.__hcHeaderNotificationAsyncReady = true;

    const notificationBox = document.querySelector(".header-notifications");

    if (!notificationBox) {
        return;
    }

    let shouldReloadOnClose = false;

    const closeNotificationBox = () => {
        if (!notificationBox.open) {
            return;
        }

        notificationBox.open = false;

        if (shouldReloadOnClose) {
            window.location.reload();
        }
    };

    const markOneAsReadInUi = (form) => {
        const itemWrap = form.closest(".notification-mini-item-wrap");

        if (itemWrap) {
            itemWrap.classList.remove("is-unread");
            itemWrap.classList.add("is-read");
        }

        form.remove();
    };

    const markPanelAsReadInUi = (form) => {
        const panel = form.closest(".notification-tab-panel");

        if (!panel) {
            return;
        }

        panel.querySelectorAll(".notification-mini-item-wrap.is-unread").forEach((item) => {
            item.classList.remove("is-unread");
            item.classList.add("is-read");
        });

        panel.querySelectorAll(".mini-read-form").forEach((readForm) => {
            readForm.remove();
        });

        const button = form.querySelector("button");
        if (button) {
            button.disabled = true;
        }
    };

    const submitReadForm = async (form) => {
        const actionInput = form.querySelector("input[name='action']");
        const action = actionInput ? actionInput.value : "";

        const submitButton = form.querySelector("button[type='submit'], button");

        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json",
                },
                credentials: "same-origin",
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || !result.ok) {
                if (submitButton) {
                    submitButton.disabled = false;
                }

                return;
            }

            shouldReloadOnClose = true;

            if (action === "mark_one") {
                markOneAsReadInUi(form);
            }

            if (
                action === "mark_all_personal" ||
                action === "mark_all_global" ||
                action === "mark_all"
            ) {
                markPanelAsReadInUi(form);
            }
        } catch (error) {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    notificationBox.addEventListener("submit", (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!form.action.includes("/dashboard/notifications/mark-read.php")) {
            return;
        }

        event.preventDefault();
        submitReadForm(form);
    });

    notificationBox.addEventListener("toggle", () => {
        if (!notificationBox.open && shouldReloadOnClose) {
            window.location.reload();
        }
    });

    document.addEventListener("click", (event) => {
        if (!notificationBox.open) {
            return;
        }

        if (notificationBox.contains(event.target)) {
            return;
        }

        closeNotificationBox();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        closeNotificationBox();
    });
})();
