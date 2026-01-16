(() => {
  const form = document.getElementById("loginForm");
  const email = document.getElementById("correo");
  const pass = document.getElementById("clave");
  const togglePass = document.getElementById("togglePass");
  const btnLogin = document.getElementById("btnLogin");
  const alertBox = document.getElementById("alertBox");

  const resetLink = document.getElementById("resetLink");
  const joinLink = document.getElementById("joinLink");

  // Slider
  const slideTitle = document.getElementById("slideTitle");
  const slideSub = document.getElementById("slideSub");
  const dotsWrap = document.getElementById("dots");
  const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll(".dot")) : [];

  const slides = [
    {
      title: `Gestioná <span class="accent">las finanzas familiares</span> desde cualquier lugar.`,
      sub: `Ingresos, gastos diarios y reportes claros en un solo sistema.`
    },
    {
      title: `Controlá <span class="accent">gastos diarios</span> sin enredos.`,
      sub: `Compras del súper, snacks, transporte, recargas… todo queda registrado.`
    },
    {
      title: `Tomá decisiones con <span class="accent">reportes mensuales</span>.`,
      sub: `Visualizá en qué se va la plata y ajustá el presupuesto con tiempo.`
    }
  ];

  let slideIndex = 0;
  let timer = null;

  const showAlert = (type, msg) => {
    alertBox.className = `alert alert-${type} d-flex align-items-center gap-2`;
    alertBox.innerHTML = `<i class="bi bi-info-circle-fill"></i><div>${msg}</div>`;
    alertBox.classList.remove("d-none");
  };

  const hideAlert = () => alertBox.classList.add("d-none");

  const setLoading = (on) => {
    const text = btnLogin.querySelector(".btn-text");
    const loader = btnLogin.querySelector(".btn-loader");
    btnLogin.disabled = on;

    if (on) {
      text.classList.add("d-none");
      loader.classList.remove("d-none");
    } else {
      text.classList.remove("d-none");
      loader.classList.add("d-none");
    }
  };

  // Toggle password
  togglePass?.addEventListener("click", () => {
    const isPass = pass.type === "password";
    pass.type = isPass ? "text" : "password";
    togglePass.innerHTML = isPass ? `<i class="bi bi-eye-slash"></i>` : `<i class="bi bi-eye"></i>`;
  });

  // Slider functions
  const renderSlide = (i) => {
    slideIndex = i;
    if (slideTitle) slideTitle.innerHTML = slides[i].title;
    if (slideSub) slideSub.textContent = slides[i].sub;
    dots.forEach((d, idx) => d.classList.toggle("active", idx === i));
  };

  const nextSlide = () => {
    const i = (slideIndex + 1) % slides.length;
    renderSlide(i);
  };

  const startAuto = () => {
    if (!dots.length) return; // si está oculto (responsive)
    stopAuto();
    timer = setInterval(nextSlide, 4500);
  };

  const stopAuto = () => {
    if (timer) clearInterval(timer);
    timer = null;
  };

  dots.forEach((dot, idx) => {
    dot.addEventListener("click", () => {
      renderSlide(idx);
      startAuto();
    });
  });

  // Links (solo UI por ahora)
  resetLink?.addEventListener("click", (e) => {
    e.preventDefault();
    showAlert("info", "Luego conectamos la recuperación de contraseña.");
  });

  joinLink?.addEventListener("click", (e) => {
    e.preventDefault();
    showAlert("info", "Luego conectamos el registro de usuario/familia.");
  });

  // Submit (solo validación UI por ahora)
  form?.addEventListener("submit", async (e) => {
    hideAlert();

    if (!form.checkValidity()) {
      e.preventDefault();
      form.classList.add("was-validated");
      showAlert("warning", "Revisá los datos. Falta información.");
      return;
    }

    setLoading(true);
  })})