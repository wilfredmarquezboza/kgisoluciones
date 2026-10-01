<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<script type="application/json" id="crud-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="box card-box" id="crud">
    <div class="tabs">
        <ul>
            <li class="is-active" data-tab="list"><a><span class="icon is-small"><i class="fa-solid fa-table-cells"></i></span><span>Listar Datos</span></a></li>
            <li data-tab="form"><a><span class="icon is-small"><i class="fa-solid fa-plus"></i></span><span id="formTabLabel">Nuevo Registro</span></a></li>
        </ul>
    </div>

    <!-- Listado -->
    <section id="tab-list">
        <div class="table-tools" id="filtros-wrap" style="margin-bottom:.4rem"><div id="filtros" style="display:flex;flex-wrap:wrap;gap:.6rem"></div></div>
        <div class="table-tools">
            <div class="field is-horizontal-inline">
                <label class="label-inline">Mostrar</label>
                <div class="select is-small"><select id="perPage"><option>10</option><option>25</option><option>50</option><option>100</option></select></div>
                <label class="label-inline">registros</label>
            </div>
            <div class="field is-horizontal-inline">
                <label class="label-inline" for="search">Buscar :</label>
                <input class="input is-small" type="search" id="search" autocomplete="off" placeholder="Escribe para filtrar…">
            </div>
        </div>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable data-table">
                <thead id="thead"></thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>

        <div class="table-footer">
            <span id="info" class="has-text-grey"></span>
            <nav class="pagination-nav" id="pager"></nav>
        </div>
    </section>

    <!-- Formulario -->
    <section id="tab-form" class="is-hidden">
        <form id="form" novalidate autocomplete="off" class="form-narrow">
            <input type="hidden" name="id" value="">
            <div id="formFields"></div>
            <div class="buttons mt-5">
                <button type="submit" class="button is-primary" id="saveBtn"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar</button>
                <button type="button" class="button is-light" id="cancelBtn">Cancelar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal" id="detailModal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width:min(680px,calc(100vw - 24px))">
        <header class="modal-card-head"><div><p class="modal-card-title" id="detailTitle"></p><p class="is-size-7 has-text-grey" id="detailMeta"></p></div></header>
        <section class="modal-card-body" id="detailBody"></section>
        <footer class="modal-card-foot"><button class="button" id="detailClose">Cerrar</button></footer>
    </div>
</div>

<div class="modal" id="confirmModal">
    <div class="modal-background"></div>
    <div class="modal-card modal-sm">
        <header class="modal-card-head"><p class="modal-card-title">Eliminar registro</p></header>
        <section class="modal-card-body">¿Seguro que deseas eliminar <strong id="confirmName"></strong>? Esta acción no se puede deshacer.</section>
        <footer class="modal-card-foot">
            <button class="button is-danger" id="confirmYes">Eliminar</button>
            <button class="button" id="confirmNo">Cancelar</button>
        </footer>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/crud.js') ?>"></script>
<?= $this->endSection() ?>
