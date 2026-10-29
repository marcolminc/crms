/**
 * Front-end behaviour for the vehicle catalogue:
 *
 *  - auto-rotating photo carousels (they only tick while on screen),
 *  - staggered scroll reveals for fleet cards,
 *  - infinite scroll layered on top of real server pagination,
 *  - live server-side filtering of the fleet (keyword as you type),
 *  - a photo picker preview on the admin vehicle form.
 *
 * Everything degrades: without JS the classic pagination links and all cards
 * stay visible, carousels simply show their first photo.
 */

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

document.documentElement.classList.add('js');

// ---------------------------------------------------------------------------
// Photo carousels
// ---------------------------------------------------------------------------

const upgradedCarousels = new WeakSet();

function initCarousels(root = document) {
    root.querySelectorAll('[data-carousel]').forEach((car) => {
        if (upgradedCarousels.has(car)) {
            return;
        }
        upgradedCarousels.add(car);

        const track = car.querySelector('[data-carousel-track]');
        const dots = car.querySelector('[data-carousel-dots]');
        const counter = car.querySelector('[data-carousel-count]');
        const toggle = car.querySelector('[data-carousel-toggle]');
        const total = track.children.length;

        if (total < 2) {
            return;
        }

        const ms = parseInt(car.dataset.interval || '3200', 10);
        let index = 0;
        let timer = null;
        let userPaused = false;

        // Dots double as angle shortcuts: "Front", "Interior", ... They only
        // exist on the detail page; cards just autoplay without controls so the
        // whole card stays a clean click-through link.
        const labels = [...track.children].map((slide) => slide.dataset.slideLabel || '');

        if (dots) {
            for (let d = 0; d < total; d += 1) {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('aria-label', labels[d]
                    ? `Show ${labels[d]} photo`
                    : `Show photo ${d + 1} of ${total}`);
                button.addEventListener('click', () => {
                    show(d);
                    restart();
                });
                dots.append(button);
            }
        }

        function render() {
            track.style.transform = `translateX(-${index * 100}%)`;
            if (dots) {
                [...dots.children].forEach((b, d) => b.classList.toggle('vc-dot-active', d === index));
            }

            // The card pill stays a static "N photos" cue; only the detail page
            // shows the live position counter.
            if (counter && dots) {
                counter.textContent = `${index + 1}/${total}`;
            }
        }

        function show(i) {
            index = (i + total) % total;
            render();
        }

        function start() {
            if (!reducedMotion && !userPaused && timer === null) {
                timer = window.setInterval(() => show(index + 1), ms);
            }
        }

        function stop() {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        }

        function restart() {
            stop();
            start();
        }

        car.addEventListener('mouseenter', stop);
        car.addEventListener('mouseleave', start);
        car.addEventListener('focusin', stop);
        car.addEventListener('focusout', start);

        const prev = car.querySelector('[data-carousel-prev]');
        const next = car.querySelector('[data-carousel-next]');

        if (prev) {
            prev.addEventListener('click', (event) => {
                event.preventDefault();
                show(index - 1);
                restart();
            });
        }
        if (next) {
            next.addEventListener('click', (event) => {
                event.preventDefault();
                show(index + 1);
                restart();
            });
        }

        // Manual play/pause wins over hover/scroll: once paused it stays paused
        // until the reader presses play again.
        if (toggle) {
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                userPaused = !userPaused;

                if (userPaused) {
                    stop();
                } else {
                    start();
                }

                toggle.setAttribute('aria-pressed', String(userPaused));
                toggle.setAttribute('aria-label', userPaused ? 'Play slideshow' : 'Pause slideshow');

                const pauseIcon = toggle.querySelector('[data-icon-pause]');
                const playIcon = toggle.querySelector('[data-icon-play]');
                if (pauseIcon) {
                    pauseIcon.classList.toggle('hidden', userPaused);
                }
                if (playIcon) {
                    playIcon.classList.toggle('hidden', !userPaused);
                }
            });
        }

        let touchX = null;
        track.addEventListener('touchstart', (event) => {
            touchX = event.touches[0].clientX;
            stop();
        }, { passive: true });
        track.addEventListener('touchend', (event) => {
            if (touchX === null) {
                return;
            }
            const dx = event.changedTouches[0].clientX - touchX;

            if (Math.abs(dx) > 32) {
                show(index + (dx < 0 ? 1 : -1));
            }
            touchX = null;
            start();
        }, { passive: true });

        if ('IntersectionObserver' in window) {
            // Save cycles on carousels the reader cannot see.
            new IntersectionObserver(([entry]) => (entry.isIntersecting ? start() : stop()), { threshold: 0.2 })
                .observe(car);
        } else {
            start();
        }

        render();
    });
}

