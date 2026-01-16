<?php
// src/acciones/login.php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: ../Inicio/Login.php");
  exit;
}

$correo = trim($_POST['correo'] ?? '');
$clave  = trim($_POST['clave'] ?? '');

if ($correo === '' || $clave === '') {
  header("Location: ../Inicio/Login.php?err=campos");
  exit;
}

$stmt = $cn->prepare("SELECT id, nombre, correo, clave, rol, estado FROM usuarios WHERE correo = ? LIMIT 1");
$stmt->bind_param("s", $correo);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  header("Location: ../Inicio/Login.php?err=datos");
  exit;
}

$u = $res->fetch_assoc();

// Validar estado
if ((int)$u['estado'] !== 1) {
  header("Location: ../Inicio/Login.php?err=estado");
  exit;
}

// Validar contraseña (hash)
if (!password_verify($clave, $u['clave'])) {
  header("Location: ../Inicio/Login.php?err=datos");
  exit;
}

// Sesión (guardamos lo mínimo necesario)
$_SESSION['fas_user'] = [
  'id'     => (int)$u['id'],
  'nombre' => $u['nombre'],
  'correo' => $u['correo'],
  'rol'    => $u['rol'],
];

// Redirigir al Dashboard
header("Location: ../Dashboard/Inicio.php");
exit;
