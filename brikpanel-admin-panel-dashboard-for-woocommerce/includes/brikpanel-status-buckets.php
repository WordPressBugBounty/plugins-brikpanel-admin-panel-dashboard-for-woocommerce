<?php
/**
 * Shipping statuses that other plugins add, counted as sales on their own.
 *
 * Every BrikPanel analytic counts the orders whose status is in the "Statuses
 * counted as valid sales" list (brikpanel_paid_order_statuses(), default
 * Processing and Completed). A shipment plugin moves a paid order on to its own
 * status ("Shipped", "Kargoya verildi", "Delivered"), and from that moment the
 * order dropped out of Revenue, Orders and Profit: a WhatsApp customer saw 2
 * orders on the dashboard against 9 in WooCommerce Analytics (November 2026).
 *
 * This file adds such a status to the list by itself, once, when all three of
 * these hold:
 *   1. its name or slug says shipped, delivered, in transit or ready for
 *      pickup, in one of BrikPanel's languages;
 *   2. its name says nothing negative: not delivered, returned, cancelled,
 *      refunded, failed, on hold, "belli değil" and the like;
 *   3. at least 9 in 10 of its latest orders were sales for WooCommerce: their
 *      sale is recorded (WooCommerce adds it to the products' sales when the
 *      order reaches Processing, Completed or On hold, and takes it back when
 *      the order is cancelled), or they carry a payment date.
 * Everything else stays as it was: not counted until the merchant adds it.
 *
 * Why not the payment date alone: on a cash on delivery order WooCommerce
 * writes it only at Completed (WC_Gateway_COD::change_payment_complete_order_status()
 * makes Completed the "payment complete" status), so every cash on delivery
 * order in "Kargoya verildi" would have been missed; 64 of the 302 Processing
 * orders on the development store had no payment date, 301 had their sale
 * recorded. Why the name has to agree as well: the sale is recorded as soon as
 * a cash on delivery order reaches Processing, before any money has changed
 * hands, so every order in "Teslim edilemedi" (could not be delivered) on such
 * a store has it too. And a plugin's own word is not enough either: "Custom
 * Order Status for WooCommerce" (10,000+ installs) reports every status it
 * creates as paid through woocommerce_order_is_paid_statuses, whatever the
 * status means.
 *
 * What it never does:
 *   - touch a list the merchant changed: as soon as the list holds anything
 *     other than Processing, Completed and the statuses added here, nothing is
 *     added again;
 *   - add back a status the merchant removed (kept in the declined list);
 *   - add a WooCommerce status (On hold included), a refund status, BrikPanel's
 *     own Return Draft, or a status excluded in WooCommerce Analytics.
 *
 * Cost: on an admin request the check compares a fingerprint of the status
 * lists with the last one (no query) and runs at most once an hour. A run reads
 * at most the latest 500 orders of each status whose name passed, through the
 * status index. Writes happen only when something changes, and go through
 * update_option(), so brikpanel_on_status_buckets_changed() empties the data
 * caches and recomputes lifetime values as for a change made by hand.
 *
 * @package BrikPanel
 * @since   3.3.58
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Statuses BrikPanel added to the valid-sales list by itself: status => time added. */
const BRIKPANEL_STATUS_AUTO_OPTION = 'brikpanel_status_buckets_auto';

/** Statuses the merchant decided are not sales: never added again. */
const BRIKPANEL_STATUS_DECLINED_OPTION = 'brikpanel_status_buckets_declined';

/**
 * The last check: fingerprint of the status lists and its time. An autoloaded
 * option rather than a transient: without a persistent object cache a
 * transient costs two queries on every admin request, this costs none.
 */
const BRIKPANEL_STATUS_CHECK_OPTION = 'brikpanel_status_buckets_check';

/** How long a check stays valid while nothing in the lists changes. */
const BRIKPANEL_STATUS_CHECK_TTL = HOUR_IN_SECONDS;

/** Share of its latest orders that must be sales before a status is counted on its own. */
const BRIKPANEL_STATUS_SOLD_SHARE = 0.9;

/** How many of a status' latest orders the check reads. */
const BRIKPANEL_STATUS_SAMPLE = 500;

