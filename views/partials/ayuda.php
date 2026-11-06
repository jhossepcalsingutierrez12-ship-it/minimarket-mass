<?php
/**
 * Recuadro «¿Para qué sirve esta sección?» de las páginas del administrador.
 * Antes de incluirlo, la vista define $ayuda = ['Qué hace...', 'Cómo usarlo...', ...].
 * @var string[] $ayuda
 */
?>
<details class="ayuda" open>
    <summary>¿Para qué sirve esta sección?</summary>
    <ul>
        <?php foreach ($ayuda as $linea): ?><li><?= $linea ?></li><?php endforeach; ?>
    </ul>
</details>
