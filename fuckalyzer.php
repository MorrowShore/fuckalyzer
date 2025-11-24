<?php
/**
 * Plugin Name: Fuckalyzer
 * Description: Detects Wappalyzer extension, then confuses it by injecting false technology signatures.
 * Version: 2.1.0
 * Author: Morrow Shore
 * Author Website: https://morrowshore.com/
 * License: AGPLv3
 */

defined('ABSPATH') || exit;

class WappalyzerConfuser {
    
    private static $instance = null;
    
    // Known technology profiler extensions
    private static $extensions = [
        'wappalyzer' => [
            'id' => 'gppongmhjkpfnbhagpmjfkannfbllamg',
            'file' => 'js/inject.js'
        ],
        'wappalyzer_edge' => [
            'id' => 'mnbndgmknlpdjdnjfmfcdjoegcckoikn', 
            'file' => 'js/inject.js'
        ],
        'builtwith' => [
            'id' => 'dllokjfcpfmgkgfgnblkkbgmkdppfjgj',
            'file' => 'img/bw.png'
        ]
    ];
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Header modifications
        add_action('send_headers', [$this, 'inject_headers']);
        
        // Frontend injections - detector script loads first
        add_action('wp_head', [$this, 'inject_detector_script'], 0);
        
        // Body class filter
        add_filter('body_class', [$this, 'add_body_classes']);
        
        // Set fake cookie
        add_action('init', [$this, 'set_fake_cookies']);
        
