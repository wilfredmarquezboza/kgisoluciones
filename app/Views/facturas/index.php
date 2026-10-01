<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="fx-toolbar">
    <p class="has-text-grey fx-lede">Cobros por empresa y proyecto. Los montos son netos de detracción, es decir, lo que recibes en tu cuenta.</p>
    <div class="buttons">
        <button type="button" class="button is-primary" data-act="nuevo-plan"><span class="icon"><i class="fa-solid fa-plus"></i></span><span>Agregar proyecto</span></button>
        <a class="button" href="<?= site_url('facturas/exportar') ?>"><span class="icon"><i class="fa-solid fa-file-csv"></i></span><span>Exportar CSV</span></a>
        <button type="button" class="button" data-act="print"><span class="icon"><i class="fa-solid fa-print"></i></span><span>Exportar a PDF</span></button>
    </div>
</div>

<div class="box fx-filters">
    <div class="columns is-multiline is-variable is-2">
        <div class="column is-7-tablet">
            <label class="label is-small" for="q">Buscar proyecto, empresa o factura</label>
            <input id="q" class="input" type="search" placeholder="Ej. Marina, FCT 51" autocomplete="off" maxlength="80">
        </div>
        <div class="column is-5-tablet">
            <label class="label is-small" for="fEstado">Estado del pago</label>
            <div class="select is-fullwidth"><select id="fEstado">
                <option value="__all__">Todos</option>
                <option value="pendiente">Pendiente</option>
                <option value="facturado">Facturado sin cobrar</option>
                <option value="pagado">Pagado</option>
            </select></div>
        </div>
    </div>
    <div class="fx-chips" id="chips" role="group" aria-label="Filtrar por empresa"></div>
</div>

<section class="fx-kpis" id="kpis" aria-label="Resumen por moneda" aria-live="polite"></section>
<div id="contenido"><p class="has-text-grey">Cargando…</p></div>
<p class="has-text-grey is-size-7 mt-4">Los montos en soles y en dólares se suman por separado porque no se convierten entre sí. IGV y detracción se configuran en Seguridad → Configuración (claves IGV y DETRACCION).</p>

<!-- Pago -->
<div class="modal" id="dlgCuota">
    <div class="modal-background" data-close></div>
    <form class="modal-card" id="frmCuota" novalidate>
        <header class="modal-card-head"><div><p class="modal-card-title">Actualizar pago</p><p class="is-size-7 has-text-grey" id="dlgCuotaSub"></p></div></header>
        <section class="modal-card-body">
            <div class="field">
                <label class="label">Estado</label>
                <label class="fx-radio"><input type="radio" name="estado" value="pendiente"> Pendiente de facturar</label>
                <label class="fx-radio"><input type="radio" name="estado" value="facturado"> Facturado, sin cobrar</label>
                <label class="fx-radio"><input type="radio" name="estado" value="pagado"> Pagado</label>
            </div>
            <div class="field">
                <label class="label" for="cFactura">N° de factura</label>
                <input id="cFactura" class="input" name="factura" maxlength="30" autocomplete="off" placeholder="Ej. FCT 51">
            </div>
            <div class="field">
                <label class="label" for="cFecha">Fecha de pago</label>
                <input id="cFecha" class="input" name="fecha" type="date">
                <p class="help">Se habilita al marcar el pago como pagado.</p>
            </div>
            <p class="help is-danger fx-error" hidden></p>
        </section>
        <footer class="modal-card-foot">
            <button type="submit" class="button is-primary">Guardar cambios</button>
            <button type="button" class="button" data-close>Cancelar</button>
        </footer>
    </form>
</div>

