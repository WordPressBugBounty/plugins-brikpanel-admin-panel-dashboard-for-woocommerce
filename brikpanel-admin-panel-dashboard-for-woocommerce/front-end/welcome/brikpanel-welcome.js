/**
 * BrikPanel: welcome tour.
 *
 * Opens the tour dialog, moves between the four steps and the final panel,
 * lays a brick on the plate for every step seen, flattens the plate into the
 * logo on the final panel and dismisses the tour for good when it closes.
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

    var panels = all('[data-bw-panel]');
    var panelBox = one('.brikpanel-welcome-panels');
    var tabs = all('[data-bw-goto]');
    var startEls = all('[data-bw-start]');
    var doneEls = all('[data-bw-done]');
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
    var modal = one('[data-bw-modal]');

    var LAST = panels.length - 1; // the final panel
    var STEPS = LAST;             // feature steps before it
    var step = 0;
    var seen = 0;
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

    function layBricks(count) {
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
                slots[i].classList.toggle('is-next', !want && i === count);
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

    /* ── Steps ────────────────────────────────────────────────────────────── */
    function goTo(i) {
        i = Math.max(0, Math.min(LAST, i));
        var final = i === LAST;
        var active = document.activeElement;
        // Back on the first step, Skip and Next on the final panel are about to
        // hide: move focus first so it never falls out of the dialog.
        var hiding = (active === btnPrev && i === 0) || (final && (active === btnSkip || active === btnNext));

        step = i;
        seen = final ? STEPS - 1 : Math.max(seen, i);

        panels.forEach(function (panel, n) {
            panel.hidden = n !== i;
        });
        if (panelBox) {
            panelBox.scrollTop = 0;
        }
        startEls.forEach(function (el) {
            el.hidden = final;
        });
        doneEls.forEach(function (el) {
            el.hidden = !final;
        });

        tabs.forEach(function (tab, n) {
            var current = n === i;
            tab.classList.toggle('is-active', current);
            tab.classList.toggle('is-seen', !current && (final || n <= seen));
            if (current) {
                tab.setAttribute('aria-current', 'step');
            } else {
                tab.removeAttribute('aria-current');
            }
        });

        if (btnPrev) {
            btnPrev.hidden = i === 0;
        }
        if (btnSkip) {
            btnSkip.hidden = final;
        }
        if (btnNext) {
            btnNext.hidden = final;
        }
        if (btnGo) {
            btnGo.hidden = !final;
        }
        if (nextLabel) {
            var label = i === STEPS - 1 ? i18n.finish : i18n.next;
            if (label) {
                nextLabel.textContent = label;
            }
        }

        var kicker = panels[i] ? panels[i].querySelector('[data-bw-kicker]') : null;
        if (live && kicker) {
            live.textContent = kicker.textContent;
        }

        if (hiding) {
            focusQuietly(final ? btnGo : btnNext);
        }

        // A frame after the panel shows, so the drop animates.
        var count = final ? STEPS : seen + 1;
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                settle(final, layBricks(count));
            });
        });
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
        goTo(0);
        focusQuietly(modal);
    }

    /* ── Events ───────────────────────────────────────────────────────────── */
    // Escape.
    dialog.addEventListener('cancel', function (e) {
        e.preventDefault();
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
        // In the note box the arrows move the caret, not the tour.
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) {
            return;
        }
        var rtl = window.getComputedStyle(dialog).direction === 'rtl';
        var forward = (e.key === 'ArrowRight') !== rtl;
        e.preventDefault();
        goTo(step + (forward ? 1 : -1));
    });

    all('[data-bw-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    if (btnSkip) {
        btnSkip.addEventListener('click', function () {
            goTo(LAST);
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            goTo(step - 1);
        });
    }
    if (btnNext) {
        btnNext.addEventListener('click', function () {
            goTo(step + 1);
        });
    }
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            goTo(parseInt(tab.getAttribute('data-bw-goto'), 10) || 0);
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
