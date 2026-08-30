# Virtual Commissary System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the AI-assisted Virtual Commissary system for sim racing incident protests in the SRL League System plugin, featuring offloaded processing to self-hosted n8n workflows with Google Gemini 1.5 Pro multimodal AI, 3 AI personas (Strict, Lax, Balanced), a Chief Steward consensus, human commissary management, and rulebook settings.

**Architecture:** 
- Drivers submit incident protests via the `[srl_protest_form]` shortcode on WordPress.
- A new Custom Post Type `srl_protest` stores incident metadata, evidence URLs, status, and structured AI verdicts.
- Administrators review protests and click "Send to Virtual Commissary", which dispatches a webhook to a self-hosted n8n instance and marks the status as `processing`.
- The n8n homelab workflow queries the League Rulebook from a custom WP REST API endpoint (`GET /wp-json/srl/v1/rulebook`), runs parallel/sequential Gemini 1.5 Pro persona analyses, evaluates the Chief Steward consensus, and posts the verdict back to WP (`POST /wp-json/srl/v1/protest-update`).
- The admin interface visually displays the 3 persona arguments, blame percentages, recommended sanctions, and provides tools for the human commissary to apply the final ruling.

**Tech Stack:** PHP 8.2, WordPress Plugin API, WP REST API, Vanilla JavaScript, CSS3, n8n Webhooks, Google Gemini 1.5 Pro API.

## Global Constraints

- Must follow the spec in `docs/specs/2026-08-29-virtual-commissary-design.md`.
- Code must reside within `wp-content/plugins/srl-league-system/`.
- Modular structure under `wp-content/plugins/srl-league-system/includes/commissary/`.
- Must support PHP 8+ and adhere to WordPress coding standards (nonces, sanitize/escape, capability checks).
- Secure REST endpoints with API secret key authentication (`X-SRL-API-KEY` or query arg).
- All PHP files must pass linting with `C:\xampp\php\php.exe -n -l`.

---

### Task 1: CPT `srl_protest` and Data Model Registration

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php`
- Modify: `wp-content/plugins/srl-league-system/includes/post-types.php`

**Interfaces:**
- Consumes: WordPress `register_post_type`, `add_action('init', ...)`
- Produces: `srl_register_protest_post_type()` function, Custom Post Type `srl_protest` with custom admin columns and filters.

- [ ] **Step 1: Create `includes/commissary/post-type-protest.php`**

```php
<?php
/**
 * Custom Post Type for Incident Protests (Virtual Commissary)
 * Location: wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

/**
 * Register the srl_protest CPT.
 */
function srl_register_protest_post_type() {
    $labels = [
        'name'                  => _x( 'Protestas', 'Post Type General Name', 'srl-league-system' ),
        'singular_name'         => _x( 'Protesta', 'Post Type Singular Name', 'srl-league-system' ),
        'menu_name'             => __( 'Comisariato (Protestas)', 'srl-league-system' ),
        'name_admin_bar'        => __( 'Protesta', 'srl-league-system' ),
        'archives'              => __( 'Archivo de Protestas', 'srl-league-system' ),
        'attributes'            => __( 'Atributos de Protesta', 'srl-league-system' ),
        'parent_item_colon'     => __( 'Protesta Padre:', 'srl-league-system' ),
        'all_items'             => __( 'Todas las Protestas', 'srl-league-system' ),
        'add_new_item'          => __( 'Nueva Protesta', 'srl-league-system' ),
        'add_new'               => __( 'Añadir Nueva', 'srl-league-system' ),
        'new_item'              => __( 'Nueva Protesta', 'srl-league-system' ),
        'edit_item'             => __( 'Revisar Protesta', 'srl-league-system' ),
        'update_item'           => __( 'Actualizar Protesta', 'srl-league-system' ),
        'view_item'             => __( 'Ver Protesta', 'srl-league-system' ),
        'view_items'            => __( 'Ver Protestas', 'srl-league-system' ),
        'search_items'          => __( 'Buscar Protestas', 'srl-league-system' ),
        'not_found'             => __( 'No se encontraron protestas', 'srl-league-system' ),
        'not_found_in_trash'    => __( 'No hay protestas en la papelera', 'srl-league-system' ),
    ];

    $args = [
        'label'                 => __( 'Protesta', 'srl-league-system' ),
        'description'           => __( 'Reclamos y protestas de incidentes en pista para análisis del Comisariato Virtual', 'srl-league-system' ),
        'labels'                => $labels,
        'supports'              => [ 'title', 'custom-fields' ],
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 23,
        'menu_icon'             => 'dashicons-shield',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
    ];

    register_post_type( 'srl_protest', $args );
}
add_action( 'init', 'srl_register_protest_post_type', 1 );

/**
 * Customize columns in admin protest list.
 */
function srl_set_protest_columns( $columns ) {
    $new_columns = [
        'cb'                => $columns['cb'],
        'title'             => __( 'Incidente / Título', 'srl-league-system' ),
        'protest_event'     => __( 'Evento / Campeonato', 'srl-league-system' ),
        'protest_drivers'   => __( 'Demandante vs Acusado', 'srl-league-system' ),
        'ai_status'         => __( 'Estado IA', 'srl-league-system' ),
        'steward_verdict'   => __( 'Veredicto Comisariato', 'srl-league-system' ),
        'date'              => __( 'Fecha', 'srl-league-system' ),
    ];
    return $new_columns;
}
add_filter( 'manage_srl_protest_posts_columns', 'srl_set_protest_columns' );

/**
 * Render content for custom columns.
 */
