<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$page_titre = 'Commandes';
$erreur = '';
$succes = '';

// --- Validation d'une nouvelle commande ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['medicaments'])) {
    require_role(['administrateur', 'responsable_stock']);
    
    $fournisseur_id = $_POST['fournisseur_id'];
    $date_prevue = $_POST['date_livraison_prevue'];
    $meds = $_POST['medicaments']; // Array of [id, quantite, prix]
    
    try {
        $pdo->beginTransaction();
        
        $montantTotal = 0;
        foreach ($meds as $m) {
            $montantTotal += $m['quantite'] * $m['prix'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO commandes (fournisseur_id, utilisateur_id, date_livraison_prevue, montant_total, statut) 
                               VALUES (?, ?, ?, ?, 'en_attente')");
        $stmt->execute([$fournisseur_id, current_user_id(), $date_prevue, $montantTotal]);
        $commandeId = $pdo->lastInsertId();
        
        $stmtDetail = $pdo->prepare("INSERT INTO details_commandes (commande_id, medicament_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
        foreach ($meds as $m) {
            $stmtDetail->execute([$commandeId, $m['id'], $m['quantite'], $m['prix']]);
        }
        
        $pdo->commit();
        $succes = "Commande #$commandeId enregistrée avec succès.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $erreur = "Erreur : " . $e->getMessage();
    }
}

// --- Marquer comme livrée ---
if (isset($_GET['livrer'])) {
    require_role(['administrateur', 'responsable_stock']);
    $id = $_GET['livrer'];
    $pdo->prepare("UPDATE commandes SET statut = 'livree', date_livraison_reelle = NOW() WHERE id = ?")->execute([$id]);
    header('Location: commandes.php?msg=livree');
    exit;
}

// --- Liste des commandes ---
$commandes = $pdo->query("
    SELECT c.*, f.nom AS fournisseur_nom, u.nom AS utilisateur_nom 
    FROM commandes c
    JOIN fournisseurs f ON f.id = c.fournisseur_id
    JOIN utilisateurs u ON u.id = c.utilisateur_id
    ORDER BY c.date_commande DESC
")->fetchAll();

$fournisseurs = $pdo->query("SELECT id, nom FROM fournisseurs ORDER BY nom")->fetchAll();
$medicaments = $pdo->query("SELECT id, nom, prix_achat FROM medicaments WHERE actif = 1 ORDER BY nom")->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Commandes Fournisseurs</h4>
    <?php if (in_array(current_role(), ['administrateur', 'responsable_stock'])): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCommande">
            <i class="bi bi-plus-lg"></i> Nouvelle commande
        </button>
    <?php endif; ?>
</div>

<?php if ($succes): ?>
    <div class="alert alert-success py-2"><?= $succes ?></div>
<?php endif; ?>
<?php if ($erreur): ?>
    <div class="alert alert-danger py-2"><?= $erreur ?></div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'livree'): ?>
    <div class="alert alert-info py-2">La commande a été marquée comme livrée.</div>
<?php endif; ?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th>Total</th>
                    <th>Livraison prévue</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($commandes as $c): ?>
                <tr>
                    <td><strong>#<?= $c['id'] ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($c['date_commande'])) ?></td>
                    <td><?= htmlspecialchars($c['fournisseur_nom']) ?></td>
                    <td><?= number_format($c['montant_total'], 3) ?> DT</td>
                    <td><?= $c['date_livraison_prevue'] ? date('d/m/Y', strtotime($c['date_livraison_prevue'])) : '-' ?></td>
                    <td>
                        <?php if ($c['statut'] === 'en_attente'): ?>
                            <span class="badge bg-warning text-dark">En attente</span>
                        <?php elseif ($c['statut'] === 'livree'): ?>
                            <span class="badge bg-success">Livrée</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= $c['statut'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($c['statut'] === 'en_attente' && in_array(current_role(), ['administrateur', 'responsable_stock'])): ?>
                            <a href="commandes.php?livrer=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Confirmer la réception de cette commande ?')">
                                <i class="bi bi-check-lg"></i> Livrée
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$commandes): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucune commande enregistrée</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nouvelle Commande -->
<div class="modal fade" id="modalCommande" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" id="formCommande">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle commande fournisseur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Fournisseur *</label>
                    <select name="fournisseur_id" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($fournisseurs as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date livraison prévue</label>
                    <input type="date" name="date_livraison_prevue" class="form-control">
                </div>
                
                <div class="col-12">
                    <h6>Articles à commander</h6>
                    <div id="listeArticles">
                        <!-- Lignes ajoutées en JS -->
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="ajouterLigne()">
                        <i class="bi bi-plus"></i> Ajouter un médicament
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer la commande</button>
            </div>
        </form>
    </div>
</div>

<template id="tplLigne">
    <div class="row g-2 mb-2 article-ligne">
        <div class="col-md-6">
            <select name="medicaments[INDEX][id]" class="form-select form-select-sm" required>
                <option value="">-- Médicament --</option>
                <?php foreach ($medicaments as $m): ?>
                    <option value="<?= $m['id'] ?>" data-prix="<?= $m['prix_achat'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="medicaments[INDEX][quantite]" class="form-control form-control-sm" placeholder="Qté" required min="1">
        </div>
        <div class="col-md-2">
            <input type="number" step="0.001" name="medicaments[INDEX][prix]" class="form-control form-control-sm" placeholder="Prix" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('.row').remove()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

<script>
let indexArticle = 0;
function ajouterLigne() {
    const container = document.getElementById('listeArticles');
    const template = document.getElementById('tplLigne').innerHTML;
    const html = template.replace(/INDEX/g, indexArticle++);
    container.insertAdjacentHTML('beforeend', html);
    
    // Auto-fill price when medicine is selected
    const select = container.lastElementChild.querySelector('select');
    const inputPrix = container.lastElementChild.querySelector('input[name*="[prix]"]');
    select.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.dataset.prix) {
            inputPrix.value = option.dataset.prix;
        }
    });
}

// Ajouter une ligne par défaut
document.addEventListener('DOMContentLoaded', () => {
    ajouterLigne();
});
</script>

<?php require 'includes/footer.php'; ?>
