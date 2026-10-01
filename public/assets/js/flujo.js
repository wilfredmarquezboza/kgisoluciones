/* Flujo de caja: cobrado vs esperado por mes y moneda (gráfico SVG + tabla + detalle). */
(function ($) {
    'use strict';

    var U = window.FX_FLUJO, MON = { USD: { sim: 'US$', nombre: 'Dólares' }, PEN: { sim: 'S/', nombre: 'Soles' } };
    var nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var mesFmt = new Intl.DateTimeFormat('es-PE', { month: 'short', year: '2-digit', timeZone: 'UTC' });
    var mesLargo = new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    var HOY = '', SEL = {};   // SEL[moneda] = clave de la fila seleccionada

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function money(n, m) { return MON[m].sim + ' ' + nf.format(Math.round((n + Number.EPSILON) * 100) / 100); }
    function dmy(iso) { return iso ? iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(2, 4) : 'Sin fecha'; }
    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
    function mesCorto(k) { return mesFmt.format(new Date(k + '-01T00:00:00Z')).replace('.', ''); }
    function tick(n) { return n >= 1e6 ? (n / 1e6).toFixed(1) + ' M' : n >= 1e3 ? (Math.round(n / 100) / 10) + ' k' : String(Math.round(n)); }
    function niceMax(v) { if (v <= 0) { return 100; } var p = Math.pow(10, Math.floor(Math.log10(v))), f = v / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p; }

    function columnas(d) {
        var cols = [];
        if (d.atrasado.total > 0) { cols.push({ key: 'atrasado', label: 'Atrasado', esp: d.atrasado.total, cob: 0, prev: d.atrasado.items, cobros: [], atrasado: true }); }
        d.meses.forEach(function (m) { cols.push({ key: m.mes, label: mesCorto(m.mes), largo: cap(mesLargo.format(new Date(m.mes + '-01T00:00:00Z'))), esp: m.proyectado, cob: m.cobrado, prev: m.prev, cobros: m.cobros, actual: m.mes === HOY.slice(0, 7) }); });
        return cols;
    }

    function chart(m, cols) {
        var W = Math.max(520, 70 + cols.length * 58), H = 250, L = 52, B = 34, T = 14, ph = H - B - T;
        var max = niceMax(Math.max.apply(null, cols.map(function (c) { return Math.max(c.esp, c.cob); }).concat([1])));
        var step = (W - L - 10) / cols.length, bw = Math.min(18, step / 3);
        function y(v) { return T + ph - v / max * ph; }
        var g = '';
        [0, .25, .5, .75, 1].forEach(function (f) {
            g += '<line x1="' + L + '" x2="' + (W - 10) + '" y1="' + y(max * f) + '" y2="' + y(max * f) + '" class="fx-grid"/>' +
                '<text x="' + (L - 8) + '" y="' + (y(max * f) + 4) + '" text-anchor="end" class="fx-axis">' + tick(max * f) + '</text>';
        });
        cols.forEach(function (c, i) {
            var cx = L + step * i + step / 2, sel = SEL[m] === c.key;
            g += '<g class="fx-col' + (sel ? ' is-sel' : '') + '" data-key="' + c.key + '" data-m="' + m + '">' +
                '<rect class="fx-hit" x="' + (cx - step / 2) + '" y="' + T + '" width="' + step + '" height="' + ph + '"/>' +
                (c.actual ? '<rect class="fx-now" x="' + (cx - step / 2 + 2) + '" y="' + T + '" width="' + (step - 4) + '" height="' + ph + '" rx="4"/>' : '');
            [[c.esp, -bw - 1, 'fx-s-esp'], [c.cob, 1, 'fx-s-cob']].forEach(function (b) {
                if (b[0] > 0) {
                    var h = Math.max(2, ph - (y(b[0]) - T)), x = cx + b[1], r = Math.min(4, h / 2), yy = y(b[0]);
                    // columna con extremo superior redondeado y base recta sobre la línea de base
                    g += '<path class="' + b[2] + '" d="M' + x + ',' + (yy + h) + 'V' + (yy + r) + 'Q' + x + ',' + yy + ' ' + (x + r) + ',' + yy + 'H' + (x + bw - r) + 'Q' + (x + bw) + ',' + yy + ' ' + (x + bw) + ',' + (yy + r) + 'V' + (yy + h) + 'Z"/>';
                }
            });
            g += '<text x="' + cx + '" y="' + (H - 12) + '" text-anchor="middle" class="fx-axis' + (c.atrasado ? ' fx-late' : '') + '">' + c.label + '</text></g>';
        });
        g += '<line x1="' + L + '" x2="' + (W - 10) + '" y1="' + y(0) + '" y2="' + y(0) + '" class="fx-base"/>';
        return '<div class="fx-chart-scroll"><svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="Cobrado y esperado por mes en ' + MON[m].nombre.toLowerCase() + '">' + g + '</svg></div>';
    }

    function tabla(m, d, cols) {
        var acum = 0;
        var filas = cols.map(function (c) {
            acum += c.esp;
            return '<tr class="' + (SEL[m] === c.key ? 'is-sel' : '') + '" data-key="' + c.key + '" data-m="' + m + '" tabindex="0"><th scope="row">' + (c.atrasado ? 'Atrasado' : c.largo) + (c.actual ? ' <span class="tag is-light">actual</span>' : '') + '</th>' +
                '<td>' + (c.cob ? money(c.cob, m) : '—') + '</td><td>' + (c.esp ? money(c.esp, m) : '—') + '</td><td>' + money(acum, m) + '</td></tr>';
        });
        [['posterior', 'Posterior al período'], ['sin_fecha', 'Sin fecha asignada']].forEach(function (b) {
            if (d[b[0]].total > 0) { filas.push('<tr class="' + (SEL[m] === b[0] ? 'is-sel' : '') + '" data-key="' + b[0] + '" data-m="' + m + '" tabindex="0"><th scope="row">' + b[1] + '</th><td>—</td><td>' + money(d[b[0]].total, m) + '</td><td></td></tr>'); }
        });
        return '<div class="fx-table-wrap"><table class="fx-table fx-flujo-table"><thead><tr><th>Período</th><th>Cobrado</th><th>Esperado</th><th>Esperado acumulado</th></tr></thead><tbody>' + filas.join('') + '</tbody></table></div>';
    }

    function detalle(m, d, cols) {
        var k = SEL[m];
        if (!k) { return '<p class="has-text-grey is-size-7">Haz clic en un mes del gráfico o de la tabla para ver el detalle de pagos.</p>'; }
        var col = cols.filter(function (c) { return c.key === k; })[0], prev, cobros = [], tit;
        if (col) { prev = col.prev; cobros = col.cobros; tit = col.atrasado ? 'Atrasado' : col.largo; }
        else { prev = d[k].items; tit = k === 'posterior' ? 'Posterior al período' : 'Sin fecha asignada'; }
        function lista(items, vacio) {
            return items.length ? '<ul class="fx-abonos">' + items.map(function (i) {
                return '<li><span><b>' + esc(i.proyecto) + '</b> · ' + esc(i.empresa) + '<br><span class="has-text-grey is-size-7">' + esc(i.pago) + ' · ' + esc(i.detalle || '') + ' · ' + dmy(i.fecha) + '</span></span><b>' + money(i.monto, m) + '</b></li>';
            }).join('') + '</ul>' : '<p class="has-text-grey is-size-7 mb-2">' + vacio + '</p>';
        }
        return '<h4 class="fx-det-h">' + esc(tit) + '</h4>' +
            (cobros.length || !prev.length ? '<p class="fx-det-s">Cobrado</p>' + lista(cobros, 'Sin cobros registrados.') : '') +
            (prev.length || !cobros.length ? '<p class="fx-det-s">Esperado</p>' + lista(prev, 'Nada esperado.') : '');
    }

    function bloque(m, d) {
        var cols = columnas(d), mes = cols.filter(function (c) { return c.actual; })[0];
        var esperado = d.meses.reduce(function (s, x) { return s + x.proyectado; }, 0);
        var kpis = [
            ['Atrasado', d.atrasado.total, d.atrasado.total > 0 ? 'late' : ''], ['Este mes', mes ? mes.esp : 0, ''],
            ['Esperado en el período', esperado, ''], ['Sin fecha o posterior', d.sin_fecha.total + d.posterior.total, '']
        ];
        return '<section class="fx-mon" data-m="' + m + '"><h2 class="fx-mon-h">' + MON[m].nombre + ' (' + MON[m].sim + ')</h2>' +
            '<div class="fx-flujo-kpis">' + kpis.map(function (k) { return '<div class="fx-kpi"><h2>' + k[0] + '</h2><span class="fx-kpi-n ' + k[2] + '">' + money(k[1], m) + '</span></div>'; }).join('') + '</div>' +
            '<div class="box fx-flujo-card"><div class="fx-legend-row"><span><i class="sw fx-s-esp"></i>Esperado</span><span><i class="sw fx-s-cob"></i>Cobrado</span></div>' +
            chart(m, cols) + '</div>' +
            '<div class="columns"><div class="column is-7">' + tabla(m, d, cols) + '</div><div class="column is-5"><div class="box fx-det" data-det="' + m + '">' + detalle(m, d, cols) + '</div></div></div></section>';
    }

    var DATA = {};
    function render() {
        var mons = Object.keys(DATA);
        if (!mons.length) { $('#flujo').html('<div class="fx-empty">Aún no hay proyectos en el control de facturas.</div>'); return; }
        $('#flujo').html(mons.map(function (m) { return bloque(m, DATA[m]); }).join(''));
    }
    function load() {
        var q = { atras: $('#selAtras').val(), meses: $('#selMeses').val() };
        $('#btnCsv').attr('href', U.csv + '?' + $.param(q));
        $.getJSON(U.datos, q).done(function (r) { HOY = r.hoy; DATA = r.flujo; render(); })
            .fail(function () { $('#flujo').html('<div class="fx-empty">No se pudo cargar el flujo de caja.</div>'); });
    }
    $('#selAtras, #selMeses').on('change', function () { SEL = {}; load(); });

    $('#flujo').on('click keydown', '.fx-col, tr[data-key]', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
        var m = $(this).data('m'); SEL[m] = $(this).data('key'); e.preventDefault();
        var y = window.scrollY; render(); window.scrollTo(0, y);
    });

    // Tooltip por columna (cada marca tiene su valor exacto; la tabla ofrece lo mismo sin pasar el cursor).
    var $tip = $('#fxTip');
    $('#flujo').on('mousemove', '.fx-col', function (e) {
        var m = $(this).data('m'), k = $(this).data('key'), cols = columnas(DATA[m]), c = cols.filter(function (x) { return x.key === k; })[0];
        $tip.html('<b>' + esc(c.atrasado ? 'Atrasado' : c.largo) + '</b><br><i class="sw fx-s-esp"></i> Esperado ' + money(c.esp, m) + '<br><i class="sw fx-s-cob"></i> Cobrado ' + money(c.cob, m))
            .css({ left: Math.min(e.clientX + 14, window.innerWidth - 220), top: e.clientY + 14 }).prop('hidden', false);
    }).on('mouseleave', '.fx-col', function () { $tip.prop('hidden', true); });

    load();
})(jQuery);
