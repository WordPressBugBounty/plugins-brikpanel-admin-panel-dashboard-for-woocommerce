<?php
/**
 * BrikPanel: "Just one feature".
 *
 * A store that installed BrikPanel for one thing (abandoned carts, Google
 * Sheets, bulk editing...) says so on the welcome tour's first screen. From
 * then on WordPress's and WooCommerce's own admin stays exactly as it is, and
 * only that feature of BrikPanel is on. "Full panel" in WordPress's admin bar
 * turns on everything with one click.
 *
 * Built on the master switch, not next to it. With the switch off BrikPanel
 * already hands every back-office user the stock admin (the access-control
 * layer forces the interface toggles to "no", sweeps BrikPanel's files and
 * menu rows). One feature is that state plus one exception: the toggles,
 * files, menu rows and screens of the chosen feature stay. The storefront,
 * REST, cron and AJAX keep working as with the switch off: cart capture and
 * visitor counts go on, so a store that turns on the full panel later finds
 * its data there.
 *
 * The feature is remembered when the full panel is turned on: switching the
 * panel off again (the power switch in BrikPanel's top bar) brings the store
 * back to its one feature, not to everything off. Owner decision, 26 November
 * 2026.
 *
 * Nothing here runs while the master switch is on.
 *
 * @package BrikPanel
 * @since   3.3.58
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BRIKPANEL_ONE_FEATURE_OPT' ) ) {
	define( 'BRIKPANEL_ONE_FEATURE_OPT', 'brikpanel_one_feature' );
}

/**
 * The features a store can keep alone, keyed like the deactivation survey's
 * "What did you install BrikPanel for?" list (brikpanel_exit_survey_features()),
 * so the two answers can be read side by side.
 *
 * Each entry:
 * - options: interface toggles (brikpanel_access_gated_options()) that stay as saved
 * - pages:   ?page= screens of the feature: their files, page shell and menu rows stay
 * - screens: other admin screens of the feature (screen ids), for their files
 * - parts:   names the feature's own code asks for with brikpanel_neutralized_for()
 * - folders: where the feature's files live, relative to the plugin folder
 * - global:  folders whose files stay on every admin screen (order alerts)
 * - menus:   menu rows (page slugs) that stay in WordPress's menu
 * - url:     the screen the tour's last button opens, relative to wp-admin
 *            ('' for none; 'orders' is the order list of the store's storage)
 * - icon:    the tour's icon for it
 *
 * Read on every admin request while the full panel is off, so it is built
 * once per site and request.
 *
 * @return array<string, array<string, mixed>>
 */
