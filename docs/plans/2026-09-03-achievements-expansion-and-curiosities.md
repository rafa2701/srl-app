# Expansión de Hitos Históricos y Sección de Estadísticas Curiosas - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand the SRL achievements catalog with 5 new championship/performance records and 7 new curious/drought statistics, organized into two distinct sections with an admin visibility toggle.

**Architecture:** Centralize achievement definitions with declarative metadata (`section`, `order`, `unit`, `note`) in `SRL_Achievement_Manager`. Calculate timing gaps, championship margins, and streak intervals using existing database tables without schema migrations. Display in `/hitos/` through two independent styled sections, with global and granular admin controls.

**Tech Stack:** PHP (WordPress Plugin), MySQL (`$wpdb`), CSS3 / HTML5.

## Global Constraints
- Spec: `docs/specs/2026-09-03-achievements-expansion-and-curiosities-section-design.md`
- No database table schema changes (`{$wpdb->prefix}srl_achievements` schema is fully sufficient).
- Maintain 100% backward compatibility for existing custom labels in `srl_achievement_labels` and enabled states in `srl_achievement_settings`.
- Preserve existing styling and responsive CSS grid layout.
- Always update `TODO.md` and `CHANGELOG.md` upon completion as required by workspace rules.

---

### Task 1: Centralized Achievement Definitions in `SRL_Achievement_Manager`

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/achievement-manager.php:340-385`

**Interfaces:**
- Produces: `SRL_Achievement_Manager::get_achievement_definitions(): array`
- Modifies: `SRL_Achievement_Manager::get_achievement_keys(): array`

- [ ] **Step 1: Define `get_achievement_definitions()` with metadata**
Add the metadata mapping method containing `label`, `section`, `order`, `unit`, and optional `note` for all 27 achievements (20 existing + 7 new curiosities + 5 new hall of fame items).

```php
public static function get_achievement_definitions() {
    return [
        // --- HALL OF FAME ---
        'max_win_streak' => [
            'label' => 'Racha de Victorias',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'races',
        ],
        'max_podium_streak' => [
            'label' => 'Racha de Podios',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'races',
        ],
        'point_stalker' => [
            'label' => 'Cazapuntos (Racha de Puntos)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'races',
        ],
        'win_efficiency' => [
            'label' => 'Efectividad de Victorias (%)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'percentage',
            'note' => '* Mínimo 10 carreras',
        ],
        'podium_efficiency' => [
            'label' => 'Efectividad de Podios (%)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'percentage',
            'note' => '* Mínimo 10 carreras',
        ],
        'pole_efficiency' => [
            'label' => 'Efectividad de Poles (%)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'percentage',
            'note' => '* Mínimo 10 carreras',
        ],
        'iron_man' => [
            'label' => 'Iron Man (Carreras sin DNF)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'races',
        ],
        'swiss_watch' => [
            'label' => 'Reloj Suizo (Vueltas en el líder)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'races',
        ],
        'hat_trick_total' => [
            'label' => 'Hat-tricks (Total)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'count',
        ],
        'grand_slam' => [
            'label' => 'Grand Slam (Hattrick + Lideró todo) (2024 en adelante)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'count',
            'note' => '* Resultados desde 2025',
        ],
        'qualifying_ace' => [
            'label' => 'As de la Clasificación (Parrilla Media)',
            'section' => 'hall_of_fame',
            'order' => 'ASC',
            'unit' => 'decimal',
            'note' => '* Mínimo 10 carreras',
        ],
        'sunday_driver' => [
            'label' => 'Especialista en Carrera (Remontada Media)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'decimal',
            'note' => '* Mínimo 10 carreras',
        ],
        'win_from_farthest_back' => [
            'label' => 'Victoria desde más atrás',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'position',
        ],
        'hard_charger' => [
            'label' => 'Remontada histórica (Posiciones)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'positions_gained',
        ],
        'nerves_of_steel' => [
            'label' => 'Nervios de Acero (Photo Finish)',
            'section' => 'hall_of_fame',
            'order' => 'ASC',
            'unit' => 'time',
        ],
        'runaway_victory' => [
            'label' => 'Paseo Triunfal (Mayor gap de victoria)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'time',
        ],
        'one_lap_wonder' => [
            'label' => 'Maravilla a una Vuelta (Pole Gap)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'time',
        ],
        'speed_demon' => [
            'label' => 'Demonio de la Velocidad (Más FL en una temporada)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'fl',
        ],
        'clean_sweep' => [
            'label' => 'Pleno (Ganar todo un campeonato)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'count',
        ],
        'season_dominator' => [
            'label' => 'El Rey de la Temporada (Más victorias en una temporada)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'wins',
        ],
        'saturday_king' => [
            'label' => 'Señor de los Sábados (Más poles en una temporada)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'poles',
        ],
        'nail_biter_championship' => [
            'label' => 'Final de Infarto (Menor margen de puntos en título)',
            'section' => 'hall_of_fame',
            'order' => 'ASC',
            'unit' => 'points',
            'note' => '* Campeonatos finalizados',
        ],
        'dominant_championship' => [
            'label' => 'Tiranía del Título (Mayor ventaja de puntos en título)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'points',
            'note' => '* Campeonatos finalizados',
        ],
        'old_guard' => [
            'label' => 'La Vieja Guardia (Total de carreras)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'races',
        ],
        'giant_killer' => [
            'label' => 'Matagigantes (Derrotar leyendas)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'count',
        ],
        'closer' => [
            'label' => 'The Closer (Adelantamientos al final)',
            'section' => 'hall_of_fame',
            'order' => 'DESC',
            'unit' => 'count',
        ],

        // --- CURIOSITIES ---
        'first_win_wait' => [
            'label' => 'Gloria Tardía (Espera 1ª Victoria)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'races',
            'note' => '* Carreras disputadas hasta su 1ª victoria',
        ],
        'first_pole_wait' => [
            'label' => 'La Larga Espera (Espera 1ª Pole)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'races',
            'note' => '* Participaciones hasta su 1ª pole',
        ],
        'longest_win_drought' => [
            'label' => 'Travesía en el Desierto (Sequía entre Victorias)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'races',
            'note' => '* Mayor intervalo de carreras entre victorias',
        ],
        'longest_pole_drought' => [
            'label' => 'Travesía en el Desierto (Sequía entre Poles)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'races',
            'note' => '* Mayor intervalo de eventos entre poles',
        ],
        'most_races_without_win' => [
            'label' => 'El Eterno Aspirante (Más carreras sin victoria)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'races',
            'note' => '* Mínimo 10 carreras disputadas',
        ],
        'most_races_without_pole' => [
            'label' => 'Cazador Sin Pole (Más carreras sin pole)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'races',
            'note' => '* Mínimo 10 carreras disputadas',
        ],
        'dnf_streak' => [
            'label' => 'Imán de Grúas (Racha de DNF)',
            'section' => 'curiosities',
            'order' => 'DESC',
            'unit' => 'dnf',
        ],
    ];
}
```

- [ ] **Step 2: Update `get_achievement_keys()`**
Refactor `get_achievement_keys()` to map the `label` column of `get_achievement_definitions()` and merge with `get_option('srl_achievement_labels', [])`.

- [ ] **Step 3: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/achievement-manager.php
git commit -m "feat(achievements): define centralized achievement metadata dictionary"
```

