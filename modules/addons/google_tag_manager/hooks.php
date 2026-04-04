<?php
/**
 * WHMCS Google Tag Manager Module Hooks File
 *
 * Hooks allow you to tie into events that occur within the WHMCS application.
 *
 * This allows you to execute your own code in addition to, or sometimes even
 * instead of that which WHMCS executes by default.
 *
 * @see https://developers.whmcs.com/hooks/
 *
 * @copyright Copyright (c) Websavers Inc 2021-2026
 * @license LICENSE file included in this package
 */

use WHMCS\Database\Capsule;
use WHMCS\User\Client;

if (!defined("WHMCS")) die("This file cannot be accessed directly");

define("MODULENAME", 'google_tag_manager');
  
// Function to retrieve module settings with caching
function gtm_get_module_settings($setting) {
    static $cached_settings = null;
    
    if ($cached_settings === null) {
        $cached_settings = [];
        $results = Capsule::table('tbladdonmodules')
            ->where('module', MODULENAME)
            ->get();
        
        foreach ($results as $row) {
            $cached_settings[$row->setting] = $row->value;
        }
    }
    
    if (empty($setting)) {
        return $cached_settings;
    }
    
    return $cached_settings[$setting] ?? null;
}

// Validate and format price safely
function gtm_format_price($price, $currencyCode, $prefix) {
    // Clean and validate price
    $clean_price = preg_replace('/[^0-9.,]/', '', $price);
    $clean_price = str_replace([',', ' '], '.', $clean_price);
    $clean_price = str_ireplace([$prefix, $currencyCode], '', $clean_price);
    
    return is_numeric($clean_price) ? number_format((float)$clean_price, 2, '.', '') : '0.00';
}

function gtm_ga_module_in_use(){
  $ga_site_tag = Capsule::table('tbladdonmodules')
        ->where('module', 'google_analytics')
        ->where('setting', 'code')
        ->value('value');
        
  $active_addons = Capsule::table('tblconfiguration')
        ->where('setting', 'ActiveAddonModules')
        ->value('value');
        
  $ga_is_active = (strpos($active_addons, 'google_analytics') !== false)? true:false;
        
  return ($ga_is_active && !empty($ga_site_tag))? true:false;
}

// Function to retrieve client information securely
function gtm_get_client_info($clientId = null) {
    if (!$clientId && isset($_SESSION['uid'])) {
        $clientId = filter_var($_SESSION['uid'], FILTER_VALIDATE_INT);
    }
    
    if ($clientId && $clientId > 0) {
        try {
            $client = Client::find($clientId);
            if ($client) {
                return [
                    'client_id' => (int)$client->id,
                    'email' => filter_var($client->email, FILTER_SANITIZE_EMAIL),
                    'first_name' => htmlspecialchars($client->firstname, ENT_QUOTES, 'UTF-8'),
                    'last_name' => htmlspecialchars($client->lastname, ENT_QUOTES, 'UTF-8'),
                    'company' => htmlspecialchars($client->companyname, ENT_QUOTES, 'UTF-8'),
                    'country' => htmlspecialchars($client->country, ENT_QUOTES, 'UTF-8'),
                    'phone' => htmlspecialchars($client->phonenumber, ENT_QUOTES, 'UTF-8')
                ];
            }
        } catch (Exception $e) {
            // Log error for debugging
            error_log('GTM: Error retrieving client info - ' . $e->getMessage());
        }
    }
    
    return null;
}

/** The following two hooks output the code required for GTM to function **/

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $container_id = gtm_get_module_settings('gtm-container-id');
    
    // Validate GTM container ID format
    if (!$container_id || !preg_match('/^GTM-[A-Z0-9]{7}$/', $container_id)) {
        return '';
    }

    return "<!-- Google Tag Manager -->
<script>window.dataLayer = window.dataLayer || [];</script>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','$container_id');</script>
<!-- End Google Tag Manager -->";
});

