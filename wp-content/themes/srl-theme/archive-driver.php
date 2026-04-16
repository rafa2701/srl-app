<?php
/**
 * Archivo para mostrar el archivo de pilotos.
 */

get_header(); ?>

<div class="srl-container">
    <header class="srl-page-header">
        <h1>Pilotos</h1>
    </header>

    <div class="srl-app-container">
        <?php if ( have_posts() ) : ?>
            <div class="srl-list-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="srl-list-card">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <?php the_post_thumbnail( 'medium' ); ?>
                        <?php endif; ?>
                        <h3><?php the_title(); ?></h3>
                        <span class="srl-card-meta">Ver perfil</span>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <p>No se encontraron pilotos.</p>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
