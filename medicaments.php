<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$page_titre = 'Médicaments';
$message = '';

// --- Suppression ---
if (isset($_GET['delete'])) {
    require_role(['administrateur', 'responsable_stock']);
    $pdo->prepare("UPDATE medicaments SET actif = 0 WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: medicaments.php?msg=supprime');
    exit;
}

// --- Ajout / Modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['administrateur', 'responsable_stock']);

    $id = $_POST['id'] ?? null;
    $data = [
        $_POST['nom'],
        $_POST['categorie_id'] ?: null,
        $_POST['fabricant'],
        $_POST['forme'],
        $_POST['dosage'],
        $_POST['code_barre'] ?: null,
        $_POST['prix_achat'],
        $_POST['prix_vente'],
        $_POST['quantite_minimale'],
        isset($_POST['necessite_ordonnance']) ? 1 : 0,
        $_POST['description'],
    ];

    if ($id) {
        $sql = "UPDATE medicaments SET nom=?, categorie_id=?, fabricant=?, forme=?, dosage=?, code_barre=?,
                prix_achat=?, prix_vente=?, quantite_minimale=?, necessite_ordonnance=?, description=? WHERE id=?";
        $data[] = $id;
    } else {
        $sql = "INSERT INTO medicaments (nom, categorie_id, fabricant, forme, dosage, code_barre,
                prix_achat, prix_vente, quantite_minimale, necessite_ordonnance, description)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    }
    $pdo->prepare($sql)->execute($data);
    header('Location: medicaments.php?msg=' . ($id ? 'modifie' : 'ajoute'));
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

// --- Recherche ---
$recherche = trim($_GET['q'] ?? '');
$sql = "SELECT m.*, c.nom AS categorie_nom, COALESCE(v.quantite_totale,0) AS stock
        FROM medicaments m
        LEFT JOIN categories c ON c.id = m.categorie_id
        LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
        WHERE m.actif = 1";
$params = [];
if ($recherche !== '') {
    $sql .= " AND (m.nom LIKE ? OR m.code_barre LIKE ?)";
    $params = ["%$recherche%", "%$recherche%"];
}
$sql .= " ORDER BY m.nom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicaments = $stmt->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Gestion des médicaments</h4>
    <?php if (in_array(current_role(), ['administrateur', 'responsable_stock'])): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMedicament" onclick="nouveauMedicament()">
            <i class="bi bi-plus-lg"></i> Nouveau médicament
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success py-2">Opération effectuée avec succès.</div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <form class="row g-2" method="GET">
        <div class="col-md-6">
            <input type="text" name="q" class="form-control" placeholder="Rechercher par nom ou code-barres..." value="<?= htmlspecialchars($recherche) ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Rechercher</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Catégorie</th>
                    <th>Forme / Dosage</th>
                    <th>Prix vente</th>
                    <th>Stock</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($medicaments as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['nom']) ?></strong><br>
                        <span class="text-muted small"><?= htmlspecialchars($m['fabricant']) ?></span></td>
                    <td><?= htmlspecialchars($m['categorie_nom'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['forme']) ?> <?= htmlspecialchars($m['dosage']) ?></td>
                    <td><?= number_format($m['prix_vente'], 3) ?> DT</td>
                    <td><?= (int) $m['stock'] ?></td>
                    <td>
                        <?php if ($m['stock'] <= $m['quantite_minimale']): ?>
                            <span class="badge badge-expire-proche">Stock faible</span>
                        <?php else: ?>
                            <span class="badge badge-ok">OK</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (in_array(current_role(), ['administrateur', 'responsable_stock'])): ?>
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick='editerMedicament(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="medicaments.php?delete=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Supprimer ce médicament ?')">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$medicaments): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucun médicament trouvé</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout/Modification -->
<div class="modal fade" id="modalMedicament" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitre">Nouveau médicament</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="id" id="f_id">
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" id="f_nom" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Catégorie</label>
                    <select name="categorie_id" id="f_categorie_id" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fabricant</label>
                    <input type="text" name="fabricant" id="f_fabricant" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Forme</label>
                    <input type="text" name="forme" id="f_forme" class="form-control" placeholder="Comprimé...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dosage</label>
                    <input type="text" name="dosage" id="f_dosage" class="form-control" placeholder="500mg">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Code-barres</label>
                    <input type="text" name="code_barre" id="f_code_barre" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prix d'achat (DT)</label>
                    <input type="number" step="0.001" name="prix_achat" id="f_prix_achat" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prix de vente (DT)</label>
                    <input type="number" step="0.001" name="prix_vente" id="f_prix_vente" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantité minimale</label>
                    <input type="number" name="quantite_minimale" id="f_quantite_minimale" class="form-control" value="10" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="necessite_ordonnance" id="f_ordonnance">
                        <label class="form-check-label" for="f_ordonnance">Nécessite ordonnance</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="f_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function nouveauMedicament() {
    document.getElementById('modalTitre').innerText = 'Nouveau médicament';
    document.querySelector('#modalMedicament form').reset();
    document.getElementById('f_id').value = '';
}

function editerMedicament(m) {
    document.getElementById('modalTitre').innerText = 'Modifier : ' + m.nom;
    document.getElementById('f_id').value = m.id;
    document.getElementById('f_nom').value = m.nom;
    document.getElementById('f_categorie_id').value = m.categorie_id || '';
    document.getElementById('f_fabricant').value = m.fabricant || '';
    document.getElementById('f_forme').value = m.forme || '';
    document.getElementById('f_dosage').value = m.dosage || '';
    document.getElementById('f_code_barre').value = m.code_barre || '';
    document.getElementById('f_prix_achat').value = m.prix_achat;
    document.getElementById('f_prix_vente').value = m.prix_vente;
    document.getElementById('f_quantite_minimale').value = m.quantite_minimale;
    document.getElementById('f_ordonnance').checked = m.necessite_ordonnance == 1;
    document.getElementById('f_description').value = m.description || '';
    new bootstrap.Modal(document.getElementById('modalMedicament')).show();
}
</script>

<?php require 'includes/footer.php'; ?>