        // AJAX endpoint for spoofing data
        add_action('wp_ajax_nopriv_get_wpc_spoof_data', [$this, 'get_spoof_data_callback']);
        add_action('wp_ajax_get_wpc_spoof_data', [$this, 'get_spoof_data_callback']);
    }
    
    /**
     * AJAX callback to serve all spoofing HTML content.
     */
    public function get_spoof_data_callback() {
        // We are separating head and body injections to ensure scripts run correctly
        ob_start();
        $this->inject_meta_tags();
        $this->inject_fake_links();
        $this->inject_css_variables();
        $this->inject_html_patterns();
        $head_html = ob_get_clean();

        ob_start();
        $this->inject_js_globals();
        $this->inject_dom_elements();
        $footer_html = ob_get_clean();

        wp_send_json_success(['head' => $head_html, 'footer' => $footer_html]);
        wp_die();
    }
    
    /**
     * Inject detector script that checks for Wappalyzer and only then activates spoofing
     */
    public function inject_detector_script() {
        $extensions_json = json_encode(self::$extensions);
        ?>
<script id="wpc-detector" data-wpc="1">
(function(){
"use strict";
window.WPC = window.WPC || {};
window.WPC.detected = false;
window.WPC.extensions = <?php echo $extensions_json; ?>;

// Detection method 1: Try to fetch web_accessible_resources
function detectViaFetch(extId, file) {
    return new Promise(function(resolve) {
        var url = 'chrome-extension://' + extId + '/' + file;
        var img = new Image();
        img.onload = function() { resolve(true); };
        img.onerror = function() { 
            // For JS files, try fetch
            if (file.endsWith('.js')) {
                fetch(url, {method:'HEAD',mode:'no-cors'})
                    .then(function() { resolve(true); })
                    .catch(function() { resolve(false); });
            } else {
                resolve(false);
            }
        };
        img.src = url;
        setTimeout(function() { resolve(false); }, 500);
    });
}

// Detection method 2: Check for Wappalyzer's injected script tag
function detectViaDOM() {
    var scripts = document.querySelectorAll('script[src*="wappalyzer"], script[id="wappalyzer"]');
    return scripts.length > 0;
}

// Detection method 3: Check for Wappalyzer message events
function detectViaMessages() {
    return new Promise(function(resolve) {
        var detected = false;
        var handler = function(e) {
            if (e.data && (e.data.id === 'patterns' || e.data.wappalyzer)) {
                detected = true;
                window.WPC.detected = true;
                window.removeEventListener('message', handler);
                resolve(true);
            }
        };
        window.addEventListener('message', handler);
        setTimeout(function() {
            window.removeEventListener('message', handler);
            resolve(detected);
        }, 1000);
    });
}

// Run all detection methods
async function detectExtensions() {
    // Check DOM first (fastest)
    if (detectViaDOM()) {
        window.WPC.detected = true;
        window.WPC.activateSpoofing();
        return;
    }
    
    // Try to detect via resource probing
    for (var name in window.WPC.extensions) {
        var ext = window.WPC.extensions[name];
        try {
            var found = await detectViaFetch(ext.id, ext.file);
            if (found) {
                window.WPC.detected = true;
                window.WPC.detectedExtension = name;
                window.WPC.activateSpoofing();
                return;
            }
        } catch(e) {}
    }
    
    // Listen for message-based detection
    detectViaMessages().then(function(found) {
        if (found) {
            window.WPC.activateSpoofing();
        }
    });
}

// Activate spoofing - fetch and inject spoofing HTML
window.WPC.activateSpoofing = function() {
    if (window.WPC.spoofingActive) return;
    window.WPC.spoofingActive = true;
    
    console.log('[WPC] Technology profiler detected, activating spoofing...');
    
    fetch('/wp-admin/admin-ajax.php?action=get_wpc_spoof_data')
        .then(function(response) { return response.json(); })
        .then(function(json) {
            if (json.success && json.data) {
                // Inject head elements
                if (json.data.head) {
                    document.head.insertAdjacentHTML('beforeend', json.data.head);
                }
                // Inject footer elements
                if (json.data.footer) {
                    document.body.insertAdjacentHTML('beforeend', json.data.footer);
                    // Manually find and execute our inline scripts
                    var scripts = document.body.querySelectorAll('#wpc-js-spoof');
                    scripts.forEach(function(script) {
                         if (script.text) {
                            try {
                                new Function(script.text)();
                            } catch (e) {
                                console.error('[WPC] Error executing spoof script', e);
                            }
                        }
                    });
                }
                 console.log('[WPC] Spoofing activated.');
            }
        })
        .catch(function(error) {
            console.error('[WPC] Failed to fetch spoofing data:', error);
        });
};

// Start detection after DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', detectExtensions);
} else {
    detectExtensions();
}

// Also check periodically for late-loading extensions
setTimeout(detectExtensions, 2000);
setTimeout(detectExtensions, 5000);

})();
</script>
        <?php
    }
    
    /**
     * Set fake cookies for detection
     */
    public function set_fake_cookies() {
        if (headers_sent()) return;
        
        // Laravel
        if (!isset($_COOKIE['laravel_session'])) {
            setcookie('laravel_session', 'wpc_' . bin2hex(random_bytes(20)), 0, '/');
        }
        // Symfony
        if (!isset($_COOKIE['sf_redirect'])) {
            setcookie('sf_redirect', '{}', 0, '/');
        }
        // Java
        if (!isset($_COOKIE['JSESSIONID'])) {
            setcookie('JSESSIONID', 'WPC' . strtoupper(bin2hex(random_bytes(16))), 0, '/');
        }
        // ASP.NET
        if (!isset($_COOKIE['ASP.NET_SessionId'])) {
            setcookie('ASP.NET_SessionId', 'wpc' . bin2hex(random_bytes(12)), 0, '/');
        }
        // OpenCart
        if (!isset($_COOKIE['OCSESSID'])) {
            setcookie('OCSESSID', bin2hex(random_bytes(16)), 0, '/');
        }
        // Zend
        if (!isset($_COOKIE['ZendServerSessionId'])) {
            setcookie('ZendServerSessionId', bin2hex(random_bytes(16)), 0, '/');
        }
        // Cloudflare (legacy)
        if (!isset($_COOKIE['__cfduid'])) {
            setcookie('__cfduid', 'd' . bin2hex(random_bytes(22)), 0, '/');
        }
        // Wix
        if (!isset($_COOKIE['domain'])) {
            setcookie('domain', '.wix.com', 0, '/');
        }
        // Azure
        if (!isset($_COOKIE['arraffinity'])) {
            setcookie('arraffinity', 'wpc_fake_arraffinity', 0, '/');
        }
        if (!isset($_COOKIE['tipmix'])) {
            setcookie('tipmix', 'wpc_fake_tipmix', 0, '/');
        }
        // Mediavine
        if (!isset($_COOKIE['mediavine_session'])) {
            setcookie('mediavine_session', '1', 0, '/');
        }
        // Microsoft Advertising
        if (!isset($_COOKIE['_uetsid'])) {
            setcookie('_uetsid', 'wpc_fake_uetsid', 0, '/');
        }
        if (!isset($_COOKIE['_uetvid'])) {
            setcookie('_uetvid', 'wpc_fake_uetvid', 0, '/');
        }
    }
    
    /**
     * Inject fake HTTP headers
     */
    public function inject_headers() {
        if (headers_sent()) return;
        
        // Vercel
        header('x-vercel-cache: HIT', false);
        header('x-vercel-id: fra1::wpc-' . substr(md5(time()), 0, 8), false);
        
        // PHP + Next.js + Express
        header('x-powered-by: PHP/8.3.0, next.js 14.1.0, Express', false);
        
        // Drupal
        header('x-drupal-cache: HIT', false);
        header('x-generator: Drupal 10.2.0', false);
        header('expires: Sun, 19 Nov 1978 05:00:00 GMT', false);
        
        // WordPress API
        header('link: <' . home_url('/wp-json/') . '>; rel="https://api.w.org/"', false);
        header('x-pingback: ' . home_url('/xmlrpc.php'), false);
        
        // ASP.NET
        header('x-aspnet-version: 4.0.30319', false);
        
        // Python
        header('server: Python/3.12.0 gunicorn/21.0.0', false);
        
        // Ruby
        header('server: Phusion Passenger/6.0.0 Ruby/3.2.0', false);
        
        // Cloudflare
        header('cf-cache-status: HIT', false);
        header('cf-ray: ' . substr(md5(microtime()), 0, 16) . '-FRA', false);
        
        // Medium
        header('x-powered-by: Medium', false);
        
        // Ghost
        header('x-ghost-cache-status: HIT', false);
        
        // Strapi
        header('x-powered-by: strapi', false);
        
        // WP Rocket
        header('x-powered-by: wp rocket', false);
        header('x-rocket-nginx-bypass: true', false);
        
        // WordPress Super Cache
        header('wp-super-cache: HIT', false);
        
        // Google PageSpeed
        header('x-mod-pagespeed: 1.14.33.1-0', false);
        header('x-page-speed: 1.14.33.1-0', false);
        
        // Wix
        header('x-wix-renderer-server: wix', false);
        header('x-wix-request-id: wpc-fake-id', false);
        header('x-wix-server-artifact-id: wpc-fake-artifact', false);
        
        // Hostinger
        header('platform: hostinger', false);
        
        // Netlify
        header('server: netlify', false);
        header('x-nf-request-id: wpc-fake-request-id', false);
        // PythonAnywhere
        header('server: pythonanywhere', false);
        // Railway
        header('server: railway', false);
        // Render
        header('x-render-origin-server: render', false);
        // SiteGround
        header('host-header: 6b7412fb82ca5edfd0917e3957f05d89', false);
        // Vercel
        header('x-now-trace: fra1', false);
        // WP Engine
        header('wpe-backend: apache', false);
        header('x-pass-why: wpc-fake-reason', false);
        header('x-powered-by: WP Engine', false);
        header('x-wpe-loopback-upstream-addr: 127.0.0.1', false);
        // WordPress VIP
        header('x-powered-by: WordPress VIP', false);
        // Amazon Web Services
        header('x-amz-request-id: WPC_FAKE_REQUEST_ID', false);
        header('x-amz-id-2: WPCfAkEId2', false);
        // Azure
        header('azure-regionname: West US', false);
        header('azure-sitename: wpc-fake-site', false);
        header('server: Windows-Azure', false);
        header('x-ms-request-id: wpc-fake-ms-request', false);
        // GitHub Pages
        header('server: GitHub.com', false);
        header('x-github-request-id: FAKE-ID', false);
        // Heroku
        header('via: 1.1 vegur', false);
        // Linkedin Ads
        header('content-security-policy: upgrade-insecure-requests; frame-ancestors *; report-uri /csp-report; default-src https:; script-src https: \'unsafe-inline\' \'unsafe-eval\' *.linkedin.com px.ads.linkedin.com; style-src https: \'unsafe-inline\';', false);
    }
    
    /**
     * Add body classes for detection
     */
    public function add_body_classes($classes) {
        $classes[] = 'fl-builder'; // Beaver Builder
        $classes[] = 'astra-'; // Astra
        return $classes;
    }
    
    /**
     * Inject fake meta generator tags
     */
    public function inject_meta_tags() {
        $generators = [
            'elementor 3.18.0',
            'divi v.4.24.0',
            'Drupal 10.2.0',
            'WordPress 6.4.2',
            'easy digital downloads v3.2.0',
            'ghost 5.0.0', // Ghost
            'wix.com website builder', // Wix
            'hostinger website builder', // Hostinger Website Builder
            'ionos mywebsite 8', // MyWebsite
            'vertex v.1.0.0', // Vertex
        ];
        
        foreach ($generators as $gen) {
            echo '<meta name="generator" content="' . esc_attr($gen) . '">' . "\n";
        }
        
        // React create-react-app
        echo '<meta name="description" content="web site created using create-react-app" data-wpc="1">' . "\n";
        
        // Flutter
        echo '<meta name="id" content="flutterweb-theme" data-wpc="1">' . "\n";
        
        // Lovable
        echo '<meta name="author" content="lovable" data-wpc="1">' . "\n";
        
        // ShareAholic WordPress
        echo '<meta name="shareaholic:wp_version" content="6.4.2" data-wpc="1">' . "\n";
        
        // Ruby on Rails CSRF
        echo '<meta name="csrf-param" content="authenticity_token" data-wpc="1">' . "\n";
        
        // PayPal
        echo '<meta name="id" content="in-context-paypal-metadata" data-wpc="1">' . "\n";
    }
    
    /**
     * Inject HTML patterns directly
     */
    public function inject_html_patterns() {
        // WordPress patterns
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/flavor/style.css')) . '" data-wpc="1">' . "\n";
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/plugins/elementor/assets/css/frontend.min.css')) . '" data-wpc="1">' . "\n";
        echo '<link href="https://s0.wp.com/wp-content/themes/flavor/style.css" rel="prefetch" data-wpc="1">' . "\n";
        
        // Drupal
        echo '<link rel="prefetch" href="/sites/default/themes/flavor/style.css" data-wpc="1">' . "\n";
        echo '<link rel="prefetch" href="/sites/all/modules/flavor/style.css" data-wpc="1">' . "\n";
        
        // ASP.NET viewstate (hidden)
        echo '<input type="hidden" name="__VIEWSTATE" value="wpc_fake_viewstate" data-wpc="1">' . "\n";
        
        // Twenty Twenty-Five
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/twentytwentyfive/style.css')) . '" data-wpc="1">' . "\n";
        
        // Twenty Twenty
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/twentytwenty/style.css')) . '" data-wpc="1">' . "\n";
        echo '<link id="twentytwenty-style-css" rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/twentytwenty/style.css')) . '" data-wpc="1">' . "\n";
        
        // Twenty Ten
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/twentyten/style.css')) . '" data-wpc="1">' . "\n";
        
        // Vertex
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/vertex/style.css')) . '" data-wpc="1">' . "\n";
        
        // Astra
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/astra/style.css')) . '" data-wpc="1">' . "\n";
        
        // Enigma
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/enigma/style.css')) . '" data-wpc="1">' . "\n";
        
        // Kadence WP Kadence
        echo '<link id="kadence-global-css" rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/kadence/style.css')) . '" data-wpc="1">' . "\n";
        
        // SitePoint
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-content/themes/sitepoint-base/style.css')) . '" data-wpc="1">' . "\n";
        // Linkedin Ads & Yahoo Tag Manager
        echo '<!-- wpc_fake_linkedin_pixel --><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=12345&fmt=gif" />' . "\n";
        echo '<!-- Yahoo! Tag Manager -->' . "\n";
    }
    
    /**
     * Inject fake link/style elements
     */
    public function inject_fake_links() {
        // Elementor
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/plugins/elementor/assets/css/frontend.min.css')) . '" data-wpc="1">' . "\n";
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/plugins/elementor-pro/assets/css/frontend.min.css')) . '" data-wpc="1">' . "\n";
        
        // Otter Blocks
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/plugins/otter-blocks/assets/css/style.css')) . '" data-wpc="1">' . "\n";
        
        // Beaver Builder
        echo '<link id="fl-builder-layout" rel="prefetch" href="#" data-wpc="1">' . "\n";
        
        // Divi
        echo '<style id="divi-style-parent-inline-inline-css" data-wpc="1">/* Version: 4.24.0 */</style>' . "\n";
        
        // Lovable
        echo '<link rel="prefetch" href="' . esc_url(home_url('/lovable-uploads/image.webp')) . '" data-wpc="1">' . "\n";
        
        // Gravity Forms
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/plugins/gravityforms/css/formreset.min.css')) . '" data-wpc="1">' . "\n";
        
        // Payoneer
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/plugins/wc-payoneer-payment-gateway/style.css')) . '" data-wpc="1">' . "\n";
        echo '<link id="payoneer-plugn-css" rel="prefetch" href="#" data-wpc="1">' . "\n";
        
        // OpenCart
        echo '<link rel="prefetch" href="/catalog/view/theme/rgen-opencart/style.css" data-wpc="1">' . "\n";
        
        // WP Rocket
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/plugins/wp-rocket/style.css')) . '" data-wpc="1">' . "\n";
        echo '<style id="wpr-usedcss" data-wpc="1">/* WP Rocket CSS */</style>' . "\n";
        
        // Twenty Twenty-Five
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/twentytwentyfive/')) . '" data-wpc="1">' . "\n";
        
        // Twenty Twenty
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/twentytwenty/')) . '" data-wpc="1">' . "\n";
        
        // Twenty Ten
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/twentyten/')) . '" data-wpc="1">' . "\n";
        
        // Vertex
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/vertex/js/')) . '" data-wpc="1">' . "\n";
        
        // Astra
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/astra/')) . '" data-wpc="1">' . "\n";
        
        // Enigma
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/enigma/')) . '" data-wpc="1">' . "\n";
        
        // Kadence WP Kadence
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/kadence/js/navigation.min.js')) . '" data-wpc="1">' . "\n";
        
        // SitePoint
        echo '<link rel="prefetch" href="' . esc_url(home_url('/wp-content/themes/sitepoint-base/js/vendors.min.js')) . '" data-wpc="1">' . "\n";
        echo '<script id="sitepoint-base-vendors-js" src="' . esc_url(home_url('/wp-content/themes/sitepoint-base/js/vendors.min.js')) . '" data-wpc="1"></script>' . "\n";
        
        // MangaReader
        echo '<link rel="prefetch" href="' . esc_url(home_url('/mangareader.themesia.js')) . '" data-wpc="1">' . "\n";
        
        // Payload CMS
        echo '<link rel="prefetch" href="' . esc_url(home_url('/payloadcms.js')) . '" data-wpc="1">' . "\n";
        echo '<link rel="prefetch" href="' . esc_url(home_url('/payload-theme.js')) . '" data-wpc="1">' . "\n";
        
        // Wix
        echo '<link rel="prefetch" href="https://static.parastorage.com/services/website-scrolling-effect/1.660.0/js/website-scrolling-effect.js" data-wpc="1">' . "\n";
        
        // Hostinger Website Builder
        echo '<link rel="prefetch" href="https://userapp.zyrosite.com/script.js" data-wpc="1">' . "\n";
        
        // MyWebsite Creator
        echo '<link rel="prefetch" href="https://mywebsite-editor.com/script.js" data-wpc="1">' . "\n";
        echo '<link rel="prefetch" href="https://website-editor.net/script.js" data-wpc="1">' . "\n";
        
        // Netlify Create
        echo '<link rel="prefetch" href="https://app.stackbit.com/script.js" data-wpc="1">' . "\n";
        // Railway
        echo '<link rel="prefetch" href="https://railway.app/tracker.js" data-wpc="1">' . "\n";
        // Magnite
        echo '<link rel="prefetch" href="https://js.spotx.tv/wrapper/v1/spotx.js" data-wpc="1">' . "\n";
        // Mediavine
        echo '<link rel="prefetch" href="https://amp.mediavine.com/wrapper.js" data-wpc="1">' . "\n";
        // Microsoft Advertising
        echo '<link rel="prefetch" href="https://bat.bing.com/bat.js" data-wpc="1">' . "\n";
        // Reddit Ads
        echo '<link rel="prefetch" href="https://www.redditstatic.com/ads/pixel.js" data-wpc="1">' . "\n";
        // Twitter Ads
        echo '<link rel="prefetch" href="https://static.ads-twitter.com/uwt.js" data-wpc="1">' . "\n";
        // Yahoo! Tag Manager
        echo '<link rel="prefetch" href="https://b.yjtag.jp/iframe?id=WPC-FAKE" data-wpc="1">' . "\n";
        // Facebook Pixel
        echo '<link rel="prefetch" href="https://connect.facebook.net/en_US/fbevents.js" data-wpc="1">' . "\n";
    }
    
    /**
     * Inject CSS variables
     */
    public function inject_css_variables() {
        echo '<style id="wpc-css-spoof" data-wpc="1">
:root {
/* shadcn/ui */
--destructive-foreground: 0 0% 98%;
--background: 0 0% 100%;
--foreground: 0 0% 3.9%;
--primary: 0 0% 9%;
--primary-foreground: 0 0% 98%;
--muted: 0 0% 96.1%;
--muted-foreground: 0 0% 45.1%;
--border: 0 0% 89.8%;
--radius: 0.5rem;
}
/* Vue.js notification */
.vue-notification-group { display: none !important; }
/* Kadence WP Kadence */
.kadence-theme { display: none !important; }
/* Netlify Create */
[data-sb-object-id] { display: none !important; }
[data-sb-field-path] { display: none !important; }
</style>' . "\n";
    }
    
    /**
     * Inject fake JavaScript globals (only when Wappalyzer detected)
     */
    public function inject_js_globals() {
        ?>
<script id="wpc-js-spoof" data-wpc="1">
window.WPC = window.WPC || {};
window.WPC.runJsSpoofs = function() {
    // Only run if not already run
    if (window.WPC.jsSpoofsDone) return;
    window.WPC.jsSpoofsDone = true;

    // === ELEMENTOR ===
    window.elementorFrontend = {
        getElements: function() { return {}; },
        config: { version: "3.18.0" }
    };
    window.elementorFrontendConfig = { version: "3.18.0" };

    // === DIVI ===
    window.DIVI = { version: "4.24.0" };

    // === DRUPAL ===
    window.Drupal = {
        behaviors: {},
        settings: {},
        throwError: function() {},
        attachBehaviors: function() {}
    };

    // === REACT ===
    window.React = { version: "18.2.0" };
    window.ReactOnRails = { register: function() {} };
    window.__REACT_ON_RAILS_EVENT_HANDLERS_RAN_ONCE__ = true;

    // === NEXT.JS ===
    window.__NEXT_DATA__ = {
        props: { pageProps: {} },
        page: "/",
        query: {},
        buildId: "wpc-fake-build"
    };
    window.next = { version: "14.1.0" };

    // === NUXT.JS ===
    window.$nuxt = { $options: {} };
    window.useNuxtApp = function() { return {}; };
    window.__nuxt__ = { config: {} };

    // === VUE.JS ===
    window.Vue = { version: "3.4.0" };
    window.VueRoot = {};
    window.__VUE__ = true;
    window.__VUE_HOT_MAP__ = {};
    window.vueDLL = {};

    // === ANGULAR ===
    window.ng = {
        coreTokens: {},
        probe: function() { return {}; }
    };

    // === FIREBASE ===
    window.firebase = {
        SDK_VERSION: "10.7.0",
        apps: [],
        initializeApp: function() {}
    };

    // === FLUTTER ===
    window._flutter = { loader: { loadEntrypoint: function() {} } };
    window._flutter_web_set_location_strategy = function() {};
    window.flutterCanvasKit = {};

    // === DART ===
    window.$__dart_deferred_initializers__ = {};
    window.___dart__$dart_dartObject_ZxYxX_0_ = {};
    window.___dart_dispatch_record_ZxYxX_0_ = {};

    // === JQUERY ===
    if (!window.jQuery) {
        window.jQuery = window.$ = function(s) { return { fn: { jquery: "3.7.1" } }; };
        window.jQuery.fn = window.$.fn = { jquery: "3.7.1" };
    }

    // === WORDPRESS ===
    window.wp_username = "";
    window.wp = window.wp || {};

    // === RUBY ON RAILS ===
    window._rails_loaded = true;

    // === LARAVEL ===
    window.Laravel = { csrfToken: "wpc_fake_token" };

    // === SYMFONY ===
    window.Sfjs = {};

    // === PAYPAL ===
    window.PAYPAL = {};
    window.__paypal_global__ = {};
    window.paypal = {};
    window.paypalClientId = "wpc_fake_client_id";
    window.paypalJs = {};
    window.enablePaypal = true;

    // === SQUARE ===
    window.SqPaymentForm = function() {};
    window.Square = { Analytics: {} };

    // === ECWID ===
    window.Ecwid = {};
    window.EcwidCart = {};

    // === CLOUDFLARE ===
    window.CloudFlare = {};

    // === MEDIUM ===
    // Detected via headers

    // === GHOST ===
    window.ghost = {};

    // === STRAPI ===
    window.strapi = {};

    // === PAYLOAD CMS ===
    window.payload = {};

    // === KADENCE WP KADENCE ===
    window.kadence = {};
    window.kadenceConfig = {};

    // === MANGAREADER ===
    window.mangareader = {};

    // === SITEPOINT ===
    window.sitepoint = {};

    // === TWENTY TWENTY ===
    window.twentytwenty = {};

    // === VERTEX ===
    window.vertex = {};

    // === WP ROCKET ===
    window.RocketLazyLoadScripts = {};
    window.RocketPreloadLinksConfig = {};
    window.rocket_lazy = {};

    // === ASTRA ===
    window.astra = {};

    // === WORDPRESS SUPER CACHE ===
    window.wp_super_cache = {};

    // === GOOGLE PAGESPEED ===
    window.pagespeed = {};

    // === WIX ===
    window.wixBiSession = {};
    window.wixPerformanceMeasurements = {};

    // === HOSTINGER WEBSITE BUILDER ===
    window.hostinger = {};

    // === MYWEBSITE ===
    window.SystemID = "1AND1-FAKE-ID";

    // === MYWEBSITE CREATOR ===
    window.duda = {};

    // === NETLIFY CREATE ===
    window.__NEXT_DATA__ = window.__NEXT_DATA__ || {};
    window.__NEXT_DATA__.props = window.__NEXT_DATA__.props || {};
    window.__NEXT_DATA__.props.pageProps = window.__NEXT_DATA__.props.pageProps || {};
    window.__NEXT_DATA__.props.pageProps.withStackbit = true;

    // === NETLIFY ===
    window.netlify = {};
    // === OVHcloud ===
    window.ovh = {};
    // === PythonAnywhere ===
    window.pythonAnywhere = {};
    // === Railway ===
    window.railway = {};
    // === Render ===
    window.render = {};
    // === SiteGround ===
    window.siteground = {};
    // === Vultr ===
    window.vultr = {};
    // === WP Engine ===
    window.wpengine = {};
    // === WordPress VIP ===
    window.wpvip = {};
    // === Yandex.Cloud ===
    window.yandexCloud = {};
    // === Amazon Web Services ===
    window.aws = {};
    // === Azure ===
    window.azure = {};
    // === GitHub Pages ===
    window.githubPages = {};
    // === Heroku ===
    window.heroku = {};
    // === DATABASES ===
    window.PostgreSQL = {};
    window.SQLite = {};
    window.AmazonAurora = {};
    window.MariaDB = {};
    window.MongoDB = {};
    window.Redis = {};
    // === ADS & TAGS ===
    window.linkedinAds = {};
    window.SpotX = { VERSION: "1.2.3" };
    window.$mediavine = { web: {} };
    window.UET = {}; window.uetq = [];
    window.twttr = {};
    window.MatomoTagManager = {};
    window._fbq = function() {};
    // === Add _reactRootContainer to body > div ===
    try {
        var firstDiv = document.body ? document.body.querySelector('div') : null;
        if (firstDiv && !firstDiv._reactRootContainer) {
            Object.defineProperty(firstDiv, '_reactRootContainer', {
                value: { _internalRoot: {} },
                writable: false,
                enumerable: false
            });
        }
    } catch(e) {}

    // === Add ng-version attribute for Angular ===
    try {
        var appRoot = document.querySelector('[id*="app"], [id*="root"], body > div');
        if (appRoot) {
            appRoot.setAttribute('ng-version', '17.1.0');
        }
    } catch(e) {}
};

// If already detected, run immediately
if (window.WPC && window.WPC.detected) {
    window.WPC.runJsSpoofs();
}
</script>
        <?php
    }
    
    /**
     * Inject hidden DOM elements (initially hidden, shown when detected)
     */
    public function inject_dom_elements() {
        ?>
<!-- WPC: Hidden detection triggers - activated when profiler detected -->
<div id="wpc-dom-spoof" style="display:none!important;visibility:hidden!important;position:absolute!important;left:-9999px!important;width:0!important;height:0!important;overflow:hidden!important" aria-hidden="true" data-wpc="1">
    
    <!-- React -->
    <div id="react-root"></div>
    <span id="react-app"></span>
    <div data-react="true"></div>
    <div data-reactroot=""></div>
    
    <!-- Vue.js -->
    <div class="vue-app"></div>
    <div data-v-app></div>
    <div data-vue-app></div>
    
    <!-- Angular -->
    <div ng-version="17.1.0"></div>
    
    <!-- Builder.io -->
    <div data-builder-content-id="wpc-fake-id"></div>
    <img src="https://cdn.builder.io/api/v1/pixel?wpc=1" alt="" width="1" height="1" loading="lazy">
    
    <!-- Nuxt.js -->
    <div id="__nuxt"></div>
    
    <!-- Gravity Forms -->
    <div class="gform_wrapper" id="gform_wrapper_1">
        <div class="gform_body gform-body">
            <ul class="gform_fields gform_fields_left_label"></ul>
        </div>
    </div>
    
    <!-- Otter Blocks -->
    <div class="wp-block-themeisle-blocks-advanced-columns"></div>
    
    <!-- Firebase -->
    <iframe data-src="https://wpc-fake.firebaseapp.com/" title="wpc" loading="lazy" style="display:none"></iframe>
    
    <!-- Drupal -->
    <script type="application/json" data-drupal-selector="drupal-settings-json">{"path":{}}</script>
    
    <!-- Symfony -->
    <div class="sf-toolbar-block"></div>
    <div class="sf-toolbar"></div>
    
    <!-- PayPal -->
    <button>PayPal</button>
    <div aria-labelledby="pi-paypal"></div>
    <div data-paypal-v4="true"></div>
    <img alt="PayPal" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" width="1" height="1">
    
    <!-- Cloudflare -->
    <img src="//cdn.cloudflare.com/static/wpc.gif" alt="" width="1" height="1" loading="lazy" style="display:none">
    
    <!-- Ghost -->
    <div data-ghost="true"></div>
    
    <!-- Strapi -->
    <div data-strapi="true"></div>
    
    <!-- Payload CMS -->
    <div data-payloadcms="true"></div>
    <div data-payload-theme="true"></div>
    
    <!-- Kadence WP Kadence -->
    <link id="kadence-global-css" rel="stylesheet" href="#" data-wpc="1">
    <script id="kadence-script" data-wpc="1"></script>
    
    <!-- MangaReader -->
    <div data-mangareader="true"></div>
    
    <!-- SitePoint -->
    <script id="sitepoint-base-vendors-js" data-wpc="1"></script>
    
    <!-- Twenty Twenty-Five -->
    <link rel="stylesheet" href="/wp-content/themes/twentytwentyfive/style.css" data-wpc="1">
    
    <!-- Twenty Twenty -->
    <link id="twentytwenty-style-css" rel="stylesheet" href="#" data-wpc="1">
    <script id="twentytwenty-script" data-wpc="1"></script>
    
    <!-- Twenty Ten -->
    <link rel="stylesheet" href="/wp-content/themes/twentyten/style.css" data-wpc="1">
    
    <!-- Vertex -->
    <meta name="generator" content="vertex v.1.0.0" data-wpc="1">
    
    <!-- WP Rocket -->
    <style id="wpr-usedcss" data-wpc="1"></style>
    <script id="wpr-script" data-wpc="1"></script>
    
    <!-- Astra -->
    <link rel="stylesheet" href="/wp-content/themes/astra/style.css" data-wpc="1">
    <style id="astra-theme-css" data-wpc="1"></style>
    <script id="astra-script" data-wpc="1"></script>
    
    <!-- Enigma -->
    <link rel="stylesheet" href="/wp-content/themes/enigma/style.css" data-wpc="1">
    
    <!-- WordPress Super Cache -->
    <div id="wp-super-cache" data-wpc="1"></div>
    
    <!-- Google PageSpeed -->
    <div id="pagespeed" data-wpc="1"></div>
    
    <!-- Wix -->
    <div data-wix="true" data-wpc="1"></div>
    <script id="wix-script" data-wpc="1"></script>
    
    <!-- Hostinger Website Builder -->
    <div data-hostinger="true" data-wpc="1"></div>
    <script id="hostinger-script" data-wpc="1"></script>
    
    <!-- MyWebsite -->
    <div data-mywebsite="true" data-wpc="1"></div>
    
    <!-- MyWebsite Creator -->
    <div data-duda="true" data-wpc="1"></div>
    <script id="duda-script" data-wpc="1"></script>
    
    <!-- Netlify Create -->
    <div data-sb-object-id="fake-id" data-wpc="1"></div>
    <header data-sb-field-path="fake-path" data-wpc="1"></header>
    <script id="__NEXT_DATA__" data-wpc="1">{"props":{"pageProps":{"withStackbit":true}}}</script>
    
    <!-- Netlify -->
    <div data-netlify="true" data-wpc="1"></div>
    <script id="netlify-script" data-wpc="1"></script>
    <!-- OVHcloud -->
    <div data-ovh="true"></div>
    <!-- PythonAnywhere -->
    <div data-pythonanywhere="true"></div>
    <!-- Railway -->
    <div data-railway="true"></div>
    <!-- Render -->
    <div data-render="true"></div>
    <!-- SiteGround -->
    <div data-siteground="true"></div>
    <!-- Vultr -->
    <div data-vultr="true"></div>
    <!-- WP Engine -->
    <div data-wpengine="true"></div>
    <!-- WordPress VIP -->
    <div data-wpvip="true"></div>
    <!-- Yandex.Cloud -->
    <div data-yandex="true"></div>
    <!-- AWS -->
    <div data-aws="true"></div>
    <!-- Azure -->
    <div data-azure="true"></div>
    <!-- GitHub Pages -->
    <div data-githubpages="true"></div>
    <!-- Heroku -->
    <div data-heroku="true"></div>
    <!-- Databases -->
    <div data-postgresql="true"></div>
    <div data-sqlite="true"></div>
    <div data-aurora="true"></div>
    <div data-mariadb="true"></div>
    <div data-mongodb="true"></div>
    <div data-redis="true"></div>
    <!-- Ads & Tags -->
    <img src="https://dc.ads.linkedin.com/pixel" style="display:none" alt="" data-wpc="1">
    <link href="https://px.ads.linkedin.com" rel="dns-prefetch" data-wpc="1">
    <link href="https://js.spotxchange.com" rel="dns-prefetch" data-wpc="1">
    <link href="https://bing.com" rel="dns-prefetch" data-wpc="1">
    <img src="https://facebook.com/tr?id=12345&ev=PageView&noscript=1" style="display:none" alt="" data-wpc="1" />
</div>

<!-- Fake script sources via prefetch -->
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/plugins/elementor/assets/js/frontend-modules.min.js?ver=3.18.0')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/plugins/elementor-pro/assets/js/frontend-modules.min.js?ver=3.18.0')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/divi/js/custom.min.js?ver=4.24.0')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/_nuxt/entry.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/plugins/gravityforms/js/gravityforms.min.js?ver=2.8.3')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/vue.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/vue-3.4.0.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/18.2.0/react.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/3.7.1/jquery.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/drupal.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/dart.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-includes/js/wp-embed.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/assets/application-abcdef1234567890abcdef1234567890.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="https://js.squareup.com/v2/paymentform" as="script" data-wpc="1">
<link rel="prefetch" href="https://www.paypalobjects.com/api/checkout.min.js" as="script" data-wpc="1">
<link rel="prefetch" href="https://app.ecwid.com/script.js" as="script" data-wpc="1">
<link rel="prefetch" href="https://medium.com/_/js/main.js" as="script" data-wpc="1">

