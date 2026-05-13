# SaaS Facturation pour Auto-Entrepreneurs

Une application de facturation simpliste et efficace développée avec **Symfony 7**. Elle permet aux entrepreneurs de gérer leur catalogue, leurs clients, et de générer des factures PDF professionnelles avec suivi de paiement.

## 🚀 Fonctionnalités

### MVP (Epic 1 & 2)
- **Authentification complète** : Inscription, connexion, et déconnexion sécurisée.
- **Gestion de Profil** : Configuration de la Raison Sociale, SIRET et IBAN (injectés dynamiquement sur les factures).
- **Dashboard** : Vue d'ensemble du Chiffre d'Affaires et des clients.
- **CRUD Produits/Services** : Gestion complète du catalogue.
- **CRUD Clients** : Répertoire détaillé des clients.
- **Facturation dynamique** : Création de factures avec ajout de lignes dynamiques en JavaScript.
- **Génération PDF** : Export des factures validées au format PDF professionnel (via Dompdf).
- **Suivi des paiements** : Gestion des statuts (Brouillon, Validée, Payée).

### Bonus (Epic 3 & 4)
- **Statistiques** : Graphique en barres de l'évolution du CA mensuel (via Symfony UX Chart.js).
- **Emailing** : Envoi de la facture PDF directement au client par email.
- **Relance Client** : Bouton de relance automatique par email avec rappel des coordonnées bancaires.

## Installation et configuration

### Prérequis
- PHP 8.2+
- Composer
- Docker (pour la base de données PostgreSQL)
- Symfony CLI

### Installation
1. **Cloner le projet**
   ```bash
   git clone https://github.com/PaulOcho26/phase3-symfony-facturation.git
   cd phase3-symfony-facturation