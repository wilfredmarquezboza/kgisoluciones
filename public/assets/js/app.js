/* Comportamiento común del panel: menú, dropdown, AJAX con CSRF y avisos. */
(function ($) {
    'use strict';

    var csrfName = $('meta[name="csrf-name"]').attr('content');
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $.ajaxSetup({
        beforeSend: function (xhr) { xhr.setRequestHeader(csrfName, csrfToken); }
    });
    // Sesión expirada: volver al login.
    $(document).ajaxError(function (e, xhr) {
        if (xhr.status === 401) { window.location.href = window.KGI_BASE + 'login'; }
    });
    window.KGI_BASE = document.querySelector('link[href*="assets/css/app.css"]').href.replace(/assets\/css\/app\.css.*$/, '');

    window.toast = function (msg, ok) {
        var $t = $('<div class="toast">').addClass(ok === false ? 'err' : 'ok').text(msg).appendTo('#toasts');
        setTimeout(function () { $t.fadeOut(250, function () { $t.remove(); }); }, 3500);
    };

    // Grupos del menú
    $('.menu-group-toggle').on('click', function () {
        var $g = $(this).closest('.menu-group').toggleClass('is-open');
        $(this).attr('aria-expanded', $g.hasClass('is-open'));
    });

    // Menú móvil
    $('#menuBtn, #sidebarBackdrop').on('click', function () { $('body').toggleClass('menu-open'); });

    // Dropdown de usuario
    $('#userMenu .dropdown-trigger').on('click', function (e) { e.stopPropagation(); $('#userMenu').toggleClass('is-active'); });
    $(document).on('click', function () { $('#userMenu').removeClass('is-active'); });
})(jQuery);
