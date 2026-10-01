/* Control de facturas: cobros por empresa/proyecto, plan de pagos y avance por actividades. */
(function ($) {
    'use strict';

    var U = window.FX_URLS;
    var D = { proyectos: [], disponibles: [], igv: 18, detraccion: 12 };
    var S = { empresa: '__all__', estado: '__all__', q: '', abiertos: {} };
    var MON = { USD: { sim: 'US$', nombre: 'Dólares' }, PEN: { sim: 'S/', nombre: 'Soles' } };
    var ETQ = { pendiente: '○ Pendiente', facturado: '◐ Facturado', pagado: '✓ Pagado' };

    /* ---------- utilidades ---------- */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
    function slug(s) { return norm(s).replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
    function clamp(n, a, b) { return Math.min(b, Math.max(a, n)); }
    var nf = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function r2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
    function money(n, m) { return MON[m].sim + ' ' + nf.format(r2(n)); }
    function fmtPct(n) { return (Math.round(n * 10) / 10).toString().replace('.', ',') + '%'; }
    function pctOf(a, b) { return b > 0 ? a / b * 100 : 0; }
    function fmtFecha(iso) { return /^\d{4}-\d{2}-\d{2}/.test(iso || '') ? iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(2, 4) : ''; }

    function calc(monto, pct) {
        var sub = monto * pct / 100, igv = sub * D.igv / 100, total = sub + igv, det = total * D.detraccion / 100;
        return { sub: sub, igv: igv, total: total, det: det, neto: total - det };
    }
    function cuotasDe(pr) { return pr.cuotas.map(function (c) { return $.extend({}, c, calc(pr.monto, c.pct)); }); }
    function resumen(lista) {
        var out = {};
        Object.keys(MON).forEach(function (m) { out[m] = { neto: 0, pagado: 0, facturado: 0, pendiente: 0 }; });
        lista.forEach(function (pr) { cuotasDe(pr).forEach(function (c) { out[pr.moneda].neto += c.neto; out[pr.moneda][c.estado] += c.neto; }); });
        return out;
    }
    function coincide(pr) {
        if (S.empresa !== '__all__' && pr.empresa !== S.empresa) { return false; }
        var cuotas = cuotasDe(pr);
        if (S.estado !== '__all__' && !cuotas.some(function (c) { return c.estado === S.estado; })) { return false; }
        if (S.q) {
            var hay = norm([pr.nombre, pr.empresa].concat(cuotas.map(function (c) { return c.factura; })).join(' '));
            if (hay.indexOf(norm(S.q)) < 0) { return false; }
        }
        return true;
    }
    function empresas() { var seen = {}, out = []; D.proyectos.forEach(function (p) { if (!seen[p.empresa]) { seen[p.empresa] = 1; out.push(p.empresa); } }); return out; }
    function byId(id) { return D.proyectos.filter(function (p) { return p.id === Number(id); })[0]; }

    /* ---------- render ---------- */
    function renderChips() {
        var items = [{ v: '__all__', t: 'Todas', n: D.proyectos.length }].concat(empresas().map(function (e) {
            return { v: e, t: e, n: D.proyectos.filter(function (p) { return p.empresa === e; }).length };
        }));
        if (S.empresa !== '__all__' && empresas().indexOf(S.empresa) < 0) { S.empresa = '__all__'; }
        $('#chips').html(items.map(function (it) {
            return '<button type="button" class="fx-chip" data-act="empresa" data-v="' + esc(it.v) + '" aria-pressed="' + (S.empresa === it.v) + '">' + esc(it.t) + '<span class="count">' + it.n + '</span></button>';
        }).join(''));
    }

    function renderKpis(lista) {
        var r = resumen(lista), filtrado = lista.length !== D.proyectos.length;
        $('#kpis').html(Object.keys(MON).filter(function (m) { return r[m].neto > 0; }).map(function (m) {
            var o = r[m], filas = [['fx-paid', 'Cobrado', o.pagado], ['fx-billed', 'Facturado, sin cobrar', o.facturado], ['fx-pend', 'Aún sin facturar', o.pendiente]];
            return '<article class="fx-kpi"><h2>' + MON[m].nombre + ' (' + MON[m].sim + ')' + (filtrado ? ', selección actual' : '') + '</h2>' +
                '<span class="fx-kpi-n">' + money(o.facturado + o.pendiente, m) + '</span><span class="fx-kpi-l">por cobrar de ' + money(o.neto, m) + ' en total</span>' +
                '<div class="fx-stack">' + filas.map(function (f) { return '<span class="' + f[0] + '" style="width:' + pctOf(f[2], o.neto).toFixed(2) + '%"></span>'; }).join('') + '</div>' +
                '<ul class="fx-legend">' + filas.map(function (f) {
                    return '<li><i class="sw ' + f[0] + '"></i><span>' + f[1] + '</span><b>' + money(f[2], m) + '</b><span class="pct">' + fmtPct(pctOf(f[2], o.neto)) + '</span></li>';
                }).join('') + '</ul></article>';
        }).join(''));
    }

    function renderCuota(pr, c) {
        var pago = c.estado === 'pagado' && c.fecha ? ' el ' + fmtFecha(c.fecha) : '';
        return '<li class="fx-cuota is-' + c.estado + '"><div class="fx-c-top"><b>' + esc(c.label) + '</b><span>' + fmtPct(c.pct) + '</span></div>' +
            '<div class="fx-c-amount">' + money(c.neto, pr.moneda) + '</div><div class="fx-c-hint">neto a cobrar</div>' +
            '<span class="fx-status is-' + c.estado + '">' + ETQ[c.estado] + pago + '</span>' +
            '<div class="fx-c-inv">' + (c.factura ? 'Factura <b>' + esc(c.factura) + '</b>' : 'Sin factura') + '</div>' +
            '<div class="fx-c-foot"><button type="button" class="button is-small" data-act="edit-cuota" data-id="' + c.id + '" data-pid="' + pr.id + '">Actualizar</button></div></li>';
    }

    function renderDesglose(pr, cuotas) {
        var filas = [['Subtotal', 'sub', ''], ['IGV (' + D.igv + '%)', 'igv', ''], ['Total con IGV', 'total', ''], ['Detracción (' + D.detraccion + '%)', 'det', ''], ['Neto a cobrar', 'neto', 'strong']];
        function suma(k) { return cuotas.reduce(function (s, c) { return s + c[k]; }, 0); }
        return '<details class="fx-details" data-pid="' + pr.id + '"' + (S.abiertos[pr.id] ? ' open' : '') + '><summary>Ver desglose de importes</summary><div class="fx-table-wrap"><table class="fx-table">' +
            '<thead><tr><th>Concepto</th>' + cuotas.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('') + '<th>Total</th></tr></thead><tbody>' +
            filas.map(function (f) {
                return '<tr class="' + f[2] + '"><th>' + f[0] + '</th>' + cuotas.map(function (c) { return '<td>' + nf.format(r2(c[f[1]])) + '</td>'; }).join('') + '<td>' + nf.format(r2(suma(f[1]))) + '</td></tr>';
            }).join('') + '</tbody></table></div></details>';
    }

    function renderActs(pr) {
        var items = pr.acts, declarado = items.reduce(function (s, a) { return s + a.pct; }, 0);
        var real = clamp(items.filter(function (a) { return a.estado === 'Concluido'; }).reduce(function (s, a) { return s + a.pct; }, 0), 0, 100);
        var lista = items.length ? '<ul class="fx-act-list">' + items.map(function (a) {
            return '<li class="fx-act"><div><div class="fx-act-name">' + esc(a.nombre) + '</div><div class="fx-act-date">' + (a.fecha ? fmtFecha(a.fecha) : 'Sin fecha') + '</div></div>' +
                '<span class="fx-status is-' + slug(a.estado) + '">' + esc(a.estado) + '</span><span class="fx-act-pct">' + a.pct + '%</span>' +
                '<div class="fx-act-tools"><button type="button" class="fx-link" data-act="edit-act" data-id="' + a.id + '" data-pid="' + pr.id + '">Editar</button>' +
                '<button type="button" class="fx-link danger" data-act="del-act" data-id="' + a.id + '" data-pid="' + pr.id + '">Eliminar</button></div></li>';
        }).join('') + '</ul>' : '<p class="has-text-grey is-size-7">Aún no hay actividades. Agrega la primera para medir el avance.</p>';
        return '<section class="fx-acts"><div class="fx-acts-head"><div><h4>Avance del proyecto</h4><span class="fx-acts-pct">' + real + '%</span></div>' +
            '<button type="button" class="button is-small" data-act="add-act" data-pid="' + pr.id + '">Agregar actividad</button></div>' +
            '<div class="fx-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + real + '"><span style="width:' + real + '%"></span></div>' +
            (declarado > 100 ? '<p class="fx-note danger" style="margin:.6rem 0 0" role="alert">Las actividades suman ' + declarado + '%, más del 100% del proyecto. Corrige los porcentajes.</p>' : '') + lista + '</section>';
    }

    function renderProyecto(pr) {
        var cuotas = cuotasDe(pr), n = cuotas.length;
        var neto = cuotas.reduce(function (s, c) { return s + c.neto; }, 0);
        var cobrado = cuotas.filter(function (c) { return c.estado === 'pagado'; }).reduce(function (s, c) { return s + c.neto; }, 0);
        var pagadas = cuotas.filter(function (c) { return c.estado === 'pagado'; }).length;
        var sumaPct = cuotas.reduce(function (s, c) { return s + c.pct; }, 0), pct = pctOf(cobrado, neto);
        return '<article class="fx-project" id="p-' + pr.id + '"><div class="fx-p-head"><div><h3>' + esc(pr.nombre) + '</h3>' +
            '<p class="fx-p-sub">' + pagadas + ' de ' + n + (n === 1 ? ' pago cobrado' : ' pagos cobrados') + ', ' + MON[pr.moneda].nombre.toLowerCase() + ' · ' +
            '<button type="button" class="fx-link fx-p-actions" data-act="edit-plan" data-pid="' + pr.id + '">Editar plan de pagos</button></p></div>' +
            '<div class="fx-p-total"><strong>' + money(pr.monto, pr.moneda) + '</strong><span>monto total sin IGV</span></div></div>' +
            (Math.abs(sumaPct - 100) > 0.05 ? '<p class="fx-note">Los porcentajes de pago suman ' + fmtPct(sumaPct) + ' y no 100%. Revisa el contrato o completa los pagos que faltan.</p>' : '') +
            '<ol class="fx-cuotas">' + cuotas.map(function (c) { return renderCuota(pr, c); }).join('') + '</ol>' +
            '<div class="fx-progress"><div class="fx-progress-top"><span>Avance de cobro <b>' + fmtPct(pct) + '</b></span><span>' + money(cobrado, pr.moneda) + ' de ' + money(neto, pr.moneda) + '</span></div>' +
            '<div class="fx-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(pct) + '"><span style="width:' + pct.toFixed(1) + '%"></span></div></div>' +
            renderDesglose(pr, cuotas) + renderActs(pr) + '</article>';
    }

    function renderMain() {
        var vis = D.proyectos.filter(coincide);
        renderKpis(vis);
        if (!D.proyectos.length) {
            $('#contenido').html('<div class="fx-empty"><p class="mb-3">Aún no hay proyectos en el control de facturas.</p><button type="button" class="button is-primary" data-act="nuevo-plan">Agregar proyecto</button></div>');
            return;
        }
        if (!vis.length) {
            $('#contenido').html('<div class="fx-empty"><p class="mb-3">Ningún proyecto coincide con los filtros.</p><button type="button" class="button" data-act="clear">Quitar filtros</button></div>');
            return;
        }
        $('#contenido').html(empresas().map(function (e) {
            var lista = vis.filter(function (p) { return p.empresa === e; });
            if (!lista.length) { return ''; }
            var r = resumen(lista);
            var pend = Object.keys(MON).filter(function (m) { return r[m].neto > 0; }).map(function (m) { return '<span>Por cobrar ' + money(r[m].facturado + r[m].pendiente, m) + '</span>'; }).join('');
            return '<section class="fx-emp"><div class="fx-emp-head"><h2>' + esc(e) + '</h2><div class="fx-emp-meta"><span>' + lista.length + (lista.length === 1 ? ' proyecto' : ' proyectos') + '</span>' + pend + '</div></div>' +
                lista.map(renderProyecto).join('') + '</section>';
        }).join(''));
    }

    function load(done) {
        $.getJSON(U.datos).done(function (res) {
            D = res; renderChips();
            var y = window.scrollY; renderMain(); window.scrollTo(0, y);
            if (done) { done(); }
        }).fail(function () { $('#contenido').html('<div class="fx-empty">No se pudo cargar la información.</div>'); });
    }

    /* ---------- modales ---------- */
    function open(sel) { $(sel).addClass('is-active'); }
    function close() { $('.modal.is-active').removeClass('is-active'); }
    $(document).on('click', '.modal [data-close]', close);
    $(document).on('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    function showErr($m, msg) { $m.find('.fx-error').text(msg).prop('hidden', !msg); }
    function fail(xhr, $m, fallback) {
        var r = xhr.responseJSON || {};
        showErr($m, r.message || fallback);
        if (r.errors) { $.each(r.errors, function (name) { $m.find('[name="' + name + '"]').addClass('is-danger').trigger('focus'); }); }
    }
    function saved(res) { close(); toast(res.message); load(); }
    function confirmar(titulo, texto, label, fn) {
        $('#confTitulo').text(titulo); $('#confTexto').html(texto); $('#confYes').text(label).off('click').on('click', function () { close(); fn(); });
        open('#dlgConfirm');
    }

    /* Pago */
    var $fc = $('#frmCuota'), ctxCuota = null;
    function syncFecha() {
        var pagado = $fc.find('[name=estado]:checked').val() === 'pagado';
        $fc.find('[name=fecha]').prop('disabled', !pagado); if (!pagado) { $fc.find('[name=fecha]').val(''); }
    }
    $fc.on('change', '[name=estado]', syncFecha);
    function abrirCuota(pid, id) {
        var pr = byId(pid), c = pr && pr.cuotas.filter(function (x) { return x.id === Number(id); })[0];
        if (!c) { return; }
        ctxCuota = c.id;
        $('#dlgCuotaSub').text(pr.nombre + ', ' + c.label);
        $fc.find('[name=estado][value=' + c.estado + ']').prop('checked', true);
        $fc.find('[name=factura]').val(c.factura).removeClass('is-danger');
        $fc.find('[name=fecha]').val(c.fecha);
        showErr($fc, ''); syncFecha(); open('#dlgCuota');
    }
    $fc.on('submit', function (e) {
        e.preventDefault(); showErr($fc, ''); $fc.find('.is-danger').removeClass('is-danger');
        $.post(U.cuota + '/' + ctxCuota, $fc.serialize()).done(saved).fail(function (x) { fail(x, $fc, 'No se pudo guardar.'); });
    });

    /* Actividad */
    var $fa = $('#frmAct'), ctxAct = null;
    function abrirAct(pid, id) {
        var pr = byId(pid), a = id ? pr.acts.filter(function (x) { return x.id === Number(id); })[0] : null;
        if (!pr || (id && !a)) { return; }
        ctxAct = pr.id; $fa[0].reset();
        $('#dlgActTitulo').text(a ? 'Editar actividad' : 'Nueva actividad'); $('#dlgActSub').text(pr.nombre);
        $fa.find('[name=id]').val(a ? a.id : '');
        if (a) { $fa.find('[name=nombre]').val(a.nombre); $fa.find('[name=fecha]').val(a.fecha); $fa.find('[name=estado]').val(a.estado); $fa.find('[name=pct]').val(a.pct); }
        $fa.find('.is-danger').removeClass('is-danger'); showErr($fa, ''); open('#dlgAct');
        setTimeout(function () { $fa.find('[name=nombre]').trigger('focus'); }, 50);
    }
    $fa.on('submit', function (e) {
        e.preventDefault(); showErr($fa, ''); $fa.find('.is-danger').removeClass('is-danger');
        $.post(U.actividad + '/' + ctxAct, $fa.serialize()).done(saved).fail(function (x) { fail(x, $fa, 'No se pudo guardar.'); });
    });

    /* Plan de pagos */
    var $fp = $('#frmPlan'), ctxPlan = null;
    function planRow(c) {
        return '<div class="fx-plan-row" data-id="' + (c.id || '') + '"><input class="input" data-f="label" maxlength="60" placeholder="Ej. Primer pago" value="' + esc(c.label || '') + '">' +
            '<input class="input" data-f="pct" type="number" min="0.0001" max="100" step="any" placeholder="%" value="' + (c.pct != null ? c.pct : '') + '">' +
            '<button type="button" class="button" data-act="plan-del" aria-label="Quitar pago"><i class="fa-solid fa-xmark"></i></button></div>';
    }
    function planSuma() {
        var s = 0; $('#planRows [data-f=pct]').each(function () { s += Number(this.value) || 0; });
        s = Math.round(s * 10000) / 10000;
        $('#planSuma').attr('class', 'is-size-7 ' + (Math.abs(s - 100) < 0.05 ? 'ok' : 'warn')).text('Suma: ' + fmtPct(s) + (Math.abs(s - 100) < 0.05 ? '' : ' (debería ser 100%)'));
    }
    function abrirPlan(pid) {
        var pr = pid ? byId(pid) : null;
        ctxPlan = pr ? pr.id : null; $fp[0].reset(); showErr($fp, ''); $fp.find('.is-danger').removeClass('is-danger');
        $('#planProyectoWrap').prop('hidden', !!pr); $('#planQuitar').prop('hidden', !pr);
        $('#dlgPlanTitulo').text(pr ? 'Editar plan de pagos' : 'Agregar proyecto a facturación'); $('#dlgPlanSub').text(pr ? pr.nombre : '');
        if (pr) {
            $fp.find('[name=moneda]').val(pr.moneda); $fp.find('[name=monto]').val(pr.monto);
            $('#planRows').html(pr.cuotas.map(planRow).join(''));
        } else {
            if (!D.disponibles.length) { toast('No hay proyectos sin monto. Crea uno en Kgi → Proyectos.', false); return; }
            $('#pProyecto').html(D.disponibles.map(function (d) { return '<option value="' + d.id + '">' + esc(d.label) + '</option>'; }).join(''));
            $('#planRows').html(planRow({ label: 'Primer pago' }) + planRow({ label: 'Pago final' }));
        }
        planSuma(); open('#dlgPlan');
    }
    $('#planAdd').on('click', function () { $('#planRows').append(planRow({})); planSuma(); $('#planRows .input[data-f=label]').last().trigger('focus'); });
    $('#planRows').on('input', '[data-f=pct]', planSuma);
    $(document).on('click', '[data-act=plan-del]', function () { $(this).closest('.fx-plan-row').remove(); planSuma(); });
    $fp.on('submit', function (e) {
        e.preventDefault(); showErr($fp, '');
        var body = {
            proyecto_id: ctxPlan || $('#pProyecto').val(), moneda: $fp.find('[name=moneda]').val(), monto: $fp.find('[name=monto]').val(),
            cuotas: $('#planRows .fx-plan-row').map(function () {
                return { id: $(this).data('id') || 0, label: $(this).find('[data-f=label]').val(), pct: $(this).find('[data-f=pct]').val() };
            }).get()
        };
        $.ajax({ url: U.plan, method: 'POST', contentType: 'application/json', data: JSON.stringify(body), dataType: 'json' })
            .done(saved).fail(function (x) { fail(x, $fp, 'No se pudo guardar.'); });
    });
    $('#planQuitar').on('click', function () {
        var pr = byId(ctxPlan); close();
        confirmar('Quitar de facturación', 'Se borrarán los pagos y actividades de <strong>' + esc(pr.nombre) + '</strong>. El proyecto seguirá existiendo en Kgi → Proyectos.', 'Quitar', function () {
            $.post(U.quitar + '/' + pr.id).done(function (r) { toast(r.message); load(); }).fail(function (x) { toast((x.responseJSON || {}).message || 'No se pudo quitar.', false); });
        });
    });

    /* ---------- eventos ---------- */
    $(document).on('click', '[data-act]', function () {
        var $t = $(this), act = $t.data('act'), pid = $t.data('pid'), id = $t.data('id');
        switch (act) {
            case 'print': window.print(); break;
            case 'nuevo-plan': abrirPlan(null); break;
            case 'edit-plan': abrirPlan(pid); break;
            case 'edit-cuota': abrirCuota(pid, id); break;
            case 'add-act': abrirAct(pid); break;
            case 'edit-act': abrirAct(pid, id); break;
            case 'del-act': {
                var pr = byId(pid), a = pr.acts.filter(function (x) { return x.id === Number(id); })[0];
                confirmar('Eliminar actividad', '¿Eliminar <strong>' + esc(a.nombre) + '</strong>?', 'Eliminar', function () {
                    $.post(U.actividadDel + '/' + id).done(function (r) { toast(r.message); load(); }).fail(function (x) { toast((x.responseJSON || {}).message || 'No se pudo eliminar.', false); });
                });
                break;
            }
            case 'empresa': S.empresa = String($t.data('v')); $('#chips .fx-chip').each(function () { $(this).attr('aria-pressed', String($(this).data('v') === S.empresa)); }); renderMain(); break;
            case 'clear': S.empresa = '__all__'; S.estado = '__all__'; S.q = ''; $('#q').val(''); $('#fEstado').val('__all__'); renderChips(); renderMain(); break;
        }
    });
    var tq = null;
    $('#q').on('input', function () { var v = this.value.trim(); clearTimeout(tq); tq = setTimeout(function () { S.q = v; renderMain(); }, 150); });
    $('#fEstado').on('change', function () { S.estado = this.value; renderMain(); });
    document.getElementById('contenido').addEventListener('toggle', function (e) {
        if (e.target.tagName === 'DETAILS') { S.abiertos[e.target.dataset.pid] = e.target.open; }
    }, true);

    // Al imprimir se despliegan todos los desgloses.
    var antes = [];
    window.addEventListener('beforeprint', function () { antes = $('details.fx-details').get().map(function (d) { return [d, d.open]; }); antes.forEach(function (x) { x[0].open = true; }); });
    window.addEventListener('afterprint', function () { antes.forEach(function (x) { x[0].open = x[1]; }); });

    load();
})(jQuery);
