# Design Spec: Expansión de Hitos Históricos y Nueva Sección de Estadísticas Curiosas

## Context & Goal
El sistema actual de la liga SRL cuenta con una página de hitos (`/hitos/`) que lista récords históricos bajo una única cuadrícula con el shortcode `[srl_achievements_leaderboard]`. 
El objetivo de este proyecto es ampliar el catálogo de récords con dos categorías:
1. **Nuevos Hitos de Rendimiento & Campeonato (Salón de la Fama)**:
   - Mayor diferencia de victoria en carrera (`runaway_victory` / "Paseo Triunfal").
   - Menor margen de puntos en definición de campeonato (`nail_biter_championship` / "Final de Infarto").
   - Mayor ventaja de puntos en definición de campeonato (`dominant_championship` / "Tiranía del Título").
   - Más victorias en una temporada (`season_dominator` / "El Rey de la Temporada").
   - Más poles en una temporada (`saturday_king` / "Señor de los Sábados").
2. **Nueva Sección Independiente: "Estadísticas Curiosas & Datos Insólitos"**:
   - Estadísticas singulares, de resistencia y anti-récords (no peyorativos, de valor estadístico):
     - Espera hasta la 1ª victoria (`first_win_wait` / "Gloria Tardía").
     - Espera hasta la 1ª pole (`first_pole_wait` / "La Larga Espera").
     - Mayor intervalo de carreras entre victorias (`longest_win_drought` / "Travesía en el Desierto - Victorias").
     - Mayor intervalo de participaciones entre poles (`longest_pole_drought` / "Travesía en el Desierto - Poles").
     - Más carreras disputadas sin haber ganado nunca (`most_races_without_win` / "El Eterno Aspirante", mín. 10 carreras).
     - Más carreras disputadas sin haber logrado pole jamás (`most_races_without_pole` / "Cazador Sin Pole", mín. 10 carreras).
     - Mayor racha consecutiva de abandonos (`dnf_streak` / "Imán de Grúas").
3. **Control de Visibilidad en el Panel de Administración**:
   - Ajuste global para mostrar u ocultar la sección de curiosidades en el frontend a voluntad del administrador.
   - Posibilidad de personalizar etiquetas y habilitar/deshabilitar hitos individuales agrupados por sección.

---

## Detailed Requirements & Metric Definitions

### 1. Salón de la Fama (Hitos Históricos)
- **`runaway_victory` (Paseo Triunfal)**:
  - **Origen**: Sesiones de tipo `Race` (`srl_sessions` + `srl_results`).
  - **Cálculo**: Margen entre P1 y P2 (`P2.total_time - P1.total_time`) donde ambos terminaron sin DQ/NC. Modo `max`.
  - **Relaciones**: Guarda a P2 en `opponent_id` y el `event_id`.
  - **Formato**: Formato de tiempo motorsport (`srl_format_time`).
- **`nail_biter_championship` (Final de Infarto)**:
  - **Origen**: Campeonatos completados (`_srl_status === 'completed'`).
  - **Cálculo**: Diferencia de puntos entre Campeón (P1) y Subcampeón (P2) usando `srl_calculate_championship_standings()`. Modo `min` (permite margen 0 por desempate de victorias).
  - **Relaciones**: P1 en `driver_id`, P2 en `opponent_id`, ID de campeonato en `championship_id`.
  - **Formato**: `X pts` (ej. `1 pt` o `0 pts`).
- **`dominant_championship` (Tiranía del Título)**:
  - **Origen**: Campeonatos completados (`_srl_status === 'completed'`).
  - **Cálculo**: Diferencia de puntos entre Campeón (P1) y Subcampeón (P2). Modo `max`.
  - **Relaciones**: P1 en `driver_id`, P2 en `opponent_id`, ID de campeonato en `championship_id`.
  - **Formato**: `X pts`.
