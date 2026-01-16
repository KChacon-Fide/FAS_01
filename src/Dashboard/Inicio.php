<?php
require_once __DIR__ . '/../inc/validar.php';
$nombre = $_SESSION['fas_user']['nombre'] ?? 'Usuario';
$rol = $_SESSION['fas_user']['rol'] ?? 'miembro';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FAS | Inicio</title>

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Tu CSS -->
    <link rel="stylesheet" href="../../assets/CSS/Iniciocss.css">
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
                <div class="avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($nombre); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($rol); ?></div>
                </div>
            </div>

            <div class="nav-title">Módulos</div>

            <nav class="nav-list">
                <a class="nav-item active" href="#">
                    <i class="bi bi-grid-fill"></i><span>Inicio</span>
                </a>

                <a class="nav-item" href="../Gastos/NuevoGasto.php">
                    <i class="bi bi-receipt"></i><span>Gastos</span>
                    <span class="badge-dot" title="Pendientes"></span>
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
                        <button class="btn btn-filter dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            Todo
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Todo</a></li>
                            <li><a class="dropdown-item" href="#">Gastos</a></li>
                            <li><a class="dropdown-item" href="#">Ingresos</a></li>
                            <li><a class="dropdown-item" href="#">Reportes</a></li>
                        </ul>
                    </div>

                    <input class="form-control search-input" type="text"
                        placeholder="Buscar movimiento, categoría, nota..." />
                    <button class="icon-btn search-btn" type="button" aria-label="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div class="top-actions">
                    <button class="icon-bell" type="button" id="btnNoti" aria-label="Notificaciones">
                        <i class="bi bi-bell-fill"></i>
                        <span class="badge-count" id="notiCount">3</span>
                    </button>

                    <button class="icon-circle yellow" type="button" aria-label="Acción rápida">
                        <i class="bi bi-wrench-adjustable"></i>
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
                        <i class="bi bi-wallet2"></i>
                        <h1>Panel familiar</h1>
                    </div>
                    <p>Resumen de movimientos y control rápido del mes.</p>
                </div>

                <!-- Quick buttons row (como el ejemplo) -->
                <div class="quick-row">
                    <button class="quick-btn primary">
                        <i class="bi bi-plus-circle"></i> Nuevo gasto
                    </button>
                    <button class="quick-btn yellow">
                        <i class="bi bi-plus-circle"></i> Nuevo ingreso
                    </button>
                    <button class="quick-btn primary">
                        <i class="bi bi-calendar-check"></i> Presupuesto
                    </button>
                    <button class="quick-btn primary">
                        <i class="bi bi-file-earmark-text"></i> Reporte
                    </button>
                </div>

                <!-- Cards -->
                <div class="cards">
                    <div class="kpi">
                        <div class="kpi-icon blue"><i class="bi bi-cash-coin"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Ingresos del mes</div>
                            <div class="kpi-value">₡ 0</div>
                            <div class="kpi-sub">+0% vs mes anterior</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon yellow"><i class="bi bi-receipt"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Gastos del mes</div>
                            <div class="kpi-value">₡ 0</div>
                            <div class="kpi-sub">Control diario activo</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon green"><i class="bi bi-pie-chart-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Balance</div>
                            <div class="kpi-value">₡ 0</div>
                            <div class="kpi-sub">Ingresos - Gastos</div>
                        </div>
                    </div>

                    <div class="kpi">
                        <div class="kpi-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="kpi-body">
                            <div class="kpi-label">Alertas</div>
                            <div class="kpi-value">0</div>
                            <div class="kpi-sub">Presupuesto / pagos</div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="panel">
                    <div class="panel-head">
                        <h2>Movimientos recientes</h2>
                        <div class="panel-actions">
                            <button class="btn btn-outline-primary btn-sm" id="btnRefrescar">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refrescar
                            </button>
                            <button class="btn btn-primary btn-sm">
                                <i class="bi bi-download me-1"></i>Exportar
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table fas-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Categoría</th>
                                    <th>Detalle</th>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="movimientosBody">
                                <!-- demo rows -->
                                <tr>
                                    <td><span class="pill pill-exp">Gasto</span></td>
                                    <td>Supermercado</td>
                                    <td>Coca-Cola</td>
                                    <td>Hoy</td>
                                    <td class="text-danger fw-bold">- ₡ 1,200</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="pill pill-inc">Ingreso</span></td>
                                    <td>Trabajo</td>
                                    <td>Pago</td>
                                    <td>Ayer</td>
                                    <td class="text-success fw-bold">+ ₡ 25,000</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="pill pill-exp">Gasto</span></td>
                                    <td>Transporte</td>
                                    <td>Bus</td>
                                    <td>12/01/2026</td>
                                    <td class="text-danger fw-bold">- ₡ 750</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <!-- Offcanvas notificaciones (simple) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notiCanvas" aria-labelledby="notiCanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="notiCanvasLabel">Notificaciones</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="noti-item">
                <div class="noti-dot"></div>
                <div>
                    <div class="noti-title">Presupuesto</div>
                    <div class="noti-sub">Aún no has definido presupuesto mensual</div>
                </div>
            </div>
            <div class="noti-item">
                <div class="noti-dot yellow"></div>
                <div>
                    <div class="noti-title">Recordatorio</div>
                    <div class="noti-sub">Agregá tus gastos diarios de hoy</div>
                </div>
            </div>
            <div class="noti-item">
                <div class="noti-dot green"></div>
                <div>
                    <div class="noti-title">Ingreso</div>
                    <div class="noti-sub">Registrá ingresos extra si aplica</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/JS/InicioJS.js"></script>
</body>

</html>