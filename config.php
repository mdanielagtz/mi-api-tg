<?php

// ─── URL BASE TRESGUERRAS ─────────────────────────────────────────────────────
// Todos los endpoints usan esta misma URL, cambia solo ?action=
//   ?action=ApiToken   → obtiene el bearer token (válido 3 min)
//   ?action=ApiRastreo → consulta la guía (requiere el token)
define('TG_BASE_URL',    'https://wsa.tresguerras.com.mx/services/apiTest/CustomerApi/WS_Daniela/');
define('TG_ACCESS_USR',  'MAT00000000');
define('TG_ACCESS_PASS', 'VkZWR1ZVMUVRWGROUkVGM1RVUkNSRlF3TlZWVmEwWlVVbU5QVWxGVlJrUldSazVDVXpCV1dnPT0=');

// ─── CREDENCIALES DE TU API ───────────────────────────────────────────────────
// El evaluador usa estas para hacer login en TU API y obtener su token
define('MI_API_USUARIO',  'evaluador');
define('MI_API_PASSWORD', 'tresguerras2026');

// ─── DURACIÓN DEL TOKEN DE TU API (segundos) ─────────────────────────────────
define('TOKEN_DURACION', 3600); // 1 hora para pruebas, 180 = 3 min como Tresguerras

// ─── MAPA DE PREFIJOS → ESTADO OBJETIVO ──────────────────────────────────────
// Se usa el PREFIJO (primeras 3 letras), no la guía completa.
// Así funciona para cualquier guía que empiece con ese prefijo.
define('ESTADOS_NODO', [
    'SCA' => '08',
    'ECA' => 'CA',
    'PAZ' => 'D5',
    'HMS' => 'CANCELADO',
]);