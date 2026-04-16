<footer class="srl-footer">
    <div class="srl-footer-content">
        <div class="srl-footer-brand">
            <img src="<?php echo esc_url( srl_get_theme_logo_url('footer') ); ?>" alt="<?php bloginfo( 'name' ); ?>">
            <p class="srl-footer-description">
                Tu punto de encuentro para el Sim Racing en Latinoamérica. Pasión y competencia en cada curva, camino a ser la comunidad referente de la región.
            </p>

            <?php if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo admin_url(); ?>" class="srl-footer-admin-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="22" x2="12" y2="15"/><line x1="5.4" y1="8.4" x2="9.5" y2="10.5"/><line x1="18.6" y1="8.4" x2="14.5" y2="10.5"/></svg>
                    Dashboard
                </a>
            <?php endif; ?>
        </div>

        <div class="srl-footer-nav">
            <?php
            wp_nav_menu( [
                'theme_location' => 'footer-menu',
                'container'      => false,
                'fallback_cb'    => false,
                'menu_class'     => 'footer-links',
            ] );
            ?>
        </div>
    </div>

    <div class="srl-footer-bottom">
        <p>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. Todos los derechos reservados.</p>
        <p>Powered by Rafael León</p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