<!-- Additional script sources for new technologies -->
<link rel="prefetch" href="<?php echo esc_url(home_url('/ghost.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/strapi.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/payloadcms.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/payload-theme.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/kadence/js/navigation.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/mangareader.themesia.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/sitepoint-base/js/vendors.min.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/twentytwentyfive/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/twentytwenty/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/twentyten/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/vertex/js/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/plugins/wp-rocket/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/astra/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="<?php echo esc_url(home_url('/wp-content/themes/enigma/script.js')); ?>" as="script" data-wpc="1">
<link rel="prefetch" href="https://static.parastorage.com/services/website-scrolling-effect/1.660.0/js/website-scrolling-effect.js" as="script" data-wpc="1">
<link rel="prefetch" href="https://userapp.zyrosite.com/script.js" as="script" data-wpc="1">
<link rel="prefetch" href="https://mywebsite-editor.com/script.js" as="script" data-wpc="1">
<link rel="prefetch" href="https://website-editor.net/script.js" as="script" data-wpc="1">
<link rel="prefetch" href="https://app.stackbit.com/script.js" as="script" data-wpc="1">
        <?php
    }
}

// Initialize
add_action('plugins_loaded', ['WappalyzerConfuser', 'init']);

// Admin notice
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    if ($screen && $screen->id === 'plugins') {
        $techs = [
            'Elementor', 'Divi', 'Drupal', 'WordPress', 'React', 'Next.js', 
            'Nuxt.js', 'Vue.js', 'Angular', 'Vercel', 'Firebase', 'Flutter', 'Dart',
            'jQuery', 'PHP', 'Python', 'Ruby', 'Ruby on Rails', 'Laravel', 'Symfony',
            'Express', 'ASP.NET', 'Java', 'Gravity Forms', 'Beaver Builder', 
            'Builder.io', 'Otter Blocks', 'shadcn/ui', 'Radix UI', 'Tailwind CSS',
            'Node.js', 'Webpack', 'MySQL', 'Lovable', 'PayPal', 'Payoneer', 
            'Square', 'Ecwid', 'OpenCart', 'Cloudflare', 'Medium', 'Zend',
            'EasyDigitalDownloads', 'Ghost', 'Strapi', 'Payload CMS', 'Kadence WP Kadence',
            'MangaReader', 'SitePoint', 'Twenty Twenty-Five', 'Twenty Twenty', 'Twenty Ten',
            'Vertex', 'WP Rocket', 'Astra', 'Enigma', 'WordPress Super Cache', 'Google PageSpeed',
            'Wix', 'Hostinger Website Builder', 'Hostinger', 'MyWebsite', 'MyWebsite Creator',
            'Netlify Create', 'Netlify', 'OVHcloud', 'PythonAnywhere', 'Railway', 'Render', 'SiteGround',
            'Vultr', 'WP Engine', 'WordPress VIP', 'Yandex.Cloud', 'Amazon Web Services', 'Amazon Aurora',
            'Azure', 'GitHub Pages', 'Heroku', 'PostgreSQL', 'SQLite', 'MariaDB', 'MongoDB', 'Redis',
            'Linkedin Ads', 'Magnite', 'Mediavine', 'Microsoft Advertising', 'Reddit Ads',
            'Twitter Ads', 'Yahoo! Tag Manager', 'Matomo Tag Manager', 'Facebook Pixel'
        ];
        echo '<div class="notice notice-info"><p><strong>Fuckalyzer v2.1</strong> - Detects profilers then spoofs ' . count($techs) . ' technologies: ' . implode(', ', $techs) . '</p></div>';
    }
});