/**
 * Statuses this file never adds: WooCommerce's own, the refund and draft
 * states, and BrikPanel's Return Draft.
 *
 * @return string[] With the wc- prefix.
 */
function brikpanel_status_buckets_never_auto() {
	return array(
		'wc-pending',
		'wc-processing',
		'wc-on-hold',
		'wc-completed',
		'wc-cancelled',
		'wc-refunded',
		'wc-failed',
		'wc-checkout-draft',
		'wc-trash',
		'wc-auto-draft',
		'wc-draft',
		'wc-return-draft',
	);
}

/**
 * Statuses BrikPanel added by itself.
 *
 * @return array<string,int> Status (wc- prefix) => Unix time it was added.
 */
function brikpanel_status_buckets_auto() {
	$raw = get_option( BRIKPANEL_STATUS_AUTO_OPTION, array() );
	$out = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $status => $time ) {
			if ( is_string( $status ) && 0 === strpos( $status, 'wc-' ) ) {
				$out[ $status ] = (int) $time;
			}
		}
	}
	return $out;
}

/**
 * Statuses the merchant decided are not sales.
 *
 * @return string[] With the wc- prefix.
 */
function brikpanel_status_buckets_declined() {
	$raw = get_option( BRIKPANEL_STATUS_DECLINED_OPTION, array() );
	return is_array( $raw ) ? brikpanel_normalise_statuses( $raw, array() ) : array();
}

/**
 * The statuses WooCommerce Analytics leaves out of its totals, the merchant's
 * own choice in WooCommerce > Analytics > Settings included.
 *
 * @return string[] With the wc- prefix.
 */
function brikpanel_wc_analytics_excluded_statuses() {
	$raw = get_option( 'woocommerce_excluded_report_order_statuses', array( 'pending', 'failed', 'cancelled' ) );
	$raw = is_array( $raw ) ? $raw : array();
	/** This filter is documented in woocommerce/src/Admin/API/Reports/DataStore.php */
	$raw = apply_filters( 'woocommerce_analytics_excluded_order_statuses', array_merge( array( 'auto-draft', 'trash' ), $raw ) );
	return is_array( $raw ) ? brikpanel_normalise_statuses( $raw, array() ) : array();
}

/**
 * A status name reduced to plain lower-case Latin letters where it has them:
 * accents and Turkish letters folded (ı to i, ş to s), dashes and underscores
 * turned into spaces. Other scripts stay as they are; the patterns match them
 * case-insensitively. No mbstring call: the extension is optional.
 *
 * @param string $text Slug or label.
 * @return string
 */
function brikpanel_status_fold_text( $text ) {
	$text = remove_accents( wp_strip_all_tags( (string) $text ) );
	$text = preg_replace( '/[\s\-_.\/]+/u', ' ', $text );
	return is_string( $text ) ? trim( $text ) : '';
}

/**
 * Whether a status name means the order has shipped, is on its way, was
 * delivered or waits to be picked up, and says nothing negative.
 *
 * @param string $status Status with or without the wc- prefix.
 * @param string $label  Its name.
 * @return bool
 */
