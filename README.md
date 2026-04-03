# Module Google Tag Manager pour WHMCS

Ce module permet d'intégrer facilement Google Tag Manager à votre installation WHMCS avec un suivi e-commerce complet.

## Fonctionnalités

- Intégration automatique du code Google Tag Manager
- Suivi complet des événements e-commerce (GA4 et Universal Analytics)
- Suivi des vues de produits et catégories
- Suivi des ajouts au panier
- Suivi des processus de paiement
- Suivi des achats et conversions
- Suivi des abandons de panier
- Suivi des inscriptions et connexions utilisateurs
- Compatible avec Google Analytics 4 et Universal Analytics

## Installation

1. Téléchargez les fichiers du module
2. Uploadez le dossier `google_tag_manager` dans le répertoire `/modules/addons/` de votre installation WHMCS
3. Activez le module dans Configuration > Modules Complémentaires
4. Configurez votre ID de conteneur GTM dans les paramètres du module

## Configuration

### Dans WHMCS

1. Allez dans Configuration > Modules Complémentaires
2. Trouvez et activez le module "Google Tag Manager"
3. Entrez votre ID de conteneur GTM (format GTM-XXXXXXX)
4. Configurez les autres options selon vos besoins

### Dans Google Tag Manager

1. Créez un nouveau conteneur ou utilisez un existant
2. Configurez des déclencheurs basés sur les événements suivants :
   - `view_item` - Vue d'un produit
   - `view_item_list` - Vue d'une liste de produits/catégorie
   - `add_to_cart` - Ajout au panier
   - `begin_checkout` - Début du processus de paiement
   - `purchase` - Achat complété
   - `sign_up` - Inscription utilisateur
   - `login` - Connexion utilisateur
   - `domain_search` - Recherche de domaine
   - `cart_abandonment` - Abandon de panier

## Événements disponibles

| Événement | Description | Pages |
|-----------|-------------|-------|
| view_item | Vue d'un produit | configureproduct, configuredomains |
| view_item_list | Vue d'une catégorie | products |
| add_to_cart | Ajout au panier | viewcart |
| begin_checkout | Début du processus de paiement | viewcart (checkout) |
| purchase | Achat complété | Après paiement |
| sign_up | Inscription utilisateur | register |
| login | Connexion utilisateur | login |
| domain_search | Recherche de domaine | domainchecker |
| cart_abandonment | Abandon de panier | checkout |

## Structure des données

Le module utilise le format de données compatible avec Google Analytics 4, qui inclut :

- Informations sur les produits (nom, ID, prix, catégorie, etc.)
- Informations sur la transaction (ID, montant, taxes, etc.)
- Informations sur l'utilisateur (ID client, email, etc.)

## Support

Pour toute question ou assistance, veuillez contacter le support.

## Licence

Ce module est distribué sous licence incluse dans ce package.