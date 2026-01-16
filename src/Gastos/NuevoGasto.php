<?php
require_once __DIR__ . '/../inc/validar.php';
require_once __DIR__ . '/../config/db.php';

$nombre = $_SESSION['fas_user']['nombre'] ?? 'Usuario';
$rol = $_SESSION['fas_user']['rol'] ?? 'miembro';
$usuarioId = (int) ($_SESSION['fas_user']['id'] ?? 0);

// Personas de la familia (para asignar el gasto a alguien)
$personas = [];
$stP = $cn->prepare("SELECT id, nombre FROM personas WHERE usuario_id=? ORDER BY nombre ASC");
$stP->bind_param("i", $usuarioId);
$stP->execute();
$personas = $stP->get_result()->fetch_all(MYSQLI_ASSOC);


$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $persona_id = (int) ($_POST['persona_id'] ?? 0);
    $categoria = trim($_POST['categoria'] ?? '');
    $detalle = trim($_POST['detalle'] ?? '');
    $montoRaw = trim($_POST['monto'] ?? '');
    $fecha = trim($_POST['fecha'] ?? '');
    $metodo_pago = trim($_POST['metodo_pago'] ?? 'Efectivo');
    $notas = trim($_POST['notas'] ?? '');

    // Normalizar monto (por si viene con comas o ₡)
    $montoRaw = str_replace(['₡', ' ', ','], ['', '', ''], $montoRaw);
    $monto = is_numeric($montoRaw) ? (float) $montoRaw : 0;

    if ($persona_id <= 0 || $categoria === '' || $detalle === '' || $monto <= 0 || $fecha === '') {
        $err = 'Completá persona, categoría, detalle, fecha y un monto mayor a 0.';
    } else {
        $stmt = $cn->prepare("INSERT INTO gastos (usuario_id, persona_id, categoria, detalle, monto, fecha, metodo_pago, notas)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdsss", $usuarioId, $persona_id, $categoria, $detalle, $monto, $fecha, $metodo_pago, $notas);

        if ($stmt->execute()) {
            $ok = 'Gasto registrado correctamente.';
        } else {
            $err = 'Error al guardar el gasto: ' . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAS | Nuevo gasto</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../../assets/CSS/Iniciocss.css">
    <link rel="stylesheet" href="../../assets/CSS/NuevoGastocss.css">
</head>

<body>

    <div class="app">

        <!-- SIDEBAR (igual que Inicio) -->
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-logo"><i class="bi bi-piggy-bank"></i></div>
                <div class="brand-text">
                    <div class="brand-title">FAS</div>
                    <div class="brand-sub">Family Accounting</div>
                </div>
            </div>

            <div class="user-box">
                <div class="avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($nombre); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($rol); ?></div>
                </div>
            </div>

            <div class="nav-title">Módulos</div>

            <nav class="nav-list">
                <a class="nav-item" href="../Dashboard/Inicio.php">
                    <i class="bi bi-grid-fill"></i><span>Inicio</span>
                </a>

                <a class="nav-item active" href="#">
                    <i class="bi bi-receipt"></i><span>Gastos</span>
                </a>

                <a class="nav-item" href="../Ingresos/Ingresos.php">
                    <i class="bi bi-cash-coin"></i><span>Ingresos</span>
                </a>

                <a class="nav-item" href="#">
                    <i class="bi bi-tags"></i><span>Categorías</span>
                </a>

                <a class="nav-item" href="#">
                    <i class="bi bi-bar-chart-line"></i><span>Reportes</span>
                </a>

                <a class="nav-item" href="../Familia/Familia.php">
                    <i class="bi bi-people"></i><span>Familia</span>
                </a>

                <a class="nav-item" href="#">
                    <i class="bi bi-gear"></i><span>Ajustes</span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <a class="logout" href="../acciones/salir.php">
                    <i class="bi bi-power"></i><span>Salir</span>
                </a>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">
            <!-- TOPBAR -->
            <header class="topbar">
                <button class="icon-btn burger" id="btnBurger" type="button" aria-label="Menú">
                    <i class="bi bi-list"></i>
                </button>

                <div class="searchbar">
                    <div class="dropdown">
                        <button class="btn btn-filter dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Gastos
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../Dashboard/Inicio.php">Ir a Inicio</a></li>
                            <li><a class="dropdown-item" href="#">Gastos</a></li>
                        </ul>
                    </div>

                    <input class="form-control search-input" id="buscador" type="text"
                        placeholder="Buscar en gastos (demo visual)..." />
                    <button class="icon-btn search-btn" type="button" aria-label="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div class="top-actions">
                    <button class="icon-bell" type="button" id="btnNoti" aria-label="Notificaciones">
                        <i class="bi bi-bell-fill"></i>
                        <span class="badge-count" id="notiCount">2</span>
                    </button>

                    <button class="icon-circle yellow" type="button" aria-label="Atajo">
                        <i class="bi bi-lightning-fill"></i>
                    </button>

                    <button class="icon-circle green" type="button" aria-label="Mensajes">
                        <i class="bi bi-envelope-fill"></i>
                        <span class="badge-count small" id="msgCount">1</span>
                    </button>
                </div>
            </header>

            <!-- CONTENT -->
            <section class="content">
                <div class="page-title">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-receipt"></i>
                        <h1>Nuevo gasto</h1>
                    </div>
                    <p>Registrá un gasto cotidiano y mantené el control al día.</p>
                </div>

                <div class="ng-grid">
                    <!-- FORM -->
                    <div class="panel ng-panel">
                        <div class="panel-head">
                            <h2>Formulario</h2>
                            <div class="panel-actions">
                                <a href="../Dashboard/Inicio.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>Volver
                                </a>
                            </div>
                        </div>

                        <?php if ($ok): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill"></i>
                                <div><?php echo htmlspecialchars($ok); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($err): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div><?php echo htmlspecialchars($err); ?></div>
                            </div>
                        <?php endif; ?>

                        <form id="formGasto" method="POST" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Persona</label>
                                    <select class="form-select form-select-lg" name="persona_id" id="persona_id"
                                        required>
                                        <option value="" selected>Seleccioná</option>
                                        <?php foreach ($personas as $p): ?>
                                            <option value="<?php echo (int) $p['id']; ?>">
                                                <?php echo htmlspecialchars($p['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Seleccioná una persona.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Categoría</label>
                                    <select class="form-select form-select-lg" name="categoria" id="categoria" required>
                                        <option value="" selected>Seleccioná</option>
                                        <option>Supermercado</option>
                                        <option>Transporte</option>
                                        <option>Comida</option>
                                        <option>Salud</option>
                                        <option>Servicios</option>
                                        <option>Educación</option>
                                        <option>Otro</option>
                                    </select>
                                    <div class="invalid-feedback">Seleccioná una categoría.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Método de pago</label>
                                    <select class="form-select form-select-lg" name="metodo_pago" id="metodo_pago"
                                        required>
                                        <option>Efectivo</option>
                                        <option>Tarjeta</option>
                                        <option>SINPE</option>
                                        <option>Transferencia</option>
                                        <option>Otro</option>
                                    </select>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Detalle</label>
                                    <input class="form-control form-control-lg" name="detalle" id="detalle"
                                        placeholder="Ej: Coca-Cola, pan, recarga..." required maxlength="120">
                                    <div class="invalid-feedback">Ingresá un detalle.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Monto (₡)</label>
                                    <input class="form-control form-control-lg" name="monto" id="monto"
                                        placeholder="Ej: 1200" inputmode="decimal" required>
                                    <div class="invalid-feedback">Ingresá un monto válido.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fecha</label>
                                    <input type="date" class="form-control form-control-lg" name="fecha" id="fecha"
                                        required>
                                    <div class="invalid-feedback">Seleccioná una fecha.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Notas (opcional)</label>
                                    <input class="form-control form-control-lg" name="notas" id="notas"
                                        placeholder="Ej: compra rápida, antojo, etc." maxlength="255">
                                </div>

                                <div class="col-12 d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1" id="btnGuardar">
                                        <span class="txt"><i class="bi bi-check2-circle me-1"></i>Guardar gasto</span>
                                        <span class="load d-none">
                                            <span class="spinner-border spinner-border-sm me-2"></span>Guardando...
                                        </span>
                                    </button>

                                    <button type="button" class="btn btn-outline-primary btn-lg" id="btnLimpiar">
                                        <i class="bi bi-eraser"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- PREVIEW / HELP -->
                    <div class="panel ng-panel">
                        <div class="panel-head">
                            <h2>Vista previa</h2>
                        </div>

                        <div class="preview-card">
                            <div class="preview-head">
                                <span class="pill pill-exp">Gasto</span>
                                <span class="muted" id="pvFecha">—</span>
                            </div>

                            <div class="preview-title" id="pvDetalle">Detalle del gasto</div>
                            <div class="preview-sub">
                                <span id="pvCategoria">Categoría</span>
                                <span class="dot-sep">•</span>
                                <span id="pvMetodo">Método</span>
                            </div>

                            <div class="preview-amount" id="pvMonto">₡ 0</div>
                            <div class="preview-note" id="pvNotas">Sin notas</div>
                        </div>

                        <div class="tips">
                            <div class="tip">
                                <i class="bi bi-lightbulb-fill"></i>
                                <div>
                                    <div class="tip-title">Tip rápido</div>
                                    <div class="tip-sub">Registrá gastos pequeños (café, snacks, bus). Suman bastante.
                                    </div>
                                </div>
                            </div>
                            <div class="tip">
                                <i class="bi bi-shield-check"></i>
                                <div>
                                    <div class="tip-title">Orden</div>
                                    <div class="tip-sub">Usá categorías consistentes para que los reportes salgan
                                        claros.</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </section>
        </main>
    </div>

    <!-- Offcanvas noti (reusamos el estilo del Inicio) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notiCanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Notificaciones</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="noti-item">
                <div class="noti-dot yellow"></div>
                <div>
                    <div class="noti-title">Gastos diarios</div>
                    <div class="noti-sub">No olvidés registrar los gastos del día</div>
                </div>
            </div>
            <div class="noti-item">
                <div class="noti-dot"></div>
                <div>
                    <div class="noti-title">Categorías</div>
                    <div class="noti-sub">Podés crear categorías personalizadas después</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/JS/InicioJS.js"></script>
    <script src="../../assets/JS/NuevoGastoJS.js"></script>
</body>

</html>