function srl_render_protest_columns( $column, $post_id ) {
    global $wpdb;

    switch ( $column ) {
        case 'protest_event':
            $event_id = get_post_meta( $post_id, '_srl_event_id', true );
            if ( $event_id ) {
                $event = get_post( $event_id );
                if ( $event ) {
                    $champ_id = get_post_meta( $event_id, '_srl_parent_championship', true );
                    $champ = $champ_id ? get_post( $champ_id ) : null;
                    echo '<strong>' . esc_html( $event->post_title ) . '</strong>';
                    if ( $champ ) {
                        echo '<br><small style="color: #666;">' . esc_html( $champ->post_title ) . '</small>';
                    }
                } else {
                    echo '<span style="color: #999;">Evento #' . intval( $event_id ) . '</span>';
                }
            } else {
                echo '<span style="color: #999;">-</span>';
            }
            break;

        case 'protest_drivers':
            $protester_id = get_post_meta( $post_id, '_srl_protesting_driver_id', true );
            $accused_id = get_post_meta( $post_id, '_srl_accused_driver_id', true );

            $protester_name = '-';
            $accused_name = '-';

            if ( $protester_id ) {
                $p = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) );
                if ( $p ) $protester_name = $p->full_name;
            }
            if ( $accused_id ) {
                $a = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
                if ( $a ) $accused_name = $a->full_name;
            }

            echo '🟢 <strong>' . esc_html( $protester_name ) . '</strong><br>';
            echo '🔴 <strong>' . esc_html( $accused_name ) . '</strong>';
            break;

        case 'ai_status':
            $status = get_post_meta( $post_id, '_srl_ai_status', true ) ?: 'pending';
            $badge_style = 'display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; text-transform: uppercase;';
            switch ( $status ) {
                case 'completed':
                    echo '<span style="' . $badge_style . ' background: #d4edda; color: #155724;">✔ Completado</span>';
                    break;
                case 'processing':
                    echo '<span style="' . $badge_style . ' background: #cce5ff; color: #004085;">⏳ Procesando...</span>';
                    break;
                case 'failed':
                    echo '<span style="' . $badge_style . ' background: #f8d7da; color: #721c24;">✖ Fallido</span>';
                    break;
                case 'pending':
                default:
                    echo '<span style="' . $badge_style . ' background: #fff3cd; color: #856404;">⏸ Pendiente</span>';
                    break;
            }
            break;

        case 'steward_verdict':
            $human_status = get_post_meta( $post_id, '_srl_steward_action_status', true ) ?: 'under_review';
            $verdict_json = get_post_meta( $post_id, '_srl_ai_verdict', true );
            $verdict = is_string( $verdict_json ) ? json_decode( $verdict_json, true ) : $verdict_json;

            if ( $human_status === 'resolved' ) {
                echo '<span style="color: #28a745; font-weight: bold;">[Dictamen Aplicado]</span><br>';
            } elseif ( $human_status === 'dismissed' ) {
                echo '<span style="color: #dc3545; font-weight: bold;">[Desestimado]</span><br>';
            }

            if ( ! empty( $verdict['chief_steward'] ) ) {
                $cs = $verdict['chief_steward'];
                $blame_p = isset( $cs['fault_protesting'] ) ? $cs['fault_protesting'] : 0;
                $blame_a = isset( $cs['fault_accused'] ) ? $cs['fault_accused'] : 0;
                $penalty = isset( $cs['penalty'] ) ? $cs['penalty'] : ( $cs['recommended_penalty'] ?? 'Sin sanción' );
                echo '<small>Culpa: ' . intval( $blame_p ) . '% vs ' . intval( $blame_a ) . '%</small><br>';
                echo '<small style="color: #333;">' . esc_html( wp_trim_words( $penalty, 6 ) ) . '</small>';
            } else {
                echo '<small style="color: #999;">Sin análisis IA aún</small>';
            }
            break;
    }
}
add_action( 'manage_srl_protest_posts_custom_column', 'srl_render_protest_columns', 10, 2 );

/**
 * Filter protests by AI status in admin.
 */
function srl_filter_protests_by_status() {
    global $typenow;
    if ( $typenow === 'srl_protest' ) {
        $selected = isset( $_GET['srl_ai_status_filter'] ) ? sanitize_text_field( $_GET['srl_ai_status_filter'] ) : '';
        $statuses = [
            ''           => 'Todos los estados IA',
            'pending'    => 'Pendiente',
            'processing' => 'Procesando',
            'completed'  => 'Completado',
            'failed'     => 'Fallido',
        ];

        echo '<select name="srl_ai_status_filter">';
        foreach ( $statuses as $val => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $selected, $val, false ), esc_html( $label ) );
        }
        echo '</select>';
    }
}
add_action( 'restrict_manage_posts', 'srl_filter_protests_by_status' );

/**
 * Modify admin query for AI status filtering.
 */
function srl_filter_protests_query( $query ) {
    global $pagenow;
    $post_type = isset( $query->query_vars['post_type'] ) ? $query->query_vars['post_type'] : '';

    if ( is_admin() && $pagenow === 'edit.php' && $post_type === 'srl_protest' && ! empty( $_GET['srl_ai_status_filter'] ) ) {
        $status = sanitize_text_field( $_GET['srl_ai_status_filter'] );
        $query->query_vars['meta_query'] = [
            [
                'key'     => '_srl_ai_status',
                'value'   => $status,
                'compare' => '=',
            ],
        ];
    }
}
add_filter( 'parse_query', 'srl_filter_protests_query' );
```

- [ ] **Step 2: Run syntax verification**

```powershell
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit CPT implementation**

```bash
git add wp-content/plugins/srl-league-system/includes/commissary/post-type-protest.php
git commit -m "feat(commissary): register srl_protest custom post type with columns and filters"
```

---

### Task 2: REST API Endpoints for Rulebook & n8n Callback

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/commissary/rest-api.php`

**Interfaces:**
- Consumes: WordPress `register_rest_route`, options `srl_rulebook_markdown`, `srl_api_secret_key`
- Produces: 
  - `GET /wp-json/srl/v1/rulebook`
  - `POST /wp-json/srl/v1/protest-update`

- [ ] **Step 1: Create `includes/commissary/rest-api.php`**

```php
<?php
/**
 * REST API Endpoints for Virtual Commissary
 * Location: wp-content/plugins/srl-league-system/includes/commissary/rest-api.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'rest_api_init', 'srl_register_commissary_rest_routes' );

/**
 * Register REST API routes.
 */
function srl_register_commissary_rest_routes() {
    register_rest_route( 'srl/v1', '/rulebook', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'srl_rest_get_rulebook',
        'permission_callback' => 'srl_rest_verify_api_key',
    ] );

    register_rest_route( 'srl/v1', '/protest-update', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'srl_rest_update_protest_verdict',
        'permission_callback' => 'srl_rest_verify_api_key',
    ] );
}

/**
 * Validate API Key for incoming REST requests.
 */
function srl_rest_verify_api_key( WP_REST_Request $request ) {
    $configured_key = get_option( 'srl_api_secret_key', '' );

    // If no key is configured, reject by default for security
    if ( empty( $configured_key ) ) {
        return new WP_Error( 'rest_forbidden', 'API Key no configurada en el servidor WordPress.', [ 'status' => 403 ] );
    }

    $provided_key = $request->get_header( 'X-SRL-API-KEY' );
    if ( empty( $provided_key ) ) {
        $auth_header = $request->get_header( 'authorization' );
        if ( $auth_header && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
            $provided_key = trim( $matches[1] );
        }
    }
    if ( empty( $provided_key ) ) {
        $provided_key = $request->get_param( 'api_key' );
    }

    if ( ! empty( $provided_key ) && hash_equals( $configured_key, $provided_key ) ) {
        return true;
    }

    return new WP_Error( 'rest_forbidden', 'Acceso denegado: API Key inválida o ausente.', [ 'status' => 401 ] );
}

/**
 * Handler: GET /wp-json/srl/v1/rulebook
 */
function srl_rest_get_rulebook( WP_REST_Request $request ) {
    $rulebook = get_option( 'srl_rulebook_markdown', '' );
    $updated_at = get_option( 'srl_rulebook_updated_at', '' );

    return rest_ensure_response( [
        'success'    => true,
        'rulebook'   => $rulebook,
        'updated_at' => $updated_at,
        'length'     => strlen( $rulebook ),
    ] );
}

/**
 * Handler: POST /wp-json/srl/v1/protest-update
 */
