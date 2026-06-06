<?php   

// ─── CONFIGURACIÓN DE LA API ───────────────────────────────────────────────────
//   PASO 1 — Obtener token de acceso:
//   POST http://localhost:8000/api.php?action=login
//   Body: { "usuario": "evaluador", "password": "tresguerras2026" }
//
//   PASO 2 — Consultar rastreo:
//   POST http://localhost:8000/api.php?action=rastreo
//   Header: Authorization: Bearer {token del paso 1}
//   Body:   { "guia": "SCA00344525" }

require_once __DIR__ . '/config.php';

// ─── HEADERS ─────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
 
// ─── SOLO ACEPTA POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError(405, 'Método no permitido. Usa POST.');
}

// ─── LEER ACTION ─────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
 
if (empty($action)) {
    responderError(400, 'Parámetro requerido: ?action=login o ?action=rastreo');
}

// ─── RUTEAR ──────────────────────────────────────────────────────────────────
switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'rastreo':
        handleRastreo();
        break;
    default:
        responderError(400, "Acción no reconocida: '$action'. Usa login o rastreo.");
}

// =============================================================================
// HANDLER: LOGIN — genera token de acceso a tu API
// =============================================================================
function handleLogin(): void
{
    $body = leerBody();
 
    $usuario  = trim($body['usuario']  ?? '');
    $password = trim($body['password'] ?? '');
 
    if (empty($usuario) || empty($password)) {
        responderError(400, 'Se requieren los campos: usuario y password.');
    }
 
    if ($usuario !== MI_API_USUARIO || $password !== MI_API_PASSWORD) {
        responderError(401, 'Credenciales incorrectas.');
    }
 
    // Generar token: base64 de datos únicos + firma simple
    $payload = base64_encode(json_encode([
        'usr' => $usuario,
        'iat' => time(),
        'exp' => time() + TOKEN_DURACION,
    ]));
    $firma = hash_hmac('sha256', $payload, MI_API_USUARIO . MI_API_PASSWORD);
    $token = $payload . '.' . $firma;
 
    responderExito([
        'token'     => $token,
        'tipo'      => 'Bearer',
        'emitido'   => date('d-m-Y H:i:s'),
        'expira_en' => TOKEN_DURACION . ' segundos (' . (TOKEN_DURACION / 60) . ' minutos)',
        'uso'       => 'Agrega este token en el header Authorization: Bearer {token} para consumir ?action=rastreo',
    ]);
}

// =============================================================================
// HANDLER: RASTREO — consulta guía en Tresguerras y filtra historial
// =============================================================================
function handleRastreo(): void
{
    // ── Validar token de tu API ───────────────────────────────────────────────
    $token = obtenerTokenHeader();
 
    if (!validarToken($token)) {
        responderError(403, 'Token inválido o expirado. Haz login en ?action=login para obtener uno nuevo.');
    }
 
    // ── Leer y validar body ───────────────────────────────────────────────────
    $body = leerBody();
    $guia = strtoupper(trim($body['guia'] ?? ''));
 
    if (empty($guia)) {
        responderError(400, 'Se requiere el campo: guia. Ejemplo: { "guia": "SCA00344525" }');
    }
 
    // ── Determinar estado objetivo según prefijo ──────────────────────────────
    $estadoObjetivo = null;
    foreach (ESTADOS_NODO as $prefijo => $estado) {
        if (str_starts_with($guia, $prefijo)) {
            $estadoObjetivo = $estado;
            break;
        }
    }
 
    if ($estadoObjetivo === null) {
        responderError(422, "Prefijo de guía no reconocido: '$guia'. Prefijos válidos: " . implode(', ', array_keys(ESTADOS_NODO)));
    }

    // ── PASO 1: Obtener token de Tresguerras ──────────────────────────────────
    $tokenResponse = httpPost(
        TG_BASE_URL . '?action=ApiToken',
        [
            'Access_Usr'  => TG_ACCESS_USR,
            'Access_Pass' => TG_ACCESS_PASS,
        ]
    );
 
    if ($tokenResponse['status'] !== 200 || empty($tokenResponse['data']['token'])) {
        responderError(502, 'No se pudo obtener el token de Tresguerras.', [
            'detalle' => $tokenResponse['raw'] ?? null,
        ]);
    }
 
    $tokenTG = $tokenResponse['data']['token'];

    // ── PASO 2: Consultar rastreo en Tresguerras ──────────────────────────────
    $rastreoResponse = httpPost(
        TG_BASE_URL . '?action=ApiRastreo',
        [
            'type'    => '01',
            'rastreo' => $guia,
        ],
        ["Authorization: Bearer $tokenTG"]
    );
 
    if ($rastreoResponse['status'] !== 200) {
        responderError(502, 'Error al consultar rastreo en Tresguerras.', [
            'http_status' => $rastreoResponse['status'],
        ]);
    }

    $data   = $rastreoResponse['data'];
    $errors = $data['Error'] ?? [];
 
    if (!empty($errors) && is_array($errors)) {
        responderError(404, 'La guía no fue encontrada en Tresguerras.', [
            'detalle' => $errors,
        ]);
    }
    
    // ── PASO 3: Filtrar historial hasta el estado objetivo ────────────────────
    $estadosFiltrados = filtrarEstados($data['estados'] ?? [], $estadoObjetivo);

 
    // ultimo_estado = último elemento del historial ya filtrado
    $ultimoEstado = !empty($estadosFiltrados)
    ? end($estadosFiltrados)
    : [];
 
    // ── Construir y devolver respuesta ────────────────────────────────────────
    $respuesta = array_merge($data, [
        'ultimo_estado' => [$ultimoEstado],
        'estados'       => $estadosFiltrados,
        'Error'         => [],
    ]);
 
    responderExito($respuesta);
}

