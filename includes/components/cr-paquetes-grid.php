<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/pricing-packages.php';

/**
 * Grilla de paquetes (landing + vender).
 *
 * @var string $crPaquetesContainerId
 * @var string $crPaquetesName
 * @var string $crCantidadManualId
 * @var string $crPaquetesRowClass
 */
$crPaquetesContainerId = $crPaquetesContainerId ?? 'paquetesNumeros';
$crPaquetesName = $crPaquetesName ?? 'paqueteNumeros';
$crCantidadManualId = $crCantidadManualId ?? 'cantidadManual';
$crPaquetesRowClass = $crPaquetesRowClass ?? 'row g-3';
$minQty = cs_package_min_quantity();
$paquetesOficiales = cs_package_grid_items();
?>
<div class="<?= htmlspecialchars($crPaquetesRowClass, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($crPaquetesContainerId, ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach ($paquetesOficiales as $paq): ?>
    <div class="col-6 col-md-4">
        <input type="radio" class="btn-check paquete-radio" name="<?= htmlspecialchars($crPaquetesName, ENT_QUOTES, 'UTF-8') ?>"
            id="<?= htmlspecialchars($paq['id'] ?? ('paq' . $paq['qty']), ENT_QUOTES, 'UTF-8') ?>"
            value="<?= (int)$paq['qty'] ?>"
            data-price="<?= (int)$paq['price'] ?>">
        <label class="w-100 py-2 d-flex flex-column align-items-center justify-content-center paquete-card<?= !empty($paq['badge_slug']) ? ' paquete-card--' . htmlspecialchars((string)$paq['badge_slug'], ENT_QUOTES, 'UTF-8') : '' ?>"
            for="<?= htmlspecialchars($paq['id'] ?? ('paq' . $paq['qty']), ENT_QUOTES, 'UTF-8') ?>">
            <?php if (!empty($paq['badge'])): ?>
            <span class="badge-paquete badge-paquete--<?= htmlspecialchars((string)$paq['badge_slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$paq['badge'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <div class="fw-bold"><?= (int)$paq['qty'] ?> nros</div>
            <div class="fs-5 fw-bold paquete-precio-final">$<?= number_format((int)$paq['price'], 0, ',', '.') ?></div>
        </label>
    </div>
    <?php endforeach; ?>

    <div class="col-6 col-md-4">
        <input type="radio" class="btn-check paquete-radio" name="<?= htmlspecialchars($crPaquetesName, ENT_QUOTES, 'UTF-8') ?>" id="paqCustom" value="custom">
        <label class="w-100 py-2 d-flex flex-column align-items-center justify-content-center paquete-card paquete-card--personalizado custom" for="paqCustom">
            <span class="badge-paquete badge-paquete--personalizado">Personalizado</span>
            <div class="fw-bold">Otro</div>
        </label>
        <input type="tel" id="<?= htmlspecialchars($crCantidadManualId, ENT_QUOTES, 'UTF-8') ?>"
            class="form-control form-control-sm text-center mt-1" min="<?= $minQty ?>" placeholder="Mín. <?= $minQty ?> nros" style="display:none;">
    </div>

</div>
<p class="small text-muted text-center mt-2 mb-0 cr-paquetes-hint">
    Compra mínima <?= $minQty ?> nros · Valor unitario $<?= number_format(cs_package_unit_price(), 0, ',', '.') ?> c/u
</p>
