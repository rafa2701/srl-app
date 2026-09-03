# Design Specification: srl_protest Frontend Template & Multi-Admin Voting System

**Date:** 2026-09-02  
**Status:** Approved by User (Pending Spec Review Gate)  
**Author:** SRL Development Team  
**Scope:** `wp-content/plugins/srl-league-system` & `wp-content/themes/srl-theme`

---

## 1. Overview & Objectives

Currently, incident protests (`srl_protest` CPT) are managed exclusively through the WordPress admin classic editor. When multiple stewards or administrators access the same protest simultaneously, WordPress post locking (`_edit_lock`) conflicts occur, creating friction and the risk of overwriting steward decisions and notes. Furthermore, there is no dedicated, clean frontend template for drivers or stewards to inspect incident reports, evidence media, and current case progression.

This specification defines:
1. **Public/Steward Frontend Template (`single-srl_protest.php`)**: A dedicated single view for `srl_protest` featuring SRL's dark motorsport aesthetics, driver faceoff, incident facts, and an embedded HTML5 / responsive evidence video player.
2. **Automated Incident Naming & Permalink Slug**: Protests follow the naming scheme: `[Nombre del Evento] - [Apellido Demandante] vs [Apellido Acusado] #[N]` (e.g. `GP Monza - Pérez vs González #1` with slug `gp-monza-perez-gonzalez-1`).
3. **Concurrent Multi-Admin Voting System**: An AJAX-driven voting engine enabling administrators and stewards to cast their judgment (**Procede** vs **No Procede**) simultaneously without post locks.
4. **Delegated External Steward Voting**: Allows administrators to record votes cast by guest or external stewards who do not have platform user accounts.
5. **AI Chief Steward Integration (`comisario-ai`)**: Automatic provisioning of the user `comisario-ai` (Display Name: `Comisario Virtual AI`) and configurable voting modes:
   - *Disabled*: AI analysis remains advisory only.
   - *Always Active*: AI automatically casts 1 vote based on its consensus analysis.
   - *Tie-Breaker Only*: AI vote is evaluated and only counted if human votes meet quorum and result in an exact tie.
6. **Configurable Quorum & Simple Majority**: Admin setting for minimum quorum (default: 3 votes). Simple majority determines whether the protest proceeds.
7. **Frontend Ruling & Sanction Console**: Allows administrators to finalize official sanctions, adopt AI recommendations with one click, or reopen cases directly on the frontend.
8. **Configurable Visibility**: Toggle between *Public Sanitized* (basic info, evidence, and final ruling for all visitors) and *Admins Only* (restricted to authenticated stewards).

---

## 2. Architecture & Data Model

### 2.1 CPT Configuration (`post-type-protest.php`)
The `srl_protest` Custom Post Type registration is updated:
* `'publicly_queryable' => true`
* `'has_archive' => false`
* `'rewrite' => [ 'slug' => 'reclamo', 'with_front' => false ]`
* Template hierarchy priority: `srl-theme/single-srl_protest.php` &rarr; `srl-league-system/templates/single-srl_protest.php`.

### 2.2 Post Naming & Slug Convention
A dedicated hook on protest creation/update formats the post title and slug:
* When saved via frontend form or admin, extract:
  - Event title (e.g., "GP de Monza")
  - Last name of protesting driver
  - Last name of accused driver
* Suffix `#[N]` calculates how many protests exist for that exact pair in that event, defaulting to `#1`.
* Generates sanitized post slug: `sanitize_title( "{$event_slug}-{$last_p}-{$last_a}-{$n}" )`.

### 2.3 Post Meta Storage
All voting and ruling state is stored atomically in WordPress post meta to bypass classic editor lockouts:

| Meta Key | Data Type | Description |
|---|---|---|
| `_srl_protest_votes` | `array` | Associative array of votes keyed by unique ID (`user_{id}` or `ext_{timestamp_uniqid}` or `ai_steward`). |
| `_srl_steward_action_status` | `string` | Official status: `under_review`, `resolved`, `racing_incident`, `dismissed`. |
| `_srl_steward_notes` | `string` | Official resolution notes and comments. |
| `_srl_final_sanction` | `string` | Applied penalty description (e.g. "+5 seg", "Apercibimiento", "Descalificación"). |
| `_srl_resolved_at` | `string` | MySQL timestamp of final ruling application. |
| `_srl_resolved_by` | `int` | WP User ID of the admin who applied the final ruling. |

