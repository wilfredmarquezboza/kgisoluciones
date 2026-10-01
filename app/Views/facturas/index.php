<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="fx-toolbar">
    <p class="has-text-grey fx-lede">Cobros por empresa y proyecto. Los montos son netos de detracción, es decir, lo que recibes en tu cuenta.</p>
    <div class="buttons">
        <?php if (puede('facturas.editar')): ?>
        <button type="button" class="button is-primary" data-act="nuevo-plan"><span class="icon"><i class="fa-solid fa-plus"></i></span><span>Agregar proyecto</span></button>
        <button type="button" class="button" data-act="avisos" title="Envía el resumen de vencidos, por vencer y listos para facturar"><span class="icon"><i class="fa-solid fa-envelope"></i></span><span>Enviar resumen por correo</span></button>
        <?php endif ?>
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
                <option value="listo">Listo para facturar (hito concluido)</option>
                <option value="vencida">Vencido</option>
            </select></div>
        </div>
    </div>
    <div class="fx-chips" id="chips" role="group" aria-label="Filtrar por empresa"></div>
</div>

<div id="alertas" class="fx-alertas"></div>
<section class="fx-kpis" id="kpis" aria-label="Resumen por moneda" aria-live="polite"></section>
<div id="contenido"><p class="has-text-grey">Cargando…</p></div>
<p class="has-text-grey is-size-7 mt-4">Los montos en soles y en dólares se suman por separado porque no se convierten entre sí. IGV y detracción se configuran en Seguridad → Configuración (claves IGV y DETRACCION).</p>