function srl_rest_update_protest_verdict( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $protest_id = isset( $params['protest_id'] ) ? intval( $params['protest_id'] ) : 0;
    if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
        return new WP_Error( 'invalid_protest', 'El ID de protesta es inválido o no existe.', [ 'status' => 404 ] );
    }

    $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'completed';
    $error = isset( $params['error'] ) ? sanitize_textarea_field( $params['error'] ) : '';
    $verdict = isset( $params['verdict'] ) ? $params['verdict'] : null;

    if ( $status === 'completed' && ! empty( $verdict ) ) {
        update_post_meta( $protest_id, '_srl_ai_status', 'completed' );
        update_post_meta( $protest_id, '_srl_ai_verdict', is_array( $verdict ) ? wp_json_encode( $verdict ) : $verdict );
        update_post_meta( $protest_id, '_srl_ai_error', '' );
        update_post_meta( $protest_id, '_srl_ai_processed_at', current_time( 'mysql' ) );
    } elseif ( $status === 'failed' ) {
        update_post_meta( $protest_id, '_srl_ai_status', 'failed' );
        update_post_meta( $protest_id, '_srl_ai_error', $error );
    } else {
        update_post_meta( $protest_id, '_srl_ai_status', $status );
    }

    return rest_ensure_response( [
        'success'    => true,
        'message'    => 'Protesta #' . $protest_id . ' actualizada con éxito.',
        'protest_id' => $protest_id,
        'status'     => get_post_meta( $protest_id, '_srl_ai_status', true ),
    ] );
}
```

- [ ] **Step 2: Run syntax verification**

```powershell
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/includes/commissary/rest-api.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit REST API implementation**

```bash
git add wp-content/plugins/srl-league-system/includes/commissary/rest-api.php
git commit -m "feat(commissary): add REST API endpoints for rulebook query and verdict callback"
```

---

### Task 3: Settings & Rulebook Management UI in Admin

**Files:**
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`
- Modify: `wp-content/plugins/srl-league-system/includes/admin-page.php`

**Interfaces:**
- Consumes: WordPress `register_setting`, `get_option`, `update_option`
- Produces: Settings tab "Comisariato Virtual" in "Gestión SRL" with Rulebook Markdown editor, Webhook URL, and Secret API Key.

- [ ] **Step 1: Register settings in `srl-league-system.php`**

In `srl_register_settings()`, add:
```php
register_setting( 'srl_settings_group', 'srl_rulebook_markdown' );
register_setting( 'srl_settings_group', 'srl_rulebook_updated_at' );
register_setting( 'srl_settings_group', 'srl_virtual_commissary_webhook_url' );
register_setting( 'srl_settings_group', 'srl_api_secret_key' );
```

- [ ] **Step 2: Add Comisariato Tab to `includes/admin-page.php`**

Add the navigation tab and content section:
```php
<!-- In tab headers -->
<a href="#commissary" class="nav-tab">Comisariato Virtual AI</a>

<!-- In tab contents -->
<div id="commissary" class="srl-tab-content">
    <div id="srl-commissary-settings-wrapper" style="max-width: 850px;">
        <h2>Configuración del Comisariato Virtual (n8n + Gemini AI)</h2>
        <p class="description">Configura la conexión con tu instancia homelab de n8n y el reglamento deportivo de la liga.</p>
        
        <form method="post" action="options.php">
            <?php
            settings_fields( 'srl_settings_group' );
            $webhook_url = get_option( 'srl_virtual_commissary_webhook_url', '' );
            $api_secret = get_option( 'srl_api_secret_key', '' );
            if ( empty( $api_secret ) ) {
                $api_secret = wp_generate_password( 32, false );
            }
            $rulebook = get_option( 'srl_rulebook_markdown', '' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">URL del Webhook de n8n</th>
                    <td>
                        <input type="url" name="srl_virtual_commissary_webhook_url" value="<?php echo esc_attr( $webhook_url ); ?>" class="large-text" placeholder="https://n8n.tu-servidor.com/webhook/srl-virtual-commissary" />
                        <p class="description">URL del webhook de n8n que recibirá los incidentes a analizar.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">API Secret Key (Autenticación)</th>
                    <td>
                        <input type="text" name="srl_api_secret_key" id="srl_api_secret_key" value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text" style="font-family: monospace;" />
                        <button type="button" class="button" onclick="document.getElementById('srl_api_secret_key').value = Array.from(crypto.getRandomValues(new Uint8Array(24))).map(b=>b.toString(16).padStart(2,'0')).join('');">Regenerar</button>
                        <p class="description">Esta clave debe enviarse en la cabecera <code>X-SRL-API-KEY</code> desde n8n para acceder al reglamento y enviar los veredictos.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Endpoints REST API</th>
                    <td>
                        <p><strong>Obtener Reglamento:</strong> <code><?php echo esc_url( rest_url( 'srl/v1/rulebook' ) ); ?></code></p>
                        <p><strong>Callback de Veredicto:</strong> <code><?php echo esc_url( rest_url( 'srl/v1/protest-update' ) ); ?></code></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Reglamento Deportivo y Código de Conducta (Markdown)</th>
                    <td>
                        <textarea name="srl_rulebook_markdown" rows="18" class="large-text code" placeholder="# Reglamento de Competición SRL&#10;&#10;## 1. Derecho a la trazada&#10;..."><?php echo esc_textarea( $rulebook ); ?></textarea>
                        <p class="description">Escribe o pega aquí el reglamento de la liga en formato Markdown. La IA consultará este texto para determinar la culpabilidad y sanciones.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar Configuración del Comisariato' ); ?>
        </form>
    </div>
</div>
```

- [ ] **Step 3: Run syntax verification**

```powershell
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/includes/admin-page.php
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/srl-league-system.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit settings UI changes**

```bash
git add wp-content/plugins/srl-league-system/includes/admin-page.php wp-content/plugins/srl-league-system/srl-league-system.php
git commit -m "feat(commissary): add virtual commissary settings tab with rulebook and webhook credentials"
```

---

### Task 4: Admin Meta Boxes & AI Dispatch for `srl_protest`

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/commissary/admin-meta-boxes.php`
- Modify: `wp-content/plugins/srl-league-system/assets/js/admin.js`

**Interfaces:**
- Consumes: WordPress `add_meta_box`, `wp_remote_post`, `wp_ajax_srl_dispatch_virtual_commissary`
- Produces:
  - Incident Details Meta Box (with embed/video links)
  - AI Analysis & Persona Verdicts Meta Box (Interactive Persona Cards & Chief Steward consensus)
  - Human Commissary Decision Meta Box
  - AJAX Dispatch Handler sending incident payload to n8n webhook.

- [ ] **Step 1: Create `includes/commissary/admin-meta-boxes.php`**

```php
<?php
/**
 * Admin Meta Boxes for Protest Review and AI Verdicts
 * Location: wp-content/plugins/srl-league-system/includes/commissary/admin-meta-boxes.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_action( 'add_meta_boxes_srl_protest', 'srl_add_protest_meta_boxes' );

function srl_add_protest_meta_boxes() {
    add_meta_box(
        'srl_protest_details_box',
        __( 'Detalles del Incidente', 'srl-league-system' ),
        'srl_render_protest_details_meta_box',
        'srl_protest',
        'normal',
        'high'
    );

    add_meta_box(
        'srl_protest_ai_verdict_box',
        __( 'Análisis del Comisariato Virtual AI (Gemini 1.5 Pro)', 'srl-league-system' ),
        'srl_render_protest_ai_verdict_meta_box',
        'srl_protest',
        'normal',
        'high'
    );

    add_meta_box(
        'srl_protest_human_decision_box',
        __( 'Resolución del Comisariato Humano', 'srl-league-system' ),
        'srl_render_protest_human_decision_meta_box',
        'srl_protest',
        'side',
        'high'
    );
}

/**
 * Meta box: Incident submission details.
 */
function srl_render_protest_details_meta_box( $post ) {
    global $wpdb;

    $event_id = get_post_meta( $post->ID, '_srl_event_id', true );
    $protester_id = get_post_meta( $post->ID, '_srl_protesting_driver_id', true );
    $accused_id = get_post_meta( $post->ID, '_srl_accused_driver_id', true );
    $lap_timecode = get_post_meta( $post->ID, '_srl_lap_timecode', true );
    $description = get_post_meta( $post->ID, '_srl_incident_description', true );
    $evidence_raw = get_post_meta( $post->ID, '_srl_evidence_urls', true );

    $evidence_urls = is_array( $evidence_raw ) ? $evidence_raw : array_filter( array_map( 'trim', explode( "\n", (string)$evidence_raw ) ) );

    $event = $event_id ? get_post( $event_id ) : null;
    $protester = $protester_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) ) : null;
    $accused = $accused_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) ) : null;
    ?>
    <div class="srl-protest-meta-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
        <div style="background: #f9f9f9; padding: 12px; border-left: 4px solid #28a745; border-radius: 4px;">
            <strong>🟢 Piloto Demandante:</strong><br>
            <span style="font-size: 1.1em;"><?php echo esc_html( $protester ? $protester->full_name : 'No asignado' ); ?></span>
        </div>
        <div style="background: #f9f9f9; padding: 12px; border-left: 4px solid #dc3545; border-radius: 4px;">
            <strong>🔴 Piloto Acusado:</strong><br>
            <span style="font-size: 1.1em;"><?php echo esc_html( $accused ? $accused->full_name : 'No asignado' ); ?></span>
        </div>
    </div>

    <table class="form-table" style="margin-top: 0;">
        <tr>
            <th scope="row" style="width: 150px;">Evento / Gran Premio:</th>
            <td><strong><?php echo esc_html( $event ? $event->post_title : 'No seleccionado' ); ?></strong></td>
        </tr>
        <tr>
            <th scope="row">Vuelta / Momento:</th>
            <td><code><?php echo esc_html( $lap_timecode ?: 'No especificado' ); ?></code></td>
        </tr>
        <tr>
            <th scope="row">Descripción de los hechos:</th>
            <td><div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px; white-space: pre-wrap;"><?php echo esc_html( $description ?: 'Sin descripción' ); ?></div></td>
        </tr>
        <tr>
            <th scope="row">Evidencias en Video:</th>
            <td>
                <?php if ( ! empty( $evidence_urls ) ) : ?>
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php foreach ( $evidence_urls as $url ) : ?>
                            <li style="margin-bottom: 8px;">
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-small" style="display: inline-flex; align-items: center; gap: 5px;">
                                    <span class="dashicons dashicons-video-alt3"></span> Ver Video: <?php echo esc_html( wp_trim_words( $url, 5 ) ); ?> ↗
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <span style="color: #999;">No se adjuntaron enlaces de video.</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Meta box: AI Verdict display and Dispatch Trigger.
 */
