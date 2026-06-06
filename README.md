# Tresguerras Web Service Integration

Proyecto desarrollado en PHP para el consumo de los servicios web de Tresguerras.

## Descripción

Este proyecto realiza la integración con la API de Tresguerras para:

* Generar un Bearer Token mediante el servicio `ApiToken`.
* Consumir el servicio `ApiRastreo` para consultar información de embarques.
* Procesar la respuesta recibida de la API.
* Truncar el historial de estados de un embarque hasta un estado específico de cancelación.
* Actualizar el nodo de último estado para reflejar la condición requerida por el ejercicio.

## Flujo de funcionamiento

1. Se solicitan credenciales al servicio `ApiToken`.
2. Se obtiene un Bearer Token válido.
3. Se consume el servicio `ApiRastreo` utilizando el token generado.
4. Se procesa el historial de movimientos del embarque.
5. Se trunca el historial cuando se alcanza el estado configurado.
6. Se actualiza el nodo `ultimo_estado`.
7. Se devuelve la respuesta modificada en formato JSON.

## Objetivo

Demostrar el consumo de APIs REST, autenticación mediante Bearer Token, manipulación de respuestas JSON y procesamiento de información logística utilizando PHP.