// =============================================================================
// FUNCIONES AUXILIARES
// =============================================================================
/**
 * Mantiene el historial hasta el estado objetivo (inclusive).
 * Todo lo que venga después se omite.
 *
 * Original:  [01, 02, 03, 04, 05, 18, 06, 08, 07]
 * Filtrado:  [01, 02, 03, 04, 05, 18, 06, 08]     (objetivo = 08)
 */

function filtrarEstados(array $estados, string $estadoObjetivo): array
{
    $filtrados = [];
 
    foreach ($estados as $item) {
        $filtrados[] = $item;
 
        $codigoItem = $item['estadoembarque'] ?? $item['estado'] ?? '';
 
        if ((string)$codigoItem === (string)$estadoObjetivo) {
            break;
        }
    }
 
    // NO se sobreescribe el campo 'estado' — mantiene el texto original
    // Solo cortamos el historial en el punto correcto
 
 
    return $filtrados;
}
 

/**
 * Extraer token del header Authorization: Bearer {token}
 */
function obtenerTokenHeader(): string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION']
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
         ?? '';
 
    if (empty($auth)) {
        responderError(401, 'Token requerido. Agrega el header: Authorization: Bearer {token}');
    }
 
    $partes = explode(' ', trim($auth));
    return $partes[1] ?? '';
}

/**
 * Validar token generado por tu API:
 * - Verifica la firma
 * - Verifica que no haya expirado
 */
function validarToken(string $token): bool
{
    $partes = explode('.', $token);
    if (count($partes) !== 2) return false;
 
    [$payload64, $firmaRecibida] = $partes;
 
    // Verificar firma
    $firmaEsperada = hash_hmac('sha256', $payload64, MI_API_USUARIO . MI_API_PASSWORD);
    if (!hash_equals($firmaEsperada, $firmaRecibida)) return false;
 
    // Verificar expiración
    $payload = json_decode(base64_decode($payload64), true);
    if (!$payload || time() > ($payload['exp'] ?? 0)) return false;
 
    return true;
}

/**
 * Leer y decodificar el body JSON de la petición
 */
function leerBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
 
    if (json_last_error() !== JSON_ERROR_NONE) {
        responderError(400, 'Body inválido. Se esperaba JSON. Error: ' . json_last_error_msg());
    }
 
    return $body ?? [];
}

/**
 * Realizar petición POST con cURL
 */
function httpPost(string $url, array $body, array $headers = []): array
{
    $ch = curl_init($url);
 
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => array_merge(
            ['Content-Type: application/json'],
            $headers
        ),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
 
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    @curl_close($ch);
 
    if ($error) {
        return ['error' => $error, 'status' => 0, 'data' => null, 'raw' => null];
    }
 
    return [
        'status' => $httpCode,
        'data'   => json_decode($response, true),
        'raw'    => $response,
    ];
}

/**
 * Responder con éxito (200)
 */
function responderExito(array $data): void
{
    http_response_code(200);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Responder con error
 */
function responderError(int $code, string $mensaje, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge([
        'error'   => true,
        'codigo'  => $code,
        'mensaje' => $mensaje,
    ], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
 
?>