function srl_render_protest_ai_verdict_meta_box( $post ) {
    $status = get_post_meta( $post->ID, '_srl_ai_status', true ) ?: 'pending';
    $error = get_post_meta( $post->ID, '_srl_ai_error', true );
    $verdict_raw = get_post_meta( $post->ID, '_srl_ai_verdict', true );
    $verdict = is_string( $verdict_raw ) ? json_decode( $verdict_raw, true ) : $verdict_raw;
    $processed_at = get_post_meta( $post->ID, '_srl_ai_processed_at', true );

    wp_nonce_field( 'srl_commissary_dispatch_nonce', 'srl_commissary_nonce' );
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
        <div>
            <strong>Estado IA: </strong>
            <?php
            switch ( $status ) {
                case 'completed': echo '<span class="dashicons dashicons-yes" style="color: green;"></span> <strong style="color: green;">Completado</strong> (' . esc_html( $processed_at ) . ')'; break;
                case 'processing': echo '<span class="spinner is-active" style="float:none;"></span> <strong style="color: #0073aa;">Procesando en n8n...</strong>'; break;
                case 'failed': echo '<span class="dashicons dashicons-no" style="color: red;"></span> <strong style="color: red;">Error en procesamiento</strong>'; break;
                default: echo '<strong style="color: #856404;">Pendiente de envío</strong>'; break;
            }
            ?>
        </div>
        <div>
            <button type="button" id="srl-dispatch-ai-btn" class="button button-primary" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                <span class="dashicons dashicons-update" style="vertical-align: middle; font-size: 16px;"></span>
                <?php echo ( $status === 'completed' ) ? 'Re-analizar con Comisariato Virtual' : 'Enviar a Comisariato Virtual (n8n)'; ?>
            </button>
            <span id="srl-dispatch-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
        </div>
    </div>
    <div id="srl-dispatch-msg"></div>

    <?php if ( $status === 'failed' && ! empty( $error ) ) : ?>
        <div class="notice notice-error inline"><p><strong>Error retornado:</strong> <?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $verdict ) ) : ?>
        <?php
        $strict = $verdict['persona_strict'] ?? [];
        $lax = $verdict['persona_lax'] ?? [];
        $balanced = $verdict['persona_balanced'] ?? [];
        $chief = $verdict['chief_steward'] ?? [];
        ?>
        <!-- Chief Steward Final Consensus Card -->
        <div style="background: linear-gradient(135deg, #1e1e2f 0%, #2a2a40 100%); color: #fff; padding: 18px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #ff4757; font-size: 1.3em;">👨‍⚖️ Dictamen Consensuado: Comisario Jefe</h3>
                <?php if ( ! empty( $chief['insufficient_evidence'] ) ) : ?>
                    <span style="background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">⚠️ Falta de Pruebas / Desestimado</span>
                <?php endif; ?>
            </div>

            <!-- Blame Percentage Bar -->
            <?php
            $blame_p = intval( $chief['fault_protesting'] ?? 0 );
            $blame_a = intval( $chief['fault_accused'] ?? 0 );
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: bold;">
                    <span style="color: #2ed573;">🟢 Demandante: <?php echo $blame_p; ?>%</span>
                    <span style="color: #ff6b81;">🔴 Acusado: <?php echo $blame_a; ?>%</span>
                </div>
                <div style="width: 100%; height: 16px; background: #ff4757; border-radius: 8px; overflow: hidden; display: flex;">
                    <div style="width: <?php echo $blame_p; ?>%; background: #2ed573; height: 100%;"></div>
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <strong style="color: #ffd32a;">Sanción Recomendada:</strong>
                <p style="margin: 4px 0 0; font-size: 1.1em; color: #fff; font-weight: 600;"><?php echo esc_html( $chief['penalty'] ?? $chief['recommended_penalty'] ?? 'Sin sanción' ); ?></p>
            </div>

            <div>
                <strong style="color: #ffd32a;">Fundamentación del Dictamen:</strong>
                <p style="margin: 4px 0 0; line-height: 1.5; color: #dcdde1;"><?php echo esc_html( $chief['rationale'] ?? $chief['argument'] ?? '' ); ?></p>
            </div>
        </div>

        <!-- 3 Persona Cards Grid -->
        <h4 style="margin: 15px 0 10px; font-size: 1.1em;">🎭 Evaluaciones Individuales por Persona</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 15px;">
            <!-- Strict Persona -->
            <div style="background: #fff; border: 1px solid #ddd; border-top: 4px solid #e74c3c; border-radius: 4px; padding: 12px;">
                <h4 style="margin: 0 0 8px; color: #c0392b;">📏 Persona A: Estricto</h4>
                <p style="font-size: 0.85em; color: #7f8c8d; margin-top: 0;">Apego estricto y literal al reglamento.</p>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Reparto de Culpa:</strong><br>
                    🟢 <?php echo intval($strict['fault_protesting'] ?? 0); ?>% / 🔴 <?php echo intval($strict['fault_accused'] ?? 0); ?>%
                </div>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Sanción:</strong> <em><?php echo esc_html($strict['recommended_penalty'] ?? $strict['penalty'] ?? '-'); ?></em>
                </div>
                <div style="font-size: 0.85em; color: #444; max-height: 150px; overflow-y: auto; background: #fdfdfd; padding: 6px; border: 1px solid #f0f0f0;">
                    <?php echo esc_html($strict['argument'] ?? ''); ?>
                </div>
            </div>

            <!-- Lax Persona -->
            <div style="background: #fff; border: 1px solid #ddd; border-top: 4px solid #f39c12; border-radius: 4px; padding: 12px;">
                <h4 style="margin: 0 0 8px; color: #d35400;">🏁 Persona B: Permisivo</h4>
                <p style="font-size: 0.85em; color: #7f8c8d; margin-top: 0;">Filosofía "Turismo Carretera" / "Rubbing is racing".</p>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Reparto de Culpa:</strong><br>
                    🟢 <?php echo intval($lax['fault_protesting'] ?? 0); ?>% / 🔴 <?php echo intval($lax['fault_accused'] ?? 0); ?>%
                </div>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Sanción:</strong> <em><?php echo esc_html($lax['recommended_penalty'] ?? $lax['penalty'] ?? '-'); ?></em>
                </div>
                <div style="font-size: 0.85em; color: #444; max-height: 150px; overflow-y: auto; background: #fdfdfd; padding: 6px; border: 1px solid #f0f0f0;">
                    <?php echo esc_html($lax['argument'] ?? ''); ?>
                </div>
            </div>

            <!-- Balanced Persona -->
            <div style="background: #fff; border: 1px solid #ddd; border-top: 4px solid #3498db; border-radius: 4px; padding: 12px;">
                <h4 style="margin: 0 0 8px; color: #2980b9;">⚖️ Persona C: Equilibrado</h4>
                <p style="font-size: 0.85em; color: #7f8c8d; margin-top: 0;">Métricas de solapamiento y derechos de curva.</p>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Reparto de Culpa:</strong><br>
                    🟢 <?php echo intval($balanced['fault_protesting'] ?? 0); ?>% / 🔴 <?php echo intval($balanced['fault_accused'] ?? 0); ?>%
                </div>
                <div style="font-size: 0.9em; margin-bottom: 8px;">
                    <strong>Sanción:</strong> <em><?php echo esc_html($balanced['recommended_penalty'] ?? $balanced['penalty'] ?? '-'); ?></em>
                </div>
                <div style="font-size: 0.85em; color: #444; max-height: 150px; overflow-y: auto; background: #fdfdfd; padding: 6px; border: 1px solid #f0f0f0;">
                    <?php echo esc_html($balanced['argument'] ?? ''); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * Meta box: Human Commissary Decision & Override.
 */
