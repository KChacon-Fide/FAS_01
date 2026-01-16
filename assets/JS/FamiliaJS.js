(() => {
    const sidebar = document.getElementById("sidebar");
    const btnBurger = document.getElementById("btnBurger");
    btnBurger?.addEventListener("click", () => sidebar?.classList.toggle("open"));

    // Tips offcanvas
    const btnTips = document.getElementById("btnTips");
    btnTips?.addEventListener("click", () => {
        const el = document.getElementById("tipsCanvas");
        if (!el) return;
        bootstrap.Offcanvas.getOrCreateInstance(el).show();
    });

    // Quick search (tabla)
    const q = document.getElementById("quickSearch");
    const tbody = document.querySelector("#tablaMov tbody");
    const rows = tbody ? Array.from(tbody.querySelectorAll("tr")) : [];

    const applySearch = () => {
        const term = (q?.value || "").trim().toLowerCase();
        if (!rows.length) return;
        rows.forEach(tr => {
            const hay = tr.getAttribute("data-search") || "";
            tr.style.display = hay.includes(term) ? "" : "none";
        });
    };
    q?.addEventListener("input", applySearch);

    // Click "Ver" en persona -> setea filtro y envía form
    const form = document.getElementById("filtrosForm");
    const personaSel = document.getElementById("personaSel");

    document.querySelectorAll("[data-set-persona]").forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.getAttribute("data-set-persona");
            if (personaSel) personaSel.value = id;
            form?.submit();
        });
    });

    // Refrescar
    document.getElementById("btnRefrescar")?.addEventListener("click", () => location.reload());

    // Export CSV (movimientos visibles)
    const toCSV = (rows) => {
        const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
        const header = ["Tipo", "Persona", "Método", "Detalle", "Fecha", "Monto"];
        const lines = [header.map(esc).join(",")];

        rows.forEach(tr => {
            if (tr.style.display === "none") return;
            const cols = Array.from(tr.querySelectorAll("td")).map(td => td.innerText.trim());
            lines.push(cols.map(esc).join(","));
        });

        return lines.join("\n");
    };

    const download = (name, content, type) => {
        const blob = new Blob([content], { type });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
    };

    document.getElementById("btnCsv")?.addEventListener("click", () => {
        const bodyRows = Array.from(document.querySelectorAll("#tablaMov tbody tr"));
        const csv = toCSV(bodyRows);
        download("fas_movimientos.csv", csv, "text/csv;charset=utf-8");
    });

    // Export tabla personas (simple)
    document.getElementById("btnExport")?.addEventListener("click", () => {
        const data = window.FAS_FAMILIA?.personas || [];
        const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
        const header = ["Nombre", "Efectivo", "Tarjeta", "Sobres", "Disponible", "Total"];
        const lines = [header.map(esc).join(",")];
        data.forEach(p => {
            lines.push([
                p.nombre, p.efectivo, p.tarjeta, p.sobres, p.disponible, p.total
            ].map(esc).join(","));
        });
        download("fas_personas.csv", lines.join("\n"), "text/csv;charset=utf-8");
    });

})();
