<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="fx-toolbar">
    <p class="has-text-grey fx-lede">Cobros reales y esperados por mes, en montos netos de detracción. Lo esperado se ubica en la fecha de vencimiento de la factura o, si aún no se factura, en la fecha estimada del pago.</p>
    <div class="buttons">
        <div class="select"><select id="selAtras" aria-label="Meses anteriores"><option value="0">Sin meses anteriores</option><option value="3" selected>3 meses anteriores</option><option value="6">6 meses anteriores</option></select></div>
        <div class="select"><select id="selMeses" aria-label="Meses a proyectar"><option value="6">Próximos 6 meses</option><option value="12" selected>Próximos 12 meses</option><option value="18">Próximos 18 meses</option></select></div>
        <a class="button" id="btnCsv" href="#"><span class="icon"><i class="fa-solid fa-file-csv"></i></span><span>Exportar CSV</span></a>
        <button type="button" class="button" onclick="window.print()"><span class="icon"><i class="fa-solid fa-print"></i></span><span>Imprimir</span></button>
    </div>
</div>
<div id="flujo"><p class="has-text-grey">Cargando…</p></div>
<div id="fxTip" class="fx-tip" role="tooltip" hidden></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>window.FX_FLUJO = { datos: <?= json_encode(site_url('facturas/flujo-datos')) ?>, csv: <?= json_encode(site_url('facturas/flujo-exportar')) ?> };</script>
<script src="<?= base_url('assets/js/flujo.js') ?>"></script>
<?= $this->endSection() ?>