function srl_render_protest_human_decision_meta_box( $post ) {
    wp_nonce_field( 'srl_save_protest_human_decision', 'srl_human_decision_nonce' );

    $action_status = get_post_meta( $post->ID, '_srl_steward_action_status', true ) ?: 'under_review';
    $notes = get_post_meta( $post->ID, '_srl_steward_notes', true );
    ?>
    <p>
        <label for="srl_steward_action_status"><strong>Resolución Oficial:</strong></label>
        <select name="srl_steward_action_status" id="srl_steward_action_status" style="width: 100%; margin-top: 5px;">
            <option value="under_review" <?php selected( $action_status, 'under_review' ); ?>>⏳ En Revisión</option>
            <option value="resolved" <?php selected( $action_status, 'resolved' ); ?>>✅ Sanción / Veredicto Aplicado</option>
            <option value="racing_incident" <?php selected( $action_status, 'racing_incident' ); ?>>🏎️ Incidente de Carrera</option>
            <option value="dismissed" <?php selected( $action_status, 'dismissed' ); ?>>❌ Desestimado / Sin lugar</option>
        </select>
    </p>
    <p>
        <label for="srl_steward_notes"><strong>Notas del Comisario Humano:</strong></label>
        <textarea name="srl_steward_notes" id="srl_steward_notes" rows="6" style="width: 100%; margin-top: 5px;" placeholder="Agrega aquí notas internas o comentarios para la resolución final..."><?php echo esc_textarea( $notes ); ?></textarea>
    </p>
    <?php
}

/**
 * Save human decision metadata when post is saved.
 */
function srl_save_protest_meta( $post_id ) {
    if ( ! isset( $_POST['srl_human_decision_nonce'] ) || ! wp_verify_nonce( $_POST['srl_human_decision_nonce'], 'srl_save_protest_human_decision' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['srl_steward_action_status'] ) ) {
        update_post_meta( $post_id, '_srl_steward_action_status', sanitize_text_field( $_POST['srl_steward_action_status'] ) );
    }
    if ( isset( $_POST['srl_steward_notes'] ) ) {
        update_post_meta( $post_id, '_srl_steward_notes', sanitize_textarea_field( $_POST['srl_steward_notes'] ) );
    }
}
add_action( 'save_post_srl_protest', 'srl_save_protest_meta' );

/**
 * AJAX Handler: Dispatch protest to n8n Virtual Commissary Webhook.
 */
