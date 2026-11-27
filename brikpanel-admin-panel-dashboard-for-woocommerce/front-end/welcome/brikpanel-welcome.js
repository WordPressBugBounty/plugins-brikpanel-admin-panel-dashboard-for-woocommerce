/**
 * BrikPanel: welcome tour.
 *
 * Opens the tour dialog, moves between the four steps and the final panel,
 * lays a brick on the plate for every step seen, flattens the plate into the
 * logo on the final panel and dismisses the tour for good when it closes.
 * On a store's first tour it opens on "What did you install BrikPanel for?":
 * the full panel goes on to the steps, "Just one feature" to the nine
 * features, and the picked one is saved (admin-ajax) before the final panel
 * shows with that one link (front-end/welcome/brikpanel-welcome-intent.php).
 * Every word comes from the markup or from brikpanelWelcome.i18n.
 *
 * @package BrikPanel
 * @since   2.0.4
 */
(function () {
    'use strict';

    var dialog = document.getElementById('brikpanel-welcome-overlay');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var cfg = window.brikpanelWelcome || {};
    var i18n = cfg.i18n || {};
    var root = document.documentElement;
    var reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

    function one(sel) {
        return dialog.querySelector(sel);
    }

    function all(sel) {
        return Array.prototype.slice.call(dialog.querySelectorAll(sel));
    }

    var panelList = all('[data-bw-panel]');
    var panelBox = one('.brikpanel-welcome-panels');
    var tabs = all('[data-bw-goto]');
    var startEls = all('[data-bw-start]');
    var doneEls = all('[data-bw-done]');
    var oneEls = all('[data-bw-one]');
    var fullOnlyEls = all('[data-bw-full-only]');
    var oneLinks = one('[data-bw-one-links]');
    var stage = one('[data-bw-stage]');
    var bricks = all('[data-bw-brick]');
    var shadows = all('[data-bw-shadow]');
    var slots = all('[data-bw-slot]');
    var live = one('[data-bw-live]');
    var btnPrev = one('[data-bw-prev]');
    var btnNext = one('[data-bw-next]');
    var nextLabel = one('[data-bw-next-label]');
    var btnSkip = one('[data-bw-skip]');
    var btnGo = one('.brikpanel-welcome-go');
    var goLabel = one('[data-bw-go-label]');
    var modal = one('[data-bw-modal]');
    var left = one('[data-bw-left]');

    // Panels by name: the steps are 0..STEPS-1 and the final panel is STEPS;
    // a store's first tour also has "intent" (what did you install BrikPanel
    // for?) and "pick" (which feature) before them.
    var panels = {};
    var numbers = [];
    panelList.forEach(function (panel) {
        var name = panel.getAttribute('data-bw-panel');
        panels[name] = panel;
        if (/^\d+$/.test(name)) {
            numbers.push(parseInt(name, 10));
        }
    });
    var LAST = numbers.length ? Math.max.apply(null, numbers) : 0; // the final panel
    var STEPS = LAST;                                               // feature steps before it
    var FINAL = String(LAST);
    var hasIntent = !!panels.intent;
    var view = hasIntent ? 'intent' : '0';
    var seen = 0;
    var way = '';
    var feature = '';
    var busy = false;
    var chosen = null; // the feature kept, once saved: {key, url, name, screen, desc}
    var doneTimer = 0;
    var closing = false;
    var dismissed = false;
    var downOnBackdrop = false;
    var returnFocus = null;

    function moving() {
        return !(reduced && reduced.matches);
    }

    function focusQuietly(el) {
        if (!el || el.hidden || typeof el.focus !== 'function') {
            return;
        }
        try {
            el.focus({ preventScroll: true });
        } catch (e) {
            el.focus();
        }
    }

    /* ── Bricks ───────────────────────────────────────────────────────────── */
    // Bricks that land together drop one after another.
    function setBrick(i, on, delay) {
        [bricks[i], shadows[i], slots[i]].forEach(function (el) {
            if (!el) {
                return;
            }
            el.style.setProperty('--bd', delay + 'ms');
            el.classList.toggle('is-in', on);
        });
    }

    function layBricks(count, pulse) {
        var fresh = 0;
        for (var i = 0; i < bricks.length; i++) {
            var want = i < count;
            if (want && !bricks[i].classList.contains('is-in')) {
                setBrick(i, true, fresh * 140);
                fresh++;
            } else if (!want && bricks[i].classList.contains('is-in')) {
                setBrick(i, false, 0);
            }
            if (slots[i]) {
                slots[i].classList.toggle('is-next', pulse !== false && !want && i === count);
            }
        }
        return fresh;
    }

    // The final panel flattens the plate into the logo once the last brick is down.
    function settle(final, fresh) {
        clearTimeout(doneTimer);
        if (!stage) {
            return;
        }
        if (!final) {
            stage.classList.remove('is-done');
            return;
        }
        var wait = moving() ? (fresh ? (fresh - 1) * 140 + 1100 : 150) : 0;
        doneTimer = setTimeout(function () {
            stage.classList.add('is-done');
        }, wait);
    }

    /* ── Views ────────────────────────────────────────────────────────────── */
    function isStep(v) {
        return /^\d+$/.test(v) && parseInt(v, 10) < STEPS;
    }

    function isFull(v) {
        return v === 'intent' || v === 'pick';
    }

    function footer() {
        var final = view === FINAL;
        var stepNo = isStep(view) ? parseInt(view, 10) : -1;
        if (btnSkip) {
            btnSkip.hidden = !(view === 'intent' || stepNo >= 0);
        }
        if (btnPrev) {
            btnPrev.hidden = !(view === 'pick' || stepNo > 0 || (stepNo === 0 && hasIntent));
        }
        if (btnNext) {
            btnNext.hidden = final;
            var label = i18n.next;
            var off = false;
            if (view === 'intent') {
                off = !way;
                label = way ? i18n.next : i18n.pick_one;
            } else if (view === 'pick') {
                off = !feature || busy;
                label = feature ? i18n['continue'] : i18n.pick_feature;
            } else if (stepNo === STEPS - 1) {
                label = i18n.finish;
            }
            btnNext.disabled = off;
            if (nextLabel && label) {
                nextLabel.textContent = label;
            }
        }
        if (btnGo) {
            btnGo.hidden = !final;
            if (chosen && goLabel) {
                goLabel.textContent = chosen.url && chosen.screen
                    ? String(i18n.open_screen || '%s').replace('%s', chosen.screen)
                    : (i18n.close || goLabel.textContent);
            }
        }
    }

    function show(v) {
        if (!panels[v]) {
            return;
        }
        var was = view;
        var final = v === FINAL;
        var stepNo = isStep(v) ? parseInt(v, 10) : -1;
        var active = document.activeElement;
        // Buttons about to hide: move focus first so it never falls out of the dialog.
        var hiding = (active === btnPrev || active === btnSkip || active === btnNext);

        view = v;
        if (stepNo >= 0) {
            seen = Math.max(seen, stepNo);
        } else if (final) {
            seen = STEPS - 1;
        }

        Object.keys(panels).forEach(function (name) {
            panels[name].hidden = name !== v;
        });
        if (panelBox) {
            panelBox.scrollTop = 0;
        }

        // The first two screens spread over the whole window; the plate comes
        // back when the tour goes on.
        dialog.setAttribute('data-bw-view', isFull(v) ? v : (chosen && final ? 'one' : 'tour'));
        if (left && !isFull(v) && isFull(was) && moving() && window.matchMedia && window.matchMedia('(min-width: 783px)').matches) {
            left.classList.remove('is-entering');
            void left.offsetWidth;
            left.classList.add('is-entering');
        }
        var labelled = v === 'intent' ? 'brikpanel-welcome-intent-title' : (v === 'pick' ? 'brikpanel-welcome-pick-title' : 'brikpanel-welcome-title');
        if (document.getElementById(labelled)) {
            dialog.setAttribute('aria-labelledby', labelled);
        }

        var oneMode = !!chosen && final;
        startEls.forEach(function (el) {
            el.hidden = final;
        });
        doneEls.forEach(function (el) {
            el.hidden = !final;
        });
        oneEls.forEach(function (el) {
            el.hidden = !oneMode;
        });
        fullOnlyEls.forEach(function (el) {
            if (oneMode) {
                el.hidden = true;
            } else if (el.hasAttribute('data-bw-done')) {
                el.hidden = !final;
            } else {
                el.hidden = false;
            }
        });
        if (oneLinks) {
            oneLinks.hidden = !(oneMode && chosen.url);
        }

        tabs.forEach(function (tab, n) {
            var current = n === stepNo;
            tab.classList.toggle('is-active', current);
            tab.classList.toggle('is-seen', !current && (final || (stepNo >= 0 && n <= seen)));
            if (current) {
                tab.setAttribute('aria-current', 'step');
            } else {
                tab.removeAttribute('aria-current');
            }
        });

        footer();

        var kicker = panels[v].querySelector('[data-bw-kicker]');
        if (live && kicker) {
            live.textContent = kicker.textContent;
        }

        if (hiding && (!active || active.hidden || active.disabled)) {
            focusQuietly(final ? btnGo : (btnNext && !btnNext.hidden ? btnNext : modal));
        }

        // A frame after the panel shows, so the drop animates. One feature
        // lays its one brick and keeps the plate as it is.
        var count = 0;
        if (stepNo >= 0) {
            count = seen + 1;
        } else if (final) {
            count = chosen ? 1 : STEPS;
        }
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                settle(final && !chosen, layBricks(count, stepNo >= 0));
            });
        });
    }

    /* ── First screen: the two ways ───────────────────────────────────────── */
    function radios(name, attr, set) {
        all('input[name="' + name + '"]').forEach(function (input) {
            var card = input.closest('[' + attr + ']');
            input.addEventListener('change', function () {
                set(input.value);
                all('[' + attr + ']').forEach(function (c) {
                    c.classList.toggle('is-picked', c === card);
                });
                footer();
            });
            input.addEventListener('focus', function () {
                var keyboard = false;
                try {
                    keyboard = input.matches(':focus-visible');
                } catch (e) {
                    keyboard = false;
                }
                if (card) {
                    card.classList.toggle('is-focus', keyboard);
                }
            });
            input.addEventListener('blur', function () {
                if (card) {
                    card.classList.remove('is-focus');
                }
            });
        });
    }
    radios('brikpanel-welcome-way', 'data-bw-way', function (v) {
        way = v;
    });
    radios('brikpanel-welcome-feature', 'data-bw-feature', function (v) {
        feature = v;
        var error = one('[data-bw-pick-error]');
        if (error) {
            error.hidden = true;
        }
    });

    /* ── "See it bigger": the full panel card's screens, side by side ─────── */
    var compare = one('[data-bw-compare]');
    var cFrame = one('[data-bw-compare-frame]');
    var cBefore = one('[data-bw-compare-before]');
    var cAfter = one('[data-bw-compare-after]');
    var cRange = one('[data-bw-compare-range]');
    var cTabs = all('[data-bw-compare-tab]');
    var cOpener = one('[data-bw-ba-open]');

    function compareTab(key) {
        var b = one('[data-bw-ba-img="' + key + '-before"]');
        var a = one('[data-bw-ba-img="' + key + '-after"]');
        if (b && a && cBefore && cAfter) {
            cBefore.src = b.getAttribute('src');
            cAfter.src = a.getAttribute('src');
        }
        cTabs.forEach(function (t) {
            t.setAttribute('aria-pressed', t.getAttribute('data-bw-compare-tab') === key ? 'true' : 'false');
        });
    }

    function compareCut(value) {
        if (cFrame) {
            cFrame.style.setProperty('--bw-cut', value + '%');
        }
    }

    function compareOpen() {
        compareTab('a');
        cRange.value = '50';
        compareCut(50);
        compare.hidden = false;
        cOpener.setAttribute('aria-expanded', 'true');
        focusQuietly(cRange);
    }

    function compareClose(focusBack) {
        compare.hidden = true;
        cOpener.setAttribute('aria-expanded', 'false');
        if (focusBack !== false) {
            focusQuietly(cOpener);
        }
    }

    if (compare && cOpener && cRange && cFrame) {
        cOpener.addEventListener('click', compareOpen);
        cTabs.forEach(function (t) {
            t.addEventListener('click', function () {
                compareTab(t.getAttribute('data-bw-compare-tab'));
            });
        });
        cRange.addEventListener('input', function () {
            compareCut(cRange.value);
        });
        cRange.addEventListener('focus', function () {
            var keyboard = false;
            try {
                keyboard = cRange.matches(':focus-visible');
            } catch (e) {
                keyboard = false;
            }
            cFrame.classList.toggle('is-focus', keyboard);
        });
        cRange.addEventListener('blur', function () {
            cFrame.classList.remove('is-focus');
        });
        var cClose = one('[data-bw-compare-close]');
        if (cClose) {
            cClose.addEventListener('click', function () {
                compareClose();
            });
        }
        var cPick = one('[data-bw-compare-pick]');
        if (cPick) {
            cPick.addEventListener('click', function () {
                var full = one('input[name="brikpanel-welcome-way"][value="full"]');
                if (full) {
                    full.checked = true;
                    full.dispatchEvent(new Event('change', { bubbles: true }));
                }
                compareClose(false);
                focusQuietly(btnNext);
            });
        }
    }

    /* ── Just one feature: save, then the last panel with that one link ───── */
    function fillChosen() {
        var link = one('[data-bw-one-link]');
        all('[data-bw-one-name]').forEach(function (el) {
            el.textContent = chosen.name;
        });
        if (!link) {
            return;
        }
        link.href = chosen.url || '#';
        link.removeAttribute('data-bw-here');
        var title = one('[data-bw-one-title]');
        var desc = one('[data-bw-one-desc]');
        var icon = one('[data-bw-one-icon]');
        if (title) {
            title.textContent = chosen.screen;
        }
        if (desc) {
            desc.textContent = chosen.desc;
        }
        var svg = one('[data-bw-feature="' + chosen.key + '"] .brikpanel-welcome-pick-icon > svg');
        if (icon && svg) {
            icon.textContent = '';
            icon.appendChild(svg.cloneNode(true));
        }
        if (btnGo && btnGo.tagName === 'A') {
            // The last button opens the feature; with no screen of its own
            // (the login page) it reloads the page, now WordPress's own.
            btnGo.href = chosen.url || window.location.href;
            btnGo.removeAttribute('data-bw-here');
        }
    }

    function apply() {
        if (!feature || busy || !cfg.ajax_url || !cfg.nonce || !window.fetch) {
            return;
        }
        busy = true;
        footer();
        var error = one('[data-bw-pick-error]');
        var fd = new FormData();
        fd.append('action', 'brikpanel_welcome_intent');
        fd.append('_wpnonce', cfg.nonce);
        fd.append('feature', feature);
        window.fetch(cfg.ajax_url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (res) {
            if (!res || !res.success) {
                throw new Error();
            }
            var f = (cfg.features || {})[feature] || {};
            chosen = {
                key: feature,
                url: (res.data && res.data.url) || '',
                name: f.name || '',
                screen: f.screen || '',
                desc: f.desc || ''
            };
            busy = false;
            fillChosen();
            show(FINAL);
        }).catch(function () {
            busy = false;
            footer();
            if (error) {
                error.textContent = i18n.apply_error || '';
                error.hidden = false;
            }
        });
    }

    function next() {
        if (view === 'intent') {
            if (!way) {
                return;
            }
            show(way === 'full' ? '0' : 'pick');
            focusQuietly(btnNext);
        } else if (view === 'pick') {
            apply();
        } else if (isStep(view)) {
            show(String(parseInt(view, 10) + 1));
        }
    }

    function prev() {
        if (view === 'pick' || (view === '0' && hasIntent)) {
            show('intent');
        } else if (isStep(view) && parseInt(view, 10) > 0) {
            show(String(parseInt(view, 10) - 1));
        }
    }

    /* ── Open, close, dismiss ─────────────────────────────────────────────── */
    function dismiss() {
        if (dismissed || !cfg.ajax_url || !cfg.nonce) {
            return;
        }
        dismissed = true;
        var fd = new FormData();
        fd.append('action', 'brikpanel_dismiss_welcome');
        fd.append('_wpnonce', cfg.nonce);
        // sendBeacon survives a page change on every browser; fetch is the fallback.
        if (navigator.sendBeacon) {
            try {
                if (navigator.sendBeacon(cfg.ajax_url, fd)) {
                    return;
                }
            } catch (e) {
                // fall through to fetch
            }
        }
        if (window.fetch) {
            window.fetch(cfg.ajax_url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        }
    }

    // A link to another screen waits for the dismissal to be saved (at most a
    // moment), or the next page would open the tour again.
    function dismissThen(next) {
        var called = false;
        var go = function () {
            if (!called) {
                called = true;
                next();
            }
        };
        setTimeout(go, 1200);
        if (dismissed || !cfg.ajax_url || !cfg.nonce || !window.fetch) {
            dismiss();
            go();
            return;
        }
        dismissed = true;
        var fd = new FormData();
        fd.append('action', 'brikpanel_dismiss_welcome');
        fd.append('_wpnonce', cfg.nonce);
        window.fetch(cfg.ajax_url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            keepalive: true
        }).then(go, go);
    }

    /* ── Where did you first hear about BrikPanel? ────────────────────────── */
    // One tap sends the answer, there is no Send button. A random token made
    // for this tour lets a changed answer or a note replace the first row on
    // brksoft.com instead of adding a second one. The request goes to
    // admin-ajax (front-end/welcome/brikpanel-welcome-source.php), which passes
    // it on without the site address. sendBeacon so a tap followed at once by a
    // link to another screen still arrives.
    var source = one('[data-bw-source]');
    var sourceAnswer = '';
    var sourceNoteSent = '';
    var sourceToken = '';
    var sourceInput = null;
    var sourceMore = null;
    // Each request of this tour is numbered: they travel side by side and may
    // arrive in another order, and brksoft.com keeps only the newest one.
    var sourceSeq = 0;

    function sourceTokenFor() {
        var bytes = new Uint8Array(16);
        var c = window.crypto || window.msCrypto;
        if (c && c.getRandomValues) {
            c.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }
        var hex = '';
        for (var j = 0; j < bytes.length; j++) {
            hex += (bytes[j] < 16 ? '0' : '') + bytes[j].toString(16);
        }
        return hex;
    }

    function sourcePost(note) {
        if (!sourceAnswer || !cfg.ajax_url || !cfg.nonce) {
            return;
        }
        var fd = new FormData();
        fd.append('action', 'brikpanel_welcome_source');
        fd.append('_wpnonce', cfg.nonce);
        fd.append('answer', sourceAnswer);
        fd.append('token', sourceToken);
        fd.append('detail', note || '');
        sourceSeq += 1;
        fd.append('seq', String(sourceSeq));
        if (navigator.sendBeacon) {
            try {
                if (navigator.sendBeacon(cfg.ajax_url, fd)) {
                    return;
                }
            } catch (e) {
                // fall through to fetch
            }
        }
        if (window.fetch) {
            window.fetch(cfg.ajax_url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        }
    }

    // A note typed but not sent goes out when the tour closes or a link is followed.
    function sourceFlush() {
        // Only while the note box belongs to the picked answer.
        if (!sourceInput || !sourceAnswer || !sourceMore || sourceMore.hidden) {
            return;
        }
        var note = sourceInput.value.trim();
        if (note && note !== sourceNoteSent) {
            sourceNoteSent = note;
            sourcePost(note);
        }
    }

    function sourceInit(box) {
        var opts = Array.prototype.slice.call(box.querySelectorAll('[data-bw-answer]'));
        var more = box.querySelector('[data-bw-source-more]');
        sourceMore = more;
        var send = box.querySelector('[data-bw-source-send]');
        var hint = box.querySelector('[data-bw-source-hint]');
        var thanks = box.querySelector('[data-bw-source-thanks]');
        var arrow = box.querySelector('[data-bw-source-arrow]');
        var tick = box.querySelector('[data-bw-source-tick]');
        var said = box.querySelector('[data-bw-source-live]');
        var sendLabel = send ? send.textContent : '';
        sourceInput = box.querySelector('[data-bw-source-input]');
        sourceToken = sourceTokenFor();

        function announce(text) {
            if (said && text) {
                said.textContent = '';
                // A fresh text node, so a screen reader reads the same words again.
                window.setTimeout(function () {
                    said.textContent = text;
                }, 30);
            }
        }

        function resetSend() {
            if (send) {
                send.textContent = sendLabel;
                send.disabled = false;
            }
        }

        opts.forEach(function (opt) {
            opt.addEventListener('click', function () {
                var key = opt.getAttribute('data-bw-answer') || '';
                if (!key || key === sourceAnswer) {
                    return;
                }
                var first = !sourceAnswer;
                sourceAnswer = key;
                sourceNoteSent = '';
                opts.forEach(function (o) {
                    o.setAttribute('aria-pressed', o === opt ? 'true' : 'false');
                });
                box.classList.add('is-answered');

                var prompt = opt.getAttribute('data-bw-prompt') || '';
                if (more && sourceInput) {
                    more.hidden = !prompt;
                    sourceInput.value = '';
                    sourceInput.placeholder = prompt;
                    resetSend();
                }
                if (first) {
                    if (hint) {
                        hint.hidden = true;
                    }
                    if (thanks) {
                        thanks.hidden = false;
                    }
                    // An svg has no `hidden` property, only the attribute.
                    if (arrow) {
                        arrow.setAttribute('hidden', '');
                    }
                    if (tick) {
                        tick.removeAttribute('hidden');
                    }
                    announce(i18n.source_thanks);
                }
                sourcePost('');
            });
        });

        function sendNote() {
            if (!sourceInput || !sourceAnswer) {
                return;
            }
            var note = sourceInput.value.trim();
            if (!note || note === sourceNoteSent) {
                return;
            }
            sourceNoteSent = note;
            sourcePost(note);
            if (send && i18n.source_sent) {
                send.textContent = i18n.source_sent;
                send.disabled = true;
            }
            announce(i18n.source_noted);
        }

        if (send) {
            send.addEventListener('click', sendNote);
        }
        if (sourceInput) {
            sourceInput.addEventListener('input', function () {
                if (sourceInput.value.trim() !== sourceNoteSent) {
                    resetSend();
                }
            });
            sourceInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.isComposing) {
                    e.preventDefault();
                    sendNote();
                }
            });
        }
    }

    if (source) {
        sourceInit(source);
    }

    function swallowSearch(e) {
        // Ctrl/Cmd+K would open the search palette behind the tour.
        if (dialog.open && (e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }

    function finish() {
        if (dialog.open) {
            dialog.close();
        }
        dialog.classList.remove('is-closing');
        root.classList.remove('brikpanel-welcome-lock');
        window.removeEventListener('keydown', swallowSearch, true);
        clearTimeout(doneTimer);
        // One feature was kept: the admin behind the tour is now WordPress's own.
        if (chosen) {
            window.location.reload();
            return;
        }
        if (returnFocus && document.contains(returnFocus)) {
            focusQuietly(returnFocus);
        }
    }

    function close() {
        if (closing || !dialog.open) {
            return;
        }
        closing = true;
        sourceFlush();
        dismiss();
        if (!moving()) {
            finish();
            return;
        }
        dialog.classList.add('is-closing');
        setTimeout(finish, 220);
    }

    function open() {
        returnFocus = document.activeElement;
        try {
            dialog.showModal();
        } catch (e) {
            return;
        }
        root.classList.add('brikpanel-welcome-lock');
        window.addEventListener('keydown', swallowSearch, true);
        show(view);
        focusQuietly(modal);
    }

    /* ── Events ───────────────────────────────────────────────────────────── */
    // Escape: out of the bigger view first, then out of the tour.
    dialog.addEventListener('cancel', function (e) {
        e.preventDefault();
        if (compare && !compare.hidden) {
            compareClose();
            return;
        }
        close();
    });

    // A click on the dialog itself is a click on the backdrop. It has to start
    // there too, so a text selection that ends outside does not close the tour.
    dialog.addEventListener('pointerdown', function (e) {
        downOnBackdrop = e.target === dialog;
    });
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog && downOnBackdrop) {
            close();
        }
        downOnBackdrop = false;
    });

    dialog.addEventListener('keydown', function (e) {
        if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) {
            return;
        }
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
            return;
        }
        // Only between the steps and the final panel: the first screens are
        // choices, and their radio buttons use the arrows themselves.
        if (!(isStep(view) || (view === FINAL && !chosen))) {
            return;
        }
        // In the note box the arrows move the caret, not the tour.
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) {
            return;
        }
        var rtl = window.getComputedStyle(dialog).direction === 'rtl';
        var forward = (e.key === 'ArrowRight') !== rtl;
        var at = parseInt(view, 10);
        var to = Math.max(0, Math.min(LAST, at + (forward ? 1 : -1)));
        e.preventDefault();
        show(String(to));
    });

    all('[data-bw-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    if (btnSkip) {
        btnSkip.addEventListener('click', function () {
            show(FINAL);
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', prev);
    }
    if (btnNext) {
        btnNext.addEventListener('click', next);
    }
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            show(String(parseInt(tab.getAttribute('data-bw-goto'), 10) || 0));
        });
    });

    // Links dismiss before the browser follows them; the one to the page you are
    // already on only closes the tour. A click that opens a new tab leaves this
    // page as it is.
    all('[data-bw-cta]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            sourceFlush();
            if (link.hasAttribute('data-bw-here')) {
                e.preventDefault();
                close();
                return;
            }
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                dismiss();
                return;
            }
            e.preventDefault();
            dismissThen(function () {
                window.location.href = link.href;
            });
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', open);
    } else {
        open();
    }
})();
