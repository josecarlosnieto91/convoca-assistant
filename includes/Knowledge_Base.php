<?php
/**
 * Knowledge Base: registers CPTs, taxonomies, metadata, admin columns,
 * and meta boxes for managing assistant-specific content data.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Manages custom post types for FAQ and Knowledge Base articles,
 * plus metadata, admin columns, meta boxes, and source configuration.
 */
class Knowledge_Base {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// CPTs + taxonomies.
		add_action( 'init', array( __CLASS__, 'register_faq_cpt' ) );
		add_action( 'init', array( __CLASS__, 'register_kb_cpt' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );

		// Admin columns for CPTs.
		if ( is_admin() ) {
			add_action( 'current_screen', array( __CLASS__, 'init_admin' ) );
		}
	}

	/**
	 * Initialize admin-specific hooks per screen.
	 *
	 * @return void
	 */
	public static function init_admin(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// FAQ columns.
		if ( 'edit-convoca_faq' === $screen->id ) {
			add_filter( 'manage_convoca_faq_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
			add_action( 'manage_convoca_faq_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
			add_filter( 'manage_edit-convoca_faq_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		}

		// KB columns.
		if ( 'edit-convoca_kb' === $screen->id ) {
			add_filter( 'manage_convoca_kb_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
			add_action( 'manage_convoca_kb_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
			add_filter( 'manage_edit-convoca_kb_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		}

		// Post/Page columns.
		if ( in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
			add_filter( "manage_{$screen->post_type}_posts_columns", array( __CLASS__, 'add_assistant_column' ) );
			add_action( "manage_{$screen->post_type}_posts_custom_column", array( __CLASS__, 'render_assistant_column' ), 10, 2 );
		}

		// Meta box on all supported post type edit screens.
		$supported = array( 'convoca_faq', 'convoca_kb', 'post', 'page' );
		if ( in_array( $screen->post_type, $supported, true ) && 'post' === $screen->base ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
			add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		}
	}

	/* ── Custom Post Types ─────────────────────── */

	/**
	 * Register convoca_faq custom post type.
	 *
	 * @return void
	 */
	public static function register_faq_cpt(): void {
		$labels = array(
			'name'               => __( 'FAQs', 'convoca-assistant' ),
			'singular_name'      => __( 'FAQ', 'convoca-assistant' ),
			'add_new'            => __( 'Añadir FAQ', 'convoca-assistant' ),
			'add_new_item'       => __( 'Añadir nueva FAQ', 'convoca-assistant' ),
			'edit_item'          => __( 'Editar FAQ', 'convoca-assistant' ),
			'view_item'          => __( 'Ver FAQ', 'convoca-assistant' ),
			'search_items'       => __( 'Buscar FAQs', 'convoca-assistant' ),
			'not_found'          => __( 'No se encontraron FAQs', 'convoca-assistant' ),
			'all_items'          => __( 'Todas las FAQs', 'convoca-assistant' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'faq' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-editor-help',
		);

		register_post_type( 'convoca_faq', $args );
	}

	/**
	 * Register convoca_kb custom post type.
	 *
	 * @return void
	 */
	public static function register_kb_cpt(): void {
		$labels = array(
			'name'               => __( 'Base de Conocimiento', 'convoca-assistant' ),
			'singular_name'      => __( 'Artículo', 'convoca-assistant' ),
			'add_new'            => __( 'Añadir artículo', 'convoca-assistant' ),
			'add_new_item'       => __( 'Añadir nuevo artículo', 'convoca-assistant' ),
			'edit_item'          => __( 'Editar artículo', 'convoca-assistant' ),
			'view_item'          => __( 'Ver artículo', 'convoca-assistant' ),
			'search_items'       => __( 'Buscar artículos', 'convoca-assistant' ),
			'not_found'          => __( 'No se encontraron artículos', 'convoca-assistant' ),
			'all_items'          => __( 'Todos los artículos', 'convoca-assistant' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'knowledge-base' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-book-alt',
		);

		register_post_type( 'convoca_kb', $args );
	}

	/* ── Taxonomies ────────────────────────────── */

	/**
	 * Register shared taxonomies: FAQ category and KB category.
	 *
	 * @return void
	 */
	public static function register_taxonomies(): void {
		$faq_labels = array(
			'name'              => __( 'Categorías de FAQ', 'convoca-assistant' ),
			'singular_name'     => __( 'Categoría de FAQ', 'convoca-assistant' ),
			'search_items'      => __( 'Buscar categorías', 'convoca-assistant' ),
			'all_items'         => __( 'Todas las categorías', 'convoca-assistant' ),
			'edit_item'         => __( 'Editar categoría', 'convoca-assistant' ),
			'update_item'       => __( 'Actualizar categoría', 'convoca-assistant' ),
			'add_new_item'      => __( 'Añadir nueva categoría', 'convoca-assistant' ),
			'new_item_name'     => __( 'Nueva categoría', 'convoca-assistant' ),
		);

		register_taxonomy(
			'convoca_faq_cat',
			'convoca_faq',
			array(
				'labels'       => $faq_labels,
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'faq-category' ),
			)
		);

		$kb_labels = array(
			'name'              => __( 'Categorías de KB', 'convoca-assistant' ),
			'singular_name'     => __( 'Categoría de KB', 'convoca-assistant' ),
			'search_items'      => __( 'Buscar categorías', 'convoca-assistant' ),
			'all_items'         => __( 'Todas las categorías', 'convoca-assistant' ),
			'edit_item'         => __( 'Editar categoría', 'convoca-assistant' ),
			'update_item'       => __( 'Actualizar categoría', 'convoca-assistant' ),
			'add_new_item'      => __( 'Añadir nueva categoría', 'convoca-assistant' ),
			'new_item_name'     => __( 'Nueva categoría', 'convoca-assistant' ),
		);

		register_taxonomy(
			'convoca_kb_cat',
			'convoca_kb',
			array(
				'labels'       => $kb_labels,
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'kb-category' ),
			)
		);
	}

	/* ── Post Meta ──────────────────────────────── */

	/**
	 * Register post meta for indexing and weights.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		$post_types = array( 'convoca_faq', 'convoca_kb', 'post', 'page' );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'_convoca_assistant_keywords',
				array(
					'type'              => 'string',
					'description'       => __( 'Palabras clave extra (separadas por coma)', 'convoca-assistant' ),
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'show_in_rest'      => true,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				'_convoca_assistant_weight',
				array(
					'type'              => 'number',
					'description'       => __( 'Peso en búsquedas (0-10). 0 = peso por defecto del tipo', 'convoca-assistant' ),
					'single'            => true,
					'default'           => 0,
					'sanitize_callback' => 'floatval',
					'show_in_rest'      => true,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				'_convoca_assistant_exclude',
				array(
					'type'              => 'boolean',
					'description'       => __( 'Excluir del índice del asistente', 'convoca-assistant' ),
					'single'            => true,
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'show_in_rest'      => true,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/* ── Admin Columns: FAQ & KB ────────────────── */

	/**
	 * Add custom columns to FAQ and KB list tables.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_admin_columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['assistant_weight']   = __( 'Peso', 'convoca-assistant' );
		$columns['assistant_keywords'] = __( 'Keywords', 'convoca-assistant' );
		$columns['assistant_exclude']  = __( 'Excluido', 'convoca-assistant' );
		$columns['date']               = $date;

		return $columns;
	}

	/**
	 * Render admin column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_admin_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'assistant_weight':
				$weight = get_post_meta( $post_id, '_convoca_assistant_weight', true );
				if ( $weight ) {
					echo esc_html( $weight );
				} else {
					$type       = (string) get_post_type( $post_id );
					$weight_def = self::get_default_weight( $type );
					echo '<span class="description">' . esc_html( (string) $weight_def ) . ' (def)</span>';
				}
				break;

			case 'assistant_keywords':
				$kw = get_post_meta( $post_id, '_convoca_assistant_keywords', true );
				if ( $kw ) {
					echo esc_html( wp_trim_words( $kw, 8, '…' ) );
				} else {
					echo '<span class="description">—</span>';
				}
				break;

			case 'assistant_exclude':
				$excluded = (bool) get_post_meta( $post_id, '_convoca_assistant_exclude', true );
				if ( $excluded ) {
					echo '<span style="color:#dc3232;">● ' . esc_html__( 'Sí', 'convoca-assistant' ) . '</span>';
				} else {
					echo '<span style="color:#46b450;">● ' . esc_html__( 'No', 'convoca-assistant' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Make weight column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['assistant_weight'] = array( '_convoca_assistant_weight', false );
		return $columns;
	}

	/* ── Admin Column: Posts & Pages (compact) ──── */

	/**
	 * Add a compact assistant column to post/page list tables.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_assistant_column( array $columns ): array {
		$columns['assistant_status'] = __( 'Asistente', 'convoca-assistant' );
		return $columns;
	}

	/**
	 * Render compact assistant status column on posts/pages.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_assistant_column( string $column, int $post_id ): void {
		if ( 'assistant_status' !== $column ) {
			return;
		}

		$excluded = (bool) get_post_meta( $post_id, '_convoca_assistant_exclude', true );
		$weight   = get_post_meta( $post_id, '_convoca_assistant_weight', true );

		if ( $excluded ) {
			echo '<span style="color:#dc3232;">' . esc_html__( 'Excluido', 'convoca-assistant' ) . '</span>';
		} else {
			$label = __( 'Indexado', 'convoca-assistant' );
			$extra = $weight ? " | {$weight}" : '';
			echo '<span style="color:#46b450;">● ' . esc_html( $label . $extra ) . '</span>';
		}
	}

	/* ── Meta Box ───────────────────────────────── */

	/**
	 * Add the assistant meta box to supported post types.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public static function add_meta_box( string $post_type ): void {
		$supported = array( 'convoca_faq', 'convoca_kb', 'post', 'page' );
		if ( ! in_array( $post_type, $supported, true ) ) {
			return;
		}

		add_meta_box(
			'convoca_assistant_meta',
			__( 'Convoca Assistant', 'convoca-assistant' ),
			array( __CLASS__, 'render_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box contents.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'convoca_assistant_meta', 'convoca_assistant_meta_nonce' );

		$keywords = get_post_meta( $post->ID, '_convoca_assistant_keywords', true );
		$weight   = get_post_meta( $post->ID, '_convoca_assistant_weight', true );
		$exclude  = (bool) get_post_meta( $post->ID, '_convoca_assistant_exclude', true );
		$default_weight = self::get_default_weight( $post->post_type );
		?>
		<p>
			<label for="convoca-assistant-keywords">
				<?php esc_html_e( 'Palabras clave extra:', 'convoca-assistant' ); ?>
			</label>
			<input type="text" id="convoca-assistant-keywords"
			       name="convoca_assistant_keywords"
			       value="<?php echo esc_attr( $keywords ); ?>"
			       class="widefat" placeholder="ej: renovación, cuota, alta" />
			<span class="description"><?php esc_html_e( 'Separadas por coma. Mejoran la búsqueda.', 'convoca-assistant' ); ?></span>
		</p>
		<p>
			<label for="convoca-assistant-weight">
				<?php esc_html_e( 'Peso (0-10):', 'convoca-assistant' ); ?>
			</label>
			<input type="number" id="convoca-assistant-weight"
			       name="convoca_assistant_weight"
			       value="<?php echo esc_attr( $weight ?: '' ); ?>"
			       step="0.1" min="0" max="10" style="width:80px;" />
			<span class="description"><?php echo esc_html( sprintf( __( 'Defecto: %s', 'convoca-assistant' ), $default_weight ) ); ?></span>
		</p>
		<p>
			<label>
				<input type="checkbox" name="convoca_assistant_exclude" value="1" <?php checked( $exclude ); ?> />
				<?php esc_html_e( 'Excluir del índice del asistente', 'convoca-assistant' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['convoca_assistant_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['convoca_assistant_meta_nonce'] ), 'convoca_assistant_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		$supported = array( 'convoca_faq', 'convoca_kb', 'post', 'page' );
		if ( ! in_array( $post->post_type, $supported, true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save keywords.
		if ( isset( $_POST['convoca_assistant_keywords'] ) ) {
			$keywords = sanitize_text_field( wp_unslash( $_POST['convoca_assistant_keywords'] ) );
			update_post_meta( $post_id, '_convoca_assistant_keywords', $keywords );
		}

		// Save weight.
		if ( isset( $_POST['convoca_assistant_weight'] ) ) {
			$weight = (float) $_POST['convoca_assistant_weight'];
			update_post_meta( $post_id, '_convoca_assistant_weight', $weight );
		} else {
			delete_post_meta( $post_id, '_convoca_assistant_weight' );
		}

		// Save exclude.
		$exclude = ! empty( $_POST['convoca_assistant_exclude'] );
		update_post_meta( $post_id, '_convoca_assistant_exclude', $exclude );

		// Mark index dirty.
		Indexer::mark_dirty();
	}

	/* ── Source helpers ─────────────────────────── */

	/**
	 * Get configured sources for indexing.
	 *
	 * @return string[]
	 */
	public static function get_active_sources(): array {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );
		$sources  = array();

		$map = array(
			'post'              => 'source_post',
			'page'              => 'source_page',
			'convoca_faq'       => 'source_convoca_faq',
			'convoca_kb'        => 'source_convoca_kb',
			'product'           => 'source_woocommerce',
		);

		foreach ( $map as $post_type => $setting_key ) {
			if ( ! empty( $settings[ $setting_key ] ) ) {
				if ( 'product' === $post_type && ! class_exists( 'WooCommerce' ) ) {
					continue;
				}
				$sources[] = $post_type;
			}
		}

		return $sources;
	}

	/**
	 * Get default weight for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return float
	 */
	public static function get_default_weight( string $post_type ): float {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		$map = array(
			'convoca_faq'  => 'weight_convoca_faq',
			'convoca_kb'   => 'weight_convoca_kb',
			'post'         => 'weight_post',
			'page'         => 'weight_page',
			'product'      => 'weight_product',
		);

		$key = $map[ $post_type ] ?? 'weight_post';
		return (float) ( $settings[ $key ] ?? 1.0 );
	}

	/**
	 * Recalculate effective weight for a post (individual weight or default).
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return float
	 */
	public static function get_effective_weight( int $post_id, string $post_type ): float {
		$individual = get_post_meta( $post_id, '_convoca_assistant_weight', true );
		if ( ! empty( $individual ) && (float) $individual > 0 ) {
			return (float) $individual;
		}
		return self::get_default_weight( $post_type );
	}
}