- **`season_dominator` (El Rey de la Temporada)**:
  - **Origen**: Todos los campeonatos (`srl_championship`).
  - **Cálculo**: Mayor número de carreras ganadas (`position = 1` sin DQ/NC) por un piloto en una sola temporada. Modo `max`.
  - **Relaciones**: ID de campeonato en `championship_id`.
  - **Formato**: `X victorias`.
- **`saturday_king` (Señor de los Sábados)**:
  - **Origen**: Todos los campeonatos (`srl_championship`).
  - **Cálculo**: Mayor número de pole positions (`has_pole = 1`) obtenidas por un piloto en una sola temporada. Modo `max`.
  - **Relaciones**: ID de campeonato en `championship_id`.
  - **Formato**: `X poles`.

### 2. Estadísticas Curiosas & Datos Insólitos
- **`first_win_wait` (Gloria Tardía)**:
  - **Criterio**: Pilotos con `>= 1` victoria histórica en carreras.
  - **Cálculo**: Total de carreras disputadas desde su debut hasta su primer triunfo inclusive. Modo `max`.
  - **Formato**: `X carreras`.
- **`first_pole_wait` (La Larga Espera)**:
  - **Criterio**: Pilotos con `>= 1` pole histórica.
  - **Cálculo**: Total de participaciones/carreras previas hasta conseguir su primera pole position. Modo `max`.
  - **Formato**: `X carreras`.
- **`longest_win_drought` (Travesía en el Desierto - Victorias)**:
  - **Criterio**: Pilotos con `>= 2` victorias históricas.
  - **Cálculo**: Mayor intervalo de carreras disputadas entre una victoria y la siguiente. Modo `max`.
  - **Formato**: `X carreras`.
- **`longest_pole_drought` (Travesía en el Desierto - Poles)**:
  - **Criterio**: Pilotos con `>= 2` poles históricas.
  - **Cálculo**: Mayor intervalo de carreras disputadas entre una pole y la siguiente. Modo `max`.
  - **Formato**: `X carreras`.
- **`most_races_without_win` (El Eterno Aspirante)**:
  - **Criterio**: Pilotos con exactamente `0` victorias históricas y **mínimo 10 carreras disputadas**.
  - **Cálculo**: Total acumulado de carreras disputadas. Modo `max`.
  - **Formato**: `X carreras (0 victorias)`.
  - **Nota UI**: `* Mínimo 10 carreras disputadas`.
- **`most_races_without_pole` (Cazador Sin Pole)**:
  - **Criterio**: Pilotos con exactamente `0` poles históricas y **mínimo 10 carreras disputadas**.
  - **Cálculo**: Total acumulado de carreras disputadas. Modo `max`.
  - **Formato**: `X carreras (0 poles)`.
  - **Nota UI**: `* Mínimo 10 carreras disputadas`.
- **`dnf_streak` (Imán de Grúas)**:
  - **Cálculo**: Mayor número de carreras consecutivas finalizadas en abandono (`is_dnf = 1`). Modo `max`.
  - **Formato**: `X abandonos seguidos`.

---

## Technical Architecture & Implementation Plan

### 1. Database Schema
- **No changes needed**: La tabla existente `{$wpdb->prefix}srl_achievements` dispone de:
  - `id`, `achievement_key`, `driver_id`, `record_value`, `event_id`, `championship_id`, `opponent_id`, `updated_at`.
  Todos los nuevos hitos se guardan limpiamente en esta estructura.
- Para `nail_biter_championship`, se actualizará la consulta del leaderboard para admitir `record_value >= 0` cuando corresponda (evitando filtrar diferencias de 0 puntos).

### 2. Backend: `SRL_Achievement_Manager` (`includes/achievement-manager.php`)
- **Metadata Centralizada**:
  Crear el método `get_achievement_definitions()` que mapea cada hito a su configuración:
  - `label`: Etiqueta por defecto en español.
  - `section`: `'hall_of_fame'` o `'curiosities'`.
  - `order`: `'DESC'` o `'ASC'`.
  - `unit`: Tipo de valor (`time`, `points`, `wins`, `poles`, `races`, `dnf`, `percent`).
  - `note`: Nota explicativa para el pie de la tarjeta (opcional).