// ---------------------------------------------------------------------------
// Scroll reveals
// ---------------------------------------------------------------------------

const revealObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 })
    : null;

function initReveal(root = document) {
    const pending = root.querySelectorAll('[data-reveal]:not(.revealed)');

    if (revealObserver === null || reducedMotion) {
        pending.forEach((el) => el.classList.add('revealed'));

        return;
    }

    pending.forEach((el) => revealObserver.observe(el));
}

// ---------------------------------------------------------------------------
// Fleet grid: infinite scroll + live server-side filtering
// ---------------------------------------------------------------------------

// Set by initFleet when the grid is manageable; the filter wiring checks it
// before hijacking the form so a page without the controller (no JS support)
// still submits the GET form normally.
let fleetSearch = null;

function initFleet() {
    const sentinel = document.querySelector('[data-infinite]');
    const grid = sentinel ? document.querySelector(sentinel.dataset.grid || '#fleet-grid') : null;

    if (!sentinel || !grid || !('IntersectionObserver' in window)) {
        return;
    }

    const spinner = sentinel.querySelector('[data-infinite-spinner]');
    const shownEl = sentinel.querySelector('[data-infinite-shown]');
    const totalEl = sentinel.querySelector('[data-infinite-total]');
    const endEl = sentinel.querySelector('[data-infinite-end]');
    const progress = sentinel.querySelector('[data-infinite-progress]');
    const bar = sentinel.querySelector('[data-infinite-bar]');
    const paginate = document.querySelector('[data-infinite-paginate]');

    let next = sentinel.dataset.next || null;
    let loading = false;
    let token = 0; // Guards against an in-flight page append landing after a search reset.

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            loadMore();
        }
    }, { rootMargin: '600px 0px' });

    observer.observe(sentinel);

    if (paginate && next) {
        paginate.hidden = true; // The numbered pager is only the no-JS fallback.
    }

    if (!next) {
        finish();
    }

    function finish() {
        observer.disconnect();

        if (endEl) {
            endEl.hidden = false;
        }
    }

    function paintMeta(data) {
        if (shownEl) {
            shownEl.textContent = data.shown;
        }
        if (totalEl) {
            totalEl.textContent = data.total;
        }
        if (bar) {
            bar.hidden = data.shown >= data.total;
        }
        if (progress && data.total > 0) {
            progress.style.width = `${Math.round((data.shown / data.total) * 100)}%`;
        }
    }

    function mount(html, append) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();

        if (!append) {
            grid.innerHTML = '';
        }

        [...template.content.children].forEach((el, i) => {
            el.style.setProperty('--reveal-delay', `${((grid.children.length + i) % 3) * 90}ms`);
            grid.append(el);
        });

        initCarousels(grid);
        initReveal(grid);
    }

    async function fetchPage(url) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const response = await fetch(url, { headers });

        if (!response.ok) {
            throw new Error(`Fleet page came back ${response.status}`);
        }

        return response.json();
    }

    async function loadMore() {
        if (loading || !next) {
            return;
        }
        const myToken = token;
        loading = true;

        if (spinner) {
            spinner.hidden = false;
        }

        try {
            const data = await fetchPage(next);

            if (myToken !== token) {
                return; // A search reset the grid while this page was in flight.
            }

            mount(data.html, true);
            paintMeta(data);
            next = data.next;

            if (!next) {
                if (paginate) {
                    paginate.hidden = true;
                }
                finish();
            }
        } catch (error) {
            // Anything went wrong: hand the classic pager back to the reader.
            next = null;

            if (paginate) {
                paginate.hidden = false;
            }
        } finally {
            if (myToken === token) {
                loading = false;

                if (spinner) {
                    spinner.hidden = true;
                }
            }
        }
    }

    // Replace the grid with page one of a brand-new query, then let the reader
    // keep infinite-scrolling through that result set.
    fleetSearch = async function search(params, baseUrl) {
        const url = new URL(baseUrl, window.location.href);
        url.search = '';

        Object.entries(params).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                url.searchParams.set(key, value);
            }
        });
        url.searchParams.set('partial', '1');

        const myToken = ++token;
        loading = true;

        if (spinner) {
            spinner.hidden = false;
        }
        if (endEl) {
            endEl.hidden = true;
        }

        try {
            const data = await fetchPage(url.toString());

            if (myToken !== token) {
                return null; // Superseded by a more recent keystroke.
            }

            mount(data.html, false);
            paintMeta(data);
            next = data.next;

            if (paginate) {
                paginate.hidden = Boolean(next);
            }

            observer.disconnect();

            if (next) {
                observer.observe(sentinel);
            } else {
                finish();
            }

            return url;
        } catch (error) {
            // The AJAX path failed; fall back to a real navigation so the
            // filters still apply exactly as they would without JavaScript.
            url.searchParams.delete('partial');
            window.location.href = url.toString();
            return null;
        } finally {
            if (myToken === token) {
                loading = false;

                if (spinner) {
                    spinner.hidden = true;
                }
            }
        }
    };
}

