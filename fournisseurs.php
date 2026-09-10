<?php
require 'config/db.php';
require 'includes/auth.php';
require_role(['administrateur']);

$page_titre = 'Fournisseurs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $data = [$_POST['nom'], $_POST['contact_personne'], $_POST['telephone'], $_POST['email'], $_POST['adresse']];

    if ($id) {
        $sql = "UPDATE fournisseurs SET nom=?, contact_personne=?, telephone=?, email=?, adresse=? WHERE id=?";
        $data[] = $id;
    } else {
        $sql = "INSERT INTO fournisseurs (nom, contact_personne, telephone, email, adresse) VALUES (?,?,?,?,?)";
    }
    $pdo->prepare($sql)->execute($data);
    header('Location: fournisseurs.php?msg=1');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM fournisseurs WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: fournisseurs.php');
    exit;
}

$fournisseurs = $pdo->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll();
require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Fournisseurs</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFournisseur" onclick="nouveauFournisseur()">
        <i class="bi bi-plus-lg"></i> Nouveau fournisseur
    </button>
</div>

<div class="card p-3">
    <table class="table table-hover align-middle">
        <thead><tr><th>Nom</th><th>Contact</th><th>Téléphone</th><th>Email</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($fournisseurs as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['nom']) ?></td>
                <td><?= htmlspecialchars($f['contact_personne'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['telephone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['email'] ?? '-') ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary" onclick='editerFournisseur(<?= json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a href="?delete=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalFournisseur" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="titreModalFournisseur">Nouveau fournisseur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="ff_id">
                <div class="mb-2"><label class="form-label">Nom *</label><input class="form-control" name="nom" id="ff_nom" required></div>
                <div class="mb-2"><label class="form-label">Personne contact</label><input class="form-control" name="contact_personne" id="ff_contact"></div>
                <div class="mb-2"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" id="ff_tel"></div>
                <div class="mb-2"><label class="form-label">Email</label><input class="form-control" name="email" id="ff_email"></div>
                <div class="mb-2"><label class="form-label">Adresse</label><textarea class="form-control" name="adresse" id="ff_adresse"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function nouveauFournisseur() {
    document.getElementById('titreModalFournisseur').innerText = 'Nouveau fournisseur';
    document.querySelector('#modalFournisseur form').reset();
    document.getElementById('ff_id').value = '';
}
function editerFournisseur(f) {
    document.getElementById('titreModalFournisseur').innerText = 'Modifier : ' + f.nom;
    document.getElementById('ff_id').value = f.id;
    document.getElementById('ff_nom').value = f.nom;
    document.getElementById('ff_contact').value = f.contact_personne || '';
    document.getElementById('ff_tel').value = f.telephone || '';
    document.getElementById('ff_email').value = f.email || '';
    document.getElementById('ff_adresse').value = f.adresse || '';
    new bootstrap.Modal(document.getElementById('modalFournisseur')).show();
}
</script>

<?php require 'includes/footer.php'; ?>
