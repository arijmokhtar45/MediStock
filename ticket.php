<?php
require 'config/db.php';
require 'includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT v.*, u.nom, u.prenom FROM ventes v JOIN utilisateurs u ON u.id = v.utilisateur_id WHERE v.id = ?");
$stmt->execute([$id]);
$vente = $stmt->fetch();
if (!$vente) { die('Vente introuvable.'); }

$stmt = $pdo->prepare("SELECT dv.*, m.nom AS medicament_nom FROM details_ventes dv
                        JOIN medicaments m ON m.id = dv.medicament_id WHERE dv.vente_id = ?");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= $vente['id'] ?></title>
    <style>
        body { font-family: 'Courier New', monospace; max-width: 320px; margin: 20px auto; }
        h3 { text-align: center; margin-bottom: 0; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 3px 0; font-size: 13px; }
        .total { border-top: 1px dashed #000; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h3>💊 MediStock</h3>
    <p class="center">Ticket de caisse<br>#<?= $vente['id'] ?></p>
    <p class="center">
        <?= date('d/m/Y H:i', strtotime($vente['date_vente'])) ?><br>
        Vendeur : <?= htmlspecialchars($vente['nom'] . ' ' . $vente['prenom']) ?>
    </p>
    <hr>
    <table>
        <?php foreach ($lignes as $l): ?>
        <tr>
            <td colspan="3"><?= htmlspecialchars($l['medicament_nom']) ?></td>
        </tr>
        <tr>
            <td><?= $l['quantite'] ?> x <?= number_format($l['prix_unitaire'], 3) ?></td>
            <td colspan="2" style="text-align:right"><?= number_format($l['sous_total'], 3) ?> DT</td>
        </tr>
        <?php endforeach; ?>
        <tr class="total">
            <td colspan="2">TOTAL</td>
            <td style="text-align:right"><?= number_format($vente['montant_total'], 3) ?> DT</td>
        </tr>
    </table>
    <p class="center" style="margin-top:20px;">Merci de votre visite 🙏</p>

    <div class="no-print center" style="margin-top:20px;">
        <button onclick="window.print()">🖨️ Imprimer</button>
    </div>
</body>
</html>