---

### Task 2: Calculate Timing Records & Championship Records

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/achievement-manager.php:185-260`

**Interfaces:**
- Consumes: `$wpdb`, `srl_calculate_championship_standings()`
- Produces: Updates to `{$wpdb->prefix}srl_achievements` for keys `runaway_victory`, `season_dominator`, `saturday_king`, `nail_biter_championship`, `dominant_championship`.

- [ ] **Step 1: Update `calculate_timing_records($event_id)`**
Calculate `runaway_victory` for the winner (P1) vs P2 when both finish without DQ/NC and margin > 0.
```php
if ( count( $top_two ) == 2 ) {
    $margin = abs( $top_two[0]->total_time - $top_two[1]->total_time );
    if ( $margin > 0 ) {
        self::update_best_achievement( $top_two[0]->driver_id, 'nerves_of_steel', $margin, $event_id, 'min', $top_two[1]->driver_id );
        self::update_best_achievement( $top_two[0]->driver_id, 'runaway_victory', $margin, $event_id, 'max', $top_two[1]->driver_id );
    }
}
```

- [ ] **Step 2: Update `calculate_championship_achievements($championship_id)`**
Calculate:
1. `season_dominator`: Driver with most wins in this championship.
2. `saturday_king`: Driver with most poles in this championship.
3. If `get_post_meta($championship_id, '_srl_status', true) === 'completed'`:
   Calculate championship standings via `srl_calculate_championship_standings($championship_id)`.
   If at least 2 drivers, determine points gap between P1 and P2:
   `self::update_best_achievement($champion_id, 'nail_biter_championship', $margin, null, 'min', $runner_up_id, $championship_id);`
   `self::update_best_achievement($champion_id, 'dominant_championship', $margin, null, 'max', $runner_up_id, $championship_id);`

- [ ] **Step 3: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/achievement-manager.php
git commit -m "feat(achievements): calculate runaway victory and championship records"
```

---