function brikpanel_one_feature_map() {
	static $cache = [];
	$blog = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
	if ( isset( $cache[ $blog ] ) ) {
		return $cache[ $blog ];
	}
	$orders = [ 'woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order' ];

	$map = [
		'abandoned_cart' => [
			'options' => [],
			'pages'   => [ 'brikpanel-abandoned-carts' ],
			'screens' => [],
			'parts'   => [ 'carts' ],
			'folders' => [ 'front-end/cart-abandonment/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-abandoned-carts' ],
			'url'     => 'admin.php?page=brikpanel-abandoned-carts',
			'icon'    => 'cart',
		],
		'dashboard'      => [
			'options' => [ 'brikpanel_modern_dashboard' ],
			'pages'   => [ 'brikpanel-dashboard', 'brikpanel-customers', 'brikpanel-segments' ],
			'screens' => [],
			'parts'   => [ 'dashboard', 'customers', 'segments' ],
			'folders' => [ 'front-end/dashboard/', 'front-end/customers/', 'front-end/customer-analytics/', 'front-end/segments/', 'front-end/push/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-dashboard', 'brikpanel-customers', 'brikpanel-segments' ],
			'url'     => 'admin.php?page=brikpanel-dashboard',
			'icon'    => 'dashboard',
		],
		'products'       => [
			'options' => [ 'brikpanel_modern_products_list' ],
			'pages'   => [ 'brikpanel-products' ],
			'screens' => [],
			'parts'   => [ 'products' ],
			'folders' => [ 'front-end/products/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-products' ],
			'url'     => 'admin.php?page=brikpanel-products',
			'icon'    => 'products',
		],
		'orders'         => [
			'options' => [
				'brikpanel_orders_enhancements',
				'brikpanel_orders_compact_list',
				'brikpanel_modern_order_edit',
				'brikpanel_order_notify_popup',
				'brikpanel_order_notify_sound',
				'brikpanel_order_notify_confetti',
			],
			'pages'   => [ 'brikpanel-merge-orders', 'brikpanel-print-orders', 'brikpanel-segments' ],
			'screens' => $orders,
			'parts'   => [ 'orders', 'segments' ],
			'folders' => [ 'front-end/orders/', 'front-end/order/', 'front-end/order-statuses/', 'front-end/segments/' ],
			// A new order is heard on every screen, as with the full panel.
			'global'  => [ 'front-end/sound/', 'assets/js/confetti.browser.min.js' ],
			'menus'   => [ 'brikpanel-segments' ],
			'url'     => 'orders',
			'icon'    => 'orders',
		],
		'google_sheets'  => [
			'options' => [],
			'pages'   => [ 'brikpanel-google-sheets' ],
			'screens' => [],
			'parts'   => [ 'google_sheets' ],
			'folders' => [ 'front-end/google-sheets/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-google-sheets' ],
			'url'     => 'admin.php?page=brikpanel-google-sheets',
			'icon'    => 'sheets',
		],
		// Net profit, ROAS and expenses are read on the dashboard.
		'ads_profit'     => [
			'options' => [ 'brikpanel_modern_dashboard' ],
			'pages'   => [ 'brikpanel-ad-platforms', 'brikpanel-dashboard', 'brikpanel-expenses' ],
			'screens' => [],
			'parts'   => [ 'ads', 'dashboard', 'expenses' ],
			'folders' => [ 'front-end/ad-platforms/', 'front-end/dashboard/', 'front-end/expenses/', 'front-end/push/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-ad-platforms', 'brikpanel-dashboard', 'brikpanel-expenses' ],
			'url'     => 'admin.php?page=brikpanel-ad-platforms',
			'icon'    => 'ads',
		],
		'inventory'      => [
			'options' => [],
			'pages'   => [ 'brikpanel-vendors', 'brikpanel-stock-orders', 'brikpanel-expenses' ],
			'screens' => [],
			'parts'   => [ 'vendors' ],
			'folders' => [ 'front-end/vendors/', 'front-end/expenses/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-vendors', 'brikpanel-stock-orders', 'brikpanel-expenses' ],
			'url'     => 'admin.php?page=brikpanel-vendors',
			'icon'    => 'inventory',
		],
		'coupons'        => [
			'options' => [ 'brikpanel_modern_coupons' ],
			'pages'   => [ 'brikpanel-coupons' ],
			'screens' => [],
			'parts'   => [ 'coupons' ],
			'folders' => [ 'front-end/coupons/' ],
			'global'  => [],
			'menus'   => [ 'brikpanel-coupons' ],
			'url'     => 'admin.php?page=brikpanel-coupons',
			'icon'    => 'coupons',
		],
		// The login page is not an admin screen: it simply stays BrikPanel's.
		'login'          => [
			'options' => [],
			'pages'   => [],
			'screens' => [],
			'parts'   => [ 'login' ],
			'folders' => [],
			'global'  => [],
			'menus'   => [],
			'url'     => '',
			'icon'    => 'login',
		],
	];

	/**
	 * Filters what each "Just one feature" choice keeps on.
	 *
	 * @param array<string, array<string, mixed>> $map Keyed by feature.
	 */
	$cache[ $blog ] = (array) apply_filters( 'brikpanel_one_feature_map', $map );
	return $cache[ $blog ];
}

/**
 * The address of the screen a feature opens on, or '' when it has none.
 *
 * @param string $key Feature key.
 * @return string
 */
function brikpanel_one_feature_url( $key ) {
	$map = brikpanel_one_feature_map();
	$url = isset( $map[ $key ]['url'] ) ? (string) $map[ $key ]['url'] : '';
	if ( '' === $url ) {
		return '';
	}
	if ( 'orders' === $url ) {
		return function_exists( 'brikpanel_wc_orders_list_url' ) ? brikpanel_wc_orders_list_url() : admin_url( 'edit.php?post_type=shop_order' );
	}
	return admin_url( $url );
}

