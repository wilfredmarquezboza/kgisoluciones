/* Login / recuperar / restablecer: mostrar contraseña, fuerza y coincidencia. */
(function ($) {
    'use strict';

    $('.toggle-pass').on('click', function () {
        var $i = $(this).closest('.control').find('input').first();
        var show = $i.attr('type') === 'password';
        $i.attr('type', show ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
    });

    var $pass = $('#password'), $conf = $('#password_conf');
    $pass.on('input', function () {
        var v = $pass.val(), s = 0;
        if (v.length >= 8) s++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
        if (/\d/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        $('#strengthBar').css({ width: (s * 25) + '%', background: ['#e8eaee', '#e5484d', '#f5a524', '#38b48b', '#0f9f86'][s] });
        checkMatch();
    });
    function checkMatch() {
        if (!$conf.length) { return true; }
        var bad = $conf.val() !== '' && $conf.val() !== $pass.val();
        $conf.toggleClass('is-danger', bad);
        $('#matchHelp').toggleClass('is-hidden', !bad);
        return !bad;
    }
    $conf.on('input', checkMatch);

    $('#authForm').on('submit', function (e) {
        if (!checkMatch()) { e.preventDefault(); return; }
        $(this).find('button[type=submit]').addClass('is-loading');
    });
})(jQuery);
