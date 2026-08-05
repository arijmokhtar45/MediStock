<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$page_titre = 'Ventes';
$erreur = '';
$succes = '';
$derniereVenteId = null;

// --- Validation de la vente ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['panier'])) {
    $panier = json_decode($_POST['panier'], true);

    if (!$panier) {
        $erreur = "Le panier est vide.";
    } else {
        try {
            $pdo->beginTransaction();

            $montantTotal = 0;
            // On calcule le total à partir des prix actuels (sécurité côté serveur)
            $lignes = [];
            foreach ($panier as $item) {
                $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ? AND actif = 1");
                $stmt->execute([$item['id']]);
                $med = $stmt->fetch();
                if (!$med) continue;

                $sousTotal = $med['prix_vente'] * $item['quantite'];
                $montantTotal += $sousTotal;
                $lignes[] = ['medicament' => $med, 'quantite' => (int) $item['quantite']];
            }

            if (!$lignes) {
                throw new Exception("Aucun article valide dans le panier.");
            }

            $stmtVente = $pdo->prepare("INSERT INTO ventes (utilisateur_id, montant_total, mode_paiement, type_document)
                                         VALUES (?, ?, ?, ?)");
            $stmtVente->execute([
                current_user_id(),
                $montantTotal,
                $_POST['mode_paiement'] ?? 'especes',
                $_POST['type_document'] ?? 'ticket',
            ]);
            $venteId = $pdo->lastInsertId();

            foreach ($lignes as $ligne) {
                $medicamentId = $ligne['medicament']['id'];
                $quantiteRestante = $ligne['quantite'];
                $prixVente = $ligne['medicament']['prix_vente'];

                // Sélection FEFO : lots triés par date d'expiration croissante
                $stmtLots = $pdo->prepare("SELECT id, quantite FROM lots
                    WHERE medicament_id = ? AND statut = 'actif' AND quantite > 0
                    ORDER BY date_expiration ASC FOR UPDATE");
                $stmtLots->execute([$medicamentId]);
                $lotsDisponibles = $stmtLots->fetchAll();

                $stockDispo = array_sum(array_column($lotsDisponibles, 'quantite'));
                if ($stockDispo < $quantiteRestante) {
                    throw new Exception("Stock insuffisant pour " . $ligne['medicament']['nom'] .
                        " (disponible: $stockDispo, demandé: $quantiteRestante).");
                }

                $stmtDetail = $pdo->prepare("INSERT INTO details_ventes
                    (vente_id, medicament_id, lot_id, quantite, prix_unitaire, sous_total)
                    VALUES (?,?,?,?,?,?)");

                foreach ($lotsDisponibles as $lot) {
                    if ($quantiteRestante <= 0) break;
                    $prisSurCeLot = min($lot['quantite'], $quantiteRestante);

                    // insertion -> le trigger trg_apres_vente décrémente automatiquement la table lots
                    $stmtDetail->execute([
                        $venteId, $medicamentId, $lot['id'], $prisSurCeLot,
                        $prixVente, $prisSurCeLot * $prixVente
                    ]);

                    $quantiteRestante -= $prisSurCeLot;
                }
            }

            $pdo->commit();
            $succes = "Vente enregistrée avec succès (Ticket #$venteId).";
            $derniereVenteId = $venteId;

        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = $e->getMessage();
        }
    }
}

// --- Historique récent des ventes ---
$historique = $pdo->query("
    SELECT v.*, u.nom, u.prenom FROM ventes v
    JOIN utilisateurs u ON u.id = v.utilisateur_id
    ORDER BY v.date_vente DESC LIMIT 10
")->fetchAll();

require 'includes/header.php';
?>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card p-3">
            <h5 class="mb-3"><i class="bi bi-search"></i> Recherche de médicament</h5>
            <input type="text" id="rechercheMedicament" class="form-control mb-2"
                   placeholder="Nom du médicament ou code-barres..." autocomplete="off">
            <div id="resultatsRecherche" class="list-group"></div>
        </div>

        <div class="card p-3 mt-3">
            <h6>Historique des ventes récentes</h6>
            <table class="table table-sm">
                <thead><tr><th>#</th><th>Date</th><th>Vendeur</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($historique as $h): ?>
                    <tr>
                        <td>#<?= $h['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($h['date_vente'])) ?></td>
                        <td><?= htmlspecialchars($h['nom'] . ' ' . $h['prenom']) ?></td>
                        <td class="text-end"><?= number_format($h['montant_total'], 3) ?> DT</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card p-3">
            <h5 class="mb-3"><i class="bi bi-cart3"></i> Panier</h5>

            <?php if ($erreur): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>
            <?php if ($succes): ?>
                <div class="alert alert-success py-2">
                    <?= htmlspecialchars($succes) ?>
                    <a href="ticket.php?id=<?= $derniereVenteId ?>" target="_blank" class="d-block mt-1">🖨️ Imprimer le ticket</a>
                </div>
            <?php endif; ?>

            <table class="table table-sm" id="tablePanier">
                <thead><tr><th>Médicament</th><th>Qté</th><th>Sous-total</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="d-flex justify-content-between fw-bold border-top pt-2">
                <span>Total</span>
                <span id="totalPanier">0.000 DT</span>
            </div>

            <form method="POST" id="formVente" class="mt-3">
                <input type="hidden" name="panier" id="panierInput">
                <div class="mb-2">
                    <label class="form-label small">Mode de paiement</label>
                    <select name="mode_paiement" class="form-select form-select-sm">
                        <option value="especes">Espèces</option>
                        <option value="carte">Carte</option>
                        <option value="cheque">Chèque</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Type de document</label>
                    <select name="type_document" class="form-select form-select-sm">
                        <option value="ticket">Ticket</option>
                        <option value="facture">Facture</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success w-100" id="btnValider" disabled>
                    <i class="bi bi-check2-circle"></i> Valider la vente
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let panier = [];

const inputRecherche = document.getElementById('rechercheMedicament');
const resultatsDiv = document.getElementById('resultatsRecherche');

inputRecherche.addEventListener('input', async () => {
    const q = inputRecherche.value.trim();
    if (q.length < 2) { resultatsDiv.innerHTML = ''; return; }

    const res = await fetch('ventes_recherche.php?q=' + encodeURIComponent(q));
    const data = await res.json();

    resultatsDiv.innerHTML = data.map(m => `
        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between resultats-item"
                data-medicament="${encodeURIComponent(JSON.stringify(m))}">
            <span>${m.nom} ${m.necessite_ordonnance == 1 ? '⚕️' : ''}</span>
            <span class="text-muted small">${m.prix_vente} DT — stock: ${m.stock}</span>
        </button>
    `).join('') || '<p class="text-muted small mb-0">Aucun résultat</p>';
});

resultatsDiv.addEventListener('click', (event) => {
    const button = event.target.closest('.resultats-item');
    if (!button) return;
    const medicament = JSON.parse(decodeURIComponent(button.dataset.medicament));
    ajouterAuPanier(medicament);
});

function ajouterAuPanier(medicament) {
    const id = parseInt(medicament.id, 10);
    const prix = parseFloat(medicament.prix_vente || medicament.prix) || 0;
    const stock = parseInt(medicament.stock, 10) || 0;
    const existant = panier.find(p => p.id === id);
    if (existant) {
        existant.quantite = parseInt(existant.quantite, 10) + 1;
    } else {
        panier.push({ id, nom: medicament.nom, prix, quantite: 1, stock });
    }
    inputRecherche.value = '';
    resultatsDiv.innerHTML = '';
    afficherPanier();
}

function modifierQuantite(id, delta) {
    const item = panier.find(p => p.id === id);
    if (!item) return;
    const nouvelleQté = parseInt(item.quantite, 10) + delta;
    if (nouvelleQté <= 0) {
        panier = panier.filter(p => p.id !== id);
    } else {
        item.quantite = nouvelleQté;
    }
    afficherPanier();
}

function saisirQuantite(id, valeur) {
    const item = panier.find(p => p.id === id);
    if (!item) return;
    const qte = parseInt(valeur, 10);
    if (isNaN(qte) || qte <= 0) {
        item.quantite = 1;
    } else {
        item.quantite = qte;
    }
    afficherPanier();
}

function afficherPanier() {
    const tbody = document.querySelector('#tablePanier tbody');
    tbody.innerHTML = panier.map(p => `
        <tr>
            <td>${p.nom}</td>
            <td>
                <div class="input-group input-group-sm" style="width: 120px;">
                    <button type="button" class="btn btn-outline-secondary" onclick="modifierQuantite(${p.id},-1)">-</button>
                    <input type="number" class="form-control text-center" value="${p.quantite}" 
                           onchange="saisirQuantite(${p.id}, this.value)" min="1">
                    <button type="button" class="btn btn-outline-secondary" onclick="modifierQuantite(${p.id},1)">+</button>
                </div>
            </td>
            <td>${(p.prix * p.quantite).toFixed(3)} DT</td>
            <td><button type="button" class="btn btn-sm btn-link text-danger" onclick="modifierQuantite(${p.id}, -${p.quantite})">✕</button></td>
        </tr>
    `).join('');

    const total = panier.reduce((sum, p) => sum + p.prix * p.quantite, 0);
    document.getElementById('totalPanier').innerText = total.toFixed(3) + ' DT';
    document.getElementById('btnValider').disabled = panier.length === 0;
}

document.getElementById('formVente').addEventListener('submit', () => {
    document.getElementById('panierInput').value = JSON.stringify(
        panier.map(p => ({ id: p.id, quantite: p.quantite }))
    );
});
</script>

<?php require 'includes/footer.php'; ?>
