<?php
/**
 * Plantilla para mostrar la vista de una sola sesión.
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
                the_content();
                ?>
                <p>Esta es una sesión individual. Los resultados detallados se encuentran en la página del Evento.</p>
            </div>
        </article>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