function srl_handle_dispatch_virtual_commissary() {
    check_ajax_referer( 'srl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'No tienes permisos para realizar esta acción.' ] );
    }

    $protest_id = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
    if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
        wp_send_json_error( [ 'message' => 'ID de protesta no válido.' ] );
    }

    $webhook_url = get_option( 'srl_virtual_commissary_webhook_url', '' );
    if ( empty( $webhook_url ) ) {
        wp_send_json_error( [ 'message' => 'No has configurado la URL del Webhook de n8n en los ajustes de Gestión SRL.' ] );
    }

    global $wpdb;
    $event_id = get_post_meta( $protest_id, '_srl_event_id', true );
    $protester_id = get_post_meta( $protest_id, '_srl_protesting_driver_id', true );
    $accused_id = get_post_meta( $protest_id, '_srl_accused_driver_id', true );
    $lap_timecode = get_post_meta( $protest_id, '_srl_lap_timecode', true );
    $description = get_post_meta( $protest_id, '_srl_incident_description', true );
    $evidence_raw = get_post_meta( $protest_id, '_srl_evidence_urls', true );
    $evidence_urls = is_array( $evidence_raw ) ? $evidence_raw : array_filter( array_map( 'trim', explode( "\n", (string)$evidence_raw ) ) );

    $event = $event_id ? get_post( $event_id ) : null;
    $champ_id = $event ? get_post_meta( $event->ID, '_srl_parent_championship', true ) : null;
    $champ = $champ_id ? get_post( $champ_id ) : null;

    $protester_name = 'Desconocido';
    $accused_name = 'Desconocido';
    if ( $protester_id ) {
        $p = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) );
        if ( $p ) $protester_name = $p->full_name;
    }
    if ( $accused_id ) {
        $a = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
        if ( $a ) $accused_name = $a->full_name;
    }

    $api_secret = get_option( 'srl_api_secret_key', '' );

    $payload = [
        'protest_id'        => $protest_id,
        'event_id'          => $event_id,
        'event_name'        => $event ? $event->post_title : '',
        'championship_name' => $champ ? $champ->post_title : '',
        'protesting_driver' => [
            'id'   => $protester_id,
            'name' => $protester_name,
        ],
        'accused_driver'    => [
            'id'   => $accused_id,
            'name' => $accused_name,
        ],
        'lap_timecode'      => $lap_timecode,
        'description'       => $description,
        'evidence_urls'     => array_values( $evidence_urls ),
        'rulebook_url'      => rest_url( 'srl/v1/rulebook' ),
        'callback_url'      => rest_url( 'srl/v1/protest-update' ),
        'api_key'           => $api_secret,
    ];

    // Update status to processing
    update_post_meta( $protest_id, '_srl_ai_status', 'processing' );
    update_post_meta( $protest_id, '_srl_ai_error', '' );

    // Dispatch HTTP POST to n8n webhook
    $response = wp_remote_post( $webhook_url, [
        'headers'     => [
            'Content-Type'   => 'application/json; charset=utf-8',
            'X-SRL-API-KEY'  => $api_secret,
        ],
        'body'        => wp_json_encode( $payload ),
        'timeout'     => 15,
        'blocking'    => false, // Asynchronous to avoid blocking admin UI
    ] );

    if ( is_wp_error( $response ) ) {
        update_post_meta( $protest_id, '_srl_ai_status', 'failed' );
        update_post_meta( $protest_id, '_srl_ai_error', $response->get_error_message() );
        wp_send_json_error( [ 'message' => 'Error al conectar con n8n: ' . $response->get_error_message() ] );
    }

    wp_send_json_success( [
        'message' => '¡Protesta enviada exitosamente al Comisariato Virtual! El análisis de Gemini IA se procesará en segundo plano.',
        'status'  => 'processing',
    ] );
}
add_action( 'wp_ajax_srl_dispatch_virtual_commissary', 'srl_handle_dispatch_virtual_commissary' );
```

- [ ] **Step 2: Add Admin JS Handler in `assets/js/admin.js`**

In `assets/js/admin.js`, append the event listener for `#srl-dispatch-ai-btn`:
```javascript
// --- Virtual Commissary AI Dispatch ---
$(document).on('click', '#srl-dispatch-ai-btn', function (e) {
    e.preventDefault();
    const btn = $(this);
    const protestId = btn.data('post-id');
    const spinner = $('#srl-dispatch-spinner');
    const msgDiv = $('#srl-dispatch-msg');

    if (!confirm('¿Deseas enviar este incidente para ser analizado por el Comisariato Virtual AI?')) {
        return;
    }

    btn.prop('disabled', true);
    spinner.addClass('is-active');
    msgDiv.html('<p style="color: #666;">Enviando payload a n8n...</p>');

    $.ajax({
        url: srl_ajax_object.ajax_url,
        type: 'POST',
        data: {
            action: 'srl_dispatch_virtual_commissary',
            nonce: srl_ajax_object.nonce,
            protest_id: protestId,
        },
        success: function (response) {
            spinner.removeClass('is-active');
            btn.prop('disabled', false);
            if (response.success) {
                msgDiv.html('<div class="notice notice-success inline" style="margin-top: 10px;"><p>' + response.data.message + '</p></div>');
                setTimeout(function () {
                    location.reload();
                }, 2000);
            } else {
                msgDiv.html('<div class="notice notice-error inline" style="margin-top: 10px;"><p>' + response.data.message + '</p></div>');
            }
        },
        error: function () {
            spinner.removeClass('is-active');
            btn.prop('disabled', false);
            msgDiv.html('<div class="notice notice-error inline" style="margin-top: 10px;"><p>Error de conexión al enviar la petición AJAX.</p></div>');
        }
    });
});
```

- [ ] **Step 3: Run syntax verification**

```powershell
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/includes/commissary/admin-meta-boxes.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit Meta Boxes & Dispatch Logic**

```bash
git add wp-content/plugins/srl-league-system/includes/commissary/admin-meta-boxes.php wp-content/plugins/srl-league-system/assets/js/admin.js
git commit -m "feat(commissary): add admin meta boxes and AJAX webhook dispatcher for srl_protest"
```

---

### Task 5: Frontend Protest Submission Form Shortcode `[srl_protest_form]`

**Files:**
- Create: `wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php`
- Modify: `wp-content/plugins/srl-league-system/assets/css/main.css`
- Modify: `wp-content/plugins/srl-league-system/assets/js/public.js`

**Interfaces:**
- Consumes: Shortcode `[srl_protest_form]`, AJAX `srl_submit_protest_form`
- Produces: Responsive frontend incident protest form with championship/event filtering, video URLs dynamic list, and submission handling.

- [ ] **Step 1: Create `includes/commissary/shortcode-protest-form.php`**

```php
<?php
/**
 * Frontend Shortcode for Incident Protests Form
 * Location: wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

add_shortcode( 'srl_protest_form', 'srl_render_protest_form_shortcode' );

/**
 * Render [srl_protest_form]
 */
