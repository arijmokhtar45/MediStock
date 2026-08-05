<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$page_titre = 'Alertes';

if (isset($_GET['traiter'])) {
    $pdo->prepare("UPDATE alertes SET statut = 'traitee', date_traitement = NOW() WHERE id = ?")
        ->execute([$_GET['traiter']]);
    header('Location: alertes.php');
    exit;
}

$filtre = $_GET['filtre'] ?? 'active';
$sql = "SELECT * FROM alertes";
if ($filtre !== 'toutes') {
    $sql .= " WHERE statut = " . ($filtre === 'active' ? "'active'" : "'traitee'");
}
$sql .= " ORDER BY date_creation DESC";
$alertes = $pdo->query($sql)->fetchAll();

$icones = [
    'stock_faible'         => ['bi-box-seam', 'warning'],
    'expiration_proche'    => ['bi-hourglass-split', 'warning'],
    'expiration_depassee'  => ['bi-x-octagon', 'danger'],
    'rupture_prevue_ia'   => ['bi-graph-down-arrow', 'danger'],
];

require 'includes/header.php';
?>

<h4 class="mb-3">Centre d'alertes</h4>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $filtre === 'active' ? 'active' : '' ?>" href="?filtre=active">Actives</a></li>
    <li class="nav-item"><a class="nav-link <?= $filtre === 'traitee' ? 'active' : '' ?>" href="?filtre=traitee">Traitées</a></li>
    <li class="nav-item"><a class="nav-link <?= $filtre === 'toutes' ? 'active' : '' ?>" href="?filtre=toutes">Toutes</a></li>
</ul>

<div class="card p-3">
    <?php if (!$alertes): ?>
        <p class="text-muted text-center py-4">Aucune alerte 🎉</p>
    <?php endif; ?>

    <?php foreach ($alertes as $a): ?>
        <?php [$icone, $couleur] = $icones[$a['type_alerte']] ?? ['bi-bell', 'secondary']; ?>
        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi <?= $icone ?> text-<?= $couleur ?> fs-5"></i>
                <div>
                    <div><?= htmlspecialchars($a['message']) ?></div>
                    <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($a['date_creation'])) ?></div>
                </div>
            </div>
            <?php if ($a['statut'] === 'active'): ?>
                <a href="?traiter=<?= $a['id'] ?>&filtre=<?= $filtre ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-check-lg"></i> Marquer traitée
                </a>
            <?php else: ?>
                <span class="badge bg-secondary">Traitée</span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require 'includes/footer.php'; ?>
