<?php
// src/config/db.php

$host = "localhost";
$user = "root";
$pass = "Kenya_05";
$db   = "fas"; // tu BD
$port = 3306;

$cn = new mysqli($host, $user, $pass, $db, $port);
if ($cn->connect_error) {
  die("Error de conexión: " . $cn->connect_error);
}

$cn->set_charset("utf8mb4");
