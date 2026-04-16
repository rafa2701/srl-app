<?php
/**
 * Plantilla para mostrar la vista de un solo campeonato.
 *
 * @package SRL_Theme
 */

get_header(); ?>

<div class="srl-container">
    <?php while ( have_posts() ) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="srl-page-header">
                <?php the_title( '<h1>', '</h1>' ); ?>
            </header>

            <div class="entry-content">
                <?php
                // Muestra la descripción del campeonato si existe
                the_content();

                // Llama al shortcode para mostrar la tabla de clasificación
                echo do_shortcode( '[srl_standings championship_id="' . get_the_ID() . '"]' );
                ?>
            </div>
        </article>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