function brikpanel_status_name_means_shipped( $status, $label ) {
	$slug = preg_replace( '/^wc-/', '', (string) $status );
	$text = ' ' . brikpanel_status_fold_text( $slug ) . ' ' . brikpanel_status_fold_text( $label ) . ' ';

	$shipped = array(
		// English.
		'\bship', '\bdeliver', '\bdispatch', '\bfulfil', '\bcourier', '\btransit', '\bpick ?up', '\bpicked up',
		'\bready (?:for|to) (?:collect|collection|pick)', '\bpacked\b', '\bpacking\b', '\btracking\b',
		// Turkish.
		'\bkargo', '\bteslim', '\byolda\b', '\bdagitim', '\bpaket', '\bsevk', '\bgonderil', '\bgonderim', '\bkurye',
		// German.
		'versand', 'versendet', 'verschickt', 'zugestellt', 'geliefert', 'zustellung', 'unterwegs', 'abholbereit', 'abholung', 'verpackt',
		// French.
		'\bexpedi', '\blivr', '\bemball', '\bpret a retirer',
		// Spanish and Portuguese.
		'\benviad', '\bentregad', '\bentregue', '\ben camino', '\bdespachad', '\bpara recoger', '\bretiro\b', '\bretirada', '\bempaquetad', '\bempacad', '\bembalad',
		// Italian.
		'\bspedit', '\bspedizion', '\bconsegn', '\britiro\b', '\bimballat',
		// Dutch.
		'\bverzonden', '\bgeleverd', '\bbezorgd', '\bonderweg', '\bafhalen', '\bverpakt',
		// Polish.
		'\bwyslan', '\bdostarcz', '\bdoreczon', '\bw drodze', '\bdo odbioru', '\bspakowan', '\bnadan',
		// Russian.
		'отправ', 'достав', 'в пути', 'упакован', 'выдач',
		// Arabic.
		'شحن', 'توصيل', 'تسليم', 'في الطريق',
	);

	$negative = array(
		// A negation anywhere in the name.
		'\b(?:not|no|non|nao|nie|niet|geen|nicht|kein\w*|pas|degil|yok)\b', '\bun(?:deliver|paid|ship|fulfil|success|claim)',
		'\bnie\w+', '(?:^|\s)не', '(?:^|\s)(?:لم|غير)\s',
		// Turkish negative verb forms: teslim edilemedi, alınmadı, gelmemiş, alınmayan; -sız / -siz.
		'\w*m[ae]di\b', '\w*m[ae]mis\b', '\w*m[ae]y[ae]n\b', '\w+s[iu]z\b',
		// English.
		'\breturn', '\brefund', '\bcancel', '\bfail', '\breject', '\bdeclin', '\blost\b', '\bdamag', '\bproblem', '\bissue',
		'\bpending', '\bhold', '\bdisput', '\bchargeback', '\bvoid\b', '\bexception', '\bquote', '\bdraft', '\bfraud', '\bspam\b', '\btest\b',
		// Turkish.
		'\biade', '\biptal', '\bgeri\b', '\bdondu', '\bdonus', '\bhata', '\bsorun', '\bkayip', '\bkaybol', '\bhasar', '\bredd', '\bred\b', '\bret\b',
		'\bbeklemede', '\bodeme bekl', '\bteklif', '\btaslak', '\bdolandir',
		// German.
		'retour', 'ruckgabe', 'rueckgabe', 'rucksend', 'ruecksend', 'erstatt', 'storn', 'fehlgeschlagen', 'fehler', 'abgelehnt',
		'unzustellbar', 'zuruck', 'zurueck', 'verloren', 'beschadig', 'beschaedig',
		// French.
		'\brembours', '\bannul', '\bechou', '\bechec', '\brefus', '\bperdu', '\bendommag',
		// Spanish and Portuguese.
		'\bdevuel', '\bdevoluc', '\bdevolvid', '\breembols', '\bcancelad', '\banulad', '\bfallid', '\bfalh', '\brechazad', '\brecusad',
		'\bperdid', '\bextraviad', '\bdanad', '\bdanificad',
		// Italian.
		'\breso\b', '\brestituit', '\brimbors', '\bannullat', '\bfallit', '\brifiutat', '\bsmarrit', '\bdanneggiat', '\bmancat',
		// Dutch.
		'\bteruggestuurd', '\bterugbetaald', '\bgeannuleerd', '\bmislukt', '\bgeweigerd', '\bbeschadigd', '\bprobleem',
		// Polish.
		'\bzwrot', '\bzwroc', '\banulow', '\bodrzuc', '\bzgubion', '\buszkodz',
		// Russian.
		'возврат', 'возвращ', 'отмен', 'неудач', 'отказ', 'потер', 'поврежд', 'проблем',
		// Arabic.
		'إلغاء', 'الغاء', 'ملغ', 'مرتجع', 'استرجاع', 'ارجاع', 'إرجاع', 'استرداد', 'فشل', 'مرفوض', 'رفض',
	);

	if ( ! preg_match( '/(?:' . implode( '|', $shipped ) . ')/iu', $text ) ) {
		return false;
	}
	return ! preg_match( '/(?:' . implode( '|', $negative ) . ')/iu', $text );
}

