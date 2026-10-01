<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#2b2f3a">
    <h2 style="color:#1f2433">KGI Soluciones</h2>
    <p>Hola <?= esc($nombre) ?>,</p>
    <p>Recibimos una solicitud para restablecer tu contraseña. Haz clic en el botón (válido por <?= (int) $minutos ?> minutos):</p>
    <p><a href="<?= esc($link, 'attr') ?>" style="background:#00b89c;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;display:inline-block">Restablecer contraseña</a></p>
    <p style="font-size:13px;color:#6b7280">Si no fuiste tú, ignora este correo; tu contraseña no cambiará.</p>
</div>
