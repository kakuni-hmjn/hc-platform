document.addEventListener("DOMContentLoaded", () => {
    const header = document.querySelector(".site-header");
    const menuButton = document.getElementById("menuButton");
    const mobileNav = document.getElementById("mobileNav");
    const themeToggle = document.getElementById("themeToggle");
    const revealTargets = document.querySelectorAll(".reveal");

    const updateHeader = () => {
        if (!header) return;
        header.classList.toggle("scrolled", window.scrollY > 16);
    };

    updateHeader();
    window.addEventListener("scroll", updateHeader);

    if (menuButton && mobileNav) {
        menuButton.addEventListener("click", () => {
            mobileNav.classList.toggle("open");
        });

        mobileNav.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                mobileNav.classList.remove("open");
            });
        });
    }

    const updateThemeButton = () => {
        if (!themeToggle) return;

        const isDark = document.body.classList.contains("dark-theme");
        const icon = themeToggle.querySelector(".theme-icon");
        const text = themeToggle.querySelector(".theme-text");

        if (icon) icon.textContent = isDark ? "☀️" : "🌙";
        if (text) text.textContent = isDark ? "Light" : "Dark";
    };

    if (localStorage.getItem("hc-theme") === "dark") {
        document.body.classList.add("dark-theme");
    }

    updateThemeButton();

    if (themeToggle) {
        themeToggle.addEventListener("click", () => {
            document.body.classList.toggle("dark-theme");
            const isDark = document.body.classList.contains("dark-theme");
            localStorage.setItem("hc-theme", isDark ? "dark" : "light");
            updateThemeButton();
        });
    }

    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("visible");
                }
            });
        }, { threshold: 0.12 });

        revealTargets.forEach((target) => observer.observe(target));
    } else {
        revealTargets.forEach((target) => target.classList.add("visible"));
    }
});
