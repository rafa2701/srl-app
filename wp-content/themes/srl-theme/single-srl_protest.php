<?php
/**
 * Single Template for Incident Protest (Reclamo)
 *
 * Location: wp-content/plugins/srl-league-system/templates/single-srl_protest.php
 *
 * @package SRL_League_System
 */

get_header();

global $wpdb;

$post_id = get_the_ID();

// Visibility Check
$visibility = get_option( 'srl_protest_frontend_visibility', 'public_sanitized' );
$is_admin   = current_user_can( 'edit_posts' );

if ( $visibility === 'admins_only' && ! $is_admin ) {
    ?>
    <div class="srl-container" style="max-width: 800px; margin: 60px auto; padding: 0 20px; text-align: center;">
        <div style="background: #1a1a24; border: 1px solid #333; border-radius: 12px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
            <span style="font-size: 48px;">🔒</span>
            <h1 style="color: #fff; margin: 15px 0 10px; font-size: 24px;">Acceso Restringido al Comisariato</h1>
            <p style="color: #aaa; margin-bottom: 25px;">Este reclamo se encuentra configurado para revisión privada exclusiva de administradores y comisarios de SRL.</p>
            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="srl-btn srl-btn-primary" style="display: inline-block; padding: 12px 28px; font-weight: bold; text-decoration: none; border-radius: 6px; background: #e10600; color: #fff;">
                Iniciar Sesión como Comisario
            </a>
        </div>
    </div>
    <?php
    get_footer();
    return;
}

// Meta details
$event_id      = get_post_meta( $post_id, '_srl_event_id', true );
$custom_event  = get_post_meta( $post_id, '_srl_custom_event_name', true );
$protester_id  = get_post_meta( $post_id, '_srl_protesting_driver_id', true );
$accused_id    = get_post_meta( $post_id, '_srl_accused_driver_id', true );
$lap_timecode  = get_post_meta( $post_id, '_srl_lap_timecode', true );
$description   = get_post_meta( $post_id, '_srl_incident_description', true );
$evidence_raw  = get_post_meta( $post_id, '_srl_evidence_urls', true );
$evidence_urls = is_array( $evidence_raw ) ? $evidence_raw : array_filter( array_map( 'trim', explode( "\n", (string)$evidence_raw ) ) );

$event = $event_id ? get_post( $event_id ) : null;
$event_title = $event ? $event->post_title : ( $custom_event ?: 'Evento de Liga' );
$champ_id = $event ? get_post_meta( $event->ID, '_srl_parent_championship', true ) : null;
$champ = $champ_id ? get_post( $champ_id ) : null;

// Drivers
$protester_name = 'No especificado';
$accused_name   = 'No especificado';
if ( $protester_id ) {
    $p = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $protester_id ) );
    if ( $p ) $protester_name = $p->full_name;
}
if ( $accused_id ) {
    $a = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM {$wpdb->prefix}srl_drivers WHERE id = %d", $accused_id ) );
    if ( $a ) $accused_name = $a->full_name;
}

// Resolution meta
$action_status  = get_post_meta( $post_id, '_srl_steward_action_status', true ) ?: 'under_review';
$steward_notes  = get_post_meta( $post_id, '_srl_steward_notes', true );
$final_sanction = get_post_meta( $post_id, '_srl_final_sanction', true );
$resolved_at    = get_post_meta( $post_id, '_srl_resolved_at', true );

// AI Verdict
$ai_status   = get_post_meta( $post_id, '_srl_ai_status', true ) ?: 'pending';
$verdict_raw = get_post_meta( $post_id, '_srl_ai_verdict', true );
$verdict     = is_string( $verdict_raw ) ? json_decode( $verdict_raw, true ) : $verdict_raw;

// Voting tally
$tally = class_exists( 'SRL_Protest_Voting' ) ? SRL_Protest_Voting::calculate_tally( $post_id ) : [];

