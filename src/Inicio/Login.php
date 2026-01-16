<?php
session_start();

// Si ya está logueado, redirigir (ajustá cuando exista tu inicio real)
if (isset($_SESSION['fas_user'])) {
    header("Location: ../inicio/inicio.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAS | Iniciar sesión</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Tu CSS -->
    <link rel="stylesheet" href="../../assets/CSS/Logincss.css" />
</head>

<body>
    <main class="login-wrap">
        <section class="login-left">
            <div class="login-box">
                <div class="brand">
                    <div class="brand-icon"><i class="bi bi-piggy-bank"></i></div>
                    <div>
                        <div class="brand-name">FAS</div>
                        <div class="brand-sub">Family Accounting System</div>
                    </div>
                </div>

                <h1 class="title">Login</h1>
                <p class="subtitle">Inicia sesión en tu cuenta.</p>

                <div id="alertBox" class="alert d-none" role="alert"></div>

                <form id="loginForm" action="../acciones/login.php" method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="correo">Correo electrónico</label>
                        <input type="email" class="form-control form-control-lg" id="correo" name="correo"
                            placeholder="correo@ejemplo.com" autocomplete="email" required />
                        <div class="invalid-feedback">Ingresá un correo válido.</div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <label class="form-label mb-0" for="clave">Contraseña</label>
                            <a href="#" class="link-reset" id="resetLink">¿Restablecer contraseña?</a>
                        </div>

                        <div class="pass-wrap mt-2">
                            <input type="password" class="form-control form-control-lg" id="clave" name="clave"
                                placeholder="••••••••" autocomplete="current-password" minlength="6" required />
                            <button type="button" class="pass-toggle" id="togglePass"
                                aria-label="Mostrar u ocultar contraseña">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div class="invalid-feedback">Ingresá tu contraseña (mínimo 6 caracteres).</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between my-3">
                        <label class="check">
                            <input type="checkbox" id="rememberMe" />
                            <span>Recordarme</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 btn-login" id="btnLogin">
                        <span class="btn-text">Iniciar sesión</span>
                        <span class="btn-loader d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Verificando...
                        </span>
                    </button>

                    <p class="hint mt-4">
                        ¿No tenés cuenta? <a href="#" class="link-join" id="joinLink">Unite a FAS.</a>
                    </p>
                </form>
            </div>
        </section>

        <section class="login-right" aria-hidden="true">
            <div class="right-overlay"></div>

            <div class="right-content">
                <h2 class="right-title" id="slideTitle">
                    Gestioná <span class="accent">las finanzas familiares</span> desde cualquier lugar.
                </h2>
                <p class="right-sub" id="slideSub">
                    Ingresos, gastos diarios y reportes claros en un solo sistema.
                </p>

                <div class="dots" id="dots">
                    <button class="dot active" type="button" aria-label="Slide 1"></button>
                    <button class="dot" type="button" aria-label="Slide 2"></button>
                    <button class="dot" type="button" aria-label="Slide 3"></button>
                </div>
            </div>
        </section>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Tu JS -->
    <script src="../../assets/JS/LoginJS.js"></script>
</body>

</html>