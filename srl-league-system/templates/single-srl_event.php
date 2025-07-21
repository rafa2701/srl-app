<?php
/**
 * Plantilla para mostrar la vista de un solo evento.
 *
 * @package SRL_League_System
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php while ( have_posts() ) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header>

                <div class="entry-content">
                    <?php
                    // Llama al nuevo shortcode para mostrar los resultados del evento
                    echo do_shortcode( '[srl_event_results event_id="' . get_the_ID() . '"]' );
                    ?>
                </div>
            </article>

        <?php endwhile; ?>
    </main>
</div>

<?php get_footer(); ?>
