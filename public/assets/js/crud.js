/* Mantenedor genérico: lista (buscar/ordenar/paginar), formulario en pestaña y eliminar. */
(function ($) {
    'use strict';

    var cfg = JSON.parse($('#crud-config').text());
    var state = { page: 1, per: 10, search: '', sort: '', dir: 'asc', rows: [], total: 0, filtered: 0 };
    var delId = null, timer = null;

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function fmtDate(v) { return v ? String(v).substring(0, 10).split('-').reverse().join('/') : ''; }
    function fmtDateTime(v) { return v ? fmtDate(v) + ' ' + String(v).substring(11, 16) : '<span class="has-text-grey-light">Nunca</span>'; }
    function initials(n) { return (n || '?').trim().split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join(''); }

    function cell(col, row) {
        var v = row[col.key];
        switch (col.type) {
            case 'date': return esc(fmtDate(v));
            case 'datetime': return fmtDateTime(v);
            case 'tag': return '<span class="tag is-light">' + esc(v) + '</span>';
            case 'estado': return Number(v) ? '<span class="tag is-active-ok">Activo</span>' : '<span class="tag is-off">Inactivo</span>';
            case 'avatar': return '<span class="row-avatar"><span class="avatar">' + esc(initials(v)) + '</span>' + esc(v) + '</span>';
            default: return esc(v);
        }
    }

    /* ---------- Lista ---------- */
    function renderHead() {
        var h = '<tr><th style="width:70px">NRO</th>';
        cfg.columns.forEach(function (c) {
            var cls = c.sortable ? 'sortable' : '';
            if (state.sort === c.key) { cls += ' ' + state.dir; }
            h += '<th class="' + cls + '" data-key="' + c.key + '">' + esc(c.label) +
                (c.sortable ? '<i class="fa-solid ' + (state.sort === c.key ? (state.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort') + ' sort"></i>' : '') + '</th>';
        });
        $('#thead').html(h + '<th style="width:110px">ACCIONES</th></tr>');
    }

    function load() {
        $.getJSON(cfg.urls.listar, { page: state.page, per: state.per, search: state.search, sort: state.sort, dir: state.dir })
            .done(function (res) {
                state.rows = res.data; state.total = res.total; state.filtered = res.filtered;
                var pages = Math.max(1, Math.ceil(state.filtered / state.per));
                if (state.page > pages) { state.page = pages; return load(); }
                renderBody(); renderPager(pages);
            })
            .fail(function () { $('#tbody').html('<tr><td class="empty" colspan="' + (cfg.columns.length + 2) + '">No se pudo cargar la información.</td></tr>'); });
    }

    function renderBody() {
        var $b = $('#tbody').empty();
        if (!state.rows.length) {
            $b.append('<tr><td class="empty" colspan="' + (cfg.columns.length + 2) + '">No hay datos para mostrar</td></tr>');
        }
        state.rows.forEach(function (row, i) {
            var tr = '<tr><td class="is-center">' + ((state.page - 1) * state.per + i + 1) + '</td>';
            cfg.columns.forEach(function (c) { tr += '<td>' + cell(c, row) + '</td>'; });
            tr += '<td class="is-center"><span class="row-actions">' +
                '<button class="btn-icon is-edit" data-id="' + row.id + '" title="Editar"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn-icon is-del" data-id="' + row.id + '" title="Eliminar"><i class="fa-solid fa-trash"></i></button></span></td></tr>';
            $b.append(tr);
        });
        var from = state.filtered ? (state.page - 1) * state.per + 1 : 0;
        var to = Math.min(state.page * state.per, state.filtered);
        var info = 'Mostrando registros del ' + from + ' al ' + to + ' de un total de ' + state.filtered + ' registros';
        if (state.filtered !== state.total) { info += ' (filtrado de ' + state.total + ')'; }
        $('#info').text(info);
    }

    function renderPager(pages) {
        var p = state.page, h = '';
        function b(label, page, dis, cur) { return '<button type="button" data-page="' + page + '"' + (dis ? ' disabled' : '') + (cur ? ' class="is-current"' : '') + '>' + label + '</button>'; }
        h += b('Primero', 1, p === 1) + b('Anterior', p - 1, p === 1);
        var start = Math.max(1, p - 2), end = Math.min(pages, start + 4);
        start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) { h += b(i, i, false, i === p); }
        h += b('Siguiente', p + 1, p === pages) + b('Último', pages, p === pages);
        $('#pager').html(h);
    }

    $('#thead').on('click', 'th.sortable', function () {
        var k = $(this).data('key');
        state.dir = (state.sort === k && state.dir === 'asc') ? 'desc' : 'asc';
        state.sort = k; state.page = 1; renderHead(); load();
    });
    $('#pager').on('click', 'button:not(:disabled)', function () { state.page = Number($(this).data('page')); load(); });
    $('#perPage').on('change', function () { state.per = Number(this.value); state.page = 1; load(); });
    $('#search').on('input', function () {
        var v = this.value; clearTimeout(timer);
        timer = setTimeout(function () { state.search = v; state.page = 1; load(); }, 250);
    });

    /* ---------- Pestañas ---------- */
    function tab(name) {
        $('.tabs li').removeClass('is-active').filter('[data-tab="' + name + '"]').addClass('is-active');
        $('#tab-list').toggleClass('is-hidden', name !== 'list');
        $('#tab-form').toggleClass('is-hidden', name !== 'form');
    }
    $('.tabs li').on('click', function () {
        var t = $(this).data('tab');
        if (t === 'form' && !$(this).hasClass('is-active')) { openForm(null); } else { tab(t); }
    });

    /* ---------- Formulario ---------- */
    function fieldHtml(f) {
        var id = 'f_' + f.name, req = f.required === true;
        var input;
        if (f.type === 'select') {
            input = '<div class="select is-fullwidth"><select id="' + id + '" name="' + f.name + '">';
            input += '<option value="">' + esc(f.empty || 'Seleccione…') + '</option>';
            $.each(f.options || {}, function (k, v) { input += '<option value="' + esc(k) + '">' + esc(v) + '</option>'; });
            input += '</select></div>';
        } else if (f.type === 'textarea') {
            input = '<textarea class="textarea" rows="3" id="' + id + '" name="' + f.name + '"></textarea>';
        } else {
            input = '<input class="input" id="' + id + '" name="' + f.name + '" type="' + (f.type || 'text') + '"' + (f.type === 'number' ? ' step="0.01" min="0"' : '') + ' autocomplete="' + (f.type === 'password' ? 'new-password' : 'off') + '">';
        }
        return '<div class="field" data-field="' + f.name + '"><label class="label" for="' + id + '">' + esc(f.label) +
            '<span class="req has-text-danger"' + (req ? '' : ' hidden') + '> *</span></label><div class="control">' + input + '</div>' +
            (f.help ? '<p class="help">' + esc(f.help) + '</p>' : '') + '<p class="help is-danger err"></p></div>';
    }
    $('#formFields').html(cfg.fields.map(fieldHtml).join(''));

    function openForm(row) {
        var $f = $('#form'); clearErrors();
        $f[0].reset();
        $f.find('[name=id]').val(row ? row.id : '');
        cfg.fields.forEach(function (f) {
            var $i = $f.find('[name="' + f.name + '"]');
            var val = row ? row[f.name] : (f.default != null ? f.default : '');
            if (f.type !== 'password') { $i.val(val == null ? '' : val); }
            if (f.required === 'create') { $f.find('[data-field="' + f.name + '"] .req').prop('hidden', !!row); }
        });
        $('#formTabLabel').text(row ? 'Editar Registro' : 'Nuevo Registro');
        tab('form');
        $f.find('input:not([type=hidden]),select,textarea').first().trigger('focus');
    }

    function clearErrors() { $('#form .err').text(''); $('#form .is-danger').removeClass('is-danger'); }

    $('#form').on('submit', function (e) {
        e.preventDefault(); clearErrors();
        var $btn = $('#saveBtn').addClass('is-loading');
        $.post(cfg.urls.guardar, $(this).serialize())
            .done(function (res) { toast(res.message); tab('list'); $('#formTabLabel').text('Nuevo Registro'); load(); })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                if (xhr.status === 422 && r.errors) {
                    $.each(r.errors, function (name, msg) {
                        var $fld = $('[data-field="' + name + '"]');
                        $fld.find('.err').text(msg); $fld.find('input,select,textarea').addClass('is-danger');
                    });
                }
                toast(r.message || 'Error al guardar.', false);
            })
            .always(function () { $btn.removeClass('is-loading'); });
    });
    $('#cancelBtn').on('click', function () { tab('list'); $('#formTabLabel').text('Nuevo Registro'); });

    /* ---------- Editar / eliminar ---------- */
    function rowById(id) { return state.rows.filter(function (r) { return String(r.id) === String(id); })[0]; }
    $('#tbody').on('click', '.is-edit', function () {
        var row = rowById($(this).data('id'));
        if (row) { openForm(row); }
    });
    $('#tbody').on('click', '.is-del', function () {
        var row = rowById($(this).data('id'));
        delId = row.id;
        $('#confirmName').text(cfg.columns.length ? (row[cfg.columns[0].key] || '#' + row.id) : '#' + row.id);
        $('#confirmModal').addClass('is-active');
    });
    function closeConfirm() { $('#confirmModal').removeClass('is-active'); delId = null; }
    $('#confirmNo, #confirmModal .modal-background').on('click', closeConfirm);
    $(document).on('keydown', function (e) { if (e.key === 'Escape') { closeConfirm(); } });
    $('#confirmYes').on('click', function () {
        var $b = $(this).addClass('is-loading');
        $.post(cfg.urls.eliminar + '/' + delId)
            .done(function (res) { toast(res.message); load(); })
            .fail(function (xhr) { toast((xhr.responseJSON || {}).message || 'No se pudo eliminar.', false); })
            .always(function () { $b.removeClass('is-loading'); closeConfirm(); });
    });

    renderHead(); load();
})(jQuery);
