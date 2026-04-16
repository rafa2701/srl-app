<?php
/**
 * Template Name: Single Driver
 * Description: Driver profile for SR Latinoamérica
 */
get_header();
$driver_id = get_the_ID();
$stats     = srl_get_driver_stats( $driver_id );
$avatar    = get_the_post_thumbnail_url( $driver_id, 'medium' ) ?: 'https://ui-avatars.com/api/?name=' . urlencode(get_post_meta( $driver_id, 'srl_driver_name', true )) . '&background=E60000&color=fff';
?>
<div class="srl-container">
    <header class="srl-page-header">
         <h1><?php echo esc_html( get_post_meta( $driver_id, 'srl_driver_name', true ) ); ?></h1>
    </header>

    <div class="srl-app-container" style="display: flex; align-items: center; gap: 2rem; margin-bottom: 3rem;">
        <img src="<?php echo esc_url( $avatar ); ?>" style="width: 150px; height: 150px; border-radius: 50%; border: 4px solid var(--srl-red);" alt="">
        <div>
            <p style="font-size: 1.2rem; color: #ccc;">Steam ID: <span style="color: #fff;"><?php echo esc_html( get_the_title() ); ?></span></p>
        </div>
    </div>

    <h2>Estadísticas del Piloto</h2>
    <div class="srl-stats-grid">
        <?php
        $labels = [ 'wins' => 'Victorias', 'podiums' => 'Podios', 'poles' => 'Poles', 'fastest_laps' => 'Vueltas Rápidas' ];
        foreach ( $labels as $k => $label ) :
            $value = $stats[ $k ] ?? 0;
            ?>
            <div class="srl-stat-card">
                <div class="stat-value"><?php echo number_format_i18n( $value ); ?></div>
                <div class="stat-label"><?php echo esc_html( $label ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 style="margin-top: 3rem;">Últimos Resultados</h2>
    <div class="srl-app-container">
        <?php echo srl_driver_results_table( $driver_id ); ?>
    </div>
</div>

<?php
get_footer();
