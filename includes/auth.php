<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bloque l'accès si l'utilisateur n'est pas connecté
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Bloque l'accès si le rôle de l'utilisateur ne fait pas partie de $roles
function require_role($roles) {
    require_login();
    $roles = (array) $roles;
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2>Accès refusé</h2>
                <p>Vous n\'avez pas la permission d\'accéder à cette page.</p>
                <a href="index.php">Retour au tableau de bord</a>
             </div>');
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_role() {
    return $_SESSION['role'] ?? null;
}