function srl_render_protest_form_shortcode( $atts ) {
    global $wpdb;

    $championships = get_posts( [
        'post_type'      => 'srl_championship',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    $drivers = $wpdb->get_results( "SELECT id, full_name FROM {$wpdb->prefix}srl_drivers ORDER BY full_name ASC" );

    ob_start();
    ?>
    <div class="srl-app-container srl-protest-form-wrapper">
        <div class="srl-section-header">
            <h2>🚨 Formulario de Reclamos y Protestas</h2>
            <p style="color: #aaa; margin-top: 5px;">Envía una protesta para revisión del Comisariato. Asegúrate de proporcionar enlaces de video claros.</p>
        </div>

        <form id="srl-public-protest-form" class="srl-form" method="post">
            <?php wp_nonce_field( 'srl-public-nonce', 'protest_nonce' ); ?>

            <div class="srl-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="srl-form-group">
                    <label for="srl_champ_id"><strong>1. Campeonato *</strong></label>
                    <select name="championship_id" id="srl_protest_champ_select" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona el Campeonato --</option>
                        <?php foreach ( $championships as $champ ) : ?>
                            <option value="<?php echo esc_attr( $champ->ID ); ?>"><?php echo esc_html( $champ->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="srl-form-group">
                    <label for="srl_event_id"><strong>2. Gran Premio / Evento *</strong></label>
                    <select name="event_id" id="srl_protest_event_select" required class="srl-input" style="width: 100%;" disabled>
                        <option value="">-- Primero selecciona campeonato --</option>
                    </select>
                </div>
            </div>

            <div class="srl-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="srl-form-group">
                    <label for="protesting_driver_id"><strong>3. Tu Nombre de Piloto (Demandante) *</strong></label>
                    <select name="protesting_driver_id" id="protesting_driver_id" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona tu piloto --</option>
                        <?php foreach ( $drivers as $d ) : ?>
                            <option value="<?php echo esc_attr( $d->id ); ?>"><?php echo esc_html( $d->full_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="srl-form-group">
                    <label for="accused_driver_id"><strong>4. Piloto Involucrado / Acusado *</strong></label>
                    <select name="accused_driver_id" id="accused_driver_id" required class="srl-input" style="width: 100%;">
                        <option value="">-- Selecciona el piloto acusado --</option>
                        <?php foreach ( $drivers as $d ) : ?>
                            <option value="<?php echo esc_attr( $d->id ); ?>"><?php echo esc_html( $d->full_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="srl-form-group" style="margin-bottom: 15px;">
                <label for="lap_timecode"><strong>5. Vuelta / Minuto de la carrera *</strong></label>
                <input type="text" name="lap_timecode" id="lap_timecode" required class="srl-input" style="width: 100%;" placeholder="Ej: Vuelta 7 curva 4 / Minuto 14:32" />
            </div>

            <div class="srl-form-group" style="margin-bottom: 15px;">
                <label for="incident_description"><strong>6. Descripción detallada de lo sucedido *</strong></label>
                <textarea name="incident_description" id="incident_description" rows="5" required class="srl-input" style="width: 100%;" placeholder="Explica la maniobra, trazada, intento de adelantamiento y por qué consideras que hubo infracción..."></textarea>
            </div>

            <div class="srl-form-group" style="margin-bottom: 20px;">
                <label for="evidence_urls"><strong>7. Enlaces de Video de Evidencia (YouTube, Twitch, Discord, Cloudflare R2) *</strong></label>
                <textarea name="evidence_urls" id="evidence_urls" rows="3" required class="srl-input" style="width: 100%; font-family: monospace;" placeholder="https://youtube.com/watch?v=...&#10;https://cdn.discordapp.com/attachments/..."></textarea>
                <small style="color: #888;">Pega uno o más enlaces directos a videos de repetición (onboard, TV cam, vista aérea). Un enlace por línea.</small>
            </div>

            <div class="srl-form-submit">
                <button type="submit" id="srl-submit-protest-btn" class="srl-button" style="padding: 12px 24px; font-size: 1.1em; background: #e60000; color: #fff; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;">
                    Enviar Protesta al Comisariato
                </button>
                <span id="srl-protest-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
            </div>
            <div id="srl-protest-response" style="margin-top: 15px;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler: Public protest submission.
 */
function srl_handle_submit_protest_form() {
    check_ajax_referer( 'srl-public-nonce', 'nonce' );

    $event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
    $protesting_id = isset( $_POST['protesting_driver_id'] ) ? intval( $_POST['protesting_driver_id'] ) : 0;
    $accused_id = isset( $_POST['accused_driver_id'] ) ? intval( $_POST['accused_driver_id'] ) : 0;
    $lap_timecode = isset( $_POST['lap_timecode'] ) ? sanitize_text_field( $_POST['lap_timecode'] ) : '';
    $description = isset( $_POST['incident_description'] ) ? sanitize_textarea_field( $_POST['incident_description'] ) : '';
    $evidence_raw = isset( $_POST['evidence_urls'] ) ? sanitize_textarea_field( $_POST['evidence_urls'] ) : '';

    if ( ! $event_id || ! $protesting_id || ! $accused_id || empty( $description ) || empty( $evidence_raw ) ) {
        wp_send_json_error( [ 'message' => 'Por favor completa todos los campos requeridos.' ] );
    }

    if ( $protesting_id === $accused_id ) {
        wp_send_json_error( [ 'message' => 'El piloto demandante y el acusado no pueden ser la misma persona.' ] );
    }

    global $wpdb;
    $p_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protesting_id ) );
    $a_name = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
    $event_post = get_post( $event_id );
    $event_title = $event_post ? $event_post->post_title : 'Evento #' . $event_id;

    $evidence_urls = array_filter( array_map( 'trim', explode( "\n", $evidence_raw ) ) );

    // Create srl_protest post
    $post_title = sprintf( 'Reclamo: %s vs %s (%s)', $p_name ?: 'Piloto', $a_name ?: 'Piloto', $event_title );
    $post_id = wp_insert_post( [
        'post_title'   => $post_title,
        'post_type'    => 'srl_protest',
        'post_status'  => 'publish',
    ] );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Error al guardar la protesta en el sistema.' ] );
    }

    // Save post meta
    update_post_meta( $post_id, '_srl_event_id', $event_id );
    update_post_meta( $post_id, '_srl_protesting_driver_id', $protesting_id );
    update_post_meta( $post_id, '_srl_accused_driver_id', $accused_id );
    update_post_meta( $post_id, '_srl_lap_timecode', $lap_timecode );
    update_post_meta( $post_id, '_srl_incident_description', $description );
    update_post_meta( $post_id, '_srl_evidence_urls', $evidence_urls );
    update_post_meta( $post_id, '_srl_ai_status', 'pending' );
    update_post_meta( $post_id, '_srl_steward_action_status', 'under_review' );

    wp_send_json_success( [
        'message'    => '¡Protesta registrada con éxito (ID #' . $post_id . ')! Ha sido enviada a los comisarios para su revisión.',
        'protest_id' => $post_id,
    ] );
}
add_action( 'wp_ajax_srl_submit_protest_form', 'srl_handle_submit_protest_form' );
add_action( 'wp_ajax_nopriv_srl_submit_protest_form', 'srl_handle_submit_protest_form' );
```

- [ ] **Step 2: Add Frontend Dynamic Cascading and AJAX in `assets/js/public.js`**

In `assets/js/public.js`, add:
```javascript
// --- Protest Form Cascading Dropdown & Submit ---
$(document).ready(function () {
    $('#srl_protest_champ_select').on('change', function () {
        const champId = $(this).val();
        const eventSelect = $('#srl_protest_event_select');
        
        if (!champId) {
            eventSelect.html('<option value="">-- Primero selecciona campeonato --</option>').prop('disabled', true);
            return;
        }

        eventSelect.html('<option value="">Cargando eventos...</option>').prop('disabled', true);

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'srl_get_events',
                nonce: srl_ajax_object.nonce,
                championship_id: champId,
            },
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    let options = '<option value="">-- Selecciona el Evento --</option>';
                    response.data.forEach(function (ev) {
                        options += '<option value="' + ev.id + '">' + ev.name + '</option>';
                    });
                    eventSelect.html(options).prop('disabled', false);
                } else {
                    eventSelect.html('<option value="">No hay eventos disponibles</option>').prop('disabled', true);
                }
            },
            error: function () {
                eventSelect.html('<option value="">Error al cargar eventos</option>').prop('disabled', true);
            }
        });
    });

    $('#srl-public-protest-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = $('#srl-submit-protest-btn');
        const responseDiv = $('#srl-protest-response');

        submitBtn.prop('disabled', true).text('Enviando protesta...');
        responseDiv.html('');

        $.ajax({
            url: srl_ajax_object.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=srl_submit_protest_form&nonce=' + srl_ajax_object.nonce,
            success: function (res) {
                submitBtn.prop('disabled', false).text('Enviar Protesta al Comisariato');
                if (res.success) {
                    responseDiv.html('<div class="srl-notice srl-notice-success" style="background: #155724; color: #d4edda; padding: 12px; border-radius: 4px; margin-top: 10px;">' + res.data.message + '</div>');
                    form[0].reset();
                    $('#srl_protest_event_select').prop('disabled', true);
                } else {
                    responseDiv.html('<div class="srl-notice srl-notice-error" style="background: #721c24; color: #f8d7da; padding: 12px; border-radius: 4px; margin-top: 10px;">' + res.data.message + '</div>');
                }
            },
            error: function () {
                submitBtn.prop('disabled', false).text('Enviar Protesta al Comisariato');
                responseDiv.html('<div class="srl-notice srl-notice-error" style="background: #721c24; color: #f8d7da; padding: 12px; border-radius: 4px; margin-top: 10px;">Error de comunicación con el servidor.</div>');
            }
        });
    });
});
```

- [ ] **Step 3: Run syntax verification**

```powershell
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit Shortcode implementation**

