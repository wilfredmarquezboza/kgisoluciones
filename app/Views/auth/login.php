<?= $this->extend('auth/layout') ?>
<?= $this->section('content') ?>
<h1 class="title is-4">Iniciar sesión</h1>
<p class="subtitle is-6 has-text-grey">Ingresa tus credenciales para continuar.</p>
<?= view('auth/_flash') ?>
<form method="post" action="<?= site_url('login') ?>" id="authForm">
    <?= csrf_field() ?>
    <div class="field">
        <label class="label" for="email">Correo electrónico</label>
        <div class="control has-icons-left">
            <input class="input" type="email" id="email" name="email" value="<?= esc(old('email')) ?>" required autofocus autocomplete="username">
            <span class="icon is-left"><i class="fa-solid fa-envelope"></i></span>
        </div>
    </div>
    <div class="field">
        <label class="label" for="password">Contraseña</label>
        <div class="control has-icons-left has-icons-right">
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
            <span class="icon is-left"><i class="fa-solid fa-lock"></i></span>
            <button type="button" class="icon is-right toggle-pass" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
        </div>
    </div>
    <div class="field has-text-right"><a href="<?= site_url('recuperar') ?>">¿Olvidaste tu contraseña?</a></div>
    <button class="button is-primary is-fullwidth" type="submit">Ingresar</button>
</form>
<?= $this->endSection() ?>
