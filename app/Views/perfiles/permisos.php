<?php
use App\Libraries\Permisos;
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="box card-box" style="padding-top:1.2rem">
    <div class="fx-toolbar">
        <div>
            <h2 class="title is-5 mb-1">Permisos del perfil «<?= esc($perfil['nombre']) ?>»</h2>
            <p class="has-text-grey is-size-7">Marca lo que este perfil puede hacer en cada módulo. Cualquier acción incluye poder ver el módulo.</p>
        </div>
        <a class="button" href="<?= site_url('perfiles') ?>"><i class="fa-solid fa-arrow-left mr-2"></i>Volver</a>
    </div>

    <form method="post" action="<?= site_url('perfiles/permisos/' . $perfil['id']) ?>" id="frmPermisos">
        <?= csrf_field() ?>
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable perm-table">
                <thead><tr><th>Módulo</th>
                    <?php foreach (Permisos::ACCIONES as $k => $label): ?><th class="has-text-centered"><?= esc($label) ?></th><?php endforeach ?>
                    <th class="has-text-centered">Todo</th></tr></thead>
                <tbody>
                <?php foreach (Permisos::CATALOGO as $mod => [$nombre, $acciones]): ?>
                    <tr data-mod="<?= esc($mod, 'attr') ?>">
                        <th><?= esc($nombre) ?></th>
                        <?php foreach (array_keys(Permisos::ACCIONES) as $a): ?>
                            <td class="has-text-centered">
                                <?php if (in_array($a, $acciones, true)): $k = "$mod.$a"; ?>
                                    <input type="checkbox" name="permisos[]" value="<?= esc($k, 'attr') ?>" data-a="<?= $a ?>" <?= isset($marcados[$k]) ? 'checked' : '' ?>
                                           aria-label="<?= esc($nombre . ': ' . Permisos::ACCIONES[$a], 'attr') ?>">
                                <?php else: ?><span class="has-text-grey-lighter">—</span><?php endif ?>
                            </td>
                        <?php endforeach ?>
                        <td class="has-text-centered"><input type="checkbox" class="perm-all" aria-label="Marcar todo <?= esc($nombre, 'attr') ?>"></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <p class="help mb-4"><strong>Registrar cobros</strong> permite marcar pagos como pagados, registrar abonos y cambiar porcentajes o montos de pagos ya cobrados.</p>
        <div class="buttons"><button class="button is-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar permisos</button>
            <a class="button is-light" href="<?= site_url('perfiles') ?>">Cancelar</a></div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function ($) {
    function sync($tr) {
        var $b = $tr.find('input[name="permisos[]"]'), n = $b.filter(':checked').length;
        $tr.find('.perm-all').prop('checked', n === $b.length).prop('indeterminate', n > 0 && n < $b.length);
    }
    $('.perm-table tbody tr').each(function () { sync($(this)); });
    $('.perm-table').on('change', '.perm-all', function () { $(this).closest('tr').find('input[name="permisos[]"]').prop('checked', this.checked); });
    $('.perm-table').on('change', 'input[name="permisos[]"]', function () {
        var $tr = $(this).closest('tr');
        // Marcar una acción marca también "Ver"; quitar "Ver" quita todo el módulo.
        if (this.checked && $(this).data('a') !== 'ver') { $tr.find('[data-a=ver]').prop('checked', true); }
        if (!this.checked && $(this).data('a') === 'ver') { $tr.find('input[name="permisos[]"]').prop('checked', false); }
        sync($tr);
    });
})(jQuery);
</script>
<?= $this->endSection() ?>
