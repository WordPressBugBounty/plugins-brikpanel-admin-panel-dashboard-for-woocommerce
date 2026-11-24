<?php
/**
 * BrikPanel: "Where did you first hear about BrikPanel?" on the welcome tour's
 * last screen.
 *
 * Eight answers, one tap each, no Send button. Nothing leaves the site until
 * the owner taps one; then the answer goes to the Brksoft Survey endpoint on
 * brksoft.com with a random token made for this one tour, the BrikPanel version
 * and the admin language. The token lets a changed answer, or a note typed
 * after it, replace the first row instead of adding a second. Three answers
 * (YouTube, social media or a forum, somewhere else) offer an optional note:
 * which channel, which site, where. No site address, email or store data:
 * WordPress's default user agent carries the site address, so the request sets
 * its own, as the deactivation survey does (includes/brikpanel-exit-survey.php).
 * readme.txt documents this under "What data does BrikPanel send outside my
 * site?" (wp.org guideline 7: data leaves only on the user's own action).
 *
 * Asked once per site, and only in the first 30 days after BrikPanel was first
 * activated: the question is how the store found BrikPanel, so a second admin,
 * or an admin added to an old install later, gets the tour without it.
 *
 * Results: brksoft.com wp-admin, Survey > BrikPanel sources (the Brksoft Survey
 * plugin, source in the BrikMentor repo under tools/brksoft-survey/).
 *
 * Why (owner, 23 November 2026): an install from wp.org carries no trace of
 * where the merchant heard about BrikPanel, so a channel that brings a few
 * installs a week (a YouTube video, a post) is lost in the daily install count.
 *
 * @package BrikPanel
 * @since   3.3.56
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BRIKPANEL_WELCOME_SOURCE_ENDPOINT' ) ) {
	define( 'BRIKPANEL_WELCOME_SOURCE_ENDPOINT', 'https://brksoft.com/wp-json/brksoft-survey/v1/source' );
}

/** Longest note, in characters (the endpoint cuts at the same length). */
if ( ! defined( 'BRIKPANEL_WELCOME_SOURCE_NOTE_MAX' ) ) {
	define( 'BRIKPANEL_WELCOME_SOURCE_NOTE_MAX', 200 );
}

/** Days after the first activation during which the tour asks. */
if ( ! defined( 'BRIKPANEL_WELCOME_SOURCE_DAYS' ) ) {
	define( 'BRIKPANEL_WELCOME_SOURCE_DAYS', 30 );
}

/**
 * The answers in the order the tour shows them (two columns, read row by row).
 * The keys are the endpoint's allow-list. `prompt` is the placeholder of the
 * optional note box, '' for an answer that has none.
 *
 * @return array<string, array{label: string, icon: string, prompt: string}>
 */
function brikpanel_welcome_source_answers() {
	return [
		'wp_search' => [
			'label'  => __( 'WordPress plugin search', 'brikpanel' ),
			'icon'   => 'plug',
			'prompt' => '',
		],
		'google'    => [
			'label'  => __( 'Google search', 'brikpanel' ),
			'icon'   => 'search',
			'prompt' => '',
		],
		'youtube'   => [
			'label'  => __( 'YouTube', 'brikpanel' ),
			'icon'   => 'play',
			'prompt' => __( 'Which channel or video? (optional)', 'brikpanel' ),
		],
		'ai'        => [
			'label'  => __( 'ChatGPT or another AI', 'brikpanel' ),
			'icon'   => 'sparkle',
			'prompt' => '',
		],
		'friend'    => [
			'label'  => __( 'A friend or colleague', 'brikpanel' ),
			'icon'   => 'people',
			'prompt' => '',
		],
		'agency'    => [
			'label'  => __( 'My developer or agency', 'brikpanel' ),
			'icon'   => 'code',
			'prompt' => '',
		],
		'social'    => [
			'label'  => __( 'Social media or a forum', 'brikpanel' ),
			'icon'   => 'chat',
			'prompt' => __( 'Which site or group? (optional)', 'brikpanel' ),
		],
		'other'     => [
			'label'  => __( 'Somewhere else', 'brikpanel' ),
			'icon'   => 'more',
			'prompt' => __( 'Where? (optional)', 'brikpanel' ),
		],
	];
}

