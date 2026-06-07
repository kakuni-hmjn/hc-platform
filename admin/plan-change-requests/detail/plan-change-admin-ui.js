document.addEventListener("DOMContentLoaded", () => {
    const statusBadge = document.querySelector(".status-badge");
    const actionPanel = document.querySelector(".request-action-panel");

    if (!statusBadge || !actionPanel) {
        return;
    }

    const isApproved = statusBadge.classList.contains("status-approved");

    if (!isApproved) {
        return;
    }

    const existingForm = actionPanel.querySelector("form[data-apply-approved='1']");

    if (existingForm) {
        return;
    }

    const requestIdInput = actionPanel.querySelector("input[name='request_id']");
    const csrfInput = actionPanel.querySelector("input[name='csrf_token']");

    if (!requestIdInput || !csrfInput) {
        return;
    }

    const form = document.createElement("form");
    form.method = "post";
    form.action = "/admin/plan-change-requests/detail/action.php";
    form.className = "action-form approved-apply-form";
    form.dataset.applyApproved = "1";

    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="${csrfInput.value}">
        <input type="hidden" name="request_id" value="${requestIdInput.value}">
        <input type="hidden" name="action" value="apply_approved">

        <label for="apply_approved_note">承認済み申請の反映メモ</label>
        <textarea id="apply_approved_note" name="admin_note" rows="3" placeholder="例: 次回更新日のため契約へ反映。"></textarea>

        <button type="submit" class="action-button action-success">
            承認済みプラン変更を契約へ反映する
        </button>
    `;

    const infoBox = actionPanel.querySelector(".info-box");

    if (infoBox) {
        infoBox.insertAdjacentElement("afterend", form);
    } else {
        actionPanel.appendChild(form);
    }
});