add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    $container_id = gtm_get_module_settings('gtm-container-id');
    
    // Validate GTM container ID format
    if (!$container_id || !preg_match('/^GTM-[A-Z0-9]{7}$/', $container_id)) {
        return '';
    }
    
    return "<!-- Google Tag Manager (noscript) -->
<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=$container_id'
height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->";
});

/** JavaScript dataLayer Variables **/

add_hook('ClientAreaFooterOutput', 1, function($vars) {

  if ( gtm_get_module_settings('gtm-enable-datalayer') == 'off' ) return '';
  // https://classdocs.whmcs.com/7.6/WHMCS/Billing/Currency.html
  $currency = $vars['activeCurrency']; //obj
  $currencyCode = $currency->code;
  $currencyPrefix = $currency->prefix; 
  $lang = $vars['activeLocale']['languageCode'];

  $itemsArray = array();
  $js_events = '';
  
  // Récupérer les informations client pour le suivi
  $clientInfo = gtm_get_client_info();
  $clientData = [];
  if ($clientInfo) {
      $clientData = [
          'user_id' => $clientInfo['client_id'],
          'user_properties' => [
              'email' => $clientInfo['email'],
              'customer_type' => !empty($clientInfo['company']) ? 'business' : 'individual',
              'country' => $clientInfo['country']
          ]
      ];
  }

  switch($vars['templatefile']){

    case 'configureproduct':

      $productAdded = $vars['productinfo'];
      $selectedCycle = $vars['billingcycle'];
      if ($vars['pricing']['type'] == "onetime") {
        $price = (string)$vars['pricing']['minprice']['simple'];
      } else {
        $price = (string)$vars['pricing']['rawpricing'][$selectedCycle];
      }
      
      // Sécurisation des données produit
      $itemsArray[] = array(
        'item_name'       => htmlspecialchars($productAdded['name'], ENT_QUOTES, 'UTF-8'),
        'item_id'         => (int)$productAdded['pid'],
        'price'           => gtm_format_price($price, $currencyCode, $currencyPrefix),
        'item_category'   => htmlspecialchars($productAdded['group_name'], ENT_QUOTES, 'UTF-8'),
        'item_variant'    => htmlspecialchars($selectedCycle, ENT_QUOTES, 'UTF-8'),
        'quantity'        => 1,
        'currency'        => $currencyCode,
        'item_brand'      => 'WHMCS'
      );
      $event = 'view_item';
      $action = 'configureproduct';

      break;
      
    case 'products':
      // Ajout du suivi des vues de catégorie
      if (isset($vars['products']) && is_array($vars['products']) && !empty($vars['products'])) {
        $categoryName = isset($vars['groupname']) ? $vars['groupname'] : 'Uncategorized';
        
        foreach ($vars['products'] as $product) {
          $itemsArray[] = array(
            'item_name'       => htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'),
            'item_id'         => (int)$product['pid'],
            'price'           => isset($product['pricing']) ? gtm_format_price($product['pricing']['minprice']['price'], $currencyCode, $currencyPrefix) : '0',
            'item_category'   => htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'),
            'quantity'        => 1,
            'currency'        => $currencyCode,
            'item_brand'      => 'WHMCS'
          );
        }
        
        $event = 'view_item_list';
        $action = 'view_category';
      }
      
      break;

    case 'configuredomains':

      if (is_array($vars['domains'])){ //domain config
        foreach($vars['domains'] as $domain){
          if (is_array($domain)){
            $itemsArray[] = array(                        
              'item_name'  => htmlspecialchars($domain['domain'], ENT_QUOTES, 'UTF-8'),
              'item_id'    => 'domain_' . substr(md5($domain['domain']), 0, 8),
              'price'      => gtm_format_price($domain['price'], $currencyCode, $currencyPrefix),
              'item_category' => 'Domain',
              'item_variant'  => htmlspecialchars(ucfirst($domain['type']), ENT_QUOTES, 'UTF-8'),
              'quantity'   => 1,
              'currency'   => $currencyCode,
              'item_brand' => 'WHMCS'
            );
         }
        }
      }
      $event = 'view_item';
      $action = 'configuredomains';

      break;

    case 'viewcart':

      foreach($vars['products'] as $productAdded){
        //https://classdocs.whmcs.com/8.1/WHMCS/View/Formatter/Price.html
        $price = $productAdded['pricing']['baseprice'];
        if (is_object($price)) $price = $price->toNumeric();
        $itemsArray[] = array(                       
          'item_name'    => htmlspecialchars($productAdded['productinfo']['name'], ENT_QUOTES, 'UTF-8'),
          'item_id'      => (int)$productAdded['productinfo']['pid'],
          'price'        => gtm_format_price($price, $currencyCode, $currencyPrefix),
          'item_category' => htmlspecialchars($productAdded['productinfo']['groupname'], ENT_QUOTES, 'UTF-8'),
          'item_variant' => htmlspecialchars($productAdded['billingcycle'], ENT_QUOTES, 'UTF-8'),
          'quantity'     => 1,
          'currency'     => $currencyCode,
          'item_brand'   => 'WHMCS'
        );
        foreach ($productAdded['addons'] as $productAddon) {
          $addonPrice = $productAddon['pricingtext'];
          if (is_object($addonPrice)) $addonPrice= $addonPrice->toNumeric();
          $itemsArray[] = array(
            'item_name'    => htmlspecialchars($productAddon['name'], ENT_QUOTES, 'UTF-8'),
            'item_id'      => (int)$productAddon['addonid'],
            'price'        => gtm_format_price($addonPrice, $currencyCode, $currencyPrefix),
            'item_category' => htmlspecialchars($productAdded['productinfo']['groupname'], ENT_QUOTES, 'UTF-8'),
            'item_category2' => 'Addon',
            'quantity'     => (int)$productAddon['qty'],
            'currency'     => $currencyCode,
            'item_brand'   => 'WHMCS'
          );
        }
      }
      
      // Ajouter les domaines au panier
      if (isset($vars['domains']) && is_array($vars['domains'])) {
        foreach($vars['domains'] as $domain) {
          $itemsArray[] = array(
            'item_name'    => htmlspecialchars($domain['domain'], ENT_QUOTES, 'UTF-8'),
            'item_id'      => 'domain_' . substr(md5($domain['domain']), 0, 8),
            'price'        => gtm_format_price($domain['price'], $currencyCode, $currencyPrefix),
            'item_category' => 'Domain',
            'item_variant' => htmlspecialchars($domain['regperiod'] . ' ' . ($domain['regperiod'] > 1 ? 'Years' : 'Year'), ENT_QUOTES, 'UTF-8'),
            'quantity'     => 1,
            'currency'     => $currencyCode,
            'item_brand'   => 'WHMCS'
          );
        }
      }
      
      // Validation sécurisée de l'action
      $action = $_REQUEST['a'] ?? '';
      if ($action === 'view'){
        $event = 'add_to_cart';
        $action = 'viewcart';
      }
      else if ($action === 'checkout'){
        $event = 'begin_checkout';
        $action = 'checkout';
        
        // Suivi des étapes de paiement
        $js_events .= '
        // Track checkout steps
        dataLayer.push({
          "event": "checkout_progress",
          "ecommerce": {
            "checkout": {
              "actionField": {"step": 1, "option": "Begin Checkout"},
              "products": ' . json_encode($itemsArray) . '
            }
          }
        });';
      }

      $js_events .= '
      // Empty Cart Event
      var emptyCartButton = document.getElementById("btnEmptyCart");
      if (emptyCartButton != null) {
        document.getElementById("btnEmptyCart").onclick = function(){
          dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
          dataLayer.push({
            event: "remove_from_cart",
            ecommerce: { items: ' . json_encode($itemsArray) . ' }
          });
        };
      }
      
      // Track cart abandonment
      window.addEventListener("beforeunload", function(e) {
        if (window.location.href.indexOf("checkout") > -1) {
          dataLayer.push({ ecommerce: null });
          dataLayer.push({
            "event": "cart_abandonment",
            "ecommerce": {
              "items": ' . json_encode($itemsArray) . '
            }
          });
        }
      });';

      break;

  }

  if (!empty($itemsArray) && !empty($event)){
    
    // Fusionner les données client avec l'événement
    $eventArray = array(
      'event'         => $event,
      'eventAction'   => $action,
      'ecommerce'     => array( 'items' => $itemsArray )
    );
    
    if (!empty($clientData)) {
      $eventArray = array_merge($eventArray, $clientData);
    }

    return "<script id='GTM_DataLayer'>
    dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
    " . (gtm_get_module_settings('gtm-debug-mode') == 'on' ? 'console.log("GTM Debug Event:", ' . json_encode($eventArray) . ');' : '') . "
    dataLayer.push(" . json_encode($eventArray) . ");
    " . $js_events . "
</script>";

  }
  
});

