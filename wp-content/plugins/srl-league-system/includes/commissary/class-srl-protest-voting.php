<?php
/**
 * Class SRL_Protest_Voting
 *
 * Manages atomic multi-admin voting, external steward delegation,
 * AI steward participation, quorum and tiebreaker evaluation for incident protests.
 *
 * Location: wp-content/plugins/srl-league-system/includes/commissary/class-srl-protest-voting.php
 *
 * @package SRL_League_System
 */

if ( ! defined( 'WPINC' ) ) die;

class SRL_Protest_Voting {

    const META_VOTES        = '_srl_protest_votes';
    const META_AI_RESERVED  = '_srl_ai_reserved_vote';
    const META_STATUS       = '_srl_steward_action_status';
    const META_NOTES        = '_srl_steward_notes';
    const META_SANCTION     = '_srl_final_sanction';
    const META_RESOLVED_AT  = '_srl_resolved_at';
    const META_RESOLVED_BY  = '_srl_resolved_by';

    /**
     * Initialize hooks.
     */
    public static function init() {
        // AJAX Endpoints
        add_action( 'wp_ajax_srl_cast_protest_vote', [ __CLASS__, 'ajax_cast_vote' ] );
        add_action( 'wp_ajax_srl_add_external_steward_vote', [ __CLASS__, 'ajax_add_external_vote' ] );
        add_action( 'wp_ajax_srl_delete_steward_vote', [ __CLASS__, 'ajax_delete_vote' ] );
        add_action( 'wp_ajax_srl_finalize_protest_ruling', [ __CLASS__, 'ajax_finalize_ruling' ] );
        add_action( 'wp_ajax_srl_reopen_protest', [ __CLASS__, 'ajax_reopen_protest' ] );
    }

    /**
     * Get all votes for a protest.
     *
     * @param int $protest_id
     * @return array
     */
    public static function get_votes( $protest_id ) {
        $votes = get_post_meta( $protest_id, self::META_VOTES, true );
        return is_array( $votes ) ? $votes : [];
    }

    /**
     * Save votes array atomically.
     *
     * @param int $protest_id
     * @param array $votes
     * @return bool
     */
    public static function save_votes( $protest_id, array $votes ) {
        return (bool) update_post_meta( $protest_id, self::META_VOTES, $votes );
    }

    /**
     * Cast or update an authenticated administrator / steward vote.
     *
     * @param int $protest_id
     * @param int $user_id
     * @param string $decision 'proceeds' | 'dismissed'
     * @param string $notes
     * @return array
     */
    public static function cast_admin_vote( $protest_id, $user_id, $decision, $notes = '' ) {
        $decision = ( $decision === 'dismissed' ) ? 'dismissed' : 'proceeds';
        $user = get_userdata( $user_id );
        $name = $user ? $user->display_name : 'Comisario #' . $user_id;

        $votes = self::get_votes( $protest_id );
        $vote_key = 'user_' . $user_id;

        $votes[ $vote_key ] = [
            'voter_type'   => 'admin',
            'user_id'      => $user_id,
            'steward_name' => $name,
            'decision'     => $decision,
            'notes'        => sanitize_textarea_field( $notes ),
            'created_at'   => current_time( 'mysql' ),
            'added_by'     => $user_id,
        ];

        self::save_votes( $protest_id, $votes );
        return self::calculate_tally( $protest_id );
    }

    /**
     * Add an external guest steward vote.
     *
     * @param int $protest_id
     * @param string $steward_name
     * @param string $decision 'proceeds' | 'dismissed'
     * @param string $notes
     * @param int $added_by
     * @return array
     */
    public static function add_external_vote( $protest_id, $steward_name, $decision, $notes = '', $added_by = 0 ) {
        $decision = ( $decision === 'dismissed' ) ? 'dismissed' : 'proceeds';
        $steward_name = sanitize_text_field( $steward_name );
        if ( empty( $steward_name ) ) {
            $steward_name = 'Comisario Externo';
        }

        $votes = self::get_votes( $protest_id );
        $unique_key = 'ext_' . substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 8 );

