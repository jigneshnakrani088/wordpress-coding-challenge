<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 *
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ): string {
		$post_types = get_post_types( [ 'public' => true ] );
		$class_name = ! empty( $attributes['className'] ) ? $attributes['className'] : '';
		$post_id    = $block->context['postId'];
		ob_start();

		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2>
				<?php esc_html_e( 'Post Counts', 'site-counts' ); ?>
			</h2>
			<ul>
				<?php
				foreach ( $post_types as $post_type_slug ) :
					$post_type_object = get_post_type_object( $post_type_slug );

					if ( ! $post_type_object ) {
						continue;
					}

					$post_counts = wp_count_posts( $post_type_slug );

					if ( 'attachment' === $post_type_slug ) {
						$post_count = property_exists( $post_counts, 'inherit' ) ? $post_counts->inherit : 0;
					} else {
						$post_count = property_exists( $post_counts, 'publish' ) ? $post_counts->publish : 0;
					}

					?>
					<li>
						<?php
						if ( $post_count ) {
							printf(
							/* translators: %d: post count */
								_n(
									'There is only %1$d %2$s.',
									'There are %1$d %2$s.',
									$post_count,
									'site-counts'
								),
								number_format_i18n( $post_count ),
								1 === intval( $post_count )
									? esc_html( $post_type_object->labels->singular_name )
									: esc_html( $post_type_object->labels->name )
							);
						} else {
							printf( 'No %1$s found.', esc_html( $post_type_object->labels->name ) );
						}
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php
				/* translators: %d: post ID */
				echo esc_html( sprintf( 'The current post ID is %d.', $post_id ) );
				?>
			</p>
			<?php
			$query = new WP_Query(
				[
					'post_type'              => [ 'post', 'page' ],
					'post_status'            => 'any',
					'posts_per_page'         => 6,
					'fields'                 => 'ids',
					'date_query'             => [
						[
							'hour'    => 9,
							'compare' => '>=',
						],
						[
							'hour'    => 17,
							'compare' => '<=',
						],
					],
					'tag'                    => 'foo',
					'category_name'          => 'baz',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'ignore_sticky_posts'    => true,
				]
			);

			if ( $query->have_posts() ) :
				// Remove current post from the list if available.
				$filtered_posts = array_diff( $query->posts, [ $post_id ] );
				$posts_count    = min( count( $filtered_posts ), 5 );
				?>
				<h2>
					<?php
					printf(
						esc_html(
							/* translators: %d: Number of post found. */
							_n(
								'%1$d post with the tag of foo and the category of baz.',
								'%1$d posts with the tag of foo and the category of baz.',
								$posts_count,
								'site-counts'
							),
						),
						intval( $posts_count )
					);
					?>
				</h2>

				<ul>
					<?php
					foreach ( $filtered_posts as $filtered_post ) {
						printf( '<li>%s</li>', wp_kses_post( get_the_title( $filtered_post ) ) );
					}
					?>
				</ul>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
