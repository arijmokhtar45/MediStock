<?php
// Doit être inclus APRES require_login() et après avoir défini $page_titre
$nombre_alertes = 0;
if (isset($pdo)) {
    $nombre_alertes = (int) $pdo->query("SELECT COUNT(*) AS n FROM alertes WHERE statut = 'active'")->fetch()['n'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediStock - <?= htmlspecialchars($page_titre ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">💊 MediStock</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="medicaments.php"><i class="bi bi-capsule"></i> Médicaments</a></li>
                <li class="nav-item"><a class="nav-link" href="lots.php"><i class="bi bi-boxes"></i> Lots</a></li>
                <li class="nav-item"><a class="nav-link" href="ventes.php"><i class="bi bi-cart-check"></i> Ventes</a></li>
                <li class="nav-item"><a class="nav-link" href="fournisseurs.php"><i class="bi bi-truck"></i> Fournisseurs</a></li>
                <li class="nav-item"><a class="nav-link" href="previsions.php"><i class="bi bi-graph-up-arrow"></i> Prévisions IA</a></li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="alertes.php">
                        <i class="bi bi-bell"></i> Alertes
                        <?php if ($nombre_alertes > 0): ?>
                            <span class="badge rounded-pill bg-danger"><?= $nombre_alertes ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['nom'] ?? '') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars(current_role()) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<main class="container-fluid py-4 px-4">