/**
 * How many of a status' latest orders were sales for WooCommerce: their sale
 * is recorded (set when the order reaches Processing, Completed or On hold,
 * cleared when it is cancelled, the same for every payment method) or they
 * carry a payment date (a gateway that completes the payment without passing
 * through those statuses).
 *
 * Reads the latest BRIKPANEL_STATUS_SAMPLE orders straight off the type, status
 * and date index (both order stores have one), newest first, so the database
 * reads 500 index entries and sorts nothing: the cost stays the same on a store
 * with a million orders. Sorting by id instead made it sort every order of the
 * status (EXPLAIN: "Using filesort").
 *
 * @param string $status Status with the wc- prefix.
 * @return array{total:int,sold:int}
 */
function brikpanel_status_sold_share( $status ) {
	global $wpdb;
	$limit = (int) BRIKPANEL_STATUS_SAMPLE;

	if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ) {
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN od.recorded_sales = 1 OR od.date_paid_gmt IS NOT NULL THEN 1 ELSE 0 END), 0) AS sold
			FROM ( SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status = %s ORDER BY date_created_gmt DESC LIMIT %d ) recent
			LEFT JOIN {$wpdb->prefix}wc_order_operational_data od ON od.order_id = recent.id",
			$status,
			$limit
		) );
	} else {
		// _recorded_sales is 'yes' while the sale counts; _date_paid since
		// WooCommerce 3.0, _paid_date before it.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = recent.ID AND (
					( pm.meta_key = '_recorded_sales' AND pm.meta_value = 'yes' )
					OR ( pm.meta_key IN ('_date_paid', '_paid_date') AND pm.meta_value NOT IN ('', '0') )
				)
			) THEN 1 ELSE 0 END), 0) AS sold
			FROM ( SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = %s ORDER BY post_date DESC LIMIT %d ) recent",
			$status,
			$limit
		) );
	}

	return array(
		'total' => $row ? (int) $row->total : 0,
		'sold'  => $row ? (int) $row->sold : 0,
	);
}

/**
 * Whether the merchant has left the valid-sales list alone: no row at all, or
 * only Processing, Completed and the statuses added here (some of those may
 * have been removed again). Anything else is a list somebody chose.
 *
 * @param mixed    $stored_raw The option row as stored, null when there is none.
 * @param string[] $paid       The list as read (normalised).
 * @param array    $auto       brikpanel_status_buckets_auto().
 * @return bool
 */
function brikpanel_status_buckets_list_untouched( $stored_raw, array $paid, array $auto ) {
	if ( null === $stored_raw ) {
		return true;
	}
	$default = brikpanel_default_paid_statuses();
	if ( array_diff( $default, $paid ) ) {
		return false;
	}
	return ! array_diff( $paid, $default, array_keys( $auto ) );
}

/**
 * Bring the valid-sales list up to date with the shipping statuses on this
 * store, and remember the statuses the merchant took out of it.
 *
 * @param bool $force   Skip the hourly fingerprint check (tests).
 * @param bool $dry_run Write nothing, only say what would be added (support:
 *                      wp eval 'print_r( brikpanel_reconcile_status_buckets( true, true ) );').
 * @return string[] Statuses added in this run, or that would be.
 */
