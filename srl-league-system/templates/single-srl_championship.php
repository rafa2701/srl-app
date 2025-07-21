<?php
/**
 * Plantilla para mostrar la vista de un solo campeonato.
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
                    // Muestra la descripción del campeonato si existe
                    the_content();

                    // Llama al shortcode para mostrar la tabla de clasificación
                    echo do_shortcode( '[srl_standings championship_id="' . get_the_ID() . '"]' );
                    ?>
                </div>
            </article>

        <?php endwhile; ?>
    </main>
</div>

<?php get_footer(); ?>
