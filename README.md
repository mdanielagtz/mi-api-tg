# 🚚 Mi API — Rastreo Tresguerras

API REST desarrollada en PHP que consume la API de Tresguerras, filtra el historial de estados de un embarque y devuelve el resultado procesado.

**URL base:**
```
https://mi-api-tg-production.up.railway.app
```

---

## 🔐 Autenticación

La API usa un sistema de tokens propio con dos pasos, igual que Tresguerras.

### PASO 1 — Obtener token de acceso

```
POST /api.php?action=login
Content-Type: application/json
```

**Body:**
```json
{
  "usuario": "evaluador",
  "password": "tresguerras2026"
}
```

**Respuesta exitosa:**
```json
{
  "token": "eyJ...",
  "tipo": "Bearer",
  "emitido": "06-06-2026 03:10:01",
  "expira_en": "3600 segundos (60 minutos)",
  "uso": "Agrega este token en el header Authorization: Bearer {token}"
}
```

> ⚠️ El token expira en **60 minutos**. Si expira, repite el PASO 1.

---

### PASO 2 — Consultar rastreo de una guía

```
POST /api.php?action=rastreo
Content-Type: application/json
Authorization: Bearer {token del paso 1}
```

**Body:**
```json
{
  "guia": "SCA00344525"
}
```

**Guías de prueba disponibles:**

| Guía | Estado objetivo |
|---|---|
| `SCA00344525` | `08` |
| `ECA00736128` | `CA` |
| `PAZ00065909` | `D5` |
| `HMS00208735` | `CANCELADO` |

**Respuesta exitosa:**
```json
{
  "rastrear": "SCA00344525",
  "talon": "SCA00344525",
  "servicio": "Rastreo : Nacional y Pretalones",
  "ultimo_estado": [
    {
      "estado": "08",
      "sucursal": "GUADALAJARA, JAL."
    }
  ],
  "estados": [
    { "estadoembarque": "01", "estado": "RECIBIDO EN BODEGA EMBARQUES" },
    { "estadoembarque": "02", "estado": "DESPACHADO A SUCURSAL" },
    { "estadoembarque": "08", "estado": "08" }
  ],
  "Error": []
}
```

> El historial `estados` se mantiene completo hasta el estado objetivo. Todo lo que venga después se omite.

---

## ⚠️ Códigos de respuesta

| Código | Significado |
|---|---|
| `200` | Éxito |
| `400` | Body inválido o parámetro faltante |
| `401` | Token requerido o credenciales incorrectas |
| `403` | Token inválido o expirado |
| `404` | Guía no encontrada en Tresguerras |
| `405` | Método no permitido (usar POST) |
| `422` | Prefijo de guía no reconocido |
| `502` | Error al conectar con Tresguerras |

---

## 📁 Estructura del proyecto

```
mi-api-tresguerras/
├── api.php          → endpoint principal (login + rastreo)
├── config.php       → credenciales y configuración
├── index.php        → entry point para Railway
├── composer.json    → detección de PHP en Railway
└── nixpacks.toml    → configuración de build en Railway
```

---

## 🔄 Flujo interno

```
Cliente
  └─► POST /api.php?action=login
        └─► valida credenciales → genera token propio

Cliente (con token)
  └─► POST /api.php?action=rastreo { "guia": "SCA00344525" }
        └─► valida token propio
        └─► POST Tresguerras ?action=ApiToken  → obtiene token TG
        └─► POST Tresguerras ?action=ApiRastreo → obtiene historial
        └─► filtra estados hasta el objetivo
        └─► devuelve JSON procesado
```