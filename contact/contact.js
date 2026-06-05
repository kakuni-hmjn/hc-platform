document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".contact-form");
    const submit = document.querySelector(".contact-submit");

    if (!form || !submit) return;

    form.addEventListener("submit", () => {
        submit.disabled = true;
        submit.textContent = "送信中...";
    });
});