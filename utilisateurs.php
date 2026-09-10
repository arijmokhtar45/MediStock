<?php
require 'config/db.php';
require 'includes/auth.php';
require_role(['administrateur']);

$page_titre = 'Utilisateurs';
$erreur = '';
$succes = '';
$rolesAutorises = ['administrateur', 'pharmacien'];

function utilisateurs_valider_role($role, array $rolesAutorises) {
    return in_array($role, $rolesAutorises, true);
}

// --- Ajout / Modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role = $_POST['role'] ?? '';
    $motDePasse = $_POST['mot_de_passe'] ?? '';
    $actif = isset($_POST['actif']) ? 1 : 0;

    if ($nom === '' || $prenom === '' || $email === '' || !utilisateurs_valider_role($role, $rolesAutorises)) {
        $erreur = 'Veuillez renseigner le nom, le prénom, l\'email et un rôle valide.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Adresse email invalide.';
    } else {
        try {
            // Email unique
            $stmtEmail = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? AND id <> ?');
            $stmtEmail->execute([$email, $id ?? 0]);
            if ($stmtEmail->fetch()) {
                throw new Exception('Cet email est déjà utilisé.');
            }

            if ($id) {
                // Empêcher de se désactiver ou de se retirer le rôle admin soi-même
                if ($id === (int) current_user_id()) {
                    $actif = 1;
                    $role = 'administrateur';
                }

                if ($motDePasse !== '') {
                    if (strlen($motDePasse) < 6) {
                        throw new Exception('Le mot de passe doit contenir au moins 6 caractères.');
                    }
                    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
                    $sql = 'UPDATE utilisateurs SET nom=?, prenom=?, email=?, telephone=?, role=?, actif=?, mot_de_passe=? WHERE id=?';
                    $pdo->prepare($sql)->execute([$nom, $prenom, $email, $telephone ?: null, $role, $actif, $hash, $id]);
                } else {
                    $sql = 'UPDATE utilisateurs SET nom=?, prenom=?, email=?, telephone=?, role=?, actif=? WHERE id=?';
                    $pdo->prepare($sql)->execute([$nom, $prenom, $email, $telephone ?: null, $role, $actif, $id]);
                }
                header('Location: utilisateurs.php?msg=modifie');
                exit;
            }

            if ($motDePasse === '' || strlen($motDePasse) < 6) {
                throw new Exception('Le mot de passe est obligatoire (6 caractères minimum).');
            }
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $sql = 'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, telephone, actif)
                    VALUES (?, ?, ?, ?, ?, ?, ?)';
            $pdo->prepare($sql)->execute([$nom, $prenom, $email, $hash, $role, $telephone ?: null, $actif]);
            header('Location: utilisateurs.php?msg=ajoute');
            exit;
        } catch (Throwable $e) {
            $erreur = $e->getMessage();
        }
    }
}

// --- Activation / désactivation ---
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    if ($id === (int) current_user_id()) {
        $erreur = 'Vous ne pouvez pas désactiver votre propre compte.';
    } else {
        $pdo->prepare('UPDATE utilisateurs SET actif = IF(actif = 1, 0, 1) WHERE id = ?')->execute([$id]);
        header('Location: utilisateurs.php?msg=statut');
        exit;
    }
}

// --- Suppression définitive ---
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id === (int) current_user_id()) {
        $erreur = 'Vous ne pouvez pas supprimer votre propre compte.';
    } else {
        try {
            // Vérifier si l'utilisateur a un historique (ventes, commandes, mouvements)
            $stmtLien = $pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM ventes WHERE utilisateur_id = ?) +
                    (SELECT COUNT(*) FROM commandes WHERE utilisateur_id = ?) +
                    (SELECT COUNT(*) FROM mouvements_stock WHERE utilisateur_id = ?) AS nb'
            );
            $stmtLien->execute([$id, $id, $id]);
            $nbLiens = (int) $stmtLien->fetchColumn();

            if ($nbLiens > 0) {
                // Impossible de supprimer à cause des clés étrangères : on désactive
                $pdo->prepare('UPDATE utilisateurs SET actif = 0 WHERE id = ?')->execute([$id]);
                header('Location: utilisateurs.php?msg=desactive_historique');
                exit;
            }

            $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$id]);
            header('Location: utilisateurs.php?msg=supprime');
            exit;
        } catch (Throwable $e) {
            $erreur = 'Suppression impossible : ' . $e->getMessage();
        }
    }
}

$filtre = $_GET['filtre'] ?? 'actifs';
$sqlUsers = 'SELECT id, nom, prenom, email, role, telephone, actif, date_creation, derniere_connexion
             FROM utilisateurs';
