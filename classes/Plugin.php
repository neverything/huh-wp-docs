<?php
/**
 * Main plugin file.
 */

namespace Neverything\WP_Huh;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Plugin version.
	 */
	const VERSION = '1.0.0';

	/**
	 * Holds the markdown doc urls.
	 *
	 * @var null
	 */
	public $doc_urls = null;

	/**
	 * Initialize.
	 *
	 * @param string $doc_urls URL of the raw markdown file.
	 */
	public function init( $doc_urls ) {

		/**
		 * Set the initial doc urls.
		 */
		$this->set_doc_urls( $doc_urls );

		/**
		 * Initialize the plugin.
		 */
		$this->hooks();

	}

	/**
	 * Load plugin text domain.
	 */
	public function load_text_domain() {
		load_plugin_textdomain(
			'huh-wp-docs',
			false,
			plugin_dir_path( dirname( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Add hooks for admin and customizer preview.
	 */
	public function hooks() {
		/**
		 * General init tasks.
		 */
		add_action( 'init', [ $this, 'load_text_domain' ] );
		add_action( 'init', [ $this, 'register_scripts' ] );

		/**
		 * Add admin hooks to display the docs in the wp-admin.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_load_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'data_urls' ] );

		/**
		 * Only show on front-end when using in the customizer preview.
		 */
		add_action( 'customize_preview_init', [ $this, 'customize_hooks' ] );

		add_action( 'init', [ $this, 'frontend_hooks' ] );
	}

	/**
	 * Hooks to enqueue docs on the front-end, for example in the customizer.
	 */
	public function customize_hooks() {
		if ( array_key_exists( 'all', $this->doc_urls )
		     || array_key_exists( 'customize.php', $this->doc_urls )
		) {
			add_action( 'wp_enqueue_scripts', [ $this, 'customizer_preview_load_scripts' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'data_urls' ] );
			add_action( 'wp_footer', [ $this, 'display' ] );
		}
	}

	public function frontend_hooks() {
		if ( is_user_logged_in() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'frontend_load_scripts' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'data_urls' ] );
			add_action( 'wp_footer', [ $this, 'display' ] );
		}
	}

	/**
	 * Only enqueue in the customizer preview, not the customizer itself.
	 */
	public function customizer_preview_load_scripts() {
		wp_enqueue_style( 'huh_style' );
		wp_enqueue_script( 'huh_script' );
	}

	/**
	 * Only enqueue in the customizer preview, not the customizer itself.
	 */
	public function frontend_load_scripts() {
		wp_enqueue_style( 'huh_style' );
		wp_enqueue_script( 'huh_script' );
	}

	/**
	 * Determine current screen.
	 */
	public function admin_load_scripts() {
		if ( ! is_customize_preview()
		     && ( array_key_exists( 'all', $this->doc_urls )
		          || array_key_exists( $this->get_current_screen_hook(), $this->doc_urls ) )
		) {
			wp_enqueue_style( 'huh_style' );
			wp_enqueue_script( 'huh_script' );
			add_action( 'admin_footer', [ $this, 'display' ] );
		}
	}

	/**
	 * Enqueue CSS and JS.
	 */
	public function register_scripts() {
		/**
		 * Minimal stylesheet to make Huh WP Docs look rad.
		 */
		wp_register_style(
			'huh_style',
			plugin_dir_url( dirname( __FILE__ ) ) . 'huh-wp-docs.min.css',
			null,
			self::VERSION
		);

		/**
		 * marked.js a minimal Markdown parser.
		 *
		 * @see https://github.com/chjj/marked
		 */
		wp_register_script(
			'huh_markdown_script',
			plugin_dir_url( dirname( __FILE__ ) ) . 'js/marked.min.js',
			null,
			self::VERSION
		);

		/**
		 * The js functions to tie it all together.
		 */
		wp_register_script(
			'huh_script',
			plugin_dir_url( dirname( __FILE__ ) ) . 'js/huh-wp-docs.min.js',
			[
				'huh_markdown_script',
				'underscore'
			],
			self::VERSION
		);

	}

	/**
	 * Helper to get current screen info ex. <code>edit.php?post_type=page</code>
	 * Includes the taxonomy and post_type, works in customize preview too.
	 *
	 * @return string current screen info.
	 */
	protected function get_current_screen_hook() {
		/**
		 * Array of potential query args to add.
		 */
		$query_args = [];

		if ( is_admin() ) {
			/**
			 * Use the $pagenow global, for ex. <code>edit.php</code>
			 */
			global $pagenow;

			/**
			 * Get the current WP_Screen object.
			 */
			$current_screen = get_current_screen();

			/**
			 * Check and set taxonomy if we have one.
			 */
			if ( $current_screen->taxonomy ) {
				$query_args['taxonomy'] = $current_screen->taxonomy;
			}

			/**
			 * Check and set custom post type if we have one.
			 */
			if ( $current_screen->post_type ) {
				$query_args['post_type'] = $current_screen->post_type;
			}
		} else if ( is_customize_preview() ) {
			/**
			 * Override $pagenow with identifier for the customize preview.
			 */
			$pagenow = 'customize.php';
		} else {
			global $wp;
			return $wp->request;
		}

		/**
		 * Return a string containing all necessary info to be checked against.
		 */
		return add_query_arg( $query_args, $pagenow );
	}

	/**
	 * Set the urls to be used as docs.
	 *
	 * @param mixed $urls set of urls for markdown docs.
	 */
	public function set_doc_urls( $urls ) {
		$urls = $this->prepare_doc_urls( $urls );
		$urls = apply_filters( 'huh_wp_docs_filter_doc_urls', $urls );
		$this->doc_urls = $this->prepare_doc_urls( $urls );
	}

	/**
	 * Clean up and prepare the several url formats to be used.
	 *
	 * @param mixed $urls array or string with comma separated urls.
	 *
	 * @return array $doc_urls sanitized format of urls.
	 */
	protected function prepare_doc_urls( $urls ) {
		$doc_urls = [];
		if ( is_array( $urls ) && ! empty( $urls ) ) {
			foreach ( $urls as $k => $v ) {
				if ( is_array( $v ) && ! empty( $v ) ) {
					$doc_urls[ $k ] = array_map( 'trim', $v );
				} else {
					// Explode URLs and Trim Whitespace
					$doc_urls[ $k ] = array_map( 'trim', explode( ',', $v ) );
				}
			}
		} else {
			// Explode URLs and Trim Whitespace
			$doc_urls['all'] = array_map( 'trim', explode( ',', $urls ) );
		}
		return $doc_urls;
	}

	/**
	 * Add data urls to the screen.
	 */
	public function data_urls() {
		$current_screen = $this->get_current_screen_hook();

		$localize = [
			'huhDocUrl' => apply_filters( "huh_wp_docs_filter_doc_urls-{$current_screen}", $this->get_doc_urls( $current_screen ) )
		];

		if ( WP_DEBUG ) {
			$localize['huhCurrentScreen'] = $current_screen;
		}

		wp_localize_script(
			'huh_script',
			'HuhWPDocs',
			$localize
		);
	}

	/**
	 * Prepare the doc urls.
	 *
	 * @param string $current_screen current screen hook.
	 *
	 * @return array of doc urls for this screen.
	 */
	public function get_doc_urls( $current_screen ) {
		$all          = [];
		$current_hook = [];

		if ( array_key_exists( 'all', $this->doc_urls ) ) {
			$all = $this->doc_urls['all'];
		}

		if ( array_key_exists( $current_screen, $this->doc_urls ) ) {
			$current_hook = $this->doc_urls[ $current_screen ];
		}

		return array_merge( $all, $current_hook );
	}

	/**
	 * Get admin color scheme.
	 */
	public function get_admin_color() {
		if ( is_admin() ) {
			global $_wp_admin_css_colors;
			$current_color_scheme = get_user_meta( get_current_user_id(), 'admin_color', true );
			$colors_array         = $_wp_admin_css_colors[ $current_color_scheme ]->colors;
			$color                = $colors_array[2];
		} else {
			$color = '#0073aa'; // Default admin theme blue.
		}

		return $color;
	}

	/**
	 * Display the markup.
	 */
	public function display() {
		?>
		<div class="huh-launcher">
			<button class="huh-launcher--button" id="huh-launcher--button" data-accent-color="<?php echo esc_attr( $this->get_admin_color() ); ?>">
				<svg class="huh-launcher--icon-enable" xmlns="https://www.w3.org/2000/svg" xmlns:xlink="https://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 100 100" style="enable-background:new 0 0 100 100;" xml:space="preserve"><g>
						<circle cx="50" cy="63.5" r="3"></circle>
						<g>
							<path d="M88.6,50c0-21.3-17.3-38.6-38.6-38.6S11.4,28.7,11.4,50S28.7,88.6,50,88.6S88.6,71.3,88.6,50z M15.6,50    c0-18.9,15.4-34.4,34.4-34.4S84.4,31.1,84.4,50S68.9,84.4,50,84.4S15.6,68.9,15.6,50z"></path>
							<path d="M55.8,42.1c0.1,2.5-1.4,4.8-3.7,5.7c-2.6,1-4.3,3.6-4.3,6.5v1.4h4.2v-1.4c0-1.1,0.7-2.2,1.6-2.6c4-1.6,6.5-5.5,6.3-9.8    c-0.2-5.1-4.5-9.4-9.6-9.6C47.7,32.1,45,33.1,43,35c-2,1.9-3.1,4.5-3.1,7.3h4.2c0-1.6,0.6-3.1,1.8-4.2c1.2-1.1,2.7-1.7,4.3-1.6    C53.3,36.6,55.7,39.1,55.8,42.1z"></path>
						</g>
					</g></svg>
				<svg class="huh-launcher--icon-close" xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24">
					<g id="plus">
						<path d="M18.36,19.78L12,13.41,5.64,19.78,4.22,18.36,10.59,12,4.22,5.64,5.64,4.22,12,10.59l6.36-6.36,1.41,1.41L13.41,12l6.36,6.36Z" />
					</g>
				</svg>
				<span class="huh-launcher--label"><?php esc_html_e( 'Need help?', 'huh-wp-docs' ); ?></span>
			</button>
		</div>

		<div class="huh-container" id="huh-container">
			<div class="huh-container--head" id="huh-header">
				<h4 class="huh-container--heading"><?php esc_html_e( 'Need help?', 'huh-wp-docs' ); ?></h4>
				<a href="javascript:;" class="huh-container--back" id="huh-back-to-toc">
					<svg xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24">
						<rect x="0" fill="none" width="24" height="24" />
						<g>
							<path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
						</g>
					</svg>
					<?php esc_html_e( 'Back', 'huh-wp-docs' ); ?>
				</a>
				<svg class="huh-container--close-mobile" id="huh-mobile-close" xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24">
					<g id="plus">
						<path d="M18.36,19.78L12,13.41,5.64,19.78,4.22,18.36,10.59,12,4.22,5.64,5.64,4.22,12,10.59l6.36-6.36,1.41,1.41L13.41,12l6.36,6.36Z" />
					</g>
				</svg>
			</div>
			<div class="huh-container--content" id="huh-content"></div>
		</div>
		<?php
	}

}
