<?php
/**
 * Plugin Name: Fuckalyzer
 * Description: Detects Wappalyzer extension, then confuses it by injecting false technology signatures.
 * Version: 2.0.0
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
        add_action('wp_head', [$this, 'inject_meta_tags'], 1);
        add_action('wp_head', [$this, 'inject_fake_links'], 99);
        add_action('wp_head', [$this, 'inject_css_variables'], 99);
        add_action('wp_head', [$this, 'inject_html_patterns'], 99);
        add_action('wp_footer', [$this, 'inject_js_globals'], 99);
        add_action('wp_footer', [$this, 'inject_dom_elements'], 99);
        
        // Body class filter
        add_filter('body_class', [$this, 'add_body_classes']);
        
        // Set fake cookie
        add_action('init', [$this, 'set_fake_cookies']);
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

// Activate spoofing - show hidden elements and run JS spoofs
window.WPC.activateSpoofing = function() {
    if (window.WPC.spoofingActive) return;
    window.WPC.spoofingActive = true;
    
    // Show hidden spoof elements
    var el = document.getElementById('wpc-dom-spoof');
    if (el) el.style.display = 'block';
    
    // Run deferred JS spoofs
    if (typeof window.WPC.runJsSpoofs === 'function') {
        window.WPC.runJsSpoofs();
    }
    
    console.log('[WPC] Technology profiler detected, spoofing activated');
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
    }
    
    /**
     * Add body classes for detection
     */
    public function add_body_classes($classes) {
        $classes[] = 'fl-builder'; // Beaver Builder
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
        echo '<link rel="stylesheet" href="' . esc_url(home_url('/wp-includes/css/dist/block-library/style.min.css')) . '" data-wpc="1">' . "\n";
        echo '<link href="https://s0.wp.com/wp-content/themes/flavor/style.css" rel="prefetch" data-wpc="1">' . "\n";
        
        // Drupal
        echo '<link rel="prefetch" href="/sites/default/themes/flavor/style.css" data-wpc="1">' . "\n";
        echo '<link rel="prefetch" href="/sites/all/modules/flavor/style.css" data-wpc="1">' . "\n";
        
        // ASP.NET viewstate (hidden)
        echo '<input type="hidden" name="__VIEWSTATE" value="wpc_fake_viewstate" data-wpc="1">' . "\n";
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
            'EasyDigitalDownloads'
        ];
        echo '<div class="notice notice-info"><p><strong>Fuckalyzer  v2.0</strong> - Detects profilers then spoofs ' . count($techs) . ' technologies: ' . implode(', ', $techs) . '</p></div>';
    }
});