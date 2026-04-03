<?php
/**
 * WHMCS Google Tag Manager Module
 *
 * Un module complet pour le suivi e-commerce avec Google Tag Manager
 * Compatible avec GA4 et Universal Analytics
 *
 * @see https://developers.whmcs.com/addon-modules/
 *
 * @copyright Copyright (c) Websavers Inc 2021-2026
 * @license LICENSE file included in this package
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define addon module configuration parameters.
 *
 * @return array
 */
function google_tag_manager_config(){
    return [
        'name' => 'Google Tag Manager',
        'description' => 'Suivi e-commerce complet avec Google Tag Manager - inclut automatiquement les codes GTM et fournit le dataLayer nécessaire pour tous les événements e-commerce',
        'author' => 'Websavers Inc.',
        'language' => 'english',
        'version' => '4.0',
        'fields' => [
            'gtm-container-id' => [
                'FriendlyName' => 'ID du conteneur GTM',
                'Type' => 'text',
                'Size' => '15',
                'Placeholder' => 'GTM-0123456',
                'Description' => 'Entrez votre ID de conteneur Google Tag Manager ici.',
            ],
            'gtm-enable-datalayer' => [
                'FriendlyName' => 'Pousser les événements DataLayer automatiquement',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Désactivez cette option si vous utiliserez Google Tag Manager pour créer vos événements et variables DataLayer',
            ],
            'gtm-ga4-compatible' => [
                'FriendlyName' => 'Format compatible GA4',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Utilise le format de données compatible avec Google Analytics 4',
            ],
            'gtm-track-user-data' => [
                'FriendlyName' => 'Suivre les données utilisateur',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Inclut les données utilisateur (ID, email, etc.) dans le dataLayer pour un meilleur suivi',
            ],
            'gtm-google-ads-id' => [
                'FriendlyName' => 'ID Google Ads (optionnel)',
                'Type' => 'text',
                'Size' => '20',
                'Placeholder' => 'AW-XXXXXXXXXX',
                'Description' => 'Entrez votre ID Google Ads pour le suivi des conversions',
            ],
            'gtm-conversion-label' => [
                'FriendlyName' => 'Label de conversion (optionnel)',
                'Type' => 'text',
                'Size' => '20',
                'Placeholder' => 'XXXXXXXXXX',
                'Description' => 'Entrez votre label de conversion Google Ads',
            ],
            'gtm-track-cart-abandonment' => [
                'FriendlyName' => 'Suivre les abandons de panier',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'Envoie un événement lorsqu\'un utilisateur quitte la page de paiement sans finaliser',
            ],
        ]
    ];
}

/**
 * Activation function called when the module is activated.
 *
 * @return array Optional success/failure message
 */
function google_tag_manager_activate() {
    return [
        'status' => 'success',
        'description' => 'Le module Google Tag Manager a été activé avec succès. Veuillez configurer votre ID de conteneur GTM dans les paramètres du module.'
    ];
}

/**
 * Deactivation function called when the module is deactivated.
 *
 * @return array Optional success/failure message
 */
function google_tag_manager_deactivate() {
    return [
        'status' => 'success',
        'description' => 'Le module Google Tag Manager a été désactivé avec succès.'
    ];
}