```bash
git add wp-content/plugins/srl-league-system/includes/commissary/shortcode-protest-form.php wp-content/plugins/srl-league-system/assets/js/public.js
git commit -m "feat(commissary): add [srl_protest_form] shortcode and frontend AJAX handling"
```

---

### Task 6: Plugin Integration & Asset Enqueuing

**Files:**
- Modify: `wp-content/plugins/srl-league-system/srl-league-system.php`

**Interfaces:**
- Consumes: All commissary modules
- Produces: Integrated commissary sub-system on plugin initialization.

- [ ] **Step 1: Require commissary files in `srl-league-system.php`**

Add includes:
```php
// --- Comisariato Virtual (AI Assistant) ---
require_once SRL_PLUGIN_PATH . 'includes/commissary/post-type-protest.php';
require_once SRL_PLUGIN_PATH . 'includes/commissary/rest-api.php';
require_once SRL_PLUGIN_PATH . 'includes/commissary/admin-meta-boxes.php';
require_once SRL_PLUGIN_PATH . 'includes/commissary/shortcode-protest-form.php';
```

And in `srl_admin_enqueue_scripts()`, add `srl_protest` to `$srl_pages`:
```php
$srl_pages = [
    'toplevel_page_srl-league-management',
    'toplevel_page_srl-drivers',
    'srl_championship',
    'srl_event',
    'driver',
    'srl_session',
    'srl_protest'
];
```

And in `srl_public_enqueue_assets()`, add check for `has_shortcode( $post->post_content, 'srl_protest_form' )`.

- [ ] **Step 2: Run syntax check on `srl-league-system.php`**

```powershell
C:\xampp\php\php.exe -n -l wp-content/plugins/srl-league-system/srl-league-system.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit integration changes**

```bash
git add wp-content/plugins/srl-league-system/srl-league-system.php
git commit -m "feat(commissary): integrate commissary modules and update script enqueues"
```

---

### Task 7: n8n Workflow Blueprint & Homelab Documentation

**Files:**
- Create: `docs/n8n/virtual-commissary-workflow.json`
- Create: `docs/n8n/README.md`

**Interfaces:**
- Consumes: n8n workflow spec & Gemini 1.5 Pro multimodal architecture
- Produces: Importable n8n workflow JSON with Gemini 1.5 Pro nodes and setup documentation.

- [ ] **Step 1: Create `docs/n8n/virtual-commissary-workflow.json`**

Provide the complete JSON workflow containing:
1. Webhook trigger (`/webhook/srl-virtual-commissary`).
2. HTTP Request Node to fetch Rulebook from `rulebook_url` using `X-SRL-API-KEY`.
3. Parallel AI Persona Nodes calling Gemini 1.5 Pro:
   - Strict Persona Prompt
   - Lax Persona Prompt
   - Balanced Persona Prompt
4. Consensus (Chief Steward) Node evaluating the 3 arguments and generating final blame % and penalty.
5. HTTP Callback Node sending JSON back to `callback_url` (`/wp-json/srl/v1/protest-update`).

- [ ] **Step 2: Create `docs/n8n/README.md`**

Provide detailed instructions on setting up n8n, configuring the Google Gemini API key credential, importing the workflow JSON, and connecting it to the WordPress site.

- [ ] **Step 3: Commit n8n assets**

```bash
git add docs/n8n/virtual-commissary-workflow.json docs/n8n/README.md
git commit -m "docs(commissary): add n8n workflow blueprint and homelab deployment guide"
```

---

### Task 8: Verification, TODO & Changelog Updates, Spec Archival

**Files:**
- Modify: `TODO.md`
- Modify: `CHANGELOG.md`
- Move: `docs/specs/2026-08-29-virtual-commissary-design.md` -> `docs/specs/archive/2026-08-29-virtual-commissary-design.md`

- [ ] **Step 1: Run comprehensive PHP linting on all plugin files**

```powershell
Get-ChildItem -Path wp-content/plugins/srl-league-system/ -Filter *.php -Recurse | ForEach-Object { C:\xampp\php\php.exe -n -l $_.FullName }
```
Expected: All files output `No syntax errors detected in ...`

- [ ] **Step 2: Update `TODO.md` and `CHANGELOG.md`**

Record the new Virtual Commissary feature in `[Unreleased]` section of `CHANGELOG.md` and check off tasks in `TODO.md`.

- [ ] **Step 3: Move spec to archive directory**

```bash
git mv docs/specs/2026-08-29-virtual-commissary-design.md docs/specs/archive/2026-08-29-virtual-commissary-design.md
```

- [ ] **Step 4: Commit final documentation and archive**

```bash
git add TODO.md CHANGELOG.md docs/specs/archive/2026-08-29-virtual-commissary-design.md
git commit -m "chore(commissary): update TODO, CHANGELOG, and archive completed spec"
```
