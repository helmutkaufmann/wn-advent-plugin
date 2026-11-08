/* Label advent doors by position ("1 December", ...) in the backend repeater.
   Position = day, so labels are recomputed after drag & drop, add/remove and title edits. */
(function ($) {
    'use strict';
    var busy = false;

    function relabel() {
        if (busy) { return; }
        busy = true;
        try {
            var $form = $('form'), year = parseInt($form.find('[name$="[year]"]').first().val(), 10);
            $('[data-field-name="doors"] ul.field-repeater-items').each(function () {
                // While dragging, the dragged item is parked at the end of the list and a
                // placeholder marks the drop slot: count the placeholder, skip the dragged item.
                var day = 0;
                $(this).children('li.field-repeater-item, li.placeholder').not('.dragged').each(function () {
                    day++;
                    if ($(this).hasClass('placeholder')) { return; }
                    var $item = $(this), i = day - 1, text = day + ' December';
                    if (year > 1900) {
                        var wd = new Date(year, 11, day).toLocaleDateString('en-GB', { weekday: 'short' });
                        text = wd + ', ' + text;
                    }
                    var title = $.trim($item.find('[data-field-name="title"] input').first().val() || '');
                    var full = title ? text + ' – ' + title : text;
                    var $c = $item.children('.repeater-item-collapsed-title');
                    if ($c.text() !== full) { $c.text(full); }
                    var $b = $item.children('.mc-door-badge');
                    if (!$b.length) { $b = $('<div class="mc-door-badge"></div>').insertAfter($c); }
                    if ($b.text() !== text) { $b.text(text); }
                });
            });
        } finally { busy = false; }
    }

    // The sortable library has no slot above the first item: a door dropped there stays
    // parked at the end (after the "add" row). Put it first (pointer above middle) or last.
    var lastY = null;
    $(document).on('mousemove', function (e) { lastY = e.clientY; });
    $(document).on('mouseup', function () {
        var y = lastY;
        setTimeout(function () {
            $('[data-field-name="doors"] ul.field-repeater-items').each(function () {
                var $ul = $(this), $add = $ul.children('li.field-repeater-add-item'),
                    $stray = $add.nextAll('li.field-repeater-item');
                if (!$stray.length) { return; }
                var $first = $ul.children('li.field-repeater-item').first(), r = $first.length && $first[0].getBoundingClientRect();
                if ($first.length && y !== null && y < r.top + r.height / 2) { $stray.insertBefore($first); }
                else { $stray.insertBefore($add); }
            });
            relabel();
        }, 10);
    });

    var timer = null;
    function schedule() { clearTimeout(timer); timer = setTimeout(relabel, 30); }
    $(document).on('render ajaxUpdateComplete input change mouseup keyup mousemove', schedule);
    $(function () {
        relabel();
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    });
})(jQuery);