        $votes[ $unique_key ] = [
            'voter_type'   => 'external',
            'user_id'      => 0,
            'steward_name' => $steward_name,
            'decision'     => $decision,
            'notes'        => sanitize_textarea_field( $notes ),
            'created_at'   => current_time( 'mysql' ),
            'added_by'     => $added_by,
        ];

        self::save_votes( $protest_id, $votes );
        return self::calculate_tally( $protest_id );
    }

    /**
     * Delete a vote by its key.
     *
     * @param int $protest_id
     * @param string $vote_key
     * @param int $requesting_user_id
     * @return bool|array Tally array or false on failure
     */
    public static function delete_vote( $protest_id, $vote_key, $requesting_user_id ) {
        $votes = self::get_votes( $protest_id );
        if ( ! isset( $votes[ $vote_key ] ) ) {
            return false;
        }

        $vote = $votes[ $vote_key ];
        // Permission check: admin can delete their own vote, or any external vote, or manage_options can delete any
        $can_delete = current_user_can( 'manage_options' )
            || ( $vote['voter_type'] === 'admin' && intval( $vote['user_id'] ) === $requesting_user_id )
            || ( $vote['voter_type'] === 'external' );

        if ( ! $can_delete ) {
            return false;
        }

        unset( $votes[ $vote_key ] );
        self::save_votes( $protest_id, $votes );
        return self::calculate_tally( $protest_id );
    }

    /**
     * Evaluate AI Chief Steward verdict and cast or reserve vote according to admin settings.
     *
     * @param int $protest_id
     * @param array $verdict
     */
    public static function evaluate_ai_verdict_vote( $protest_id, $verdict ) {
        $mode = get_option( 'srl_protest_ai_vote_mode', 'always' );
        if ( $mode === 'disabled' || empty( $verdict ) ) {
            return;
        }

        $chief = $verdict['chief_steward'] ?? [];
        $is_insufficient = ! empty( $chief['insufficient_evidence'] );
        $fault_accused = intval( $chief['fault_accused'] ?? $chief['blame_accused'] ?? 0 );
        $penalty = strtolower( trim( (string)( $chief['penalty'] ?? $chief['recommended_penalty'] ?? '' ) ) );

        $is_dismissed = $is_insufficient 
            || str_contains( $penalty, 'desestimado' ) 
            || str_contains( $penalty, 'sin sanción' ) 
            || str_contains( $penalty, 'incidente de carrera' )
            || $fault_accused <= 50;

        $decision = $is_dismissed ? 'dismissed' : 'proceeds';
        $notes = sanitize_textarea_field( $chief['rationale'] ?? $chief['argument'] ?? '' );
        if ( empty( $notes ) ) {
            $notes = $is_dismissed 
                ? 'Análisis IA: Evidencia insuficiente o incidente de carrera (culpa imputable <= 50%).'
                : sprintf( 'Análisis IA: Mayoría de culpa imputable al acusado (%d%%) con sanción recomendada: %s.', $fault_accused, $chief['penalty'] ?? '' );
        }

        $ai_user_id = function_exists( 'srl_ensure_ai_steward_user' ) ? srl_ensure_ai_steward_user() : 0;

        $ai_vote_record = [
            'voter_type'   => 'ai',
            'user_id'      => $ai_user_id,
            'steward_name' => 'Comisario Virtual AI',
            'decision'     => $decision,
            'notes'        => $notes,
            'created_at'   => current_time( 'mysql' ),
            'added_by'     => 0,
        ];

        if ( $mode === 'always' ) {
            $votes = self::get_votes( $protest_id );
            $votes['ai_steward'] = $ai_vote_record;
            self::save_votes( $protest_id, $votes );
            delete_post_meta( $protest_id, self::META_AI_RESERVED );
        } elseif ( $mode === 'tiebreaker' ) {
            // Store in reserve to evaluate only during tiebreaker
            update_post_meta( $protest_id, self::META_AI_RESERVED, $ai_vote_record );
        }
    }

    /**
     * Calculate current voting tally, quorum status, and simple majority heading.
     *
     * @param int $protest_id
     * @return array
     */
    public static function calculate_tally( $protest_id ) {
        $votes = self::get_votes( $protest_id );
        $min_quorum = intval( get_option( 'srl_protest_min_quorum', 3 ) );
        if ( $min_quorum < 1 ) $min_quorum = 3;
        $ai_mode = get_option( 'srl_protest_ai_vote_mode', 'always' );

        // If tiebreaker mode and AI reserved vote exists, check if tiebreaker is needed
        if ( $ai_mode === 'tiebreaker' && ! isset( $votes['ai_steward'] ) ) {
            $reserved = get_post_meta( $protest_id, self::META_AI_RESERVED, true );
            if ( is_array( $reserved ) && ! empty( $reserved ) ) {
                // Count human votes
                $h_proceeds = 0;
                $h_dismissed = 0;
                foreach ( $votes as $v ) {
                    if ( ( $v['decision'] ?? '' ) === 'proceeds' ) $h_proceeds++;
                    else $h_dismissed++;
                }
                $h_total = count( $votes );
                // If human quorum reached and exactly tied, activate AI vote to break tie
                if ( $h_total >= $min_quorum && $h_proceeds === $h_dismissed ) {
                    $votes['ai_steward'] = $reserved;
                    self::save_votes( $protest_id, $votes );
                }
            }
        }

        $proceeds_count = 0;
        $dismissed_count = 0;
        foreach ( $votes as $v ) {
            if ( ( $v['decision'] ?? '' ) === 'proceeds' ) {
                $proceeds_count++;
            } else {
                $dismissed_count++;
            }
        }

        $total_votes = count( $votes );
        $quorum_reached = ( $total_votes >= $min_quorum );
        $votes_needed = max( 0, $min_quorum - $total_votes );

        $percent_proceeds = ( $total_votes > 0 ) ? round( ( $proceeds_count / $total_votes ) * 100 ) : 0;
        $percent_dismissed = ( $total_votes > 0 ) ? ( 100 - $percent_proceeds ) : 0;

        if ( ! $quorum_reached ) {
            $status = 'deliberation';
            $status_label = sprintf( 'En Deliberación (faltan %d voto%s para quórum)', $votes_needed, $votes_needed === 1 ? '' : 's' );
            $badge_color = '#856404';
            $badge_bg = '#fff3cd';
        } else {
            if ( $proceeds_count > $dismissed_count ) {
                $status = 'majority_proceeds';
                $status_label = 'Mayoría alcanzada: Procede';
                $badge_color = '#155724';
                $badge_bg = '#d4edda';
            } elseif ( $dismissed_count > $proceeds_count ) {
                $status = 'majority_dismissed';
                $status_label = 'Mayoría alcanzada: No Procede (Desestimado)';
                $badge_color = '#721c24';
                $badge_bg = '#f8d7da';
            } else {
                $status = 'tied';
                $status_label = 'Empate Técnico (50% vs 50%) — Requiere desempate';
                $badge_color = '#004085';
                $badge_bg = '#cce5ff';
            }
        }

        return [
            'total_votes'        => $total_votes,
            'min_quorum'         => $min_quorum,
            'quorum_reached'     => $quorum_reached,
            'votes_needed'       => $votes_needed,
            'proceeds_count'     => $proceeds_count,
            'dismissed_count'    => $dismissed_count,
            'percent_proceeds'   => $percent_proceeds,
            'percent_dismissed'  => $percent_dismissed,
            'status'             => $status,
            'status_label'       => $status_label,
            'badge_color'        => $badge_color,
            'badge_bg'           => $badge_bg,
            'votes'              => $votes,
        ];
    }

    /**
     * AJAX: Cast admin vote.
     */
    public static function ajax_cast_vote() {
        check_ajax_referer( 'srl-public-nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'No tienes permisos de comisario para votar.' ] );
        }

        $protest_id = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
        $decision   = isset( $_POST['decision'] ) ? sanitize_text_field( $_POST['decision'] ) : 'proceeds';
        $notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

        if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
            wp_send_json_error( [ 'message' => 'ID de reclamo no válido.' ] );
        }

        $user_id = get_current_user_id();
        $tally = self::cast_admin_vote( $protest_id, $user_id, $decision, $notes );

        wp_send_json_success( [
            'message' => '¡Tu voto ha sido registrado con éxito!',
            'tally'   => $tally,
        ] );
    }

    /**
     * AJAX: Add external steward vote.
     */
    public static function ajax_add_external_vote() {
        check_ajax_referer( 'srl-public-nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'No tienes permisos de comisario para agregar votos externos.' ] );
        }

        $protest_id   = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
        $steward_name = isset( $_POST['steward_name'] ) ? sanitize_text_field( $_POST['steward_name'] ) : '';
        $decision     = isset( $_POST['decision'] ) ? sanitize_text_field( $_POST['decision'] ) : 'proceeds';
        $notes        = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

        if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
            wp_send_json_error( [ 'message' => 'ID de reclamo no válido.' ] );
        }
        if ( empty( $steward_name ) ) {
            wp_send_json_error( [ 'message' => 'Por favor ingresa el nombre del comisario externo.' ] );
        }

        $added_by = get_current_user_id();
        $tally = self::add_external_vote( $protest_id, $steward_name, $decision, $notes, $added_by );

        wp_send_json_success( [
            'message' => 'Voto de comisario externo registrado con éxito.',
            'tally'   => $tally,
        ] );
    }

    /**
     * AJAX: Delete vote.
     */
    public static function ajax_delete_vote() {
        check_ajax_referer( 'srl-public-nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'No tienes permisos para realizar esta acción.' ] );
        }

        $protest_id = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
        $vote_key   = isset( $_POST['vote_key'] ) ? sanitize_text_field( $_POST['vote_key'] ) : '';

        if ( ! $protest_id || empty( $vote_key ) ) {
            wp_send_json_error( [ 'message' => 'Parámetros inválidos.' ] );
        }

        $user_id = get_current_user_id();
        $tally = self::delete_vote( $protest_id, $vote_key, $user_id );

        if ( false === $tally ) {
            wp_send_json_error( [ 'message' => 'No se pudo eliminar el voto o no tienes permisos suficientes.' ] );
        }

        wp_send_json_success( [
            'message' => 'Voto eliminado con éxito.',
            'tally'   => $tally,
        ] );
    }

    /**
     * AJAX: Finalize protest ruling and official sanction.
     */
    public static function ajax_finalize_ruling() {
        check_ajax_referer( 'srl-public-nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'No tienes permisos para aplicar dictámenes oficiales.' ] );
        }

        $protest_id     = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
        $action_status  = isset( $_POST['action_status'] ) ? sanitize_text_field( $_POST['action_status'] ) : 'resolved';
        $final_sanction = isset( $_POST['final_sanction'] ) ? sanitize_text_field( $_POST['final_sanction'] ) : '';
        $steward_notes  = isset( $_POST['steward_notes'] ) ? sanitize_textarea_field( $_POST['steward_notes'] ) : '';

        if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
            wp_send_json_error( [ 'message' => 'ID de reclamo no válido.' ] );
        }

        update_post_meta( $protest_id, self::META_STATUS, $action_status );
        update_post_meta( $protest_id, self::META_SANCTION, $final_sanction );
        update_post_meta( $protest_id, self::META_NOTES, $steward_notes );
        update_post_meta( $protest_id, self::META_RESOLVED_AT, current_time( 'mysql' ) );
        update_post_meta( $protest_id, self::META_RESOLVED_BY, get_current_user_id() );

        wp_send_json_success( [
            'message'       => '¡Dictamen oficial y sanción aplicados con éxito!',
            'action_status' => $action_status,
            'final_sanction'=> $final_sanction,
            'steward_notes' => $steward_notes,
        ] );
    }

    /**
     * AJAX: Reopen a closed protest for review.
     */
    public static function ajax_reopen_protest() {
        check_ajax_referer( 'srl-public-nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'No tienes permisos para reabrir este reclamo.' ] );
        }

        $protest_id = isset( $_POST['protest_id'] ) ? intval( $_POST['protest_id'] ) : 0;
        if ( ! $protest_id || get_post_type( $protest_id ) !== 'srl_protest' ) {
            wp_send_json_error( [ 'message' => 'ID de reclamo no válido.' ] );
        }

        update_post_meta( $protest_id, self::META_STATUS, 'under_review' );

        wp_send_json_success( [
            'message'       => 'Reclamo reabierto con éxito para nueva revisión.',
            'action_status' => 'under_review',
        ] );
    }
}

// Hook initialization
add_action( 'init', [ 'SRL_Protest_Voting', 'init' ] );
