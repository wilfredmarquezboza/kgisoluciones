<?= $this->extend('auth/layout') ?>
<?= $this->section('content') ?>
<h1 class="title is-4">Nueva contraseña</h1>
<p class="subtitle is-6 has-text-grey">Elige una contraseña de al menos 8 caracteres.</p>
<?= view('auth/_flash') ?>
<form method="post" action="<?= site_url('restablecer/' . $token) ?>" id="authForm">
    <?= csrf_field() ?>
    <div class="field">
        <label class="label" for="password">Nueva contraseña</label>
        <div class="control has-icons-left has-icons-right">
            <input class="input" type="password" id="password" name="password" minlength="8" required autofocus autocomplete="new-password">
            <span class="icon is-left"><i class="fa-solid fa-lock"></i></span>
            <button type="button" class="icon is-right toggle-pass" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="strength"><span id="strengthBar"></span></div>
    </div>
    <div class="field">
        <label class="label" for="password_conf">Confirmar contraseña</label>
        <div class="control has-icons-left">
            <input class="input" type="password" id="password_conf" name="password_conf" minlength="8" required autocomplete="new-password">
            <span class="icon is-left"><i class="fa-solid fa-lock"></i></span>
        </div>
        <p class="help is-danger is-hidden" id="matchHelp">Las contraseñas no coinciden.</p>
    </div>
    <button class="button is-primary is-fullwidth" type="submit">Guardar contraseña</button>
</form>
<?= $this->endSection() ?>
