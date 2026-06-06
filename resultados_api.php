<?php
 
// =============================================================================
// CAPTURA DE RESULTADOS — Mi API Tresguerras
// Consume la API desplegada en Railway y guarda los resultados en JSON
// =============================================================================
 
const MI_API_URL  = "https://mi-api-tg-production.up.railway.app";
const MI_USUARIO  = "evaluador";
const MI_PASSWORD = "tresguerras2026";
 
const GUIAS = [
    "SCA00344525",
    "ECA00736128",
    "PAZ00065909",
    "HMS00208735",
];
 
// =============================================================================
// FUNCIÓN: POST con cURL
// =============================================================================
function httpPost(string $url, array $body, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => array_merge(
            ["Content-Type: application/json"],
            $headers
        ),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    @curl_close($ch);
 
    return [
        "status" => $httpCode,
        "data"   => json_decode($response, true),
        "raw"    => $response,
    ];
}
 
// =============================================================================
// PASO 1: Login en Mi API → obtener token propio
// =============================================================================
$sep = str_repeat("─", 60);
 
echo PHP_EOL;
echo "╔" . str_repeat("═", 58) . "╗" . PHP_EOL;
echo "║   CAPTURA DE RESULTADOS — MI API TRESGUERRAS           ║" . PHP_EOL;
echo "╚" . str_repeat("═", 58) . "╝" . PHP_EOL;
echo PHP_EOL;
 
echo "─── PASO 1: Login en Mi API ────────────────────────────────" . PHP_EOL;
 
$loginResponse = httpPost(
    MI_API_URL . "/api.php?action=login",
    [
        "usuario"  => MI_USUARIO,
        "password" => MI_PASSWORD,
    ]
);
 
if ($loginResponse["status"] !== 200 || empty($loginResponse["data"]["token"])) {
    die("❌ Error al hacer login.\n" . $loginResponse["raw"] . "\n");
}
 
$miToken = $loginResponse["data"]["token"];
 
echo "✅ Login exitoso." . PHP_EOL;
echo "   Token     : " . substr($miToken, 0, 40) . "..." . PHP_EOL;
echo "   Emitido   : " . ($loginResponse["data"]["emitido"]    ?? "N/A") . PHP_EOL;
echo "   Expira en : " . ($loginResponse["data"]["expira_en"]  ?? "N/A") . PHP_EOL;
echo PHP_EOL;
 
// Guardar respuesta del login
$resultadoLogin = [
    "endpoint"  => MI_API_URL . "/api.php?action=login",
    "metodo"    => "POST",
    "body_enviado" => [
        "usuario"  => MI_USUARIO,
        "password" => MI_PASSWORD,
    ],
    "http_status" => $loginResponse["status"],
    "respuesta"   => $loginResponse["data"],
];
 
// =============================================================================
// PASO 2: Rastrear cada guía con Mi API
// =============================================================================
echo "─── PASO 2: Rastreando guías con Mi API ────────────────────" . PHP_EOL;
echo PHP_EOL;
 
$resultadosRastreo = [];
 
foreach (GUIAS as $guia) {
    echo "📦 Rastreando: $guia" . PHP_EOL;
 
    $rastreoResponse = httpPost(
        MI_API_URL . "/api.php?action=rastreo",
        ["guia" => $guia],
        ["Authorization: Bearer $miToken"]
    );
 
    $status = $rastreoResponse["status"];
    $data   = $rastreoResponse["data"];
 
    if ($status === 200) {
        $totalEstados = count($data["estados"] ?? []);
        $ultimoEstado = $data["ultimo_estado"][0]["estado"] ?? "N/A";
        echo "   ✅ HTTP $status | Estados: $totalEstados registro(s) | Último estado: $ultimoEstado" . PHP_EOL;
    } else {
        echo "   ❌ HTTP $status | " . ($data["mensaje"] ?? "Error desconocido") . PHP_EOL;
    }
 
    $resultadosRastreo[$guia] = [
        "endpoint"     => MI_API_URL . "/api.php?action=rastreo",
        "metodo"       => "POST",
        "body_enviado" => ["guia" => $guia],
        "http_status"  => $status,
        "respuesta"    => $data,
    ];
}
 
echo PHP_EOL;
 
// =============================================================================
// GUARDAR RESULTADOS EN JSON
// =============================================================================
$resultadoFinal = [
    "generado_en" => date("d-m-Y H:i:s"),
    "api_url"     => MI_API_URL,
    "ApiToken"    => $resultadoLogin,
    "ApiRastreo"  => $resultadosRastreo,
];
 
$archivoJson = __DIR__ . "/resultados_api.json";
file_put_contents($archivoJson, json_encode($resultadoFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
 
echo "─── RESULTADO ──────────────────────────────────────────────" . PHP_EOL;
echo "💾 Resultados guardados en: $archivoJson" . PHP_EOL;
echo PHP_EOL;