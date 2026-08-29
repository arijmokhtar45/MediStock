<?php

function medistock_ai_fetch_context(PDO $pdo, string $question, string $page): array
{
    $context = [
        'page' => $page ?: 'inconnue',
        'rules' => 'Le stock est calculé à partir des lots actifs non expirés. Les ventes utilisent la logique FEFO. Les données sont lues en lecture seule.',
        'resume' => $pdo->query("SELECT
            (SELECT COUNT(*) FROM medicaments WHERE actif = 1) AS medicaments_actifs,
            (SELECT COUNT(*) FROM fournisseurs) AS fournisseurs,
            (SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente', 'livree_partiellement')) AS commandes_a_receptionner,
            (SELECT COUNT(*) FROM alertes WHERE statut = 'active') AS alertes_actives,
            (SELECT COALESCE(SUM(montant_total), 0) FROM ventes WHERE DATE(date_vente) = CURDATE()) AS ventes_du_jour")->fetch(PDO::FETCH_ASSOC),
        'fournisseurs' => $pdo->query("SELECT id, nom, contact_personne, telephone, email, adresse FROM fournisseurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC),
        'categories' => $pdo->query("SELECT id, nom, description FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC),
        'medicaments' => $pdo->query("SELECT m.id, m.nom, m.fabricant, m.forme, m.dosage, m.prix_vente, COALESCE(v.quantite_totale, 0) AS stock, c.nom AS categorie
            FROM medicaments m
            LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
            LEFT JOIN categories c ON c.id = m.categorie_id
            WHERE m.actif = 1
            ORDER BY m.nom LIMIT 50")->fetchAll(PDO::FETCH_ASSOC),
        'stock_zero' => $pdo->query("SELECT m.nom, COALESCE(v.quantite_totale, 0) AS stock
            FROM medicaments m
            LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
            WHERE m.actif = 1
            HAVING stock = 0
            ORDER BY m.nom")->fetchAll(PDO::FETCH_ASSOC),
        'lots' => $pdo->query("SELECT l.id, l.numero_lot, m.nom AS medicament, l.quantite, l.date_expiration, l.statut
            FROM lots l
            JOIN medicaments m ON m.id = l.medicament_id
            ORDER BY l.date_expiration ASC, l.quantite DESC
            LIMIT 30")->fetchAll(PDO::FETCH_ASSOC),
        'commandes' => $pdo->query("SELECT c.id, c.statut, c.date_commande, c.date_livraison_prevue, c.montant_total, f.nom AS fournisseur
            FROM commandes c
            JOIN fournisseurs f ON f.id = c.fournisseur_id
            ORDER BY c.date_commande DESC
            LIMIT 20")->fetchAll(PDO::FETCH_ASSOC),
        'ventes' => $pdo->query("SELECT v.id, v.date_vente, v.montant_total, v.mode_paiement, v.type_document, u.nom, u.prenom
            FROM ventes v
            JOIN utilisateurs u ON u.id = v.utilisateur_id
            ORDER BY v.date_vente DESC
            LIMIT 20")->fetchAll(PDO::FETCH_ASSOC),
        'alertes' => $pdo->query("SELECT a.id, a.type_alerte, a.message, a.statut, a.date_creation, m.nom AS medicament
            FROM alertes a
            LEFT JOIN medicaments m ON m.id = a.medicament_id
            ORDER BY a.date_creation DESC
            LIMIT 20")->fetchAll(PDO::FETCH_ASSOC),
        'utilisateurs' => $pdo->query("SELECT id, nom, prenom, email, role, actif FROM utilisateurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC),
    ];

    $isVendorQuestion = preg_match('/(?:vente|ventes|vendeur|vendeuse|au nom du vendeur|au nom de|par le vendeur)/i', $question);
    if ($isVendorQuestion) {
        $nameTerms = preg_split('/\s+/u', preg_replace('/[^\\p{L}\\p{N} ]/u', ' ', $question), -1, PREG_SPLIT_NO_EMPTY);
        $nameTerms = array_values(array_filter($nameTerms, static fn($word) => !in_array(mb_strtolower($word), ['vente','ventes','vendeur','vendeuse','nom','du','de','le','la','les','au','pour','donne','moi','combien','total','qui','quel','quelle','quels','quelles'], true)));

        if ($nameTerms !== []) {
            $namePatterns = [];
            $params = [];
            foreach (array_slice($nameTerms, 0, 6) as $term) {
                $like = '%' . trim($term) . '%';
                $namePatterns[] = 'CONCAT(u.nom, " ", u.prenom) LIKE ?';
                $params[] = $like;
            }

            $sql = "SELECT v.id, v.date_vente, v.montant_total, v.mode_paiement, v.type_document,
                    CONCAT(u.nom, ' ', u.prenom) AS vendeur
                    FROM ventes v
                    JOIN utilisateurs u ON u.id = v.utilisateur_id
                    WHERE " . implode(' OR ', $namePatterns) . "
                    ORDER BY v.date_vente DESC LIMIT 30";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $context['ventes_par_vendeur'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(v.montant_total), 0) AS total
                    FROM ventes v
                    JOIN utilisateurs u ON u.id = v.utilisateur_id
                    WHERE " . implode(' OR ', $namePatterns));
            $sumStmt->execute($params);
            $context['total_ventes_par_vendeur'] = $sumStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (preg_match('/(stock|medicament|médicament|rupture|vide|épuis|epuis|inventaire)/i', $question)) {
        $context['stock_reel'] = $pdo->query("SELECT m.nom, COALESCE(v.quantite_totale, 0) AS stock, m.prix_vente
            FROM medicaments m
            LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
            WHERE m.actif = 1
            ORDER BY stock ASC, m.nom ASC
            LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    }

    $term = trim(preg_replace('/[^\p{L}\p{N} ._-]/u', ' ', $question));
    $words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
    $stopWords = ['quel', 'quelle', 'quels', 'quelles', 'combien', 'comment', 'peux', 'tu', 'je', 'on', 'a', 'de', 'le', 'la', 'les', 'des', 'un', 'une', 'avec', 'dans', 'pour', 'est', 'sont', 'et', 'ou', 'qui', 'quoi', 'leur', 'leurs'];
    $searchWords = array_values(array_filter($words, static fn($word) => mb_strlen($word) >= 3 && !in_array(mb_strtolower($word), $stopWords, true)));

    if ($searchWords) {
        $conditions = [];
        $params = [];
        foreach (array_slice($searchWords, 0, 6) as $word) {
            $conditions[] = '(m.nom LIKE ? OR m.fabricant LIKE ? OR c.nom LIKE ? OR m.code_barre LIKE ? OR f.nom LIKE ? OR u.nom LIKE ?)';
            $like = '%' . $word . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $stmt = $pdo->prepare("SELECT DISTINCT m.nom, m.fabricant, COALESCE(v.quantite_totale, 0) AS stock, c.nom AS categorie,
            f.nom AS fournisseur, u.nom AS utilisateur
            FROM medicaments m
            LEFT JOIN v_stock_medicaments v ON v.medicament_id = m.id
            LEFT JOIN categories c ON c.id = m.categorie_id
            LEFT JOIN lots l ON l.medicament_id = m.id
            LEFT JOIN fournisseurs f ON f.id = l.fournisseur_id
            LEFT JOIN utilisateurs u ON u.id = m.id
            WHERE m.actif = 1 AND (" . implode(' OR ', $conditions) . ")
            ORDER BY m.nom LIMIT 15");
        $stmt->execute($params);
        $context['recherche'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $context;
}