<!-- Pago -->
<div class="modal" id="dlgCuota">
    <div class="modal-background" data-close></div>
    <form class="modal-card fx-cuota-card" id="frmCuota" novalidate>
        <header class="modal-card-head"><div><p class="modal-card-title">Actualizar pago</p><p class="is-size-7 has-text-grey" id="dlgCuotaSub"></p></div></header>
        <section class="modal-card-body">
            <div class="field">
                <label class="label">Estado</label>
                <div class="fx-radios">
                    <label class="fx-radio"><input type="radio" name="estado" value="pendiente"> Pendiente de facturar</label>
                    <label class="fx-radio"><input type="radio" name="estado" value="facturado"> Facturado, sin cobrar</label>
                    <label class="fx-radio"><input type="radio" name="estado" value="pagado"> Pagado</label>
                </div>
            </div>
            <div class="columns is-multiline is-variable is-2">
                <div class="column is-6"><label class="label" for="cHito">Hito que habilita el cobro</label>
                    <div class="select is-fullwidth"><select id="cHito" name="actividad_id"></select></div></div>
                <div class="column is-6"><label class="label" for="cEstimada">Fecha estimada de cobro</label><input id="cEstimada" class="input" name="estimada" type="date"></div>
            </div>

            <fieldset class="fx-fieldset" id="grpFactura">
                <legend>Factura</legend>
                <div class="columns is-multiline is-variable is-2">
                    <div class="column is-4"><label class="label" for="cFactura">N° de factura</label><input id="cFactura" class="input" name="factura" maxlength="30" autocomplete="off" placeholder="Ej. FCT 51"></div>
                    <div class="column is-4"><label class="label" for="cEmision">Emisión</label><input id="cEmision" class="input" name="emision" type="date"></div>
                    <div class="column is-4"><label class="label" for="cVenc">Vencimiento</label><input id="cVenc" class="input" name="vencimiento" type="date"></div>
                </div>
                <div class="columns is-multiline is-variable is-2">
                    <div class="column is-4"><label class="label" for="cDetFecha">Detracción depositada</label><input id="cDetFecha" class="input" name="detr_fecha" type="date"></div>
                    <div class="column is-4"><label class="label" for="cDetRef">N° de constancia</label><input id="cDetRef" class="input" name="detr_ref" maxlength="40" autocomplete="off"></div>
                    <div class="column is-4"><label class="label" for="cFecha">Fecha de pago</label><input id="cFecha" class="input" name="fecha" type="date"></div>
                </div>
                <p class="help" id="detrHint"></p>
            </fieldset>

            <fieldset class="fx-fieldset" id="grpAbonos">
                <legend>Cobros (abonos)</legend>
                <p class="help mb-2" id="abonoResumen"></p>
                <ul id="abonoLista" class="fx-abonos"></ul>
                <div class="columns is-multiline is-variable is-2 fx-abono-form">
                    <div class="column is-3"><input class="input is-small" id="abFecha" type="date" aria-label="Fecha del abono"></div>
                    <div class="column is-3"><input class="input is-small" id="abMonto" type="number" min="0" step="0.01" placeholder="Monto" aria-label="Monto del abono"></div>
                    <div class="column is-3"><input class="input is-small" id="abRef" maxlength="60" placeholder="N° operación / banco" aria-label="Referencia"></div>
                    <div class="column is-3"><button type="button" class="button is-small is-fullwidth" id="abAdd">Registrar abono</button></div>
                </div>
                <p class="help">Un abono que cubre el saldo marca el pago como pagado automáticamente.</p>
            </fieldset>
            <fieldset class="fx-fieldset" id="grpAdj">
                <legend>Documentos adjuntos</legend>
                <ul id="adjLista" class="fx-abonos"></ul>
                <div class="columns is-multiline is-variable is-2 fx-abono-form">
                    <div class="column is-4"><div class="select is-small is-fullwidth"><select id="adjTipo" aria-label="Tipo de documento">
                        <option value="factura_pdf">Factura (PDF)</option><option value="factura_xml">Factura (XML)</option>
                        <option value="constancia">Constancia de detracción</option><option value="otro">Otro</option></select></div></div>
                    <div class="column is-5"><input class="input is-small" id="adjArchivo" type="file" accept=".pdf,.xml,.jpg,.jpeg,.png" aria-label="Archivo"></div>
                    <div class="column is-3"><button type="button" class="button is-small is-fullwidth" id="adjAdd">Subir</button></div>
                </div>
                <p class="help">PDF, XML, JPG o PNG, hasta 5 MB.</p>
            </fieldset>
            <fieldset class="fx-fieldset" id="grpHist">
                <legend>Historial de cambios</legend>
                <ul id="histLista" class="fx-abonos"></ul>
            </fieldset>
            <p class="help is-danger fx-error" hidden></p>
        </section>
        <footer class="modal-card-foot">
            <button type="submit" class="button is-primary" id="btnGuardarCuota">Guardar cambios</button>
            <button type="button" class="button" data-close>Cerrar</button>
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
<script>window.FX_CAN = <?= json_encode(['editar' => puede('facturas.editar'), 'cobrar' => puede('facturas.cobrar'), 'eliminar' => puede('facturas.eliminar')]) ?>;
window.FX_URLS = {
    historial: <?= json_encode(site_url('facturas/historial')) ?>,
    datos: <?= json_encode(site_url('facturas/datos')) ?>, plan: <?= json_encode(site_url('facturas/plan')) ?>,
    quitar: <?= json_encode(site_url('facturas/quitar')) ?>, cuota: <?= json_encode(site_url('facturas/cuota')) ?>,
    actividad: <?= json_encode(site_url('facturas/actividad')) ?>, abono: <?= json_encode(site_url('facturas/abono')) ?>, adjunto: <?= json_encode(site_url('facturas/adjunto')) ?>, adjuntoDel: <?= json_encode(site_url('facturas/adjunto-eliminar')) ?>, avisos: <?= json_encode(site_url('facturas/avisos')) ?>, abonoDel: <?= json_encode(site_url('facturas/abono-eliminar')) ?>, actividadDel: <?= json_encode(site_url('facturas/actividad-eliminar')) ?>
};</script>
<script src="<?= base_url('assets/js/facturas.js') ?>"></script>
<?= $this->endSection() ?>