/**
 * Whether the tour on this request asks the question: nobody on this site has
 * answered it yet, BrikPanel was first activated at most 30 days ago (or the
 * date is not known) and the answer has somewhere to go.
 *
 * @return bool
 */
function brikpanel_welcome_source_applies() {
	$first   = (int) get_option( 'brikpanel_activated_at', 0 );
	$applies = ! get_option( 'brikpanel_welcome_source_sent' ) // prime-ignore: read only while the one-time tour renders.
		&& ( $first <= 0 || time() - $first <= BRIKPANEL_WELCOME_SOURCE_DAYS * DAY_IN_SECONDS )
		&& '' !== brikpanel_welcome_source_endpoint();
	/**
	 * Whether the tour's last panel asks where the owner heard about BrikPanel.
	 * Tests and staging sites turn it on for an old install.
	 *
	 * @param bool $applies Default: nobody answered here yet, first activation at most 30 days ago.
	 */
	return (bool) apply_filters( 'brikpanel_welcome_source_applies', $applies );
}

/**
 * Where the answer goes. Staging and tests point it elsewhere; '' asks nothing.
 *
 * @return string
 */
function brikpanel_welcome_source_endpoint() {
	/**
	 * Full URL of the /source route.
	 *
	 * @param string $endpoint Default: brksoft.com.
	 */
	return (string) apply_filters( 'brikpanel_welcome_source_endpoint', BRIKPANEL_WELCOME_SOURCE_ENDPOINT );
}

/**
 * One answer icon: static 18 px line art in the tour's style.
 *
 * @param string $name An icon name from brikpanel_welcome_source_answers().
 * @return string Trusted static SVG.
 */
