<?php
require 'config/db.php';
require 'includes/auth.php';
require_role(['administrateur']);

$page_titre = 'Catégories';

// --- Ajout / Modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = $_POST['id'] ?? null;
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE categories SET nom = ?, description = ? WHERE id = ?");
        $stmt->execute([$nom, $description, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (nom, description) VALUES (?, ?)");
        $stmt->execute([$nom, $description]);
    }
    header('Location: categories.php?msg=succes');
    exit;
}

// --- Suppression ---
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: categories.php?msg=supprime');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Gestion des catégories</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategorie" onclick="nouvelleCategorie()">
        <i class="bi bi-plus-lg"></i> Nouvelle catégorie
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success py-2">Opération effectuée avec succès.</div>
<?php endif; ?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Description</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($c['description']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" 
                                onclick='editerCategorie(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="categories.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" 
                           onclick="return confirm('Supprimer cette catégorie ?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?>
                <tr><td colspan="3" class="text-center text-muted">Aucune catégorie définie</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout/Modification -->
<div class="modal fade" id="modalCategorie" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitre">Nouvelle catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="id" id="f_id">
                <div class="col-12">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" id="f_nom" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="f_description" class="form-control" rows="3"></textarea>
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
function nouvelleCategorie() {
    document.getElementById('modalTitre').innerText = 'Nouvelle catégorie';
    document.querySelector('#modalCategorie form').reset();
    document.getElementById('f_id').value = '';
}

function editerCategorie(c) {
    document.getElementById('modalTitre').innerText = 'Modifier : ' + c.nom;
    document.getElementById('f_id').value = c.id;
    document.getElementById('f_nom').value = c.nom;
    document.getElementById('f_description').value = c.description || '';
    new bootstrap.Modal(document.getElementById('modalCategorie')).show();
}
</script>

<?php require 'includes/footer.php'; ?>
