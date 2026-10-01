<?php foreach (['error' => 'is-danger', 'success' => 'is-success'] as $k => $cls):
    if ($m = session()->getFlashdata($k)): ?>
        <div class="notification <?= $cls ?> is-light" role="alert"><?= esc($m) ?></div>
<?php endif; endforeach ?>
