<?php

class s24wp {

	/**
	 * Prefix for the autenerated HTML field IDs.
	 *
	 * @var string
	 */
	private static $id_prefix = 'wpw-select2-';

	/**
	 * Number of UI controls generated. Used to ensure unique HTML element IDs.
	 *
	 * @var int
	 */
	private static $id_counter = 0;

	/**
	 * True once the scripts were queued. We only want to include them once.
	 *
	 * @var bool
	 */
	private static $scripts_queued = false;

	/**
	 * Full URL to the root folder of the library. This depends on a plugin or theme where the library is used.
	 *
	 * @var string
	 */
	private static $base_url;

	/**
	 * Initializes the library with given base URL.
	 *
	 * @param string $lib_base_url URL pointing to the root of the library.
	 */
	public static function init( $lib_base_url ) {
		self::$base_url = $lib_base_url;

		if ( ! has_action( 'wp_ajax_wpws_s24wp', array( __CLASS__, 'handle_ajax_call' ) ) ) {
			add_action( 'wp_ajax_wpws_s24wp', array( __CLASS__, 'handle_ajax_call' ) );
		}
	}

	/**
	 * Handles AJAX requests from the autocomplete controls.
	 */
	public static function handle_ajax_call() {

		// TODO verify nonce

		// Check the 'entity' parameter.
		if ( ! array_key_exists( 'entity', $_REQUEST ) ) {
			wp_send_json_error( 'Data type not defined.' );
		}

		if ( ! array_key_exists( 'term', $_REQUEST ) ) {
			wp_send_json_error( 'Search term is missing.' );
		}

		$result      = array();
		$entity      = wp_unslash( trim( $_REQUEST['entity'] ) );
		$search_term = wp_unslash( trim( $_REQUEST['term'] ) );
		switch ( $entity ) {
			case 'user':
				$result = self::get_users( $search_term );
				break;
			case 'post':
				$result = self::get_posts( $search_term );
				break;
			default:
				wp_send_json_error( 'Unsupported data type.' );
		}

		echo wp_json_encode(
			array(
				'results' => $result,
			)
		);
		die();
	}

	/**
	 * Get the user id through ajax, used in 'select2'.
	 *
	 * @param string $search_term Search term.
	 *
	 * @return array
	 */
	public function get_users( $search_term ) {
		$result = array();
		$users  = get_users(
			array(
				'search'         => '*' . $search_term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
			)
		);

		if ( empty( $users ) ) {
			return $result;
		}

		return array_map(
			function ( $user ) {
				return array(
					'id'   => $user->ID,
					'text' => $user->user_login,
				);
			},
			$users
		);
	}

	/**
	 * Handles AJAX calls to retrieve post data to be used in 'select2'.
	 *
	 * @param string $search_term Search term.
	 *
	 * @return array
	 */
	public function get_posts( $search_term ) {
		$result = array();

		$args = array(
			'search_post_title' => $search_term, // Search post title only.
			'suppress_filters'  => false,
			'post_status'       => 'publish',
		);
		add_filter( 'posts_where', array( __CLASS__, 'search_post_title' ), 10, 2 );
		$posts = get_posts( $args );
		remove_filter( 'posts_where', array( __CLASS__, 'search_post_title' ), 10 );

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$post_title = $post->post_title;
				if ( strlen( $post_title ) > 50 ) {
					$post_title = substr( $post->post_title, 0, 50 ) . '...';
				}
				array_push(
					$result,
					array(
						'id'   => $post->ID,
						'text' => $post->ID . ' - ' . $post_title,
					)
				);
			}
		}


		return $result;
	}

	/**
	 * Renders all the HTML, CSS and JS code necesary to display select2 form control configure using given parameters.
	 *
	 * @param array $args Select form control parameters.
	 */
	public static function insert( $args ) {

		// name - string
		// data-type - user, role, post
		// placeholder - string
		// width - int (pixels)
		// id - string
		// multiple - bool
		// min_chars - int (minimum number of characters to type before searching)

		if ( ! isset( self::$base_url ) ) {
			// Library not initialized correctly.
			return;
		}

		if ( ! array_key_exists( 'name', $args ) ) {
			// Field name is missing.
			return;
		}

		if ( ! array_key_exists( 'data-type', $args ) && ! array_key_exists( 'data', $args ) ) {
			// No data source defined.
			return;
		}

		// Enqueue scripts.
		if ( ! self::$scripts_queued ) {
			self::enqueue_scripts();
		}

		$attributes = array(
			'name' => $args['name'],
		);

		if ( ! array_key_exists( 'id', $args ) ) {
			$args['id'] = self::$id_prefix . self::$id_counter ++;
		}

		$attributes['id'] = $args['id'];
		if ( array_key_exists( 'placeholder', $args ) ) {
			$attributes['placeholder'] = $args['placeholder'];
		}

		if ( array_key_exists( 'width', $args ) ) {
			$attributes['style'] = 'width: ' . $args['width'] . 'px;';
		}

		array_walk(
			$attributes,
			function ( &$value, $key ) {
				$value = $key . '="' . esc_attr( $value ) . '"';
			}
		);

		echo '<select ' . implode( ' ', $attributes ) . '></select>';
		echo '<script type="application/javascript">';
		echo 'jQuery( document ).ready( function() {';
		echo 'jQuery( "#' . $args['id'] . '" ).select2( {';
		echo 'placeholder: "' . $args['placeholder'] . '",';

		if ( array_key_exists( 'multiple', $args ) && true === $args['multiple'] ) {
			echo 'multiple: true,';
		}

		if ( array_key_exists( 'width', $args ) ) {
			echo 'width: "' . $args['width'] . 'px",';
		}

		if ( array_key_exists( 'data', $args ) ) {
			echo 'data: ' . json_encode( $args['data'] );
		}

		if ( array_key_exists( 'data-type', $args ) ) {

			$url = admin_url( 'admin-ajax.php' ) . '?action=wpws_s24wp&entity=' . $args['data-type'];

			$min_chars = array_key_exists( 'min_chars', $args ) ? intval( $args['min_chars'] ) : 3;
			echo 'minimumInputLength: "' . $min_chars . '",';
			echo 'ajax: {';
			echo 'url : "' . $url . '",';
			echo 'dataType: "json"';
			echo '}';

		}

		echo '} );';
		echo '} );';
		echo '</script>';
	}

	private static function enqueue_scripts() {
		if ( self::$scripts_queued ) {
			return;
		}

		wp_enqueue_style(
			'wpw-select2',
			self::$base_url . '/assets/css/select2.min.css',
			array(),
			'4.0.13'
		);

		wp_enqueue_script(
			'wpw-select2',
			self::$base_url . '/assets/js/select2.min.js',
			array( 'jquery' ),
			'4.0.13',
			true
		);

		self::$scripts_queued = true;
	}

	/**
	 * Alters WordPress query to search only by post title.
	 *
	 * @param string   $where    SQL WHERE statement.
	 * @param WP_Query $wp_query WordPress query object.
	 *
	 * @return string
	 */
	public static function search_post_title( $where, &$wp_query ) {
		$search_term = $wp_query->get( 'search_post_title' );
		if ( $search_term ) {
			global $wpdb;
			$where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . $wpdb->esc_like( $search_term ) . '%\'';
		}

		return $where;
	}
}