// ---------------------------------------------------------------------------
// Live vehicle filters
// ---------------------------------------------------------------------------

function initFleetFilters() {
    const form = document.querySelector('[data-fleet-filter]');

    if (!form) {
        return;
    }

    const baseUrl = form.getAttribute('action') || window.location.pathname;
    const keyword = form.querySelector('[data-fleet-q]');

    function currentParams() {
        const params = {};

        new FormData(form).forEach((value, key) => {
            params[key] = typeof value === 'string' ? value.trim() : '';
        });

        return params;
    }

    async function run() {
        // Without the grid controller (e.g. IntersectionObserver unsupported)
        // leave the plain GET submit / link navigation to the browser.
        if (typeof fleetSearch !== 'function') {
            return;
        }

        const url = await fleetSearch(currentParams(), baseUrl);

        if (url) {
            const clean = new URL(url.toString());
            clean.searchParams.delete('partial');
            window.history.replaceState({}, '', clean.toString());
        }
    }

    let timer = null;

    // Keyword filters as the reader types, debounced so a burst of keystrokes
    // is a single request rather than one per character.
    if (keyword) {
        keyword.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 350);
        });
    }

    // Selects, numbers and dates have discrete values, so apply on change.
    form.querySelectorAll('[data-fleet-apply]').forEach((control) => {
        control.addEventListener('change', () => {
            window.clearTimeout(timer);
            run();
        });
    });

    form.addEventListener('submit', (event) => {
        if (typeof fleetSearch !== 'function') {
            return; // No controller: let the browser perform the GET.
        }
        event.preventDefault();
        window.clearTimeout(timer);
        run();
    });
}

// ---------------------------------------------------------------------------
// Admin photo slot picker
// ---------------------------------------------------------------------------

function initPhotoManager() {
    document.querySelectorAll('[data-photo-manager] [data-slot]').forEach((slot) => {
        const input = slot.querySelector('input[type="file"]');
        const pick = slot.querySelector('[data-slot-pick]');
        const preview = slot.querySelector('[data-slot-preview]');
        const previewImg = preview ? preview.querySelector('img') : null;
        const previewName = preview ? preview.querySelector('[data-slot-preview-name]') : null;
        const removeBox = slot.querySelector('[data-slot-remove]');

        if (!input || !pick || !preview) {
            return;
        }

        pick.addEventListener('click', () => input.click());

        input.addEventListener('change', () => {
            const file = input.files[0];

            if (!file) {
                preview.classList.add('hidden');

                return;
            }

            // A replacement photo wins over a pending "remove" tick on the
            // same slot, so stop the checkbox from confusing the staff.
            if (removeBox) {
                removeBox.checked = false;
            }

            const url = URL.createObjectURL(file);
            previewImg.src = url;
            previewName.textContent = file.name;
            preview.classList.remove('hidden');
            previewImg.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
        });
    });
}

// ---------------------------------------------------------------------------
// Dynamic keyword search (readme: search replaces filter forms)
// ---------------------------------------------------------------------------

