<?php
// src/acciones/salir.php
session_start();
session_unset();
session_destroy();
header("Location: ../Inicio/Login.php?ok=salio");
exit;
