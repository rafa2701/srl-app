<?php get_header(); ?>

<section class="hero" style="background-image: url('https://images.unsplash.com/photo-1547915720-30c275997231?q=80&w=2000&auto=format&fit=crop');">
    <div class="hero-content">
        <h1 class="hero-title">Sim Racing Latinoamérica</h1>
        <p class="hero-subtitle">Pasión y competencia en cada curva.</p>
    </div>
</section>

<div class="container">
    <div id="primary" class="content-area">
        <main id="main" class="site-main">

        <?php
        while ( have_posts() ) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <div class="entry-content" style="padding-top: 40px;">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        endwhile;
        ?>

        </main>
    </div>
</div>

<?php get_footer(); ?>