// Admin list pages mark their GET filter form [data-live-search]; a debounced
// keystroke auto-submits it, so no "Apply filters" button is needed. This is
// a plain navigation (not AJAX) on purpose: pagination and deep links keep
// working, and without JS the form still submits normally.
function initLiveSearch() {
    document.querySelectorAll('form[data-live-search]').forEach((form) => {
        const input = form.querySelector('[data-live-q]');
        const clear = form.querySelector('[data-live-clear]');
        const card = form.closest('.live-search-card');
        let timer = null;
        let last = input ? input.value : null;

        function busy() {
            if (card) {
                card.classList.add('is-searching');
            }
        }

        function submit() {
            busy();
            form.submit();
        }

        if (input) {
            input.addEventListener('input', () => {
                window.clearTimeout(timer);

                if (clear) {
                    clear.toggleAttribute('data-empty', input.value === '');
                }

                timer = window.setTimeout(() => {
                    // Identical text (e.g. a stray space) is not a new search.
                    if (input.value === last) {
                        return;
                    }
                    last = input.value;
                    submit();
                }, 300);
            });
        }

        if (clear) {
            clear.addEventListener('click', () => {
                window.clearTimeout(timer);

                if (input) {
                    input.value = '';
                    clear.setAttribute('data-empty', '1');
                }

                // Dropping q (and any page number riding on it) shows all rows.
                const url = new URL(window.location.href);
                url.searchParams.delete('q');
                url.searchParams.delete('page');

                if (url.search === window.location.search) {
                    return; // Already unfiltered; nothing to reload.
                }

                busy();
                window.location.href = url.toString();
            });
        }

        // Discrete controls (status selects, dates, checkboxes) apply on change.
        form.querySelectorAll('[data-live-apply]').forEach((control) => {
            control.addEventListener('change', () => {
                window.clearTimeout(timer);
                submit();
            });
        });

        // Middle-mouse / Enter-to-open-in-new-tab would carry untrimmed text.
        form.addEventListener('submit', () => {
            window.clearTimeout(timer);

            if (input) {
                input.value = input.value.trim();
            }
        });
    });
}

// ---------------------------------------------------------------------------
// Password strength meter (readme: password strength)
// ---------------------------------------------------------------------------

// Mirrors the server rule (Password::defaults(): min 8, letters, numbers)
// purely as feedback; the server stays the authority.
function initPasswordMeters() {
    document.querySelectorAll('[data-password-meter]').forEach((root) => {
        const input = root.querySelector('[data-password-input]');
        const confirm = root.querySelector('[data-password-confirm]');
        const panel = root.querySelector('[data-meter-panel]');
        const label = root.querySelector('[data-meter-label]');
        const bars = {
            1: root.querySelector('[data-meter-bar="1"]'),
            2: root.querySelector('[data-meter-bar="2"]'),
            3: root.querySelector('[data-meter-bar="3"]'),
        };

        if (!input || !panel) {
            return;
        }

        panel.hidden = false;

        Object.values(bars).forEach((bar) => bar.classList.add('opacity-0'));

        const checks = {
            length: (v) => v.length >= 8,
            letters: (v) => /[A-Za-z]/.test(v),
            numbers: (v) => /[0-9]/.test(v),
        };

        function paint() {
            const value = input.value;
            const touched = value !== '' || input.dataset.dirty === '1';

            if (!touched) {
                // Optional field left blank (admin edit keeps the old password).
                panel.classList.add('opacity-50');
            } else {
                panel.classList.remove('opacity-50');
            }

            const met = Object.fromEntries(
                Object.entries(checks).map(([key, test]) => [key, touched ? test(value) : false]),
            );
            const bonus = value.length >= 12 && (/[A-Z]/.test(value) || /[^A-Za-z0-9]/.test(value));
            const score = Object.values(met).filter(Boolean).length + (bonus ? 1 : 0);

            root.querySelectorAll('[data-check]').forEach((li) => {
                const name = li.dataset.check;
                const mark = li.querySelector('[data-check-mark]');
                const ok = name === 'match'
                    ? confirm.value !== '' && confirm.value === value
                    : met[name];

                mark.textContent = ok ? '\u2713' : '\u2022';
                li.classList.toggle('text-emerald-700', ok);
            });

            if (confirm) {
                const matchLi = root.querySelector('[data-check="match"]');
                matchLi.classList.toggle('opacity-50', value === '' && confirm.value === '');
            }

            const levels = [
                { text: 'Weak', color: 'text-rose-600' },
                { text: 'Fair', color: 'text-amber-600' },
                { text: 'Good', color: 'text-emerald-600' },
                { text: 'Strong', color: 'text-emerald-600' },
            ];
            const shown = touched ? Math.max(1, score) : 0;

            [1, 2, 3].forEach((n) => {
                bars[n].classList.toggle('opacity-0', shown < n);
            });
            label.textContent = touched ? levels[shown - 1].text : '';
            label.classList.remove('text-rose-600', 'text-amber-600', 'text-emerald-600');

            if (touched) {
                label.classList.add(levels[shown - 1].color);
            }
        }

        [input, confirm].forEach((el) => {
            if (!el) {
                return;
            }
            el.addEventListener('input', paint);
            el.addEventListener('blur', () => {
                input.dataset.dirty = '1';
                paint();
            });
        });

        paint();
    });
}

