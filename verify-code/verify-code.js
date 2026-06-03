document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("code");
    const form = document.querySelector(".auth-form");
    const submit = document.querySelector(".auth-submit");

    if (input) {
        input.addEventListener("input", () => {
            input.value = input.value.replace(/[^0-9]/g, "").slice(0, 6);
        });
    }

    if (form && submit) {
        form.addEventListener("submit", () => {
            submit.disabled = true;
            submit.textContent = "認証中...";
        });
    }
});