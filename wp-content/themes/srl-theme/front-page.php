<?php
get_header();
?>

<section class="srl-hero">
    <h1>Sim Racing Latinoamérica</h1>
    <p>Pasión y competencia en cada curva.</p>
</section>

<div class="srl-container">
    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
    endif;
    ?>
</div>

<?php
get_footer();
