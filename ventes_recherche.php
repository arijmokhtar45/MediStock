<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.id, m.nom, m.prix_vente, m.necessite_ordonnance, COALESCE(v.quantite_totale,0) AS stock
    FROM medicaments m
    LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
    WHERE m.actif = 1 AND (m.nom LIKE ? OR m.code_barre LIKE ?)
    ORDER BY m.nom
    LIMIT 10
");
$stmt->execute(["%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll());