#### Vote Array Structure (`_srl_protest_votes`)
```php
[
    'user_12' => [
        'voter_type'   => 'admin',           // 'admin' | 'external' | 'ai'
        'user_id'      => 12,                // WP User ID
        'steward_name' => 'Rafael Admin',    // Display Name
        'decision'     => 'proceeds',        // 'proceeds' | 'dismissed'
        'notes'        => 'Cambio deliberado de línea de frenada.',
        'created_at'   => '2026-09-02 21:55:00',
        'added_by'     => 12,
    ],
    'ext_66d628ab' => [
        'voter_type'   => 'external',
        'user_id'      => 0,
        'steward_name' => 'Carlos Mendoza (Comisario FADA)',
        'decision'     => 'dismissed',
        'notes'        => 'Contacto de carrera sin dolo según Art. 18.',
        'created_at'   => '2026-09-02 22:00:00',
        'added_by'     => 12,
    ],
    'ai_steward' => [
        'voter_type'   => 'ai',
        'user_id'      => 45,                // comisario-ai user ID
        'steward_name' => 'Comisario Virtual AI',
        'decision'     => 'proceeds',
        'notes'        => 'Dictamen consensuado: 80% de culpa imputable al acusado.',
        'created_at'   => '2026-09-02 22:05:00',
        'added_by'     => 0,
    ],
]
```

### 2.4 User Provisioning: `comisario-ai`
A helper function `srl_ensure_ai_steward_user()` guarantees the existence of the AI steward account:
* **Username:** `comisario-ai`
* **Display Name:** `Comisario Virtual AI`
* **Email:** `comisario-ai@simracinglatinoamerica.com` (or admin domain)
* **Role:** `subscriber` (with custom capability `srl_ai_steward`)
* **User Meta:** `_srl_is_ai_steward => true`

---

## 3. Admin Configuration Options

