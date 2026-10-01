<!doctype html>
<html lang="es">
<head>
    <title><?= esc($title) ?> · KGI Soluciones</title>
    <?= view('layouts/head') ?>
</head>
<body class="auth">
<div class="auth-wrap">
    <aside class="auth-hero">
        <div>
            <div class="auth-logo"><strong>KGI</strong> Soluciones</div>
            <h2>Gestión de proyectos, clientes y procesos en un solo lugar.</h2>
            <p>Plataforma interna de KGI Soluciones.</p>
        </div>
        <small>&copy; <?= date('Y') ?> KGI Soluciones · v1.0</small>
    </aside>
    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-logo is-mobile-only"><strong>KGI</strong> Soluciones</div>
            <?= $this->renderSection('content') ?>
        </div>
    </section>
</div>
<script src="<?= base_url('assets/vendor/jquery.min.js') ?>"></script>
<script src="<?= base_url('assets/js/auth.js') ?>"></script>
</body>
</html>
