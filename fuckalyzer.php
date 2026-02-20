<?php
/**
 * Plugin Name:       Fuckalyzer
 * Plugin URI:        https://morrowshore.com/
 * Description:       Detects Wappalyzer extension, then confuses it by injecting false technology signatures.
 * Version:           2.3.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Morrow Shore
 * Author URI:        https://morrowshore.com/
 * License:           AGPL v3 or later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain:       fuckalyzer
 * Domain Path:       /languages
 *
 * @package           Fuckalyzer
 */

defined('ABSPATH') || exit;

/**
 * Main plugin class.
 *
 * This class handles the detection of technology profilers and injects spoofed data
 * to confuse them. It follows a singleton pattern to ensure only one instance is loaded.
 *
 * @since 2.2.0
 */
class Fuckalyzer_Confuser {

	/**
	 * Cookie name used to mark a browser where a profiler was detected.
	 *
	 * @since 2.3.0
	 * @var string
	 */
	private const DETECTION_COOKIE_NAME = 'wpc_profiler_detected';

	/**
	 * Detection cookie lifetime in seconds.
	 *
	 * @since 2.3.0
	 * @var int
	 */
	private const DETECTION_COOKIE_TTL = 1800;

	/**
	 * The single instance of the class.
	 *
	 * @since 2.2.0
	 * @var   WappalyzerConfuser|null
	 */
	private static $instance = null;

	/**
	 * Known technology profiler extensions and their resource identifiers.
	 *
	 * @since 2.2.0
	 * @var   array
	 */
	private static $extensions = [
		'wappalyzer'      => [
			'id'   => 'gppongmhjkpfnbhagpmjfkannfbllamg',
			'file' => 'js/inject.js',
		],
		'wappalyzer_edge' => [
			'id'   => 'mnbndgmknlpdjdnjfmfcdjoegcckoikn',
			'file' => 'js/inject.js',
		],
		'builtwith'       => [
			'id'   => 'dllokjfcpfmgkgfgnblkkbgmkdppfjgj',
			'file' => 'img/bw.png',
		],
	];

