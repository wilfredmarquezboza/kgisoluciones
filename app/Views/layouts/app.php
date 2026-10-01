<?php
$u    = usuario_actual();
$path   = trim(service('uri')->getPath(), '/');
// Ruta activa: coincidencia exacta o el prefijo más largo (p. ej. perfiles/permisos/3 -> perfiles).
$activa = null;
foreach (menu_permitido() as [, $its]) {
    foreach (array_keys($its) as $r) {
        if ($path === $r) { $activa = $r; break 2; }
        if (str_starts_with($path, $r . '/') && strlen($r) > strlen((string) $activa)) { $activa = $r; }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <title><?= esc($title ?? 'KGI Soluciones') ?> · KGI Soluciones</title>
    <?= view('layouts/head') ?>
</head>
<body class="app">
<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="<?= site_url('/') ?>"><strong>KGI</strong> Soluciones <small>v1.0</small></a>

    <div class="sidebar-user">
        <span class="avatar"><?= esc(iniciales($u['nombres'] ?? '')) ?></span>
        <div>
            <div class="name"><?= esc($u['nombres'] ?? '') ?></div>
            <div class="role"><i class="fa-solid fa-location-dot"></i> Perfil <?= esc($u['perfil'] ?? '') ?></div>
        </div>
    </div>

    <p class="sidebar-label">GENERAL</p>
    <?php foreach (menu_permitido() as $grupo => [$icono, $items]):
        $abierto = $activa !== null && array_key_exists($activa, $items); ?>
        <div class="menu-group <?= $abierto ? 'is-open' : '' ?>">
            <button type="button" class="menu-group-toggle" aria-expanded="<?= $abierto ? 'true' : 'false' ?>">
                <span><i class="fa-solid <?= $icono ?>"></i><?= esc($grupo) ?></span>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </button>
            <ul class="menu-items">
                <?php foreach ($items as $ruta => [$label, $ico]): ?>
                    <li><a href="<?= site_url($ruta) ?>" class="<?= $activa === $ruta ? 'is-active' : '' ?>">
                        <i class="fa-solid <?= $ico ?>"></i><?= esc($label) ?></a></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endforeach ?>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main">
    <header class="topbar">
        <button class="button is-white is-hidden-desktop" id="menuBtn" aria-label="Menú"><i class="fa-solid fa-bars"></i></button>
        <div class="dropdown is-right ml-auto" id="userMenu">
            <button class="user-btn dropdown-trigger" type="button" aria-haspopup="true">
                <span class="avatar is-small"><?= esc(iniciales($u['nombres'] ?? '')) ?></span>
                <span class="is-hidden-mobile"><?= esc($u['nombres'] ?? '') ?></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu"><div class="dropdown-content">
                <div class="dropdown-item"><strong><?= esc($u['nombres'] ?? '') ?></strong><br><small class="has-text-grey"><?= esc($u['email'] ?? '') ?></small></div>
                <hr class="dropdown-divider">
                <form method="post" action="<?= site_url('logout') ?>">
                    <?= csrf_field() ?>
                    <button class="dropdown-item as-button" type="submit"><i class="fa-solid fa-right-from-bracket mr-2"></i>Cerrar sesión</button>
                </form>
            </div></div>
        </div>
    </header>

    <div class="page-title"><h1><?= esc($title ?? '') ?></h1></div>
    <main class="content-wrap">
        <?= $this->renderSection('content') ?>
    </main>
</div>

<div id="toasts" class="toasts"></div>
<script src="<?= base_url('assets/lib/jquery.min.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?php foreach (['success' => true, 'error' => false] as $k => $ok): if ($m = session()->getFlashdata($k)): ?>
<script>window.toast && toast(<?= json_encode($m) ?>, <?= $ok ? 'true' : 'false' ?>);</script>
<?php endif; endforeach ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
