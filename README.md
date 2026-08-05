# MediStock — Gestion intelligente de pharmacie

Conception et développement d'une application web intelligente pour la gestion
du stock d'une pharmacie (PFE).

## Stack technique
- Frontend : HTML, CSS, Bootstrap 5, JavaScript (vanilla)
- Backend : PHP (PDO)
- Base de données : MySQL
- Graphiques : Chart.js
- Serveur local : XAMPP

## Installation (XAMPP)

1. **Copier le projet**
   Placez le dossier `medistock` dans `C:\xampp\htdocs\` (Windows) ou `/opt/lampp/htdocs/` (Linux).

2. **Créer la base de données**
   - Démarrez Apache et MySQL depuis le panneau XAMPP.
   - Ouvrez `http://localhost/phpMyAdmin`.
   - Cliquez sur **Importer** → sélectionnez `medistock_database.sql` → **Exécuter**.
   - Cela crée la base `medistock` avec les 11 tables, les vues, les triggers et des données de test.

3. **Activer l'event scheduler (optionnel, pour les alertes automatiques quotidiennes)**
   Dans phpMyAdmin, onglet SQL :
   ```sql
   SET GLOBAL event_scheduler = ON;
   ```

4. **Configurer les comptes de test**
   Ouvrez `http://localhost/medistock/setup.php` dans le navigateur (une seule fois).
   Cela génère les mots de passe hashés correctement (bcrypt) pour les comptes suivants :

   | Rôle                | Email                       | Mot de passe |
   |----------------------|------------------------------|---------------|
   | Administrateur       | admin@medistock.tn          | admin123      |
   | Pharmacien            | pharmacien@medistock.tn     | pharma123     |
   | Responsable stock     | stock@medistock.tn          | stock123      |

   ⚠️ **Supprimez `setup.php` après cette première exécution.**

5. **Se connecter**
   Allez sur `http://localhost/medistock/login.php` et connectez-vous avec un des comptes ci-dessus.

## Structure du projet

```
medistock/
├── config/
│   └── db.php                 # Connexion PDO à MySQL
├── includes/
│   ├── auth.php                # Authentification et gestion des rôles
│   ├── header.php               # Navbar commune
│   └── footer.php               # Pied de page commun
├── assets/css/style.css        # Styles personnalisés
├── login.php / logout.php       # Authentification
├── setup.php                    # Création des mots de passe de test (à supprimer après usage)
├── index.php                    # Dashboard (statistiques, graphique, alertes)
├── medicaments.php              # CRUD médicaments
├── lots.php                     # Gestion des lots (FEFO)
├── ventes.php                   # Module de vente (panier + FEFO auto)
├── ventes_recherche.php         # Endpoint AJAX de recherche médicament
├── ticket.php                   # Impression du ticket de caisse
├── fournisseurs.php             # CRUD fournisseurs
├── alertes.php                  # Centre d'alertes
├── previsions.php               # Module IA : prévision de stock (moyenne mobile pondérée)
└── medistock_database.sql       # Script complet de la base de données
```

## Fonctionnalités principales

- **Authentification par rôle** : administrateur / pharmacien / responsable de stock
- **Gestion des médicaments** : CRUD complet, catégories, code-barres
- **Gestion des lots (FEFO)** : chaque médicament peut avoir plusieurs lots ; le système
  utilise en priorité le lot le plus proche de la date d'expiration lors d'une vente
- **Ventes** : recherche en temps réel, panier, décrémentation automatique du stock
  (via triggers SQL), impression de ticket
- **Alertes automatiques** (via triggers MySQL) :
  - stock faible
  - expiration proche (≤ 30 jours)
  - expiration dépassée
- **Dashboard** : statistiques clés, graphique des ventes (Chart.js), top médicaments vendus
- **Prévisions IA** : moyenne mobile pondérée sur les 30 derniers jours pour estimer
  la demande future, la date de rupture de stock estimée, et la quantité à commander

## Comment fonctionne le module IA (previsions.php)

Pour chaque médicament :
1. On récupère les ventes journalières des 30 derniers jours.
2. On calcule une **moyenne mobile pondérée** (les jours récents comptent plus que les
   jours anciens) pour obtenir une estimation de la demande journalière.
3. On projette cette moyenne sur les 7 prochains jours.
4. On estime le nombre de jours avant rupture de stock (`stock actuel / moyenne journalière`).
5. On calcule une quantité de commande suggérée qui couvre le délai de livraison
   (7 jours) + un stock de sécurité (3 jours).

Cette approche est volontairement simple et explicable (pas de boîte noire), ce qui la
rend appropriée pour un PFE réalisé en autonomie sur un mois, tout en démontrant une
vraie logique de data analysis appliquée à un cas métier réel.

## Notes de sécurité (à mentionner dans le rapport)
- Mots de passe hashés avec `password_hash()` (bcrypt)
- Requêtes préparées PDO (protection contre les injections SQL)
- Contrôle d'accès par rôle sur chaque action sensible (`require_role()`)
- `htmlspecialchars()` systématique à l'affichage (protection XSS)
