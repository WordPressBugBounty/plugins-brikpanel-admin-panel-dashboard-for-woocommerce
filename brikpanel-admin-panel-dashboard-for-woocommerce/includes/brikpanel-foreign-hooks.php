<?php
/**
 * BrikPanel - Other plugins' hooks on WooCommerce screens BrikPanel replaces.
 *
 * Generic parts for a BrikPanel screen that shows what other plugins and the
 * theme add to a WooCommerce screen (the coupon window first,
 * front-end/coupons/brikpanel-coupons.php):
 *
 *  - who a hook callback belongs to (a plugin, the theme, a must-use plugin),
 *  - firing a hook one callback at a time, so one plugin that fails cannot
 *    hide the next one, and the output buffer stack is left as it was found,
 *  - running a hook for a chosen set of owners only, so a save never reaches a
 *    plugin whose fields were not on the form ("absent means delete" handlers,
 *    B2BKing's coupon role, ShopBuilder Pro's switches).
 *
 * Ported from the product editor (front-end/products/brikpanel-product-editor.php:
 * capture_isolated_hook(), capture_hook_chunks(), filter_chunks(),
 * dispatch_foreign_hooks()), which keeps its own copies.
 *
 * @package BrikPanel
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Brikpanel_Foreign_Hooks {

    /**
     * The file that declares a hook callback, real path, '' when unknown.
     *
     * @param mixed $function A WP_Hook callback.
     * @return string
     */
    public static function callback_file($function) {
        try {
            if (is_string($function) && strpos($function, '::') !== false) {
                $function = explode('::', $function, 2);
            }
            if ($function instanceof \Closure || (is_string($function) && function_exists($function))) {
                $ref = new \ReflectionFunction($function);
            } elseif (is_array($function) && count($function) === 2) {
                $ref = new \ReflectionMethod(is_object($function[0]) ? get_class($function[0]) : (string) $function[0], (string) $function[1]);
            } elseif (is_object($function) && method_exists($function, '__invoke')) {
                $ref = new \ReflectionMethod($function, '__invoke');
            } else {
                return '';
            }
            $file = (string) $ref->getFileName();
        } catch (\Throwable $e) {
            return '';
        }
        return $file === '' ? '' : wp_normalize_path((string) (realpath($file) ?: $file));
    }

    /**
     * Who a callback belongs to.
     *
     * @param mixed $function A WP_Hook callback.
     * @return array{key: string, name: string, dir: string}|null Null for
     *         WordPress itself, WooCommerce and BrikPanel: they are never
     *         "another plugin".
     */
    public static function owner($function) {
        $file = self::callback_file($function);
        return $file === '' ? null : self::owner_of_file($file);
    }

    /**
     * Who a file belongs to, see owner(). The key is a fixed slug
     * (`plugin:<folder>`, `theme:<folder>`, `mu:<file>`, `other:<hash>`), never
     * a translated name: settings store it.
     *
     * @param string $file Real, normalised path.
     * @return array{key: string, name: string, dir: string}|null
     */
    public static function owner_of_file($file) {
        static $memo = [];
        if (isset($memo[$file])) {
            return $memo[$file] ?: null;
        }
        $memo[$file] = false;

        foreach (self::own_dirs() as $dir) {
            if (strpos($file, $dir) === 0) {
                return null;
            }
        }

        foreach (self::dir_variants(defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : '') as $base) {
            if (strpos($file, $base) !== 0) {
                continue;
            }
            $rest  = substr($file, strlen($base));
            $slash = strpos($rest, '/');
            $first = $slash === false ? $rest : substr($rest, 0, $slash);
            $slug  = preg_replace('/\.php$/', '', $first);
            $name  = self::mu_plugin_name($first);
            if ($name === $first) {
                $name = ''; // WordPress names a header-less file after itself
            }
            return self::remember($memo, $file, 'mu:' . sanitize_key($slug), $name !== '' ? $name : $slug, $base . $first);
        }

        foreach (self::dir_variants(defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '') as $base) {
            if (strpos($file, $base) !== 0) {
                continue;
            }
            $rest  = substr($file, strlen($base));
            $slash = strpos($rest, '/');
            $slug  = $slash === false ? preg_replace('/\.php$/', '', $rest) : substr($rest, 0, $slash);
            $name  = self::plugin_name($slash === false ? $rest : $slug);
            return self::remember($memo, $file, 'plugin:' . sanitize_key($slug), $name !== '' ? $name : $slug, $base . ($slash === false ? $rest : $slug . '/'));
        }

        if (function_exists('get_theme_root')) {
            foreach (array_unique([get_theme_root(), defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/themes' : '']) as $root) {
                foreach (self::dir_variants((string) $root) as $base) {
                    if (strpos($file, $base) !== 0) {
                        continue;
                    }
                    $rest  = substr($file, strlen($base));
                    $slash = strpos($rest, '/');
                    if ($slash === false) {
                        continue;
                    }
                    $slug  = substr($rest, 0, $slash);
                    $theme = wp_get_theme($slug);
                    $name  = $theme->exists() ? (string) $theme->get('Name') : '';
                    return self::remember($memo, $file, 'theme:' . sanitize_key($slug), $name !== '' ? $name : $slug, $base . $slug . '/');
                }
            }
        }

        // WordPress itself (wp-includes, wp-admin) is never another plugin.
        $abspath = wp_normalize_path((string) (realpath(ABSPATH) ?: ABSPATH));
        $abspath = trailingslashit($abspath);
        if (strpos($file, $abspath . 'wp-includes/') === 0 || strpos($file, $abspath . 'wp-admin/') === 0) {
            return null;
        }

        // Code loaded from anywhere else (a drop-in, a custom folder).
        return self::remember($memo, $file, 'other:' . substr(md5(dirname($file)), 0, 10), basename(dirname($file)), trailingslashit(dirname($file)));
    }

    /**
     * Fire an action and return what it printed, leaving the output buffer
     * stack exactly as deep as it was found and swallowing a Throwable.
     *
     * Plugins sometimes open a buffer on one WooCommerce hook and close it on a
     * later one; fired alone, half of such a pair would leak a buffer or eat
     * one of ours. A spare guard buffer absorbs one stray close, and the stack
     * is unwound back to where it started (capture_isolated_hook() in the
     * product editor).
     *
     * @param string $hook Action.
     * @param array  $args Positional args.
     * @return array{0: string, 1: bool} What it printed, and whether it threw.
     */
    public static function capture($hook, array $args = []) {
        $base  = ob_get_level();
        $threw = false;
        ob_start(); // capture
        ob_start(); // guard, absorbs a stray ob_get_clean()
        try {
            self::die_guard(static function () use ($hook, $args) {
                do_action_ref_array($hook, $args);
            });
        } catch (\Throwable $e) {
            $threw = true;
        }
        while (ob_get_level() < $base) {
            ob_start();
        }
        $html = '';
        while (ob_get_level() > $base) {
            $html = ob_get_clean() . $html;
        }
        return [trim($html), $threw];
    }

    /**
     * Fire an action one callback at a time and return what each printed,
     * with its owner. WordPress', WooCommerce's and BrikPanel's own callbacks
     * are not fired. A callback that throws is reported and the rest still run.
     *
     * Each callback runs on a temporarily narrowed hook, so current_filter()
     * and the priority order stay as WordPress presents them; the hook is put
     * back in `finally`.
     *
     * @param string $hook Action.
     * @param array  $args Positional args.
     * @return array<int, array{owner: array, html: string, threw: bool}> In hook order; empty output is left out unless it threw.
     */
    public static function chunks($hook, array $args = []) {
        global $wp_filter;
        $out = [];
        if (empty($wp_filter[$hook]) || !($wp_filter[$hook] instanceof \WP_Hook)) {
            return $out;
        }
        $original = $wp_filter[$hook];
        try {
            foreach ($original->callbacks as $priority => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!isset($entry['function'])) {
                        continue;
                    }
                    $owner = self::owner($entry['function']);
                    if ($owner === null) {
                        continue;
                    }
                    $solo = new \WP_Hook();
                    $solo->add_filter($hook, $entry['function'], $priority, isset($entry['accepted_args']) ? (int) $entry['accepted_args'] : 1);
                    $wp_filter[$hook] = $solo;
                    list($html, $threw) = self::capture($hook, $args);
                    if ($html !== '' || $threw) {
                        $out[] = ['owner' => $owner, 'html' => $html, 'threw' => $threw];
                    }
                }
            }
        } finally {
            $wp_filter[$hook] = $original;
        }
        return $out;
    }

    /**
     * What each callback of a filter adds to a list on its own: the keys it
     * returns that `$base` does not have. For a list such as the coupon
     * discount types or the coupon data tabs, this tells which plugin added
     * which entry. A callback that throws is reported as a thrower.
     *
     * @param string $filter Filter.
     * @param array  $base   The list the filter starts from.
     * @return array{added: array<string, array{owner: array, entries: array}>, throwers: array<string, array>}
     */
    public static function filter_additions($filter, array $base) {
        global $wp_filter;
        $added    = [];
        $throwers = [];
        if (empty($wp_filter[$filter]) || !($wp_filter[$filter] instanceof \WP_Hook)) {
            return ['added' => $added, 'throwers' => $throwers];
        }
        $original = $wp_filter[$filter];
        try {
            foreach ($original->callbacks as $priority => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!isset($entry['function'])) {
                        continue;
                    }
                    $owner = self::owner($entry['function']);
                    if ($owner === null) {
                        continue;
                    }
                    $solo = new \WP_Hook();
                    $solo->add_filter($filter, $entry['function'], $priority, isset($entry['accepted_args']) ? (int) $entry['accepted_args'] : 1);
                    $wp_filter[$filter] = $solo;
                    try {
                        $result = self::die_guard(static function () use ($filter, $base) {
                            return apply_filters($filter, $base);
                        });
                    } catch (\Throwable $e) {
                        $throwers[$owner['key']] = $owner;
                        continue;
                    }
                    if (!is_array($result)) {
                        continue;
                    }
                    $new = array_diff_key($result, $base);
                    if (!$new) {
                        continue;
                    }
                    if (!isset($added[$owner['key']])) {
                        $added[$owner['key']] = ['owner' => $owner, 'entries' => []];
                    }
                    $added[$owner['key']]['entries'] += $new;
                }
            }
        } finally {
            $wp_filter[$filter] = $original;
        }
        return ['added' => $added, 'throwers' => $throwers];
    }

    /**
     * Run an action for the callbacks of the given owners only.
     *
     * The rest of the hook is not detached and re-added (which would change
     * the order of equal-priority callbacks for the rest of the request): the
     * hook object is swapped for a narrowed copy and put back in `finally`.
     *
     * @param string   $hook       Action.
     * @param array    $args       Positional args.
     * @param string[] $owner_keys Owner keys from owner().
     * @return string[] Names of the owners whose callback threw or called wp_die().
     */
    public static function dispatch_only($hook, array $args, array $owner_keys) {
        global $wp_filter;
        $failed = [];
        if (!$owner_keys || empty($wp_filter[$hook]) || !($wp_filter[$hook] instanceof \WP_Hook)) {
            return $failed;
        }
        $original = $wp_filter[$hook];
        try {
            foreach ($original->callbacks as $priority => $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!isset($entry['function'])) {
                        continue;
                    }
                    $owner = self::owner($entry['function']);
                    if ($owner === null || !in_array($owner['key'], $owner_keys, true)) {
                        continue;
                    }
                    // One callback at a time, like WordPress would call it: one
                    // plugin's failure must not stop the next plugin's save.
                    $solo = new \WP_Hook();
                    $solo->add_filter($hook, $entry['function'], $priority, isset($entry['accepted_args']) ? (int) $entry['accepted_args'] : 1);
                    $wp_filter[$hook] = $solo;
                    try {
                        self::die_guard(static function () use ($hook, $args) {
                            do_action_ref_array($hook, $args);
                        });
                    } catch (\Throwable $e) {
                        $failed[$owner['key']] = $owner['name'];
                        error_log(sprintf('[BrikPanel] %s stopped on %s: %s', $owner['name'], $hook, $e->getMessage()));
                    } finally {
                        $wp_filter[$hook] = $original;
                    }
                }
            }
        } finally {
            $wp_filter[$hook] = $original;
        }
        return array_values($failed);
    }

    /**
     * Run a callable with wp_die() turned into an exception, so another
     * plugin calling wp_die() (or wp_send_json_*() inside an AJAX request)
     * cannot end BrikPanel's request halfway.
     *
     * @param callable $fn Work.
     * @return mixed What $fn returned.
     * @throws \Throwable What $fn threw, or a RuntimeException for wp_die().
     */
    public static function die_guard(callable $fn) {
        $to_exception = static function () {
            return static function ($message = '') {
                throw new \RuntimeException(esc_html('wp_die(): ' . (is_scalar($message) ? (string) $message : gettype($message))));
            };
        };
        add_filter('wp_die_ajax_handler', $to_exception, PHP_INT_MAX);
        add_filter('wp_die_json_handler', $to_exception, PHP_INT_MAX);
        add_filter('wp_die_handler', $to_exception, PHP_INT_MAX);
        try {
            return $fn();
        } finally {
            remove_filter('wp_die_ajax_handler', $to_exception, PHP_INT_MAX);
            remove_filter('wp_die_json_handler', $to_exception, PHP_INT_MAX);
            remove_filter('wp_die_handler', $to_exception, PHP_INT_MAX);
        }
    }

    /**
     * WooCommerce's and BrikPanel's folders, real and as reached, with a
     * trailing slash.
     *
     * @return string[]
     */
    private static function own_dirs() {
        static $dirs = null;
        if ($dirs === null) {
            $dirs = [];
            if (defined('WC_ABSPATH')) {
                $dirs = array_merge($dirs, self::dir_variants(WC_ABSPATH));
            }
            if (defined('BRIKPANEL_PATH')) {
                $dirs = array_merge($dirs, self::dir_variants(BRIKPANEL_PATH));
            }
            $dirs = array_values(array_unique($dirs));
        }
        return $dirs;
    }

    /**
     * A folder as given and as its real path, normalised, trailing slash.
     *
     * @param string $dir Folder.
     * @return string[]
     */
    private static function dir_variants($dir) {
        if ($dir === '') {
            return [];
        }
        $out = [trailingslashit(wp_normalize_path($dir))];
        $real = realpath($dir);
        if ($real) {
            $out[] = trailingslashit(wp_normalize_path($real));
        }
        return array_values(array_unique($out));
    }

    /**
     * @param string $file Main file relative to the plugins folder, or the folder.
     * @return string The plugin's name from its header, '' when unknown.
     */
    private static function plugin_name($file) {
        static $by_folder = null;
        if ($by_folder === null) {
            $by_folder = [];
            if (!function_exists('get_plugins') && is_readable(ABSPATH . 'wp-admin/includes/plugin.php')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if (function_exists('get_plugins')) {
                foreach ((array) get_plugins() as $main => $data) {
                    $folder = strpos($main, '/') !== false ? substr($main, 0, strpos($main, '/')) : $main;
                    if (!isset($by_folder[$folder]) && !empty($data['Name'])) {
                        $by_folder[$folder] = (string) $data['Name'];
                    }
                }
            }
        }
        return isset($by_folder[$file]) ? $by_folder[$file] : '';
    }

    /**
     * @param string $file File or folder name inside the must-use plugins folder.
     * @return string The plugin's name from its header, '' when unknown.
     */
    private static function mu_plugin_name($file) {
        static $names = null;
        if ($names === null) {
            $names = [];
            if (!function_exists('get_mu_plugins') && is_readable(ABSPATH . 'wp-admin/includes/plugin.php')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if (function_exists('get_mu_plugins')) {
                foreach ((array) get_mu_plugins() as $main => $data) {
                    if (!empty($data['Name'])) {
                        $names[$main] = (string) $data['Name'];
                    }
                }
            }
        }
        return isset($names[$file]) ? $names[$file] : '';
    }

    /**
     * @param array  $memo Owner memo, by reference.
     * @param string $file File.
     * @param string $key  Owner key.
     * @param string $name Owner name.
     * @param string $dir  Owner folder (or file).
     * @return array{key: string, name: string, dir: string}
     */
    private static function remember(array &$memo, $file, $key, $name, $dir) {
        $memo[$file] = ['key' => $key, 'name' => wp_strip_all_tags((string) $name), 'dir' => (string) $dir];
        return $memo[$file];
    }
}
