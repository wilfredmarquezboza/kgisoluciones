<?= $this->extend('auth/layout') ?>
<?= $this->section('content') ?>
<h1 class="title is-4">Recuperar contraseña</h1>
<p class="subtitle is-6 has-text-grey">Te enviaremos un enlace para crear una nueva contraseña.</p>
<?= view('auth/_flash') ?>
<form method="post" action="<?= site_url('recuperar') ?>" id="authForm">
    <?= csrf_field() ?>
    <div class="field">
        <label class="label" for="email">Correo electrónico</label>
        <div class="control has-icons-left">
            <input class="input" type="email" id="email" name="email" value="<?= esc(old('email')) ?>" required autofocus>
            <span class="icon is-left"><i class="fa-solid fa-envelope"></i></span>
        </div>
    </div>
    <button class="button is-primary is-fullwidth" type="submit">Enviar enlace</button>
    <p class="has-text-centered mt-4"><a href="<?= site_url('login') ?>"><i class="fa-solid fa-arrow-left mr-1"></i>Volver al inicio de sesión</a></p>
</form>
<?= $this->endSection() ?>
