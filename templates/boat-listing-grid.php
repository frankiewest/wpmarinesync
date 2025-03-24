<?php
/**
 * Template for displaying boat listings in carousel format
 *
 * @package MarineSync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Variables available:
// $query - The WP_Query object with boat posts
// $atts - The attributes passed to the shortcode/block

// Generate a unique ID for this carousel
$carousel_id = 'marinesync-carousel-' . uniqid();
?>

<div class="marinesync-boat-listing marinesync-carousel" id="<?php echo esc_attr($carousel_id); ?>">
	<?php if ($query->have_posts()) : ?>
		<!-- Carousel navigation -->
		<div class="marinesync-carousel-nav">
			<button class="marinesync-carousel-prev" aria-label="<?php esc_attr_e('Previous', 'marinesync'); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
			</button>
			<button class="marinesync-carousel-next" aria-label="<?php esc_attr_e('Next', 'marinesync'); ?>">
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>

		<!-- Carousel container -->
		<div class="marinesync-carousel-container">
			<?php while ($query->have_posts()) : $query->the_post();
				// Get boat meta data
				$price = get_post_meta(get_the_ID(), 'price', true);
				$year = get_post_meta(get_the_ID(), 'year', true);
				$length = get_post_meta(get_the_ID(), 'length', true);
				$location = get_post_meta(get_the_ID(), 'location', true);
				?>
				<div class="marinesync-carousel-item">
					<div class="marinesync-boat-card">
						<div class="marinesync-boat-image">
							<?php if (has_post_thumbnail()) : ?>
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail('medium_large'); ?>
								</a>
							<?php else : ?>
								<a href="<?php the_permalink(); ?>">
									<img src="<?php echo esc_url(MARINESYNC_PLUGIN_URL . 'assets/images/placeholder.jpg'); ?>" alt="<?php the_title_attribute(); ?>">
								</a>
							<?php endif; ?>
						</div>

						<div class="marinesync-boat-content">
							<h3 class="marinesync-boat-title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>

							<div class="marinesync-boat-meta">
								<?php if (!empty($price)) : ?>
									<div class="marinesync-boat-price">
										<span class="marinesync-value"><?php echo esc_html(number_format($price)); ?></span>
									</div>
								<?php endif; ?>

								<div class="marinesync-boat-specs">
									<?php if (!empty($year)) : ?>
										<span class="marinesync-year"><?php echo esc_html($year); ?></span>
									<?php endif; ?>

									<?php if (!empty($length)) : ?>
										<span class="marinesync-length"><?php echo esc_html($length); ?> ft</span>
									<?php endif; ?>
								</div>
							</div>

							<div class="marinesync-boat-actions">
								<a href="<?php the_permalink(); ?>" class="marinesync-button marinesync-button-primary">
									<?php _e('View Details', 'marinesync'); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
			<?php endwhile; ?>
		</div>

	<?php wp_reset_postdata(); ?>

		<script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize the carousel
                const carousel = document.querySelector('#<?php echo esc_js($carousel_id); ?> .marinesync-carousel-container');
                const prevBtn = document.querySelector('#<?php echo esc_js($carousel_id); ?> .marinesync-carousel-prev');
                const nextBtn = document.querySelector('#<?php echo esc_js($carousel_id); ?> .marinesync-carousel-next');
                let scrollAmount = 0;
                const itemWidth = carousel.querySelector('.marinesync-carousel-item').offsetWidth;

                // Handle next button click
                nextBtn.addEventListener('click', function() {
                    carousel.scrollBy({
                        left: itemWidth,
                        behavior: 'smooth'
                    });
                });

                // Handle previous button click
                prevBtn.addEventListener('click', function() {
                    carousel.scrollBy({
                        left: -itemWidth,
                        behavior: 'smooth'
                    });
                });
            });
		</script>
	<?php else : ?>
		<div class="marinesync-no-boats">
			<?php _e('No boats found matching your criteria.', 'marinesync'); ?>
		</div>
	<?php endif; ?>
</div>