<?php
require 'config/db.php';
require 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1");
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch();

    if ($utilisateur && password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
        $_SESSION['user_id'] = $utilisateur['id'];
        $_SESSION['nom']     = $utilisateur['nom'] . ' ' . $utilisateur['prenom'];
        $_SESSION['role']    = $utilisateur['role'];

        $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")
            ->execute([$utilisateur['id']]);

        header('Location: index.php');
        exit;
    } else {
        $erreur = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>MediStock - Connexion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page d-flex align-items-center justify-content-center">
    <div class="card login-card shadow">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="login-logo">💊</div>
                <h3 class="fw-bold text-primary">MediStock</h3>
                <p class="text-muted mb-0">Gestion intelligente de pharmacie</p>
            </div>

            <?php if ($erreur): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="mot_de_passe" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>

            <hr>
            <p class="small text-muted mb-0 text-center">
                Pas encore configuré ? Lancez <code>setup.php</code> pour créer les comptes de test.
            </p>
        </div>
    </div>
</body>
</html>