if ($filtre === 'actifs') {
    $sqlUsers .= ' WHERE actif = 1';
} elseif ($filtre === 'inactifs') {
    $sqlUsers .= ' WHERE actif = 0';
}
$sqlUsers .= ' ORDER BY role, nom, prenom';
$utilisateurs = $pdo->query($sqlUsers)->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Gestion des utilisateurs</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUtilisateur" onclick="nouveauUtilisateur()">
        <i class="bi bi-person-plus"></i> Nouvel utilisateur
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'supprime'): ?>
        <div class="alert alert-success py-2">Utilisateur supprimé définitivement.</div>
    <?php elseif ($_GET['msg'] === 'desactive_historique'): ?>
        <div class="alert alert-warning py-2">
            Ce compte a un historique (ventes, commandes ou mouvements) et ne peut pas être effacé.
            Il a été <strong>désactivé</strong> : il n’apparaît plus dans la liste des actifs et ne peut plus se connecter.
        </div>
    <?php else: ?>
        <div class="alert alert-success py-2">Opération effectuée avec succès.</div>
    <?php endif; ?>
<?php endif; ?>
<?php if ($erreur): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<div class="alert alert-info py-2">
    Créez des comptes <strong>Pharmacien</strong> pour qu’ils puissent se connecter via la page de connexion.
    La suppression retire le compte de la base s’il n’a aucun historique ; sinon il est désactivé.
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $filtre === 'actifs' ? 'active' : '' ?>" href="?filtre=actifs">Actifs</a></li>
    <li class="nav-item"><a class="nav-link <?= $filtre === 'inactifs' ? 'active' : '' ?>" href="?filtre=inactifs">Inactifs</a></li>
    <li class="nav-item"><a class="nav-link <?= $filtre === 'tous' ? 'active' : '' ?>" href="?filtre=tous">Tous</a></li>
</ul>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($utilisateurs as $u): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong>
                        <?php if ((int) $u['id'] === (int) current_user_id()): ?>
                            <span class="badge bg-secondary">Vous</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge <?= $u['role'] === 'administrateur' ? 'bg-primary' : 'bg-success' ?>"><?= htmlspecialchars(role_label($u['role'])) ?></span></td>
                    <td><?= htmlspecialchars($u['telephone'] ?? '-') ?></td>
                    <td>
                        <?php if ((int) $u['actif'] === 1): ?>
                            <span class="badge bg-success">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : 'Jamais' ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick='editerUtilisateur(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ((int) $u['id'] !== (int) current_user_id()): ?>
                        <a href="utilisateurs.php?toggle=<?= (int) $u['id'] ?>&filtre=<?= urlencode($filtre) ?>" class="btn btn-sm btn-outline-warning"
                           title="Activer / désactiver"
                           onclick="return confirm('Changer le statut de cet utilisateur ?')">
                            <i class="bi bi-toggle-on"></i>
                        </a>
                        <a href="utilisateurs.php?delete=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-danger"
                           title="Supprimer"
                           onclick="return confirm('Supprimer définitivement ce compte ?')">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$utilisateurs): ?>
                <tr><td colspan="7" class="text-center text-muted">Aucun utilisateur</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalUtilisateur" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitreUser">Nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="id" id="u_id">
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" id="u_nom" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="prenom" id="u_prenom" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" id="u_email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" id="u_telephone" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rôle *</label>
                    <select name="role" id="u_role" class="form-select" required>
                        <option value="pharmacien">Pharmacien</option>
                        <option value="administrateur">Administrateur</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" id="u_mdp_label">Mot de passe *</label>
                    <input type="password" name="mot_de_passe" id="u_mot_de_passe" class="form-control" minlength="6" autocomplete="new-password">
                    <div class="form-text" id="u_mdp_help">Minimum 6 caractères.</div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="actif" id="u_actif" checked>
                        <label class="form-check-label" for="u_actif">Compte actif</label>
                    </div>
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
function nouveauUtilisateur() {
    document.getElementById('modalTitreUser').innerText = 'Nouvel utilisateur';
    document.querySelector('#modalUtilisateur form').reset();
    document.getElementById('u_id').value = '';
    document.getElementById('u_role').value = 'pharmacien';
    document.getElementById('u_actif').checked = true;
    document.getElementById('u_mot_de_passe').required = true;
    document.getElementById('u_mdp_label').innerText = 'Mot de passe *';
    document.getElementById('u_mdp_help').innerText = 'Minimum 6 caractères.';
}

function editerUtilisateur(u) {
    document.getElementById('modalTitreUser').innerText = 'Modifier : ' + u.nom + ' ' + u.prenom;
    document.getElementById('u_id').value = u.id;
    document.getElementById('u_nom').value = u.nom;
    document.getElementById('u_prenom').value = u.prenom;
    document.getElementById('u_email').value = u.email;
    document.getElementById('u_telephone').value = u.telephone || '';
    document.getElementById('u_role').value = u.role;
    document.getElementById('u_actif').checked = u.actif == 1;
    document.getElementById('u_mot_de_passe').value = '';
    document.getElementById('u_mot_de_passe').required = false;
    document.getElementById('u_mdp_label').innerText = 'Nouveau mot de passe';
    document.getElementById('u_mdp_help').innerText = 'Laisser vide pour conserver le mot de passe actuel.';
    new bootstrap.Modal(document.getElementById('modalUtilisateur')).show();
}
</script>

<?php require 'includes/footer.php'; ?>
