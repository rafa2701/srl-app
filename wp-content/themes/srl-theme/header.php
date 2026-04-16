<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="srl-navbar">
    <div class="logo">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <img src="<?php echo esc_url( srl_get_theme_logo_url('header') ); ?>" alt="<?php bloginfo( 'name' ); ?>">
        </a>
    </div>

    <nav class="srl-nav-menu">
        <?php
        wp_nav_menu( [
            'theme_location' => 'main-menu',
            'container'      => false,
            'fallback_cb'    => false,
        ] );
        ?>
    </nav>
</header>
