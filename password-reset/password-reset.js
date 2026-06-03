document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".auth-form");
    const submit = document.querySelector(".auth-submit");

    if (!form || !submit) return;

    form.addEventListener("submit", () => {
        submit.disabled = true;
        submit.textContent = "処理中...";
    });
});