### Task 3: Calculate Streaks, DNF Streaks, Dry Streaks & Efficiency

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/achievement-manager.php:15-115`

**Interfaces:**
- Updates: `calculate_streaks($driver_id)` and `calculate_efficiency($driver_id)`.
- Produces: Updates to `srl_achievements` for `dnf_streak`, `first_win_wait`, `first_pole_wait`, `longest_win_drought`, `longest_pole_drought`, `most_races_without_win`, `most_races_without_pole`.

- [ ] **Step 1: Implement DNF streak & Dry Streaks in `calculate_streaks($driver_id)`**
In `calculate_streaks`:
- Add tracking for `$current_dnf_streak = 0; $max_dnf_streak = 0;`
  - When `$res->is_dnf`: `$current_dnf_streak++`, update `$max_dnf_streak`.
  - When `!$res->is_dnf`: `$current_dnf_streak = 0`.
- Store race indices of wins: `$win_indices = []`.
- Store race indices of poles: `$pole_indices = []`.
- If `count($win_indices) > 0`:
  - `first_win_wait = $win_indices[0] + 1` (number of races run up to 1st win).
  - If `count($win_indices) >= 2`:
    - Calculate max gap between consecutive wins: `max(i[k] - i[k-1] - 1)`.
    - Save as `longest_win_drought`.
- If `count($pole_indices) > 0`:
  - `first_pole_wait = $pole_indices[0] + 1`.
  - If `count($pole_indices) >= 2`:
    - Calculate max gap between consecutive poles: `max(p[k] - p[k-1] - 1)`.
    - Save as `longest_pole_drought`.
- Save `dnf_streak`, `first_win_wait`, `first_pole_wait`, `longest_win_drought`, `longest_pole_drought`.

- [ ] **Step 2: Implement "El Eterno Aspirante" & "Cazador Sin Pole" in `calculate_efficiency($driver_id)`**
In `calculate_efficiency`:
- If `$stats->total_races >= 10`:
  - If `$stats->wins == 0`: `self::save_achievement($driver_id, 'most_races_without_win', $stats->total_races);`
  - If `$stats->poles == 0`: `self::save_achievement($driver_id, 'most_races_without_pole', $stats->total_races);`

- [ ] **Step 3: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/achievement-manager.php
git commit -m "feat(achievements): calculate DNF streak, dry streaks and zero-win/pole records"
```

---

### Task 4: Update Leaderboard Queries in `SRL_Achievement_Manager`

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/achievement-manager.php:285-320`

**Interfaces:**
- Produces: `SRL_Achievement_Manager::get_achievements_leaderboard($section = null): array`

- [ ] **Step 1: Enhance `get_achievements_leaderboard($section = null)`**
- Filter keys by definition section if `$section` is provided.
- For `nail_biter_championship`, condition is `a.record_value >= 0`. For others, `a.record_value > 0`.
- Sort order comes directly from definition `order` (`ASC` or `DESC`).
- Return definition metadata along with records.

- [ ] **Step 2: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/achievement-manager.php
git commit -m "feat(achievements): support section filtering and zero-margin leaderboards"
```

---

### Task 5: Admin Panel: Global Curiosity Toggle & Grouped Settings

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/admin-page.php:140-185`

**Interfaces:**
- Consumes: `get_option('srl_show_curiosities_section', 1)`
- Produces: Two distinct settings tables in Admin: Salón de la Fama and Estadísticas Curiosas.

- [ ] **Step 1: Add global switch `srl_show_curiosities_section`**
Add the switch above the tables in the achievements tab with clear description.

- [ ] **Step 2: Group achievements table by section**
Iterate over definitions grouped into `hall_of_fame` and `curiosities`. Render two separate styled tables with custom labels and enable/disable toggles.

- [ ] **Step 3: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/admin-page.php
git commit -m "feat(admin): add curiosity section visibility toggle and grouped settings"
```

---

### Task 6: Frontend Template & Formatting in Shortcode (`/hitos/`)

**Files:**
- Modify: `wp-content/plugins/srl-league-system/includes/shortcodes.php:725-827`

**Interfaces:**
- Shortcode: `[srl_achievements_leaderboard]`

- [ ] **Step 1: Add Unit Formatter**
Support formatting for `time`, `points`, `wins`, `poles`, `races`, `dnf`, `percentage`, `positions_gained`.

- [ ] **Step 2: Render Section 1: Hitos Históricos**
Render cards for `hall_of_fame` with icon `🏆`.

- [ ] **Step 3: Render Section 2: Estadísticas Curiosas & Datos Insólitos**
If `get_option('srl_show_curiosities_section', 1)` is enabled and curious records exist:
- Render section header with subtitle.
- Render cards with icon `⏱️`.
- Output card notes dynamically from definition metadata.

- [ ] **Step 4: Commit**
```bash
git add wp-content/plugins/srl-league-system/includes/shortcodes.php
git commit -m "feat(frontend): render split historical and curious achievement leaderboards"
```

---

### Task 7: Verification, Documentation & Spec Archiving

**Files:**
- Modify: `TODO.md`
- Modify: `CHANGELOG.md`
- Move: `docs/specs/2026-09-03-achievements-expansion-and-curiosities-section-design.md` -> `docs/specs/archive/` (after implementation completes).

- [ ] **Step 1: Code review and verification**
Ensure no PHP syntax errors, check leaderboard output and admin settings.
- [ ] **Step 2: Update `TODO.md`**
Mark achievement expansion tasks completed under Current Sprint.
- [ ] **Step 3: Update `CHANGELOG.md`**
Document all additions and UI improvements under `[Unreleased]`.
- [ ] **Step 4: Commit**
```bash
git add TODO.md CHANGELOG.md
git commit -m "docs: document achievements expansion and update changelog"
```