/**
 * The feature names, as the welcome tour and the deactivation survey show them.
 *
 * @return array<string, string>
 */
function brikpanel_one_feature_labels() {
	$labels = function_exists( 'brikpanel_exit_survey_features' ) ? brikpanel_exit_survey_features() : [];
	unset( $labels['other'] );
	return array_intersect_key( $labels, brikpanel_one_feature_map() );
}

/**
 * The name of the screen each feature opens on, as WordPress's menu shows it.
 *
 * @return array<string, string>
 */
function brikpanel_one_feature_screen_names() {
	return [
		'abandoned_cart' => __( 'Abandoned Carts', 'brikpanel' ),
		'dashboard'      => __( 'Dashboard', 'brikpanel' ),
		'products'       => __( 'Products', 'brikpanel' ),
		'orders'         => __( 'Orders', 'brikpanel' ),
		'google_sheets'  => __( 'Google Sheets', 'brikpanel' ),
		'ads_profit'     => __( 'Ad Platforms', 'brikpanel' ),
		'inventory'      => __( 'Suppliers', 'brikpanel' ),
		'coupons'        => __( 'Coupons', 'brikpanel' ),
		'login'          => __( 'Login page', 'brikpanel' ),
	];
}

/**
 * The feature this store keeps when the full panel is off, or ''.
 *
 * @return string
 */
function brikpanel_one_feature() {
	$key = get_option( BRIKPANEL_ONE_FEATURE_OPT, '' );
	return is_string( $key ) && '' !== $key && array_key_exists( $key, brikpanel_one_feature_map() ) ? $key : '';
}

/**
 * Whether the store runs on one feature right now: the full panel is off and
 * a feature was chosen.
 *
 * @return bool
 */
function brikpanel_one_feature_active() {
	// Also read before the access-control layer is loaded (the login module
	// decides at file scope): the master switch's option itself then.
	$master_on = function_exists( 'brikpanel_master_enabled' )
		? brikpanel_master_enabled()
		: 'no' !== get_option( 'brikpanel_master_enabled', 'yes' );
	return ! $master_on && '' !== brikpanel_one_feature();
}

/**
 * The chosen feature's entry for the current user, or null.
 *
 * The choice is the store's, but someone the owner keeps on WordPress's own
 * admin (the access rules, personal mode) does not get it back this way.
 *
 * @return array<string, mixed>|null
 */
function brikpanel_one_feature_def() {
	if ( ! brikpanel_one_feature_active() ) {
		return null;
	}
	if ( function_exists( 'brikpanel_access_is_disabled_for_user' ) && brikpanel_access_is_disabled_for_user() ) {
		return null;
	}
	$map = brikpanel_one_feature_map();
	return isset( $map[ brikpanel_one_feature() ] ) ? $map[ brikpanel_one_feature() ] : null;
}

/**
 * Whether an interface toggle keeps its saved value although the full panel
 * is off, because it belongs to the chosen feature.
 *
 * @param string $option Option name.
 * @return bool
 */
function brikpanel_one_feature_keeps_option( $option ) {
	$def = brikpanel_one_feature_def();
	return $def && in_array( (string) $option, (array) $def['options'], true );
}

/**
 * Whether one of the named parts belongs to the chosen feature.
 *
 * @param string|string[] $parts Part names from brikpanel_one_feature_map().
 * @return bool
 */
function brikpanel_one_feature_keeps( $parts ) {
	$def = brikpanel_one_feature_def();
	return $def && (bool) array_intersect( (array) $parts, (array) $def['parts'] );
}

/**
 * Whether BrikPanel steps aside for this request, for the code of the named
 * parts: it does when the interface is off for the user, unless the store
 * kept one of these parts as its one feature.
 *
 * @param string|string[] $parts Part names from brikpanel_one_feature_map().
 * @return bool
 */
function brikpanel_neutralized_for( $parts ) {
	if ( ! function_exists( 'brikpanel_access_should_neutralize' ) || ! brikpanel_access_should_neutralize() ) {
		return false;
	}
	return ! brikpanel_one_feature_keeps( $parts );
}

/**
 * The ?page= of the current request ('' for none).
 *
 * @return string
 */
function brikpanel_one_feature_request_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen lookup.
	return isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
}