function brikpanel_reconcile_status_buckets( $force = false, $dry_run = false ) {
	if ( ! function_exists( 'wc_get_order_statuses' ) || ! function_exists( 'brikpanel_paid_order_statuses' ) ) {
		return array();
	}
	$registered = wc_get_order_statuses();
	if ( ! is_array( $registered ) || ! $registered ) {
		return array();
	}

	$default    = brikpanel_default_paid_statuses();
	$stored_raw = get_option( BRIKPANEL_PAID_STATUSES_OPTION, null );
	$paid       = ( null === $stored_raw ) ? $default : brikpanel_normalise_statuses( $stored_raw, $default );
	$auto       = brikpanel_status_buckets_auto();
	$declined   = brikpanel_status_buckets_declined();
	$refunds    = brikpanel_refunded_order_statuses();

	// Every option read up to here is primed (includes/brikpanel-option-prime.php),
	// so an admin request where nothing changed costs no query. WooCommerce's
	// Analytics exclusions are read only once a check really runs: on most
	// stores that option has no row, and asking for it on every admin request
	// would be one query each time. A change there is picked up within the hour.
	$fingerprint = static function () use ( &$registered, &$paid, &$auto, &$declined, $refunds ) {
		return md5( (string) wp_json_encode( array( array_keys( $registered ), $paid, array_keys( $auto ), $declined, $refunds ) ) );
	};
	$last = get_option( BRIKPANEL_STATUS_CHECK_OPTION, array() );
	if ( ! $force && is_array( $last ) && isset( $last['fp'], $last['at'] )
		&& $last['fp'] === $fingerprint() && ( time() - (int) $last['at'] ) < BRIKPANEL_STATUS_CHECK_TTL ) {
		return array();
	}
	// Stored first, so a request arriving meanwhile does not run the same check.
	if ( ! $dry_run ) {
		update_option( BRIKPANEL_STATUS_CHECK_OPTION, array( 'fp' => $fingerprint(), 'at' => time() ), true );
	}

	// A status added here that is no longer in the list was taken out by the
	// merchant: never add it again. A status no plugin registers any more was
	// not on the settings form, so saving the form dropped it without a choice;
	// it may come back with its plugin.
	$bookkeeping = false;
	foreach ( $auto as $status => $added_at ) {
		if ( in_array( $status, $paid, true ) ) {
			continue;
		}
		unset( $auto[ $status ] );
		if ( isset( $registered[ $status ] ) && ! in_array( $status, $declined, true ) ) {
			$declined[] = $status;
		}
		$bookkeeping = true;
	}

	$added = array();
	if ( brikpanel_status_buckets_list_untouched( $stored_raw, $paid, $auto ) ) {
		$never    = brikpanel_status_buckets_never_auto();
		$excluded = brikpanel_wc_analytics_excluded_statuses();
		foreach ( $registered as $status => $label ) {
			$status = (string) $status;
			if ( 0 !== strpos( $status, 'wc-' )
				|| in_array( $status, $paid, true )
				|| isset( $auto[ $status ] )
				|| in_array( $status, $declined, true )
				|| in_array( $status, $never, true )
				|| in_array( $status, $refunds, true )
				|| in_array( $status, $excluded, true )
				|| ! brikpanel_status_name_means_shipped( $status, (string) $label ) ) {
				continue;
			}
			$share = brikpanel_status_sold_share( $status );
			if ( $share['total'] > 0 && $share['sold'] >= BRIKPANEL_STATUS_SOLD_SHARE * $share['total'] ) {
				$added[] = $status;
			}
		}
	}

	if ( $dry_run || ( ! $added && ! $bookkeeping ) ) {
		return $added;
	}

	$now = time();
	foreach ( $added as $status ) {
		$auto[ $status ] = $now;
	}
	update_option( BRIKPANEL_STATUS_AUTO_OPTION, $auto, true );
	update_option( BRIKPANEL_STATUS_DECLINED_OPTION, array_values( $declined ), true );
	if ( $added ) {
		$paid = array_values( array_unique( array_merge( $paid, $added ) ) );
		// Runs brikpanel_on_status_buckets_changed(): caches emptied, lifetime
		// values recomputed, exactly as for a change made on the settings page.
		update_option( BRIKPANEL_PAID_STATUSES_OPTION, $paid );
	}
	update_option( BRIKPANEL_STATUS_CHECK_OPTION, array( 'fp' => $fingerprint(), 'at' => time() ), true );

	return $added;
}

/**
 * Run the check on admin requests of the people who read the figures. Not on
 * the network admin, not while BrikPanel is switched off.
 */
