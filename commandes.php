<?php
require 'config/db.php';
require 'includes/auth.php';
require_role(['administrateur']);

$page_titre = 'Commandes';
$erreur = '';
$succes = '';

// --- Validation d'une nouvelle commande ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['medicaments'])) {
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

// --- Réception d'une commande : création automatique des lots ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recevoir_commande'])) {
    $commandeId = (int) $_POST['recevoir_commande'];
    $receptions = $_POST['receptions'] ?? [];

    try {
        $pdo->beginTransaction();

        // Verrouillage de la commande : une seule réception simultanée est possible.
        $stmtCommande = $pdo->prepare("SELECT id, fournisseur_id, statut FROM commandes WHERE id = ? FOR UPDATE");
        $stmtCommande->execute([$commandeId]);
        $commande = $stmtCommande->fetch();

        if (!$commande) {
            throw new Exception('Commande introuvable.');
        }
        if ($commande['statut'] === 'livree') {
            throw new Exception('Cette commande a déjà été entièrement reçue.');
        }
        if ($commande['statut'] === 'annulee') {
            throw new Exception('Une commande annulée ne peut pas être reçue.');
        }

        // Le trigger d'insertion des lots utilise cette variable pour journaliser
        // l'utilisateur qui a validé la réception, sans dupliquer le mouvement.
        $pdo->prepare('SET @medistock_user_id = ?')->execute([current_user_id()]);

        $stmtDetail = $pdo->prepare("SELECT id, medicament_id, quantite, quantite_recue, prix_unitaire
                                     FROM details_commandes WHERE id = ? AND commande_id = ? FOR UPDATE");
        $stmtLot = $pdo->prepare("INSERT INTO lots
            (medicament_id, fournisseur_id, numero_lot, quantite_initiale, quantite,
             prix_achat_unitaire, date_entree, date_expiration)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)");
        $stmtUpdateDetail = $pdo->prepare("UPDATE details_commandes SET quantite_recue = quantite_recue + ? WHERE id = ?");

        $nbRecus = 0;
        foreach ($receptions as $detailId => $reception) {
            $stmtDetail->execute([(int) $detailId, $commandeId]);
            $detail = $stmtDetail->fetch();
            if (!$detail) {
                throw new Exception('Une ligne de commande est invalide.');
            }

            $quantite = filter_var($reception['quantite'] ?? 0, FILTER_VALIDATE_INT);
            if ($quantite === false || $quantite < 0) {
                throw new Exception('La quantité reçue doit être un entier positif.');
            }
            $reste = (int) $detail['quantite'] - (int) $detail['quantite_recue'];
            if ($quantite > $reste) {
                throw new Exception("La quantité reçue dépasse le reste attendu pour la ligne #{$detailId}.");
            }
            if ($quantite === 0) {
                continue;
            }

            $numeroLot = trim($reception['numero_lot'] ?? '');
            $dateExpiration = $reception['date_expiration'] ?? '';
            $date = DateTime::createFromFormat('Y-m-d', $dateExpiration);
            if ($numeroLot === '' || !$date || $date->format('Y-m-d') !== $dateExpiration) {
                throw new Exception("Le numéro de lot et la date d'expiration sont obligatoires pour la ligne #{$detailId}.");
            }
            if ($dateExpiration < date('Y-m-d')) {
                throw new Exception("La date d'expiration du lot {$numeroLot} est dépassée.");
            }

            // L'INSERT déclenche les alertes existantes et le mouvement d'entrée existant.
            $stmtLot->execute([
                $detail['medicament_id'],
                $commande['fournisseur_id'],
                $numeroLot,
                $quantite,
                $quantite,
                $detail['prix_unitaire'],
                $dateExpiration,
            ]);
            $stmtUpdateDetail->execute([$quantite, $detailId]);
            $nbRecus++;
        }

        if ($nbRecus === 0) {
            throw new Exception('Indiquez au moins une quantité reçue.');
        }

        $stmtReste = $pdo->prepare("SELECT COUNT(*) FROM details_commandes WHERE commande_id = ? AND quantite_recue < quantite");
        $stmtReste->execute([$commandeId]);
        $statut = ((int) $stmtReste->fetchColumn() === 0) ? 'livree' : 'livree_partiellement';

        $stmtStatut = $pdo->prepare("UPDATE commandes SET statut = ?, date_livraison_reelle = CASE WHEN ? = 'livree' THEN CURDATE() ELSE date_livraison_reelle END WHERE id = ?");
        $stmtStatut->execute([$statut, $statut, $commandeId]);

        $pdo->commit();
        header('Location: commandes.php?msg=' . ($statut === 'livree' ? 'livree' : 'partielle'));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erreur = $e->getMessage();
    }
}

// Compatibilité : toute réception passe maintenant par le formulaire détaillé.
$commandeReceptionId = isset($_GET['recevoir']) ? (int) $_GET['recevoir'] : 0;
$detailsReception = [];
if ($commandeReceptionId > 0) {
    $stmt = $pdo->prepare("SELECT dc.*, m.nom AS medicament_nom,
                                  (dc.quantite - dc.quantite_recue) AS quantite_restante
                           FROM details_commandes dc
                           JOIN medicaments m ON m.id = dc.medicament_id
                           JOIN commandes c ON c.id = dc.commande_id
                           WHERE dc.commande_id = ? AND c.statut IN ('en_attente','livree_partiellement')
                           ORDER BY dc.id");
    $stmt->execute([$commandeReceptionId]);
    $detailsReception = $stmt->fetchAll();
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
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCommande">
        <i class="bi bi-plus-lg"></i> Nouvelle commande
    </button>
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
                        <?php if (in_array($c['statut'], ['en_attente', 'livree_partiellement'], true)): ?>
                            <a href="commandes.php?recevoir=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-box-arrow-in-down"></i> Réceptionner
                            </a>
                        <?php elseif ($c['statut'] === 'livree'): ?>
                            <span class="text-success small"><i class="bi bi-check-circle"></i> Reçue</span>
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

<?php if ($commandeReceptionId > 0 && $detailsReception): ?>
<div class="modal fade show" id="modalReception" tabindex="-1" style="display:block;" aria-modal="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title">Réception de la commande #<?= $commandeReceptionId ?></h5>
                <a href="commandes.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">Pour chaque médicament reçu, saisissez la quantité réellement reçue, le numéro du lot et la date d'expiration. Les lignes à quantité zéro ne seront pas réceptionnées.</div>
                <?php foreach ($detailsReception as $d): ?>
                    <div class="card p-3 mb-3">
                        <strong><?= htmlspecialchars($d['medicament_nom']) ?></strong>
                        <span class="text-muted small">Attendu : <?= (int) $d['quantite'] ?> — Déjà reçu : <?= (int) $d['quantite_recue'] ?> — Reste : <?= (int) $d['quantite_restante'] ?></span>
                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <label class="form-label small">Quantité reçue</label>
                                <input type="number" name="receptions[<?= $d['id'] ?>][quantite]" class="form-control" min="0" max="<?= (int) $d['quantite_restante'] ?>" value="<?= (int) $d['quantite_restante'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Numéro du lot</label>
                                <input type="text" name="receptions[<?= $d['id'] ?>][numero_lot]" class="form-control" maxlength="50" placeholder="Ex. LOT-2026-001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Date d'expiration</label>
                                <input type="date" name="receptions[<?= $d['id'] ?>][date_expiration]" class="form-control" min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <a href="commandes.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" name="recevoir_commande" value="<?= $commandeReceptionId ?>" class="btn btn-success"><i class="bi bi-check-lg"></i> Valider la réception</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

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
