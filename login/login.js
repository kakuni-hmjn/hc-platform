document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".auth-form");
    const submit = document.querySelector(".auth-submit");
    const toggles = document.querySelectorAll(".password-toggle");

    toggles.forEach((button) => {
        button.addEventListener("click", () => {
            const targetId = button.dataset.target;
            const input = document.getElementById(targetId);

            if (!input) return;

            const isPassword = input.type === "password";

            input.type = isPassword ? "text" : "password";
            button.textContent = isPassword ? "非表示" : "表示";
            button.setAttribute(
                "aria-label",
                isPassword ? "パスワードを非表示" : "パスワードを表示"
            );
        });
    });

    if (form && submit) {
        form.addEventListener("submit", () => {
            submit.disabled = true;
            submit.textContent = "ログイン中...";
        });
    }
});