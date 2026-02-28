<?php
/**
 * GlamLux2Lux Theme — Index Template
 *
 * Fallback for any page that doesn't have a dedicated template.
 * front-page.php handles the homepage.
 */

get_header(); ?>

<main class="gl-container gl-section gl-page-enter" style="min-height:60vh;">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'gl-card' ); ?> style="padding:48px;margin-bottom:32px;">
            <h1 class="gl-headline" style="font-size:2rem;margin-bottom:16px;"><?php the_title(); ?></h1>
            <div class="gl-card-desc" style="font-size:1rem;line-height:1.8;"><?php the_content(); ?></div>
        </article>
    <?php endwhile; else : ?>
        <div class="gl-card" style="padding:48px;text-align:center;">
            <p class="gl-subhead" style="font-size:1.25rem;color:var(--gl-text-muted);">Nothing here yet — check back soon.</p>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
