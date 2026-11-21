/**
 * BrikPanel - AJAX Coupons List
 * @package BrikPanel
 * @since 1.8.0
 */
(function ($) {
    'use strict';

    var CP = window.brikpanelCP || {};

    var state = {
        page: 1,
        per_page: CP.per_page || 20,
        search: '',
        status: 'any',
        sort: 'date-desc',
        selected: [],
        coupons: [],
        loading: false,
        total: 0,
        pages: 0,
        extraColumns: {},
        // Token-picker selections keyed by target ('products', 'exclude_products',
        // 'categories', 'exclude_categories', 'emails'). Each value is an array
        // of {value, label, sub} objects.
        tokens: {
            products: [],
            exclude_products: [],
            categories: [],
            exclude_categories: [],
            emails: []
        }
    };

    var searchTimer = null;
    var tokenSearchTimers = {};

    // Matches a plain address as well as WooCommerce wildcards (*@example.com).
    var EMAIL_RE = /^[a-z0-9._%+\-*]+@[a-z0-9.\-*]+\.[a-z*]{2,}$/i;

    // Map of token-target -> config (which AJAX action to call and which DOM ids
    // to use). 'string' targets keep their value as text (an email) instead of
    // an integer ID; 'free' targets also accept values typed by hand.
    var TOKEN_TARGETS = {
        products:           { action: 'brikpanel_coupons_search_products',   tokensId: 'bpc-products-tokens',           inputId: 'bpc-products-search',           sugId: 'bpc-products-suggestions' },
        exclude_products:   { action: 'brikpanel_coupons_search_products',   tokensId: 'bpc-exclude-products-tokens',   inputId: 'bpc-exclude-products-search',   sugId: 'bpc-exclude-products-suggestions' },
        categories:         { action: 'brikpanel_coupons_search_categories', tokensId: 'bpc-categories-tokens',         inputId: 'bpc-categories-search',         sugId: 'bpc-categories-suggestions' },
        exclude_categories: { action: 'brikpanel_coupons_search_categories', tokensId: 'bpc-exclude-categories-tokens', inputId: 'bpc-exclude-categories-search', sugId: 'bpc-exclude-categories-suggestions' },
        emails:             { action: 'brikpanel_coupons_search_users',      tokensId: 'bpc-emails-tokens',             inputId: 'bpc-emails-search',             sugId: 'bpc-emails-suggestions', string: true, free: true }
    };

    function isFieldEnabled(name) {
        return !!(CP.enabled_fields && CP.enabled_fields[name]);
    }

    // =========================================================================
    // INIT
    // =========================================================================

    function bindEvents() {
        // Search
        $('#bpc-search').on('input', function () {
            clearTimeout(searchTimer);
            var val = $(this).val();
            searchTimer = setTimeout(function () {
                state.search = val;
                state.page = 1;
                fetchCoupons();
            }, 350);
        });

        // Status tabs
        $(document).on('click', '.brikpanel-cp-tab', function () {
            $('.brikpanel-cp-tab').removeClass('active');
            $(this).addClass('active');
            state.status = $(this).data('status');
            state.page = 1;
            fetchCoupons();
        });

        // Sort
        $('#bpc-sort').on('change', function () {
            state.sort = $(this).val();
            state.page = 1;
            fetchCoupons();
        });

        // Check all
        $('#bpc-check-all').on('change', function () {
            var checked = this.checked;
            $('.brikpanel-cp-row-check').prop('checked', checked);
            if (checked) {
                state.selected = state.coupons.map(function (c) { return c.id; });
            } else {
                state.selected = [];
            }
            updateBulkBar();
        });

        // Row checkbox
        $(document).on('change', '.brikpanel-cp-row-check', function () {
            var id = parseInt($(this).val());
            if (this.checked) {
                if (state.selected.indexOf(id) === -1) state.selected.push(id);
            } else {
                state.selected = state.selected.filter(function (s) { return s !== id; });
            }
            $('#bpc-check-all').prop('checked', state.selected.length === state.coupons.length && state.coupons.length > 0);
            updateBulkBar();
        });

        // Bulk actions
        $('#bpc-select-all-btn').on('click', function () {
            state.selected = state.coupons.map(function (c) { return c.id; });
            $('.brikpanel-cp-row-check').prop('checked', true);
            $('#bpc-check-all').prop('checked', true);
            updateBulkBar();
        });

        $('#bpc-deselect-all-btn').on('click', function () {
            state.selected = [];
            $('.brikpanel-cp-row-check').prop('checked', false);
            $('#bpc-check-all').prop('checked', false);
            updateBulkBar();
        });

        $('#bpc-bulk-publish').on('click', function () { bulkStatusChange('publish'); });
        $('#bpc-bulk-draft').on('click', function () { bulkStatusChange('draft'); });
        $('#bpc-bulk-trash').on('click', function () { bulkTrash(); });

        // Pagination
        $(document).on('click', '.brikpanel-cp-page-btn:not(.disabled):not(.active)', function () {
            var p = $(this).data('page');
            if (p === 'prev') {
                state.page = Math.max(1, state.page - 1);
            } else if (p === 'next') {
                state.page = Math.min(state.pages, state.page + 1);
            } else {
                state.page = parseInt(p);
            }
            fetchCoupons();
            $('html, body').animate({ scrollTop: $('#bpc-table').offset().top - 80 }, 200);
        });

        // Inline edit - click on editable amount
        $(document).on('click', '.brikpanel-cp-editable', function (e) {
            e.stopPropagation();
            startInlineEdit($(this));
        });

        // Inline edit - save on Enter, cancel on Escape
        $(document).on('keydown', '.brikpanel-cp-inline-input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveInlineEdit($(this));
            } else if (e.key === 'Escape') {
                cancelInlineEdit($(this));
            }
        });

        // Inline edit - save on blur
        $(document).on('blur', '.brikpanel-cp-inline-input', function () {
            var $input = $(this);
            setTimeout(function () {
                if ($input.closest('.brikpanel-cp-editable').length) {
                    saveInlineEdit($input);
                }
            }, 150);
        });

        // Edit coupon (open drawer)
        $(document).on('click', '.brikpanel-cp-action-edit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            openDrawer(id);
        });

        // Click coupon code to open drawer
        $(document).on('click', '.brikpanel-cp-code-text', function (e) {
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            openDrawer(id);
        });

        // Add new coupon
        $('#bpc-add-new').on('click', function () {
            openDrawer(0);
        });

        // Close drawer
        $('#bpc-drawer-close, #bpc-drawer-cancel, #bpc-drawer-overlay').on('click', closeDrawer);
        $('#bpc-drawer-save').on('click', saveDrawer);

        // Keyboard shortcut: Escape to close drawer
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#bpc-drawer').hasClass('open')) {
                closeDrawer();
            }
        });

        // Toggle status
        $(document).on('click', '.brikpanel-cp-status-badge', function (e) {
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            toggleStatus(id);
        });

        // Delete coupon
        $(document).on('click', '.brikpanel-cp-action-delete', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            var code = $(this).closest('tr').find('.brikpanel-cp-code-text').text();
            if (confirm(CP.i18n.confirm_delete.replace('%s', code))) {
                deleteCoupon(id, false);
            }
        });

        // Permanent delete (trash view)
        $(document).on('click', '.brikpanel-cp-action-delete-perm', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            if (confirm(CP.i18n.confirm_permanent_delete)) {
                deleteCoupon(id, true);
            }
        });

        // Restore from trash
        $(document).on('click', '.brikpanel-cp-action-restore', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            restoreCoupon(id);
        });

        // Duplicate coupon
        $(document).on('click', '.brikpanel-cp-action-duplicate', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).closest('tr').data('id'));
            duplicateCoupon(id);
        });

        // Generate coupon code
        $('#bpc-generate-code').on('click', function () {
            var code = generateCouponCode();
            $('#bpc-code').val(code).focus();
            updateCouponLink();
        });

        // The coupon link follows the code as it is typed.
        $('#bpc-code').on('input', updateCouponLink);
        $('#bpc-copy-link').on('click', copyCouponLink);

        // Discount type changes prefix symbol, and the type other plugins'
        // fields follow (syncTypeMirror()).
        $('#bpc-discount-type').on('change', function () {
            updateAmountPrefix();
            syncTypeMirror();
        });

        // Other plugins' fields: the folded box, and the two links that turn
        // their display setting on and off.
        $(document).on('click', '.bpc-ext__toggle', togglePluginFold);
        $(document).on('click', '.bpc-ext__always, .bpc-ext__hide', switchPluginFields);

        // Token pickers (products / exclude products / categories / exclude categories)
        bindTokenPickers();
    }

    // =========================================================================
    // TOKEN PICKERS
    // =========================================================================

    function bindTokenPickers() {
        Object.keys(TOKEN_TARGETS).forEach(function (target) {
            var cfg = TOKEN_TARGETS[target];
            var $input = $('#' + cfg.inputId);
            if (!$input.length) return; // field not rendered (setting disabled)

            // Typing -> debounced search.
            $input.on('input', function () {
                var term = $(this).val();
                clearTimeout(tokenSearchTimers[target]);
                if (term.length < 2) {
                    $('#' + cfg.sugId).attr('hidden', true).empty();
                    return;
                }
                tokenSearchTimers[target] = setTimeout(function () {
                    runTokenSearch(target, term);
                }, 250);
            });

            // Keyboard: Escape closes suggestions. On free-entry fields Enter,
            // comma and Tab turn whatever was typed into a token, and Backspace
            // on an empty input removes the last one.
            $input.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $('#' + cfg.sugId).attr('hidden', true);
                    return;
                }
                if (!cfg.free) return;

                if (e.key === 'Enter' || e.key === ',' || e.key === 'Tab') {
                    if (!$(this).val().trim()) return;
                    if (e.key !== 'Tab') e.preventDefault();
                    commitFreeTokens(target);
                    $('#' + cfg.sugId).attr('hidden', true);
                } else if (e.key === 'Backspace' && !$(this).val()) {
                    var list = state.tokens[target] || [];
                    if (list.length) {
                        removeToken(target, list[list.length - 1].value);
                    }
                }
            });

            // Hide suggestions when focus leaves the wrap (small delay so a click registers).
            $input.on('blur', function () {
                setTimeout(function () {
                    $('#' + cfg.sugId).attr('hidden', true);
                    // Do not lose a hand-typed address just because the field lost
                    // focus. Silent here: a half-typed search term is not an error.
                    if (cfg.free) commitFreeTokens(target, true);
                }, 150);
            });
        });

        // Suggestion clicks (event delegation).
        $(document).on('mousedown', '.brikpanel-cp-token-suggestion', function (e) {
            e.preventDefault();
            var $sug = $(this).closest('.brikpanel-cp-token-suggestions');
            var $field = $sug.closest('.brikpanel-cp-token-field');
            var target = $field.data('token-target');
            if (!target) return;
            addToken(target, {
                value: $(this).attr('data-value'),
                label: $(this).attr('data-label'),
                sub: $(this).attr('data-sub')
            });
            $field.find('.brikpanel-cp-token-input').val('').focus();
            $sug.attr('hidden', true);
        });

        // Token remove (event delegation).
        $(document).on('click', '.brikpanel-cp-token-remove', function (e) {
            e.preventDefault();
            var $token = $(this).closest('.brikpanel-cp-token');
            var $field = $token.closest('.brikpanel-cp-token-field');
            var target = $field.data('token-target');
            if (!target) return;
            // .attr() not .data(): jQuery would coerce numeric-looking values.
            removeToken(target, $token.attr('data-value'));
        });
    }

    /**
     * Turns whatever is typed in a free-entry token input into tokens.
     * Accepts several addresses at once (comma / space / semicolon separated),
     * which makes pasting a list work.
     */
    function commitFreeTokens(target, silent) {
        var cfg = TOKEN_TARGETS[target];
        if (!cfg || !cfg.free) return;

        var $input = $('#' + cfg.inputId);
        var raw = ($input.val() || '').trim();
        if (!raw) return;

        var parts = raw.split(/[\s,;]+/);
        var invalid = [];

        for (var i = 0; i < parts.length; i++) {
            var candidate = parts[i].trim();
            if (!candidate) continue;
            if (EMAIL_RE.test(candidate)) {
                addToken(target, { value: candidate, label: candidate, sub: '' });
            } else {
                invalid.push(candidate);
            }
        }

        // Anything unparsable stays in the box so it can be corrected instead of
        // vanishing. Silent callers (blur, save) skip the toast: a half-typed
        // search term is not an error.
        if (invalid.length && !silent) {
            showToast(CP.i18n.invalid_email.replace('%s', invalid.join(', ')), 'error');
        }
        $input.val(invalid.join(', '));
    }

    function runTokenSearch(target, term) {
        var cfg = TOKEN_TARGETS[target];
        var $sug = $('#' + cfg.sugId);

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: cfg.action,
                security: CP.nonce,
                q: term
            },
            success: function (res) {
                if (!res || !res.success) return;
                var items = (res.data && res.data.items) || [];
                if (!items.length) {
                    $sug.html('<div class="brikpanel-cp-token-empty">' + escHtml(CP.i18n.no_results) + '</div>');
                    $sug.removeAttr('hidden');
                    return;
                }
                // Filter out anything already selected for this target.
                var selectedValues = (state.tokens[target] || []).map(function (t) { return String(t.value).toLowerCase(); });
                var html = '';
                for (var i = 0; i < items.length; i++) {
                    if (selectedValues.indexOf(String(items[i].value).toLowerCase()) !== -1) continue;
                    var sub = items[i].sub || '';
                    html += '<div class="brikpanel-cp-token-suggestion" data-value="' + escAttr(items[i].value) + '" data-label="' + escAttr(items[i].label) + '" data-sub="' + escAttr(sub) + '">' +
                        '<span class="brikpanel-cp-token-suggestion-label">' + escHtml(items[i].label) + '</span>' +
                        (sub ? '<span class="brikpanel-cp-token-suggestion-sub" dir="ltr">' + escHtml(sub) + '</span>' : '') +
                        '</div>';
                }
                if (!html) {
                    $sug.html('<div class="brikpanel-cp-token-empty">' + escHtml(CP.i18n.no_results) + '</div>');
                } else {
                    $sug.html(html);
                }
                $sug.removeAttr('hidden');
            }
        });
    }

    // Normalizes one {value,label,sub} descriptor for its target: integer IDs
    // for product/category pickers, lowercased text for the email picker.
    function normalizeToken(target, item) {
        var cfg = TOKEN_TARGETS[target] || {};
        if (cfg.string) {
            var val = String(item.value == null ? '' : item.value).trim().toLowerCase();
            return { value: val, label: String(item.label || val), sub: String(item.sub || '') };
        }
        var id = parseInt(item.value, 10);
        return { value: id, label: String(item.label || id), sub: '' };
    }

    function addToken(target, item) {
        if (!item || item.value === undefined || item.value === null || item.value === '') return;
        var token = normalizeToken(target, item);
        if (!token.value) return;

        var list = state.tokens[target] || (state.tokens[target] = []);
        var sv = String(token.value).toLowerCase();
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].value).toLowerCase() === sv) return; // already in
        }
        list.push(token);
        renderTokens(target);
    }

    function removeToken(target, value) {
        var list = state.tokens[target] || [];
        var sv = String(value).toLowerCase();
        state.tokens[target] = list.filter(function (t) { return String(t.value).toLowerCase() !== sv; });
        renderTokens(target);
    }

    function setTokens(target, items) {
        state.tokens[target] = (items || []).map(function (i) {
            return normalizeToken(target, i);
        }).filter(function (t) { return !!t.value; });
        renderTokens(target);
    }

    function renderTokens(target) {
        var cfg = TOKEN_TARGETS[target];
        if (!cfg) return;
        var $host = $('#' + cfg.tokensId);
        if (!$host.length) return;

        var list = state.tokens[target] || [];
        if (!list.length) {
            $host.empty();
            return;
        }
        var html = '';
        for (var i = 0; i < list.length; i++) {
            var sub = list[i].sub || '';
            // Email addresses are left-to-right even on an RTL admin: without
            // this, "*@team.com" is reordered on screen as "team.com@*".
            var labelDir = (cfg.string && !sub) ? ' dir="ltr"' : '';
            html += '<span class="brikpanel-cp-token" data-value="' + escAttr(list[i].value) + '" title="' + escAttr(sub || list[i].label) + '">' +
                '<span class="brikpanel-cp-token-label"' + labelDir + '>' + escHtml(list[i].label) + '</span>' +
                (sub ? '<span class="brikpanel-cp-token-sub" dir="ltr">' + escHtml(sub) + '</span>' : '') +
                '<button type="button" class="brikpanel-cp-token-remove" aria-label="' + escAttr(CP.i18n.remove) + '">&times;</button>' +
                '</span>';
        }
        $host.html(html);
    }

    function tokenValues(target) {
        var list = state.tokens[target] || [];
        var cfg = TOKEN_TARGETS[target] || {};
        if (cfg.string) {
            return list.map(function (t) { return String(t.value); }).filter(function (v) { return v !== ''; });
        }
        return list.map(function (t) { return parseInt(t.value, 10); }).filter(function (v) { return v > 0; });
    }

    // =========================================================================
    // FETCH COUPONS
    // =========================================================================

    // The table stacks its rows into cards when it does not fit its card
    // (front-end/shared/brikpanel-fit-table.js, CLAUDE.md "Tablo sığma kuralı").
    // Measured again after every render of the body.
    var tableFit = null;
    function refitTable() {
        if (!tableFit && window.brikpanelFitTable) {
            var table = document.getElementById('bpc-table');
            if (table) {
                tableFit = window.brikpanelFitTable(table, { labels: 'head', slack: 0 });
            }
        }
        if (tableFit) {
            tableFit.refit();
        }
    }

    function totalColumnCount() {
        // 9 native cols (check, code, type, amount, desc, usage, revenue, expiry,
        // status) + 1 actions col + N extras.
        var extras = state.extraColumns ? Object.keys(state.extraColumns).length : 0;
        return 10 + extras;
    }

    function syncAseHeaders() {
        var $headerRow = $('#bpc-table thead tr');
        if (!$headerRow.length) return;

        $headerRow.find('th.brikpanel-cp-th-ase').remove();

        var cols = state.extraColumns || {};
        var ids  = Object.keys(cols);
        if (!ids.length) return;

        var $actionsTh = $headerRow.find('th.brikpanel-cp-th-actions');
        for (var i = 0; i < ids.length; i++) {
            var colId = ids[i];
            var label = cols[colId];
            var $th = $('<th class="brikpanel-cp-th-ase column-' + escAttr(colId) + '" data-ase-col="' + escAttr(colId) + '"></th>')
                .text(label);
            if ($actionsTh.length) {
                $actionsTh.before($th);
            } else {
                $headerRow.append($th);
            }
        }
    }

    function fetchCoupons() {
        if (state.loading) return;
        state.loading = true;

        var $body = $('#bpc-table-body');
        $body.html('<tr class="brikpanel-cp-loading-row"><td colspan="' + totalColumnCount() + '"><div class="brikpanel-cp-spinner"></div></td></tr>');
        refitTable();

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: 'brikpanel_fetch_coupons',
                security: CP.nonce,
                page: state.page,
                per_page: state.per_page,
                search: state.search,
                status: state.status,
                sort: state.sort
            },
            success: function (res) {
                state.loading = false;
                if (!res.success) {
                    $body.html('<tr><td colspan="' + totalColumnCount() + '" class="brikpanel-cp-empty">' + escHtml(res.data.message || CP.i18n.error) + '</td></tr>');
                    refitTable();
                    return;
                }

                state.coupons = res.data.coupons;
                state.total = res.data.total;
                state.pages = res.data.pages;
                state.extraColumns = res.data.extra_columns || {};

                updateCounts(res.data.counts);
                syncAseHeaders();
                renderCoupons();
                renderPagination();
                updateBulkBar();

                // Reset check-all
                $('#bpc-check-all').prop('checked', false);
            },
            error: function () {
                state.loading = false;
                $body.html('<tr><td colspan="' + totalColumnCount() + '" class="brikpanel-cp-empty">' + escHtml(CP.i18n.error) + '</td></tr>');
                refitTable();
            }
        });
    }

    // =========================================================================
    // RENDER COUPONS
    // =========================================================================

    function renderCoupons() {
        var $body = $('#bpc-table-body');

        if (!state.coupons.length) {
            $body.html('<tr><td colspan="' + totalColumnCount() + '" class="brikpanel-cp-empty">' +
                '<div class="brikpanel-cp-empty-state">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#8a8a8a" stroke-width="1.5"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/></svg>' +
                '<p>' + escHtml(CP.i18n.no_coupons) + '</p>' +
                '</div></td></tr>');
            refitTable();
            return;
        }

        var html = '';
        for (var i = 0; i < state.coupons.length; i++) {
            html += renderCouponRow(state.coupons[i]);
        }

        $body.html(html);

        // Restore checked states
        state.selected.forEach(function (id) {
            $body.find('.brikpanel-cp-row-check[value="' + id + '"]').prop('checked', true);
        });
        refitTable();
    }

    function renderCouponRow(c) {
        var checked = state.selected.indexOf(c.id) > -1 ? ' checked' : '';
        var statusClass = c.status === 'publish' ? 'published' : (c.status === 'trash' ? 'trashed' : 'draft');
        var statusLabel = c.status === 'publish' ? CP.i18n.published : (c.status === 'trash' ? CP.i18n.trashed : CP.i18n.draft);

        // Type display
        var typeLabel = getTypeLabel(c.discount_type);

        // An automatic coupon carries a small "Auto" badge next to its code
        // (outside the code's own span: other handlers read that text).
        var autoBadge = c.auto_apply === 'yes'
            ? ' <span class="brikpanel-badge brikpanel-cp-auto-badge" title="' + escAttr(CP.i18n.auto_title) + '">' + escHtml(CP.i18n.auto_badge) + '</span>'
            : '';

        // Amount display
        var amountDisplay = formatAmount(c.amount, c.discount_type);

        // Description
        var descHtml = c.description ? escHtml(c.description) : '<span class="brikpanel-cp-text-muted">&mdash;</span>';

        // Usage
        var usageHtml = c.usage_count;
        if (c.usage_limit) {
            usageHtml += ' / ' + c.usage_limit;
        } else {
            usageHtml += ' / &infin;'; // i18n-ignore: HTML entity for infinity symbol, language-neutral typographic separator
        }

        // Revenue
        var revenueHtml = c.revenue_formatted
            ? '<span class="brikpanel-cp-revenue-cell">' + c.revenue_formatted + '</span>'
            : '<span class="brikpanel-cp-text-muted">&mdash;</span>';

        // Expiry
        var expiryHtml = '';
        if (c.expiry_date) {
            // The store's short date ("Sep 30, 2026"), not the raw 2026-09-30,
            // and "expired" against the store's today, not the browser's.
            var BFx = window.brikpanelFormat;
            var shown = BFx ? BFx.dateShort(c.expiry_date) : c.expiry_date;
            var now = BFx ? BFx.now() : null;
            var todayYmd = now
                ? now.y + '-' + (now.m < 10 ? '0' : '') + now.m + '-' + (now.d < 10 ? '0' : '') + now.d
                : '';
            if (todayYmd && c.expiry_date < todayYmd) {
                expiryHtml = '<span class="brikpanel-cp-expiry-expired">' + escHtml(shown) + '</span>';
            } else {
                expiryHtml = escHtml(shown);
            }
        } else {
            expiryHtml = '<span class="brikpanel-cp-text-muted">&mdash;</span>';
        }

        // Actions
        var actionsHtml = '';
        if (c.status === 'trash') {
            actionsHtml = '<div class="brikpanel-cp-actions">' +
                '<button type="button" class="brikpanel-cp-action-restore" title="' + escAttr(CP.i18n.restore) + '">' +
                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1,4 1,10 7,10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>' +
                '</button>' +
                '<button type="button" class="brikpanel-cp-action-delete-perm" title="' + escAttr(CP.i18n.delete_permanently) + '">' +
                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#d72c0d" stroke-width="2"><polyline points="3,6 5,6 21,6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
                '</button>' +
                '</div>';
        } else {
            actionsHtml = '<div class="brikpanel-cp-actions">' +
                '<button type="button" class="brikpanel-cp-action-edit" title="' + escAttr(CP.i18n.edit) + '">' +
                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                '</button>' +
                '<button type="button" class="brikpanel-cp-action-duplicate" title="' + escAttr(CP.i18n.duplicate) + '">' +
                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
                '</button>' +
                '<button type="button" class="brikpanel-cp-action-delete" title="' + escAttr(CP.i18n.trash) + '">' +
                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3,6 5,6 21,6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
                '</button>' +
                '</div>';
        }

        // Cells contributed by third-party plugins (ASE etc.) via the
        // manage_{post_type}_posts_custom_column action. HTML already
        // sanitised server-side via wp_kses_post.
        var aseCellsHtml = '';
        var extraCols    = state.extraColumns || {};
        var extraIds     = Object.keys(extraCols);
        if (extraIds.length && c.extra_cells) {
            for (var ci = 0; ci < extraIds.length; ci++) {
                var aseColId = extraIds[ci];
                var aseHtml  = (typeof c.extra_cells[aseColId] !== 'undefined') ? c.extra_cells[aseColId] : '';
                aseCellsHtml += '<td class="brikpanel-cp-cell-ase column-' + escAttr(aseColId) + '" data-ase-col="' + escAttr(aseColId) + '">' + aseHtml + '</td>';
            }
        }

        // Row actions injected via post_row_actions filter, on their own grey
        // line under the code (the shared row links in
        // front-end/shared/brikpanel-ui.css).
        var aseActionsHtml = '';
        if (c.extra_actions && c.extra_actions.length) {
            var parts = [];
            for (var ai = 0; ai < c.extra_actions.length; ai++) {
                parts.push('<span class="brikpanel-cp-row-action brikpanel-cp-row-action-' + escAttr(c.extra_actions[ai].id || '') + '">' + (c.extra_actions[ai].html || '') + '</span>');
            }
            aseActionsHtml = '<div class="brikpanel-row-links brikpanel-cp-row-actions">' + parts.join('') + '</div>';
        }

        return '<tr class="brikpanel-cp-row" data-id="' + c.id + '">' +
            '<td class="brikpanel-cp-cell-check"><input type="checkbox" class="brikpanel-cp-row-check brikpanel-cp-checkbox" value="' + c.id + '"' + checked + '></td>' +
            '<td class="brikpanel-cp-cell-code brikpanel-fit-lead"><span class="brikpanel-cp-code-text">' + escHtml(asciiUpper(c.code)) + '</span>' + autoBadge + aseActionsHtml + '</td>' +
            '<td class="brikpanel-cp-cell-type"><span class="brikpanel-cp-type-badge">' + escHtml(typeLabel) + '</span></td>' +
            '<td class="brikpanel-cp-cell-amount brikpanel-fit-headline"><span class="brikpanel-cp-editable brikpanel-cp-amount-cell" data-field="amount" data-value="' + escAttr(c.amount) + '">' + amountDisplay + '</span></td>' +
            '<td class="brikpanel-cp-cell-desc"><span class="brikpanel-cp-desc-cell">' + descHtml + '</span></td>' +
            '<td class="brikpanel-cp-cell-usage"><span class="brikpanel-cp-usage-cell">' + usageHtml + '</span></td>' +
            '<td class="brikpanel-cp-cell-revenue">' + revenueHtml + '</td>' +
            '<td class="brikpanel-cp-cell-expiry"><span class="brikpanel-cp-expiry-cell">' + expiryHtml + '</span></td>' +
            '<td class="brikpanel-cp-cell-status"><span class="brikpanel-cp-status-badge ' + statusClass + '" title="' + escAttr(CP.i18n.click_to_toggle) + '">' + escHtml(statusLabel) + '</span></td>' +
            aseCellsHtml +
            '<td class="brikpanel-cp-actions-cell brikpanel-fit-full">' + actionsHtml + '</td>' +
            '</tr>';
    }

    // =========================================================================
    // INLINE EDITING (Amount)
    // =========================================================================

    function startInlineEdit($el) {
        if ($el.find('.brikpanel-cp-inline-input').length) return;

        var field = $el.data('field');
        var value = $el.data('value') || '';

        var $input = $('<input type="text" class="brikpanel-cp-inline-input" value="' + escAttr(value) + '">');

        $el.data('original-html', $el.html());
        $el.html('').append($input);
        $input.focus().select();
    }

    function saveInlineEdit($input) {
        var $el = $input.closest('.brikpanel-cp-editable');
        if (!$el.length) return;

        var field = $el.data('field');
        var newValue = $input.val().trim();
        var oldValue = String($el.data('value') || '');
        var couponId = parseInt($el.closest('tr').data('id'));

        // No change
        if (newValue === oldValue) {
            cancelInlineEdit($input);
            return;
        }

        $el.addClass('saving');
        $input.prop('disabled', true);

        // Get the coupon to know what fields to send
        var coupon = getCouponById(couponId);
        if (!coupon) {
            cancelInlineEdit($input);
            return;
        }

        var data = {
            action: 'brikpanel_save_coupon',
            security: CP.nonce,
            coupon_id: couponId,
            code: coupon.code,
            description: coupon.description,
            discount_type: coupon.discount_type,
            amount: field === 'amount' ? newValue : coupon.amount,
            free_shipping: coupon.free_shipping,
            expiry_date: coupon.expiry_date,
            minimum_amount: coupon.minimum_amount,
            maximum_amount: coupon.maximum_amount,
            individual_use: coupon.individual_use,
            exclude_sale_items: coupon.exclude_sale_items,
            usage_limit: coupon.usage_limit,
            usage_limit_per_user: coupon.usage_limit_per_user
        };

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: data,
            success: function (res) {
                $el.removeClass('saving');
                if (res.success) {
                    showToast(CP.i18n.saved, 'success');
                    fetchCoupons();
                } else {
                    cancelInlineEdit($input);
                    showToast(res.data.message || CP.i18n.error, 'error');
                }
            },
            error: function () {
                $el.removeClass('saving');
                cancelInlineEdit($input);
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    function cancelInlineEdit($input) {
        var $el = $input.closest('.brikpanel-cp-editable');
        var original = $el.data('original-html');
        if (original !== undefined) {
            $el.html(original);
        }
    }

    function getCouponById(id) {
        for (var i = 0; i < state.coupons.length; i++) {
            if (state.coupons[i].id === id) return state.coupons[i];
        }
        return null;
    }

    // =========================================================================
    // DRAWER (Create / Edit)
    // =========================================================================

    function openDrawer(id) {
        var coupon = null;
        var isNew = (id === 0);

        if (!isNew) {
            coupon = getCouponById(id);
            if (!coupon) return;
        }

        // Reset form
        $('#bpc-coupon-id').val(isNew ? '' : coupon.id);
        $('#bpc-code').val(isNew ? '' : coupon.code);
        $('#bpc-description').val(isNew ? '' : coupon.description);
        setDrawerType(isNew ? 'percent' : coupon.discount_type);
        $('#bpc-amount').val(isNew ? '' : coupon.amount);
        $('#bpc-free-shipping').prop('checked', isNew ? false : coupon.free_shipping === 'yes');
        $('#bpc-expiry').val(isNew ? '' : coupon.expiry_date);
        $('#bpc-min-spend').val(isNew ? '' : coupon.minimum_amount);
        $('#bpc-max-spend').val(isNew ? '' : coupon.maximum_amount);
        $('#bpc-individual-use').prop('checked', isNew ? false : coupon.individual_use === 'yes');
        $('#bpc-exclude-sale').prop('checked', isNew ? false : coupon.exclude_sale_items === 'yes');
        $('#bpc-usage-limit').val(isNew ? '' : coupon.usage_limit);
        $('#bpc-usage-limit-user').val(isNew ? '' : coupon.usage_limit_per_user);

        // Advanced restriction fields (rendered only when the matching setting is enabled).
        setTokens('products',           isNew ? [] : (coupon.product_labels || []));
        setTokens('exclude_products',   isNew ? [] : (coupon.excluded_product_labels || []));
        setTokens('categories',         isNew ? [] : (coupon.category_labels || []));
        setTokens('exclude_categories', isNew ? [] : (coupon.excluded_category_labels || []));

        setTokens('emails',             isNew ? [] : (coupon.email_labels || []));

        $('#bpc-limit-items').val(isNew ? '' : (coupon.limit_usage_to_x_items || ''));

        // Automatic coupons: the switch, the item range and the coupon link.
        $('#bpc-auto-apply').prop('checked', isNew ? false : coupon.auto_apply === 'yes');
        $('#bpc-min-items').val(isNew ? '' : (coupon.min_items || ''));
        $('#bpc-max-items').val(isNew ? '' : (coupon.max_items || ''));
        updateCouponLink();

        // What other plugins and the theme add to this coupon.
        loadPluginFields(isNew ? 0 : coupon.id);

        // Clear any leftover search input / suggestions in token pickers.
        $('.brikpanel-cp-token-input').val('');
        $('.brikpanel-cp-token-suggestions').attr('hidden', true).empty();

        // Usage count
        if (!isNew && coupon.usage_count > 0) {
            $('#bpc-usage-count-display').text(coupon.usage_count);
            $('#bpc-usage-count-field').show();
        } else {
            $('#bpc-usage-count-field').hide();
        }

        // Update drawer title
        $('#bpc-drawer-title').text(isNew ? CP.i18n.add_coupon : CP.i18n.edit_coupon);

        // Update save button text
        $('#bpc-drawer-save').text(isNew ? CP.i18n.create : CP.i18n.save_changes);

        // Update amount prefix
        updateAmountPrefix();

        // Open
        $('#bpc-drawer, #bpc-drawer-overlay').addClass('open');
        $('body').addClass('brikpanel-cp-drawer-open');

        // Focus
        setTimeout(function () { $('#bpc-code').focus(); }, 200);
    }

    function closeDrawer() {
        $('#bpc-drawer, #bpc-drawer-overlay').removeClass('open');
        $('body').removeClass('brikpanel-cp-drawer-open');
    }

    function saveDrawer() {
        var $btn = $('#bpc-drawer-save');
        var couponId = parseInt($('#bpc-coupon-id').val()) || 0;
        var code = $('#bpc-code').val().trim();

        // An address typed but never confirmed with Enter still counts as intent.
        commitFreeTokens('emails', true);

        if (!code) {
            showToast(CP.i18n.code_required, 'error');
            $('#bpc-code').focus();
            return;
        }

        $btn.prop('disabled', true).text(CP.i18n.saving);

        var data = {
            action: 'brikpanel_save_coupon',
            security: CP.nonce,
            coupon_id: couponId,
            code: code,
            description: $('#bpc-description').val(),
            discount_type: $('#bpc-discount-type').val(),
            amount: $('#bpc-amount').val(),
            free_shipping: $('#bpc-free-shipping').is(':checked') ? 'yes' : 'no',
            expiry_date: $('#bpc-expiry').val(),
            minimum_amount: $('#bpc-min-spend').val(),
            maximum_amount: $('#bpc-max-spend').val(),
            individual_use: $('#bpc-individual-use').is(':checked') ? 'yes' : 'no',
            exclude_sale_items: $('#bpc-exclude-sale').is(':checked') ? 'yes' : 'no',
            usage_limit: $('#bpc-usage-limit').val(),
            usage_limit_per_user: $('#bpc-usage-limit-user').val(),
            auto_apply: $('#bpc-auto-apply').is(':checked') ? 'yes' : 'no',
            min_items: String($('#bpc-min-items').val() || '').trim(),
            max_items: String($('#bpc-max-items').val() || '').trim()
        };

        // Advanced restriction fields - only sent if the corresponding setting is on
        // (the server also gates this, so a forged payload still cannot write).
        // Arrays are joined into comma-separated strings so that an "empty
        // selection" still transmits the key — jQuery drops empty array
        // values from the POST body, which would otherwise prevent the user
        // from clearing out a previously-selected list.
        if (isFieldEnabled('products')) {
            data.product_ids = tokenValues('products').join(',');
        }
        if (isFieldEnabled('exclude_products')) {
            data.excluded_product_ids = tokenValues('exclude_products').join(',');
        }
        if (isFieldEnabled('categories')) {
            data.category_ids = tokenValues('categories').join(',');
        }
        if (isFieldEnabled('exclude_categories')) {
            data.excluded_category_ids = tokenValues('exclude_categories').join(',');
        }
        if (isFieldEnabled('emails')) {
            data.email_restrictions = tokenValues('emails').join(',');
        }
        if (isFieldEnabled('limit_items')) {
            data.limit_usage_to_x_items = $('#bpc-limit-items').val();
        }

        // Other plugins' fields on the form go along under their own names
        // (brackets kept, so PHP reads them as WooCommerce's screen posts
        // them), with their owners, whose own save then runs.
        var ext = pluginFieldPairs();
        if (ext.owners.length) {
            data.bp_cp_owners = JSON.stringify(ext.owners);
        }
        var body = $.param(data, true) + (ext.pairs.length ? '&' + $.param(ext.pairs) : '');

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: body,
            success: function (res) {
                $btn.prop('disabled', false).text(couponId > 0 ? CP.i18n.save_changes : CP.i18n.create);
                if (res.success) {
                    // Saved; a plugin whose own save stopped is named in red.
                    showToast(res.data.message, res.data.warning ? 'error' : 'success');
                    closeDrawer();
                    fetchCoupons();
                } else {
                    showToast(res.data.message || CP.i18n.error, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text(couponId > 0 ? CP.i18n.save_changes : CP.i18n.create);
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    // =========================================================================
    // TOGGLE STATUS
    // =========================================================================

    function toggleStatus(id) {
        var $badge = $('#bpc-table-body tr[data-id="' + id + '"] .brikpanel-cp-status-badge'); // i18n-ignore: selector fragment
        $badge.addClass('brikpanel-cp-status-saving');

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: 'brikpanel_toggle_coupon_status',
                security: CP.nonce,
                coupon_id: id
            },
            success: function (res) {
                $badge.removeClass('brikpanel-cp-status-saving');
                if (res.success) {
                    var newClass = res.data.status === 'publish' ? 'published' : 'draft';
                    var newLabel = res.data.status === 'publish' ? CP.i18n.published : CP.i18n.draft;
                    $badge.removeClass('published draft trashed').addClass(newClass).text(newLabel);

                    // Update state
                    for (var i = 0; i < state.coupons.length; i++) {
                        if (state.coupons[i].id === id) {
                            state.coupons[i].status = res.data.status;
                            break;
                        }
                    }

                    showToast(res.data.message, 'success');
                } else {
                    showToast(res.data.message || CP.i18n.error, 'error');
                }
            },
            error: function () {
                $badge.removeClass('brikpanel-cp-status-saving');
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    // =========================================================================
    // DELETE / RESTORE / DUPLICATE
    // =========================================================================

    function deleteCoupon(id, permanent) {
        var $row = $('#bpc-table-body tr[data-id="' + id + '"]');
        $row.addClass('brikpanel-cp-row-deleting');

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: 'brikpanel_delete_coupon',
                security: CP.nonce,
                coupon_id: id,
                permanent: permanent ? 'yes' : 'no'
            },
            success: function (res) {
                if (res.success) {
                    $row.fadeOut(300, function () {
                        $(this).remove();
                        showToast(res.data.message, 'success');
                        fetchCoupons();
                    });
                } else {
                    $row.removeClass('brikpanel-cp-row-deleting');
                    showToast(res.data.message || CP.i18n.error, 'error');
                }
            },
            error: function () {
                $row.removeClass('brikpanel-cp-row-deleting');
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    function restoreCoupon(id) {
        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: 'brikpanel_toggle_coupon_status',
                security: CP.nonce,
                coupon_id: id
            },
            success: function (res) {
                if (res.success) {
                    showToast(CP.i18n.restored, 'success');
                    fetchCoupons();
                } else {
                    showToast(res.data.message || CP.i18n.error, 'error');
                }
            },
            error: function () {
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    function duplicateCoupon(id) {
        showToast(CP.i18n.duplicating, 'info');

        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: 'brikpanel_duplicate_coupon',
                security: CP.nonce,
                coupon_id: id
            },
            success: function (res) {
                if (res.success) {
                    showToast(res.data.message || CP.i18n.duplicated, 'success');
                    fetchCoupons();
                } else {
                    showToast(res.data.message || CP.i18n.error, 'error');
                }
            },
            error: function () {
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    function bulkStatusChange(newStatus) {
        if (!state.selected.length) return;

        var confirmMsg = window.brikpanelFormat ? window.brikpanelFormat.count(CP.i18n.confirm_bulk, state.selected.length) : '';
        if (!confirm(confirmMsg)) return;

        var pending = state.selected.length;
        var completed = 0;

        state.selected.forEach(function (id) {
            // We set status by using wp_update_post approach via toggle
            // For bulk, we need a different approach - update each post status
            $.ajax({
                url: CP.ajax_url,
                type: 'POST',
                data: {
                    action: 'brikpanel_toggle_coupon_status',
                    security: CP.nonce,
                    coupon_id: id
                },
                complete: function () {
                    completed++;
                    if (completed >= pending) {
                        state.selected = [];
                        updateBulkBar();
                        fetchCoupons();
                        showToast(CP.i18n.bulk_updated, 'success');
                    }
                }
            });
        });
    }

    function bulkTrash() {
        if (!state.selected.length) return;

        var confirmMsg = window.brikpanelFormat ? window.brikpanelFormat.count(CP.i18n.confirm_bulk_trash, state.selected.length) : '';
        if (!confirm(confirmMsg)) return;

        var pending = state.selected.length;
        var completed = 0;

        state.selected.forEach(function (id) {
            $.ajax({
                url: CP.ajax_url,
                type: 'POST',
                data: {
                    action: 'brikpanel_delete_coupon',
                    security: CP.nonce,
                    coupon_id: id,
                    permanent: 'no'
                },
                complete: function () {
                    completed++;
                    if (completed >= pending) {
                        state.selected = [];
                        updateBulkBar();
                        fetchCoupons();
                        showToast(CP.i18n.bulk_trashed, 'success');
                    }
                }
            });
        });
    }

    function updateBulkBar() {
        var count = state.selected.length;
        $('#bpc-selected-count').text(count);
        if (count > 0) {
            $('#bpc-bulk-bar').slideDown(200);
        } else {
            $('#bpc-bulk-bar').slideUp(200);
        }
    }

    // =========================================================================
    // PAGINATION
    // =========================================================================

    // "Showing 1-20 of 57 coupons": plural by the total, numbers in the
    // store's format (front-end/shared/brikpanel-format.js).
    function showingText(msg, values) {
        var BF = window.brikpanelFormat;
        if (!BF) return '';
        return BF.format(BF.plural(msg, state.total), values.map(function (v) { return BF.number(v); }));
    }

    function renderPagination() {
        var $pag = $('#bpc-pagination');
        if (state.pages <= 1) {
            $pag.html('<span class="brikpanel-cp-showing">' + escHtml(showingText(CP.i18n.showing, [state.total, state.total])) + '</span>');
            return;
        }

        var start = (state.page - 1) * state.per_page + 1;
        var end = Math.min(state.page * state.per_page, state.total);
        var html = '<span class="brikpanel-cp-showing">' +
            escHtml(showingText(CP.i18n.showing_range, [start, end, state.total])) +
            '</span>';

        html += '<div class="brikpanel-cp-page-btns">';

        // Prev
        html += '<button class="brikpanel-cp-page-btn' + (state.page <= 1 ? ' disabled' : '') + '" data-page="prev">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg>' +
            '</button>';

        // Page numbers
        var pages = getPaginationNumbers(state.page, state.pages);
        for (var i = 0; i < pages.length; i++) {
            if (pages[i] === '...') {
                html += '<span class="brikpanel-cp-page-dots">...</span>';
            } else {
                html += '<button class="brikpanel-cp-page-btn' + (pages[i] === state.page ? ' active' : '') + '" data-page="' + pages[i] + '">' + pages[i] + '</button>';
            }
        }

        // Next
        html += '<button class="brikpanel-cp-page-btn' + (state.page >= state.pages ? ' disabled' : '') + '" data-page="next">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>' +
            '</button>';

        html += '</div>';
        $pag.html(html);
    }

    function getPaginationNumbers(current, total) {
        if (total <= 7) {
            var arr = [];
            for (var i = 1; i <= total; i++) arr.push(i);
            return arr;
        }

        var pages = [1];
        if (current > 3) pages.push('...');
        for (var j = Math.max(2, current - 1); j <= Math.min(total - 1, current + 1); j++) {
            pages.push(j);
        }
        if (current < total - 2) pages.push('...');
        pages.push(total);
        return pages;
    }

    // =========================================================================
    // UPDATE COUNTS
    // =========================================================================

    function updateCounts(counts) {
        if (!counts) return;
        $('[data-count="all"]').text(counts.all);
        $('[data-count="publish"]').text(counts.publish);
        $('[data-count="draft"]').text(counts.draft);
        $('#bpc-total-count').text(counts.all);

        // Show/hide trash tab
        if (counts.trash > 0) {
            if (!$('[data-count="trash"]').length) {
                $('.brikpanel-cp-tabs').append(
                    '<button class="brikpanel-cp-tab' + (state.status === 'trash' ? ' active' : '') + '" data-status="trash">' +
                    escHtml(CP.i18n.trashed) + ' <span class="brikpanel-cp-tab-count" data-count="trash">' + counts.trash + '</span></button>'
                );
            } else {
                $('[data-count="trash"]').text(counts.trash);
            }
        } else {
            // Remove trash tab when count is 0
            var $trashTab = $('.brikpanel-cp-tab[data-status="trash"]');
            if ($trashTab.length) {
                // If trash tab was active, switch to 'any'
                if ($trashTab.hasClass('active')) {
                    state.status = 'any';
                    $trashTab.remove();
                    $('.brikpanel-cp-tab[data-status="any"]').addClass('active');
                    fetchCoupons();
                } else {
                    $trashTab.remove();
                }
            }
        }
    }

    // =========================================================================
    // TOAST NOTIFICATIONS
    // =========================================================================

    function showToast(message, type) {
        type = type || 'success';
        var $container = $('#bpc-toast-container');
        // is-*, never a bare `error`: WordPress styles every div.error as an admin notice.
        var $toast = $('<div class="brikpanel-cp-toast is-' + type + '">' +
            '<span class="brikpanel-cp-toast-text">' + escHtml(message) + '</span>' +
            '<button class="brikpanel-cp-toast-close">&times;</button>' +
            '</div>');

        $container.append($toast);

        // Animate in
        setTimeout(function () { $toast.addClass('show'); }, 10);

        // Close button
        $toast.find('.brikpanel-cp-toast-close').on('click', function () {
            $toast.removeClass('show');
            setTimeout(function () { $toast.remove(); }, 300);
        });

        // Auto-dismiss
        setTimeout(function () {
            $toast.removeClass('show');
            setTimeout(function () { $toast.remove(); }, 300);
        }, 3500);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    function getTypeLabel(type) {
        switch (type) {
            case 'percent': return CP.i18n.type_percent;
            case 'fixed_cart': return CP.i18n.type_fixed_cart;
            case 'fixed_product': return CP.i18n.type_fixed_product;
            // Another plugin's type: its own name (wc_get_coupon_types()).
            default: return (CP.type_labels && CP.type_labels[type]) || type;
        }
    }

    function isFixedType(type) {
        return type === 'fixed_cart' || type === 'fixed_product';
    }

    // The store's price format and separators, the percent sign where the
    // viewer's language writes it (front-end/shared/brikpanel-format.js). The
    // symbol used to go in front whatever the store's setting (field test E2/E9).
    function formatAmount(amount, type) {
        if (!amount || parseFloat(amount) === 0) return '<span class="brikpanel-cp-text-muted">&mdash;</span>';
        var BF = window.brikpanelFormat;
        if (!BF) return escHtml(amount);
        if (type === 'percent') return escHtml(BF.percent(parseFloat(amount), 2));
        if (isFixedType(type)) return escHtml(BF.money(parseFloat(amount)));
        // Another plugin's type: what its amount means is that plugin's, so
        // the number alone, neither % nor a price.
        return escHtml(BF.number(parseFloat(amount), 2, true));
    }

    /* The coupon link: the store's home page with ?bp-coupon=CODE (the
       parameter the cart share link uses for its coupon too). */
    function updateCouponLink() {
        var code = String($('#bpc-code').val() || '').trim();
        var base = String(CP.link_base || '');
        var link = '';
        if (code && base) {
            link = base + (base.indexOf('?') === -1 ? '?' : '&') + encodeURIComponent(CP.link_var || 'bp-coupon') + '=' + encodeURIComponent(code); // i18n-ignore: URL parameter name, not text.
        }
        $('#bpc-link').val(link);
        $('#bpc-copy-link').prop('disabled', !link);
    }

    function copyCouponLink() {
        var $input = $('#bpc-link');
        var link = $input.val();
        if (!link) return;
        var selectIt = function () {
            $input.trigger('focus');
            $input[0].select();
        };
        var fallback = function () {
            selectIt();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            showToast(ok ? CP.i18n.link_copied : CP.i18n.copy_failed, ok ? 'success' : 'info');
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(function () {
                showToast(CP.i18n.link_copied, 'success');
            }, fallback);
        } else {
            fallback();
        }
    }

    function updateAmountPrefix() {
        var type = $('#bpc-discount-type').val();
        var $prefix = $('#bpc-amount-prefix');
        if (type === 'percent') {
            $prefix.text('%').removeClass('is-empty');
        } else if (isFixedType(type)) {
            $prefix.text(CP.currency).removeClass('is-empty');
        } else {
            // Another plugin's type: no sign (see formatAmount()).
            $prefix.text('').addClass('is-empty');
        }
    }

    // =========================================================================
    // DISCOUNT TYPES AND OTHER PLUGINS' FIELDS
    // =========================================================================

    // The window's list offers WooCommerce's types and the ones the settings
    // show (as printed, or as ajax_plugin_fields() sends them). A coupon whose
    // own type is not among them gets it as an extra option while it is open,
    // so the box is never empty and saving never changes the type.
    function setDrawerType(type) {
        var $sel = $('#bpc-discount-type');
        $sel.find('option[data-bpc-own]').remove();
        if (type && !$sel.find('option').filter(function () { return this.value === type; }).length) {
            $('<option>').val(type).text(getTypeOptionLabel(type)).attr('data-bpc-own', '1').appendTo($sel);
        }
        $sel.val(type || 'percent');
        updateAmountPrefix();
        syncTypeMirror();
    }

    function getTypeOptionLabel(type) {
        return (CP.type_labels && CP.type_labels[type]) || type;
    }

    function refreshTypeOptions(types) {
        if (!types || typeof types !== 'object') return;
        var $sel = $('#bpc-discount-type');
        var current = $sel.val();
        $sel.empty();
        $.each(types, function (value, label) {
            $('<option>').val(value).text(label).appendTo($sel);
        });
        setDrawerType(current);
    }

    // select#discount_type, hidden in WooCommerce's frame around other
    // plugins' fields, follows the window's type: plugins show or hide their
    // fields by it, as on WooCommerce's coupon screen.
    function syncTypeMirror() {
        var $mirror = $('#discount_type');
        if (!$mirror.length) return;
        var type = $('#bpc-discount-type').val() || '';
        if (type && !$mirror.find('option').filter(function () { return this.value === type; }).length) {
            $('<option>').val(type).text(getTypeOptionLabel(type)).appendTo($mirror);
        }
        $mirror.val(type).trigger('change');
    }

    var pluginFieldsRequest = 0;

    // The fields other plugins and the theme add to this coupon (0: a new
    // one), drawn by the server as WooCommerce's screen draws them. Their
    // own inline scripts run as the markup goes in, as there.
    function loadPluginFields(couponId) {
        var $ext = $('#bpc-ext');
        if (!CP.plugin_fields || !$ext.length) return;
        var $body = $('#bpc-ext-body');
        var token = ++pluginFieldsRequest;
        $ext.prop('hidden', false).attr('aria-busy', 'true').removeData('loaded');
        $body.html('<p class="bpc-ext__state">' + escHtml(CP.i18n.ext_loading) + '</p>');
        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: 'brikpanel_coupon_plugin_fields',
                security: CP.nonce,
                coupon_id: couponId || 0
            },
            success: function (res) {
                if (token !== pluginFieldsRequest) return;
                $ext.removeAttr('aria-busy');
                if (!res || !res.success) {
                    $body.html('<p class="bpc-ext__state is-failed">' + escHtml(CP.i18n.ext_failed) + '</p>');
                    return;
                }
                refreshTypeOptions(res.data.types);
                if (!res.data.html) {
                    $body.empty();
                    $ext.prop('hidden', true);
                    return;
                }
                $body.html(res.data.html);
                $ext.data('loaded', true);
                initPluginFields($body);
            },
            error: function () {
                if (token !== pluginFieldsRequest) return;
                $ext.removeAttr('aria-busy');
                $body.html('<p class="bpc-ext__state is-failed">' + escHtml(CP.i18n.ext_failed) + '</p>');
            }
        });
    }

    // WooCommerce's own helpers, started the way its coupon screen starts
    // them: help tips, product and customer search boxes, date pickers. Then
    // each plugin tab is clicked once, for plugins that fill their panel then.
    function initPluginFields($body) {
        $(document.body).trigger('wc-enhanced-select-init');
        $(document.body).trigger('init_tooltips');
        if ($.fn.datepicker) {
            $body.find('.date-picker').each(function () {
                if (!$(this).hasClass('hasDatepicker')) {
                    $(this).datepicker({ dateFormat: 'yy-mm-dd', numberOfMonths: 1, showButtonPanel: true });
                }
            });
        }
        $body.find('.bpc-ext__tabs li[data-bpc-plugin-tab] a').each(function () {
            try {
                $(this).trigger('click');
            } catch (e) {
                // another plugin's tab handler; its panel stays as printed
            }
        });
        syncTypeMirror();
    }

    // Opened once, the folded box's fields go with the save (data-held is
    // dropped); the person's choice is kept for every coupon.
    function togglePluginFold() {
        var $toggle = $(this);
        var $fold = $toggle.closest('.bpc-ext__fold');
        var open = !$fold.hasClass('is-open');
        $fold.toggleClass('is-open', open);
        $toggle.attr('aria-expanded', open ? 'true' : 'false');
        $fold.find('.bpc-ext__foldbody').prop('hidden', !open);
        if (open) {
            $fold.removeAttr('data-held');
            $(document.body).trigger('wc-enhanced-select-init');
        }
        $.post(CP.ajax_url, {
            action: 'brikpanel_cp_fold_state',
            security: CP.nonce,
            open: open ? '1' : '0'
        });
    }

    // "Always show these fields" / "Hide these fields": the same two
    // settings as WooCommerce > Settings > BrikPanel > Coupons, then the
    // fields (and the type list) are drawn again for this coupon.
    function switchPluginFields() {
        var $btn = $(this);
        if ($btn.prop('disabled')) return;
        var show = $btn.hasClass('bpc-ext__always');
        var label = $btn.text();
        $btn.prop('disabled', true).text(show ? CP.i18n.turning_on : CP.i18n.hiding);
        $.ajax({
            url: CP.ajax_url,
            type: 'POST',
            data: {
                action: show ? 'brikpanel_cp_enable_plugin_fields' : 'brikpanel_cp_disable_plugin_fields',
                security: CP.nonce
            },
            success: function (res) {
                if (res && res.success) {
                    loadPluginFields(parseInt($('#bpc-coupon-id').val(), 10) || 0);
                    return;
                }
                $btn.prop('disabled', false).text(label);
                showToast((res && res.data && res.data.message) || CP.i18n.error, 'error');
            },
            error: function () {
                $btn.prop('disabled', false).text(label);
                showToast(CP.i18n.error, 'error');
            }
        });
    }

    // Other plugins' fields on the form: the open ones, and the folded box's
    // once it was opened. Name/value pairs as a form would post them
    // (unticked boxes left out), and the owners whose fields they are. A
    // field under one of the window's own names is left out, the window's
    // value wins.
    function pluginFieldPairs() {
        var out = { pairs: [], owners: [] };
        var $ext = $('#bpc-ext');
        if (!$ext.length || !$ext.data('loaded')) return out;
        var own = {};
        $.each(CP.own_keys || [], function (i, key) { own[key] = true; });
        $ext.find('.bpc-ext__group').each(function () {
            var $group = $(this);
            if ($group.closest('[data-held]').length) return;
            out.owners.push(String($group.attr('data-owner') || ''));
            $group.find(':input[name]').each(function () {
                var el = this;
                var name = el.name;
                if (!name || el.disabled || own[name.replace(/\[.*$/, '')]) return;
                var type = (el.type || '').toLowerCase();
                if (type === 'button' || type === 'submit' || type === 'reset' || type === 'file' || type === 'image') return;
                if ((type === 'checkbox' || type === 'radio') && !el.checked) return;
                var value = $(el).val();
                if (el.tagName === 'SELECT' && el.multiple) {
                    $.each(value || [], function (i, v) { out.pairs.push({ name: name, value: v }); });
                    return;
                }
                out.pairs.push({ name: name, value: value === null || value === undefined ? '' : value });
            });
        });
        return out;
    }

    function generateCouponCode() {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var code = '';
        for (var i = 0; i < 8; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return code;
    }

    // Coupon codes are shown in capitals, Latin letters only, the way the
    // popup and BrikMentor's emails show them (ascii_upper()). CSS
    // text-transform followed the page's language, so a Turkish dashboard
    // turned "brik" into "BRİK", a code nobody can type back. Other letters
    // are left as they are: "ß" or "ı" made capital would read as a
    // different code.
    function asciiUpper(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[a-z]+/g, function (m) { return m.toUpperCase(); });
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function escAttr(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    $(document).ready(function () {
        if (!$('#brikpanel-coupons-list').length) return;
        bindEvents();
        fetchCoupons();
        // The top bar's "Create > Coupon" opens this page with action=new.
        if (/[?&]action=new(?:&|$)/.test(window.location.search)) {
            openDrawer(0);
        }
    });

})(jQuery);
