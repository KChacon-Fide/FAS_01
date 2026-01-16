<?php
// src/inc/validar.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['fas_user'])) {
  header("Location: ../Inicio/Login.php?err=login");
  exit;
}
