<?php

function medistock_ai_require_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user_id']) || empty($_SESSION['user_email'])) {
        throw new RuntimeException('Session MediStock requise.');
    }
}

function medistock_ai_validate_question(string $question): string
{
    $q = trim($question);
    if ($q === '') {
        throw new InvalidArgumentException('Question vide.');
    }
    if (mb_strlen($q) > MEDISTOCK_AI_MAX_MESSAGE_LENGTH) {
        throw new InvalidArgumentException('La question est trop longue.');
    }
    return $q;
}

function medistock_ai_validate_csrf(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return isset($_SESSION['chatbot_csrf']) && hash_equals($_SESSION['chatbot_csrf'], $token);
}

function medistock_ai_is_medi_stock_question(string $question): bool
{
    $q = mb_strtolower($question);
    $patterns = [
        'medistock', 'medicament', 'médicament', 'stock', 'lot', 'lots', 'commande', 'commandes',
        'fournisseur', 'fournisseurs', 'vente', 'ventes', 'vendeur', 'vendeuse', 'ticket',
        'alerte', 'alertes', 'expiration', 'expirations', 'prévision', 'prevision', 'prévisions',
        'catégorie', 'categorie', 'categories', 'pharmacie', 'reception', 'réception', 'fefo',
        'rupture', 'nom', 'liste', 'details', 'détail', 'historique', 'dashboard', 'tableau',
        'accueil', 'combien', 'nombre', 'total', 'actuel', 'actuels', 'quantite', 'quantités',
        'prix', 'achat', 'achats', 'paiement', 'ca', 'revenu', 'vente du jour', 'vente au nom',
        'nom du vendeur', 'du vendeur', 'au vendeur', 'fournise', 'nommer', 'quels', 'quelle',
        'utilisateur', 'utilisateurs', 'mouvement', 'mouvements', 'résumé', 'resume', 'sommaire',
        'qui', 'quoi', 'quand'
    ];

    foreach ($patterns as $pattern) {
        if (str_contains($q, $pattern)) {
            return true;
        }
    }

    return false;
}
