<?php
$mon = static fn ($n, $m) => ($m === 'PEN' ? 'S/ ' : 'US$ ') . number_format($n, 2);
$fecha = static fn ($f) => $f ? date('d/m/Y', strtotime($f)) : '';
$bloque = static function (string $titulo, string $color, array $items, callable $detalle) use ($mon) {
    if (! $items) {
        return;
    } ?>
    <h3 style="margin:22px 0 6px;color:<?= $color ?>"><?= esc($titulo) ?> (<?= count($items) ?>)</h3>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <?php foreach ($items as $i): ?>
            <tr>
                <td style="padding:6px 8px;border-top:1px solid #e5e7eb"><b><?= esc($i['proyecto']) ?></b><br><span style="color:#6b7280"><?= esc($i['empresa']) ?> · <?= esc($i['pago']) ?></span></td>
                <td style="padding:6px 8px;border-top:1px solid #e5e7eb;color:#4b5563"><?= esc($detalle($i)) ?></td>
                <td style="padding:6px 8px;border-top:1px solid #e5e7eb;text-align:right;white-space:nowrap"><b><?= esc($mon($i['monto'], $i['moneda'])) ?></b></td>
            </tr>
        <?php endforeach ?>
    </table>
<?php };
?>
<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#2b2f3a">
    <h2 style="color:#1f2433">KGI Soluciones · Resumen de cobranza</h2>
    <p style="color:#6b7280;margin:0">Montos netos de detracción. <?= date('d/m/Y') ?></p>
    <?php $bloque('Pagos vencidos', '#a2382a', $vencidos, static fn ($i) => 'Factura ' . $i['factura'] . ' · venció ' . $fecha($i['vencimiento']) . ' (' . $i['atraso'] . ' d)'); ?>
    <?php $bloque("Vencen en los próximos $dias días", '#7c5200', $por_vencer, static fn ($i) => 'Factura ' . $i['factura'] . ' · vence ' . $fecha($i['vencimiento'])); ?>
    <?php $bloque('Listos para facturar', '#1a5799', $listos, static fn ($i) => 'Hito concluido: ' . $i['hito']); ?>
    <p style="margin-top:26px"><a href="<?= esc($link, 'attr') ?>" style="background:#00b89c;color:#fff;padding:11px 20px;border-radius:6px;text-decoration:none;display:inline-block">Abrir Control de facturas</a></p>
</div>
