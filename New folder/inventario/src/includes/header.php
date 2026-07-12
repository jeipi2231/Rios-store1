<?php
require_once __DIR__ . '/auth.php';

$usuario = current_user();
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($titulo_pagina) ? e($titulo_pagina) . ' · ' : '' ?>Sistema de Inventario</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-nav sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php">
      <i class="bi bi-box-seam me-2"></i>Inventario
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='index.php'?'active':'' ?>" href="index.php"><i class="bi bi-graph-up me-1"></i>Reportes</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='products.php'?'active':'' ?>" href="products.php"><i class="bi bi-box me-1"></i>Productos</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='categories.php'?'active':'' ?>" href="categories.php"><i class="bi bi-tags me-1"></i>Categorías</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='suppliers.php'?'active':'' ?>" href="suppliers.php"><i class="bi bi-truck me-1"></i>Proveedores</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='movements.php'?'active':'' ?>" href="movements.php"><i class="bi bi-arrow-left-right me-1"></i>Movimientos</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='cashier.php'?'active':'' ?>" href="cashier.php"><i class="bi bi-upc-scan me-1"></i>Cajero</a></li>
        <?php if (($usuario['rol'] ?? '') === 'admin'): ?>
        <li class="nav-item"><a class="nav-link <?= $pagina_actual==='users.php'?'active':'' ?>" href="users.php"><i class="bi bi-people me-1"></i>Usuarios</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
            <i class="bi bi-person-circle me-1"></i><?= e($usuario['nombre'] ?? 'Invitado') ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e($usuario['email'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<main class="container-fluid py-4">
