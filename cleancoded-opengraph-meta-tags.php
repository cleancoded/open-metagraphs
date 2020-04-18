<?php
/*
Plugin Name:       Cleancoded Open Graph Meta Tags
Description:       Displays meta information for the open graph protocol on various parts of a WordPress installation.
Version:           1.0
Author:            Cleancoded
Author URI:        http://cleancoded.com/
*/

Cleancoded_Open_Graph_Meta_Tags::init();

/**
 * Cleancoded Open Graph Meta Tags
 *
 * @package      Cleancoded_Open_Graph_Meta_Tags
 * @author       Cleancoded
 */
class Cleancoded_Open_Graph_Meta_Tags {

	/**
	 * Hook into WordPress.
	 *
	 * @since 0.1
	 */
	public static function init() {
		add_action( 'wp_head', array( __class__, 'print_meta' ) );
	}

	/**
	 * Print the meta tags to the head of the html document.
	 *
	 * Hooked into the "wp_head" action.
	 *
	 * @since 0.1
	 */
	public static function print_meta() {
		$data = self::get_meta();
		foreach ( (array) $data as $key => $value ) {
			if ( empty( $value ) )
				continue;

			/* Append prefix to all keys. */
			$property = self::get_prefix( $key ) . $key;

			/* Determine the appropriate escaping function to use. */
			$esc = 'esc_attr';
			if ( 'url' == $key )
				$esc = 'esc_url';

			/* Print the meta tag. */
			print '<meta property="' . esc_attr( $property ) . '" content="' . call_user_func( $esc, $value ) . '">' . "\n";
		}
	}

	/**
	 * Get meta property prefix.
	 *
	 * @since 0.2
	 */
	public static function get_prefix( $key ) {
		$prefix = 'og:';
		if ( in_array( $key, array( 'admins', 'app_id' ) ) )
			$prefix = 'fb:';

		return $prefix;
	}

	/**
	 * Get meta key and value pairs for the current query.
	 *
	 * @return array Associative. Keys represent unprefixed meta properties. Values represent unescaped meta content.
	 * @since 0.1
	 */
	public static function get_meta() {
		$output = array();
		if ( is_home() )
			$output = self::get_meta_home();
		else if ( is_singular() || is_front_page() )
			$output = self::get_meta_singular();
		else if ( is_category() || is_tag() || is_tax() )
			$output = self::get_meta_term();
		else if ( is_author() )
			$output = self::get_meta_author();

		$output = wp_parse_args( $output, array(
			'admins'      => '',
			'description' => '',
			'image'       => '',
			'site_name'   => get_bloginfo(),
			'title'       => '',
			'type'        => 'article',
			'url'         => '',
			'locale'      => '',
			) );

		$locale = get_locale();
		if ( 'en_US' != get_locale() )
			$output['locale'] = $locale;

		$output['description'] = wp_strip_all_tags( $output['description'] );

		$output = apply_filters( 'Cleancoded_open_graph_meta_tags', $output );

		return $output;
	}

	/**
	 * Get homepage specific meta key and value pairs.
	 *
	 * @return array Associative. Keys represent unprefixed meta properties. Values represent unescaped meta content.
	 * @since 0.1
	 */
	private static function get_meta_home() {
		$output = array(
			'description' => get_bloginfo( 'description' ),
			'title'       => get_bloginfo(),
			'type'        => 'website',
			'url'         => site_url(),
		);

		return apply_filters( 'Cleancoded_open_graph_meta_tags_home', $output );
	}