function brikpanel_welcome_source_icon( $name ) {
	$paths = [
		'plug'    => '<path d="M9 3v4M15 3v4"/><path d="M6.5 7h11v3.5a5.5 5.5 0 0 1-11 0V7z"/><path d="M12 16v5"/>',
		'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.4-4.4"/>',
		'play'    => '<rect x="2.75" y="5.5" width="18.5" height="13" rx="3.5"/><path d="M10.25 9.4v5.2l4.4-2.6z"/>',
		'sparkle' => '<path d="M11 3.5l1.7 4.8 4.8 1.7-4.8 1.7L11 16.5l-1.7-4.8L4.5 10l4.8-1.7z"/><path d="M18 14.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/>',
		'people'  => '<circle cx="9" cy="8" r="3.25"/><path d="M3 19.5a6 6 0 0 1 12 0"/><path d="M15.5 5a3.25 3.25 0 0 1 0 6.3M17.5 14.2a6 6 0 0 1 3.5 5.3"/>',
		'code'    => '<path d="M8.5 7.5L4 12l4.5 4.5M15.5 7.5L20 12l-4.5 4.5M13.5 5l-3 14"/>',
		'chat'    => '<path d="M20.5 11.5a8 8 0 0 1-11.7 7.1L4 19.8l1.2-4.4a8 8 0 1 1 15.3-3.9z"/>',
		'more'    => '<circle cx="12" cy="12" r="8.75"/><path d="M8.2 12h.01M12 12h.01M15.8 12h.01" stroke-width="2.6"/>',
	];
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * The question, its answers and the optional note box, for the last panel.
 * The heading and the sentence above them are the panel's own.
 *
 * @param string $title_id Id of the panel heading that names the group.
 * @return string Escaped HTML.
 */
function brikpanel_welcome_source_markup( $title_id ) {
	$check = '<svg viewBox="0 0 20 20" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 10l3 3 7-7"/></svg>';
	$lock  = '<svg viewBox="0 0 20 20" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4.5" y="9" width="11" height="8" rx="2"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9"/></svg>';
	// The hint's arrow and the tick that replaces it, drawn like the tour's
	// sketches (the delays are in brikpanel-welcome.css: no inline style).
	$arrow = '<svg class="brikpanel-welcome-source-ink" viewBox="0 0 40 30" width="40" height="30" aria-hidden="true" focusable="false" data-bw-source-arrow><path pathLength="1" d="M35 4 C 30 18, 20 24, 7 23"/><path pathLength="1" d="M13 17.5 L 6.5 23 L 13.5 27.5"/></svg>';
	$tick  = '<svg class="brikpanel-welcome-source-ink brikpanel-welcome-source-ink--tick" viewBox="0 0 40 30" width="40" height="30" aria-hidden="true" focusable="false" data-bw-source-tick hidden><path pathLength="1" d="M9 15.5 L 16.5 23 L 33 6"/></svg>';

	$html  = '<div class="brikpanel-welcome-source" role="group" aria-labelledby="' . esc_attr( $title_id ) . '" data-bw-source>';
	$html .= '<p class="brikpanel-welcome-source-hint" aria-hidden="true">'
		. '<span class="brikpanel-welcome-source-hint-text" dir="auto" data-bw-source-hint>' . esc_html_x( 'just one tap', 'welcome tour handwritten note', 'brikpanel' ) . '</span>'
		. '<span class="brikpanel-welcome-source-hint-text" dir="auto" data-bw-source-thanks hidden>' . esc_html_x( 'thank you!', 'welcome tour handwritten note', 'brikpanel' ) . '</span>'
		. $arrow . $tick
		. '</p>';

	$html .= '<div class="brikpanel-welcome-source-grid">';
	foreach ( brikpanel_welcome_source_answers() as $key => $answer ) {
		$html .= '<button type="button" class="brikpanel-welcome-source-opt" aria-pressed="false" data-bw-answer="' . esc_attr( $key ) . '"'
			. ( '' !== $answer['prompt'] ? ' data-bw-prompt="' . esc_attr( $answer['prompt'] ) . '"' : '' ) . '>'
			// A picked answer's icon turns into a tick, so the tick takes no width.
			. '<span class="brikpanel-welcome-source-icon">' . brikpanel_welcome_source_icon( $answer['icon'] )
			. '<span class="brikpanel-welcome-source-check">' . $check . '</span></span>'
			. '<span class="brikpanel-welcome-source-label">' . esc_html( $answer['label'] ) . '</span>'
			. '</button>';
	}
	$html .= '</div>';

	$html .= '<div class="brikpanel-welcome-source-more" data-bw-source-more hidden>'
		. '<input type="text" class="brikpanel-control brikpanel-welcome-source-input" maxlength="' . (int) BRIKPANEL_WELCOME_SOURCE_NOTE_MAX . '" autocomplete="off" dir="auto" aria-label="' . esc_attr__( 'Your note', 'brikpanel' ) . '" data-bw-source-input>'
		. '<button type="button" class="brikpanel-btn brikpanel-btn--secondary brikpanel-welcome-source-send" data-bw-source-send>' . esc_html_x( 'Send', 'welcome tour note button', 'brikpanel' ) . '</button>'
		. '</div>';

	$html .= '<p class="brikpanel-welcome-source-foot">' . $lock
		. '<span>' . esc_html__( 'Sent anonymously. No email, no site address.', 'brikpanel' ) . '</span>'
		. '</p>';
	$html .= '<p class="brikpanel-welcome-sr" aria-live="polite" data-bw-source-live></p>';
	$html .= '</div>';
	return $html;
}

/**
 * What brikpanel_welcome_source_markup() may print: its own tags and
 * attributes, nothing else. Attribute names are lowercase because wp_kses
 * lowercases them; the HTML parser gives viewBox and pathLength their case
 * back inside an svg.
 *
 * @return array
 */
function brikpanel_welcome_source_kses() {
	$common = [
		'class'           => true,
		'id'              => true,
		'role'            => true,
		'hidden'          => true,
		'dir'             => true,
		'aria-hidden'     => true,
		'aria-label'      => true,
		'aria-labelledby' => true,
		'aria-live'       => true,
		'aria-pressed'    => true,
		'data-*'          => true,
	];
	$shape  = [
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
		'stroke-width' => true,
	];
	return [
		'div'    => $common,
		'p'      => $common,
		'span'   => $common,
		'button' => $common + [ 'type' => true ],
		'input'  => $common + [
			'type'         => true,
			'maxlength'    => true,
			'autocomplete' => true,
		],
		'svg'    => $common + [
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
		'path'   => $shape,
		'rect'   => $shape,
		'circle' => $shape,
	];
}

/* ── AJAX: the tap ───────────────────────────────────────────────────────────── */

/**
 * Receives one tap (or a note) from the tour and passes it on to brksoft.com.
 * The browser sends it with sendBeacon and reads no answer.
 */
function brikpanel_welcome_source_ajax() {
	check_ajax_referer( 'brikpanel_welcome_nonce' );
	// The same people the tour is shown to (brikpanel_should_show_welcome()).
	$allowed = function_exists( 'brikpanel_user_can_open_settings' ) ? brikpanel_user_can_open_settings() : current_user_can( 'manage_woocommerce' );
	if ( ! $allowed || ( function_exists( 'brikpanel_access_is_disabled_for_user' ) && brikpanel_access_is_disabled_for_user() ) ) {
		wp_send_json_error( null, 403 );
	}

	$answers = brikpanel_welcome_source_answers();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
	$answer = isset( $_POST['answer'] ) ? sanitize_key( wp_unslash( $_POST['answer'] ) ) : '';
	$token  = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
	$note   = isset( $_POST['detail'] ) ? sanitize_text_field( wp_unslash( $_POST['detail'] ) ) : '';
	// The tour numbers its requests: they can arrive out of order, and the
	// endpoint keeps only the newest one.
	$seq = isset( $_POST['seq'] ) ? min( 1000, absint( $_POST['seq'] ) ) : 0;
	// phpcs:enable
	if ( ! isset( $answers[ $answer ] ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		wp_send_json_error( null, 400 );
	}
	// Only the answers that ask for a note carry one.
	$note = '' === $answers[ $answer ]['prompt'] ? '' : ( function_exists( 'brikpanel_substr' ) ? brikpanel_substr( $note, 0, BRIKPANEL_WELCOME_SOURCE_NOTE_MAX ) : substr( $note, 0, BRIKPANEL_WELCOME_SOURCE_NOTE_MAX ) );

	// The tour may already be closing; the answer should still leave.
	ignore_user_abort( true );

	$sent = brikpanel_welcome_source_send(
		[
			'answer' => $answer,
			'detail' => $note,
			'token'  => $token,
			'seq'    => $seq,
			'bp'     => BRIKPANEL_VERSION,
			'locale' => get_user_locale(),
		]
	);
	if ( $sent && ! get_option( 'brikpanel_welcome_source_sent' ) ) { // prime-ignore: written once, on the first answer.
		add_option( 'brikpanel_welcome_source_sent', time(), '', false );
	}
	wp_send_json_success( [ 'sent' => $sent ] );
}
add_action( 'wp_ajax_brikpanel_welcome_source', 'brikpanel_welcome_source_ajax' );

/**
 * Posts one answer to the endpoint.
 *
 * @param array $payload Answer and context, already sanitized.
 * @return bool Whether the endpoint stored it.
 */
function brikpanel_welcome_source_send( array $payload ) {
	$endpoint = brikpanel_welcome_source_endpoint();
	if ( '' === $endpoint ) {
		return false;
	}
	$response = wp_remote_post(
		$endpoint,
		[
			'timeout'             => 4,
			'redirection'         => 0,
			'blocking'            => true,
			'sslverify'           => true,
			// WordPress's default user agent ends with the site address.
			'user-agent'          => 'BrikPanel/' . BRIKPANEL_VERSION,
			'headers'             => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			'body'                => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'limit_response_size' => 4096,
		]
	);
	return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
}
