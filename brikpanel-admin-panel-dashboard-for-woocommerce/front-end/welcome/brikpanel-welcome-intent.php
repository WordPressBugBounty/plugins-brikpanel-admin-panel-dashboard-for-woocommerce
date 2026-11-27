<?php
/**
 * BrikPanel: the welcome tour's first screen, "What did you install BrikPanel
 * for?" (owner's request, 26 November 2026, after BrikFront's setup guide).
 *
 * Two cards. "The full panel" shows WooCommerce's own Orders and Dashboard
 * screens turning into BrikPanel's (real captures of the bp-shots demo store);
 * "See it bigger" opens both screens large with a line to drag. "Just one
 * feature" leads to the deactivation survey's nine features: the one picked
 * stays on, WordPress's and WooCommerce's own admin stays as it is
 * (includes/brikpanel-one-feature.php), and the tour ends on its last panel
 * with that one link.
 *
 * Who is asked: once per site, on a store's first tour (nobody closed the tour
 * there before), at most 30 days after BrikPanel was first activated, someone
 * who may switch the full panel off. Everyone else gets the tour as before.
 * Closing or skipping the first screen keeps the full panel (as before).
 *
 * @package BrikPanel
 * @since   3.3.58
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BRIKPANEL_WELCOME_INTENT_ASKED_OPT' ) ) {
	define( 'BRIKPANEL_WELCOME_INTENT_ASKED_OPT', 'brikpanel_welcome_intent_asked' );
}

/**
 * Whether the current user may make the choice: it switches the whole store
 * to one feature, so it is the master switch's people (administrators, roles
 * the owner added), with BrikPanel's settings open to them, and not in
 * personal mode, where the switch only governs the user's own account.
 *
 * @return bool
 */
function brikpanel_welcome_intent_user_can() {
	if ( function_exists( 'brikpanel_access_personal_mode_active' ) && brikpanel_access_personal_mode_active() ) {
		return false;
	}
	if ( ! function_exists( 'brikpanel_can_use_master_switch' ) || ! brikpanel_can_use_master_switch() ) {
		return false;
	}
	return ! function_exists( 'brikpanel_user_can_open_settings' ) || brikpanel_user_can_open_settings();
}

/**
 * Whether anybody on this site has closed the welcome tour before, in any
 * version: then the store is past its first tour.
 *
 * @return bool
 */
function brikpanel_welcome_seen_on_site() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed lookup, only while the one-time tour renders.
	$seen = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT 1", '_brikpanel_welcome_dismissed' ) );
	/**
	 * Whether this site is past its first welcome tour.
	 *
	 * @param bool $seen Default: somebody closed the tour here before.
	 */
	return (bool) apply_filters( 'brikpanel_welcome_seen_on_site', $seen );
}

/**
 * Whether the tour on this request opens on "What did you install BrikPanel
 * for?".
 *
 * @return bool
 */
function brikpanel_welcome_intent_applies() {
	$applies = false;
	if ( function_exists( 'brikpanel_master_enabled' ) && brikpanel_master_enabled()
		&& function_exists( 'brikpanel_one_feature_map' )
		&& brikpanel_welcome_intent_user_can()
		&& ! get_option( BRIKPANEL_WELCOME_INTENT_ASKED_OPT ) ) { // prime-ignore: read only while the one-time tour renders.
		$first   = (int) get_option( 'brikpanel_activated_at', 0 );
		$days    = defined( 'BRIKPANEL_WELCOME_SOURCE_DAYS' ) ? (int) BRIKPANEL_WELCOME_SOURCE_DAYS : 30;
		$applies = $first > 0 && time() - $first <= $days * DAY_IN_SECONDS && ! brikpanel_welcome_seen_on_site();
	}
	/**
	 * Whether the welcome tour opens on "What did you install BrikPanel for?".
	 * Tests and staging sites turn it on for an old install.
	 *
	 * @param bool $applies Default: the site's first tour, at most 30 days after the first activation, for someone who may switch the full panel off.
	 */
	return (bool) apply_filters( 'brikpanel_welcome_intent_applies', $applies );
}