// ---------------------------------------------------------------------------
// Client-side form validation (readme: validation (javascript))
// ---------------------------------------------------------------------------

// Browsers only fire their built-in constraint validation on submit (and even
// then skip it for GET forms), which feels static. We mirror it live instead:
// a field flags the moment you pause typing, updates as you keep typing, and
// clears the instant it becomes valid. To avoid nagging, an empty field the
// reader has not touched yet stays quiet until blur or a submit attempt. The
// server-side rules remain exactly as authoritative; this is guidance only.
function initFormValidation() {
    document.querySelectorAll('form').forEach((form) => {
        const fields = () => [...form.querySelectorAll('input, select, textarea')]
            .filter((el) => ! el.disabled)
            .filter((el) => el.type !== 'hidden')
            .filter((el) => el.type !== 'radio');

        let submitAttempted = false;
        const timers = new WeakMap();

        form.noValidate = true; // We render our own messages; suppress the native bubble.

        function messageFor(el) {
            if (el.validity.valueMissing) return 'This field is required.';
            if (el.validity.typeMismatch) return 'Enter a valid value of the expected type.';
            if (el.validity.tooShort) return `Use at least ${el.minLength} characters.`;
            if (el.validity.patternMismatch && el.pattern) return 'The format you entered is not valid.';

            return el.validationMessage || 'This value is not valid.';
        }

        function clearMark(el) {
            el.classList.remove('is-invalid', 'border-rose-400');
            el.parentElement?.querySelector(`[data-invalid-for="${el.id}"]`)?.remove();
        }

        function mark(el) {
            // Respect an explicit novalidate escape hatch on a nested form.
            if (typeof el.checkValidity !== 'function') {
                return true;
            }

            if (el.validity.valid) {
                clearMark(el);

                return true;
            }

            el.classList.add('is-invalid', 'border-rose-400');

            if (el.id) {
                const holder = el.closest('div');

                if (holder && ! holder.querySelector(`[data-invalid-for="${el.id}"]`)) {
                    const note = document.createElement('p');
                    note.dataset.invalidFor = el.id;
                    note.className = 'mt-1 text-xs text-rose-600';
                    note.textContent = messageFor(el); // textContent, never innerHTML.
                    holder.append(note);
                } else if (holder) {
                    const note = holder.querySelector(`[data-invalid-for="${el.id}"]`);
                    const text = messageFor(el);

                    if (note && note.textContent !== text) {
                        note.textContent = text; // Keep the wording fresh as they type.
                    }
                }
            }

            return false;
        }

        // Live check while typing, settled by a short debounce so a fast burst
        // of keystrokes doesn't flicker half-typed "errors" (e.g. "0" in a
        // phone field before the second digit lands).
        function live(el) {
            if (! el.matches('input, select, textarea')) {
                return;
            }

            // Quiet on an untouched empty field: nothing was wrong yet, just empty.
            if (el.value === '' && el.dataset.dirty !== '1' && ! submitAttempted) {
                clearMark(el);

                return;
            }

            window.clearTimeout(timers.get(el));
            timers.set(el, window.setTimeout(() => mark(el), 180));
        }

        form.addEventListener('input', (event) => live(event.target), true);
        form.addEventListener('change', (event) => live(event.target), true);

        form.addEventListener('blur', (event) => {
            const el = event.target;

            if (el.matches('input, select, textarea')) {
                if (el.value !== '') {
                    el.dataset.dirty = '1';
                }
                window.clearTimeout(timers.get(el));
                mark(el);
            }
        }, true);

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return; // Another handler (e.g. a confirm dialog) already decided.
            }

            submitAttempted = true;
            const invalid = fields().filter((el) => ! mark(el));

            if (invalid.length > 0) {
                event.preventDefault();
                invalid[0].focus();
            }
        });
    });
}

// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    initCarousels();
    initReveal();
    initFleet();
    initFleetFilters();
    initPhotoManager();
    initLiveSearch();
    initPasswordMeters();
    initFormValidation();
});
