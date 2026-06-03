document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("code");
    const verifyForm = document.querySelector(".auth-form");
    const verifySubmit = document.querySelector(".auth-submit");
    const resendForm = document.querySelector(".resend-form");
    const resendButton = document.querySelector(".resend-button");

    if (input) {
        input.addEventListener("input", () => {
            input.value = input.value.replace(/[^0-9]/g, "").slice(0, 6);
        });
    }

    if (verifyForm && verifySubmit) {
        verifyForm.addEventListener("submit", () => {
            verifySubmit.disabled = true;
            verifySubmit.textContent = "認証中...";
        });
    }

    if (resendForm && resendButton) {
        resendForm.addEventListener("submit", () => {
            resendButton.disabled = true;
            resendButton.textContent = "再送中...";
        });
    }
});