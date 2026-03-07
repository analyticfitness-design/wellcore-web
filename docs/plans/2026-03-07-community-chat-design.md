# Community Chat Grupal — Design Doc

**Fecha**: 2026-03-07
**Estado**: Aprobado

## Objetivo

Agregar un chat grupal en tiempo real a la seccion Comunidad de WellCore.
Un solo chat unificado para todos los planes (esencial, metodo, elite, RISE).
Filtro de malas palabras integrado.

## Arquitectura

### UI: Tabs en la seccion Comunidad

La seccion Comunidad tendra dos pestanas: "Feed" (existente) y "Chat" (nuevo).
Ambas dentro del mismo `sec-comunidad` / `communityContainer`.

```
Comunidad WellCore
[Feed] [Chat]  <-- tab switcher

+-------------------------------+
| Avatar  Nombre  ELITE  . 2m  |
| Mensaje del usuario...        |
|                               |
|      Nombre  RISE  . 5m  Av  |  <-- mensajes propios a la derecha
|       Mi mensaje...           |
|                               |
|-------------------------------|
| [Escribe un mensaje...]   >  |
+-------------------------------+
```

### Base de datos

Nueva tabla `community_chat`:

```sql
CREATE TABLE community_chat (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    message     VARCHAR(500) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_created (created_at DESC)
);
```

### API Endpoints

| Endpoint | Metodo | Descripcion |
|----------|--------|-------------|
| `/api/community/chat.php` | GET | Ultimos 50 mensajes, o `?after_id=X` para polling incremental |
| `/api/community/chat.php` | POST | Enviar mensaje (filtro de palabras aplicado) |

**GET response:**
```json
{
  "ok": true,
  "messages": [{
    "id": 1,
    "client_id": 3,
    "author_name": "Carlos R.",
    "author_initial": "C",
    "author_plan": "elite",
    "message": "Buen entrenamiento hoy!",
    "created_at": "2026-03-07T10:30:00"
  }]
}
```

**POST body:** `{ "message": "texto" }`
- Valida longitud 1-500 caracteres
- Aplica filtro de palabras (reemplaza con ***)
- Retorna mensaje creado

### Filtro de Malas Palabras

Archivo: `api/includes/profanity-filter.php`

- Array de ~200+ palabras en espanol (groserias, insultos, lenguaje sexual, discriminatorio)
- `filterMessage($text)` — reemplaza coincidencias con `***`
- `containsProfanity($text)` — retorna boolean (para logging)
- Deteccion de evasion: espacios intercalados ("p u t a"), numeros ("put4"), repeticion ("puuuta")
- Estrategia: reemplazar, no bloquear — el usuario puede comunicarse pero el contenido se filtra

### Frontend — community.js

Extensiones al modulo Community IIFE:

1. **Tab switcher** — dos botones (Feed | Chat) que toggle entre contenedores
2. **Chat container** — div con scroll, lista de mensajes, scroll automatico al fondo
3. **Input bar** — input texto + boton enviar, Enter para enviar
4. **Polling** — `setInterval` cada 5s usando `?after_id=lastId` (solo trae nuevos)
5. **Auto-scroll** — solo si el usuario esta al fondo del chat
6. **Pausa polling** — cuando `document.hidden` o seccion no activa
7. **Mensajes propios** — alineados a la derecha (estilo WhatsApp)
8. **Cargar anteriores** — boton en la parte superior para historial

### Experiencia del Usuario

- Tab "Chat" dentro de Comunidad, junto a "Feed"
- Al abrir: carga ultimos 50 mensajes
- Mensajes nuevos aparecen abajo con animacion sutil (fade-in)
- Cada mensaje: avatar (inicial), nombre, badge plan, timestamp relativo, texto
- Input con contador 0/500
- Mensajes propios a la derecha, ajenos a la izquierda
- Polling se detiene cuando no esta visible (ahorro de recursos)

### Archivos a crear/modificar

| Archivo | Accion |
|---------|--------|
| `api/setup/migrate-chat.php` | CREAR — migracion tabla community_chat |
| `api/includes/profanity-filter.php` | CREAR — filtro de malas palabras |
| `api/community/chat.php` | CREAR — GET/POST mensajes |
| `js/community.js` | MODIFICAR — agregar tabs + chat UI + polling |
| `rise-dashboard.html` | VERIFICAR — que el init de Community funcione con tabs |
| `cliente.html` | VERIFICAR — idem |

### Seguridad

- Autenticacion requerida (token) para GET y POST
- `strip_tags()` en mensajes antes de guardar
- `textContent` para renderizar (no innerHTML con datos de usuario)
- Rate limiting: maximo 1 mensaje por segundo por cliente (prevenir spam)
- Filtro de profanidad server-side (no confiable solo en frontend)
