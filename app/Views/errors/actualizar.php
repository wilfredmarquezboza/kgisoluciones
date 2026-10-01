<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Falta actualizar la base de datos</title>
<style>body{font-family:system-ui,sans-serif;background:#f6f7f9;color:#3a3f4b;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:1rem}
.c{background:#fff;max-width:560px;padding:2rem;border-radius:10px;box-shadow:0 8px 28px rgba(20,23,31,.08)}h1{font-size:1.25rem;margin:0 0 .6rem}code{background:#eef0f3;padding:.15rem .4rem;border-radius:4px}ol{padding-left:1.2rem;line-height:1.7}small{color:#6b7285}</style></head>
<body><div class="c"><h1>Falta actualizar la base de datos</h1>
<p>El sistema tiene cambios nuevos que aún no se aplicaron a la base de datos<?= $detalle ? ' (' . esc($detalle) . ')' : '' ?>. Pide a quien administra el servidor que ejecute:</p>
<ol><li>En la carpeta del proyecto: <code>php spark migrate</code></li><li>Luego: <code>php spark kgi:diagnostico</code> para verificar todo.</li><li>Recarga esta página.</li></ol>
<small>No se perdió ningún dato; la migración solo agrega tablas y columnas.</small></div></body></html>
