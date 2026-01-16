<?php
require_once __DIR__ . '/../inc/validar.php';
require_once __DIR__ . '/../config/db.php';

$nombre = $_SESSION['fas_user']['nombre'] ?? 'Usuario';
$rol = $_SESSION['fas_user']['rol'] ?? 'miembro';
$usuarioId = (int) ($_SESSION['fas_user']['id'] ?? 0);

function money_crc($n)
{
    return '₡ ' . number_format((float) $n, 0, '.', ',');
}
function clean_date($d, $fallback)
{
    $d = trim((string) $d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : $fallback;
}
function sum_between($cn, $sql, $types, $params)
{
    $st = $cn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    return (float) $st->get_result()->fetch_assoc()['s'];
}

$hoy = date('Y-m-d');
$iniMes = date('Y-m-01');
$finMes = date('Y-m-t');

$ini = clean_date($_GET['ini'] ?? $iniMes, $iniMes);
$fin = clean_date($_GET['fin'] ?? $finMes, $finMes);
$personaSel = (int) ($_GET['persona'] ?? 0);
$tipoSel = trim($_GET['tipo'] ?? 'TODO'); // TODO | INGRESO | GASTO | TRANSFERENCIA | SOBRES
$metodoSel = trim($_GET['metodo'] ?? 'TODO'); // TODO | Efectivo | Tarjeta

/* ====== Personas + balance ====== */
$stP = $cn->prepare("SELECT p.id, p.nombre, b.efectivo, b.tarjeta, b.sobres
                     FROM personas p
                     JOIN persona_balance b ON b.persona_id = p.id
                     WHERE p.usuario_id=?
                     ORDER BY p.nombre ASC");
$stP->bind_param("i", $usuarioId);
$stP->execute();
$personas = $stP->get_result()->fetch_all(MYSQLI_ASSOC);

// Totales familiares (en vivo)
$totalE = $totalT = $totalS = 0;
foreach ($personas as $p) {
    $totalE += $p['efectivo'];
    $totalT += $p['tarjeta'];
    $totalS += $p['sobres'];
}
$totalDisponible = $totalE + $totalT;
$totalGeneral = $totalDisponible + $totalS;

/* ====== KPIs por rango: ingresos vs gastos del rango ======
   - Ingresos vienen de movimientos tipo INGRESO
   - Gastos idealmente vienen de tabla gastos (si existe)
*/
$ingRango = sum_between(
    $cn,
    "SELECT COALESCE(SUM(monto),0) s FROM movimientos
   WHERE usuario_id=? AND tipo='INGRESO' AND fecha BETWEEN ? AND ?",
    "iss",
    [$usuarioId, $ini, $fin]
);

/* Gastos: si tu tabla se llama 'gastos' y tiene usuario_id, monto y fecha */
$gastoRango = 0;
$existeGastos = false;
$chk = $cn->query("SHOW TABLES LIKE 'gastos'");
if ($chk && $chk->num_rows > 0) {
    $existeGastos = true;
    $gastoRango = sum_between(
        $cn,
        "SELECT COALESCE(SUM(monto),0) s FROM gastos
     WHERE usuario_id=? AND fecha BETWEEN ? AND ?",
        "iss",
        [$usuarioId, $ini, $fin]
    );
}

$balanceRango = $ingRango - $gastoRango;

/* ====== Tendencia vs mes anterior (ingresos y gastos) ====== */
$iniPrev = date('Y-m-01', strtotime($iniMes . ' -1 month'));
$finPrev = date('Y-m-t', strtotime($iniMes . ' -1 month'));

$ingMes = sum_between(
    $cn,
    "SELECT COALESCE(SUM(monto),0) s FROM movimientos
   WHERE usuario_id=? AND tipo='INGRESO' AND fecha BETWEEN ? AND ?",
    "iss",
    [$usuarioId, $iniMes, $finMes]
);
$ingPrev = sum_between(
    $cn,
    "SELECT COALESCE(SUM(monto),0) s FROM movimientos
   WHERE usuario_id=? AND tipo='INGRESO' AND fecha BETWEEN ? AND ?",
    "iss",
    [$usuarioId, $iniPrev, $finPrev]
);

$gMes = 0;
$gPrev = 0;
if ($existeGastos) {
    $gMes = sum_between(
        $cn,
        "SELECT COALESCE(SUM(monto),0) s FROM gastos
     WHERE usuario_id=? AND fecha BETWEEN ? AND ?",
        "iss",
        [$usuarioId, $iniMes, $finMes]
    );
    $gPrev = sum_between(
        $cn,
        "SELECT COALESCE(SUM(monto),0) s FROM gastos
     WHERE usuario_id=? AND fecha BETWEEN ? AND ?",
        "iss",
        [$usuarioId, $iniPrev, $finPrev]
    );
}

$diffIng = $ingMes - $ingPrev;
$porIng = ($ingPrev > 0) ? ($diffIng / $ingPrev) * 100 : (($ingMes > 0) ? 100 : 0);

$diffGas = $gMes - $gPrev;
$porGas = ($gPrev > 0) ? ($diffGas / $gPrev) * 100 : (($gMes > 0) ? 100 : 0);

/* ====== Movimientos unificados (tabla) ======
   - Unificamos: ingresos (movimientos tipo INGRESO),
                 transferencias,
                 sobres in/out,
                 gastos (si hay tabla).
   - Nota: para gastos por persona/metodo se recomienda tener persona_id + metodo en gastos.
*/
$rows = [];

// Movimientos de ingresos/transferencias/sobres
$wherePersona = "";
$params = [$usuarioId, $ini, $fin];
$types = "iss";
if ($personaSel > 0) {
    // si es persona, puede ser origen o destino, así capturamos transferencias
    $wherePersona = " AND (persona_origen_id = ? OR persona_destino_id = ?) ";
    $params[] = $personaSel;
    $params[] = $personaSel;
    $types .= "ii";
}

$whereTipo = "";
if ($tipoSel !== "TODO") {
    if ($tipoSel === "SOBRES")
        $whereTipo = " AND tipo IN ('SOBRE_IN','SOBRE_OUT') ";
    else
        $whereTipo = " AND tipo = ? ";
    if ($tipoSel !== "SOBRES") {
        $params[] = $tipoSel;
        $types .= "s";
    }
}

$whereMetodo = "";
if ($metodoSel !== "TODO") {
    $whereMetodo = " AND metodo = ? ";
    $params[] = $metodoSel;
    $types .= "s";
}

$sqlMov = "SELECT m.id, m.tipo, m.persona_origen_id, m.persona_destino_id, m.metodo, m.monto, m.detalle, m.fecha
           FROM movimientos m
           WHERE m.usuario_id=? AND m.fecha BETWEEN ? AND ?
           $wherePersona
           $whereTipo
           $whereMetodo
           ORDER BY m.fecha DESC, m.id DESC
           LIMIT 150";

$stM = $cn->prepare($sqlMov);
$stM->bind_param($types, ...$params);
$stM->execute();
$movs = $stM->get_result()->fetch_all(MYSQLI_ASSOC);

// Mapa nombre por persona
$mapN = [];
foreach ($personas as $p) {
    $mapN[(int) $p['id']] = $p['nombre'];
}

foreach ($movs as $m) {
    $tipo = $m['tipo'];
    $label = $tipo;
    $badge = "info";
    $sign = "+";
    if ($tipo === "INGRESO") {
        $label = "Ingreso";
        $badge = "success";
        $sign = "+";
    }
    if ($tipo === "TRANSFERENCIA") {
        $label = "Transferencia";
        $badge = "primary";
        $sign = "↔";
    }
    if ($tipo === "SOBRE_IN") {
        $label = "Sobre (guardar)";
        $badge = "warning";
        $sign = "→";
    }
    if ($tipo === "SOBRE_OUT") {
        $label = "Sobre (retirar)";
        $badge = "warning";
        $sign = "←";
    }

    $or = $m['persona_origen_id'] ? ($mapN[(int) $m['persona_origen_id']] ?? '—') : '—';
    $de = $m['persona_destino_id'] ? ($mapN[(int) $m['persona_destino_id']] ?? '—') : '—';

    $rows[] = [
        "fuente" => "mov",
        "tipo" => $label,
        "tipo_raw" => $tipo,
        "badge" => $badge,
        "persona" => ($tipo === "TRANSFERENCIA") ? "$or → $de" : $de,
        "metodo" => $m['metodo'] ?? '—',
        "detalle" => $m['detalle'],
        "fecha" => $m['fecha'],
        "monto" => (float) $m['monto'],
        "signo" => $sign
    ];
}

// Gastos (si existe tabla gastos)
if ($existeGastos && ($tipoSel === "TODO" || $tipoSel === "GASTO")) {
    $gParams = [$usuarioId, $ini, $fin];
    $gTypes = "iss";
    $wP = "";
    if ($personaSel > 0) {
        $wP = " AND persona_id=? ";
        $gParams[] = $personaSel;
        $gTypes .= "i";
    }
    $wM = "";
    if ($metodoSel !== "TODO") {
        $wM = " AND metodo=? ";
        $gParams[] = $metodoSel;
        $gTypes .= "s";
    }

    // columns esperadas: persona_id, metodo, monto, detalle, fecha, categoria
    $sqlG = "SELECT persona_id, metodo_pago AS metodo, monto, detalle, fecha, categoria
FROM gastos
           WHERE usuario_id=? AND fecha BETWEEN ? AND ?
           $wP $wM
           ORDER BY fecha DESC
           LIMIT 150";

    $stG = $cn->prepare($sqlG);
    $stG->bind_param($gTypes, ...$gParams);
    $stG->execute();
    $gs = $stG->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($gs as $g) {
        $per = $g['persona_id'] ? ($mapN[(int) $g['persona_id']] ?? '—') : '—';
        $rows[] = [
            "fuente" => "gasto",
            "tipo" => "Gasto",
            "tipo_raw" => "GASTO",
            "badge" => "danger",
            "persona" => $per,
            "metodo" => $g['metodo'] ?? '—',
            "detalle" => trim(($g['categoria'] ?? '') . ' — ' . ($g['detalle'] ?? '')),
            "fecha" => $g['fecha'],
            "monto" => (float) $g['monto'],
            "signo" => "-"
        ];
    }

    // Re-ordenar mezcla por fecha
    usort($rows, function ($a, $b) {
        if ($a['fecha'] === $b['fecha'])
            return 0;
        return ($a['fecha'] < $b['fecha']) ? 1 : -1;
    });
}

// Data JSON para charts (cliente)
$chartPersonas = [];
foreach ($personas as $p) {
    $disp = (float) $p['efectivo'] + (float) $p['tarjeta'];
    $tot = $disp + (float) $p['sobres'];
    $chartPersonas[] = [
        "id" => (int) $p['id'],
        "nombre" => $p['nombre'],
        "efectivo" => (float) $p['efectivo'],
        "tarjeta" => (float) $p['tarjeta'],
        "sobres" => (float) $p['sobres'],
        "disponible" => $disp,
        "total" => $tot
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAS | Familia</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../../assets/CSS/Iniciocss.css">
    <link rel="stylesheet" href="../../assets/CSS/Familiacss.css">
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
                <a class="nav-item" href="../Ingresos/Ingresos.php"><i
                        class="bi bi-cash-coin"></i><span>Ingresos</span></a>
                <a class="nav-item" href="#"><i class="bi bi-tags"></i><span>Categorías</span></a>
                <a class="nav-item" href="#"><i class="bi bi-bar-chart-line"></i><span>Reportes</span></a>
                <a class="nav-item active" href="#"><i class="bi bi-people-fill"></i><span>Familia</span></a>
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
                        <button class="btn btn-filter dropdown-toggle" type="button"
                            data-bs-toggle="dropdown">Familia</button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../Dashboard/Inicio.php">Inicio</a></li>
                            <li><a class="dropdown-item" href="../Gastos/NuevoGasto.php">Gastos</a></li>
                            <li><a class="dropdown-item" href="../Ingresos/Ingresos.php">Ingresos</a></li>
                            <li><a class="dropdown-item" href="#">Familia</a></li>
                        </ul>
                    </div>
                    <input class="form-control search-input" id="quickSearch"
                        placeholder="Buscar en movimientos (detalle, persona, tipo)..." />
                    <button class="icon-btn search-btn" type="button"><i class="bi bi-search"></i></button>
                </div>

                <div class="top-actions">
                    <button class="icon-bell" type="button" id="btnNoti">
                        <i class="bi bi-bell-fill"></i><span class="badge-count" id="notiCount">3</span>
                    </button>
                    <button class="icon-circle yellow" type="button" id="btnTips"><i
                            class="bi bi-lightning-fill"></i></button>
                    <button class="icon-circle green" type="button"><i class="bi bi-envelope-fill"></i><span
                            class="badge-count small">1</span></button>
                </div>
            </header>

            <section class="content">
                <div class="page-title">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people-fill"></i>
                        <h1>Familia</h1>
                    </div>
                    <p>Visión completa: personas, sobres, disponible, gastos, ingresos y movimientos para tomar
                        decisiones.</p>
                </div>

                <!-- Filtros -->
                <form class="panel filtros" method="GET" id="filtrosForm">
                    <div class="filtros-grid">
                        <div>
                            <label class="form-label fw-bold">Persona</label>
                            <select class="form-select form-select-lg" name="persona" id="personaSel">
                                <option value="0" <?php echo $personaSel === 0 ? 'selected' : ''; ?>>Todas</option>
                                <?php foreach ($personas as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>" <?php echo $personaSel === (int) $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-bold">Tipo</label>
                            <select class="form-select form-select-lg" name="tipo" id="tipoSel">
                                <?php
                                $tipos = ["TODO" => "Todo", "INGRESO" => "Ingresos", "GASTO" => "Gastos", "TRANSFERENCIA" => "Transferencias", "SOBRES" => "Sobres"];
                                foreach ($tipos as $k => $v) {
                                    $sel = ($tipoSel === $k) ? "selected" : "";
                                    echo "<option value=\"$k\" $sel>$v</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-bold">Método</label>
                            <select class="form-select form-select-lg" name="metodo" id="metodoSel">
                                <?php
                                $mets = ["TODO" => "Todos", "Efectivo" => "Efectivo", "Tarjeta" => "Tarjeta"];
                                foreach ($mets as $k => $v) {
                                    $sel = ($metodoSel === $k) ? "selected" : "";
                                    echo "<option value=\"$k\" $sel>$v</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-bold">Desde</label>
                            <input type="date" class="form-control form-control-lg" name="ini"
                                value="<?php echo htmlspecialchars($ini); ?>">
                        </div>

                        <div>
                            <label class="form-label fw-bold">Hasta</label>
                            <input type="date" class="form-control form-control-lg" name="fin"
                                value="<?php echo htmlspecialchars($fin); ?>">
                        </div>

                        <div class="filtros-actions">
                            <button class="btn btn-primary btn-lg w-100" type="submit">
                                <i class="bi bi-funnel-fill me-1"></i>Aplicar
                            </button>
                            <a class="btn btn-outline-primary btn-lg w-100" href="Familia.php">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>

                <!-- KPIs generales -->
                <div class="cards familia-cards">
                    <div class="kpi">
                        <div class="kpi-icon green"><i class="bi bi-safe2-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Total familiar</div>
                            <div class="kpi-value">
                                <?php echo money_crc($totalGeneral); ?>
                            </div>
                            <div class="kpi-sub">Disponible + sobres</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon blue"><i class="bi bi-wallet2"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Disponible</div>
                            <div class="kpi-value">
                                <?php echo money_crc($totalDisponible); ?>
                            </div>
                            <div class="kpi-sub">Efectivo + tarjeta (movible)</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon yellow"><i class="bi bi-inbox-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Sobres</div>
                            <div class="kpi-value">
                                <?php echo money_crc($totalS); ?>
                            </div>
                            <div class="kpi-sub">Dinero guardado</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon <?php echo ($balanceRango >= 0 ? 'green' : 'red'); ?>">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="kpi-body">
                            <div class="kpi-label">Balance del rango</div>
                            <div class="kpi-value">
                                <?php echo money_crc($balanceRango); ?>
                            </div>
                            <div class="kpi-sub">Ingresos - Gastos</div>
                        </div>
                    </div>
                </div>

                <!-- Tendencias -->
                <div class="trend-row">
                    <div class="panel trend">
                        <div>
                            <div class="trend-title">Ingresos del mes</div>
                            <div class="trend-value">
                                <?php echo money_crc($ingMes); ?>
                            </div>
                            <div class="trend-sub">vs mes anterior</div>
                        </div>
                        <span class="trend-badge <?php echo ($diffIng >= 0 ? 'up' : 'down'); ?>">
                            <?php echo ($diffIng >= 0 ? '+' : '-'); ?>
                            <?php echo money_crc(abs($diffIng)); ?>
                            (
                            <?php echo ($diffIng >= 0 ? '+' : '-') . number_format(abs($porIng), 1); ?>%)
                        </span>
                    </div>

                    <div class="panel trend">
                        <div>
                            <div class="trend-title">Gastos del mes</div>
                            <div class="trend-value">
                                <?php echo money_crc($gMes); ?>
                            </div>
                            <div class="trend-sub">vs mes anterior</div>
                        </div>
                        <span class="trend-badge <?php echo ($diffGas <= 0 ? 'up' : 'down'); ?>">
                            <?php
                            // Para gastos: bajar es "bueno" (verde), subir es rojo
                            $good = ($diffGas <= 0);
                            echo ($good ? '-' : '+') . money_crc(abs($diffGas)) . " (" . ($good ? '-' : '+') . number_format(abs($porGas), 1) . "%)";
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Personas: vista robusta -->
                <div class="panel">
                    <div class="panel-head">
                        <h2>Personas (estado actual)</h2>
                        <div class="panel-actions">
                            <button class="btn btn-outline-primary btn-sm" type="button" id="btnExport">
                                <i class="bi bi-download me-1"></i>Exportar tabla
                            </button>
                        </div>
                    </div>

                    <div class="people-grid">
                        <?php foreach ($personas as $p):
                            $disp = (float) $p['efectivo'] + (float) $p['tarjeta'];
                            $tot = $disp + (float) $p['sobres'];
                            $ratio = ($tot > 0) ? ((float) $p['sobres'] / $tot) * 100 : 0;
                            ?>
                            <div class="person-card" data-name="<?php echo htmlspecialchars(strtolower($p['nombre'])); ?>">
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
                                    <button class="btn btn-light btn-sm pc-btn" type="button"
                                        data-set-persona="<?php echo (int) $p['id']; ?>">
                                        Ver
                                    </button>
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
                                    <span class="pc-pill disp">Disponible:
                                        <?php echo money_crc($disp); ?>
                                    </span>
                                </div>

                                <div class="pc-progress">
                                    <div class="pc-prog-top">
                                        <span>Sobres</span>
                                        <span>
                                            <?php echo number_format($ratio, 1); ?>%
                                        </span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                            style="width: <?php echo (int) $ratio; ?>%"></div>
                                    </div>
                                    <div class="pc-prog-sub">Mientras más alto, más dinero está “guardado”.</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Movimientos -->
                <div class="panel">
                    <div class="panel-head">
                        <h2>Movimientos (conectado a ingresos + gastos)</h2>
                        <div class="panel-actions">
                            <button class="btn btn-outline-primary btn-sm" type="button" id="btnRefrescar">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refrescar
                            </button>
                        </div>
                    </div>

                    <?php if (!$existeGastos): ?>
                        <div class="alert alert-warning d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                No encontré la tabla <b>gastos</b>. Familia mostrará ingresos/transferencias/sobres, pero no
                                podrá sumar gastos hasta que exista y esté conectada.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="table-wrap">
                        <table class="table table-fas" id="tablaMov">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Persona</th>
                                    <th>Método</th>
                                    <th>Detalle</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r):
                                    $isGasto = ($r['tipo_raw'] === 'GASTO');
                                    $m = $r['monto'];
                                    $mTxt = money_crc($m);
                                    $clsMonto = $isGasto ? "neg" : (($r['tipo_raw'] === 'INGRESO') ? "pos" : "neu");
                                    ?>
                                    <tr
                                        data-search="<?php echo htmlspecialchars(strtolower($r['tipo'] . ' ' . $r['persona'] . ' ' . $r['detalle'] . ' ' . $r['metodo'] . ' ' . $r['fecha'])); ?>">
                                        <td><span class="badge badge-fas <?php echo $r['badge']; ?>">
                                                <?php echo htmlspecialchars($r['tipo']); ?>
                                            </span></td>
                                        <td>
                                            <?php echo htmlspecialchars($r['persona']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($r['metodo']); ?>
                                        </td>
                                        <td class="text-truncate" style="max-width:420px;">
                                            <?php echo htmlspecialchars($r['detalle']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($r['fecha']); ?>
                                        </td>
                                        <td class="text-end monto <?php echo $clsMonto; ?>">
                                            <?php echo $isGasto ? '- ' : '+ '; ?>
                                            <?php echo $mTxt; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-foot">
                        <div class="muted">Mostrando hasta 150 movimientos (filtrables arriba).</div>
                        <button class="btn btn-primary btn-sm" type="button" id="btnCsv">
                            <i class="bi bi-filetype-csv me-1"></i>CSV
                        </button>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <!-- Offcanvas tips -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="tipsCanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Tips de decisión</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="tip">
                <div class="tip-ico blue"><i class="bi bi-lightbulb"></i></div>
                <div>
                    <div class="tip-title">Disponible vs sobres</div>
                    <div class="tip-sub">Si el disponible baja pero sobres suben, quizá estás guardando demasiado y te
                        falta liquidez.</div>
                </div>
            </div>
            <div class="tip">
                <div class="tip-ico yellow"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="tip-title">Gastos en subida</div>
                    <div class="tip-sub">Si los gastos del mes suben respecto al anterior, revisá categorías y reducí lo
                        “cotidiano”.</div>
                </div>
            </div>
            <div class="tip">
                <div class="tip-ico green"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="tip-title">Balance positivo</div>
                    <div class="tip-sub">Si el balance del rango es positivo, podés definir metas y aumentar sobres por
                        objetivo.</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.FAS_FAMILIA = <?php echo json_encode([
            "personas" => $chartPersonas,
            "moneda" => "CRC"
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/JS/InicioJS.js"></script>
    <script src="../../assets/JS/FamiliaJS.js"></script>
</body>

</html>