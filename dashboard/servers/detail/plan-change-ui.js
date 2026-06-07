document.addEventListener("DOMContentLoaded", () => {
    const changeTypeSelect = document.querySelector("#change_type");
    const planChangeForm = document.querySelector(".plan-change-form");

    if (!changeTypeSelect || !planChangeForm) {
        return;
    }

    const hasImmediateOption = Array.from(changeTypeSelect.options).some((option) => {
        return option.value === "immediate";
    });

    if (!hasImmediateOption) {
        const option = document.createElement("option");
        option.value = "immediate";
        option.textContent = "今すぐ変更する（1ヶ月分の料金が発生）";
        changeTypeSelect.appendChild(option);
    }

    let warning = planChangeForm.querySelector(".immediate-charge-warning");

    if (!warning) {
        warning = document.createElement("div");
        warning.className = "immediate-charge-warning";
        warning.hidden = true;
        warning.innerHTML = `
            <strong>今すぐ変更する場合の注意</strong>
            <p>
                今すぐプランを変更する場合、変更先プランの1ヶ月分の料金が発生します。
                申請後、管理者確認のうえで決済・プラン変更処理を行います。
            </p>
            <label class="immediate-charge-check">
                <input type="checkbox" name="immediate_charge_agreed" value="1">
                <span>今すぐ変更する場合、変更先プランの1ヶ月分の料金が発生することに同意します。</span>
            </label>
        `;

        const submitButton = planChangeForm.querySelector("button[type='submit']");
        if (submitButton) {
            planChangeForm.insertBefore(warning, submitButton);
        } else {
            planChangeForm.appendChild(warning);
        }
    }

    const checkbox = warning.querySelector("input[name='immediate_charge_agreed']");

    const updateWarning = () => {
        const isImmediate = changeTypeSelect.value === "immediate";
        warning.hidden = !isImmediate;

        if (checkbox) {
            checkbox.required = isImmediate;

            if (!isImmediate) {
                checkbox.checked = false;
            }
        }
    };

    changeTypeSelect.addEventListener("change", updateWarning);
    updateWarning();
});

/* Contract event timeline loader */
(() => {
    if (window.__hcServerEventTimelineLoaded) {
        return;
    }

    window.__hcServerEventTimelineLoaded = true;

    document.addEventListener("DOMContentLoaded", async () => {
        const detailGrid = document.querySelector(".server-detail-grid");

        if (!detailGrid) {
            return;
        }

        if (document.querySelector(".event-timeline-panel")) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const orderId = params.get("id");

        if (!orderId) {
            return;
        }

        try {
            const response = await fetch(`/dashboard/servers/detail/events-fragment.php?id=${encodeURIComponent(orderId)}`, {
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            const wrapper = document.createElement("div");
            wrapper.innerHTML = html.trim();

            const panel = wrapper.firstElementChild;

            if (!panel) {
                return;
            }

            detailGrid.insertBefore(panel, detailGrid.firstElementChild);
        } catch (error) {
            return;
        }
    });
})();