/**
 * https://developers.whmcs.com/hooks-reference/shopping-cart/#shoppingcartcheckoutcompletepage
 */
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {

  if ( gtm_get_module_settings('gtm-enable-datalayer') == 'off' ) return '';
    
  // Optimized API call with error handling
  try {
    $res_orders = localAPI('GetOrders', ['id' => (int)$vars['orderid'], 'limit' => 1]);
    if (!$res_orders || !isset($res_orders['orders']['order'][0])) {
      return '';
    }
    $order = $res_orders['orders']['order'][0];
  } catch (Exception $e) {
    error_log('GTM: Error retrieving order - ' . $e->getMessage());
    return '';
  }
  
  $currencyCode = $order['currencysuffix'] ?? '';
  $currencyPrefix = $order['currencyprefix'] ?? '';
  
  // Récupérer les informations client pour le suivi
  $clientInfo = gtm_get_client_info((int)($order['userid'] ?? 0));
  $clientData = [];
  if ($clientInfo) {
      $clientData = [
          'user_id' => $clientInfo['client_id'],
          'user_properties' => [
              'email' => $clientInfo['email'],
              'customer_type' => !empty($clientInfo['company']) ? 'business' : 'individual',
              'country' => $clientInfo['country']
          ]
      ];
  }
	
  $itemsArray = array();
  if (isset($order['lineitems']['lineitem']) && is_array($order['lineitems']['lineitem'])) {
    foreach ($order['lineitems']['lineitem'] as $product){
      $productName = $product['product'] ?? '';
      $p_g_n = explode(' - ', $productName);
      if ( count($p_g_n) == 1 ){ 
        $category = '';
        $name = $productName;
      }
      else if ( count($p_g_n) == 2 ){
        $category = $p_g_n[0];
        $name = $p_g_n[1];
      }
      
      // Déterminer le type de produit
      $productType = 'product';
      if (strpos(strtolower($name), 'domain') !== false) {
        $productType = 'domain';
      } elseif (strpos(strtolower($category), 'addon') !== false) {
        $productType = 'addon';
      }
      
      $itemsArray[] = array(
        'item_name'      => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'item_id'        => (int)($product['relid'] ?? 0),
        'price'          => gtm_format_price($product['amount'] ?? '0', $currencyCode, $currencyPrefix),
        'item_brand'     => 'WHMCS',
        'item_category'  => htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
        'item_category2' => $productType,
        'quantity'       => 1,
        'currency'       => $currencyCode
      );
    }
  }
  
  // Optimized invoice API call
  try {
    $res_invoice = localAPI('GetInvoice', ['invoiceid' => (int)($order['invoiceid'] ?? 0)]);
    $tax = (float)($res_invoice['tax'] ?? 0) + (float)($res_invoice['tax2'] ?? 0);
    $discount = (float)($res_invoice['discount'] ?? 0);
  } catch (Exception $e) {
    $tax = 0;
    $discount = 0;
  }
  
  $shipping = 0;
  
  // Calculer la remise si un code promo est utilisé
  if (!empty($order['promocode'])) {
    $discount = max($discount, 0);
  }
  
  // Get Google Ads settings
  $googleAdsId = gtm_get_module_settings('gtm-google-ads-id');
  $conversionLabel = gtm_get_module_settings('gtm-conversion-label');
  
  // Fusionner les données client avec l'événement
  $eventArray = array(
    'event' => 'purchase',
    'ecommerce' => array(
      'transaction_id'  => (int)($order['id'] ?? 0),
      'affiliation'     => 'WHMCS Orderform',
      'value'           => gtm_format_price($order['amount'] ?? '0', $currencyCode, $currencyPrefix),
      'tax'             => gtm_format_price($tax, $currencyCode, $currencyPrefix),
      'shipping'        => $shipping,
      'currency'        => $currencyCode,
      'coupon'          => htmlspecialchars($order['promocode'] ?? '', ENT_QUOTES, 'UTF-8'),
      'discount'        => gtm_format_price($discount, $currencyCode, $currencyPrefix),
      'payment_type'    => htmlspecialchars($order['paymentmethod'] ?? '', ENT_QUOTES, 'UTF-8'),
      'items'           => $itemsArray
    )
  );
  
  if (!empty($clientData)) {
    $eventArray = array_merge($eventArray, $clientData);
  }

  $conversionScript = '';
  if ($googleAdsId && $conversionLabel) {
    $conversionScript = '
    // Conversion tracking
    dataLayer.push({
      "event": "conversion",
      "send_to": "' . htmlspecialchars($googleAdsId, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($conversionLabel, ENT_QUOTES, 'UTF-8') . '",
      "value": ' . gtm_format_price($order['amount'] ?? '0', $currencyCode, $currencyPrefix) . ',
      "currency": "' . htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8') . '",
      "transaction_id": "' . (int)($order['id'] ?? 0) . '"
    });';
  }

  return "<script id='GTM_DataLayer'>
    dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
    dataLayer.push(" . json_encode($eventArray) . ");
    " . $conversionScript . "
  </script>";
  
});

