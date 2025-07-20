<?php
/**
 * Template Name: Single Driver
 * Description: Driver profile for SR Latinoamérica
 */
get_header();
$driver_id = get_the_ID();
$stats     = srl_get_driver_stats( $driver_id );
$avatar    = get_the_post_thumbnail_url( $driver_id, 'medium' ) ?: 'https://avatars.dicebear.com/api/initials/' . get_post_meta( $driver_id, 'srl_driver_name', true ) . '.svg';
?>
<div class="max-w-4xl mx-auto px-4 py-8 space-y-8">

    <!-- Hero Card -->
    <div class="bg-white rounded-lg shadow flex items-center space-x-6 p-6">
        <img src="<?php echo esc_url( $avatar ); ?>" class="w-24 h-24 rounded-full object-cover" alt="">
        <div>
            <h1 class="text-3xl font-bold text-srl-red"><?php echo esc_html( get_post_meta( $driver_id, 'srl_driver_name', true ) ); ?></h1>
            <p class="text-gray-600">Steam ID: <?php echo esc_html( get_the_title() ); ?></p>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <?php
        $labels = [ 'wins' => 'Wins', 'podiums' => 'Podiums', 'poles' => 'Poles', 'fastest_laps' => 'Fastest Laps' ];
        foreach ( $labels as $k => $label ) :
            $value = $stats[ $k ] ?? 0;
            ?>
            <div class="bg-white rounded p-4 shadow">
                <div class="text-2xl font-bold text-srl-red"><?php echo number_format_i18n( $value ); ?></div>
                <div class="text-sm text-gray-600"><?php echo esc_html( $label ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Results Table -->
    <div class="bg-white rounded-lg shadow">
        <h2 class="text-xl font-bold p-4 border-b">Race Results</h2>
        <div class="overflow-x-auto">
            <?php echo srl_driver_results_table( $driver_id ); ?>
        </div>
    </div>

    <!-- Placeholder for future chart -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-xl font-bold mb-3">Progress</h2>
        <canvas id="progressChart" height="120"></canvas>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('progressChart');
// TODO: feed with real data via wp_localize_script later
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['R1','R2','R3','R4'],
        datasets: [{
            label: 'Championship Points',
            data: [25,45,60,82],
            borderColor: 'var(--srl-red)',
            fill: false
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});
</script>

<?php
get_footer();