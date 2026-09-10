<?php
require 'config/db.php';
require 'includes/auth.php';
require_role(['administrateur']);

$page_titre = 'Lots';

// Les lots sont créés automatiquement depuis la réception d'une commande fournisseur.
// Aucune création manuelle n'est autorisée ici afin de garantir la traçabilité du stock.

// --- Liste des lots, triés en FEFO (les plus proches de l'expiration en premier) ---
$filtre = $_GET['filtre'] ?? 'actif';
$sql = "SELECT l.*, m.nom AS medicament_nom, f.nom AS fournisseur_nom,
        DATEDIFF(l.date_expiration, CURDATE()) AS jours_restants
        FROM lots l
        JOIN medicaments m ON m.id = l.medicament_id
        LEFT JOIN fournisseurs f ON f.id = l.fournisseur_id";
if ($filtre === 'actif') {
    $sql .= " WHERE l.statut = 'actif'";
} elseif ($filtre === 'expire') {
    $sql .= " WHERE l.statut = 'expire' OR l.date_expiration < CURDATE()";
}
$sql .= " ORDER BY l.date_expiration ASC";
$lots = $pdo->query($sql)->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Gestion des lots <span class="text-muted small">(ordre FEFO : First Expired, First Out)</span></h4>
    <a href="commandes.php" class="btn btn-primary">
        <i class="bi bi-truck"></i> Réceptionner via une commande
    </a>
</div>

<div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> Les lots sont créés automatiquement lors de la réception d'une commande fournisseur. Le stock est calculé à partir des lots disponibles.</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $filtre === 'actif' ? 'active' : '' ?>" href="?filtre=actif">Actifs</a></li>
    <li class="nav-item"><a class="nav-link <?= $filtre === 'expire' ? 'active' : '' ?>" href="?filtre=expire">Expirés</a></li>
    <li class="nav-item"><a class="nav-link <?= $filtre === 'tous' ? 'active' : '' ?>" href="?filtre=tous">Tous</a></li>
</ul>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>N° Lot</th>
                    <th>Médicament</th>
                    <th>Fournisseur</th>
                    <th>Quantité</th>
                    <th>Date entrée</th>
                    <th>Date expiration</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lots as $l): ?>
                <tr>
                    <td><code><?= htmlspecialchars($l['numero_lot']) ?></code></td>
                    <td><?= htmlspecialchars($l['medicament_nom']) ?></td>
                    <td><?= htmlspecialchars($l['fournisseur_nom'] ?? '-') ?></td>
                    <td><?= (int) $l['quantite'] ?> / <?= (int) $l['quantite_initiale'] ?></td>
                    <td><?= date('d/m/Y', strtotime($l['date_entree'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($l['date_expiration'])) ?></td>
                    <td>
                        <?php if ($l['jours_restants'] < 0): ?>
                            <span class="badge badge-expire">Expiré</span>
                        <?php elseif ($l['jours_restants'] <= 30): ?>
                            <span class="badge badge-expire-proche">Expire dans <?= (int)$l['jours_restants'] ?>j</span>
                        <?php else: ?>
                            <span class="badge badge-ok">Actif</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lots): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucun lot trouvé</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