Registered under `srl_commissary_settings` in [`includes/admin-page.php`](file:///e:/Dev/srl-app/wp-content/plugins/srl-league-system/includes/admin-page.php):

1. **`srl_protest_frontend_visibility`**:
   - `public_sanitized` (Default): Anyone can view the incident, driver details, media player, and final sanction. Deliberations, individual votes, and AI persona arguments are hidden for non-admins.
   - `admins_only`: Only users with `edit_posts` capability can view the frontend protest page. Unauthenticated requests see an access restriction screen.
2. **`srl_protest_min_quorum`**:
   - Integer (Default: `3`, minimum: `1`). Minimum total votes required to validate a majority verdict.
3. **`srl_protest_ai_vote_mode`**:
   - `disabled`: AI provides analysis, but never votes.
   - `always` (Default): AI casts 1 vote as `comisario-ai` once n8n completes analysis.
   - `tiebreaker`: AI vote is stored in reserve and only activates if human votes reach quorum with an exact 50/50 tie.

---

## 4. Voting & Quorum Engine

### 4.1 AI Decision Deduction
When the n8n webhook returns a completed verdict to `/wp-json/srl/v1/protest-update`:
```php
$chief = $verdict['chief_steward'] ?? [];
$is_insufficient = !empty( $chief['insufficient_evidence'] );
$fault_accused = intval( $chief['fault_accused'] ?? 0 );
$penalty = strtolower( trim( $chief['penalty'] ?? $chief['recommended_penalty'] ?? '' ) );

$is_dismissed = $is_insufficient 
    || str_contains( $penalty, 'desestimado' ) 
    || str_contains( $penalty, 'sin sanción' ) 
    || str_contains( $penalty, 'incidente de carrera' )
    || $fault_accused <= 50;

$ai_decision = $is_dismissed ? 'dismissed' : 'proceeds';
```
If `srl_protest_ai_vote_mode === 'always'`, the AI vote is immediately recorded in `_srl_protest_votes`. If `tiebreaker`, the decision is cached in `_srl_ai_reserved_vote`.

### 4.2 Quorum & Majority Evaluation
A helper function `srl_calculate_protest_tally( $post_id )` computes:
* Total active votes (Admins + External + Activated AI).
* Count `proceeds` vs `dismissed`.
* Progress towards `srl_protest_min_quorum`.
* Majority outcome:
  - If `total < quorum`: `deliberation` ("En Deliberación: faltan N votos").
  - If `total >= quorum`:
    - `proceeds > dismissed`: `majority_proceeds` ("Mayoría: Procede").
    - `dismissed > proceeds`: `majority_dismissed` ("Mayoría: No Procede / Desestimado").
    - `proceeds == dismissed`:
      - If tiebreaker mode enabled and AI vote unactivated &rarr; activates AI vote and re-evaluates.
      - If still tied &rarr; `tied` ("Empate Técnico: requiere voto de desempate").

### 4.3 AJAX Endpoints
1. `wp_ajax_srl_cast_protest_vote`: Authenticated admin votes `proceeds` or `dismissed` with optional comment.
2. `wp_ajax_srl_add_external_steward_vote`: Authenticated admin records an external steward's name, vote, and comment.
3. `wp_ajax_srl_delete_steward_vote`: Allows removal of an external vote or personal admin vote.
4. `wp_ajax_srl_finalize_protest_ruling`: Applies official resolution (`resolved`, `racing_incident`, `dismissed`), final sanction text, and steward notes.
5. `wp_ajax_srl_reopen_protest`: Reopens a closed case back to `under_review`.

---

## 5. Frontend Template Design (`single-srl_protest.php`)

### 5.1 Access Control Check
```php
$visibility = get_option( 'srl_protest_frontend_visibility', 'public_sanitized' );
$is_admin = current_user_can( 'edit_posts' );

if ( $visibility === 'admins_only' && ! $is_admin ) {
    // Render sleek access denied / login prompt card
    return;
}
```

### 5.2 Layout & Sections
1. **Header Bar**:
   - Championship badge, Event title, incident timestamp.
   - Incident title: `GP de Monza - Pérez vs González #1`.
   - Lap / Timecode badge.
   - Case status badge: `⏳ En Revisión`, `🟢 Procede`, `🔴 Desestimado`, `🏎️ Incidente de Carrera`, `✅ Sanción Aplicada`.
2. **Driver Duel Card**:
   - 🟢 Demandante: Full driver name, vehicle, car number.
   - VS
   - 🔴 Acusado: Full driver name, vehicle, car number.
3. **Incident Narrative**:
   - Clean card displaying the driver's submitted description of the facts.
4. **HTML5 Evidence Video Player**:
   - Detects video type:
     - Direct Video (`.mp4`, `.webm`, `.mov`, Cloudflare R2 presigned/public URLs): `<video controls preload="metadata">` with slow-motion speed toggles (0.25x, 0.5x, 1x) for precise incident review.
     - Embeds: YouTube (`iframe`), Twitch Clips (`iframe`), Streamable.
     - Fallback: Direct download / external link button.
   - Multiple evidence clips rendered via clean tabbed selector.
5. **Public Sanitized Section**:
   - Official Ruling Banner (when resolved): Displays final verdict, applied sanction, and official steward reasoning.
   - Non-admins see only this public verdict.
6. **Steward Deliberation Console (Admins Only)**:
   - **Tally & Quorum Bar**: Visual gradient progress bar (% Procede vs % No Procede) + Quorum counter badge.
   - **Interactive Vote Buttons**:
     - `🟢 Mi Voto: Procede` (active highlight if user voted Procede).
     - `🔴 Mi Voto: No Procede` (active highlight if user voted No Procede).
     - Inline rationale popup/drawer.
     - `➕ Añadir Voto Comisario Externo` modal button.
   - **Votes Breakdown Table**: Real-time list of all votes cast (Voter, Role/Type, Decision badge, Date, Notes, Actions).
   - **AI Virtual Commissary Advisory Accordion**:
     - Consensus card (Fault % bar, recommended penalty, rationale).
     - 3 Persona breakdown cards (Estricto, Permisivo, Equilibrado).
   - **Final Ruling Console**:
     - Resolution status selector (`Sanción Aplicada`, `Incidente de Carrera`, `Desestimado`).
     - Sanction text field with *"📋 Adoptar Sanción de la IA"* shortcut.
     - Resolution notes textarea.
     - *"Guardar Dictamen Oficial"* button.
     - *"🔓 Reabrir Reclamo"* button (active when case is closed).
     - Link to WP-Admin edit screen as backup.

---

## 6. Error Handling & Edge Cases

1. **Simultaneous Submissions**: Storing votes in keyed array post meta avoids race conditions. Using atomic `update_post_meta` ensures no admin is locked out.
2. **Missing Video / Broken URLs**: Safe fallback in media player with an external link button if format is unplayable.
3. **Unauthenticated Access**: When `admins_only` is active, redirect or render clean unauthorized screen with `wp_login_url()`.
4. **AI Processing Pending/Failed**: If AI has not yet responded or failed, human voting proceeds normally without blocking. When AI completes, its vote is registered retroactively.
5. **No Full Roster Voting**: Quorum threshold (e.g. 3) prevents cases from being blocked indefinitely if some admins do not vote.

---

## 7. Verification & Testing Plan

### 7.1 Automated & Unit Checks
- PHP syntax verification on all modified files (`php -l`).
- Test CPT registration arguments and rewrite rules.
- Test `srl_calculate_protest_tally()` with sample vote arrays (quorum met, quorum unmet, ties, tiebreaker activation).

### 7.2 Manual & End-to-End Scenarios
1. **Frontend View as Guest / Driver**: Verify public view displays incident, evidence player, and resolved sanction, while hiding internal votes and steward console.
2. **Frontend View as Admin**: Verify voting buttons, progress bar, external steward modal, and final ruling panel work smoothly via AJAX.
3. **Multi-Admin Test**: Open two distinct admin sessions simultaneously; verify both can vote on the same incident without edit-lock conflicts.
4. **AI Voting Modes**: Test `disabled`, `always`, and `tiebreaker` modes with mock n8n callbacks.
5. **Case Closure & Reopening**: Verify closing a case updates status and locks voting, while clicking "Reabrir" unlocks voting for appeals.
