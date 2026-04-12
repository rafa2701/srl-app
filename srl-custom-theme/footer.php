	<footer id="colophon" class="site-footer">
		<div class="container">
            <div class="footer-content">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo-srl.png" alt="SRL Logo" class="footer-logo">
                <p class="footer-description">
                    "Tu punto de encuentro para el Sim Racing en Latinoamérica. Pasión y competencia en cada curva, camino a ser la comunidad referente de la región."
                </p>
            </div>

            <div class="footer-bottom">
                <div class="site-info">
                    &copy; <?php echo date('Y'); ?> Sim Racing Latinoamérica. Powered by Rafael León.
                </div>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url() ); ?>" class="admin-link" title="Dashboard">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12,2A10,10,0,1,0,22,12,10,10,0,0,0,12,2Zm0,18a8,8,0,1,1,8-8A8,8,0,0,1,12,20ZM13,7H11V13h6V11H13Z"/></svg>
                    </a>
                <?php endif; ?>
            </div>
		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