/**
 * Remembers that this site has seen its first tour: the question is asked once.
 *
 * @return void
 */
function brikpanel_welcome_intent_mark_asked() {
	if ( ! get_option( BRIKPANEL_WELCOME_INTENT_ASKED_OPT ) ) { // prime-ignore: written once, when the first tour closes or a feature is picked.
		update_option( BRIKPANEL_WELCOME_INTENT_ASKED_OPT, time(), false );
	}
}

/* ── AJAX: "Just one feature" ────────────────────────────────────────────────── */

/**
 * Keeps the picked feature only (includes/brikpanel-one-feature.php) and
 * answers with the address of its screen.
 *
 * @return void
 */
function brikpanel_welcome_intent_ajax() {
	check_ajax_referer( 'brikpanel_welcome_nonce' );
	if ( ! brikpanel_welcome_intent_user_can() ) {
		wp_send_json_error( null, 403 );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
	$key = isset( $_POST['feature'] ) ? sanitize_key( wp_unslash( $_POST['feature'] ) ) : '';
	if ( ! function_exists( 'brikpanel_one_feature_apply' ) || ! brikpanel_one_feature_apply( $key ) ) {
		wp_send_json_error( null, 400 );
	}
	brikpanel_welcome_intent_mark_asked();
	wp_send_json_success( [ 'url' => brikpanel_one_feature_url( $key ) ] );
}
add_action( 'wp_ajax_brikpanel_welcome_intent', 'brikpanel_welcome_intent_ajax' );

/* ── What the script needs ───────────────────────────────────────────────────── */

/**
 * Per feature: its name (the survey's), the screen it opens and a line about
 * that screen, for the last panel's link and button.
 *
 * @return array<string, array{name: string, screen: string, desc: string}>
 */
function brikpanel_welcome_intent_features() {
	$names   = brikpanel_one_feature_labels();
	$screens = brikpanel_one_feature_screen_names();
	$descs   = [
		'abandoned_cart' => __( 'Carts left at checkout, and the ones that came back', 'brikpanel' ),
		'dashboard'      => __( 'Sales, net profit and live visitors', 'brikpanel' ),
		'products'       => __( 'Edit price and stock right in the list', 'brikpanel' ),
		'orders'         => __( 'New orders and quick status changes', 'brikpanel' ),
		'google_sheets'  => __( 'Sync your store to a sheet', 'brikpanel' ),
		'ads_profit'     => __( 'Google and Meta spend, and your true ROAS', 'brikpanel' ),
		'inventory'      => __( 'Suppliers and stock orders', 'brikpanel' ),
		'coupons'        => __( 'Your coupons in one clear list', 'brikpanel' ),
		'login'          => '',
	];
	$out = [];
	foreach ( $names as $key => $name ) {
		$out[ $key ] = [
			'name'   => $name,
			'screen' => isset( $screens[ $key ] ) ? $screens[ $key ] : '',
			'desc'   => isset( $descs[ $key ] ) ? $descs[ $key ] : '',
		];
	}
	return $out;
}

/**
 * The words the script writes on its buttons, for brikpanelWelcome.i18n.
 *
 * @return array<string, string>
 */
function brikpanel_welcome_intent_i18n() {
	return [
		'pick_one'     => _x( 'Pick one', 'welcome tour button', 'brikpanel' ),
		'pick_feature' => _x( 'Pick a feature', 'welcome tour button', 'brikpanel' ),
		'continue'     => _x( 'Continue', 'welcome tour button', 'brikpanel' ),
		/* translators: %s: screen name, e.g. "Abandoned Carts". */
		'open_screen'  => _x( 'Open %s', 'welcome tour button', 'brikpanel' ),
		'close'        => _x( 'Close', 'welcome tour button', 'brikpanel' ),
		'apply_error'  => __( 'Your choice was not saved. Please try again.', 'brikpanel' ),
	];
}

/* ── Markup ──────────────────────────────────────────────────────────────────── */

/**
 * One of the small line icons of the tour's new screens.
 *
 * @param string $name Icon name.
 * @param int    $size Width and height.
 * @return string Static SVG.
 */
function brikpanel_welcome_intent_icon( $name, $size = 20 ) {
	$paths = [
		'cart'      => '<path d="M3 4h2.2l2.1 10.1a1.6 1.6 0 0 0 1.6 1.3h8.2a1.6 1.6 0 0 0 1.6-1.2L20.5 8H6.1"/><circle cx="9.5" cy="19.5" r="1.4"/><circle cx="17" cy="19.5" r="1.4"/>',
		'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="4" rx="1"/><rect x="14" y="11" width="7" height="10" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
		'products'  => '<path d="M3.5 12.6V4.5a1 1 0 0 1 1-1h8.1l7.9 7.9a1.5 1.5 0 0 1 0 2.1l-6.9 6.9a1.5 1.5 0 0 1-2.1 0z"/><circle cx="8.2" cy="8.2" r="1.5"/>',
		'orders'    => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/>',
		'sheets'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 9v12"/>',
		'ads'       => '<path d="M3.5 17.5l6-6 4 4 7-7"/><path d="M15 8.5h5.5V14"/>',
		'inventory' => '<path d="M3.5 7.8L12 3.5l8.5 4.3v8.4L12 20.5l-8.5-4.3z"/><path d="M3.5 7.8L12 12l8.5-4.2M12 12v8.5"/>',
		'coupons'   => '<path d="M3.5 7.5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2V10a2 2 0 0 0 0 4v2.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2V14a2 2 0 0 0 0-4z"/><path d="M9.5 14.5l5-5"/><circle cx="9.7" cy="9.7" r=".9"/><circle cx="14.3" cy="14.3" r=".9"/>',
		'login'     => '<path d="M14 4h3.5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H14"/><path d="M10 16l4-4-4-4M14 12H4"/>',
		'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="4" rx="1"/><rect x="14" y="11" width="7" height="10" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
		'switch'    => '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 9M20 4v5h-5"/><path d="M20 12a8 8 0 0 1-13.7 5.6L4 15M4 20v-5h5"/>',
		'zoom'      => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.4-4.4M11 8.5v5M8.5 11h5"/>',
		'drag'      => '<path d="M9 6l-6 6 6 6M15 6l6 6-6 6"/>',
		'tick'      => '<path d="M3.5 12.5l5 5L20.5 6"/>',
	];
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$size = (int) $size;
	return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * The picked card's check and the picked feature's check.
 *
 * @return string Static SVG.
 */
function brikpanel_welcome_intent_check() {
	return '<svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 10l3 3 7-7"/></svg>';
}

/**
 * The real screens of the full panel card, WooCommerce's own and BrikPanel's,
 * Orders then Dashboard.
 *
 * @return array<string, array{before: string, after: string}>
 */
function brikpanel_welcome_intent_shots() {
	$base = BRIKPANEL_URL . 'front-end/welcome/images/';
	return [
		'a' => [
			'before' => $base . 'welcome-before-orders.webp',
			'after'  => $base . 'welcome-after-orders.webp',
		],
		'b' => [
			'before' => $base . 'welcome-before-dashboard.webp',
			'after'  => $base . 'welcome-after-dashboard.webp',
		],
	];
}

/**
 * The first two screens: the two ways, then the nine features.
 *
 * @return string Escaped markup, printed through wp_kses() with brikpanel_welcome_intent_kses().
 */
function brikpanel_welcome_intent_markup() {
	$check = brikpanel_welcome_intent_check();
	$shots = brikpanel_welcome_intent_shots();
	$ba    = '<span class="brikpanel-welcome-ba">';
	foreach ( [ 'a', 'b' ] as $pair ) {
		$ba .= '<span class="brikpanel-welcome-ba-pair brikpanel-welcome-ba-pair--' . $pair . '">'
			. '<img class="brikpanel-welcome-ba-img" src="' . esc_url( $shots[ $pair ]['before'] ) . '" alt="" width="1440" height="900" decoding="async" data-bw-ba-img="' . $pair . '-before">'
			. '<img class="brikpanel-welcome-ba-img brikpanel-welcome-ba-after" src="' . esc_url( $shots[ $pair ]['after'] ) . '" alt="" width="1440" height="900" decoding="async" data-bw-ba-img="' . $pair . '-after">'
			. '</span>';
	}
	$ba .= '<span class="brikpanel-welcome-ba-line"><span></span></span>'
		. '<span class="brikpanel-welcome-ba-tag brikpanel-welcome-ba-tag--before">WooCommerce</span>'
		. '<span class="brikpanel-welcome-ba-tag brikpanel-welcome-ba-tag--after">BrikPanel</span>'
		. '</span>';

	$html = '<section class="brikpanel-welcome-panel brikpanel-welcome-panel--intent" data-bw-panel="intent">'
		. '<p class="brikpanel-welcome-intent-brand" dir="ltr"><span class="brikpanel-welcome-brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>BrikPanel</p>'
		. '<h3 class="brikpanel-welcome-heading brikpanel-welcome-heading--duo" id="brikpanel-welcome-intent-title" tabindex="-1" data-bw-kicker>'
		// Its own context: the exit survey asks the same words in the past ("had installed").
		. '<span>' . esc_html_x( 'What did you install BrikPanel for?', 'welcome tour question, asked right after installing', 'brikpanel' ) . '</span> '
		. '<span class="brikpanel-welcome-heading-soft">' . esc_html__( 'You can switch any time.', 'brikpanel' ) . '</span></h3>'
		. '<div class="brikpanel-welcome-ways" role="radiogroup" aria-labelledby="brikpanel-welcome-intent-title">'
		// The full panel: real screens, and the way to see them large.
		. '<div class="brikpanel-welcome-way-slot">'
		. '<label class="brikpanel-welcome-way" data-bw-way="full">'
		. '<input type="radio" name="brikpanel-welcome-way" value="full">'
		. '<span class="brikpanel-welcome-way-art" dir="ltr" aria-hidden="true">' . $ba
		. '<span class="brikpanel-welcome-way-tick">' . $check . '</span></span>'
		. '<span class="brikpanel-welcome-way-text">'
		. '<span class="brikpanel-welcome-way-name">' . esc_html__( 'The full panel', 'brikpanel' ) . '</span>'
		. '<span class="brikpanel-welcome-way-line">' . esc_html__( 'A new, faster admin for your whole store.', 'brikpanel' ) . '</span>'
		. '</span>'
		. '<span class="brikpanel-welcome-way-fact">' . brikpanel_welcome_intent_icon( 'grid', 14 ) . esc_html__( 'Dashboard, orders, products and more', 'brikpanel' ) . '</span>'
		. '</label>'
		. '<button type="button" class="brikpanel-welcome-ba-open" data-bw-ba-open aria-controls="brikpanel-welcome-compare" aria-expanded="false">'
		. brikpanel_welcome_intent_icon( 'zoom', 14 ) . esc_html__( 'See it bigger', 'brikpanel' ) . '</button>'
		. '</div>'
		// Just one feature: WordPress's own admin, one row circled.
		. '<div class="brikpanel-welcome-way-slot">'
		. '<label class="brikpanel-welcome-way" data-bw-way="one">'
		. '<input type="radio" name="brikpanel-welcome-way" value="one">'
		. '<span class="brikpanel-welcome-way-art brikpanel-welcome-way-art--one" dir="ltr" aria-hidden="true">'
		. ( function_exists( 'brikpanel_welcome_way_one_sketch' ) ? brikpanel_welcome_way_one_sketch() : '' )
		. '<span class="brikpanel-welcome-way-tick">' . $check . '</span></span>'
		. '<span class="brikpanel-welcome-way-text">'
		. '<span class="brikpanel-welcome-way-name">' . esc_html__( 'Just one feature', 'brikpanel' ) . '</span>'
		. '<span class="brikpanel-welcome-way-line">' . esc_html__( 'Your WordPress admin stays as it is.', 'brikpanel' ) . '</span>'
		. '</span>'
		. '<span class="brikpanel-welcome-way-fact">' . brikpanel_welcome_intent_icon( 'switch', 14 ) . esc_html__( 'Full panel any time, in one click', 'brikpanel' ) . '</span>'
		. '</label>'
		. '</div>'
		. '</div>'
		. '</section>';

	// The nine features, in the survey's order.
	$icons = [];
	foreach ( brikpanel_one_feature_map() as $key => $def ) {
		$icons[ $key ] = isset( $def['icon'] ) ? (string) $def['icon'] : '';
	}
	$picks = '';
	foreach ( brikpanel_one_feature_labels() as $key => $name ) {
		$picks .= '<label class="brikpanel-welcome-pick" data-bw-feature="' . esc_attr( $key ) . '">'
			. '<input type="radio" name="brikpanel-welcome-feature" value="' . esc_attr( $key ) . '">'
			. '<span class="brikpanel-welcome-pick-icon">' . brikpanel_welcome_intent_icon( isset( $icons[ $key ] ) ? $icons[ $key ] : '' )
			. '<span class="brikpanel-welcome-pick-check">' . $check . '</span></span>'
			. '<span class="brikpanel-welcome-pick-name">' . esc_html( $name ) . '</span>'
			. '</label>';
	}
	$html .= '<section class="brikpanel-welcome-panel brikpanel-welcome-panel--pick" data-bw-panel="pick" hidden>'
		. '<p class="brikpanel-welcome-kicker">' . esc_html__( 'Just one feature', 'brikpanel' ) . '</p>'
		. '<h3 class="brikpanel-welcome-heading brikpanel-welcome-heading--duo" id="brikpanel-welcome-pick-title" tabindex="-1" data-bw-kicker>'
		. '<span>' . esc_html__( 'Which feature do you need?', 'brikpanel' ) . '</span> '
		. '<span class="brikpanel-welcome-heading-soft">' . esc_html__( 'Only this one turns on.', 'brikpanel' ) . '</span></h3>'
		. '<div class="brikpanel-welcome-picks" role="radiogroup" aria-labelledby="brikpanel-welcome-pick-title">' . $picks . '</div>'
		. '<p class="brikpanel-welcome-pick-note">' . brikpanel_welcome_intent_icon( 'tick', 16 ) . esc_html__( 'Everything else in your admin stays exactly as it is.', 'brikpanel' ) . '</p>'
		. '<p class="brikpanel-welcome-pick-error" role="alert" data-bw-pick-error hidden></p>'
		. '</section>';

	return $html;
}

/**
 * "See it bigger": the full panel card's two screens side by side, a line to drag.
 *
 * @return string Escaped markup, printed through wp_kses() with brikpanel_welcome_intent_kses().
 */
function brikpanel_welcome_intent_compare_markup() {
	return '<div class="brikpanel-welcome-compare" id="brikpanel-welcome-compare" role="group" aria-labelledby="brikpanel-welcome-compare-title" data-bw-compare hidden>'
		. '<div class="brikpanel-welcome-compare-head">'
		. '<h3 class="brikpanel-welcome-compare-title" id="brikpanel-welcome-compare-title">' . esc_html__( 'What the full panel changes', 'brikpanel' ) . '</h3>'
		. '<div class="brikpanel-welcome-compare-tabs" role="group" aria-label="' . esc_attr__( 'Screen', 'brikpanel' ) . '">'
		. '<button type="button" class="brikpanel-welcome-compare-tab" data-bw-compare-tab="a" aria-pressed="true">' . esc_html__( 'Orders', 'brikpanel' ) . '</button>'
		. '<button type="button" class="brikpanel-welcome-compare-tab" data-bw-compare-tab="b" aria-pressed="false">' . esc_html__( 'Dashboard', 'brikpanel' ) . '</button>'
		. '</div></div>'
		. '<div class="brikpanel-welcome-compare-stage">'
		. '<div class="brikpanel-welcome-compare-frame" dir="ltr" data-bw-compare-frame>'
		. '<img class="brikpanel-welcome-ba-img" src="" alt="' . esc_attr__( 'WooCommerce\'s own screen', 'brikpanel' ) . '" width="1440" height="900" data-bw-compare-before>'
		. '<img class="brikpanel-welcome-ba-img brikpanel-welcome-ba-after" src="" alt="' . esc_attr__( 'The same screen with BrikPanel', 'brikpanel' ) . '" width="1440" height="900" data-bw-compare-after>'
		. '<span class="brikpanel-welcome-compare-line" aria-hidden="true"><span class="brikpanel-welcome-compare-knob">' . brikpanel_welcome_intent_icon( 'drag', 18 ) . '</span></span>'
		. '<span class="brikpanel-welcome-ba-tag brikpanel-welcome-ba-tag--before" aria-hidden="true">WooCommerce</span>'
		. '<span class="brikpanel-welcome-ba-tag brikpanel-welcome-ba-tag--after" aria-hidden="true">BrikPanel</span>'
		. '<input type="range" class="brikpanel-welcome-compare-range" min="0" max="100" value="50" step="1" aria-label="' . esc_attr__( 'Drag to compare WooCommerce and BrikPanel', 'brikpanel' ) . '" data-bw-compare-range>'
		. '</div></div>'
		. '<div class="brikpanel-welcome-compare-foot">'
		. '<p class="brikpanel-welcome-compare-hint">' . esc_html__( 'Drag the line to compare.', 'brikpanel' ) . '</p>'
		. '<span class="brikpanel-welcome-footer-gap"></span>'
		. '<button type="button" class="brikpanel-btn brikpanel-btn--secondary brikpanel-welcome-compare-btn" data-bw-compare-close>' . esc_html_x( 'Back', 'welcome tour button', 'brikpanel' ) . '</button>'
		. '<button type="button" class="brikpanel-btn brikpanel-btn--primary brikpanel-welcome-compare-btn" data-bw-compare-pick>' . esc_html__( 'Use the full panel', 'brikpanel' ) . '</button>'
		. '</div>'
		. '</div>';
}

/**
 * What the new screens' markup may print: their own tags and attributes,
 * nothing else. Attribute names are lowercase because wp_kses lowercases
 * them; the HTML parser gives viewBox and pathLength their case back inside
 * an svg.
 *
 * @return array
 */
function brikpanel_welcome_intent_kses() {
	$common = [
		'class'           => true,
		'id'              => true,
		'role'            => true,
		'hidden'          => true,
		'dir'             => true,
		'tabindex'        => true,
		'aria-hidden'     => true,
		'aria-label'      => true,
		'aria-labelledby' => true,
		'aria-controls'   => true,
		'aria-expanded'   => true,
		'aria-pressed'    => true,
		'data-*'          => true,
	];
	$shape  = [
		'class'      => true,
		'd'          => true,
		'pathlength' => true,
		'x'          => true,
		'y'          => true,
		'width'      => true,
		'height'     => true,
		'rx'         => true,
		'cx'         => true,
		'cy'         => true,
		'r'          => true,
		'fill'       => true,
	];
	return [
		'section' => $common,
		'div'     => $common,
		'p'       => $common,
		'h3'      => $common,
		'span'    => $common,
		'b'       => $common,
		'label'   => $common,
		'button'  => $common + [ 'type' => true ],
		'input'   => $common + [
			'type'  => true,
			'name'  => true,
			'value' => true,
			'min'   => true,
			'max'   => true,
			'step'  => true,
		],
		'img'     => $common + [
			'src'      => true,
			'alt'      => true,
			'width'    => true,
			'height'   => true,
			'decoding' => true,
			'loading'  => true,
		],
		'svg'     => $common + [
			'viewbox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'focusable'       => true,
		],
		'path'    => $shape,
		'rect'    => $shape,
		'circle'  => $shape,
	];
}
