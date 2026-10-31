<?php
declare(strict_types=1);

/**
 * Toggle: números al azar vs elegir en la grilla (rifa tipo both).
 *
 * @var string $crModoNumerosName
 */
$crModoNumerosName = $crModoNumerosName ?? 'modoNumeros';
?>
<div id="modoAsignacionNumeros" class="d-none cr-modo-numeros">
    <p class="fw-semibold mb-2">¿Cómo quieres tus códigos?</p>
    <div class="cr-modo-numeros__group" role="group" aria-label="Modo de códigos">
        <input type="radio" class="btn-check" name="<?= htmlspecialchars($crModoNumerosName, ENT_QUOTES, 'UTF-8') ?>" id="modoNumerosAuto" value="automatic" checked>
        <label class="btn cr-modo-numeros__btn" for="modoNumerosAuto">Al azar</label>
        <input type="radio" class="btn-check" name="<?= htmlspecialchars($crModoNumerosName, ENT_QUOTES, 'UTF-8') ?>" id="modoNumerosManual" value="manual">
        <label class="btn cr-modo-numeros__btn" for="modoNumerosManual">Yo elijo</label>
    </div>
    <p class="small text-muted mb-0 mt-2" id="modoNumerosHint">Te asignamos códigos libres al confirmar el pago.</p>
</div>