	/**
	 * Get author specific meta key and value pairs.
	 *
	 * @return array Associative. Keys represent unprefixed meta properties. Values represent unescaped meta content.
	 * @since 0.1
	 */
	private static function get_meta_author() {
		global $wp_query;

		$output = array(
			'type' => 'author'
		);

		$author = $wp_query->get_queried_object();
		if ( isset( $author->description ) )
			$output['description'] = $author->description;

		if ( isset( $author->user_email ) && class_exists( 'DOMDocument' ) ) {
			$html = get_avatar( $author->user_email, 50 );
			$img = new DOMDocument();
			$img->loadHTML( $html );
			$img_tags = $img->getElementsByTagName( 'img' );
			if ( 1 == $img_tags->length ) {
				foreach ( (array) $img_tags as $tag ) {
					$output['image'] = $tag->getAttribute( 'src' );
				}
			}
		}

		if ( isset( $author->display_name ) )
			$output['title'] = $author->display_name;

		if ( isset( $author->ID ) )
			$output['url'] = get_author_posts_url( $author->ID );

		$output = apply_filters( 'Cleancoded_open_graph_meta_tags_author', $output, $author );
		if ( isset( $author->ID ) )
			$output = apply_filters( "Cleancoded_open_graph_meta_tags_author_{$author->ID}", $output, $author );

		return $output;
	}

	/**
	 * Get term specific meta key and value pairs.
	 *
	 * @return array Associative. Keys represent unprefixed meta properties. Values represent unescaped meta content.
	 * @since 0.1
	 */
	private static function get_meta_term() {
		global $wp_query;

		$output = array();

		$term = $wp_query->get_queried_object();

		if ( isset( $term->name ) )
			$output['title'] = $term->name;

		if ( isset( $term->description ) )
			$output['description'] = $term->description;

		if ( isset( $term->term_id ) ) {
			if ( isset( $term->taxonomy ) ) {
				$url = get_term_link( $term, $term->taxonomy );
				if ( ! is_wp_error( $url ) )
					$output['url'] = $url;
			}
			if ( function_exists( 'taxonomy_images_plugin_get_archive_image_src' ) ) {
				$image = taxonomy_images_plugin_get_archive_image_src( $term->term_id );
				if ( ! empty( $image ) )
					$output['image'] = $image;
			}
		}

		$output = apply_filters( 'Cleancoded_open_graph_meta_tags_term', $output, $term );
		if ( isset( $term->taxonomy ) )
			$output = apply_filters( "Cleancoded_open_graph_meta_tags_term_{$term->taxonomy}", $output, $term );

		return $output;
	}

	/**
	 * Get post specific meta key and value pairs.
	 *
	 * @return array Associative. Keys represent unprefixed meta properties. Values represent unescaped meta content.
	 * @since 0.1
	 */
	private static function get_meta_singular() {
		global $post;
		setup_postdata( $post );

		$post_type = get_post_type();

		$output = array();
		
		$permalink = esc_url( get_permalink() );
		if ( empty( $permalink ) )
			return;

		$output['url'] = $permalink;

		/* Use the title only if this post_type supports it. */
		if ( post_type_supports( $post_type, 'title' ) ) {
			$title = apply_filters( 'the_title', get_the_title() );
			if ( ! empty( $title ) )
				$output['title'] = $title;
		}

		/* Use the excerpt only if this post_type supports it. */
		if ( post_type_supports( $post_type, 'excerpt' ) || post_type_supports( $post_type, 'editor' ) ) {
			$excerpt = apply_filters( 'the_excerpt', get_the_excerpt() );
			if ( ! empty( $excerpt ) )
				$output['description'] = $excerpt;
		}

		if ( post_type_supports( $post_type, 'thumbnail' ) ) {
			$post_thumbnail_id = absint( apply_filters( 'Cleancoded_open_graph_meta_tags_default_image_id', 0 ) );
			if ( has_post_thumbnail() )
				$post_thumbnail_id = get_post_thumbnail_id();

			if ( is_attachment() )
				$post_thumbnail_id = get_the_id();

			if ( ! empty( $post_thumbnail_id ) ) {
				$post_thumbnail = wp_get_attachment_image_src( $post_thumbnail_id, 'medium' );
				if ( isset( $post_thumbnail[0] ) ) {
					$output['image'] = $post_thumbnail[0];
				}
			}
		}

		wp_reset_postdata();

		$output = apply_filters( 'Cleancoded_open_graph_meta_tags_singular', $output, $post, $post_type );
		$output = apply_filters( "Cleancoded_open_graph_meta_tags_singular_{$post_type}", $output, $post, $post_type );

		return $output;
	}
}