<!-- Actividad -->
<div class="modal" id="dlgAct">
    <div class="modal-background" data-close></div>
    <form class="modal-card" id="frmAct" novalidate>
        <header class="modal-card-head"><div><p class="modal-card-title" id="dlgActTitulo">Nueva actividad</p><p class="is-size-7 has-text-grey" id="dlgActSub"></p></div></header>
        <section class="modal-card-body">
            <input type="hidden" name="id">
            <div class="field">
                <label class="label" for="aNombre">Actividad</label>
                <input id="aNombre" class="input" name="nombre" maxlength="120" autocomplete="off" placeholder="Ej. Levantamiento de campo">
            </div>
            <div class="columns is-variable is-2">
                <div class="column"><label class="label" for="aFecha">Fecha</label><input id="aFecha" class="input" name="fecha" type="date"></div>
                <div class="column"><label class="label" for="aEstado">Estado</label>
                    <div class="select is-fullwidth"><select id="aEstado" name="estado"><option>En proceso</option><option>Presentado</option><option>Concluido</option></select></div></div>
                <div class="column"><label class="label" for="aPct">% del proyecto</label><input id="aPct" class="input" name="pct" type="number" min="1" max="100" step="1" inputmode="numeric"></div>
            </div>
            <p class="help">El avance del proyecto suma solo las actividades concluidas.</p>
            <p class="help is-danger fx-error" hidden></p>
        </section>
        <footer class="modal-card-foot">
            <button type="submit" class="button is-primary">Guardar actividad</button>
            <button type="button" class="button" data-close>Cancelar</button>
        </footer>
    </form>
</div>

<!-- Plan de pagos -->
<div class="modal" id="dlgPlan">
    <div class="modal-background" data-close></div>
    <form class="modal-card fx-plan-card" id="frmPlan" novalidate>
        <header class="modal-card-head"><div><p class="modal-card-title" id="dlgPlanTitulo">Plan de pagos</p><p class="is-size-7 has-text-grey" id="dlgPlanSub"></p></div></header>
        <section class="modal-card-body">
            <div class="field" id="planProyectoWrap">
                <label class="label" for="pProyecto">Proyecto</label>
                <div class="select is-fullwidth"><select id="pProyecto" name="proyecto_id"></select></div>
                <p class="help">Solo aparecen proyectos sin monto. Créalos en Kgi → Proyectos.</p>
            </div>
            <div class="columns is-variable is-2">
                <div class="column is-5"><label class="label" for="pMoneda">Moneda</label>
                    <div class="select is-fullwidth"><select id="pMoneda" name="moneda"><option value="USD">Dólares (US$)</option><option value="PEN">Soles (S/)</option></select></div></div>
                <div class="column"><label class="label" for="pMonto">Monto total sin IGV</label><input id="pMonto" class="input" name="monto" type="number" min="0" step="0.01" inputmode="decimal"></div>
            </div>
            <label class="label">Pagos</label>
            <div id="planRows"></div>
            <div class="fx-plan-foot">
                <button type="button" class="button is-small" id="planAdd"><span class="icon is-small"><i class="fa-solid fa-plus"></i></span><span>Agregar pago</span></button>
                <span id="planSuma" class="is-size-7"></span>
            </div>
            <p class="help is-danger fx-error" hidden></p>
        </section>
        <footer class="modal-card-foot">
            <button type="submit" class="button is-primary">Guardar plan</button>
            <button type="button" class="button" data-close>Cancelar</button>
            <button type="button" class="button is-danger is-light ml-auto" id="planQuitar" hidden>Quitar de facturación</button>
        </footer>
    </form>
</div>

<!-- Confirmación -->
<div class="modal" id="dlgConfirm">
    <div class="modal-background" data-close></div>
    <div class="modal-card modal-sm">
        <header class="modal-card-head"><p class="modal-card-title" id="confTitulo">Confirmar</p></header>
        <section class="modal-card-body" id="confTexto"></section>
        <footer class="modal-card-foot"><button class="button is-danger" id="confYes">Eliminar</button><button class="button" data-close>Cancelar</button></footer>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>window.FX_URLS = {
    datos: <?= json_encode(site_url('facturas/datos')) ?>, plan: <?= json_encode(site_url('facturas/plan')) ?>,
    quitar: <?= json_encode(site_url('facturas/quitar')) ?>, cuota: <?= json_encode(site_url('facturas/cuota')) ?>,
    actividad: <?= json_encode(site_url('facturas/actividad')) ?>, actividadDel: <?= json_encode(site_url('facturas/actividad-eliminar')) ?>
};</script>
<script src="<?= base_url('assets/js/facturas.js') ?>"></script>
<?= $this->endSection() ?>
