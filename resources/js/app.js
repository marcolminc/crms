/**
 * Front-end behaviour for the vehicle catalogue:
 *
 *  - auto-rotating photo carousels (they only tick while on screen),
 *  - staggered scroll reveals for fleet cards,
 *  - infinite scroll layered on top of real server pagination,
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
        const total = track.children.length;

        if (total < 2) {
            return;
        }

        const ms = parseInt(car.dataset.interval || '3200', 10);
        let index = 0;
        let timer = null;

        for (let d = 0; d < total; d += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('aria-label', `Show photo ${d + 1} of ${total}`);
            button.addEventListener('click', () => {
                show(d);
                restart();
            });
            dots.append(button);
        }

        function render() {
            track.style.transform = `translateX(-${index * 100}%)`;
            [...dots.children].forEach((b, d) => b.classList.toggle('vc-dot-active', d === index));

            if (counter) {
                counter.textContent = `${index + 1}/${total}`;
            }
        }

        function show(i) {
            index = (i + total) % total;
            render();
        }

        function start() {
            if (!reducedMotion && timer === null) {
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

        car.querySelector('[data-carousel-prev]').addEventListener('click', (event) => {
            event.preventDefault();
            show(index - 1);
            restart();
        });
        car.querySelector('[data-carousel-next]').addEventListener('click', (event) => {
            event.preventDefault();
            show(index + 1);
            restart();
        });

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
// Infinite scroll over server pages
// ---------------------------------------------------------------------------

function initInfiniteScroll() {
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

    if (paginate && next) {
        paginate.hidden = true; // The numbered pager is only the no-JS fallback.
    }

    function finish() {
        observer.disconnect();

        if (endEl) {
            endEl.hidden = false;
        }
    }

    async function loadMore() {
        if (loading || !next) {
            return;
        }
        loading = true;

        if (spinner) {
            spinner.hidden = false;
        }

        try {
            const response = await fetch(next, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) {
                throw new Error(`Fleet page came back ${response.status}`);
            }

            const data = await response.json();
            const template = document.createElement('template');
            template.innerHTML = data.html.trim();

            [...template.content.children].forEach((el, i) => {
                el.style.setProperty('--reveal-delay', `${((grid.children.length + i) % 3) * 90}ms`);
                grid.append(el);
            });

            initCarousels(grid);
            initReveal(grid);

            if (shownEl) {
                shownEl.textContent = data.shown;
            }
            if (totalEl) {
                totalEl.textContent = data.total;
            }
            if (bar) {
                bar.hidden = false;
            }
            if (progress && data.total > 0) {
                progress.style.width = `${Math.round((data.shown / data.total) * 100)}%`;
            }

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
            loading = false;

            if (spinner) {
                spinner.hidden = true;
            }
        }
    }

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            loadMore();
        }
    }, { rootMargin: '600px 0px' });

    observer.observe(sentinel);

    if (!next) {
        finish();
    }
}

// ---------------------------------------------------------------------------
// Admin photo picker preview
// ---------------------------------------------------------------------------

function initPhotoManager() {
    const manager = document.querySelector('[data-photo-manager]');

    if (!manager) {
        return;
    }

    const input = manager.querySelector('input[type="file"][name="photos[]"]');
    const preview = manager.querySelector('[data-photo-preview]');
    const max = parseInt(manager.dataset.photoMax || '6', 10);

    if (!input || !preview) {
        return;
    }

    function keptTiles() {
        return manager.querySelectorAll('input[name="remove_images[]"]:not(:checked)').length;
    }

    function render() {
        preview.innerHTML = '';
        manager.querySelectorAll('[data-photo-warning]').forEach((el) => el.remove());

        const files = [...input.files];

        if (files.length === 0) {
            return;
        }

        const room = Math.max(0, max - keptTiles());

        files.forEach((file, i) => {
            const tile = document.createElement('div');
            tile.className = `relative overflow-hidden rounded-lg border ${i >= room ? 'opacity-40' : 'border-slate-200'}`;

            const img = document.createElement('img');
            img.className = 'aspect-[4/3] w-full object-cover';
            img.alt = '';
            img.src = URL.createObjectURL(file);
            img.addEventListener('load', () => URL.revokeObjectURL(img.src));

            const name = document.createElement('span');
            name.className = 'absolute inset-x-0 bottom-0 truncate bg-slate-900/60 px-1 py-0.5 text-[10px] text-white';
            name.textContent = file.name;

            tile.append(img, name);
            preview.append(tile);
        });

        if (files.length > room) {
            const warn = document.createElement('p');
            warn.dataset.photoWarning = '';
            warn.className = 'mt-1 text-xs text-rose-600';
            warn.textContent = room === 0
                ? 'No gallery slots left — untick a current photo to free one up.'
                : `Only ${room} slot${room === 1 ? '' : 's'} left — the extra photos will not be saved.`;
            preview.after(warn);
        }
    }

    input.addEventListener('change', render);
    manager.querySelectorAll('input[name="remove_images[]"]').forEach((box) => {
        box.addEventListener('change', render);
    });
}

// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    initCarousels();
    initReveal();
    initInfiniteScroll();
    initPhotoManager();
});
