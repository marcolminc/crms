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
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

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

document.addEventListener('DOMContentLoaded', () => {
    initCarousels();
    initReveal();
    initFleet();
    initFleetFilters();
    initPhotoManager();
});