/**
 * Whether a ?page= screen belongs to the chosen feature.
 *
 * @param string|null $page Page slug; the current request's when null.
 * @return bool
 */
function brikpanel_one_feature_page_kept( $page = null ) {
	$def = brikpanel_one_feature_def();
	if ( ! $def ) {
		return false;
	}
	$page = null === $page ? brikpanel_one_feature_request_page() : (string) $page;
	return '' !== $page && in_array( $page, (array) $def['pages'], true );
}

/**
 * Whether a ?page= screen belongs to the store's remembered feature, while
 * the full panel is on as well (where to land when it is switched off).
 *
 * @param string $page Page slug.
 * @return bool
 */
function brikpanel_one_feature_page_belongs( $page ) {
	$key = brikpanel_one_feature();
	$map = brikpanel_one_feature_map();
	return '' !== $key && '' !== (string) $page && in_array( (string) $page, (array) $map[ $key ]['pages'], true );
}

/**
 * The remembered feature's name for the current user, or '' when the store has
 * none or the user governs BrikPanel for their own account (personal mode).
 *
 * @return string
 */
function brikpanel_one_feature_name() {
	if ( function_exists( 'brikpanel_access_personal_mode_active' ) && brikpanel_access_personal_mode_active() ) {
		return '';
	}
	$labels = brikpanel_one_feature_labels();
	$key    = brikpanel_one_feature();
	return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
}

/**
 * Whether the current admin screen belongs to the chosen feature.
 *
 * @return bool
 */
