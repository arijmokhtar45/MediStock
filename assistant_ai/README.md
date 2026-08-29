# MediStock AI Assistant

Ce dossier contient le module IA de MediStock.

## Objectif

Le chatbot doit :
- lire les données de la base en temps réel,
- n’exécuter que des requêtes SQL en lecture seule,
- construire un contexte strict avant d’appeler Groq,
- répondre uniquement sur le périmètre MediStock,
- refuser les demandes hors sujet ou médicales.

## Structure

- `config.php` : configuration Groq et paramètres globaux
- `db_context.php` : récupération des données de la base en temps réel
- `chat.php` : orchestration de la requête et appel Groq
- `guard.php` : contrôles de sécurité et validation
- `widget.php` : rendu HTML du widget

## Sécurité

- lecture seule des tables,
- aucune requête UPDATE/INSERT/DELETE,
- validation CSRF,
- session MediStock obligatoire,
- sortie HTML échappée,
- pas de clé API dans le dépôt Git.
