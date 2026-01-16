<?php
require_once __DIR__ . '/../inc/validar.php';
require_once __DIR__ . '/../config/db.php';

$nombre = $_SESSION['fas_user']['nombre'] ?? 'Usuario';
$rol = $_SESSION['fas_user']['rol'] ?? 'miembro';
$usuarioId = (int) ($_SESSION['fas_user']['id'] ?? 0);

$ok = '';
$err = '';

function num_clean($v)
{
    $v = str_replace(['₡', ' ', ','], ['', '', ''], trim((string) $v));
    return is_numeric($v) ? (float) $v : 0;
}

function hoy()
{
    return date('Y-m-d');
}

/* ====== Acciones POST ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1) Crear persona + balance inicial
    if ($accion === 'crear_persona') {
        $pNombre = trim($_POST['p_nombre'] ?? '');
        $total = num_clean($_POST['p_total'] ?? 0);
        $efec = num_clean($_POST['p_efectivo'] ?? 0);
        $tarj = num_clean($_POST['p_tarjeta'] ?? 0);

        if ($pNombre === '')
            $err = 'Ingresá el nombre de la persona.';
        else if ($total < 0 || $efec < 0 || $tarj < 0)
            $err = 'Los montos no pueden ser negativos.';
        else if (round($efec + $tarj, 2) !== round($total, 2))
            $err = 'Efectivo + Tarjeta debe ser igual al Total.';
        else {
            $cn->begin_transaction();
            try {
                $st = $cn->prepare("INSERT INTO personas (usuario_id, nombre) VALUES (?,?)");
                $st->bind_param("is", $usuarioId, $pNombre);
                $st->execute();
                $personaId = $cn->insert_id;

                $st2 = $cn->prepare("INSERT INTO persona_balance (persona_id, efectivo, tarjeta, sobres) VALUES (?,?,?,0)");
                $st2->bind_param("idd", $personaId, $efec, $tarj);
                $st2->execute();

                // Guardar movimiento tipo INGRESO inicial (detalle: saldo inicial)
                if ($total > 0) {
                    $fecha = hoy();
                    $det = "Saldo inicial";
                    // Si querés reflejar ambos métodos, guardamos 2 movimientos si aplica
                    if ($efec > 0) {
                        $m = $cn->prepare("INSERT INTO movimientos (usuario_id,tipo,persona_destino_id,metodo,monto,detalle,fecha)
                               VALUES (?,?,?,?,?,?,?)");
                        $tipo = 'INGRESO';
                        $met = 'Efectivo';
                        $m->bind_param("isssdss", $usuarioId, $tipo, $personaId, $met, $efec, $det, $fecha);
                        $m->execute();
                    }
                    if ($tarj > 0) {
                        $m = $cn->prepare("INSERT INTO movimientos (usuario_id,tipo,persona_destino_id,metodo,monto,detalle,fecha)
                               VALUES (?,?,?,?,?,?,?)");
                        $tipo = 'INGRESO';
                        $met = 'Tarjeta';
                        $m->bind_param("isssdss", $usuarioId, $tipo, $personaId, $met, $tarj, $det, $fecha);
                        $m->execute();
                    }
                }

                $cn->commit();
                $ok = 'Persona creada correctamente.';
            } catch (Throwable $e) {
                $cn->rollback();
                $err = 'Error al crear persona: ' . $e->getMessage();
            }
        }
    }

    // 2) Registrar ingreso (sumar a efectivo o tarjeta)
    if ($accion === 'registrar_ingreso') {
        $personaId = (int) ($_POST['i_persona'] ?? 0);
        $metodo = trim($_POST['i_metodo'] ?? 'Efectivo');
        $monto = num_clean($_POST['i_monto'] ?? 0);
        $detalle = trim($_POST['i_detalle'] ?? 'Ingreso');
        $fecha = trim($_POST['i_fecha'] ?? hoy());

        if ($personaId <= 0 || $monto <= 0)
            $err = 'Seleccioná persona y un monto mayor a 0.';
        else if (!in_array($metodo, ['Efectivo', 'Tarjeta'], true))
            $err = 'Método inválido.';
        else {
            $cn->begin_transaction();
            try {
                // Validar persona sea del usuario
                $chk = $cn->prepare("SELECT p.id FROM personas p WHERE p.id=? AND p.usuario_id=? LIMIT 1");
                $chk->bind_param("ii", $personaId, $usuarioId);
                $chk->execute();
                if ($chk->get_result()->num_rows === 0)
                    throw new Exception('Persona no válida.');

                if ($metodo === 'Efectivo') {
                    $up = $cn->prepare("UPDATE persona_balance SET efectivo = efectivo + ? WHERE persona_id=?");
                } else {
                    $up = $cn->prepare("UPDATE persona_balance SET tarjeta = tarjeta + ? WHERE persona_id=?");
                }
                $up->bind_param("di", $monto, $personaId);
                $up->execute();

                $ins = $cn->prepare("INSERT INTO movimientos (usuario_id,tipo,persona_destino_id,metodo,monto,detalle,fecha)
                             VALUES (?,?,?,?,?,?,?)");
                $tipo = 'INGRESO';
                $ins->bind_param("isssdss", $usuarioId, $tipo, $personaId, $metodo, $monto, $detalle, $fecha);
                $ins->execute();

                $cn->commit();
                $ok = 'Ingreso registrado.';
            } catch (Throwable $e) {
                $cn->rollback();
                $err = 'Error: ' . $e->getMessage();
            }
        }
    }

    // 3) Transferencia entre personas (valida que el origen tenga disponible fuera de sobres)
    if ($accion === 'transferir') {
        $origen = (int) ($_POST['t_origen'] ?? 0);
        $destino = (int) ($_POST['t_destino'] ?? 0);
        $metodo = trim($_POST['t_metodo'] ?? 'Efectivo');
        $monto = num_clean($_POST['t_monto'] ?? 0);
        $detalle = trim($_POST['t_detalle'] ?? 'Transferencia');
        $fecha = trim($_POST['t_fecha'] ?? hoy());

        if ($origen <= 0 || $destino <= 0 || $origen === $destino)
            $err = 'Seleccioná origen y destino distintos.';
        else if ($monto <= 0)
            $err = 'Monto debe ser mayor a 0.';
        else if (!in_array($metodo, ['Efectivo', 'Tarjeta'], true))
            $err = 'Método inválido.';
        else {
            $cn->begin_transaction();
            try {
                // Validar ambos pertenecen al usuario
                $chk = $cn->prepare("SELECT COUNT(*) c FROM personas WHERE usuario_id=? AND id IN (?,?)");
                $chk->bind_param("iii", $usuarioId, $origen, $destino);
                $chk->execute();
                $c = (int) $chk->get_result()->fetch_assoc()['c'];
                if ($c !== 2)
                    throw new Exception('Persona origen/destino no válida.');

                // Obtener disponible del origen (sin sobres)
                $b = $cn->prepare("SELECT efectivo, tarjeta, sobres FROM persona_balance WHERE persona_id=? LIMIT 1");
                $b->bind_param("i", $origen);
                $b->execute();
                $bal = $b->get_result()->fetch_assoc();
                if (!$bal)
                    throw new Exception('Balance no encontrado.');

                $disp = ($metodo === 'Efectivo') ? (float) $bal['efectivo'] : (float) $bal['tarjeta'];
                if ($monto > $disp)
                    throw new Exception('No hay saldo suficiente disponible. (Si está en sobres, primero retiralo).');

                // Aplicar transferencia
                if ($metodo === 'Efectivo') {
                    $up1 = $cn->prepare("UPDATE persona_balance SET efectivo = efectivo - ? WHERE persona_id=?");
                    $up2 = $cn->prepare("UPDATE persona_balance SET efectivo = efectivo + ? WHERE persona_id=?");
                } else {
                    $up1 = $cn->prepare("UPDATE persona_balance SET tarjeta = tarjeta - ? WHERE persona_id=?");
                    $up2 = $cn->prepare("UPDATE persona_balance SET tarjeta = tarjeta + ? WHERE persona_id=?");
                }
                $up1->bind_param("di", $monto, $origen);
                $up1->execute();
                $up2->bind_param("di", $monto, $destino);
                $up2->execute();

                $ins = $cn->prepare("INSERT INTO movimientos (usuario_id,tipo,persona_origen_id,persona_destino_id,metodo,monto,detalle,fecha)
                             VALUES (?,?,?,?,?,?,?,?)");
                $tipo = 'TRANSFERENCIA';
                $ins->bind_param("ississds", $usuarioId, $tipo, $origen, $destino, $metodo, $monto, $detalle, $fecha);
                $ins->execute();

                $cn->commit();
                $ok = 'Transferencia realizada.';
            } catch (Throwable $e) {
                $cn->rollback();
                $err = 'Error: ' . $e->getMessage();
            }
        }
    }

    // 4) Sobres: depositar (SOBRE_IN) o retirar (SOBRE_OUT)
    if ($accion === 'sobre') {
        $personaId = (int) ($_POST['s_persona'] ?? 0);
        $modo = trim($_POST['s_modo'] ?? 'in'); // in | out
        $metodo = trim($_POST['s_metodo'] ?? 'Efectivo');
        $monto = num_clean($_POST['s_monto'] ?? 0);
        $detalle = trim($_POST['s_detalle'] ?? 'Sobre');
        $fecha = trim($_POST['s_fecha'] ?? hoy());

        if ($personaId <= 0 || $monto <= 0)
            $err = 'Seleccioná persona y un monto mayor a 0.';
        else if (!in_array($metodo, ['Efectivo', 'Tarjeta'], true))
            $err = 'Método inválido.';
        else if (!in_array($modo, ['in', 'out'], true))
            $err = 'Acción inválida.';
        else {
            $cn->begin_transaction();
            try {
                // validar persona
                $chk = $cn->prepare("SELECT id FROM personas WHERE id=? AND usuario_id=? LIMIT 1");
                $chk->bind_param("ii", $personaId, $usuarioId);
                $chk->execute();
                if ($chk->get_result()->num_rows === 0)
                    throw new Exception('Persona no válida.');

                $b = $cn->prepare("SELECT efectivo, tarjeta, sobres FROM persona_balance WHERE persona_id=? LIMIT 1");
                $b->bind_param("i", $personaId);
                $b->execute();
                $bal = $b->get_result()->fetch_assoc();

                if ($modo === 'in') {
                    // meter al sobre: se descuenta del método y se suma a sobres
                    $disp = ($metodo === 'Efectivo') ? (float) $bal['efectivo'] : (float) $bal['tarjeta'];
                    if ($monto > $disp)
                        throw new Exception('No hay saldo suficiente para meter al sobre.');

                    if ($metodo === 'Efectivo') {
                        $up = $cn->prepare("UPDATE persona_balance SET efectivo = efectivo - ?, sobres = sobres + ? WHERE persona_id=?");
                    } else {
                        $up = $cn->prepare("UPDATE persona_balance SET tarjeta = tarjeta - ?, sobres = sobres + ? WHERE persona_id=?");
                    }
                    $up->bind_param("ddi", $monto, $monto, $personaId);
                    $up->execute();

                    $tipo = 'SOBRE_IN';
                } else {
                    // sacar del sobre: se resta de sobres y se suma al método
                    if ($monto > (float) $bal['sobres'])
                        throw new Exception('No hay suficiente en sobres para retirar.');

                    if ($metodo === 'Efectivo') {
                        $up = $cn->prepare("UPDATE persona_balance SET efectivo = efectivo + ?, sobres = sobres - ? WHERE persona_id=?");
                    } else {
                        $up = $cn->prepare("UPDATE persona_balance SET tarjeta = tarjeta + ?, sobres = sobres - ? WHERE persona_id=?");
                    }
                    $up->bind_param("ddi", $monto, $monto, $personaId);
                    $up->execute();

                    $tipo = 'SOBRE_OUT';
                }

                $ins = $cn->prepare("INSERT INTO movimientos (usuario_id,tipo,persona_destino_id,metodo,monto,detalle,fecha)
                             VALUES (?,?,?,?,?,?,?)");
                $ins->bind_param("isssdss", $usuarioId, $tipo, $personaId, $metodo, $monto, $detalle, $fecha);
                $ins->execute();

                $cn->commit();
                $ok = ($modo === 'in') ? 'Dinero guardado en sobre.' : 'Dinero retirado del sobre.';
            } catch (Throwable $e) {
                $cn->rollback();
                $err = 'Error: ' . $e->getMessage();
            }
        }
    }
}

/* ====== Data para UI ====== */
$personas = [];
$res = $cn->prepare("SELECT p.id, p.nombre, b.efectivo, b.tarjeta, b.sobres
                     FROM personas p
                     JOIN persona_balance b ON b.persona_id = p.id
                     WHERE p.usuario_id=?
                     ORDER BY p.nombre ASC");
$res->bind_param("i", $usuarioId);
$res->execute();
$personas = $res->get_result()->fetch_all(MYSQLI_ASSOC);

// Totales familia (incluye sobres)
$totalE = $totalT = $totalS = 0;
foreach ($personas as $p) {
    $totalE += $p['efectivo'];
    $totalT += $p['tarjeta'];
    $totalS += $p['sobres'];
}
$totalGeneral = $totalE + $totalT + $totalS;

// % vs mes anterior: ingresos (movimientos tipo INGRESO) mes actual vs anterior
$y = (int) date('Y');
$m = (int) date('m');
$inicioMes = date('Y-m-01');
$finMes = date('Y-m-t');
$inicioPrev = date('Y-m-01', strtotime('-1 month'));
$finPrev = date('Y-m-t', strtotime('-1 month'));

function sum_ingresos($cn, $usuarioId, $ini, $fin)
{
    $q = $cn->prepare("SELECT COALESCE(SUM(monto),0) s
                     FROM movimientos
                     WHERE usuario_id=? AND tipo='INGRESO' AND fecha BETWEEN ? AND ?");
    $q->bind_param("iss", $usuarioId, $ini, $fin);
    $q->execute();
    return (float) $q->get_result()->fetch_assoc()['s'];
}
$ingActual = sum_ingresos($cn, $usuarioId, $inicioMes, $finMes);
$ingPrev = sum_ingresos($cn, $usuarioId, $inicioPrev, $finPrev);
$diff = $ingActual - $ingPrev;
$porc = ($ingPrev > 0) ? ($diff / $ingPrev) * 100 : (($ingActual > 0) ? 100 : 0);
$trendUp = $diff >= 0;

function money_crc($n)
{
    return '₡ ' . number_format((float) $n, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAS | Ingresos</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../../assets/CSS/Iniciocss.css">
    <link rel="stylesheet" href="../../assets/CSS/Ingresoscss.css">
</head>

<body>

    <div class="app">
        <!-- SIDEBAR -->
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
                    <div class="user-name">
                        <?php echo htmlspecialchars($nombre); ?>
                    </div>
                    <div class="user-role">
                        <?php echo htmlspecialchars($rol); ?>
                    </div>
                </div>
            </div>

            <div class="nav-title">Módulos</div>
            <nav class="nav-list">
                <a class="nav-item" href="../Dashboard/Inicio.php"><i
                        class="bi bi-grid-fill"></i><span>Inicio</span></a>
                <a class="nav-item" href="../Gastos/NuevoGasto.php"><i class="bi bi-receipt"></i><span>Gastos</span></a>
                <a class="nav-item active" href="#"><i class="bi bi-cash-coin"></i><span>Ingresos</span></a>
                <a class="nav-item" href="#"><i class="bi bi-tags"></i><span>Categorías</span></a>
                <a class="nav-item" href="#"><i class="bi bi-bar-chart-line"></i><span>Reportes</span></a>
                <a class="nav-item" href="../Familia/Familia.php"><i class="bi bi-people"></i><span>Familia</span></a>
                <a class="nav-item" href="#"><i class="bi bi-gear"></i><span>Ajustes</span></a>
            </nav>

            <div class="sidebar-bottom">
                <a class="logout" href="../acciones/salir.php"><i class="bi bi-power"></i><span>Salir</span></a>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">
            <header class="topbar">
                <button class="icon-btn burger" id="btnBurger" type="button"><i class="bi bi-list"></i></button>

                <div class="searchbar">
                    <div class="dropdown">
                        <button class="btn btn-filter dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Ingresos
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../Dashboard/Inicio.php">Inicio</a></li>
                            <li><a class="dropdown-item" href="../Gastos/NuevoGasto.php">Gastos</a></li>
                            <li><a class="dropdown-item" href="#">Ingresos</a></li>
                        </ul>
                    </div>
                    <input class="form-control search-input" placeholder="Buscar personas o movimientos (visual)..." />
                    <button class="icon-btn search-btn" type="button"><i class="bi bi-search"></i></button>
                </div>

                <div class="top-actions">
                    <button class="icon-bell" type="button" id="btnNoti">
                        <i class="bi bi-bell-fill"></i><span class="badge-count" id="notiCount">2</span>
                    </button>
                    <button class="icon-circle yellow" type="button"><i class="bi bi-lightning-fill"></i></button>
                    <button class="icon-circle green" type="button"><i class="bi bi-envelope-fill"></i><span
                            class="badge-count small">1</span></button>
                </div>
            </header>

            <section class="content">
                <div class="page-title">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cash-coin"></i>
                        <h1>Ingresos</h1>
                    </div>
                    <p>Gestioná personas, dinero disponible, sobres y transferencias.</p>
                </div>

                <?php if ($ok): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <div>
                            <?php echo htmlspecialchars($ok); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($err): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <?php echo htmlspecialchars($err); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- KPIs -->
                <div class="cards ingresos-cards">
                    <div class="kpi">
                        <div class="kpi-icon blue"><i class="bi bi-people-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Personas registradas</div>
                            <div class="kpi-value">
                                <?php echo count($personas); ?>
                            </div>
                            <div class="kpi-sub">Control familiar activo</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon green"><i class="bi bi-safe2-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Total familiar</div>
                            <div class="kpi-value">
                                <?php echo money_crc($totalGeneral); ?>
                            </div>
                            <div class="kpi-sub">Incluye sobres + efectivo + tarjeta</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon yellow"><i class="bi bi-cash-stack"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Efectivo familiar</div>
                            <div class="kpi-value">
                                <?php echo money_crc($totalE); ?>
                            </div>
                            <div class="kpi-sub">Disponible para movimientos</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon blue"><i class="bi bi-credit-card-2-front-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Tarjeta familiar</div>
                            <div class="kpi-value">
                                <?php echo money_crc($totalT); ?>
                            </div>
                            <div class="kpi-sub">Disponible para movimientos</div>
                        </div>
                    </div>
                </div>

                <!-- Trend row -->
                <div class="panel trend-panel">
                    <div class="trend-left">
                        <div class="trend-title">Ingresos del mes</div>
                        <div class="trend-value">
                            <?php echo money_crc($ingActual); ?>
                        </div>
                        <div class="trend-sub">Comparado con el mes anterior</div>
                    </div>
                    <div class="trend-right">
                        <span class="trend-badge <?php echo $trendUp ? 'up' : 'down'; ?>">
                            <?php echo $trendUp ? '+' : '-'; ?>
                            <?php echo money_crc(abs($diff)); ?>
                            (
                            <?php echo ($trendUp ? '+' : '-') . number_format(abs($porc), 1); ?>%)
                        </span>
                    </div>
                </div>

                <!-- Layout: personas + forms -->
                <div class="ing-grid">

                    <!-- Personas cards -->
                    <div class="panel">
                        <div class="panel-head">
                            <h2>Personas</h2>
                            <div class="panel-actions">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal"
                                    data-bs-target="#modalPersona">
                                    <i class="bi bi-person-plus me-1"></i>Nueva persona
                                </button>
                            </div>
                        </div>

                        <?php if (!count($personas)): ?>
                            <div class="empty">
                                <div class="empty-icon"><i class="bi bi-people"></i></div>
                                <div class="empty-title">Aún no tenés personas registradas</div>
                                <div class="empty-sub">Creá una persona para empezar a gestionar ingresos, sobres y
                                    transferencias.</div>
                                <button class="btn btn-primary mt-3" type="button" data-bs-toggle="modal"
                                    data-bs-target="#modalPersona">
                                    Crear primera persona
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="people-grid">
                                <?php foreach ($personas as $p):
                                    $disp = (float) $p['efectivo'] + (float) $p['tarjeta'];
                                    $tot = $disp + (float) $p['sobres'];
                                    ?>
                                    <div class="person-card" data-persona="<?php echo (int) $p['id']; ?>">
                                        <div class="pc-top">
                                            <div class="pc-avatar"><i class="bi bi-person-fill"></i></div>
                                            <div class="pc-meta">
                                                <div class="pc-name">
                                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                                </div>
                                                <div class="pc-sub">Total: <b>
                                                        <?php echo money_crc($tot); ?>
                                                    </b></div>
                                            </div>
                                        </div>

                                        <div class="pc-badges">
                                            <span class="pc-pill ef">Efectivo:
                                                <?php echo money_crc($p['efectivo']); ?>
                                            </span>
                                            <span class="pc-pill tj">Tarjeta:
                                                <?php echo money_crc($p['tarjeta']); ?>
                                            </span>
                                            <span class="pc-pill sb">Sobres:
                                                <?php echo money_crc($p['sobres']); ?>
                                            </span>
                                        </div>

                                        <div class="pc-actions">
                                            <button class="btn btn-outline-primary btn-sm" type="button"
                                                data-fill-persona="<?php echo (int) $p['id']; ?>">
                                                <i class="bi bi-plus-circle me-1"></i>Ingreso
                                            </button>
                                            <button class="btn btn-outline-primary btn-sm" type="button"
                                                data-fill-transfer="<?php echo (int) $p['id']; ?>">
                                                <i class="bi bi-arrow-left-right me-1"></i>Mover
                                            </button>
                                            <button class="btn btn-outline-primary btn-sm" type="button"
                                                data-fill-sobre="<?php echo (int) $p['id']; ?>">
                                                <i class="bi bi-inbox me-1"></i>Sobres
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Forms -->
                    <div class="forms-col">

                        <!-- Registrar ingreso -->
                        <div class="panel">
                            <div class="panel-head">
                                <h2>Registrar ingreso</h2>
                            </div>

                            <form method="POST" id="formIngreso" class="needs-validation" novalidate>
                                <input type="hidden" name="accion" value="registrar_ingreso">

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Persona</label>
                                        <select class="form-select form-select-lg" name="i_persona" id="i_persona"
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
                                        <label class="form-label fw-bold">Método</label>
                                        <select class="form-select form-select-lg" name="i_metodo" id="i_metodo"
                                            required>
                                            <option>Efectivo</option>
                                            <option>Tarjeta</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Monto (₡)</label>
                                        <input class="form-control form-control-lg" name="i_monto" id="i_monto"
                                            placeholder="Ej: 25000" inputmode="decimal" required>
                                        <div class="invalid-feedback">Ingresá un monto válido.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Fecha</label>
                                        <input type="date" class="form-control form-control-lg" name="i_fecha"
                                            value="<?php echo hoy(); ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Detalle</label>
                                        <input class="form-control form-control-lg" name="i_detalle" value="Ingreso"
                                            maxlength="160">
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary btn-lg w-100" type="submit" id="btnIngreso">
                                            <span class="txt"><i class="bi bi-check2-circle me-1"></i>Guardar
                                                ingreso</span>
                                            <span class="load d-none"><span
                                                    class="spinner-border spinner-border-sm me-2"></span>Guardando...</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Transferir -->
                        <div class="panel">
                            <div class="panel-head">
                                <h2>Mover dinero entre personas</h2>
                            </div>

                            <form method="POST" id="formTransfer" class="needs-validation" novalidate>
                                <input type="hidden" name="accion" value="transferir">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Origen</label>
                                        <select class="form-select form-select-lg" name="t_origen" id="t_origen"
                                            required>
                                            <option value="" selected>Seleccioná</option>
                                            <?php foreach ($personas as $p): ?>
                                                <option value="<?php echo (int) $p['id']; ?>">
                                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Seleccioná origen.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Destino</label>
                                        <select class="form-select form-select-lg" name="t_destino" id="t_destino"
                                            required>
                                            <option value="" selected>Seleccioná</option>
                                            <?php foreach ($personas as $p): ?>
                                                <option value="<?php echo (int) $p['id']; ?>">
                                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Seleccioná destino.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Método</label>
                                        <select class="form-select form-select-lg" name="t_metodo" id="t_metodo"
                                            required>
                                            <option>Efectivo</option>
                                            <option>Tarjeta</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Monto (₡)</label>
                                        <input class="form-control form-control-lg" name="t_monto" id="t_monto"
                                            placeholder="Ej: 5000" required>
                                        <div class="invalid-feedback">Ingresá un monto válido.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Fecha</label>
                                        <input type="date" class="form-control form-control-lg" name="t_fecha"
                                            value="<?php echo hoy(); ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Detalle</label>
                                        <input class="form-control form-control-lg" name="t_detalle" value="Préstamo"
                                            maxlength="160">
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary btn-lg w-100" type="submit" id="btnTransfer">
                                            <span class="txt"><i class="bi bi-arrow-left-right me-1"></i>Realizar
                                                movimiento</span>
                                            <span class="load d-none"><span
                                                    class="spinner-border spinner-border-sm me-2"></span>Procesando...</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Sobres -->
                        <div class="panel">
                            <div class="panel-head">
                                <h2>Sobres</h2>
                            </div>

                            <form method="POST" id="formSobre" class="needs-validation" novalidate>
                                <input type="hidden" name="accion" value="sobre">

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Persona</label>
                                        <select class="form-select form-select-lg" name="s_persona" id="s_persona"
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
                                        <label class="form-label fw-bold">Acción</label>
                                        <select class="form-select form-select-lg" name="s_modo" id="s_modo" required>
                                            <option value="in">Guardar en sobre</option>
                                            <option value="out">Sacar del sobre</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Método</label>
                                        <select class="form-select form-select-lg" name="s_metodo" id="s_metodo"
                                            required>
                                            <option>Efectivo</option>
                                            <option>Tarjeta</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Monto (₡)</label>
                                        <input class="form-control form-control-lg" name="s_monto" id="s_monto"
                                            placeholder="Ej: 10000" required>
                                        <div class="invalid-feedback">Ingresá un monto válido.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Fecha</label>
                                        <input type="date" class="form-control form-control-lg" name="s_fecha"
                                            value="<?php echo hoy(); ?>" required>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold">Detalle</label>
                                        <input class="form-control form-control-lg" name="s_detalle" value="Ahorro"
                                            maxlength="160">
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary btn-lg w-100" type="submit" id="btnSobre">
                                            <span class="txt"><i class="bi bi-inbox me-1"></i>Aplicar</span>
                                            <span class="load d-none"><span
                                                    class="spinner-border spinner-border-sm me-2"></span>Procesando...</span>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="mini-note mt-3">
                                <i class="bi bi-info-circle"></i>
                                <div>
                                    Si el dinero está en <b>sobres</b>, no se puede mover a otra persona hasta
                                    retirarlo.
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- Modal Nueva persona -->
                <div class="modal fade" id="modalPersona" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content fas-modal">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nueva persona</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <form method="POST" id="formPersona" class="needs-validation" novalidate>
                                <input type="hidden" name="accion" value="crear_persona">
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Nombre</label>
                                            <input class="form-control form-control-lg" name="p_nombre" id="p_nombre"
                                                required maxlength="80" placeholder="Ej: Kendall, Mamá, Papá...">
                                            <div class="invalid-feedback">Ingresá el nombre.</div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Total (₡)</label>
                                            <input class="form-control form-control-lg" name="p_total" id="p_total"
                                                required placeholder="Ej: 50000">
                                            <div class="invalid-feedback">Ingresá el total.</div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Efectivo (₡)</label>
                                            <input class="form-control form-control-lg" name="p_efectivo"
                                                id="p_efectivo" required placeholder="Ej: 30000">
                                            <div class="invalid-feedback">Ingresá el efectivo.</div>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Tarjeta (₡)</label>
                                            <input class="form-control form-control-lg" name="p_tarjeta" id="p_tarjeta"
                                                required placeholder="Ej: 20000">
                                            <div class="invalid-feedback">Ingresá la tarjeta.</div>
                                        </div>

                                        <div class="col-12">
                                            <div class="mini-note">
                                                <i class="bi bi-shield-check"></i>
                                                <div>Efectivo + Tarjeta debe ser igual al Total.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-primary"
                                        data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary" id="btnCrearPersona">
                                        <span class="txt">Crear persona</span>
                                        <span class="load d-none"><span
                                                class="spinner-border spinner-border-sm me-2"></span>Creando...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <!-- Offcanvas Noti -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notiCanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Notificaciones</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="noti-item">
                <div class="noti-dot yellow"></div>
                <div>
                    <div class="noti-title">Sobres</div>
                    <div class="noti-sub">Recordá: lo guardado en sobres no se mueve hasta retirarlo</div>
                </div>
            </div>
            <div class="noti-item">
                <div class="noti-dot green"></div>
                <div>
                    <div class="noti-title">Ingresos</div>
                    <div class="noti-sub">Registrá ingresos por método (efectivo/tarjeta)</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/JS/InicioJS.js"></script>
    <script src="../../assets/JS/IngresosJS.js"></script>
</body>

</html>