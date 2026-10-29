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

        // Dots double as angle shortcuts: "Front", "Interior", ...
        const labels = [...track.children].map((slide) => slide.dataset.slideLabel || '');

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
    initInfiniteScroll();
    initPhotoManager();
});
