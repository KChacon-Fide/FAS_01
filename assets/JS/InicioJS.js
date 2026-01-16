(() => {
    const sidebar = document.getElementById("sidebar");
    const btnBurger = document.getElementById("btnBurger");
    const btnNoti = document.getElementById("btnNoti");
    const btnRefrescar = document.getElementById("btnRefrescar");

    // Sidebar toggle (mobile)
    btnBurger?.addEventListener("click", () => {
        sidebar?.classList.toggle("open");
    });

    // Close sidebar clicking outside (mobile)
    document.addEventListener("click", (e) => {
        if (!sidebar) return;
        const isMobile = window.matchMedia("(max-width: 992px)").matches;
        if (!isMobile) return;

        const clickedInsideSidebar = sidebar.contains(e.target);
        const clickedBurger = btnBurger && btnBurger.contains(e.target);
        if (!clickedInsideSidebar && !clickedBurger) sidebar.classList.remove("open");
    });

    // Notificaciones
    btnNoti?.addEventListener("click", () => {
        const canvasEl = document.getElementById("notiCanvas");
        if (!canvasEl) return;
        const oc = bootstrap.Offcanvas.getOrCreateInstance(canvasEl);
        oc.show();
    });

    // Refrescar (demo: anima la tabla)
    btnRefrescar?.addEventListener("click", () => {
        const body = document.getElementById("movimientosBody");
        if (!body) return;

        body.classList.add("pulse");
        setTimeout(() => body.classList.remove("pulse"), 450);
    });
})();
