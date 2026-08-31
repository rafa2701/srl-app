# Guía de Configuración: Cloudflare R2 para Evidencias de Reclamos

Esta guía explica paso a paso cómo crear y configurar un bucket de **Cloudflare R2** para almacenar videos y capturas de incidentes subidos por los pilotos en el **Comisariato Virtual**.

---

## ¿Por qué Cloudflare R2?

- **Gratuito:** El nivel gratuito incluye **10 GB de almacenamiento mensual**, 1,000,000 operaciones de escritura y **0 costos por transferencia de datos (egress)**.
- **Rendimiento:** CDN global ultrarrápido para que los comisarios e IA reproduzcan las repeticiones al instante.
- **Ahorro de espacio:** Evita saturar el disco de tu servidor web WordPress.

---

## Paso 1: Crear un Bucket en Cloudflare R2

1. Inicia sesión en tu panel de [Cloudflare Dashboard](https://dash.cloudflare.com/).
2. En el menú lateral izquierdo, haz clic en **R2** (o **Almacenamiento de objetos R2**).
3. Haz clic en el botón azul **"Crear bucket"** (Create bucket).
4. Asigna un nombre al bucket (por ejemplo: `srl-incident-videos`).
5. En *Ubicación* (Location), selecciona **Automático** (Automatic).
6. Haz clic en **Crear bucket**.

---

## Paso 2: Habilitar Acceso Público (Dominio o R2.dev)

Para que los videos y capturas puedan ser reproducidos en WordPress y por la IA de n8n, el bucket debe ser accesible públicamente:

### Opción A: Subdominio Público R2.dev (Rápido)
1. Dentro de tu bucket, ve a la pestaña **Configuración** (Settings).
2. Desplázate hacia abajo hasta la sección **Acceso público a R2.dev** (R2.dev public access).
3. Haz clic en **Permitir acceso** (Allow access) y escribe `permitir` para confirmar.
4. Copia la URL pública generada (ejemplo: `https://pub-xxxxxxxxxxxxxxxxxxxxxxxx.r2.dev`).

### Opción B: Dominio Personalizado (Recomendado para producción)
1. En **Configuración** ➔ **Dominios personalizados** (Custom Domains), haz clic en **Conectar dominio**.
2. Escribe tu subdominio deseado (por ejemplo: `media.simracinglatinoamerica.com`).
3. Haz clic en **Continuar** y **Conectar dominio** (Cloudflare creará automáticamente el registro DNS).
4. Tu URL pública será: `https://media.simracinglatinoamerica.com`.

---

## Paso 3: Configurar CORS (Permisos de Subida)

Para permitir que el navegador suba archivos directamente sin bloqueos de seguridad:

1. En la pestaña **Configuración** de tu bucket, baja hasta **Política de CORS** (CORS Policy).
2. Haz clic en **Añadir regla de CORS** y pega el siguiente JSON:

```json
[
  {
    "AllowedOrigins": [
      "https://srlatinoamerica.yzz.me",
      "https://simracinglatinoamerica.com",
      "http://localhost",
      "http://localhost:*"
    ],
    "AllowedMethods": [
      "GET",
      "PUT",
      "POST",
      "HEAD"
    ],
    "AllowedHeaders": [
      "*"
    ],
    "ExposeHeaders": [
      "ETag"
    ],
    "MaxAgeSeconds": 3600
  }
]
```

3. Haz clic en **Guardar**.

---

## Paso 4: Crear Token de API R2 (S3 Credentials)

1. Vuelve a la página principal de **R2** (menú lateral).
2. En la barra lateral derecha, haz clic en **"Administrar tokens de API de R2"** (Manage R2 API Tokens).
3. Haz clic en **Crear token de API** (Create API token).
4. Configura el token:
   - **Nombre del token:** `SRL WordPress Uploader`
   - **Permisos:** Selecciona **Objeto de lectura y escritura** (Object Read & Write).
   - **Aplicar a buckets:** Selecciona tu bucket específico (`srl-incident-videos`) o todos.
   - **TTL:** Deja en blanco o selecciona sin expiración.
5. Haz clic en **Crear token de API**.
6. **IMPORTANTE:** Copia los siguientes valores (solo se muestran una vez):
   - **Identificador de clave de acceso** (`Access Key ID`)
   - **Clave de acceso secreta** (`Secret Access Key`)
   - **Account ID** (se muestra en la URL del endpoint o en el panel de R2).

---

## Paso 5: Guardar Credenciales en WordPress

1. En tu panel de WordPress, navega a **Gestión SRL** ➔ Tab **Comisariato Virtual AI**.
2. En la sección **Almacenamiento Directo de Evidencias (Cloudflare R2)**:
   - Marca la casilla **"Habilitar Cloudflare R2"**.
   - Pega tu **Cloudflare Account ID**.
   - Pega el **R2 Bucket Name** (ej: `srl-incident-videos`).
   - Pega el **S3 Access Key ID**.
   - Pega la **S3 Secret Access Key**.
   - Pega el **Dominio Público / URL Base** (ej: `https://pub-xxxx.r2.dev` o `https://media.simracinglatinoamerica.com`).
3. Haz clic en **Guardar Configuración del Comisariato**.

¡Listo! A partir de este momento, todos los videos o imágenes que los pilotos arrastren o seleccionen en el formulario `[srl_protest_form]` se subirán directamente a tu almacenamiento Cloudflare R2 y sus enlaces se generarán automáticamente.
