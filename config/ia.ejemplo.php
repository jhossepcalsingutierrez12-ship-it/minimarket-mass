<?php
/**
 * Clave del asistente con IA (Google Gemini).
 *
 * 1. Entra a https://aistudio.google.com/apikey con tu cuenta de Google y toca «Create API key».
 * 2. Copia este archivo y llámalo ia.php (en esta misma carpeta config/).
 * 3. Pega la clave abajo. config/ia.php está en .gitignore: la clave NO se sube a GitHub.
 *
 * Si ia.php no existe, la página del asistente explica cómo configurarlo.
 */
return [
    'clave'  => 'PEGA_AQUI_TU_API_KEY',
    // Modelo de Gemini. "gemini-flash-latest" usa siempre la versión Flash más nueva (rápida y con nivel gratis).
    'modelo' => 'gemini-flash-latest',
];
