<?php
/**
 * BrikPanel - AJAX Coupons Management
 *
 * Replaces the default WooCommerce coupons list with a modern,
 * fully AJAX-powered interface with drawer editing.
 *
 * @package BrikPanel
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Brikpanel_Coupons {

    /**
     * "Auto-show fields other plugins add" (WooCommerce > Settings > BrikPanel
     * > Coupons): everything other plugins and the theme add to WooCommerce's
     * coupon screen shows open in the coupon window, their discount types too.
     */
    const AUTO_OPTION = 'brikpanel_coupons_plugin_fields_auto';

    /** "Additional coupon data sections": owner keys shown without the switch above. */
    const SELECTED_OPTION = 'brikpanel_coupons_plugin_fields_selected';

    /** What "Hide these fields" turned off, for "Always show these fields" to put back. */
    const PARKED_OPTION = 'brikpanel_coupons_plugin_fields_parked';

    /** Per person: the folded "Fields from your other plugins" box is kept open. */
    const OPEN_META = 'brikpanel_cp_plugin_fields_open';

    /** WooCommerce's own discount types, always offered. */
    const CORE_TYPES = ['percent', 'fixed_cart', 'fixed_product'];

    /** The field sections of WooCommerce's coupon screen and the hook each prints. */
    const SECTION_HOOKS = [
        'general'           => 'woocommerce_coupon_options',
        'usage_restriction' => 'woocommerce_coupon_options_usage_restriction',
        'usage_limit'       => 'woocommerce_coupon_options_usage_limit',
    ];

    /**
     * Payload keys of the window's own save: a field another plugin prints
     * under one of these names is not sent along, BrikPanel's value wins.
     */
    const OWN_KEYS = [
        'action', 'security', 'coupon_id', 'code', 'description', 'discount_type', 'amount', 'free_shipping',
        'expiry_date', 'minimum_amount', 'maximum_amount', 'individual_use', 'exclude_sale_items', 'usage_limit',
        'usage_limit_per_user', 'product_ids', 'excluded_product_ids', 'category_ids', 'excluded_category_ids',
        'email_restrictions', 'limit_usage_to_x_items', 'auto_apply', 'min_items', 'max_items', 'bp_cp_owners',
    ];

    public function __construct() {
        if (get_option('brikpanel_modern_coupons', 'yes') !== 'yes') {
            return;
        }

        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'redirect_default_list']);

        // AJAX endpoints
        add_action('wp_ajax_brikpanel_fetch_coupons', [$this, 'ajax_fetch_coupons']);
        add_action('wp_ajax_brikpanel_save_coupon', [$this, 'ajax_save_coupon']);
        add_action('wp_ajax_brikpanel_delete_coupon', [$this, 'ajax_delete_coupon']);
        add_action('wp_ajax_brikpanel_toggle_coupon_status', [$this, 'ajax_toggle_coupon_status']);
        add_action('wp_ajax_brikpanel_duplicate_coupon', [$this, 'ajax_duplicate_coupon']);
        add_action('wp_ajax_brikpanel_coupons_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_brikpanel_coupons_search_categories', [$this, 'ajax_search_categories']);
        add_action('wp_ajax_brikpanel_coupons_search_users', [$this, 'ajax_search_users']);

        // What other plugins and the theme add to WooCommerce's coupon screen.
        add_action('wp_ajax_brikpanel_coupon_plugin_fields', [$this, 'ajax_plugin_fields']);
        add_action('wp_ajax_brikpanel_cp_fold_state', [$this, 'ajax_fold_state']);
        add_action('wp_ajax_brikpanel_cp_enable_plugin_fields', [$this, 'ajax_enable_plugin_fields']);
        add_action('wp_ajax_brikpanel_cp_disable_plugin_fields', [$this, 'ajax_disable_plugin_fields']);
    }

    /**
     * Returns which optional usage-restriction fields the store owner has
     * enabled from BrikPanel → Settings → Coupons. Used to render the drawer
     * conditionally and to gate the corresponding save-paths so a disabled
     * field cannot be tampered with via the AJAX endpoint.
     *
     * @return array<string,bool>
     */
    public static function enabled_fields() {
        return [
            'products'           => get_option('brikpanel_coupons_show_products', 'no') === 'yes',
            'exclude_products'   => get_option('brikpanel_coupons_show_exclude_products', 'no') === 'yes',
            'categories'         => get_option('brikpanel_coupons_show_categories', 'no') === 'yes',
            'exclude_categories' => get_option('brikpanel_coupons_show_exclude_categories', 'no') === 'yes',
            'emails'             => get_option('brikpanel_coupons_show_emails', 'no') === 'yes',
            'limit_items'        => get_option('brikpanel_coupons_show_limit_items', 'no') === 'yes',
        ];
    }

    /**
     * Whether another plugin or the theme adds anything to WooCommerce's
     * coupon screen: a discount type, fields, a tab of its own or a box.
     *
     * @return bool
     */
    public static function coupon_plugin_settings() {
        return (bool) self::plugin_additions();
    }

    /**
     * What other plugins and the theme add to WooCommerce's coupon screen, per
     * owner, for the lists: the settings picker, the page's scripts and the
     * window's types. Read on the newest coupon, names and labels only.
     *
     * Most stores have none and finding out fires every coupon hook, so a
     * "none" answer is kept for an hour under a key that changes with the
     * active plugins, the theme and the versions (fingerprint()).
     *
     * @return array<string, array> See collect().
     */
    public static function plugin_additions() {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        // The settings save runs before the admin includes load (WooCommerce
        // does not check a multiselect against its options): answer nothing
        // and remember nothing, the render after it reads again.
        if (!self::admin_ready()) {
            return [];
        }
        $key = 'bpc_tp_' . self::fingerprint();
        if ('no' === get_transient($key)) {
            return $memo = [];
        }
        $memo = self::collect(self::probe_coupon(), false);
        $want = $memo ? 'yes' : 'no';
        if (get_transient($key) !== $want) {
            set_transient($key, $want, HOUR_IN_SECONDS);
        }
        return $memo;
    }

    /**
     * Fires WooCommerce's coupon screen hooks the way that screen does, one
     * callback at a time (includes/brikpanel-foreign-hooks.php), and sorts
     * what each plugin or the theme adds by owner. WordPress', WooCommerce's
     * and BrikPanel's own callbacks are left out (WooCommerce's Brands field
     * included, as before: it is WooCommerce's).
     *
     * The screen is the coupon edit screen while it runs (screen, post,
     * pagenow, thepostid): WooCommerce's field helpers and many plugins read
     * those. Everything is put back in `finally`.
     *
     * @param WC_Coupon $coupon    Coupon to draw for (id 0: a new coupon).
     * @param bool      $with_html Keep the printed markup (the window), or only names and labels.
     * @return array<string, array{name: string, dir: string, types: array<string,string>, sections: array<string,string[]>, tabs: array<string,array{label: string, target: string, class: string}>, panels: string[], boxes: array<string,array{title: string, html: string}>, labels: string[]}>
     */
    public static function collect($coupon, $with_html = false) {
        $owners = [];
        if (!self::admin_ready() || !($coupon instanceof WC_Coupon)) {
            return $owners;
        }

        $coupon_id = (int) $coupon->get_id();
        $post      = $coupon_id ? get_post($coupon_id) : null;
        if (!($post instanceof WP_Post) || $post->post_type !== 'shop_coupon') {
            // WooCommerce's field helpers read $post->ID when no id is set.
            $coupon_id = 0;
            $post      = new WP_Post((object) ['ID' => 0, 'post_type' => 'shop_coupon', 'post_status' => 'auto-draft', 'post_title' => '', 'filter' => 'raw']);
        }

        global $wp_meta_boxes;
        $saved = [];
        foreach (['current_screen', 'post', 'post_type', 'typenow', 'taxnow', 'pagenow', 'thepostid'] as $global) {
            $saved[$global] = isset($GLOBALS[$global]) ? $GLOBALS[$global] : null;
        }
        $had_boxes    = is_array($wp_meta_boxes) && array_key_exists('shop_coupon', $wp_meta_boxes);
        $boxes_before = $had_boxes ? $wp_meta_boxes['shop_coupon'] : null;

        try {
            set_current_screen('shop_coupon');
            if (function_exists('brikpanel_mark_screen_classic')) {
                brikpanel_mark_screen_classic();
            }
            $GLOBALS['post']      = $post;
            $GLOBALS['post_type'] = 'shop_coupon';
            $GLOBALS['typenow']   = 'shop_coupon';
            $GLOBALS['pagenow']   = $coupon_id ? 'post.php' : 'post-new.php';
            $GLOBALS['thepostid'] = $coupon_id;

            // Discount types.
            foreach (self::type_owners() as $key => $row) {
                self::owner_slot($owners, $row['owner']);
                $owners[$key]['types'] = $row['types'];
            }

            // Fields in WooCommerce's three sections.
            foreach (self::SECTION_HOOKS as $section => $hook) {
                foreach (Brikpanel_Foreign_Hooks::chunks($hook, [$coupon_id, $coupon]) as $chunk) {
                    if ($chunk['html'] === '') {
                        continue;
                    }
                    $key = $chunk['owner']['key'];
                    self::owner_slot($owners, $chunk['owner']);
                    $owners[$key]['labels']               = array_merge($owners[$key]['labels'], self::field_labels($chunk['html']));
                    $owners[$key]['sections'][$section][] = $with_html ? $chunk['html'] : '';
                }
            }

            // Tabs of their own, and the panels behind them. The base list is
            // WooCommerce's own three tabs, only used to tell what was added.
            $core_tabs = [
                'general'           => ['label' => 'General', 'target' => 'general_coupon_data', 'class' => 'general_coupon_data'], // i18n-ignore: comparison base, never shown
                'usage_restriction' => ['label' => 'Usage restriction', 'target' => 'usage_restriction_coupon_data', 'class' => ''], // i18n-ignore: comparison base, never shown
                'usage_limit'       => ['label' => 'Usage limits', 'target' => 'usage_limit_coupon_data', 'class' => ''], // i18n-ignore: comparison base, never shown
            ];
            $tabs = Brikpanel_Foreign_Hooks::filter_additions('woocommerce_coupon_data_tabs', $core_tabs);
            foreach ($tabs['added'] as $key => $row) {
                foreach ($row['entries'] as $tab_key => $tab) {
                    if (!is_array($tab)) {
                        continue;
                    }
                    self::owner_slot($owners, $row['owner']);
                    $label = wp_strip_all_tags((string) (isset($tab['label']) ? $tab['label'] : $tab_key));
                    $owners[$key]['tabs'][sanitize_key((string) $tab_key)] = [
                        'label'  => $label,
                        'target' => sanitize_html_class((string) (isset($tab['target']) ? $tab['target'] : '')),
                        'class'  => implode(' ', array_filter(array_map('sanitize_html_class', (array) (isset($tab['class']) ? $tab['class'] : [])))),
                    ];
                    $owners[$key]['labels'][] = $label;
                }
            }
            foreach (Brikpanel_Foreign_Hooks::chunks('woocommerce_coupon_data_panels', [$coupon_id, $coupon]) as $chunk) {
                if ($chunk['html'] === '') {
                    continue;
                }
                $key = $chunk['owner']['key'];
                self::owner_slot($owners, $chunk['owner']);
                $owners[$key]['labels']   = array_merge($owners[$key]['labels'], self::field_labels($chunk['html']));
                $owners[$key]['panels'][] = $with_html ? $chunk['html'] : '';
            }

            // Boxes of their own (WordPress draws them on a coupon that exists).
            if ($coupon_id) {
                $wp_meta_boxes['shop_coupon'] = [];
                try {
                    Brikpanel_Foreign_Hooks::die_guard(static function () use ($post) {
                        do_action('add_meta_boxes', 'shop_coupon', $post);
                        do_action('add_meta_boxes_shop_coupon', $post);
                    });
                } catch (\Throwable $e) {
                    unset($e); // the boxes registered before the failure still count
                }
                foreach ((array) $wp_meta_boxes['shop_coupon'] as $priorities) {
                    foreach ((array) $priorities as $boxes) {
                        foreach ((array) $boxes as $box_id => $box) {
                            if (empty($box) || !is_array($box) || empty($box['callback']) || in_array((string) $box_id, self::CORE_BOXES, true)) {
                                continue;
                            }
                            $owner = Brikpanel_Foreign_Hooks::owner($box['callback']);
                            if ($owner === null) {
                                continue;
                            }
                            $html = $with_html ? self::render_box($box, $post) : '';
                            if ($with_html && $html === '') {
                                continue;
                            }
                            $title = wp_strip_all_tags((string) $box['title']);
                            self::owner_slot($owners, $owner);
                            $owners[$owner['key']]['boxes'][(string) $box_id] = ['title' => $title, 'html' => $html];
                            $owners[$owner['key']]['labels'][]               = $title;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[BrikPanel] Reading what other plugins add to coupons stopped: ' . $e->getMessage());
        } finally {
            if ($had_boxes) {
                $wp_meta_boxes['shop_coupon'] = $boxes_before;
            } elseif (is_array($wp_meta_boxes)) {
                unset($wp_meta_boxes['shop_coupon']);
            }
            foreach ($saved as $global => $value) {
                $GLOBALS[$global] = $value;
            }
        }

        foreach ($owners as $key => $row) {
            $owners[$key]['labels'] = array_values(array_unique(array_filter(array_map('trim', $row['labels']), 'strlen')));
            if (!$row['types'] && !$row['sections'] && !$row['tabs'] && !$row['panels'] && !$row['boxes']) {
                unset($owners[$key]);
            }
        }
        return $owners;
    }

    /**
     * Which plugin added which discount type, from each callback of
     * `woocommerce_coupon_discount_types` on its own. A type no single
     * callback adds by itself (it builds on another plugin's, or its callback
     * throws alone) is still another plugin's: it goes under "Other code".
     *
     * @return array<string, array{owner: array, types: array<string,string>}>
     */
    public static function type_owners() {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $memo = [];
        if (!function_exists('wc_get_coupon_types')) {
            return $memo;
        }
        $all   = wc_get_coupon_types();
        $base  = array_intersect_key($all, array_flip(self::CORE_TYPES));
        $found = Brikpanel_Foreign_Hooks::filter_additions('woocommerce_coupon_discount_types', $base);
        $taken = [];
        foreach ($found['added'] as $key => $row) {
            foreach (array_intersect_key($row['entries'], $all) as $slug => $label) {
                if (!isset($memo[$key])) {
                    $memo[$key] = ['owner' => $row['owner'], 'types' => []];
                }
                $memo[$key]['types'][(string) $slug] = wp_strip_all_tags((string) $all[$slug]);
                $taken[$slug] = true;
            }
        }
        foreach (array_diff_key($all, $base, $taken) as $slug => $label) {
            if (!isset($memo['other:types'])) {
                $memo['other:types'] = ['owner' => ['key' => 'other:types', 'name' => __('Other code', 'brikpanel'), 'dir' => ''], 'types' => []];
            }
            $memo['other:types']['types'][(string) $slug] = wp_strip_all_tags((string) $label);
        }
        return $memo;
    }

    /**
     * Whether the settings show this owner's additions open: the switch is on,
     * or it is picked in "Additional coupon data sections".
     *
     * @param string $key Owner key.
     * @return bool
     */
    public static function owner_shown($key) {
        if (get_option(self::AUTO_OPTION, 'no') === 'yes') {
            return true;
        }
        return in_array((string) $key, array_map('strval', (array) get_option(self::SELECTED_OPTION, [])), true);
    }

    /**
     * The discount types the window offers, value => label: WooCommerce's
     * three always; another plugin's when the settings show that plugin; and
     * the coupon's own type whatever the settings say, so that opening and
     * saving a coupon never changes its type.
     *
     * @param WC_Coupon|null $coupon The coupon being edited.
     * @return array<string,string>
     */
    public static function type_options($coupon = null) {
        $all     = function_exists('wc_get_coupon_types') ? wc_get_coupon_types() : [];
        $options = [
            'percent'       => __('Percentage discount', 'brikpanel'),
            'fixed_cart'    => __('Fixed cart discount', 'brikpanel'),
            'fixed_product' => __('Fixed product discount', 'brikpanel'),
        ];
        foreach (self::type_owners() as $key => $row) {
            if (!self::owner_shown($key)) {
                continue;
            }
            foreach ($row['types'] as $slug => $label) {
                if (isset($all[$slug]) && !isset($options[$slug])) {
                    $options[$slug] = $label;
                }
            }
        }
        if ($coupon instanceof WC_Coupon && $coupon->get_id()) {
            $own = (string) $coupon->get_discount_type();
            if (isset($all[$own]) && !isset($options[$own])) {
                $options[$own] = wp_strip_all_tags((string) $all[$own]);
            }
        }
        return $options;
    }

    /**
     * The choices of "Additional coupon data sections": owner key => its name
     * and the first things it adds, e.g. "WoodMart Child (Loyalty discount,
     * Loyalty tier)".
     *
     * @return array<string,string>
     */
    public static function settings_choices() {
        $choices = [];
        foreach (self::plugin_additions() as $key => $row) {
            $parts = array_values(array_unique(array_filter(array_merge(array_values($row['types']), $row['labels']), 'strlen')));
            $label = $row['name'];
            if ($parts) {
                $label .= ' (' . implode(', ', array_slice($parts, 0, 4)) . (count($parts) > 4 ? ', …' : '') . ')';
            }
            $choices[$key] = $label;
        }
        return $choices;
    }

    /**
     * The newest coupon, for drawing what plugins add without a coupon in hand
     * (a new, empty one when the store has none).
     *
     * @return WC_Coupon
     */
    public static function probe_coupon() {
        $ids = get_posts([
            'post_type'        => 'shop_coupon',
            'post_status'      => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page'   => 1,
            'orderby'          => 'ID',
            'order'            => 'DESC',
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ]);
        return new WC_Coupon($ids ? (int) $ids[0] : 0);
    }

    /** WordPress' and WooCommerce's own boxes on the coupon screen. */
    const CORE_BOXES = [
        'submitdiv', 'slugdiv', 'postcustom', 'commentsdiv', 'commentstatusdiv', 'authordiv', 'revisionsdiv',
        'postexcerpt', 'postimagediv', 'trackbacksdiv', 'pageparentdiv', 'formatdiv', 'woocommerce-coupon-data',
    ];

    /**
     * @param array $owners Owners so far, by reference.
     * @param array $owner  Owner from Brikpanel_Foreign_Hooks::owner().
     * @return void
     */
    private static function owner_slot(array &$owners, array $owner) {
        if (!isset($owners[$owner['key']])) {
            $owners[$owner['key']] = [
                'name'     => (string) $owner['name'],
                'dir'      => (string) $owner['dir'],
                'types'    => [],
                'sections' => [],
                'tabs'     => [],
                'panels'   => [],
                'boxes'    => [],
                'labels'   => [],
            ];
        }
    }

    /**
     * What a box another plugin registered for coupons prints.
     *
     * @param array   $box  Box from $wp_meta_boxes.
     * @param WP_Post $post The coupon.
     * @return string Markup, '' when it printed nothing or failed.
     */
    private static function render_box(array $box, $post) {
        $hook = 'brikpanel_cp_render_box';
        add_action($hook, $box['callback'], 10, 2);
        try {
            list($html, $threw) = Brikpanel_Foreign_Hooks::capture($hook, [$post, $box]);
        } finally {
            remove_action($hook, $box['callback'], 10);
        }
        return $threw ? '' : $html;
    }

    /**
     * The text of each <label> in some markup, for naming what a plugin adds.
     *
     * @param string $html Markup.
     * @return string[]
     */
    private static function field_labels($html) {
        if (!preg_match_all('/<label\b[^>]*>(.*?)<\/label>/is', (string) $html, $m)) {
            return [];
        }
        $out = [];
        foreach ($m[1] as $raw) {
            $text = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags(html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8'))));
            if ($text !== '' && strlen($text) <= 80) {
                $out[] = $text;
            }
        }
        return $out;
    }

    /**
     * Whether this request can draw the coupon screen: the admin includes
     * (screens, boxes) are loaded and WooCommerce is there.
     *
     * @return bool
     */
    private static function admin_ready() {
        return function_exists('add_meta_box') && function_exists('set_current_screen') && class_exists('WP_Screen')
            && class_exists('WC_Coupon') && class_exists('Brikpanel_Foreign_Hooks');
    }

    /**
     * Everything that can change whether this store adds anything to coupons:
     * the active plugins, must-use plugins, the theme and its functions.php,
     * BrikPanel's and WooCommerce's versions. A change is a new key, so a stale
     * answer is never read.
     *
     * @return string
     */
    private static function fingerprint() {
        $parts = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $parts = array_merge($parts, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        sort($parts);
        if (function_exists('wp_get_mu_plugins')) {
            foreach (wp_get_mu_plugins() as $file) {
                $parts[] = basename($file) . '@' . (int) @filemtime($file);
            }
        }
        $parts[] = get_stylesheet() . '|' . get_template();
        foreach (array_unique([get_stylesheet_directory(), get_template_directory()]) as $dir) {
            $parts[] = (int) @filemtime($dir . '/functions.php');
        }
        $parts[] = defined('BRIKPANEL_VERSION') ? BRIKPANEL_VERSION : '';
        $parts[] = defined('WC_VERSION') ? WC_VERSION : '';
        return substr(md5(implode('|', $parts)), 0, 12);
    }

    // =========================================================================
    // PAGE REGISTRATION & REDIRECT
    // =========================================================================

    /**
     * Registers the list under the menu WooCommerce keeps coupons in (Marketing
     * on current versions), as a row only access plugins see
     * (includes/brikpanel-screen-menu.php).
     */
    public function register_page() {
        $hooks = function_exists('brikpanel_add_screen_page')
            ? brikpanel_add_screen_page(brikpanel_screen_coupons_parent(), __('Coupons', 'brikpanel'), 'manage_woocommerce', 'brikpanel-coupons', [$this, 'render_page'])
            : array_filter([add_submenu_page('', __('Coupons', 'brikpanel'), '', 'manage_woocommerce', 'brikpanel-coupons', [$this, 'render_page'])]);

        foreach ($hooks as $hook) {
            add_action('load-' . $hook, function () {
                global $title;
                $title = __('Coupons', 'brikpanel');
            });
        }
    }

    public function redirect_default_list() {
        global $pagenow;

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Redirect from edit.php?post_type=shop_coupon
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && sanitize_key($_GET['post_type']) === 'shop_coupon') {
            if (!empty($_GET['action']) || !empty($_GET['action2']) || !empty($_GET['page']) || !empty($_GET['taxonomy'])) {
                return;
            }
            wp_safe_redirect(admin_url('admin.php?page=brikpanel-coupons'));
            exit;
        }

        // Redirect from post-new.php?post_type=shop_coupon
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && sanitize_key($_GET['post_type']) === 'shop_coupon') {
            wp_safe_redirect(admin_url('admin.php?page=brikpanel-coupons'));
            exit;
        }
    }

    // =========================================================================
    // RENDER PAGE
    // =========================================================================

    public function render_page() {
        $currency = get_woocommerce_currency_symbol();
        $fields   = self::enabled_fields();
        // What other plugins and the theme add to coupons: their types join the
        // type list when shown, their fields come into the window (filled in
        // per coupon by ajax_plugin_fields()).
        $plugin_owners = self::plugin_additions();
        $has_plugin_ui = false;
        foreach ($plugin_owners as $plugin_owner) {
            if ($plugin_owner['sections'] || $plugin_owner['panels'] || $plugin_owner['boxes'] || $plugin_owner['tabs']) {
                $has_plugin_ui = true;
                break;
            }
        }

        // Count coupons by status
        $counts  = wp_count_posts('shop_coupon');
        $publish = isset($counts->publish) ? (int) $counts->publish : 0;
        $draft   = isset($counts->draft) ? (int) $counts->draft : 0;
        $trash   = isset($counts->trash) ? (int) $counts->trash : 0;
        $all     = $publish + $draft;
        ?>
        <div class="wrap brikpanel-shell__page">
        <div class="brikpanel-cp" id="brikpanel-coupons-list">

            <?php
            // The header row gives way in its own order, measured
            // (front-end/shared/brikpanel-fit-row.js, CLAUDE.md "Başlık satırı
            // kuralı"): one line while it fits; then the title and Add coupon on
            // top with the search below; Add coupon's label goes to its icon only
            // as the last resort. On a 360px phone the search box could not
            // shrink and pushed Add coupon off the screen (field test C4).
            $bpc_header_fit = [
                'title'  => 'h1',
                'lines'  => [''],
                'levels' => [
                    '',
                    [ 'cls' => 'is-two-rows', 'lines' => [ '.brikpanel-cp-header-main' ] ],
                    [ 'cls' => 'is-two-rows is-icon-add', 'lines' => [ '.brikpanel-cp-header-main' ] ],
                ],
            ];
            ?>
            <!-- Header -->
            <div class="brikpanel-cp-header" id="bpc-header" data-bp-fit-row="<?php echo esc_attr(wp_json_encode($bpc_header_fit)); ?>">
                <?php
                // Fit as soon as the header opens: the page arrives in pieces and
                // each is painted (the helper itself is printed in <head>).
                wp_print_inline_script_tag('if(window.brikpanelFitRow){window.brikpanelFitRow.auto(document.getElementById("bpc-header"));}');
                ?>
                <div class="brikpanel-cp-header-main">
                    <div class="brikpanel-cp-header-left">
                        <h1><?php esc_html_e('Coupons', 'brikpanel'); ?></h1>
                        <span class="brikpanel-cp-count" id="bpc-total-count"><?php echo esc_html($all); ?></span>
                    </div>
                    <div class="brikpanel-cp-header-right">
                        <button type="button" class="brikpanel-cp-btn primary" id="bpc-add-new">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span class="brikpanel-cp-add-label"><?php esc_html_e('Add coupon', 'brikpanel'); ?></span>
                        </button>
                    </div>
                </div>
                <div class="brikpanel-cp-search-wrap">
                    <svg class="brikpanel-cp-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" id="bpc-search" class="brikpanel-cp-search brikpanel-control brikpanel-control--quiet" placeholder="<?php esc_attr_e('Search coupons...', 'brikpanel'); ?>" aria-label="<?php esc_attr_e('Search coupons...', 'brikpanel'); ?>">
                </div>
            </div>
            <?php brikpanel_header_end(); ?>

            <!-- Filters Bar -->
            <div class="brikpanel-cp-filters">
                <div class="brikpanel-cp-tabs" data-bp-strip>
                    <button class="brikpanel-cp-tab active" data-status="any">
                        <?php esc_html_e('All', 'brikpanel'); ?>
                        <span class="brikpanel-cp-tab-count" data-count="all"><?php echo esc_html($all); ?></span>
                    </button>
                    <button class="brikpanel-cp-tab" data-status="publish">
                        <?php esc_html_e('Published', 'brikpanel'); ?>
                        <span class="brikpanel-cp-tab-count" data-count="publish"><?php echo esc_html($publish); ?></span>
                    </button>
                    <button class="brikpanel-cp-tab" data-status="draft">
                        <?php esc_html_e('Draft', 'brikpanel'); ?>
                        <span class="brikpanel-cp-tab-count" data-count="draft"><?php echo esc_html($draft); ?></span>
                    </button>
                    <?php if ($trash > 0) : ?>
                    <button class="brikpanel-cp-tab" data-status="trash">
                        <?php esc_html_e('Trash', 'brikpanel'); ?>
                        <span class="brikpanel-cp-tab-count" data-count="trash"><?php echo esc_html($trash); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="brikpanel-cp-filter-group">
                    <select id="bpc-sort" class="brikpanel-cp-select brikpanel-control brikpanel-control--quiet">
                        <option value="date-desc"><?php esc_html_e('Newest first', 'brikpanel'); ?></option>
                        <option value="date-asc"><?php esc_html_e('Oldest first', 'brikpanel'); ?></option>
                        <option value="title-asc"><?php esc_html_e('Code A-Z', 'brikpanel'); ?></option>
                        <option value="title-desc"><?php esc_html_e('Code Z-A', 'brikpanel'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Bulk Actions Bar (hidden by default) -->
            <div class="brikpanel-cp-bulk-bar" id="bpc-bulk-bar" style="display:none;">
                <div class="brikpanel-cp-bulk-left">
                    <span id="bpc-selected-count">0</span> <?php esc_html_e('selected', 'brikpanel'); ?>
                    <button type="button" class="brikpanel-cp-bulk-link" id="bpc-select-all-btn"><?php esc_html_e('Select all', 'brikpanel'); ?></button>
                    <button type="button" class="brikpanel-cp-bulk-link" id="bpc-deselect-all-btn"><?php esc_html_e('Deselect all', 'brikpanel'); ?></button>
                </div>
                <div class="brikpanel-cp-bulk-right">
                    <button type="button" class="brikpanel-cp-btn secondary small" id="bpc-bulk-publish"><?php esc_html_e('Publish', 'brikpanel'); ?></button>
                    <button type="button" class="brikpanel-cp-btn secondary small" id="bpc-bulk-draft"><?php esc_html_e('Set as draft', 'brikpanel'); ?></button>
                    <button type="button" class="brikpanel-cp-btn danger small" id="bpc-bulk-trash"><?php esc_html_e('Move to trash', 'brikpanel'); ?></button>
                </div>
            </div>

            <!-- Coupons Table -->
            <div class="brikpanel-cp-card">
                <div class="brikpanel-cp-table-wrap">
                    <table class="brikpanel-cp-table brikpanel-fit-table" id="bpc-table">
                        <thead>
                            <tr>
                                <th class="brikpanel-cp-th-check">
                                    <input type="checkbox" id="bpc-check-all" class="brikpanel-cp-checkbox">
                                </th>
                                <th class="brikpanel-cp-th-code"><?php esc_html_e('Code', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-type"><?php esc_html_e('Type', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-amount"><?php esc_html_e('Amount', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-desc"><?php esc_html_e('Description', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-usage"><?php esc_html_e('Usage / limit', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-revenue"><?php esc_html_e('Revenue', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-expiry"><?php esc_html_e('Expiry date', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-status"><?php esc_html_e('Status', 'brikpanel'); ?></th>
                                <th class="brikpanel-cp-th-actions"></th>
                            </tr>
                        </thead>
                        <tbody id="bpc-table-body">
                            <tr class="brikpanel-cp-loading-row">
                                <td colspan="10">
                                    <div class="brikpanel-cp-spinner"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="brikpanel-cp-pagination" id="bpc-pagination"></div>
            </div>

            <!-- Drawer Overlay -->
            <div class="brikpanel-cp-drawer-overlay" id="bpc-drawer-overlay"></div>

            <!-- Coupon Drawer (Edit / Create) -->
            <div class="brikpanel-cp-drawer" id="bpc-drawer">
                <div class="brikpanel-cp-drawer-header">
                    <h3 id="bpc-drawer-title"><?php esc_html_e('Edit coupon', 'brikpanel'); ?></h3>
                    <button type="button" class="brikpanel-cp-drawer-close" id="bpc-drawer-close">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="brikpanel-cp-drawer-body">
                    <input type="hidden" id="bpc-coupon-id" value="">

                    <!-- General Section -->
                    <div class="brikpanel-cp-drawer-section">
                        <h4 class="brikpanel-cp-drawer-section-title"><?php esc_html_e('General', 'brikpanel'); ?></h4>

                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-code"><?php esc_html_e('Coupon code', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-code-row">
                                <?php // lang="en": the box shows the code in capitals with CSS, and CSS capitals follow the page language (a Turkish dashboard wrote "BRİK"). A code is not Turkish text. ?>
                                <input type="text" id="bpc-code" lang="en" spellcheck="false" placeholder="<?php esc_attr_e('e.g. SUMMER2024', 'brikpanel'); ?>">
                                <button type="button" class="brikpanel-cp-btn secondary small" id="bpc-generate-code">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                    <?php esc_html_e('Generate', 'brikpanel'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-description"><?php esc_html_e('Description', 'brikpanel'); ?></label>
                            <textarea id="bpc-description" rows="2" placeholder="<?php esc_attr_e('Optional description for internal use', 'brikpanel'); ?>"></textarea>
                        </div>

                        <div class="brikpanel-cp-qe-row">
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-discount-type"><?php esc_html_e('Discount type', 'brikpanel'); ?></label>
                                <?php
                                // WooCommerce's three types, and another plugin's when the
                                // settings show it (type_options()). A coupon whose own type
                                // is not in this list gets it added by the script, so saving
                                // never changes its type.
                                ?>
                                <select id="bpc-discount-type" class="brikpanel-cp-select full brikpanel-control">
                                    <?php foreach (self::type_options() as $type_value => $type_label) : ?>
                                    <option value="<?php echo esc_attr($type_value); ?>"><?php echo esc_html($type_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-amount"><?php esc_html_e('Amount', 'brikpanel'); ?></label>
                                <div class="brikpanel-cp-input-group" id="bpc-amount-group">
                                    <span class="brikpanel-cp-input-prefix" id="bpc-amount-prefix">%</span>
                                    <input type="text" id="bpc-amount" placeholder="0">
                                </div>
                            </div>
                        </div>

                        <div class="brikpanel-cp-qe-field">
                            <label class="brikpanel-cp-toggle-label">
                                <span><?php esc_html_e('Free shipping', 'brikpanel'); ?></span>
                                <span class="brikpanel-cp-toggle" id="bpc-free-shipping-toggle">
                                    <input type="checkbox" id="bpc-free-shipping">
                                    <span class="brikpanel-cp-toggle-slider"></span>
                                </span>
                            </label>
                        </div>

                        <div class="brikpanel-cp-qe-field">
                            <label class="brikpanel-cp-toggle-label">
                                <span><?php esc_html_e('Apply automatically', 'brikpanel'); ?></span>
                                <span class="brikpanel-cp-toggle">
                                    <input type="checkbox" id="bpc-auto-apply" aria-describedby="bpc-auto-apply-hint">
                                    <span class="brikpanel-cp-toggle-slider"></span>
                                </span>
                            </label>
                            <span class="brikpanel-cp-field-hint" id="bpc-auto-apply-hint"><?php esc_html_e('Added to the cart by itself when the cart meets this coupon\'s rules. Shoppers do not type the code. When several automatic coupons fit, the one with the biggest discount is used.', 'brikpanel'); ?></span>
                        </div>

                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-expiry"><?php esc_html_e('Expiry date', 'brikpanel'); ?></label>
                            <input type="date" id="bpc-expiry">
                        </div>

                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-link"><?php esc_html_e('Coupon link', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-code-row">
                                <input type="text" id="bpc-link" class="brikpanel-cp-link-input" dir="ltr" readonly aria-describedby="bpc-link-hint">
                                <button type="button" class="brikpanel-cp-btn secondary small" id="bpc-copy-link"><?php esc_html_e('Copy link', 'brikpanel'); ?></button>
                            </div>
                            <span class="brikpanel-cp-field-hint" id="bpc-link-hint"><?php esc_html_e('Opens your store with this coupon added. It takes effect once the cart meets the coupon\'s rules.', 'brikpanel'); ?></span>
                        </div>
                    </div>

                    <!-- Usage Restrictions Section -->
                    <div class="brikpanel-cp-drawer-section">
                        <h4 class="brikpanel-cp-drawer-section-title"><?php esc_html_e('Usage restrictions', 'brikpanel'); ?></h4>

                        <div class="brikpanel-cp-qe-row">
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-min-spend"><?php esc_html_e('Minimum spend', 'brikpanel'); ?></label>
                                <div class="brikpanel-cp-input-group">
                                    <span class="brikpanel-cp-input-prefix"><?php echo esc_html($currency); ?></span>
                                    <input type="text" id="bpc-min-spend" placeholder="<?php esc_attr_e('No minimum', 'brikpanel'); ?>">
                                </div>
                            </div>
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-max-spend"><?php esc_html_e('Maximum spend', 'brikpanel'); ?></label>
                                <div class="brikpanel-cp-input-group">
                                    <span class="brikpanel-cp-input-prefix"><?php echo esc_html($currency); ?></span>
                                    <input type="text" id="bpc-max-spend" placeholder="<?php esc_attr_e('No maximum', 'brikpanel'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="brikpanel-cp-qe-row">
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-min-items"><?php esc_html_e('Minimum items', 'brikpanel'); ?></label>
                                <input type="number" id="bpc-min-items" min="0" step="1" inputmode="numeric" placeholder="<?php esc_attr_e('No minimum', 'brikpanel'); ?>" aria-describedby="bpc-items-hint">
                            </div>
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-max-items"><?php esc_html_e('Maximum items', 'brikpanel'); ?></label>
                                <input type="number" id="bpc-max-items" min="0" step="1" inputmode="numeric" placeholder="<?php esc_attr_e('No maximum', 'brikpanel'); ?>" aria-describedby="bpc-items-hint">
                            </div>
                        </div>
                        <span class="brikpanel-cp-field-hint brikpanel-cp-items-hint" id="bpc-items-hint"><?php esc_html_e('Counts the items in the cart this coupon can discount. For tiers, give each coupon a range, e.g. 1 to 5, 6 to 10.', 'brikpanel'); ?></span>

                        <div class="brikpanel-cp-qe-field">
                            <label class="brikpanel-cp-toggle-label">
                                <span><?php esc_html_e('Individual use only', 'brikpanel'); ?></span>
                                <span class="brikpanel-cp-toggle">
                                    <input type="checkbox" id="bpc-individual-use">
                                    <span class="brikpanel-cp-toggle-slider"></span>
                                </span>
                            </label>
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Cannot be used with other coupons', 'brikpanel'); ?></span>
                        </div>

                        <div class="brikpanel-cp-qe-field">
                            <label class="brikpanel-cp-toggle-label">
                                <span><?php esc_html_e('Exclude sale items', 'brikpanel'); ?></span>
                                <span class="brikpanel-cp-toggle">
                                    <input type="checkbox" id="bpc-exclude-sale">
                                    <span class="brikpanel-cp-toggle-slider"></span>
                                </span>
                            </label>
                        </div>

                        <?php if ($fields['products']) : ?>
                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-products-search"><?php esc_html_e('Products', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-token-field" data-token-target="products">
                                <div class="brikpanel-cp-tokens" id="bpc-products-tokens"></div>
                                <div class="brikpanel-cp-token-input-wrap">
                                    <input type="text" id="bpc-products-search" class="brikpanel-cp-token-input" autocomplete="off" placeholder="<?php esc_attr_e('Search products by name or SKU…', 'brikpanel'); ?>">
                                    <div class="brikpanel-cp-token-suggestions" id="bpc-products-suggestions" hidden></div>
                                </div>
                            </div>
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Limit the coupon to these products. Leave empty to allow all products.', 'brikpanel'); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($fields['exclude_products']) : ?>
                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-exclude-products-search"><?php esc_html_e('Exclude products', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-token-field" data-token-target="exclude_products">
                                <div class="brikpanel-cp-tokens" id="bpc-exclude-products-tokens"></div>
                                <div class="brikpanel-cp-token-input-wrap">
                                    <input type="text" id="bpc-exclude-products-search" class="brikpanel-cp-token-input" autocomplete="off" placeholder="<?php esc_attr_e('Search products to exclude…', 'brikpanel'); ?>">
                                    <div class="brikpanel-cp-token-suggestions" id="bpc-exclude-products-suggestions" hidden></div>
                                </div>
                            </div>
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Coupon will not apply when the cart contains these products.', 'brikpanel'); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($fields['categories']) : ?>
                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-categories-search"><?php esc_html_e('Product categories', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-token-field" data-token-target="categories">
                                <div class="brikpanel-cp-tokens" id="bpc-categories-tokens"></div>
                                <div class="brikpanel-cp-token-input-wrap">
                                    <input type="text" id="bpc-categories-search" class="brikpanel-cp-token-input" autocomplete="off" placeholder="<?php esc_attr_e('Search categories…', 'brikpanel'); ?>">
                                    <div class="brikpanel-cp-token-suggestions" id="bpc-categories-suggestions" hidden></div>
                                </div>
                            </div>
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Coupon applies only to products in these categories. Leave empty to allow all categories.', 'brikpanel'); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($fields['exclude_categories']) : ?>
                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-exclude-categories-search"><?php esc_html_e('Exclude categories', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-token-field" data-token-target="exclude_categories">
                                <div class="brikpanel-cp-tokens" id="bpc-exclude-categories-tokens"></div>
                                <div class="brikpanel-cp-token-input-wrap">
                                    <input type="text" id="bpc-exclude-categories-search" class="brikpanel-cp-token-input" autocomplete="off" placeholder="<?php esc_attr_e('Search categories to exclude…', 'brikpanel'); ?>">
                                    <div class="brikpanel-cp-token-suggestions" id="bpc-exclude-categories-suggestions" hidden></div>
                                </div>
                            </div>
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Coupon will not apply to products in these categories.', 'brikpanel'); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($fields['emails']) : ?>
                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-emails-search"><?php esc_html_e('Assigned customers', 'brikpanel'); ?></label>
                            <div class="brikpanel-cp-token-field" data-token-target="emails">
                                <div class="brikpanel-cp-tokens" id="bpc-emails-tokens"></div>
                                <div class="brikpanel-cp-token-input-wrap">
                                    <input type="text" id="bpc-emails-search" class="brikpanel-cp-token-input" autocomplete="off" placeholder="<?php esc_attr_e('Search customers by name, username or email…', 'brikpanel'); ?>">
                                    <div class="brikpanel-cp-token-suggestions" id="bpc-emails-suggestions" hidden></div>
                                </div>
                            </div>
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Only these customers can use the coupon. Pick registered users from the list, or type any email address (wildcards like *@example.com work too) and press Enter. A picked user is matched by their account email, so it works even if they check out with a different billing address.', 'brikpanel'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Usage Limits Section -->
                    <div class="brikpanel-cp-drawer-section">
                        <h4 class="brikpanel-cp-drawer-section-title"><?php esc_html_e('Usage limits', 'brikpanel'); ?></h4>

                        <div class="brikpanel-cp-qe-row">
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-usage-limit"><?php esc_html_e('Usage limit per coupon', 'brikpanel'); ?></label>
                                <input type="number" id="bpc-usage-limit" min="0" placeholder="<?php esc_attr_e('Unlimited', 'brikpanel'); ?>">
                            </div>
                            <div class="brikpanel-cp-qe-field">
                                <label for="bpc-usage-limit-user"><?php esc_html_e('Usage limit per user', 'brikpanel'); ?></label>
                                <input type="number" id="bpc-usage-limit-user" min="0" placeholder="<?php esc_attr_e('Unlimited', 'brikpanel'); ?>">
                            </div>
                        </div>

                        <?php if ($fields['limit_items']) : ?>
                        <div class="brikpanel-cp-qe-field">
                            <label for="bpc-limit-items"><?php esc_html_e('Limit usage to X items', 'brikpanel'); ?></label>
                            <input type="number" id="bpc-limit-items" min="0" placeholder="<?php esc_attr_e('All matching items', 'brikpanel'); ?>">
                            <span class="brikpanel-cp-field-hint"><?php esc_html_e('Caps the number of matching items the discount applies to per cart. Only used with product or category restrictions.', 'brikpanel'); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="brikpanel-cp-qe-field" id="bpc-usage-count-field" style="display:none;">
                            <label><?php esc_html_e('Usage count', 'brikpanel'); ?></label>
                            <span class="brikpanel-cp-usage-count-display" id="bpc-usage-count-display">0</span>
                        </div>
                    </div>

                    <?php if ($has_plugin_ui) : ?>
                    <?php
                    // What other plugins and the theme add to WooCommerce's coupon
                    // screen, filled in per coupon by ajax_plugin_fields(). The
                    // frame is WooCommerce's own (#woocommerce-coupon-data >
                    // .inside > #coupon_options) and select#discount_type follows
                    // the window's type: plugin scripts that bind to those when
                    // the page loads (ShopBuilder Pro shows its fields by the type)
                    // find them, as on WooCommerce's screen.
                    ?>
                    <div class="bpc-ext" id="bpc-ext" hidden>
                        <div id="woocommerce-coupon-data" class="bpc-ext__frame">
                            <div class="inside">
                                <div id="coupon_options" class="panel-wrap coupon_data">
                                    <select id="discount_type" class="bpc-ext__mirror" tabindex="-1" aria-hidden="true" hidden>
                                        <?php foreach (wc_get_coupon_types() as $type_value => $type_label) : ?>
                                        <option value="<?php echo esc_attr($type_value); ?>"><?php echo esc_html($type_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="bpc-ext__body" id="bpc-ext-body"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="brikpanel-cp-drawer-footer">
                    <button type="button" class="brikpanel-cp-btn secondary" id="bpc-drawer-cancel"><?php esc_html_e('Cancel', 'brikpanel'); ?></button>
                    <button type="button" class="brikpanel-cp-btn primary" id="bpc-drawer-save"><?php esc_html_e('Save changes', 'brikpanel'); ?></button>
                </div>
            </div>

            <!-- Toast Container -->
            <div class="brikpanel-cp-toast-container" id="bpc-toast-container"></div>

        </div>
        </div>
        <?php
    }

    // =========================================================================
    // AJAX: FETCH COUPONS
    // =========================================================================

    public function ajax_fetch_coupons() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $page     = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(1, min(100, intval($_POST['per_page'] ?? 20)));
        $search   = sanitize_text_field($_POST['search'] ?? '');
        $status   = sanitize_key($_POST['status'] ?? 'any');
        $sort     = sanitize_text_field($_POST['sort'] ?? 'date-desc');

        // Parse sort
        $sort_parts = explode('-', $sort);
        $orderby = $sort_parts[0] ?? 'date';
        $order   = strtoupper($sort_parts[1] ?? 'DESC');

        if (!in_array($orderby, ['date', 'title'], true)) {
            $orderby = 'date';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $statuses = ['publish', 'draft'];
        if ($status === 'publish') {
            $statuses = ['publish'];
        } elseif ($status === 'draft') {
            $statuses = ['draft'];
        } elseif ($status === 'trash') {
            $statuses = ['trash'];
        }

        $args = [
            'post_type'      => 'shop_coupon',
            'post_status'    => $statuses,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        ];

        if ($search) {
            $args['s'] = $search;
        }

        $query   = new WP_Query($args);
        $coupons = [];

        // Resolve once per request: third-party (ASE etc.) extra columns
        // contributed via manage_{post_type}_posts_columns filter.
        $extra_columns = class_exists('Brikpanel_ASE_Bridge')
            ? Brikpanel_ASE_Bridge::get_extra_columns('shop_coupon')
            : [];

        // First pass: build coupon objects and collect codes for batch revenue query.
        $coupon_objects = [];
        $coupon_codes   = [];
        foreach ($query->posts as $post) {
            $coupon                      = new WC_Coupon($post->ID);
            $coupon_objects[$post->ID]   = $coupon;
            $coupon_codes[]              = $coupon->get_code();
        }

        // Single DB query for all coupon revenues on this page.
        $revenue_map = $this->get_coupons_revenue_batch($coupon_codes);

        // Resolve every restricted email to a registered user in one query so the
        // drawer can label them, instead of one lookup per email per coupon.
        $all_emails = [];
        foreach ($coupon_objects as $c) {
            foreach ((array) $c->get_email_restrictions() as $restricted_email) {
                $all_emails[] = $restricted_email;
            }
        }
        $this->prime_user_email_cache($all_emails);

        // Publish our local query as the global loop while third-party column
        // cells render — column callbacks are written for WP_Posts_List_Table
        // and several of them bulk-prefetch from `global $wp_query`->posts.
        // See Brikpanel_ASE_Bridge::begin_loop_context(). Skipped when no
        // plugin contributes columns, and always closed in the finally so a
        // throw cannot leave a stale loop behind.
        $loop_published = !empty($extra_columns) && class_exists('Brikpanel_ASE_Bridge')
            && Brikpanel_ASE_Bridge::begin_loop_context($query);

        try {
            foreach ($query->posts as $post) {
                $coupon = $coupon_objects[$post->ID];

                $expiry_ts   = $coupon->get_date_expires();
                $expiry_date = $expiry_ts ? $expiry_ts->date('Y-m-d') : '';

                $usage_limit     = $coupon->get_usage_limit();
                $usage_count     = $coupon->get_usage_count();
                $coupon_revenue  = $revenue_map[ strtolower( $coupon->get_code() ) ] ?? 0.0;

                $extra_cells = [];
                if ($extra_columns) {
                    if ($loop_published) {
                        Brikpanel_ASE_Bridge::set_loop_post($post);
                    }
                    foreach ($extra_columns as $col_id => $col_label) {
                        $extra_cells[$col_id] = Brikpanel_ASE_Bridge::render_cell('shop_coupon', $col_id, $post->ID);
                    }
                }

                $extra_actions = class_exists('Brikpanel_ASE_Bridge')
                    ? Brikpanel_ASE_Bridge::get_row_actions($post)
                    : [];

                $product_ids          = array_map('intval', (array) $coupon->get_product_ids());
                $excluded_product_ids = array_map('intval', (array) $coupon->get_excluded_product_ids());
                $category_ids         = array_map('intval', (array) $coupon->get_product_categories());
                $excluded_category_ids = array_map('intval', (array) $coupon->get_excluded_product_categories());
                $email_restrictions   = (array) $coupon->get_email_restrictions();
                $limit_items          = $coupon->get_limit_usage_to_x_items();

                $coupons[] = [
                    'id'                       => $post->ID,
                    'code'                     => $coupon->get_code() ?? '',
                    'description'              => $coupon->get_description() ?? '',
                    'discount_type'            => $coupon->get_discount_type(),
                    'amount'                   => $coupon->get_amount(),
                    'free_shipping'            => $coupon->get_free_shipping() ? 'yes' : 'no',
                    'expiry_date'              => $expiry_date,
                    'minimum_amount'           => $coupon->get_minimum_amount(),
                    'maximum_amount'           => $coupon->get_maximum_amount(),
                    'individual_use'           => $coupon->get_individual_use() ? 'yes' : 'no',
                    'exclude_sale_items'       => $coupon->get_exclude_sale_items() ? 'yes' : 'no',
                    'usage_limit'              => $usage_limit ? $usage_limit : '',
                    'usage_limit_per_user'     => $coupon->get_usage_limit_per_user() ? $coupon->get_usage_limit_per_user() : '',
                    'usage_count'              => $usage_count,
                    'revenue'                  => $coupon_revenue,
                    'revenue_formatted'        => $coupon_revenue > 0 ? wc_price($coupon_revenue) : '',
                    'status'                   => $post->post_status,
                    'product_ids'              => $product_ids,
                    'product_labels'           => $this->build_product_labels($product_ids),
                    'excluded_product_ids'     => $excluded_product_ids,
                    'excluded_product_labels'  => $this->build_product_labels($excluded_product_ids),
                    'category_ids'             => $category_ids,
                    'category_labels'          => $this->build_category_labels($category_ids),
                    'excluded_category_ids'    => $excluded_category_ids,
                    'excluded_category_labels' => $this->build_category_labels($excluded_category_ids),
                    'email_restrictions'       => implode(', ', $email_restrictions),
                    'email_labels'             => $this->build_email_labels($email_restrictions),
                    'limit_usage_to_x_items'   => $limit_items ? (int) $limit_items : '',
                    // Automatic coupons (front-end/coupons/brikpanel-auto-coupons.php).
                    'auto_apply'               => class_exists('Brikpanel_Auto_Coupons') && Brikpanel_Auto_Coupons::is_auto($coupon) ? 'yes' : 'no',
                    'min_items'                => class_exists('Brikpanel_Auto_Coupons') ? Brikpanel_Auto_Coupons::item_limit($coupon, 'min') : '',
                    'max_items'                => class_exists('Brikpanel_Auto_Coupons') ? Brikpanel_Auto_Coupons::item_limit($coupon, 'max') : '',
                    'extra_cells'              => (object) $extra_cells,
                    'extra_actions'            => $extra_actions,
                ];
            }
        } finally {
            if ($loop_published) {
                Brikpanel_ASE_Bridge::end_loop_context();
            }
        }

        // Counts
        $all_counts = wp_count_posts('shop_coupon');

        wp_send_json_success([
            'coupons'       => $coupons,
            'total'         => (int) $query->found_posts,
            'pages'         => (int) $query->max_num_pages,
            'extra_columns' => (object) $extra_columns,
            'counts'        => [
                'all'     => (int) (($all_counts->publish ?? 0) + ($all_counts->draft ?? 0)),
                'publish' => (int) ($all_counts->publish ?? 0),
                'draft'   => (int) ($all_counts->draft ?? 0),
                'trash'   => (int) ($all_counts->trash ?? 0),
            ],
        ]);
    }

    // =========================================================================
    // REVENUE BATCH QUERY
    // =========================================================================

    /**
     * Returns total order revenue (wc-processing + wc-completed) per coupon code.
     * Uses a single JOIN query so N coupons on a page = 1 DB hit, not N.
     *
     * @param string[] $coupon_codes  Raw coupon codes (case-insensitive match).
     * @return array<string,float>    Keys are lowercase coupon codes.
     */
    private function get_coupons_revenue_batch( array $coupon_codes ): array {
        if ( empty( $coupon_codes ) ) {
            return [];
        }

        global $wpdb;

        $lower_codes  = array_map( 'strtolower', $coupon_codes );
        $placeholders = implode( ',', array_fill( 0, count( $lower_codes ), '%s' ) );
        $hpos         = get_option( 'woocommerce_custom_orders_table_enabled' ) === 'yes';

        // Each order counts once per code: the inner DISTINCT drops a coupon
        // line stored twice on the same order, and the order total is read
        // from a single meta row. An order that used two different coupons
        // still credits its total to each of them, on purpose.
        $coupon_orders = "SELECT DISTINCT LOWER(oi.order_item_name) AS code, oi.order_id
                 FROM {$wpdb->prefix}woocommerce_order_items oi
                 WHERE oi.order_item_type = 'coupon'
                 AND LOWER(oi.order_item_name) IN ($placeholders)";

        if ( $hpos ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT c.code, SUM(o.total_amount) AS revenue
                 FROM ( {$coupon_orders} ) c
                 INNER JOIN {$wpdb->prefix}wc_orders o ON o.id = c.order_id
                 WHERE o.type = 'shop_order'
                 AND o.status IN (" . brikpanel_paid_statuses_sql() . ")
                 GROUP BY c.code",
                ...$lower_codes
            ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT c.code, SUM(CAST(pm.meta_value AS DECIMAL(20,4))) AS revenue
                 FROM ( {$coupon_orders} ) c
                 INNER JOIN {$wpdb->posts} p ON p.ID = c.order_id
                 " . brikpanel_sql_single_meta_join( 'post', 'pm', 'c.order_id', '_order_total', '', 'INNER' ) . "
                 WHERE p.post_type = 'shop_order'
                 AND p.post_status IN (" . brikpanel_paid_statuses_sql() . ")
                 GROUP BY c.code",
                ...$lower_codes
            ) );
        }

        $map = [];
        foreach ( $rows as $row ) {
            $map[ $row->code ] = (float) $row->revenue;
        }

        return $map;
    }

    // =========================================================================
    // AJAX: SAVE COUPON (Create or Update)
    // =========================================================================

    public function ajax_save_coupon() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        $code      = sanitize_text_field($_POST['code'] ?? '');

        if (empty($code)) {
            wp_send_json_error(['message' => __('Coupon code is required.', 'brikpanel')]);
        }

        // Check for duplicate coupon code (only for new coupons or code changes)
        if ($coupon_id > 0) {
            $coupon = new WC_Coupon($coupon_id);
        } else {
            $coupon = new WC_Coupon();
        }

        // Check duplicate code
        $existing = wc_get_coupon_id_by_code($code);
        if ($existing && $existing !== $coupon_id) {
            wp_send_json_error(['message' => __('A coupon with this code already exists.', 'brikpanel')]);
        }

        $coupon->set_code($code);
        $coupon->set_description(sanitize_textarea_field($_POST['description'] ?? ''));

        // The type must be one the window offers (type_options(): WooCommerce's
        // three, another plugin's the settings show, and this coupon's own).
        // Anything else leaves a saved coupon's type as it is. A type the old
        // fixed list did not know became "percent" here, so a 10 $ coupon of a
        // theme's own type came back as 10 % after any save, the table's
        // amount edit included (wp.org sstefanov, 21 Nov 2026).
        $type_options  = self::type_options($coupon->get_id() ? $coupon : null);
        $discount_type = isset($_POST['discount_type']) && is_string($_POST['discount_type'])
            ? sanitize_text_field(wp_unslash($_POST['discount_type']))
            : '';
        if (isset($type_options[$discount_type])) {
            if (!$coupon->get_id() || $discount_type !== $coupon->get_discount_type()) {
                try {
                    $coupon->set_discount_type($discount_type);
                } catch (WC_Data_Exception $e) {
                    wp_send_json_error(['message' => __('Choose a discount type.', 'brikpanel')]);
                }
            }
        } elseif (!$coupon->get_id()) {
            wp_send_json_error(['message' => __('Choose a discount type.', 'brikpanel')]);
        }

        $amount = floatval($_POST['amount'] ?? 0);
        $coupon->set_amount($amount);

        $free_shipping = sanitize_key($_POST['free_shipping'] ?? 'no');
        $coupon->set_free_shipping($free_shipping === 'yes');

        // Expiry date
        $expiry = sanitize_text_field($_POST['expiry_date'] ?? '');
        if ($expiry) {
            // "Expires on 19 September" means usable through the end of the 19th
            // IN THE STORE'S TIMEZONE. strtotime() ran with PHP's default zone,
            // which WordPress pins to UTC, so the coupon actually survived until
            // 03:00 on the 20th on a UTC+3 store — and WooCommerce's own coupon
            // screen, which renders the stored instant in site time, then showed
            // the 20th. Building the instant in wp_timezone() fixes both.
            $expiry_day = brikpanel_local_day_object($expiry);
            if ($expiry_day) {
                $coupon->set_date_expires($expiry_day->setTime(23, 59, 59)->getTimestamp());
            } else {
                $coupon->set_date_expires(null);
            }
        } else {
            $coupon->set_date_expires(null);
        }

        // Usage restrictions
        $min_amount = sanitize_text_field($_POST['minimum_amount'] ?? '');
        $coupon->set_minimum_amount($min_amount !== '' ? floatval($min_amount) : '');

        $max_amount = sanitize_text_field($_POST['maximum_amount'] ?? '');
        $coupon->set_maximum_amount($max_amount !== '' ? floatval($max_amount) : '');

        $individual_use = sanitize_key($_POST['individual_use'] ?? 'no');
        $coupon->set_individual_use($individual_use === 'yes');

        $exclude_sale = sanitize_key($_POST['exclude_sale_items'] ?? 'no');
        $coupon->set_exclude_sale_items($exclude_sale === 'yes');

        // Usage limits
        $usage_limit = sanitize_text_field($_POST['usage_limit'] ?? '');
        $coupon->set_usage_limit($usage_limit !== '' ? intval($usage_limit) : 0);

        $usage_limit_user = sanitize_text_field($_POST['usage_limit_per_user'] ?? '');
        $coupon->set_usage_limit_per_user($usage_limit_user !== '' ? intval($usage_limit_user) : 0);

        // Optional advanced restriction fields. We only touch each field when:
        //   1) the matching setting is enabled (a disabled field cannot be
        //      tampered with via a forged POST), AND
        //   2) the key is explicitly present in the payload (so inline edits
        //      from the table — which only send "amount" — do not wipe out
        //      the rest of the restriction config).
        $enabled = self::enabled_fields();

        if ($enabled['products'] && array_key_exists('product_ids', $_POST)) {
            $coupon->set_product_ids($this->sanitize_id_list($_POST['product_ids']));
        }
        if ($enabled['exclude_products'] && array_key_exists('excluded_product_ids', $_POST)) {
            $coupon->set_excluded_product_ids($this->sanitize_id_list($_POST['excluded_product_ids']));
        }
        if ($enabled['categories'] && array_key_exists('category_ids', $_POST)) {
            $coupon->set_product_categories($this->sanitize_id_list($_POST['category_ids']));
        }
        if ($enabled['exclude_categories'] && array_key_exists('excluded_category_ids', $_POST)) {
            $coupon->set_excluded_product_categories($this->sanitize_id_list($_POST['excluded_category_ids']));
        }
        if ($enabled['emails'] && array_key_exists('email_restrictions', $_POST)) {
            $raw_emails = wp_unslash($_POST['email_restrictions']);
            $coupon->set_email_restrictions($this->sanitize_email_list($raw_emails));
        }
        if ($enabled['limit_items'] && array_key_exists('limit_usage_to_x_items', $_POST)) {
            $limit_items = sanitize_text_field($_POST['limit_usage_to_x_items']);
            $coupon->set_limit_usage_to_x_items($limit_items !== '' ? intval($limit_items) : '');
        }

        // Automatic coupons: "Apply automatically" and the item count rule.
        // Written only when the key is in the payload: the table's inline
        // amount edit sends the core fields alone and must not clear these.
        if (class_exists('Brikpanel_Auto_Coupons')) {
            if (array_key_exists('auto_apply', $_POST)) {
                $auto = 'yes' === sanitize_key(wp_unslash($_POST['auto_apply']));
                if ($auto) {
                    $coupon->update_meta_data(Brikpanel_Auto_Coupons::META_AUTO, 'yes');
                } else {
                    $coupon->delete_meta_data(Brikpanel_Auto_Coupons::META_AUTO);
                }
            }
            $min_items = array_key_exists('min_items', $_POST)
                ? Brikpanel_Auto_Coupons::sanitize_item_limit(wp_unslash($_POST['min_items']))
                : Brikpanel_Auto_Coupons::item_limit($coupon, 'min');
            $max_items = array_key_exists('max_items', $_POST)
                ? Brikpanel_Auto_Coupons::sanitize_item_limit(wp_unslash($_POST['max_items']))
                : Brikpanel_Auto_Coupons::item_limit($coupon, 'max');
            if ('' !== $min_items && '' !== $max_items && $max_items < $min_items) {
                wp_send_json_error(['message' => __('Maximum items cannot be less than minimum items.', 'brikpanel')]);
            }
            foreach (['min' => $min_items, 'max' => $max_items] as $which => $value) {
                $key = 'min' === $which ? Brikpanel_Auto_Coupons::META_MIN : Brikpanel_Auto_Coupons::META_MAX;
                if ('' === $value) {
                    $coupon->delete_meta_data($key);
                } else {
                    $coupon->update_meta_data($key, (int) $value);
                }
            }
        }

        // Set status - default publish for new coupons.
        // The status is staged before the save and reasserted after it: older
        // WooCommerce has no WC_Coupon::set_status(), and a brand-new coupon
        // has no post to update until save() has given it an ID.
        $is_new = ($coupon_id === 0);
        if ($is_new) {
            brikpanel_wc_coupon_set_status($coupon, 'publish');
        }

        $coupon->save();

        // Only for a new coupon: reasserting this on an edit would silently
        // republish a coupon the merchant had drafted or scheduled.
        if ($is_new) {
            brikpanel_wc_coupon_sync_status($coupon->get_id(), 'publish');
        }

        // Other plugins' fields that were on the form (shown open, or their
        // folded box opened) are saved by those plugins, as on WooCommerce's
        // screen. A plugin whose fields were not on the form is never asked:
        // several clear a value whose field is absent (B2BKing's coupon role).
        $posted_owners = isset($_POST['bp_cp_owners']) && is_string($_POST['bp_cp_owners'])
            ? sanitize_text_field(wp_unslash($_POST['bp_cp_owners']))
            : '';
        $failed = $this->save_plugin_fields($coupon->get_id(), $posted_owners);

        $message = $coupon_id > 0 ? __('Coupon updated!', 'brikpanel') : __('Coupon created!', 'brikpanel');
        if ($failed) {
            /* translators: %s: names of the plugins whose coupon fields could not be saved, comma separated. */
            $message = sprintf(__('Coupon saved, but these plugins could not save their fields: %s', 'brikpanel'), implode(', ', $failed));
        }

        wp_send_json_success([
            'message'   => $message,
            'coupon_id' => $coupon->get_id(),
            'warning'   => (bool) $failed,
        ]);
    }

    // =========================================================================
    // OTHER PLUGINS' COUPON FIELDS
    // =========================================================================

    /**
     * The fields other plugins and the theme add to WooCommerce's coupon
     * screen, drawn for one coupon (0: a new one) for the window.
     *
     * Owners the settings show come open, each under its name; the rest wait
     * in the folded "Fields from your other plugins" box, open only for a
     * person who opened it before. Discount types are not here: they are in
     * the type list (type_options()).
     */
    public function ajax_plugin_fields() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $coupon_id = isset($_POST['coupon_id']) ? absint(wp_unslash($_POST['coupon_id'])) : 0;
        if ($coupon_id) {
            if (get_post_type($coupon_id) !== 'shop_coupon' || !current_user_can('edit_post', $coupon_id)) {
                wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
            }
            $coupon = new WC_Coupon($coupon_id);
        } else {
            $coupon = new WC_Coupon();
        }

        // Another plugin's markup goes back inside JSON: a stray echo or
        // notice of theirs must not break the reply.
        ob_start();
        $owners = self::collect($coupon, true);
        ob_end_clean();

        $shown = [];
        $fold  = [];
        foreach ($owners as $key => $row) {
            if (!$row['sections'] && !$row['panels'] && !$row['boxes']) {
                continue; // types only, or a tab whose panel printed nothing
            }
            if (self::owner_shown($key)) {
                $shown[$key] = $row;
            } else {
                $fold[$key] = $row;
            }
        }

        wp_send_json_success([
            'html'  => self::plugin_fields_html($shown, $fold, $owners, $coupon_id),
            'types' => self::type_options($coupon_id ? $coupon : null),
        ]);
    }

    /**
     * The markup of the window's part for other plugins, see ajax_plugin_fields().
     *
     * WooCommerce's panel ids (general_coupon_data…) go on the first panel of
     * each section, once: plugin scripts look their fields up under them.
     * Another plugin's markup is printed as that plugin printed it, the way
     * WooCommerce's own screen prints it.
     *
     * @param array $shown     Owners shown open.
     * @param array $fold      Owners in the folded box.
     * @param array $owners    Every owner, for the hidden tab list.
     * @param int   $coupon_id Coupon, 0 for a new one.
     * @return string
     */
    private static function plugin_fields_html(array $shown, array $fold, array $owners, $coupon_id) {
        if (!$shown && !$fold) {
            return '';
        }
        $used_ids = [];
        $edit_url = $coupon_id ? admin_url('post.php?post=' . (int) $coupon_id . '&action=edit') : '';
        $manage   = current_user_can('manage_woocommerce');

        // WooCommerce's tab list, hidden: plugins that fill a panel when its
        // tab is clicked find their tab (the script clicks each one once).
        $tabs = '<ul class="coupon_data_tabs wc-tabs bpc-ext__tabs" aria-hidden="true">';
        foreach (['general' => 'general_coupon_data', 'usage_restriction' => 'usage_restriction_coupon_data', 'usage_limit' => 'usage_limit_coupon_data'] as $tab_key => $target) {
            $tabs .= '<li class="' . esc_attr($tab_key . '_options ' . $tab_key . '_tab') . '"><a href="#' . esc_attr($target) . '" tabindex="-1"></a></li>';
        }
        foreach ($owners as $row) {
            foreach ($row['tabs'] as $tab_key => $tab) {
                $tabs .= '<li class="' . esc_attr(trim($tab_key . '_options ' . $tab_key . '_tab ' . $tab['class'])) . '" data-bpc-plugin-tab="1">'
                    . '<a href="#' . esc_attr($tab['target']) . '" tabindex="-1"><span>' . esc_html($tab['label']) . '</span></a></li>';
            }
        }
        $tabs .= '</ul>';

        $html = $tabs;

        if ($shown) {
            $html .= '<div class="bpc-ext__card">';
            foreach ($shown as $key => $row) {
                $html .= self::owner_group_html($key, $row, $used_ids);
            }
            $foot = [];
            if ($manage) {
                $foot[] = '<button type="button" class="bpc-ext__link bpc-ext__hide">' . esc_html__('Hide these fields', 'brikpanel') . '</button>';
            }
            if ($edit_url) {
                $foot[] = '<a class="bpc-ext__link" href="' . esc_url($edit_url) . '">' . esc_html__('Open in WooCommerce', 'brikpanel') . '</a>';
            }
            if ($foot) {
                $html .= '<p class="bpc-ext__foot">' . implode('', $foot) . '</p>';
            }
            $html .= '</div>';
        }

        if ($fold) {
            $open  = 'yes' === get_user_meta(get_current_user_id(), self::OPEN_META, true);
            $count = count($fold);
            $num   = function_exists('brikpanel_number') ? brikpanel_number($count) : (string) $count;
            $html .= '<div class="bpc-ext__fold' . ($open ? ' is-open' : '') . '"' . ($open ? '' : ' data-held="1"') . '>'
                . '<button type="button" class="bpc-ext__toggle" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="bpc-ext-fold">'
                . '<span class="bpc-ext__toggle-name">' . esc_html__('Fields from your other plugins', 'brikpanel') . '</span>'
                . '<span class="bpc-ext__count">' . esc_html($num) . '</span>'
                . '<svg class="bpc-ext__chev" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                . '</button>'
                . '<div class="bpc-ext__foldbody" id="bpc-ext-fold"' . ($open ? '' : ' hidden') . '>';
            foreach ($fold as $key => $row) {
                $html .= self::owner_group_html($key, $row, $used_ids);
            }
            $foot = [];
            if ($manage) {
                $foot[] = '<button type="button" class="bpc-ext__link bpc-ext__always">' . esc_html__('Always show these fields', 'brikpanel') . '</button>';
            }
            if ($edit_url) {
                $foot[] = '<a class="bpc-ext__link" href="' . esc_url($edit_url) . '">' . esc_html__('Open in WooCommerce', 'brikpanel') . '</a>';
            }
            if ($foot) {
                $html .= '<p class="bpc-ext__foot">' . implode('', $foot) . '</p>';
            }
            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * One owner's part of the window: its name, then what it prints in each
     * of WooCommerce's sections, its own tab panels and its boxes.
     *
     * @param string $key      Owner key.
     * @param array  $row      Owner from collect() with markup.
     * @param array  $used_ids WooCommerce panel ids already given out, by reference.
     * @return string
     */
    private static function owner_group_html($key, array $row, array &$used_ids) {
        $titles = [
            'general'           => __('General', 'brikpanel'),
            'usage_restriction' => __('Usage restrictions', 'brikpanel'),
            'usage_limit'       => __('Usage limits', 'brikpanel'),
        ];
        $out = '<section class="bpc-ext__group" data-owner="' . esc_attr($key) . '">'
            . '<h4 class="bpc-ext__name">' . esc_html($row['name']) . '</h4>';
        foreach ($row['sections'] as $section => $chunks) {
            $target = $section . '_coupon_data';
            $id     = isset($used_ids[$target]) ? '' : ' id="' . esc_attr($target) . '"';
            $used_ids[$target] = true;
            $out .= '<div class="panel woocommerce_options_panel bpc-ext__panel"' . $id . ' data-section="' . esc_attr($section) . '">'
                . '<p class="bpc-ext__where">' . esc_html(isset($titles[$section]) ? $titles[$section] : $section) . '</p>'
                . implode("\n", $chunks)
                . '</div>';
        }
        foreach ($row['panels'] as $panel) {
            $out .= '<div class="bpc-ext__tabpanel">' . $panel . '</div>';
        }
        foreach ($row['boxes'] as $box_id => $box) {
            $out .= '<div id="' . esc_attr($box_id) . '" class="postbox bpc-ext__box">'
                . '<p class="bpc-ext__where">' . esc_html($box['title']) . '</p>'
                . '<div class="inside">' . $box['html'] . '</div>'
                . '</div>';
        }
        return $out . '</section>';
    }

    /**
     * The owners whose fields were on the form, from the window's save: known
     * to draw something on this coupon, so a forged key cannot reach a plugin
     * that has nothing here.
     *
     * @param int    $coupon_id Saved coupon.
     * @param string $json      The posted owner list (JSON), as sent by the window.
     * @return array{owners: string[], boxes: string[]} Owner keys, and those of them with a box.
     */
    private function posted_owners($coupon_id, $json) {
        $none = ['owners' => [], 'boxes' => []];
        if (!is_string($json) || $json === '') {
            return $none;
        }
        $posted = json_decode($json, true);
        if (!is_array($posted)) {
            return $none;
        }
        $keys = [];
        foreach ($posted as $key) {
            if (is_string($key) && preg_match('/^(plugin|theme|mu|other):[a-z0-9_\-]{1,120}$/', $key)) {
                $keys[$key] = true;
            }
        }
        if (!$keys) {
            return $none;
        }
        $owners = self::collect(new WC_Coupon($coupon_id), false);
        $out    = $none;
        foreach ($owners as $key => $row) {
            if (!isset($keys[$key]) || (!$row['sections'] && !$row['panels'] && !$row['boxes'])) {
                continue;
            }
            $out['owners'][] = $key;
            if ($row['boxes']) {
                $out['boxes'][] = $key;
            }
        }
        return $out;
    }

    /**
     * Runs the save of the plugins whose fields were on the form, and only
     * theirs, with what the form posted under the plugins' own field names:
     * WooCommerce's two coupon save hooks, and for a plugin with a box of its
     * own the post save hooks WordPress fires on its screen. WooCommerce's own
     * coupon save is never run (it rebuilds every coupon field from the post),
     * BrikPanel saved those already.
     *
     * @param int    $coupon_id Saved coupon.
     * @param string $json      The posted owner list (JSON).
     * @return string[] Names of the plugins whose save stopped (threw, or called wp_die()).
     */
    private function save_plugin_fields($coupon_id, $json) {
        $coupon_id = (int) $coupon_id;
        if (!$coupon_id || $json === '' || !class_exists('Brikpanel_Foreign_Hooks')) {
            return [];
        }
        $posted = $this->posted_owners($coupon_id, $json);
        if (!$posted['owners']) {
            return [];
        }

        $post   = get_post($coupon_id);
        $failed = [];
        $failed = array_merge($failed, Brikpanel_Foreign_Hooks::dispatch_only('woocommerce_process_shop_coupon_meta', [$coupon_id, $post], $posted['owners']));
        $failed = array_merge($failed, Brikpanel_Foreign_Hooks::dispatch_only('woocommerce_coupon_options_save', [$coupon_id, new WC_Coupon($coupon_id)], $posted['owners']));
        if ($posted['boxes']) {
            $post = get_post($coupon_id);
            $failed = array_merge($failed, Brikpanel_Foreign_Hooks::dispatch_only('save_post_shop_coupon', [$coupon_id, $post, true], $posted['boxes']));
            $failed = array_merge($failed, Brikpanel_Foreign_Hooks::dispatch_only('save_post', [$coupon_id, $post, true], $posted['boxes']));
            $failed = array_merge($failed, Brikpanel_Foreign_Hooks::dispatch_only('edit_post', [$coupon_id, $post], $posted['boxes']));
        }
        return array_values(array_unique($failed));
    }

    /**
     * Remembers whether this person keeps the folded "Fields from your other
     * plugins" box open. Opened once, it stays open on every coupon for them.
     */
    public function ajax_fold_state() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }
        $open = isset($_POST['open']) && is_string($_POST['open']) && '1' === sanitize_key(wp_unslash($_POST['open']));
        if ($open) {
            update_user_meta(get_current_user_id(), self::OPEN_META, 'yes');
        } else {
            delete_user_meta(get_current_user_id(), self::OPEN_META);
        }
        wp_send_json_success();
    }

    /**
     * "Always show these fields": turns on "Auto-show fields other plugins
     * add", or puts back the state "Hide these fields" parked (the switch, or
     * a hand-picked list). Same as the product editor's link.
     */
    public function ajax_enable_plugin_fields() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }
        $parked   = get_option(self::PARKED_OPTION, []);
        $auto     = (is_array($parked) && isset($parked['auto'])) ? (string) $parked['auto'] : '';
        $selected = (is_array($parked) && isset($parked['selected'])) ? array_values(array_map('strval', (array) $parked['selected'])) : [];
        if ('yes' === $auto || $selected) {
            update_option(self::AUTO_OPTION, 'yes' === $auto ? 'yes' : 'no');
            update_option(self::SELECTED_OPTION, $selected);
        } else {
            update_option(self::AUTO_OPTION, 'yes');
        }
        delete_option(self::PARKED_OPTION);
        wp_send_json_success();
    }

    /**
     * "Hide these fields": parks what was on (not autoloaded) and turns both
     * settings off, so "Always show these fields" can put it back.
     */
    public function ajax_disable_plugin_fields() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }
        $auto     = (string) get_option(self::AUTO_OPTION, 'no');
        $selected = (array) get_option(self::SELECTED_OPTION, []);
        if ('yes' === $auto || $selected) {
            update_option(self::PARKED_OPTION, ['auto' => $auto, 'selected' => $selected], false);
        }
        update_option(self::SELECTED_OPTION, []);
        update_option(self::AUTO_OPTION, 'no');
        wp_send_json_success();
    }

    /**
     * Copies the values of other plugins' fields from one coupon to its
     * duplicate: the stored values under the names their fields post (and the
     * same name with a leading underscore, a common way to store them).
     * Nothing else is copied, a plugin's own counters stay with the original.
     *
     * @param int $from Original coupon.
     * @param int $to   Its copy.
     * @return void
     */
    private static function copy_plugin_field_values($from, $to) {
        $names = [];
        foreach (self::collect(new WC_Coupon($from), true) as $row) {
            $markup = '';
            foreach ($row['sections'] as $chunks) {
                $markup .= implode('', $chunks);
            }
            $markup .= implode('', $row['panels']);
            foreach ($row['boxes'] as $box) {
                $markup .= $box['html'];
            }
            if (preg_match_all('/\bname\s*=\s*(["\'])([^"\'\[\]]+)/i', $markup, $m)) {
                foreach ($m[2] as $name) {
                    $names[$name] = true;
                }
            }
        }
        $skip = array_flip(array_merge(self::OWN_KEYS, [
            'coupon_amount', 'customer_email', 'exclude_product_ids', 'product_categories', 'exclude_product_categories',
            'usage_count', 'date_expires', '_used_by', '_edit_lock', '_edit_last', 'post_ID', '_wpnonce', 'woocommerce_meta_nonce',
        ]));
        foreach (array_keys($names) as $name) {
            foreach ([$name, '_' . $name] as $meta_key) {
                if (isset($skip[$meta_key]) || !metadata_exists('post', $from, $meta_key) || metadata_exists('post', $to, $meta_key)) {
                    continue;
                }
                foreach (get_post_meta($from, $meta_key, false) as $value) {
                    add_post_meta($to, $meta_key, wp_slash($value));
                }
            }
        }
    }

    // =========================================================================
    // AJAX: DELETE COUPON
    // =========================================================================

    public function ajax_delete_coupon() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        $permanent = sanitize_key($_POST['permanent'] ?? 'no') === 'yes';

        if (!$coupon_id) {
            wp_send_json_error(['message' => __('Invalid coupon ID.', 'brikpanel')]);
        }

        $post = get_post($coupon_id);
        if (!$post || $post->post_type !== 'shop_coupon') {
            wp_send_json_error(['message' => __('Coupon not found.', 'brikpanel')]);
        }

        if ($permanent) {
            wp_delete_post($coupon_id, true);
            wp_send_json_success(['message' => __('Coupon permanently deleted.', 'brikpanel')]);
        } else {
            wp_trash_post($coupon_id);
            wp_send_json_success(['message' => __('Coupon moved to trash.', 'brikpanel')]);
        }
    }

    // =========================================================================
    // AJAX: TOGGLE COUPON STATUS
    // =========================================================================

    public function ajax_toggle_coupon_status() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        if (!$coupon_id) {
            wp_send_json_error(['message' => __('Invalid coupon ID.', 'brikpanel')]);
        }

        $post = get_post($coupon_id);
        if (!$post || $post->post_type !== 'shop_coupon') {
            wp_send_json_error(['message' => __('Coupon not found.', 'brikpanel')]);
        }

        $new_status = ($post->post_status === 'publish') ? 'draft' : 'publish';

        wp_update_post([
            'ID'          => $coupon_id,
            'post_status' => $new_status,
        ]);

        $label = $new_status === 'publish' ? __('Published', 'brikpanel') : __('Draft', 'brikpanel');

        wp_send_json_success([
            'status'  => $new_status,
            'message' => sprintf(
                /* translators: %s: status label */
                __('Coupon status changed to %s.', 'brikpanel'),
                $label
            ),
        ]);
    }

    // =========================================================================
    // AJAX: DUPLICATE COUPON
    // =========================================================================

    public function ajax_duplicate_coupon() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        if (!$coupon_id) {
            wp_send_json_error(['message' => __('Invalid coupon ID.', 'brikpanel')]);
        }

        $original = new WC_Coupon($coupon_id);
        if (!$original->get_id()) {
            wp_send_json_error(['message' => __('Coupon not found.', 'brikpanel')]);
        }

        $new_coupon = new WC_Coupon();

        // Generate unique code
        $base_code = $original->get_code();
        $new_code  = $base_code . '-copy';
        $suffix    = 1;
        while (wc_get_coupon_id_by_code($new_code)) {
            $suffix++;
            $new_code = $base_code . '-copy-' . $suffix;
        }

        $new_coupon->set_code($new_code);
        $new_coupon->set_description($original->get_description());
        $new_coupon->set_discount_type($original->get_discount_type());
        $new_coupon->set_amount($original->get_amount());
        $new_coupon->set_free_shipping($original->get_free_shipping());
        $new_coupon->set_date_expires($original->get_date_expires() ? $original->get_date_expires()->getTimestamp() : null);
        $new_coupon->set_minimum_amount($original->get_minimum_amount());
        $new_coupon->set_maximum_amount($original->get_maximum_amount());
        $new_coupon->set_individual_use($original->get_individual_use());
        $new_coupon->set_exclude_sale_items($original->get_exclude_sale_items());
        $new_coupon->set_usage_limit($original->get_usage_limit());
        $new_coupon->set_usage_limit_per_user($original->get_usage_limit_per_user());
        $new_coupon->set_product_ids($original->get_product_ids());
        $new_coupon->set_excluded_product_ids($original->get_excluded_product_ids());
        $new_coupon->set_product_categories($original->get_product_categories());
        $new_coupon->set_excluded_product_categories($original->get_excluded_product_categories());
        $new_coupon->set_email_restrictions($original->get_email_restrictions());
        $new_coupon->set_limit_usage_to_x_items($original->get_limit_usage_to_x_items());
        if (class_exists('Brikpanel_Auto_Coupons')) {
            foreach ([Brikpanel_Auto_Coupons::META_AUTO, Brikpanel_Auto_Coupons::META_MIN, Brikpanel_Auto_Coupons::META_MAX] as $key) {
                $value = $original->get_meta($key, true, 'edit');
                if ('' !== $value && null !== $value) {
                    $new_coupon->update_meta_data($key, $value);
                }
            }
        }
        brikpanel_wc_coupon_set_status($new_coupon, 'draft');

        $new_coupon->save();

        // The copy keeps what other plugins' fields hold too (a theme's
        // "Loyalty tier", B2BKing's role), as a copy made by hand would.
        if ($new_coupon->get_id()) {
            self::copy_plugin_field_values($original->get_id(), $new_coupon->get_id());
        }

        // A duplicate must never go live on its own. On a WooCommerce without
        // the setter the copy is created as 'publish', so this is the call that
        // actually makes it a draft there.
        brikpanel_wc_coupon_sync_status($new_coupon->get_id(), 'draft');

        wp_send_json_success([
            'message'   => __('Coupon duplicated!', 'brikpanel'),
            'coupon_id' => $new_coupon->get_id(),
        ]);
    }

    // =========================================================================
    // AJAX: SEARCH PRODUCTS (for advanced restriction fields)
    // =========================================================================

    public function ajax_search_products() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $term = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        if (strlen($term) < 2) {
            wp_send_json_success(['items' => []]);
        }

        // Use WooCommerce's data store search when available so SKU lookups
        // work like the native product search; fall back to WP_Query.
        $ids = [];
        if (function_exists('wc_get_container')) {
            $data_store = WC_Data_Store::load('product');
            if (method_exists($data_store, 'search_products')) {
                $ids = $data_store->search_products($term, '', false, false, 20);
                $ids = array_filter(array_map('intval', $ids));
            }
        }

        if (empty($ids)) {
            $query = new WP_Query([
                'post_type'      => ['product'],
                'post_status'    => 'publish',
                's'              => $term,
                'posts_per_page' => 20,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            $ids = $query->posts;
        }

        $items = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) {
                continue;
            }
            $items[] = [
                'value' => (int) $pid,
                'label' => $this->format_product_label($product),
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    // =========================================================================
    // AJAX: SEARCH CATEGORIES
    // =========================================================================

    public function ajax_search_categories() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $term = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        if (strlen($term) < 1) {
            wp_send_json_success(['items' => []]);
        }

        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'name__like' => $term,
            'number'     => 30,
        ]);

        if (is_wp_error($terms)) {
            wp_send_json_success(['items' => []]);
        }

        $items = [];
        foreach ($terms as $t) {
            $items[] = [
                'value' => (int) $t->term_id,
                'label' => brikpanel_plain_name($t->name),
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    // =========================================================================
    // AJAX: SEARCH USERS (for the "Allowed emails" picker)
    // =========================================================================

    /**
     * Searches registered users by display name, username or email so the
     * coupon drawer can restrict a coupon to real accounts without the store
     * owner having to remember and retype email addresses.
     */
    public function ajax_search_users() {
        check_ajax_referer('brikpanel_coupons_nonce', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'brikpanel')]);
        }

        $term = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        if (strlen($term) < 2) {
            wp_send_json_success(['items' => []]);
        }

        /**
         * Filters the user query used by the coupon "Allowed emails" picker,
         * e.g. to limit suggestions to the customer role only.
         *
         * @param array  $args WP_User_Query arguments.
         * @param string $term Search term.
         */
        $args = apply_filters('brikpanel_coupons_user_search_args', [
            'search'         => '*' . $term . '*',
            'search_columns' => ['user_login', 'user_email', 'user_nicename', 'display_name'],
            'number'         => 20,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
            'fields'         => ['ID', 'user_login', 'user_email', 'display_name'],
        ], $term);

        $users = (new WP_User_Query($args))->get_results();

        $items = [];
        foreach ($users as $user) {
            if (empty($user->user_email)) {
                continue;
            }
            $items[] = [
                'value' => $user->user_email,
                'label' => $this->format_user_label($user->display_name, $user->user_login),
                'sub'   => $user->user_email,
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Cached email => user row map, filled by prime_user_email_cache().
     *
     * @var array<string,object|null>
     */
    private $user_email_cache = [];

    /**
     * Resolves a batch of email addresses to registered users in a single query.
     *
     * @param string[] $emails Raw email addresses (wildcards are ignored).
     */
    private function prime_user_email_cache(array $emails) {
        $lookup = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || strpos($email, '*') !== false) {
                continue;
            }
            if (!array_key_exists($email, $this->user_email_cache)) {
                $lookup[$email] = true;
            }
        }

        if (empty($lookup)) {
            return;
        }

        global $wpdb;

        $list         = array_keys($lookup);
        $placeholders = implode(',', array_fill(0, count($list), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_login, user_email, display_name
             FROM {$wpdb->users}
             WHERE user_email IN ($placeholders)",
            ...$list
        ));

        // Default everything to "not a registered user", then fill in the hits.
        foreach ($list as $email) {
            $this->user_email_cache[$email] = null;
        }
        foreach ($rows as $row) {
            $this->user_email_cache[strtolower($row->user_email)] = $row;
        }
    }

    /**
     * Builds {value,label,sub} token descriptors for a coupon's email
     * restrictions. Registered users get their name and username as the label;
     * plain addresses and wildcards fall back to the address itself.
     *
     * @param string[] $emails
     * @return array<int,array<string,string>>
     */
    private function build_email_labels(array $emails) {
        if (empty($emails)) {
            return [];
        }

        $this->prime_user_email_cache($emails);

        $labels = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }
            $user = $this->user_email_cache[$email] ?? null;
            if ($user) {
                $labels[] = [
                    'value' => $email,
                    'label' => $this->format_user_label($user->display_name, $user->user_login),
                    'sub'   => $email,
                ];
                continue;
            }
            $labels[] = [
                'value' => $email,
                'label' => $email,
                'sub'   => '',
            ];
        }

        return $labels;
    }

    /**
     * "Jane Doe (jane)" when the username adds information, otherwise just the
     * name. Keeps the picker readable when display name and login are the same.
     */
    private function format_user_label($display_name, $user_login) {
        // Display names are stored HTML-encoded; the picker writes text.
        $display_name = trim(brikpanel_plain_name((string) $display_name));
        $user_login   = trim((string) $user_login);

        if ($display_name === '') {
            return $user_login;
        }
        if ($user_login === '' || strcasecmp($display_name, $user_login) === 0) {
            return $display_name;
        }

        /* translators: 1: user display name, 2: username */
        return sprintf(_x('%1$s (%2$s)', 'user display name (username)', 'brikpanel'), $display_name, $user_login);
    }

    private function sanitize_id_list($raw) {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $v) {
            $v = (int) $v;
            if ($v > 0) {
                $ids[] = $v;
            }
        }
        return array_values(array_unique($ids));
    }

    private function sanitize_email_list($raw) {
        // Accepts both the comma-separated string the token picker posts and a
        // plain array, so programmatic callers keep working.
        if (is_array($raw)) {
            $raw = implode(',', array_map('strval', $raw));
        }
        if (!is_string($raw)) {
            return [];
        }
        $parts = preg_split('/[\s,]+/', $raw);
        $emails = [];
        foreach ($parts as $email) {
            $email = trim(strtolower($email));
            if ($email === '') {
                continue;
            }
            // Allow wildcard emails like *@example.com (WooCommerce supports them).
            if (strpos($email, '*') !== false) {
                if (preg_match('/^[a-z0-9._%+\-\*]+@[a-z0-9.\-\*]+\.[a-z*]{2,}$/i', $email)) {
                    $emails[] = $email;
                }
                continue;
            }
            if (is_email($email)) {
                $emails[] = sanitize_email($email);
            }
        }
        return array_values(array_unique(array_filter($emails)));
    }

    private function build_product_labels(array $ids) {
        if (empty($ids)) {
            return [];
        }
        $labels = [];
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) {
                // Product was deleted — still expose the ID so it can be removed
                // from the coupon without losing the rest of the selection.
                $labels[] = [
                    'value' => (int) $pid,
                    /* translators: %d: product ID */
                    'label' => sprintf(__('(deleted product #%d)', 'brikpanel'), (int) $pid),
                ];
                continue;
            }
            $labels[] = [
                'value' => (int) $pid,
                'label' => $this->format_product_label($product),
            ];
        }
        return $labels;
    }

    private function build_category_labels(array $ids) {
        if (empty($ids)) {
            return [];
        }
        $labels = [];
        foreach ($ids as $tid) {
            $term = get_term((int) $tid, 'product_cat');
            if (!$term || is_wp_error($term)) {
                $labels[] = [
                    'value' => (int) $tid,
                    /* translators: %d: category ID */
                    'label' => sprintf(__('(deleted category #%d)', 'brikpanel'), (int) $tid),
                ];
                continue;
            }
            $labels[] = [
                'value' => (int) $tid,
                'label' => brikpanel_plain_name($term->name),
            ];
        }
        return $labels;
    }

    private function format_product_label($product) {
        // Stored names can hold "&amp;" and, for variations, markup.
        $name = brikpanel_plain_label($product->get_name());
        $sku  = $product->get_sku();
        if ($sku) {
            return $name . ' (' . $sku . ')';
        }
        return $name . ' (#' . $product->get_id() . ')';
    }
}

new Brikpanel_Coupons();

/**
 * Tell Import / Export about the coupon window's "other plugins' fields"
 * state. Internal, not portable: the parked option is the undo of one "Hide
 * these fields" click on one site (deleted when "Always show these fields"
 * uses it), and the per-person "folded box kept open" is a habit of one
 * account on one site.
 *
 * @param array $map Registry so far.
 * @return array
 */
add_filter('brikpanel_exportable_option_keys', 'brikpanel_cp_register_internal_export_keys');
function brikpanel_cp_register_internal_export_keys($map) {
    $map[Brikpanel_Coupons::PARKED_OPTION] = ['class' => 'internal'];
    $map[Brikpanel_Coupons::OPEN_META]     = ['class' => 'internal'];
    return $map;
}
