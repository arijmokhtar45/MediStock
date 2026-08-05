<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$page_titre = 'Lots';

// --- Ajout d'un nouveau lot (réception de stock) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['administrateur', 'responsable_stock']);

    $stmt = $pdo->prepare("INSERT INTO lots
        (medicament_id, fournisseur_id, numero_lot, quantite_initiale, quantite, prix_achat_unitaire, date_entree, date_expiration)
        VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['medicament_id'],
        $_POST['fournisseur_id'] ?: null,
        $_POST['numero_lot'],
        $_POST['quantite'],
        $_POST['quantite'],
        $_POST['prix_achat_unitaire'],
        $_POST['date_entree'],
        $_POST['date_expiration'],
    ]);
    // Les triggers trg_verifier_expiration_lot et trg_verifier_stock_faible s'occupent des alertes automatiquement
    header('Location: lots.php?msg=ajoute');
    exit;
}

$medicaments = $pdo->query("SELECT id, nom FROM medicaments WHERE actif = 1 ORDER BY nom")->fetchAll();
$fournisseurs = $pdo->query("SELECT id, nom FROM fournisseurs ORDER BY nom")->fetchAll();

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
    <?php if (in_array(current_role(), ['administrateur', 'responsable_stock'])): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalLot">
            <i class="bi bi-plus-lg"></i> Nouveau lot (réception)
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success py-2">Lot ajouté avec succès. Le stock a été mis à jour.</div>
<?php endif; ?>

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

<!-- Modal Nouveau Lot -->
<div class="modal fade" id="modalLot" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title">Réception d'un nouveau lot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Médicament *</label>
                    <select name="medicament_id" class="form-select" required>
                        <?php foreach ($medicaments as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Fournisseur</label>
                    <select name="fournisseur_id" class="form-select">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($fournisseurs as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Numéro de lot *</label>
                    <input type="text" name="numero_lot" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Quantité *</label>
                    <input type="number" name="quantite" class="form-control" required min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prix d'achat unitaire *</label>
                    <input type="number" step="0.001" name="prix_achat_unitaire" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date d'entrée *</label>
                    <input type="date" name="date_entree" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Date d'expiration *</label>
                    <input type="date" name="date_expiration" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