add_hook('ClientAreaPageRegister', 1, function($vars) {
    
	if ( gtm_get_module_settings('gtm-enable-datalayer') == 'off' ) return '';

	add_hook('ClientAreaFooterOutput', 1, function($vars) {

		return '
		<script id="GTM_DataLayer">
		
			document.querySelectorAll("#inputNewPassword1, #inputNewPassword2, #inputEmail").forEach(field => {
				field.setAttribute("required", "");
			});
			
			document.querySelector("form#frmCheckout input[type=\"submit\"]").onclick = function(e) {
				e.preventDefault();

				const register_form 		= document.getElementById("frmCheckout");
				const inputCountry			= document.querySelector("#inputCountry");
				let first_name              = document.querySelector("#inputFirstName").value;
				let last_name               = document.querySelector("#inputLastName").value;
				let email_address           = document.querySelector("#inputEmail").value;
				let phone_number            = document.querySelector("#inputPhone").value.replace(/\\s+/g, "");
				//let phone_country_code      = document.querySelector(".selected-dial-code").innerHTML;
				let city                    = document.querySelector("#inputCity").value;
				let state                   = document.querySelector("#stateinput").value;
				let country                 = inputCountry.options[inputCountry.selectedIndex].text;
				let postal_code             = document.querySelector("#inputPostcode").value;
				let street_address          = document.querySelector("#inputAddress1").value;

				let company_name            = document.querySelector("#inputCompanyName").value;
				let street_address_2        = document.querySelector("#inputAddress2").value;

        if (first_name && last_name && email_address && phone_number){

          signupEvent = {
            event: "sign_up",
            signupData: {
              method: "WHMCS",
              first_name: first_name,
              last_name: last_name,
              email_address: email_address,
              phone_number: phone_number,
              //phone_country_code: phone_country_code,
              street_address: street_address,
              city: city,
              state: state,
              country: country,
              postal_code: postal_code,
            }
          }

          // Add to Data Layer if available
          if(company_name){ signupEvent.signupData.company_name = company_name; }
          if(street_address_2){ signupEvent.signupData.street_address_2 = street_address_2; }

          // Submit event to Google
          dataLayer.push(signupEvent);

        }

        // Submit form normally
				register_form.submit();

			}

		</script>
		';
	});

});

