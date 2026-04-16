<?php
/**
 * Plantilla para mostrar la vista de un solo evento.
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
                // Llama al shortcode para mostrar los resultados del evento
                echo do_shortcode( '[srl_event_results event_id="' . get_the_ID() . '"]' );
                ?>
            </div>
        </article>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