function brikpanel_reconcile_status_buckets_on_admin() {
	if ( is_network_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( function_exists( 'brikpanel_master_enabled' ) && ! brikpanel_master_enabled() ) {
		return;
	}
	brikpanel_reconcile_status_buckets();
}
add_action( 'admin_init', 'brikpanel_reconcile_status_buckets_on_admin', 20 );

/* ── The dashboard asks about the rest ────────────────────────────────────── */

/**
 * Statuses the dashboard may ask about ("7 orders in these dates are not
 * counted as sales ... Count them as sales?"): registered, not counted yet,
 * not a refund status, not decided before, not left out of WooCommerce
 * Analytics, and never Pending, Failed, Cancelled, a draft or BrikPanel's own
 * Return Draft. Processing and Completed are never asked about: a list
 * without them is a choice. On hold is asked about only while the merchant
 * has not changed the list; one who did chose among WooCommerce's own
 * statuses already (owner, 26 November 2026: the cargo statuses count by
 * themselves, the dashboard asks about the rest).
 *
 * @return array<string,string> Status (wc- prefix) => its name.
 */
function brikpanel_status_buckets_askable() {
	if ( ! function_exists( 'wc_get_order_statuses' ) || ! function_exists( 'brikpanel_paid_order_statuses' ) ) {
		return array();
	}
	$registered = wc_get_order_statuses();
	if ( ! is_array( $registered ) || ! $registered ) {
		return array();
	}
	$default    = brikpanel_default_paid_statuses();
	$stored_raw = get_option( BRIKPANEL_PAID_STATUSES_OPTION, null );
	$stored     = ( null === $stored_raw ) ? $default : brikpanel_normalise_statuses( $stored_raw, $default );

	$skip = array_merge(
		brikpanel_paid_order_statuses(),
		brikpanel_refunded_order_statuses(),
		brikpanel_status_buckets_declined(),
		brikpanel_wc_analytics_excluded_statuses(),
		array( 'wc-pending', 'wc-processing', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-checkout-draft', 'wc-trash', 'wc-auto-draft', 'wc-draft', 'wc-return-draft' )
	);
	if ( ! brikpanel_status_buckets_list_untouched( $stored_raw, $stored, brikpanel_status_buckets_auto() ) ) {
		$skip[] = 'wc-on-hold';
	}

	$out = array();
	foreach ( $registered as $status => $label ) {
		$status = (string) $status;
		if ( 0 === strpos( $status, 'wc-' ) && ! in_array( $status, $skip, true ) ) {
			$out[ $status ] = wp_strip_all_tags( (string) $label );
		}
	}
	return $out;
}

/**
 * Orders of the period in a status the dashboard may ask about, the status
 * with the most orders first. Counted on the Orders card's basis (orders
 * placed by store administrators left out, marketplace orders too on a
 * BrikMarket store), so "7 orders are not counted" sits next to the Orders
 * figure without the two disagreeing. One query over the type, status and
 * date index.
 *
 * @param string $start_gmt           Y-m-d H:i:s (UTC).
 * @param string $end_gmt             Y-m-d H:i:s (UTC).
 * @param bool   $exclude_marketplace The Orders card's basis on a BrikMarket store.
 * @return array<int,array{status:string,label:string,count:int}>
 */
function brikpanel_status_buckets_uncounted( $start_gmt, $end_gmt, $exclude_marketplace = false ) {
	$askable = brikpanel_status_buckets_askable();
	if ( ! $askable ) {
		return array();
	}
	global $wpdb;
	$statuses = array_keys( $askable );
	$place    = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$hpos     = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' );

	if ( $hpos ) {
		$sql   = "SELECT status AS st, COUNT(*) AS n FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status IN ({$place}) AND date_created_gmt >= %s AND date_created_gmt <= %s";
		$group = ' GROUP BY status';
	} else {
		$sql   = "SELECT post_status AS st, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ({$place}) AND post_date_gmt >= %s AND post_date_gmt <= %s";
		$group = ' GROUP BY post_status';
	}
	$args = array_merge( $statuses, array( (string) $start_gmt, (string) $end_gmt ) );

	if ( function_exists( 'brikpanel_admin_order_exclusion_sql' ) ) {
		$exclusion = brikpanel_admin_order_exclusion_sql( $hpos, 'ID' );
		$sql      .= $exclusion['sql'];
		$args      = array_merge( $args, $exclusion['args'] );
	}
	if ( $exclude_marketplace && function_exists( 'brikpanel_marketplace_order_exclusion_sql' ) ) {
		$mp_exclusion = brikpanel_marketplace_order_exclusion_sql( $hpos );
		$sql         .= $mp_exclusion['sql'];
		$args         = array_merge( $args, $mp_exclusion['args'] );
	}

	$rows = $wpdb->get_results( $wpdb->prepare( $sql . $group, $args ) );
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$status = (string) $row->st;
		$count  = (int) $row->n;
		if ( $count > 0 && isset( $askable[ $status ] ) ) {
			$out[] = array( 'status' => $status, 'label' => $askable[ $status ], 'count' => $count );
		}
	}
	usort( $out, static function ( $a, $b ) {
		return ( $b['count'] <=> $a['count'] ) ?: strcmp( $a['label'], $b['label'] );
	} );
	return $out;
}

/**
 * Whether this page view may put the question up. The question keeps the
 * one-ask rules (includes/brikpanel-asks.php, rules 1, 2, 6 and 7) and stands
 * outside the line like the phone panel (rule 8): it waits for nobody's turn
 * and takes none, and shows only on a page with no welcome tour, no ask, no
 * notice box or "saved" message, and no phone panel opening by itself
 * (owner, 26 November 2026: "diğer çıkan popup larla çakışmasın"). Decided in
 * the footer, once every ask of the page has printed.
 *
 * @return bool
 */
function brikpanel_status_buckets_may_ask() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return false;
	}
	if ( function_exists( 'brikpanel_asks_in_scope' ) ) {
		if ( ! brikpanel_asks_in_scope() || brikpanel_asks_hold() || brikpanel_asks_url_hold() || brikpanel_asks_screen_taken() || brikpanel_asks_on_page() ) {
			return false;
		}
	} elseif ( wp_doing_ajax() || ! is_admin() ) {
		return false;
	}
	return ! ( function_exists( 'brikpanel_should_show_welcome' ) && brikpanel_should_show_welcome() );
}

