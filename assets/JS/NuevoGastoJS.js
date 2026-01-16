(() => {
    const form = document.getElementById("formGasto");
    if (!form) return;

    const categoria = document.getElementById("categoria");
    const metodo = document.getElementById("metodo_pago");
    const detalle = document.getElementById("detalle");
    const monto = document.getElementById("monto");
    const fecha = document.getElementById("fecha");
    const notas = document.getElementById("notas");

    const btnGuardar = document.getElementById("btnGuardar");
    const btnLimpiar = document.getElementById("btnLimpiar");

    // Preview
    const pvCategoria = document.getElementById("pvCategoria");
    const pvMetodo = document.getElementById("pvMetodo");
    const pvDetalle = document.getElementById("pvDetalle");
    const pvMonto = document.getElementById("pvMonto");
    const pvFecha = document.getElementById("pvFecha");
    const pvNotas = document.getElementById("pvNotas");

    const fmtCRC = (n) => {
        const v = isFinite(n) ? n : 0;
        return "₡ " + v.toLocaleString("es-CR", { maximumFractionDigits: 2 });
    };

    const normalizeNumber = (s) => {
        if (!s) return 0;
        const clean = String(s).replace(/[₡\s,]/g, "");
        const num = Number(clean);
        return isFinite(num) ? num : 0;
    };

    const updatePreview = () => {
        pvCategoria.textContent = categoria.value || "Categoría";
        pvMetodo.textContent = metodo.value || "Método";
        pvDetalle.textContent = detalle.value?.trim() || "Detalle del gasto";

        const num = normalizeNumber(monto.value);
        pvMonto.textContent = fmtCRC(num);

        pvFecha.textContent = fecha.value ? fecha.value.split("-").reverse().join("/") : "—";
        pvNotas.textContent = notas.value?.trim() ? notas.value.trim() : "Sin notas";
    };

    [categoria, metodo, detalle, monto, fecha, notas].forEach(el => {
        el?.addEventListener("input", updatePreview);
        el?.addEventListener("change", updatePreview);
    });

    // Set hoy por defecto
    if (fecha && !fecha.value) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, "0");
        const dd = String(today.getDate()).padStart(2, "0");
        fecha.value = `${yyyy}-${mm}-${dd}`;
    }
    updatePreview();

    const setLoading = (on) => {
        const txt = btnGuardar.querySelector(".txt");
        const load = btnGuardar.querySelector(".load");
        btnGuardar.disabled = on;

        if (on) {
            txt.classList.add("d-none");
            load.classList.remove("d-none");
        } else {
            txt.classList.remove("d-none");
            load.classList.add("d-none");
        }
    };

    form.addEventListener("submit", (e) => {
        // Validación Bootstrap
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add("was-validated");
            return;
        }

        // Monto válido
        const num = normalizeNumber(monto.value);
        if (num <= 0) {
            e.preventDefault();
            e.stopPropagation();
            monto.setCustomValidity("invalid");
            form.classList.add("was-validated");
            return;
        } else {
            monto.setCustomValidity("");
        }

        // Si todo ok -> loader y enviar
        setLoading(true);
    });

    btnLimpiar?.addEventListener("click", () => {
        form.reset();
        form.classList.remove("was-validated");
        // volver a hoy
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, "0");
        const dd = String(today.getDate()).padStart(2, "0");
        fecha.value = `${yyyy}-${mm}-${dd}`;
        updatePreview();
        detalle?.focus();
    });
})();
