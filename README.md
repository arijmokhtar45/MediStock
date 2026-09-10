# MediStock — Gestion intelligente de pharmacie

Application web (PFE) pour la gestion du stock d’une pharmacie : catalogue, lots FEFO, ventes, fournisseurs / commandes, alertes, prévisions IA et assistant conversationnel Groq.

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Frontend | HTML, CSS, Bootstrap 5.3, Bootstrap Icons, JavaScript (vanilla) |
| Backend | PHP (PDO, sessions) |
| Base de données | MySQL / MariaDB |
| Graphiques | Chart.js |
| Prévisions | Régression linéaire (`includes/ia_engine.php`) |
| Assistant | API Groq (Chat Completions) |
| Serveur local | XAMPP (Apache + MySQL) |

## Rôles et accès

Deux rôles uniquement (`responsable_stock` a été supprimé ; l’admin gère le stock) :

| Module | Administrateur | Pharmacien |
|--------|----------------|------------|
| Dashboard | Oui | Oui |
| Médicaments | CRUD complet | Consultation seule |
| Catégories | CRUD | — |
| Lots (FEFO) | Consultation / suivi | — |
| Ventes (panier, FEFO, ticket, historique) | Oui | Oui |
| Fournisseurs | CRUD | — |
| Commandes + réception | Oui | — |
| Prévisions IA | Oui | — |
| Utilisateurs | CRUD comptes | — |
| Alertes | Oui | Oui |
| Assistant MediStock | Oui | Oui |

**Workflow commandes :** l’administrateur crée une commande fournisseur → à la réception (n° de lot + date d’expiration), les lots sont créés automatiquement → le stock devient disponible pour les ventes du pharmacien (FEFO).

## Installation (XAMPP)

1. **Copier le projet**  
   Placez le dossier dans `C:\xampp\htdocs\MediStock` (ou équivalent).

2. **Importer la base**  
   - Démarrez Apache et MySQL dans XAMPP.  
   - phpMyAdmin → **Importer** :
     - **`medistock.sql`** : dump complet avec données de démo et mots de passe bcrypt prêts ; **recommandé**.
     - ou **`medistock_database.sql`** : schéma propre + données de test (hashes placeholders — créez les mots de passe via *Utilisateurs* après connexion admin, ou réimportez `medistock.sql`).

3. **Base déjà existante avec 3 rôles**  
   Exécutez une fois **`medistock_fix_roles.sql`** pour convertir / retirer `responsable_stock`.

4. **Event scheduler (optionnel)** — alertes d’expiration quotidiennes :
   ```sql
   SET GLOBAL event_scheduler = ON;
   ```

5. **Connexion BDD** — `config/db.php` (par défaut XAMPP) :
   - host `localhost`, base `medistock`, user `root`, mot de passe vide.

6. **Se connecter**  
   `http://localhost/MediStock/login.php`

### Comptes de démo (`medistock.sql`)

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | `admin@medistock.tn` | `admin123` |
| Pharmacien | `pharmacien@medistock.tn` | `pharma123` |

L’ancien compte stock (`stock@medistock.tn`) est désactivé / converti en pharmacien inactif. Créez de nouveaux pharmaciens via **Utilisateurs** (menu admin).

> Si un mot de passe ne fonctionne pas après import, reconnectez-vous en admin (ou via phpMyAdmin) et mettez à jour le hash depuis la page **Utilisateurs**.

## Assistant Groq (optionnel)

1. Créez un fichier `.env` à la racine du projet :
   ```env
   GROQ_API_KEY=VOTRE_CLE_GROQ
   GROQ_MODEL=openai/gpt-oss-20b
   ```
2. Redémarrez Apache.  
3. Le widget apparaît sur les pages authentifiées ; l’API interne est `chatbot_api.php` (session + CSRF).

Le fichier `.env` est ignoré par Git. Alternative legacy : `config/groq.local.php.example` → `config/groq.local.php`.

L’assistant répond uniquement sur MediStock (stock, ventes, commandes, alertes, etc.), en lecture seule. Pas de conseil médical patient, pas d’écriture en base.

## Structure du projet

```
MediStock/
├── config/
│   ├── db.php                      # Connexion PDO
│   ├── groq.php                    # Chatbot Groq + widget
│   └── groq.local.php.example
├── includes/
│   ├── auth.php                    # Login, rôles (is_admin, require_role…)
│   ├── header.php / footer.php     # Navbar selon le rôle
│   └── ia_engine.php               # Prévisions + alertes IA
├── assistant_ai/                   # Module assistant (contexte, guard, chat)
├── assets/css/style.css
├── login.php / logout.php
├── index.php                       # Dashboard
├── medicaments.php / categories.php / lots.php
├── ventes.php / ventes_recherche.php / ticket.php
├── fournisseurs.php / commandes.php
├── alertes.php / previsions.php
├── utilisateurs.php                # Gestion des comptes (admin)
├── chatbot_api.php
├── medistock.sql                   # Dump données + schéma
├── medistock_database.sql          # Schéma + seeds
├── medistock_fix_roles.sql         # Migration 2 rôles
└── medistock_update_triggers.sql   # Recréation triggers / event
```

## Fonctionnalités principales

- **Auth par rôle** : administrateur / pharmacien ; navbar et actions filtrées
- **Gestion utilisateurs** : création de comptes pharmacien (ou admin), activation / désactivation, reset mot de passe
- **Médicaments & catégories** : catalogue, code-barres, prix, seuil de stock
- **Lots FEFO** : lots créés à la réception de commande ; vente priorise la date d’expiration la plus proche
- **Ventes** : recherche, panier, ticket ; triggers SQL pour stock et mouvements
- **Fournisseurs & commandes** : commande → réception partielle ou totale → lots auto
- **Alertes** (triggers + event) : stock faible, expiration proche / dépassée, rupture prévue IA
- **Dashboard** : KPI, ventes 7 jours (Chart.js), top médicaments, alertes récentes
- **Prévisions IA** : régression linéaire sur l’historique mensuel → consommation, jours avant rupture, quantité à commander
- **Assistant Groq** : questions métier MediStock avec contexte SQL contrôlé

## Module IA — prévisions (`previsions.php`)

Pour chaque médicament actif :

1. Historique des ventes agrégées sur **6 mois complets** (le mois en cours est exclu).
2. **Régression linéaire** pour estimer la tendance et la consommation journalière.
3. Estimation des **jours avant rupture** (`stock / conso. journalière`).
4. **Quantité suggérée** : couverture délai fournisseur (7 j) + stock de sécurité (3 j).
5. Si rupture estimée ≤ 30 jours → alerte `rupture_prevue_ia` (aussi vérifiée au chargement du dashboard).

Approche volontairement explicable (pas de boîte noire), adaptée à un PFE.

## Scripts SQL utiles

| Fichier | Usage |
|---------|--------|
| `medistock.sql` | Import complet (données de démo) |
| `medistock_database.sql` | Création / reset schéma + seeds |
| `medistock_fix_roles.sql` | Migration : retirer `responsable_stock` |
| `medistock_update_triggers.sql` | Mettre à jour triggers et event d’expiration |

## Sécurité

- Mots de passe : `password_hash()` / `password_verify()` (bcrypt)
- Requêtes préparées PDO
- `require_login()` / `require_role()` sur les actions sensibles
- `htmlspecialchars()` à l’affichage
- Chatbot : contexte lecture seule, CSRF, clé API côté serveur uniquement

## Licence / contexte

Projet académique (PFE) — gestion intelligente de stock de pharmacie.
