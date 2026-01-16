(() => {
    // Helpers
    const cleanNum = (s) => {
        if (!s) return 0;
        const x = String(s).replace(/[₡\s,]/g, "");
        const n = Number(x);
        return Number.isFinite(n) ? n : 0;
    };
    const fmt = (n) => "₡ " + Number(n || 0).toLocaleString("es-CR", { maximumFractionDigits: 2 });

    // Sidebar toggle (reusa InicioJS pero por si acaso)
    const sidebar = document.getElementById("sidebar");
    const btnBurger = document.getElementById("btnBurger");
    btnBurger?.addEventListener("click", () => sidebar?.classList.toggle("open"));

    // Noti
    const btnNoti = document.getElementById("btnNoti");
    btnNoti?.addEventListener("click", () => {
        const el = document.getElementById("notiCanvas");
        if (!el) return;
        bootstrap.Offcanvas.getOrCreateInstance(el).show();
    });

    // Bootstrap validation
    document.querySelectorAll(".needs-validation").forEach((form) => {
        form.addEventListener("submit", (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add("was-validated");
        }, false);
    });

    // Loading buttons
    const setBtnLoading = (btn, on) => {
        if (!btn) return;
        const t = btn.querySelector(".txt");
        const l = btn.querySelector(".load");
        btn.disabled = on;
        if (t && l) {
            t.classList.toggle("d-none", on);
            l.classList.toggle("d-none", !on);
        }
    };

    const hookLoading = (formId, btnId) => {
        const f = document.getElementById(formId);
        const b = document.getElementById(btnId);
        if (!f || !b) return;
        f.addEventListener("submit", () => {
            // si el navegador considera válido, dejamos loading
            if (f.checkValidity()) setBtnLoading(b, true);
        });
    };

    hookLoading("formIngreso", "btnIngreso");
    hookLoading("formTransfer", "btnTransfer");
    hookLoading("formSobre", "btnSobre");
    hookLoading("formPersona", "btnCrearPersona");

    // Autollenar selects desde cards
    document.querySelectorAll("[data-fill-persona]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const id = btn.getAttribute("data-fill-persona");
            const sel = document.getElementById("i_persona");
            if (sel) sel.value = id;
            document.getElementById("i_monto")?.focus();
            window.scrollTo({ top: document.getElementById("formIngreso").offsetTop - 40, behavior: "smooth" });
        });
    });

    document.querySelectorAll("[data-fill-transfer]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const id = btn.getAttribute("data-fill-transfer");
            const sel = document.getElementById("t_origen");
            if (sel) sel.value = id;
            document.getElementById("t_destino")?.focus();
            window.scrollTo({ top: document.getElementById("formTransfer").offsetTop - 40, behavior: "smooth" });
        });
    });

    document.querySelectorAll("[data-fill-sobre]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const id = btn.getAttribute("data-fill-sobre");
            const sel = document.getElementById("s_persona");
            if (sel) sel.value = id;
            document.getElementById("s_monto")?.focus();
            window.scrollTo({ top: document.getElementById("formSobre").offsetTop - 40, behavior: "smooth" });
        });
    });

    // Form persona: valida total = efectivo + tarjeta (cliente)
    const pTotal = document.getElementById("p_total");
    const pEfe = document.getElementById("p_efectivo");
    const pTar = document.getElementById("p_tarjeta");

    const validateSplit = () => {
        if (!pTotal || !pEfe || !pTar) return;
        const t = cleanNum(pTotal.value);
        const e = cleanNum(pEfe.value);
        const r = cleanNum(pTar.value);
        const ok = Math.round((e + r) * 100) === Math.round(t * 100);

        pTotal.setCustomValidity(ok ? "" : "invalid");
    };

    [pTotal, pEfe, pTar].forEach(el => el?.addEventListener("input", validateSplit));

})();
