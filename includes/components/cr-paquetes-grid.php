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
$crPaquetesRowClass = $crPaquetesRowClass ?? 'row g-2 g-md-3';
$minQty = cs_package_min_quantity();
$unitBase = cs_package_unit_price();
$unitBulk = cs_package_bulk_unit_price();
$unitBulk2 = cs_package_bulk2_unit_price();
$bulkFrom = cs_package_bulk_threshold();
$bulk2From = cs_package_bulk2_threshold();
$paquetesOficiales = cs_package_grid_items();
?>
<div class="<?= htmlspecialchars($crPaquetesRowClass, ENT_QUOTES, 'UTF-8') ?> cr-paquetes-grid" id="<?= htmlspecialchars($crPaquetesContainerId, ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach ($paquetesOficiales as $paq):
        $qty = (int)$paq['qty'];
        $priceNow = (int)$paq['price'];
        $priceBefore = (int)($paq['compare_at'] ?? ($qty * $unitBase));
        $hasSaving = $priceBefore > $priceNow;
        $unitNow = $qty > 0 ? (int)round($priceNow / $qty) : $unitBase;
        $badgeSlug = !empty($paq['badge_slug']) ? (string)$paq['badge_slug'] : '';
        $cardClasses = ['paquete-card'];
        if ($badgeSlug !== '') {
            $cardClasses[] = 'paquete-card--' . $badgeSlug;
        }
        if ($hasSaving) {
            $cardClasses[] = 'paquete-card--con-ahorro';
        }
    ?>
    <div class="col-12 col-md-4">
        <input type="radio" class="btn-check paquete-radio" name="<?= htmlspecialchars($crPaquetesName, ENT_QUOTES, 'UTF-8') ?>"
            id="<?= htmlspecialchars($paq['id'] ?? ('paq' . $qty), ENT_QUOTES, 'UTF-8') ?>"
            value="<?= $qty ?>"
            data-price="<?= $priceNow ?>"
            data-compare-at="<?= $priceBefore ?>">
        <label class="<?= htmlspecialchars(implode(' ', $cardClasses), ENT_QUOTES, 'UTF-8') ?>"
            for="<?= htmlspecialchars($paq['id'] ?? ('paq' . $qty), ENT_QUOTES, 'UTF-8') ?>">
            <?php if (!empty($paq['badge'])): ?>
            <span class="badge-paquete badge-paquete--<?= htmlspecialchars($badgeSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$paq['badge'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <div class="paquete-card__body">
                <div class="paquete-card__meta">
                    <span class="paquete-qty"><?= $qty ?> <small>códigos</small></span>
                    <?php if ($hasSaving): ?>
                    <span class="paquete-unit-price">$<?= number_format($unitNow, 0, ',', '.') ?> c/u</span>
                    <?php endif; ?>
                </div>
                <div class="paquete-precios">
                    <?php if ($hasSaving): ?>
                    <span class="paquete-precio-antes">Antes <s>$<?= number_format($priceBefore, 0, ',', '.') ?></s></span>
                    <?php endif; ?>
                    <span class="paquete-precio-final">$<?= number_format($priceNow, 0, ',', '.') ?></span>
                </div>
            </div>
        </label>
    </div>
    <?php endforeach; ?>

    <div class="col-12 col-md-4">
        <input type="radio" class="btn-check paquete-radio" name="<?= htmlspecialchars($crPaquetesName, ENT_QUOTES, 'UTF-8') ?>" id="paqCustom" value="custom">
        <label class="paquete-card paquete-card--personalizado custom" for="paqCustom">
            <div class="paquete-card__body">
                <div class="paquete-card__meta">
                    <span class="paquete-qty">Otro</span>
                    <span class="paquete-unit-price paquete-unit-price--muted">Tú eliges cuántos</span>
                </div>
                <div class="paquete-precios">
                    <span class="paquete-precio-final paquete-precio-final--muted">→</span>
                </div>
            </div>
        </label>
        <input type="tel" id="<?= htmlspecialchars($crCantidadManualId, ENT_QUOTES, 'UTF-8') ?>"
            class="form-control form-control-sm text-center mt-1" min="<?= $minQty ?>" placeholder="Mín. <?= $minQty ?> códigos" style="display:none;">
    </div>

</div>
<div class="cr-paquetes-hint">
    <p class="cr-paquetes-hint__mobile mb-0">Desde <?= $bulkFrom ?> códigos baja el precio de cada uno.</p>
    <div class="cr-paquetes-hint__desktop">
        <p class="mb-1"><strong>Precio normal:</strong> mínimo <?= $minQty ?> códigos · $<?= number_format($unitBase, 0, ',', '.') ?> c/u</p>
        <p class="mb-0"><strong>Precio especial:</strong> desde <?= $bulkFrom ?> códigos · $<?= number_format($unitBulk, 0, ',', '.') ?> c/u · desde <?= $bulk2From ?> códigos · $<?= number_format($unitBulk2, 0, ',', '.') ?> c/u</p>
    </div>
</div>