- **`get_achievement_keys()`**: Mantiene retrocompatibilidad devolviendo un mapa `[key => label]` fusionando las opciones personalizadas de `srl_achievement_labels`.
- **Métodos de Cálculo actualizados**:
  - `calculate_streaks($driver_id)`:
    - Agrega cálculo de `dnf_streak`, `first_win_wait`, `first_pole_wait`, `longest_win_drought` y `longest_pole_drought`.
  - `calculate_efficiency($driver_id)`:
    - Agrega cálculo de `most_races_without_win` y `most_races_without_pole` condicionada a `total_races >= 10`.
  - `calculate_timing_records($event_id)`:
    - Agrega cálculo de `runaway_victory` (`max` gap entre P1 y P2).
  - `calculate_championship_achievements($championship_id)`:
    - Agrega `season_dominator` y `saturday_king`.
    - Si el campeonato tiene `_srl_status === 'completed'`, ejecuta `srl_calculate_championship_standings` y calcula `nail_biter_championship` (`min`) y `dominant_championship` (`max`).
  - `get_achievements_leaderboard($section = null)`:
    - Acepta parámetro opcional `$section` para retornar solo los hitos de esa sección o agrupados.
    - Respeta flags de activación de `srl_achievement_settings`.

### 3. Panel de Configuración (`includes/admin-page.php`)
- **Ajuste Global de Visibilidad**:
  - Nuevo setting `srl_show_curiosities_section` registrado en `srl_settings_group` (default: 1 / Activado).
  - Toggle switch en la cabecera de la pestaña "Hitos (Logros)" para mostrar/ocultar la sección en la web pública.
- **Listas Divididas**:
  - Renderiza dos tablas separadas:
    1. *Hitos Históricos (Salón de la Fama)*
    2. *Estadísticas Curiosas & Datos Insólitos*
  - Cada fila mantiene su clave, campo de texto para renombrar etiqueta y switch de estado (Habilitado / Deshabilitado).

### 4. Frontend: Shortcode `[srl_achievements_leaderboard]` (`includes/shortcodes.php`)
- Renderiza el Salón de la Fama:
  - Título `<h2>Hitos Históricos</h2>`
  - Tarjetas con ícono `🏆`
  - Formateo de valores según la metadata de la clave.
- Si `get_option('srl_show_curiosities_section', 1)` está activado:
  - Renderiza una sección separada:
    - Título `<h2>Estadísticas Curiosas & Datos Insólitos</h2>`
    - Tarjetas con ícono temático (`⏱️`)
    - Formateo explícito de unidades (`X carreras`, `X abandonos seguidos`).
    - Notas explicativas correspondientes al pie de cada tarjeta.

---

## Verification & Testing Plan
1. **Verificación de Sincronización**:
   - Ejecutar `SRL_Achievement_Manager::sync_all_achievements()` y comprobar en base de datos la correcta población de las nuevas claves (`runaway_victory`, `nail_biter_championship`, `dominant_championship`, `season_dominator`, `saturday_king`, `first_win_wait`, `first_pole_wait`, `longest_win_drought`, `longest_pole_drought`, `most_races_without_win`, `most_races_without_pole`, `dnf_streak`).
2. **Verificación de Panel Admin**:
   - Probar el guardado de etiquetas personalizadas y la activación/desactivación individual.
   - Probar el switch global `srl_show_curiosities_section` para confirmar que apaga y enciende la segunda sección en el frontend.
3. **Verificación Visual en Frontend**:
   - Cargar la página `/hitos/` y revisar el renderizado de ambas cuadrículas, colores de links, nombres de rivales y enlaces a eventos/campeonatos.