/**
 * The dashboard question's two buttons. "Yes, count" adds the status to the
 * valid-sales list as if BrikPanel had added it (a list that only gained
 * statuses this way still counts as unchanged, so cargo statuses keep being
 * added), "No" never asks about it again. Both empty the data caches, so the
 * figures the dashboard fetches next are the new ones.
 *
 * @return void
 */
function brikpanel_status_buckets_ajax_decide() {
	if ( ! check_ajax_referer( 'brikpanel_dashboard_nonce', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
	}
	$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
	$askable  = brikpanel_status_buckets_askable();
	if ( ! isset( $askable[ $status ] ) || ! in_array( $decision, array( 'count', 'skip' ), true ) ) {
		wp_send_json_error( array( 'message' => 'This status cannot be decided here.' ), 400 );
	}

	if ( 'count' === $decision ) {
		$auto            = brikpanel_status_buckets_auto();
		$auto[ $status ] = time();
		update_option( BRIKPANEL_STATUS_AUTO_OPTION, $auto, true );
		$default    = brikpanel_default_paid_statuses();
		$stored_raw = get_option( BRIKPANEL_PAID_STATUSES_OPTION, null );
		$paid       = ( null === $stored_raw ) ? $default : brikpanel_normalise_statuses( $stored_raw, $default );
		$paid[]     = $status;
		// Runs brikpanel_on_status_buckets_changed(): caches emptied, lifetime
		// values recomputed, exactly as for a change made on the settings page.
		update_option( BRIKPANEL_PAID_STATUSES_OPTION, array_values( array_unique( $paid ) ) );
	} else {
		$declined   = brikpanel_status_buckets_declined();
		$declined[] = $status;
		update_option( BRIKPANEL_STATUS_DECLINED_OPTION, array_values( array_unique( $declined ) ), true );
		// The question is in the cached dashboard figures; without a new data
		// version it would come back until the copy expires.
		if ( function_exists( 'brikpanel_bust_data_caches' ) ) {
			brikpanel_bust_data_caches();
		}
	}
	wp_send_json_success( array( 'decision' => $decision, 'status' => $status ) );
}
add_action( 'wp_ajax_brikpanel_status_bucket_decide', 'brikpanel_status_buckets_ajax_decide' );
