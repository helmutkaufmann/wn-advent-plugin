/* Mercator.Advent: remembers per browser which doors were opened. */
(function () {
    'use strict';

    function load(key) {
        try {
            var raw = window.localStorage.getItem(key);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function save(key, list) {
        try { window.localStorage.setItem(key, JSON.stringify(list)); } catch (e) { /* private mode */ }
    }

    function init(root) {
        if (root.dataset.mcReady) { return; }
        root.dataset.mcReady = '1';

        if (root.hasAttribute('data-animate')
            && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
            && 'IntersectionObserver' in window) {
            root.classList.add('mc-fly', 'mc-fly-' + root.getAttribute('data-animate'));
            var io = new IntersectionObserver(function (entries) {
                if (entries.some(function (e) { return e.isIntersecting; })) {
                    root.classList.add('is-in');
                    io.disconnect();
                }
            }, { threshold: 0.1 });
            io.observe(root);
        }

        var key = 'mercator-advent-' + root.dataset.year;
        var opened = load(key);
        var modal = root.querySelector('[data-mc-modal]');
        var body = root.querySelector('[data-mc-dialog-body]');

        function mark(button) {
            button.classList.add('is-opened');
        }

        root.querySelectorAll('.mc-door').forEach(function (button) {
            var day = parseInt(button.dataset.day, 10);
            if (button.dataset.unlocked === '1' && opened.indexOf(day) !== -1) { mark(button); }

            button.addEventListener('click', function () {
                if (button.dataset.unlocked !== '1') {
                    button.classList.remove('is-shaking');
                    void button.offsetWidth;
                    button.classList.add('is-shaking');
                    return;
                }
                if (button.dataset.missed === '1' && opened.indexOf(day) === -1) {
                    button.classList.remove('is-shaking');
                    void button.offsetWidth;
                    button.classList.add('is-shaking');
                    return;
                }
                var tpl = root.querySelector('template[data-door-content="' + day + '"]');
                if (!tpl) { return; }
                function show() {
                    body.innerHTML = '';
                    body.appendChild(tpl.content.cloneNode(true));
                    if (opened.indexOf(day) === -1) {
                        opened.push(day);
                        save(key, opened);
                    }
                    mark(button);
                    button.classList.remove('is-opening');
                    if (window.UIkit) { window.UIkit.modal(modal).show(); }
                }
                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (opened.indexOf(day) === -1 && !reduce) {
                    button.classList.add('is-opening');
                    window.setTimeout(show, 330);
                } else { show(); }
            });
        });
    }

    function boot() {
        document.querySelectorAll('[data-mc-advent]').forEach(init);
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
    else { boot(); }
})();
