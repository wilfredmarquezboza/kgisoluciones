<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="box has-text-centered" style="max-width:520px;margin:3rem auto">
    <p class="is-size-1 has-text-grey-light"><i class="fa-solid fa-lock"></i></p>
    <h2 class="title is-5 mt-3">Sin permiso</h2>
    <p class="has-text-grey"><?= esc($mensaje) ?></p>
    <p class="mt-4 is-size-7 has-text-grey">Si crees que es un error, pide al administrador que revise los permisos de tu perfil.</p>
</div>
<?= $this->endSection() ?>