function brikpanel_one_feature_on_screen() {
	$def = brikpanel_one_feature_def();
	if ( ! $def ) {
		return false;
	}
	if ( brikpanel_one_feature_page_kept() ) {
		return true;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && in_array( (string) $screen->id, (array) $def['screens'], true );
}

/**
 * Turns the store to one feature: the full panel off, this feature kept.
 *
 * @param string $key A key of brikpanel_one_feature_map().
 * @return bool False for an unknown key.
 */
function brikpanel_one_feature_apply( $key ) {
	$key = (string) $key;
	if ( ! array_key_exists( $key, brikpanel_one_feature_map() ) ) {
		return false;
	}
	update_option( BRIKPANEL_ONE_FEATURE_OPT, $key, true );
	// Suppliers and stock orders come switched off: choosing them is the ask.
	if ( 'inventory' === $key ) {
		update_option( 'brikpanel_vendors_enabled', 'yes' );
	}
	update_option( BRIKPANEL_MASTER_OPT, 'no' );

	/**
	 * Fires after a store chose to keep one feature only.
	 *
	 * @param string $key Feature key.
	 */
	do_action( 'brikpanel_one_feature_applied', $key );
	return true;
}

/* ── The chosen feature's files ──────────────────────────────────────────────── */

/**
 * Keeps the chosen feature's styles and scripts out of the access-control
 * sweep: on its own screens its folders, BrikPanel's shared parts and the
 * libraries in assets/; on every screen its "global" folders. Each kept file's
 * dependencies stay too, or WordPress would not print it.
 *
 * @param string[] $keep Handles already kept.
 * @return string[]
 */
function brikpanel_one_feature_sweep_keep( $keep ) {
	$def = brikpanel_one_feature_def();
	if ( ! $def || ! defined( 'BRIKPANEL_URL' ) ) {
		return $keep;
	}
	$folders = (array) $def['global'];
	if ( brikpanel_one_feature_on_screen() ) {
		$folders = array_merge( $folders, (array) $def['folders'], [ 'front-end/shared/', 'assets/' ] );
	}
	if ( ! $folders ) {
		return $keep;
	}
	$keep = (array) $keep;
	foreach ( [ wp_styles(), wp_scripts() ] as $assets ) {
		if ( ! $assets instanceof WP_Dependencies ) {
			continue;
		}
		$found = [];
		foreach ( $assets->registered as $handle => $dep ) {
			$src = isset( $dep->src ) ? (string) $dep->src : '';
			if ( '' === $src || 0 !== strpos( $src, BRIKPANEL_URL ) ) {
				continue;
			}
			$rel = substr( $src, strlen( BRIKPANEL_URL ) );
			foreach ( $folders as $folder ) {
				if ( 0 === strpos( $rel, $folder ) ) {
					$found[] = $handle;
					break;
				}
			}
		}
		// Their dependencies, all the way down.
		while ( $found ) {
			$handle = array_pop( $found );
			if ( in_array( $handle, $keep, true ) ) {
				continue;
			}
			$keep[] = $handle;
			if ( isset( $assets->registered[ $handle ] ) ) {
				foreach ( (array) $assets->registered[ $handle ]->deps as $child ) {
					$found[] = (string) $child;
				}
			}
		}
	}
	return $keep;
}
add_filter( 'brikpanel_access_sweep_keep', 'brikpanel_one_feature_sweep_keep' );

/**
 * The chosen feature's rows in WordPress's menu, kept by the sweep that hides
 * every other BrikPanel row.
 *
 * @return string[] Page slugs.
 */
function brikpanel_one_feature_menu_keep() {
	$def = brikpanel_one_feature_def();
	return $def ? (array) $def['menus'] : [];
}

/* ── The login page ──────────────────────────────────────────────────────────── */

/**
 * The login page stays WordPress's own unless it is the feature kept. With the
 * full panel switched off and no feature chosen it stays as it was (the switch
 * has never touched the login page).
 *
 * @param mixed $pre Short-circuit value.
 * @return mixed
 */
function brikpanel_one_feature_login( $pre ) {
	if ( brikpanel_one_feature_active() && 'login' !== brikpanel_one_feature() ) {
		return 'no';
	}
	return $pre;
}
add_filter( 'pre_option_brikpanel_modern_login', 'brikpanel_one_feature_login' );

/* ── Suppliers ───────────────────────────────────────────────────────────────── */

/**
 * The Suppliers module stays out unless it is the feature kept. With it off,
 * Operational Expenses stands as its own menu row instead of under Suppliers,
 * so the Ad spend and profit feature keeps it without the Suppliers menu. The
 * BrikPanel settings tab still shows the saved value (it is never neutralized).
 *
 * @param mixed $pre Short-circuit value.
 * @return mixed
 */
function brikpanel_one_feature_vendors( $pre ) {
	if ( function_exists( 'brikpanel_access_should_neutralize' ) && brikpanel_access_should_neutralize()
		&& brikpanel_one_feature_def() && ! brikpanel_one_feature_keeps( 'vendors' ) ) {
		return 'no';
	}
	return $pre;
}
add_filter( 'pre_option_brikpanel_vendors_enabled', 'brikpanel_one_feature_vendors' );

/* ── Other BrikPanel screens ─────────────────────────────────────────────────── */

/**
 * A BrikPanel screen that is not part of the chosen feature (a link on the
 * feature's own screen, an old bookmark) opens the card that says so, with the
 * button that turns on the full panel. With the full panel off its files are
 * swept, so the screen itself would only show unstyled.
 *
 * @return void
 */
function brikpanel_one_feature_redirect_other_screens() {
	if ( wp_doing_ajax() || ! brikpanel_one_feature_def() ) {
		return;
	}
	$page = brikpanel_one_feature_request_page();
	if ( '' === $page || 0 !== strpos( $page, 'brikpanel' ) || brikpanel_one_feature_page_kept( $page ) ) {
		return;
	}
	if ( defined( 'BRIKPANEL_MODULE_OFF_PAGE' ) && BRIKPANEL_MODULE_OFF_PAGE === $page ) {
		return;
	}
	wp_safe_redirect( add_query_arg( [ 'page' => BRIKPANEL_MODULE_OFF_PAGE, 'module' => $page ], admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_init', 'brikpanel_one_feature_redirect_other_screens', 1 );

/* ── Import / Export ─────────────────────────────────────────────────────────── */

/**
 * The choice is this store's setup, not a layout worth copying to another
 * store: it never travels.
 *
 * @param array $map Registry so far.
 * @return array
 */
function brikpanel_one_feature_register_export_keys( $map ) {
	$map[ BRIKPANEL_ONE_FEATURE_OPT ]   = [ 'class' => 'internal' ];
	$map['brikpanel_welcome_intent_asked'] = [ 'class' => 'internal' ];
	return $map;
}
add_filter( 'brikpanel_exportable_option_keys', 'brikpanel_one_feature_register_export_keys' );