// Ajout du suivi des vues de produits
add_hook('ClientAreaPageProductDetails', 1, function($vars) {
    if (gtm_get_module_settings('gtm-enable-datalayer') == 'off') return '';
    
    add_hook('ClientAreaFooterOutput', 1, function($vars) {
        if (!isset($vars['product']) || !is_array($vars['product'])) return '';
        
        $product = $vars['product'];
        $currency = $vars['activeCurrency'] ?? null;
        if (!$currency || !is_object($currency)) return '';
        
        $currencyCode = $currency->code ?? '';
        $currencyPrefix = $currency->prefix ?? '';
        
        // Récupérer le prix du produit
        $price = '0';
        if (isset($product['pricing'])) {
            if (isset($product['pricing']['minprice']['price'])) {
                $price = $product['pricing']['minprice']['price'];
            } elseif (isset($product['pricing']['monthly'])) {
                $price = $product['pricing']['monthly'];
            }
        }
        
        $itemData = [
            'item_name' => htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'item_id' => (int)($product['pid'] ?? 0),
            'price' => gtm_format_price($price, $currencyCode, $currencyPrefix),
            'item_category' => htmlspecialchars($product['groupname'] ?? '', ENT_QUOTES, 'UTF-8'),
            'item_brand' => 'WHMCS',
            'quantity' => 1,
            'currency' => $currencyCode
        ];
        
        return '<script id="GTM_DataLayer">
            dataLayer.push({ ecommerce: null });
            dataLayer.push({
                "event": "view_item",
                "ecommerce": {
                    "items": [' . json_encode($itemData) . ']
                }
            });
        </script>';
    });
});