	/**
	 * Get the singleton instance of the class.
	 *
	 * @since  2.2.0
	 * @return Fuckalyzer_Confuser
	 */
	public static function init_fuckalyzer() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 * Sets up WordPress hooks.
	 *
	 * @since 2.2.0
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'load_textdomain_fuckalyzer' ] );
		add_action( 'send_headers', [ $this, 'inject_headers_fuckalyzer' ] );
		add_action( 'wp_head', [ $this, 'inject_detector_script_fuckalyzer' ], 0 );
		add_filter( 'body_class', [ $this, 'add_body_classes_fuckalyzer' ] );
		add_action( 'init', [ $this, 'set_fake_cookies_fuckalyzer' ] );
		add_action( 'wp_ajax_nopriv_get_wpc_spoof_data', [ $this, 'get_spoof_data_callback_fuckalyzer' ] );
		add_action( 'wp_ajax_get_wpc_spoof_data', [ $this, 'get_spoof_data_callback_fuckalyzer' ] );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function load_textdomain_fuckalyzer() {
		load_plugin_textdomain( 'fuckalyzer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * AJAX callback to serve all spoofing HTML content.
	 * Verifies nonce for security.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function get_spoof_data_callback_fuckalyzer() {
		check_ajax_referer( 'wpc_ajax_nonce', 'security' );
		$this->mark_detected_browser_fuckalyzer();

		ob_start();
		$this->inject_meta_tags_fuckalyzer();
		$this->inject_fake_links_fuckalyzer();
		$this->inject_css_variables_fuckalyzer();
		$this->inject_html_patterns_fuckalyzer();
		$head_html = ob_get_clean();

		ob_start();
		$this->inject_dom_elements_fuckalyzer();
		$footer_html = ob_get_clean();

		wp_send_json_success( [ 'head' => $head_html, 'footer' => $footer_html ] );
		wp_die();
	}

	/**
	 * Inject the primary detector script into the head.
	 * This script checks for Wappalyzer and only then activates spoofing.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_detector_script_fuckalyzer() {
		if ( ! $this->should_run_for_frontend_fuckalyzer() ) {
			return;
		}

		$nonce            = wp_create_nonce( 'wpc_ajax_nonce' );
		$ajax_url         = admin_url( 'admin-ajax.php' );
		$detection_cookie = self::DETECTION_COOKIE_NAME;
		?>
<script id="wpc-detector" data-wpc="1">
(function(){
"use strict";
window.WPC = window.WPC || {};
window.WPC.detected = false;
window.WPC.spoofingActive = false;

var wpcAjaxUrl = <?php echo wp_json_encode( esc_url_raw( $ajax_url ) ); ?>;
var wpcNonce = <?php echo wp_json_encode( $nonce ); ?>;
var wpcDetectionCookie = <?php echo wp_json_encode( $detection_cookie ); ?>;
var wpcDetectionCookieMaxAge = <?php echo (int) self::DETECTION_COOKIE_TTL; ?>;

function markDetectedBrowser_fuckalyzer() {
	try {
		document.cookie = wpcDetectionCookie + "=1; path=/; max-age=" + wpcDetectionCookieMaxAge + "; samesite=lax";
	} catch (e) {}
}

// Detection method 1: Check for Wappalyzer's injected script tag
function detectViaDOM_fuckalyzer() {
	var scripts = document.querySelectorAll('script[src*="wappalyzer"], script[id="wappalyzer"]');
	return scripts.length > 0;
}

// Detection method 2: Check for Wappalyzer message events
function detectViaMessages_fuckalyzer() {
	return new Promise(function(resolve) {
		var detected = false;
		var handler = function(e) {
			if ( e && e.data && typeof e.data === 'object' && ( e.data.wappalyzer || e.data.source === 'wappalyzer' ) ) {
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

// Combined passive detection strategy
function startPassiveDetection_fuckalyzer() {
	if ( detectViaDOM_fuckalyzer() ) {
		window.WPC.detected = true;
		window.WPC.activateSpoofing();
		return;
	}

	detectViaMessages_fuckalyzer().then(function(found) {
		if ( found ) {
			window.WPC.detected = true;
			window.WPC.activateSpoofing();
		}
	});
}

// Activate spoofing - fetch and inject spoofing HTML
window.WPC.activateSpoofing = function() {
	if ( window.WPC.spoofingActive ) {
		return;
	}

	window.WPC.spoofingActive = true;
	markDetectedBrowser_fuckalyzer();

	console.log('[WPC] Technology profiler detected, activating spoofing...');

	fetch(wpcAjaxUrl + '?action=get_wpc_spoof_data&security=' + encodeURIComponent(wpcNonce), { credentials: 'same-origin' })
		.then(function(response) { return response.json(); })
		.then(function(json) {
			if ( json.success && json.data ) {
				// Inject head elements
				if ( json.data.head && document.head ) {
					document.head.insertAdjacentHTML('beforeend', json.data.head);
				}
				// Inject footer elements
				if ( json.data.footer && document.body ) {
					document.body.insertAdjacentHTML('beforeend', json.data.footer);
				}
				 console.log('[WPC] Spoofing activated.');
			}
		})
		.catch(function(error) {
			console.error('[WPC] Failed to fetch spoofing data:', error);
		});
};

// Start detection after DOM ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener('DOMContentLoaded', startPassiveDetection_fuckalyzer);
} else {
	startPassiveDetection_fuckalyzer();
}

})();
</script>
		<?php
	}

	/**
	 * Set fake cookies for various technologies to aid detection spoofing.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function set_fake_cookies_fuckalyzer() {
		if ( ! $this->should_emit_server_side_spoofs_fuckalyzer() || headers_sent() ) {
			return;
		}

		$cookies_to_set = [
			'laravel_session'     => 'wpc_' . $this->random_hex_fuckalyzer( 20 ),
			'sf_redirect'       => '{}',
			'JSESSIONID'          => 'WPC' . strtoupper( $this->random_hex_fuckalyzer( 16 ) ),
			'ASP.NET_SessionId'   => 'wpc' . $this->random_hex_fuckalyzer( 12 ),
			'OCSESSID'            => $this->random_hex_fuckalyzer( 16 ),
			'ZendServerSessionId' => $this->random_hex_fuckalyzer( 16 ),
			'arraffinity'         => 'wpc_fake_arraffinity',
			'tipmix'              => 'wpc_fake_tipmix',
		];

		foreach ( $cookies_to_set as $name => $value ) {
			if ( ! isset( $_COOKIE[ $name ] ) ) {
				$this->set_cookie_compat_fuckalyzer( $name, $value );
			}
		}
	}

	/**
	 * Inject fake HTTP headers to spoof server-side technologies.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_headers_fuckalyzer() {
		if ( ! $this->should_emit_server_side_spoofs_fuckalyzer() || headers_sent() ) {
			return;
		}

		$headers = [
			'x-vercel-cache'       => 'HIT',
			'x-vercel-id'          => 'fra1::wpc-' . substr( md5( (string) time() ), 0, 8 ),
			'x-powered-by'         => 'PHP/8.3.0, next.js 14.1.0, Express, WordPress',
			'x-drupal-cache'       => 'HIT',
			'x-generator'          => 'Drupal 10.2.0',
			'x-aspnet-version'     => '4.0.30319',
			'cf-cache-status'      => 'HIT',
			'cf-ray'               => substr( md5( microtime() ), 0, 16 ) . '-FRA',
			'x-ghost-cache-status' => 'HIT',
			'x-mod-pagespeed'      => '1.14.33.1-0',
			'x-page-speed'         => '1.14.33.1-0',
			'x-nf-request-id'      => 'wpc-fake-request-id',
			'x-render-origin-server' => 'render',
			'x-amz-request-id'     => 'WPC_FAKE_REQUEST_ID',
			'x-ms-request-id'      => 'wpc-fake-ms-request',
		];

		foreach ( $headers as $name => $value ) {
			header( "$name: $value", false );
		}
	}

	/**
	 * Add fake body classes for various page builders and themes.
	 *
	 * @since  2.2.0
	 * @param  array $classes An array of body classes.
	 * @return array The modified array of body classes.
	 */
	public function add_body_classes_fuckalyzer( $classes ) {
		if ( ! $this->should_emit_server_side_spoofs_fuckalyzer() ) {
			return $classes;
		}

		$classes[] = 'fl-builder'; // Beaver Builder
		$classes[] = 'astra';      // Astra
		return $classes;
	}

	/**
	 * Inject fake meta generator tags into the document head.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_meta_tags_fuckalyzer() {
		$generators = [
			'elementor 3.18.0',
			'divi v.4.24.0',
			'Drupal 10.2.0',
			'WordPress 6.4.2',
			'easy digital downloads v3.2.0',
			'ghost 5.0.0',
			'wix.com website builder',
			'hostinger website builder',
			'ionos mywebsite 8',
			'vertex v.1.0.0',
		];

		foreach ( $generators as $gen ) {
			echo '<meta name="generator" content="' . esc_attr( $gen ) . '">' . "\n";
		}

		echo '<meta name="description" content="web site created using create-react-app" data-wpc="1">' . "\n";
		echo '<meta name="id" content="flutterweb-theme" data-wpc="1">' . "\n";
		echo '<meta name="author" content="lovable" data-wpc="1">' . "\n";
		echo '<meta name="shareaholic:wp_version" content="6.4.2" data-wpc="1">' . "\n";
		echo '<meta name="csrf-param" content="authenticity_token" data-wpc="1">' . "\n";
		echo '<meta name="id" content="in-context-paypal-metadata" data-wpc="1">' . "\n";
	}

	/**
	 * Inject various HTML patterns like stylesheets to spoof themes and plugins.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_html_patterns_fuckalyzer() {
		$links = [
			'/wp-content/themes/flavor/style.css',
			'/wp-content/plugins/elementor/assets/css/frontend.min.css',
			'https://s0.wp.com/wp-content/themes/flavor/style.css',
			'/sites/default/themes/flavor/style.css',
			'/sites/all/modules/flavor/style.css',
			'/wp-content/themes/twentytwentyfive/style.css',
			'/wp-content/themes/twentytwenty/style.css',
			'/wp-content/themes/twentyten/style.css',
			'/wp-content/themes/vertex/style.css',
			'/wp-content/themes/astra/style.css',
			'/wp-content/themes/enigma/style.css',
			'/wp-content/themes/kadence/style.css',
			'/wp-content/themes/sitepoint-base/style.css',
		];

		foreach ( $links as $link ) {
			$url = ( strpos( $link, 'http' ) === 0 ) ? $link : home_url( $link );
			echo '<link rel="prefetch" href="' . esc_url( $url ) . '" data-wpc="1">' . "\n";
		}

		echo '<link id="twentytwenty-style-css" rel="prefetch" href="' . esc_url( home_url( '/wp-content/themes/twentytwenty/style.css' ) ) . '" data-wpc="1">' . "\n";
		echo '<meta name="wpc-viewstate" content="wpc_fake_viewstate" data-wpc="1">' . "\n";
		echo '<!-- wpc_fake_linkedin_pixel --><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=12345&fmt=gif" />' . "\n";
		echo '<!-- Yahoo! Tag Manager -->' . "\n";
	}

	/**
	 * Inject fake link and style elements to spoof various technologies.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_fake_links_fuckalyzer() {
		$prefetch_links = [
			'/wp-content/plugins/elementor/assets/css/frontend.min.css',
			'/wp-content/plugins/elementor-pro/assets/css/frontend.min.css',
			'/wp-content/plugins/otter-blocks/assets/css/style.css',
			'/lovable-uploads/image.webp',
			'/wp-content/plugins/gravityforms/css/formreset.min.css',
			'/wp-content/plugins/wc-payoneer-payment-gateway/style.css',
			'/catalog/view/theme/rgen-opencart/style.css',
			'/wp-content/plugins/wp-rocket/style.css',
			'/wp-content/themes/twentytwentyfive/',
			'/wp-content/themes/twentytwenty/',
			'/wp-content/themes/twentyten/',
			'/wp-content/themes/vertex/js/',
			'/wp-content/themes/astra/',
			'/wp-content/themes/enigma/',
			'/wp-content/themes/kadence/js/navigation.min.js',
			'/wp-content/themes/sitepoint-base/js/vendors.min.js',
			'/mangareader.themesia.js',
			'/payloadcms.js',
			'/payload-theme.js',
			'https://static.parastorage.com/services/website-scrolling-effect/1.660.0/js/website-scrolling-effect.js',
			'https://userapp.zyrosite.com/script.js',
			'https://mywebsite-editor.com/script.js',
			'https://website-editor.net/script.js',
			'https://app.stackbit.com/script.js',
			'https://railway.app/tracker.js',
			'https://js.spotx.tv/wrapper/v1/spotx.js',
			'https://amp.mediavine.com/wrapper.js',
			'https://bat.bing.com/bat.js',
			'https://www.redditstatic.com/ads/pixel.js',
			'https://static.ads-twitter.com/uwt.js',
			'https://b.yjtag.jp/iframe?id=WPC-FAKE',
			'https://connect.facebook.net/en_US/fbevents.js',
		];

		foreach ( $prefetch_links as $link ) {
			$url = ( strpos( $link, 'http' ) === 0 ) ? $link : home_url( $link );
			echo '<link rel="prefetch" href="' . esc_url( $url ) . '" data-wpc="1">' . "\n";
		}

		echo '<link id="fl-builder-layout" rel="prefetch" href="#" data-wpc="1">' . "\n";
		echo '<style id="divi-style-parent-inline-inline-css" data-wpc="1">/* Version: 4.24.0 */</style>' . "\n";
		echo '<link id="payoneer-plugn-css" rel="prefetch" href="#" data-wpc="1">' . "\n";
		echo '<style id="wpr-usedcss" data-wpc="1">/* WP Rocket CSS */</style>' . "\n";
		echo '<link id="sitepoint-base-vendors-js" rel="prefetch" as="script" href="' . esc_url( home_url( '/wp-content/themes/sitepoint-base/js/vendors.min.js' ) ) . '" data-wpc="1">' . "\n";
	}

	/**
	 * Inject CSS variables used by frameworks like shadcn/ui.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_css_variables_fuckalyzer() {
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
	 * Inject fake JavaScript global variables to mimic various frameworks and libraries.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_js_globals_fuckalyzer() {
		?>
<script id="wpc-js-spoof" data-wpc="1">
window.WPC = window.WPC || {};
window.WPC.runJsSpoofs = function() {
	if ( window.WPC.jsSpoofsDone ) return;
	window.WPC.jsSpoofsDone = true;

	window.elementorFrontend = { getElements: function() { return {}; }, config: { version: "3.18.0" } };
	window.elementorFrontendConfig = { version: "3.18.0" };
	window.DIVI = { version: "4.24.0" };
	window.Drupal = { behaviors: {}, settings: {}, throwError: function() {}, attachBehaviors: function() {} };
	window.React = { version: "18.2.0" };
	window.ReactOnRails = { register: function() {} };
	window.__REACT_ON_RAILS_EVENT_HANDLERS_RAN_ONCE__ = true;
	window.__NEXT_DATA__ = { props: { pageProps: {} }, page: "/", query: {}, buildId: "wpc-fake-build" };
	window.next = { version: "14.1.0" };
	window.$nuxt = { $options: {} };
	window.useNuxtApp = function() { return {}; };
	window.__nuxt__ = { config: {} };
	window.Vue = { version: "3.4.0" };
	window.VueRoot = {};
	window.__VUE__ = true;
	window.__VUE_HOT_MAP__ = {};
	window.vueDLL = {};
	window.ng = { coreTokens: {}, probe: function() { return {}; } };
	window.firebase = { SDK_VERSION: "10.7.0", apps: [], initializeApp: function() {} };
	window._flutter = { loader: { loadEntrypoint: function() {} } };
	window._flutter_web_set_location_strategy = function() {};
	window.flutterCanvasKit = {};
	window.$__dart_deferred_initializers__ = {};
	window.___dart__$dart_dartObject_ZxYxX_0_ = {};
	window.___dart_dispatch_record_ZxYxX_0_ = {};
	if ( ! window.jQuery ) {
		window.jQuery = window.$ = function(s) { return { fn: { jquery: "3.7.1" } }; };
		window.jQuery.fn = window.$.fn = { jquery: "3.7.1" };
	}
	window.wp_username = "";
	window.wp = window.wp || {};
	window._rails_loaded = true;
	window.Laravel = { csrfToken: "wpc_fake_token" };
	window.Sfjs = {};
	window.PAYPAL = {};
	window.__paypal_global__ = {};
	window.paypal = {};
	window.paypalClientId = "wpc_fake_client_id";
	window.paypalJs = {};
	window.enablePaypal = true;
	window.SqPaymentForm = function() {};
	window.Square = { Analytics: {} };
	window.Ecwid = {};
	window.EcwidCart = {};
	window.CloudFlare = {};
	window.ghost = {};
	window.strapi = {};
	window.payload = {};
	window.kadence = {};
	window.kadenceConfig = {};
	window.mangareader = {};
	window.sitepoint = {};
	window.twentytwenty = {};
	window.vertex = {};
	window.RocketLazyLoadScripts = {};
	window.RocketPreloadLinksConfig = {};
	window.rocket_lazy = {};
	window.astra = {};
	window.wp_super_cache = {};
	window.pagespeed = {};
	window.wixBiSession = {};
	window.wixPerformanceMeasurements = {};
	window.hostinger = {};
	window.SystemID = "1AND1-FAKE-ID";
	window.duda = {};
	window.__NEXT_DATA__ = window.__NEXT_DATA__ || {};
	window.__NEXT_DATA__.props = window.__NEXT_DATA__.props || {};
	window.__NEXT_DATA__.props.pageProps = window.__NEXT_DATA__.props.pageProps || {};
	window.__NEXT_DATA__.props.pageProps.withStackbit = true;
	window.netlify = {};
	window.ovh = {};
	window.pythonAnywhere = {};
	window.railway = {};
	window.render = {};
	window.siteground = {};
	window.vultr = {};
	window.wpengine = {};
	window.wpvip = {};
	window.yandexCloud = {};
	window.aws = {};
	window.azure = {};
	window.githubPages = {};
	window.heroku = {};
	window.PostgreSQL = {};
	window.SQLite = {};
	window.AmazonAurora = {};
	window.MariaDB = {};
	window.MongoDB = {};
	window.Redis = {};
	window.linkedinAds = {};
	window.SpotX = { VERSION: "1.2.3" };
	window.$mediavine = { web: {} };
	window.UET = {}; window.uetq = [];
	window.twttr = {};
	window.MatomoTagManager = {};
	window._fbq = function() {};

	try {
		var firstDiv = document.body ? document.body.querySelector('div') : null;
		if ( firstDiv && ! firstDiv._reactRootContainer ) {
			Object.defineProperty(firstDiv, '_reactRootContainer', {
				value: { _internalRoot: {} },
				writable: false,
				enumerable: false
			});
		}
	} catch(e) {}

	try {
		var appRoot = document.querySelector('[id*="app"], [id*="root"], body > div');
		if ( appRoot ) {
			appRoot.setAttribute('ng-version', '17.1.0');
		}
	} catch(e) {}
};

if ( window.WPC && window.WPC.detected ) {
	window.WPC.runJsSpoofs();
}
</script>
		<?php
	}

	/**
	 * Inject hidden DOM elements to act as triggers for technology detection.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function inject_dom_elements_fuckalyzer() {
		?>
<!-- WPC: Hidden detection triggers - activated when profiler detected -->
<div id="wpc-dom-spoof" style="display:none!important;visibility:hidden!important;position:absolute!important;left:-9999px!important;width:0!important;height:0!important;overflow:hidden!important" aria-hidden="true" data-wpc="1">
	<div id="react-root"></div>
	<span id="react-app"></span>
	<div data-react="true"></div>
	<div data-reactroot=""></div>
	<div class="vue-app"></div>
	<div data-v-app></div>
	<div data-vue-app></div>
	<div ng-version="17.1.0"></div>
	<div data-builder-content-id="wpc-fake-id"></div>
	<img src="https://cdn.builder.io/api/v1/pixel?wpc=1" alt="" width="1" height="1" loading="lazy">
	<div id="__nuxt"></div>
	<div class="gform_wrapper" id="gform_wrapper_1">
		<div class="gform_body gform-body">
			<ul class="gform_fields gform_fields_left_label"></ul>
		</div>
	</div>
	<div class="wp-block-themeisle-blocks-advanced-columns"></div>
	<iframe data-src="https://wpc-fake.firebaseapp.com/" title="wpc" loading="lazy" style="display:none"></iframe>
	<script type="application/json" data-drupal-selector="drupal-settings-json">{"path":{}}</script>
	<div class="sf-toolbar-block"></div>
	<div class="sf-toolbar"></div>
	<button>PayPal</button>
	<div aria-labelledby="pi-paypal"></div>
	<div data-paypal-v4="true"></div>
	<img alt="PayPal" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" width="1" height="1">
	<img src="//cdn.cloudflare.com/static/wpc.gif" alt="" width="1" height="1" loading="lazy" style="display:none">
	<div data-ghost="true"></div>
	<div data-strapi="true"></div>
	<div data-payloadcms="true"></div>
	<div data-payload-theme="true"></div>
	<div data-mangareader="true"></div>
	<div id="wp-super-cache" data-wpc="1"></div>
	<div id="pagespeed" data-wpc="1"></div>
	<div data-wix="true" data-wpc="1"></div>
	<div data-hostinger="true" data-wpc="1"></div>
	<div data-mywebsite="true" data-wpc="1"></div>
	<div data-duda="true" data-wpc="1"></div>
	<div data-sb-object-id="fake-id" data-wpc="1"></div>
	<header data-sb-field-path="fake-path" data-wpc="1"></header>
	<script id="__NEXT_DATA__" data-wpc="1">{"props":{"pageProps":{"withStackbit":true}}}</script>
	<div data-netlify="true" data-wpc="1"></div>
	<div data-ovh="true"></div>
	<div data-pythonanywhere="true"></div>
	<div data-railway="true"></div>
	<div data-render="true"></div>
	<div data-siteground="true"></div>
	<div data-vultr="true"></div>
	<div data-wpengine="true"></div>
	<div data-wpvip="true"></div>
	<div data-yandex="true"></div>
	<div data-aws="true"></div>
	<div data-azure="true"></div>
	<div data-githubpages="true"></div>
	<div data-heroku="true"></div>
	<div data-postgresql="true"></div>
	<div data-sqlite="true"></div>
	<div data-aurora="true"></div>
	<div data-mariadb="true"></div>
	<div data-mongodb="true"></div>
	<div data-redis="true"></div>
	<img src="https://dc.ads.linkedin.com/pixel" style="display:none" alt="" data-wpc="1">
	<link href="https://px.ads.linkedin.com" rel="dns-prefetch" data-wpc="1">
	<link href="https://js.spotxchange.com" rel="dns-prefetch" data-wpc="1">
	<link href="https://bing.com" rel="dns-prefetch" data-wpc="1">
	<img src="https://facebook.com/tr?id=12345&ev=PageView&noscript=1" style="display:none" alt="" data-wpc="1" />
</div>

<!-- Fake script sources via prefetch -->
		<?php
		$script_links = [
			'/wp-content/plugins/elementor/assets/js/frontend-modules.min.js?ver=3.18.0',
			'/wp-content/plugins/elementor-pro/assets/js/frontend-modules.min.js?ver=3.18.0',
			'/divi/js/custom.min.js?ver=4.24.0',
			'/_nuxt/entry.js',
			'/wp-content/plugins/gravityforms/js/gravityforms.min.js?ver=2.8.3',
			'/vue.min.js',
			'/vue-3.4.0.min.js',
			'/18.2.0/react.min.js',
			'/3.7.1/jquery.min.js',
			'/drupal.js',
			'/dart.js',
			'https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js',
			'/wp-includes/js/wp-embed.min.js',
			'/assets/application-abcdef1234567890abcdef1234567890.js',
			'https://js.squareup.com/v2/paymentform',
			'https://www.paypalobjects.com/api/checkout.min.js',
			'https://app.ecwid.com/script.js',
			'https://medium.com/_/js/main.js',
			'/ghost.js',
			'/strapi.js',
			'/payloadcms.js',
			'/payload-theme.js',
			'/wp-content/themes/kadence/js/navigation.min.js',
			'/mangareader.themesia.js',
			'/wp-content/themes/sitepoint-base/js/vendors.min.js',
			'/wp-content/themes/twentytwentyfive/script.js',
			'/wp-content/themes/twentytwenty/script.js',
			'/wp-content/themes/twentyten/script.js',
			'/wp-content/themes/vertex/js/script.js',
			'/wp-content/plugins/wp-rocket/script.js',
			'/wp-content/themes/astra/script.js',
			'/wp-content/themes/enigma/script.js',
			'https://static.parastorage.com/services/website-scrolling-effect/1.660.0/js/website-scrolling-effect.js',
			'https://userapp.zyrosite.com/script.js',
			'https://mywebsite-editor.com/script.js',
			'https://website-editor.net/script.js',
			'https://app.stackbit.com/script.js',
		];
		foreach ( $script_links as $link ) {
			$url = ( strpos( $link, 'http' ) === 0 ) ? $link : home_url( $link );
			echo '<link rel="prefetch" href="' . esc_url( $url ) . '" as="script" data-wpc="1">' . "\n";
		}
	}

	/**
	 * Determine whether this request is a frontend HTML request.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	private function should_run_for_frontend_fuckalyzer() {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return false;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) ) : '';
		if ( '' !== $accept && false === strpos( $accept, 'text/html' ) && false === strpos( $accept, '*/*' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether server-side spoofing should be emitted.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	private function should_emit_server_side_spoofs_fuckalyzer() {
		return $this->should_run_for_frontend_fuckalyzer() && $this->is_detected_browser_fuckalyzer();
	}

	/**
	 * Check if detection cookie is present.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	private function is_detected_browser_fuckalyzer() {
		return ! empty( $_COOKIE[ self::DETECTION_COOKIE_NAME ] );
	}

	/**
	 * Mark a browser as detected so spoofing can happen safely on subsequent frontend requests.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	private function mark_detected_browser_fuckalyzer() {
		if ( headers_sent() ) {
			return;
		}

		$this->set_cookie_compat_fuckalyzer( self::DETECTION_COOKIE_NAME, '1', time() + self::DETECTION_COOKIE_TTL, false );
		$_COOKIE[ self::DETECTION_COOKIE_NAME ] = '1';
	}

	/**
	 * Cross-version cookie helper.
	 *
	 * @since 2.3.0
	 * @param string $name     Cookie name.
	 * @param string $value    Cookie value.
	 * @param int    $expires  Expiration unix timestamp.
	 * @param bool   $http_only Whether cookie is HTTP only.
	 * @return void
	 */
	private function set_cookie_compat_fuckalyzer( $name, $value, $expires = 0, $http_only = true ) {
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				$name,
				$value,
				[
					'expires'  => (int) $expires,
					'path'     => '/',
					'domain'   => '',
					'secure'   => is_ssl(),
					'httponly' => (bool) $http_only,
					'samesite' => 'Lax',
				]
			);
			return;
		}

		setcookie( $name, $value, (int) $expires, '/; samesite=Lax', '', is_ssl(), (bool) $http_only );
	}

	/**
	 * Generate a random hex string with a safe fallback.
	 *
	 * @since 2.3.0
	 * @param int $bytes Number of bytes.
	 * @return string
	 */
	private function random_hex_fuckalyzer( $bytes ) {
		try {
			return bin2hex( random_bytes( $bytes ) );
		} catch ( Exception $exception ) {
			return wp_generate_password( $bytes * 2, false, false );
		}
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', [ 'Fuckalyzer_Confuser', 'init_fuckalyzer' ] );