// Check current user vote
$current_user_id = get_current_user_id();
$user_vote_key   = 'user_' . $current_user_id;
$my_current_vote = ( $is_admin && isset( $tally['votes'][ $user_vote_key ] ) ) ? $tally['votes'][ $user_vote_key ]['decision'] : '';
$my_current_note = ( $is_admin && isset( $tally['votes'][ $user_vote_key ] ) ) ? ( $tally['votes'][ $user_vote_key ]['notes'] ?? '' ) : '';
?>

<div class="srl-container srl-protest-single-container" style="max-width: 1100px; margin: 40px auto; padding: 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #e1e1e6;">

    <!-- Top Navigation Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="<?php echo esc_url( home_url( '/comisariato/' ) ); ?>" style="color: #aaa; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
            ← Volver al Comisariato
        </a>
        <?php if ( $is_admin ) : ?>
            <div style="display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 13px; color: #00d2d3; background: rgba(0,210,211,0.1); padding: 4px 10px; border-radius: 4px; border: 1px solid rgba(0,210,211,0.3);">
                    🛡️ Modo Comisario Activo
                </span>
                <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" target="_blank" style="color: #bbb; text-decoration: none; font-size: 13px; background: #222; padding: 4px 10px; border-radius: 4px; border: 1px solid #444;">
                    Editar en WP-Admin ↗
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Header Card -->
    <div class="srl-card" style="background: linear-gradient(135deg, #181824 0%, #1e1e2f 100%); border: 1px solid #2d2d3f; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
            <div>
                <?php if ( $champ ) : ?>
                    <span style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #ff4757; font-weight: bold;">
                        🏆 <?php echo esc_html( $champ->post_title ); ?>
                    </span>
                <?php endif; ?>
                <h1 style="color: #fff; margin: 6px 0; font-size: 26px; font-weight: 800; line-height: 1.2;">
                    <?php the_title(); ?>
                </h1>
                <div style="display: flex; gap: 15px; font-size: 14px; color: #888; flex-wrap: wrap;">
                    <span>🏁 <strong>Evento:</strong> <?php echo esc_html( $event_title ); ?></span>
                    <span>⏱️ <strong>Vuelta / Momento:</strong> <code style="color: #ffd32a;"><?php echo esc_html( $lap_timecode ?: 'No especificado' ); ?></code></span>
                    <span>📅 <strong>Fecha:</strong> <?php echo esc_html( get_the_date( 'd/m/Y H:i' ) ); ?></span>
                </div>
            </div>

            <!-- Main Status Badge -->
            <div id="srl-header-status-badge">
                <?php
                switch ( $action_status ) {
                    case 'resolved':
                        echo '<span style="display: inline-block; padding: 6px 14px; background: #2ed573; color: #000; font-weight: 800; border-radius: 20px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">✔ Sanción Aplicada</span>';
                        break;
                    case 'racing_incident':
                        echo '<span style="display: inline-block; padding: 6px 14px; background: #3742fa; color: #fff; font-weight: 800; border-radius: 20px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">🏎️ Incidente de Carrera</span>';
                        break;
                    case 'dismissed':
                        echo '<span style="display: inline-block; padding: 6px 14px; background: #ff4757; color: #fff; font-weight: 800; border-radius: 20px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">✖ Desestimado</span>';
                        break;
                    default:
                        echo '<span style="display: inline-block; padding: 6px 14px; background: #ffa502; color: #000; font-weight: 800; border-radius: 20px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">⏳ En Revisión</span>';
                        break;
                }
                ?>
            </div>
        </div>

        <!-- Drivers Duel Grid -->
        <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 15px; align-items: center; background: rgba(0,0,0,0.25); border-radius: 8px; padding: 15px; border: 1px solid rgba(255,255,255,0.06);">
            <div style="background: rgba(46, 213, 115, 0.1); border-left: 4px solid #2ed573; padding: 10px 15px; border-radius: 4px;">
                <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #2ed573; font-weight: bold;">🟢 Piloto Demandante</span>
                <div style="font-size: 18px; font-weight: 700; color: #fff; margin-top: 2px;">
                    <?php echo esc_html( $protester_name ); ?>
                </div>
            </div>

            <div style="font-weight: 900; color: #555; font-size: 18px; text-align: center;">
                VS
            </div>

            <div style="background: rgba(255, 71, 87, 0.1); border-left: 4px solid #ff4757; padding: 10px 15px; border-radius: 4px;">
                <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #ff4757; font-weight: bold;">🔴 Piloto Acusado</span>
                <div style="font-size: 18px; font-weight: 700; color: #fff; margin-top: 2px;">
                    <?php echo esc_html( $accused_name ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Verdict Public Announcement (if already resolved) -->
    <?php if ( $action_status !== 'under_review' ) : ?>
        <div id="srl-public-verdict-card" style="background: #151520; border-left: 6px solid <?php echo ( $action_status === 'resolved' ) ? '#2ed573' : ( ( $action_status === 'racing_incident' ) ? '#3742fa' : '#ff4757' ); ?>; border-radius: 8px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; color: #fff; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    👨‍⚖️ Resolución Oficial del Comisariato
                </h3>
                <?php if ( $resolved_at ) : ?>
                    <span style="font-size: 12px; color: #888;">Publicado: <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $resolved_at ) ) ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $final_sanction ) ) : ?>
                <div style="background: rgba(255,255,255,0.05); padding: 12px 16px; border-radius: 6px; margin-bottom: 12px;">
                    <strong style="color: #ffd32a; font-size: 13px; text-transform: uppercase;">Sanción Aplicada:</strong>
                    <div style="font-size: 17px; font-weight: bold; color: #fff; margin-top: 4px;">
                        <?php echo esc_html( $final_sanction ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $steward_notes ) ) : ?>
                <div style="color: #ccc; font-size: 14px; line-height: 1.6; white-space: pre-wrap;">
                    <?php echo esc_html( $steward_notes ); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 2 Column Layout: Left (Incident Facts + Media Player) / Right (Admin Console or Public Summary) -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 25px;">

        <!-- Left: Incident Narrative & Evidence Video Player -->
        <div>
            <!-- Narrative Box -->
            <div class="srl-card" style="background: #181824; border: 1px solid #2d2d3f; border-radius: 12px; padding: 22px; margin-bottom: 25px;">
                <h3 style="color: #fff; margin: 0 0 12px; font-size: 17px; border-bottom: 1px solid #2a2a3c; padding-bottom: 8px;">
                    📝 Descripción de los Hechos (Demandante)
                </h3>
                <div style="color: #bbb; line-height: 1.6; font-size: 15px; white-space: pre-wrap; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.04);">
                    <?php echo esc_html( $description ?: 'Sin descripción provista.' ); ?>
                </div>
            </div>

            <!-- Evidence Video Player Card -->
            <div class="srl-card" style="background: #181824; border: 1px solid #2d2d3f; border-radius: 12px; padding: 22px; margin-bottom: 25px;">
                <h3 style="color: #fff; margin: 0 0 15px; font-size: 17px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #2a2a3c; padding-bottom: 8px;">
                    <span>🎥 Video y Evidencias Audiovisuales</span>
                    <span style="font-size: 12px; color: #888;"><?php echo count( $evidence_urls ); ?> clip(s)</span>
                </h3>

                <?php if ( ! empty( $evidence_urls ) ) : ?>
                    <?php foreach ( $evidence_urls as $idx => $url ) : ?>
                        <?php
                        $is_direct_video = (bool) preg_match( '/\.(mp4|webm|mov|mkv)($|\?)/i', $url );
                        $is_youtube      = (bool) preg_match( '/(youtube\.com|youtu\.be)/i', $url );
                        $is_twitch       = (bool) preg_match( '/(twitch\.tv)/i', $url );
                        ?>
                        <div class="srl-evidence-item" style="margin-bottom: 20px; background: #12121a; border: 1px solid #262638; border-radius: 8px; overflow: hidden;">
                            <div style="padding: 10px 14px; background: #1a1a28; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #262638;">
                                <strong style="font-size: 13px; color: #ddd;">Clip #<?php echo ( $idx + 1 ); ?></strong>
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" style="color: #00d2d3; font-size: 12px; text-decoration: none;">
                                    Abrir Enlace Externo ↗
                                </a>
                            </div>

                            <div style="padding: 12px;">
                                <?php if ( $is_direct_video ) : ?>
                                    <div class="srl-video-container" style="position: relative; width: 100%; border-radius: 6px; overflow: hidden; background: #000;">
                                        <video id="srl-video-<?php echo esc_attr( $idx ); ?>" controls preload="metadata" style="width: 100%; max-height: 480px; display: block;">
                                            <source src="<?php echo esc_url( $url ); ?>" type="video/mp4">
                                            Tu navegador no soporta el reproductor HTML5.
                                        </video>
                                    </div>
                                    <!-- Slow Motion Speed Control Toolbar -->
                                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 12px; color: #aaa; background: #161622; padding: 6px 12px; border-radius: 6px;">
                                        <span>⏱️ Velocidad Comisarios:</span>
                                        <button type="button" class="srl-speed-btn" data-video-id="srl-video-<?php echo esc_attr( $idx ); ?>" data-speed="0.25" style="cursor: pointer; background: #222; border: 1px solid #444; color: #ddd; padding: 3px 8px; border-radius: 4px;">0.25x (Super Lenta)</button>
                                        <button type="button" class="srl-speed-btn" data-video-id="srl-video-<?php echo esc_attr( $idx ); ?>" data-speed="0.5" style="cursor: pointer; background: #222; border: 1px solid #444; color: #ddd; padding: 3px 8px; border-radius: 4px;">0.5x (Lenta)</button>
                                        <button type="button" class="srl-speed-btn" data-video-id="srl-video-<?php echo esc_attr( $idx ); ?>" data-speed="1.0" style="cursor: pointer; background: #00d2d3; border: 1px solid #00d2d3; color: #000; font-weight: bold; padding: 3px 8px; border-radius: 4px;">1.0x (Normal)</button>
                                    </div>
                                <?php elseif ( $is_youtube ) : ?>
                                    <?php
                                    preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $yt_matches );
                                    $yt_id = $yt_matches[1] ?? '';
                                    ?>
                                    <?php if ( $yt_id ) : ?>
                                        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 6px;">
                                            <iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $yt_id ); ?>" frameborder="0" allowfullscreen style="position: absolute; top:0; left: 0; width: 100%; height: 100%;"></iframe>
                                        </div>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="srl-btn" style="color: #fff; background: #c4302b; padding: 8px 16px; border-radius: 4px; display: inline-block; text-decoration: none;">Ver Video en YouTube ↗</a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <div style="padding: 10px; text-align: center;">
                                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="srl-btn" style="display: inline-block; padding: 10px 18px; background: #333; color: #fff; text-decoration: none; border-radius: 6px;">
                                            🔗 Ver / Descargar Evidencia Externa (<?php echo esc_html( wp_trim_words( $url, 8 ) ); ?>) ↗
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p style="color: #777; margin: 0; font-style: italic;">No se adjuntaron evidencias multimedia para este incidente.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right / Full Width: Steward Deliberation Console (Admins Only) -->
        <?php if ( $is_admin ) : ?>
            <div id="srl-steward-deliberation-console" class="srl-card" style="background: #181824; border: 2px solid #3742fa; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(55,66,250,0.15);">
                
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #2a2a3c; padding-bottom: 12px; margin-bottom: 20px;">
                    <h2 style="color: #fff; margin: 0; font-size: 20px; display: flex; align-items: center; gap: 8px;">
                        ⚖️ Consola de Deliberación y Votación Colegiada
                    </h2>
                    <span id="srl-tally-badge" style="background: <?php echo esc_attr( $tally['badge_bg'] ?? '#fff3cd' ); ?>; color: <?php echo esc_attr( $tally['badge_color'] ?? '#856404' ); ?>; padding: 5px 12px; border-radius: 15px; font-weight: bold; font-size: 12px;">
                        <?php echo esc_html( $tally['status_label'] ?? 'En Deliberación' ); ?>
                    </span>
                </div>

                <!-- Live Tally & Quorum Progress Bar -->
                <div style="background: #12121a; padding: 18px; border-radius: 8px; border: 1px solid #262638; margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 14px; font-weight: bold;">
                        <span style="color: #2ed573;">🟢 Procede: <span id="srl-tally-count-proceeds"><?php echo intval( $tally['proceeds_count'] ?? 0 ); ?></span> (<span id="srl-tally-pct-proceeds"><?php echo intval( $tally['percent_proceeds'] ?? 0 ); ?></span>%)</span>
                        <span style="color: #aaa; font-size: 12px;">Quórum: <span id="srl-tally-total"><?php echo intval( $tally['total_votes'] ?? 0 ); ?></span>/<?php echo intval( $tally['min_quorum'] ?? 3 ); ?> votos</span>
                        <span style="color: #ff4757;">🔴 No Procede: <span id="srl-tally-count-dismissed"><?php echo intval( $tally['dismissed_count'] ?? 0 ); ?></span> (<span id="srl-tally-pct-dismissed"><?php echo intval( $tally['percent_dismissed'] ?? 0 ); ?></span>%)</span>
                    </div>

                    <!-- Visual Duel Progress Bar -->
                    <div style="width: 100%; height: 18px; background: #262638; border-radius: 10px; overflow: hidden; display: flex;">
                        <div id="srl-bar-proceeds" style="width: <?php echo intval( $tally['percent_proceeds'] ?? 0 ); ?>%; background: #2ed573; height: 100%; transition: width 0.3s ease;"></div>
                        <div id="srl-bar-dismissed" style="width: <?php echo intval( $tally['percent_dismissed'] ?? 0 ); ?>%; background: #ff4757; height: 100%; transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <!-- Admin Voting Form -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid #2a2a3c; border-radius: 8px; padding: 18px; margin-bottom: 25px;">
                    <h4 style="color: #fff; margin: 0 0 12px; font-size: 15px;">Tu Voto como Comisario</h4>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                        <button type="button" id="srl-vote-btn-proceeds" class="srl-decision-choice-btn" data-decision="proceeds" style="flex: 1; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; border: 2px solid #2ed573; background: <?php echo ( $my_current_vote === 'proceeds' ) ? '#2ed573' : 'transparent'; ?>; color: <?php echo ( $my_current_vote === 'proceeds' ) ? '#000' : '#2ed573'; ?>;">
                            🟢 Votar: Procede
                        </button>
                        <button type="button" id="srl-vote-btn-dismissed" class="srl-decision-choice-btn" data-decision="dismissed" style="flex: 1; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; border: 2px solid #ff4757; background: <?php echo ( $my_current_vote === 'dismissed' ) ? '#ff4757' : 'transparent'; ?>; color: <?php echo ( $my_current_vote === 'dismissed' ) ? '#fff' : '#ff4757'; ?>;">
                            🔴 Votar: No Procede
                        </button>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label for="srl_my_vote_notes" style="display: block; font-size: 12px; color: #aaa; margin-bottom: 4px;">Fundamentación / Argumento de tu voto (opcional):</label>
                        <textarea id="srl_my_vote_notes" rows="2" style="width: 100%; background: #12121a; border: 1px solid #333; border-radius: 4px; color: #fff; padding: 8px; font-size: 13px;" placeholder="Ej: Observo que el piloto acusado deja suficiente espacio en el vértice..."><?php echo esc_textarea( $my_current_note ); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" id="srl-submit-my-vote-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" style="background: #3742fa; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; font-weight: bold; cursor: pointer;">
                            Confirmar / Actualizar Mi Voto
                        </button>
                        <button type="button" id="srl-toggle-external-modal-btn" style="background: #2f3542; color: #ddd; border: 1px solid #57606f; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                            ➕ Registrar Voto Comisario Externo
                        </button>
                    </div>
                    <div id="srl-my-vote-feedback" style="margin-top: 10px; font-size: 13px;"></div>
                </div>

                <!-- External Steward Vote Modal / Drawer (Hidden by default) -->
                <div id="srl-external-vote-modal" style="display: none; background: #1f1f2e; border: 1px solid #57606f; border-radius: 8px; padding: 18px; margin-bottom: 25px;">
                    <h4 style="margin: 0 0 12px; color: #00d2d3;">Registrar Voto de Comisario Externo Invitado</h4>
                    <p style="font-size: 12px; color: #aaa; margin-top: 0;">Permite asentar el voto de comisarios que no tienen cuenta activa en la plataforma.</p>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 12px; color: #ccc;">Nombre del Comisario Externo *</label>
                        <input type="text" id="srl_external_steward_name" style="width: 100%; background: #12121a; border: 1px solid #333; color: #fff; padding: 7px; border-radius: 4px;" placeholder="Ej: Carlos Gómez (Comisario FADA)" />
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 12px; color: #ccc;">Decisión del Comisario *</label>
                        <select id="srl_external_decision" style="width: 100%; background: #12121a; border: 1px solid #333; color: #fff; padding: 7px; border-radius: 4px;">
                            <option value="proceeds">🟢 Procede</option>
                            <option value="dismissed">🔴 No Procede</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; color: #ccc;">Justificación / Observaciones</label>
                        <textarea id="srl_external_notes" rows="2" style="width: 100%; background: #12121a; border: 1px solid #333; color: #fff; padding: 7px; border-radius: 4px;"></textarea>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" id="srl-save-external-vote-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" style="background: #00d2d3; color: #000; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                            Guardar Voto Externo
                        </button>
                        <button type="button" onclick="document.getElementById('srl-external-vote-modal').style.display='none';" style="background: transparent; color: #aaa; border: 1px solid #444; padding: 8px 12px; border-radius: 4px; cursor: pointer;">
                            Cancelar
                        </button>
                    </div>
                </div>

                <!-- Votes Table -->
                <h4 style="color: #fff; margin: 0 0 10px; font-size: 15px;">Registro de Votos Emitidos</h4>
                <div style="overflow-x: auto; margin-bottom: 25px;">
                    <table id="srl-votes-table" style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                        <thead>
                            <tr style="background: #14141f; border-bottom: 2px solid #2a2a3c; color: #aaa;">
                                <th style="padding: 10px;">Comisario</th>
                                <th style="padding: 10px;">Rol</th>
                                <th style="padding: 10px;">Voto</th>
                                <th style="padding: 10px;">Fecha</th>
                                <th style="padding: 10px;">Argumento</th>
                                <th style="padding: 10px; text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="srl-votes-tbody">
                            <?php if ( ! empty( $tally['votes'] ) ) : ?>
                                <?php foreach ( $tally['votes'] as $vkey => $vote ) : ?>
                                    <tr id="srl-vote-row-<?php echo esc_attr( $vkey ); ?>" style="border-bottom: 1px solid #262638;">
                                        <td style="padding: 10px; font-weight: bold; color: #fff;">
                                            <?php echo esc_html( $vote['steward_name'] ?? 'Comisario' ); ?>
                                        </td>
                                        <td style="padding: 10px; color: #888;">
                                            <?php
                                            $vtype = $vote['voter_type'] ?? 'admin';
                                            if ( $vtype === 'ai' ) echo '<span style="color: #ff4757; font-weight: bold;">🤖 IA</span>';
                                            elseif ( $vtype === 'external' ) echo '<span style="color: #00d2d3;">🌐 Externo</span>';
                                            else echo '<span style="color: #3742fa;">🛡️ Admin</span>';
                                            ?>
                                        </td>
                                        <td style="padding: 10px;">
                                            <?php if ( ( $vote['decision'] ?? '' ) === 'proceeds' ) : ?>
                                                <span style="color: #2ed573; font-weight: bold;">🟢 Procede</span>
                                            <?php else : ?>
                                                <span style="color: #ff4757; font-weight: bold;">🔴 No Procede</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px; color: #777; font-size: 11px;">
                                            <?php echo esc_html( $vote['created_at'] ? date( 'd/m H:i', strtotime( $vote['created_at'] ) ) : '-' ); ?>
                                        </td>
                                        <td style="padding: 10px; color: #ccc; font-size: 12px; max-width: 300px;">
                                            <?php echo esc_html( $vote['notes'] ?? '-' ); ?>
                                        </td>
                                        <td style="padding: 10px; text-align: right;">
                                            <?php if ( current_user_can( 'manage_options' ) || ( $vote['voter_type'] === 'external' ) || ( $vote['voter_type'] === 'admin' && intval( $vote['user_id'] ) === $current_user_id ) ) : ?>
                                                <button type="button" class="srl-delete-vote-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-vote-key="<?php echo esc_attr( $vkey ); ?>" style="background: transparent; border: none; color: #ff6b81; cursor: pointer; font-size: 13px;" title="Eliminar este voto">🗑️</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr id="srl-no-votes-row"><td colspan="6" style="padding: 15px; text-align: center; color: #666;">No se han emitido votos todavía para este reclamo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- AI Analysis Accordion -->
                <?php if ( ! empty( $verdict ) ) : ?>
                    <?php
                    $strict   = $verdict['persona_strict'] ?? [];
                    $lax      = $verdict['persona_lax'] ?? [];
                    $balanced = $verdict['persona_balanced'] ?? [];
                    $chief    = $verdict['chief_steward'] ?? [];
                    ?>
                    <details style="background: #14141f; border: 1px solid #2d2d42; border-radius: 8px; padding: 12px 16px; margin-bottom: 25px;">
                        <summary style="cursor: pointer; font-weight: bold; color: #ffd32a; font-size: 15px;">
                            🤖 Ver Análisis del Comisariato Virtual AI (Gemini) ▼
                        </summary>
                        <div style="margin-top: 15px;">
                            <!-- Chief Steward Consensus Card -->
                            <div style="background: linear-gradient(135deg, #1e1e2f 0%, #2a2a40 100%); color: #fff; padding: 16px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ff4757;">
                                <h4 style="margin: 0 0 10px; color: #ff4757;">👨‍⚖️ Dictamen Consensuado de la IA</h4>
                                <?php
                                $blame_p = intval( $chief['fault_protesting'] ?? $chief['blame_protesting'] ?? 0 );
                                $blame_a = intval( $chief['fault_accused'] ?? $chief['blame_accused'] ?? 0 );
                                ?>
                                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                                    <span style="color: #2ed573;">🟢 Demandante: <?php echo $blame_p; ?>%</span>
                                    <span style="color: #ff6b81;">🔴 Acusado: <?php echo $blame_a; ?>%</span>
                                </div>
                                <div style="width: 100%; height: 10px; background: #ff4757; border-radius: 5px; overflow: hidden; display: flex; margin-bottom: 10px;">
                                    <div style="width: <?php echo $blame_p; ?>%; background: #2ed573; height: 100%;"></div>
                                </div>
                                <div style="font-size: 13px; margin-bottom: 6px;">
                                    <strong style="color: #ffd32a;">Sanción Recomendada:</strong>
                                    <span id="srl-ai-recommended-penalty-text"><?php echo esc_html( $chief['penalty'] ?? $chief['recommended_penalty'] ?? 'Sin sanción' ); ?></span>
                                </div>
                                <div style="font-size: 12px; color: #bbb; line-height: 1.5;">
                                    <?php echo esc_html( $chief['rationale'] ?? $chief['argument'] ?? '' ); ?>
                                </div>
                            </div>

                            <!-- 3 Personas -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; font-size: 12px;">
                                <div style="background: #1a1a26; padding: 10px; border-radius: 6px; border-top: 3px solid #e74c3c;">
                                    <strong style="color: #e74c3c;">📏 Estricto</strong><br>
                                    <em><?php echo esc_html( $strict['recommended_penalty'] ?? $strict['penalty'] ?? '-' ); ?></em>
                                    <p style="color: #888; margin: 4px 0 0;"><?php echo esc_html( wp_trim_words( $strict['argument'] ?? '', 18 ) ); ?></p>
                                </div>
                                <div style="background: #1a1a26; padding: 10px; border-radius: 6px; border-top: 3px solid #f39c12;">
                                    <strong style="color: #f39c12;">🏁 Permisivo</strong><br>
                                    <em><?php echo esc_html( $lax['recommended_penalty'] ?? $lax['penalty'] ?? '-' ); ?></em>
                                    <p style="color: #888; margin: 4px 0 0;"><?php echo esc_html( wp_trim_words( $lax['argument'] ?? '', 18 ) ); ?></p>
                                </div>
                                <div style="background: #1a1a26; padding: 10px; border-radius: 6px; border-top: 3px solid #3498db;">
                                    <strong style="color: #3498db;">⚖️ Equilibrado</strong><br>
                                    <em><?php echo esc_html( $balanced['recommended_penalty'] ?? $balanced['penalty'] ?? '-' ); ?></em>
                                    <p style="color: #888; margin: 4px 0 0;"><?php echo esc_html( wp_trim_words( $balanced['argument'] ?? '', 18 ) ); ?></p>
                                </div>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>

                <!-- Official Ruling / Sanctions Finalization Panel -->
                <div style="background: #13131f; border: 1px solid #3742fa; border-radius: 8px; padding: 20px;">
                    <h3 style="color: #fff; margin: 0 0 15px; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                        👨‍⚖️ Resolución Oficial y Sanción Final
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #ccc; margin-bottom: 5px;"><strong>Resolución Oficial:</strong></label>
                            <select id="srl_final_action_status" style="width: 100%; background: #1c1c2b; border: 1px solid #444; color: #fff; padding: 8px; border-radius: 4px;">
                                <option value="resolved" <?php selected( $action_status, 'resolved' ); ?>>✅ Sanción / Veredicto Aplicado</option>
                                <option value="racing_incident" <?php selected( $action_status, 'racing_incident' ); ?>>🏎️ Incidente de Carrera</option>
                                <option value="dismissed" <?php selected( $action_status, 'dismissed' ); ?>>❌ Desestimado / Sin Lugar</option>
                                <option value="under_review" <?php selected( $action_status, 'under_review' ); ?>>⏳ En Revisión</option>
                            </select>
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <label style="font-size: 13px; color: #ccc;"><strong>Sanción Aplicada:</strong></label>
                                <?php if ( ! empty( $chief['penalty'] ?? $chief['recommended_penalty'] ?? '' ) ) : ?>
                                    <button type="button" id="srl-copy-ai-penalty-btn" style="background: transparent; border: none; color: #ffd32a; font-size: 11px; cursor: pointer; text-decoration: underline;">
                                        📋 Adoptar de la IA
                                    </button>
                                <?php endif; ?>
                            </div>
                            <input type="text" id="srl_final_sanction_input" value="<?php echo esc_attr( $final_sanction ); ?>" placeholder="Ej: +5 seg. en tiempo final / Apercibimiento" style="width: 100%; background: #1c1c2b; border: 1px solid #444; color: #fff; padding: 8px; border-radius: 4px;" />
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 13px; color: #ccc; margin-bottom: 5px;"><strong>Fundamentación del Dictamen Oficial:</strong></label>
                        <textarea id="srl_final_steward_notes" rows="4" style="width: 100%; background: #1c1c2b; border: 1px solid #444; color: #fff; padding: 8px; border-radius: 4px; font-size: 13px;" placeholder="Detalla la resolución oficial que será visible para los pilotos..."><?php echo esc_textarea( $steward_notes ); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <button type="button" id="srl-save-final-ruling-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" style="background: #2ed573; color: #000; border: none; padding: 10px 22px; font-weight: 800; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            💾 Guardar Dictamen Oficial
                        </button>

                        <?php if ( $action_status !== 'under_review' ) : ?>
                            <button type="button" id="srl-reopen-protest-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>" style="background: #2f3542; color: #ff6b81; border: 1px solid #ff4757; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer;">
                                🔓 Reabrir Reclamo para Nueva Revisión
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="srl-final-ruling-feedback" style="margin-top: 10px; font-size: 13px;"></div>
                </div>

            </div>
        <?php endif; ?>

    </div>

</div>

<?php
get_footer();