// Suivi des recherches de domaines
add_hook('ClientAreaPageDomainSearch', 1, function($vars) {
    if (gtm_get_module_settings('gtm-enable-datalayer') == 'off') return '';
    
    add_hook('ClientAreaFooterOutput', 1, function($vars) {
        return '<script id="GTM_DataLayer">
            // Track domain search
            document.addEventListener("DOMContentLoaded", function() {
                const searchForms = document.querySelectorAll("form[action*=\'domainchecker\']");
                searchForms.forEach(form => {
                    form.addEventListener("submit", function(e) {
                        const searchTerm = form.querySelector("input[name=\'domain\']").value;
                        if (searchTerm) {
                            dataLayer.push({
                                "event": "domain_search",
                                "search_term": searchTerm
                            });
                        }
                    });
                });
            });
        </script>';
    });
});

// Suivi des connexions client
add_hook('ClientAreaPageLogin', 1, function($vars) {
    if (gtm_get_module_settings('gtm-enable-datalayer') == 'off') return '';
    
    add_hook('ClientAreaFooterOutput', 1, function($vars) {
        return '<script id="GTM_DataLayer">
            document.addEventListener("DOMContentLoaded", function() {
                const loginForm = document.querySelector("form[action*=\'dologin\']");
                if (loginForm) {
                    loginForm.addEventListener("submit", function() {
                        dataLayer.push({
                            "event": "login",
                            "method": "WHMCS"
                        });
                    });
                }
            });
        </script>';
    });
});