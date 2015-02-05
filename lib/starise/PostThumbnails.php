<?php

namespace starise;

class PostThumbnails
{
	const LANG_DOMAIN = 'post-thumbnails';

	public function __construct($args = [])
	{
		$this->load_language( self::LANG_DOMAIN );
		$this->register( $args );
	}

	/**
	 * Register a new post thumbnail.
	 *
	 * Required $args contents:
	 *
	 * label - The name of the post thumbnail to display in the admin metabox
	 * id - Used to build the CSS class for the admin meta box. Needs to be unique and valid in a CSS class selector.
	 *
	 * Optional $args contents:
	 *
	 * post_type - Array of post types to register this thumbnail for. Defaults to post.
	 * priority - The admin metabox priority. Defaults to 'low'.
	 * context - The admin metabox context. Defaults to 'side'.
	 *
	 * @param array|string $args See above description.
	 * @return void
	 */
	public function register($args = [])
	{
		global $wp_version;

		$defaults = [
			'label' => null,
			'id' => null,
			'post_type' => ['post'],
			'priority' => 'low',
			'context' => 'side',
		];

		$args = wp_parse_args($args, $defaults);

		// Create and set properties
		foreach($args as $key => $value) {
			$this->$key = $value;
		}

		// Need these args to be set at a minimum
		if (null === $this->label || null === $this->id) {
			if (WP_DEBUG) {
				trigger_error(sprintf(__("The 'label' and 'id' values of the 'args' parameter of '%s::%s()' are required", self::LANG_DOMAIN), __CLASS__, __FUNCTION__));
			}
			return;
		}

		// add theme support if not already added
		if (!current_theme_supports('post-thumbnails')) {
			add_theme_support( 'post-thumbnails' );
		}

		add_action('add_meta_boxes', [$this, 'add_metabox']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		add_action('admin_print_scripts-post.php', [$this, 'admin_header_scripts']);
		add_action('admin_print_scripts-post-new.php', [$this, 'admin_header_scripts']);
		add_action("wp_ajax_set-{$this->id}-thumbnail", [$this, 'set_thumbnail']);
		add_action('delete_attachment', [$this, 'action_delete_attachment']);
		add_filter('is_protected_meta', [$this, 'filter_is_protected_meta'], 20, 2);
	}

	/**
	 * Get the meta key used to store a post's thumbnail
	 *
	 * @return string
	 */
	public function get_meta_key()
	{
		return "_{$this->id}_thumbnail_id";
	}

	/**
	 * Add admin metabox for thumbnail chooser
	 *
	 * @return void
	 */
	public function add_metabox()
	{
		foreach ($this->post_type as $post_type) {
			add_meta_box("{$this->id}", __($this->label, self::LANG_DOMAIN), [$this, 'thumbnail_meta_box'], $post_type, $this->context, $this->priority);
		}
	}

	/**
	 * Output the thumbnail meta box
	 *
	 * @return string HTML output
	 */
	public function thumbnail_meta_box()
	{
		global $post;

		$thumbnail_id = get_post_meta($post->ID, $this->get_meta_key(), true);
		echo $this->post_thumbnail_html($thumbnail_id);
	}

	/**
	 * Throw this in the media attachment fields
	 *
	 * @param string $form_fields
	 * @param string $post
	 * @return void
	 */
	public function add_attachment_field($form_fields, $post)
	{
		$calling_post_id = 0;
		if (isset($_GET['post_id']))
			$calling_post_id = absint($_GET['post_id']);
		elseif (isset($_POST) && count($_POST)) // Like for async-upload where $_GET['post_id'] isn't set
			$calling_post_id = $post->post_parent;

		if (!$calling_post_id)
			return $form_fields;

		// check the post type to see if link needs to be added
		$calling_post = get_post($calling_post_id);
		if (is_null($calling_post) || $calling_post->post_type != $this->post_type) {
			return $form_fields;
		}

		$referer = wp_get_referer();
		$query_vars = wp_parse_args(parse_url($referer, PHP_URL_QUERY));

		if( (isset($_REQUEST['context']) && $_REQUEST['context'] != $this->id) || (isset($query_vars['context']) && $query_vars['context'] != $this->id) )
			return $form_fields;

		$ajax_nonce = wp_create_nonce("set_post_thumbnail-{$this->id}-{$calling_post_id}");
		$link = sprintf('<a id="%1$s-thumbnail-%2$s" class="%1$s-thumbnail" href="#" onclick="PostThumbnails.setAsThumbnail(\'%2$s\', \'%1$s\', \'%4$s\');return false;">' . __( 'Set as %3$s', self::LANG_DOMAIN ) . '</a>', $this->id, $post->ID, $this->label, $ajax_nonce);
		$form_fields["{$this->id}-thumbnail"] = [
			'label' => $this->label,
			'input' => 'html',
			'html' => $link
		];
		return $form_fields;
	}

	/**
	 * Enqueue admin JavaScripts
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook )
	{
		global $wp_version, $post_ID;

		// only load on select pages
		if ( ! in_array( $hook, ['post-new.php', 'post.php', 'media-upload-popup'] ) )
			return;

		wp_enqueue_media( [ 'post' => ( $post_ID ? $post_ID : null ) ] );
		wp_enqueue_script( "pt-featured-image", PT_URL . 'js' . DIRECTORY_SEPARATOR . 'post-thumbnails-admin.js', [ 'jquery', 'set-post-thumbnail' ] );
		wp_enqueue_script( "pt-featured-image-modal", PT_URL . 'js' . DIRECTORY_SEPARATOR . 'media-modal.js', [ 'jquery', 'media-models' ] );
		wp_enqueue_style( "pt-admin-css", PT_URL . 'css' . DIRECTORY_SEPARATOR . 'post-thumbnails-admin.css' );
	}

	public function admin_header_scripts()
	{
		$post_id = get_the_ID();
		echo "<script>var post_id = $post_id;</script>";
	}

	/**
	 * Deletes the post meta data for posts when an attachment used as a
	 * multiple post thumbnail is deleted from the Media Libray
	 *
	 * @global object $wpdb
	 * @param int     $post_id
	 */
	public function action_delete_attachment($post_id)
	{
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = '%s' AND meta_value = %d", $this->get_meta_key(), $post_id ));
	}

	/**
	 * Unprotect post thumbnails so they can be shown in Custom Fields metabox
	 * Filter example: add_filter('pt_unprotect_meta', '__return_true');
	 *
	 * @param boolean $protected Passed in from filter
	 * @param type    $meta_key  Passed in from filter
	 * @return boolean
	 */
	public function filter_is_protected_meta($protected, $meta_key)
	{
		if ($meta_key == $this->get_meta_key() && apply_filters('pt_unprotect_meta', false)) {
			$protected = false;
		}

		return $protected;
	}

	/**
	 * Check if post has an image attached.
	 *
	 * @param  string $thumb_id The id used to register the thumbnail.
	 * @param  string $post_id  Optional. Post ID.
	 * @return bool Whether post has an image attached.
	 */
	public static function has_post_thumbnail($thumb_id, $post_id = null)
	{
		if (null === $post_id) {
			$post_id = get_the_ID();
		}

		if (!$post_id) {
			return false;
		}

		return get_post_meta($post_id, "_{$thumb_id}_thumbnail_id", true);
	}

	/**
	 * Display Post Thumbnail.
	 *
	 * @param string $thumb_id The id used to register the thumbnail.
	 * @param int    $post_id  Optional. Post ID.
	 * @param string $size     Optional. Image size.  Defaults to 'post-thumbnail'.
	 * @param mixed  $attr     Optional. Query string or array of attributes.
	 * @param bool   $link     Optional. Wrap link to original image around thumbnail?
	 */
	public static function the_post_thumbnail($thumb_id, $post_id = null, $size = 'post-thumbnail', $attr = '', $link = false)
	{
		echo self::get_the_post_thumbnail($thumb_id, $post_id, $size, $attr, $link);
	}

	/**
	 * Returns an HTML element representing the Post Thumbnail.
	 *
	 * @param string $thumb_id The id used to register the thumbnail.
	 * @param int    $post_id  Optional. Post ID.
	 * @param string $size     Optional. Image size.  Defaults to 'thumbnail'.
	 * @param bool   $link     Optional. Wrap link to original image around thumbnail?
	 * @param mixed  $attr     Optional. Query string or array of attributes.
	  */
	public static function get_the_post_thumbnail($thumb_id, $post_id = NULL, $size = 'post-thumbnail', $attr = '' , $link = false)
	{
		$post_id = (NULL === $post_id) ? get_the_ID() : $post_id;
		$post_thumbnail_id = self::get_post_thumbnail_id($thumb_id, $post_id);
		$size = apply_filters("_{$thumb_id}_thumbnail_size", $size);
		if ($post_thumbnail_id) {
			do_action("begin_fetch_thumbnail_html", $post_id, $post_thumbnail_id, $size); // for "Just In Time" filtering of all of wp_get_attachment_image()'s filters
			$html = wp_get_attachment_image( $post_thumbnail_id, $size, false, $attr );
			do_action("end_fetch_thumbnail_html", $post_id, $post_thumbnail_id, $size);
		} else {
			$html = '';
		}

		if ($link && $html) {
			$html = sprintf('<a href="%s">%s</a>', wp_get_attachment_url($post_thumbnail_id), $html);
		}

		return apply_filters("_{$thumb_id}_thumbnail_html", $html, $post_id, $post_thumbnail_id, $size, $attr);
	}

	/**
	 * Retrieve Post Thumbnail ID.
	 *
	 * @param string $thumb_id The id used to register the thumbnail.
	 * @param int    $post_id  Post ID.
	 * @return int
	 */
	public static function get_post_thumbnail_id($thumb_id, $post_id)
	{
		return get_post_meta($post_id, "_{$thumb_id}_thumbnail_id", true);
	}

	/**
	 * Get the URL of the thumbnail.
	 *
	 * @param string $thumb_id The id used to register the thumbnail.
	 * @param int    $post_id  Optional. The post ID. If not set, will attempt to get it.
	 * @param string $size     Optional. The thumbnail size to use. If set, use wp_get_attachment_image_src() instead of wp_get_attachment_url()
	 * @return mixed Thumbnail url or false if the post doesn't have a thumbnail for the given ID.
	 */
	public static function get_post_thumbnail_url($thumb_id, $post_id = 0, $size = null)
	{
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$post_thumbnail_id = self::get_post_thumbnail_id($thumb_id, $post_id);

		if ($size) {
			if ($url = wp_get_attachment_image_src($post_thumbnail_id, $size)) {
				$url = $url[0];
			} else {
				$url = '';
			}
		} else {
			$url = wp_get_attachment_url($post_thumbnail_id);
		}

		return $url;
	}

	/**
	 * Output the post thumbnail HTML for the metabox and AJAX callbacks
	 *
	 * @param string $thumbnail_id The thumbnail's post ID.
	 * @return string HTML
	 */
	private function post_thumbnail_html($thumbnail_id = null)
	{
		global $content_width, $_wp_additional_image_sizes, $post_ID, $wp_version;

		$url_class = "";
		$ajax_nonce = wp_create_nonce("set_post_thumbnail-{$this->id}-{$post_ID}");

		$image_library_url = "#";
		$modal_js = sprintf(
			'var mm_%2$s = new MediaModal({
				calling_selector : "#set-%1$s-thumbnail",
				cb : function(attachment){
					PostThumbnails.setAsThumbnail(attachment.id, "%1$s", "%3$s");
				}
			});',
			$this->id, md5($this->id), $ajax_nonce
		);
		$format_string = '<p class="hide-if-no-js"><a title="%1$s" href="%2$s" id="set-%3$s-thumbnail" class="%4$s" data-thumbnail_id="%6$s" data-uploader_title="%1$s" data-uploader_button_text="%1$s">%%s</a></p>';
		$set_thumbnail_link = sprintf( $format_string, sprintf( esc_attr__( "Set %s" , self::LANG_DOMAIN ), $this->label ), $image_library_url, $this->id, $url_class, $this->label, $thumbnail_id );
		$content = sprintf( $set_thumbnail_link, sprintf( esc_html__( "Set %s", self::LANG_DOMAIN ), $this->label ) );

		if ($thumbnail_id && get_post($thumbnail_id)) {
			$old_content_width = $content_width;
			$content_width = 266;
			$attr = [ 'class' => 'post-thumbnails' ];

			if ( !isset($_wp_additional_image_sizes["{$this->id}-thumbnail"])) {
					$thumbnail_html = wp_get_attachment_image( $thumbnail_id, [$content_width, $content_width], false, $attr );
			} else {
					$thumbnail_html = wp_get_attachment_image( $thumbnail_id, "{$this->id}-thumbnail", false, $attr );
			}

			if (!empty($thumbnail_html)) {
				$content = sprintf($set_thumbnail_link, $thumbnail_html);
				$format_string = '<p class="hide-if-no-js"><a href="#" id="remove-%1$s-thumbnail" onclick="PostThumbnails.removeThumbnail(\'%1$s\', \'%3$s\');return false;">%2$s</a></p>';
				$content .= sprintf( $format_string, $this->id, sprintf( esc_html__( "Remove %s", self::LANG_DOMAIN ), $this->label ), $ajax_nonce );
			}
			$content_width = $old_content_width;
		}

		$content .= sprintf('<script>%s</script>', $modal_js);

		return apply_filters( sprintf( '%s_admin_post_thumbnail_html', $this->id ), $content, $post_ID, $thumbnail_id );
	}

	/**
	 * Set/remove the post thumbnail. AJAX handler.
	 *
	 * @return string Updated post thumbnail HTML.
	 */
	public function set_thumbnail()
	{
		global $post_ID; // have to do this so get_upload_iframe_src() can grab it
		$post_ID = intval($_POST['post_id']);
		if ( !current_user_can('edit_post', $post_ID))
			die('-1');
		$thumbnail_id = intval($_POST['thumbnail_id']);

		check_ajax_referer("set_post_thumbnail-{$this->id}-{$post_ID}");

		if ($thumbnail_id == '-1') {
			delete_post_meta($post_ID, $this->get_meta_key());
			die($this->post_thumbnail_html(null));
		}

		if ($thumbnail_id && get_post($thumbnail_id)) {
			$thumbnail_html = wp_get_attachment_image($thumbnail_id, 'thumbnail');
			if (!empty($thumbnail_html)) {
				$this->set_meta($post_ID, $this->id, $thumbnail_id);
				die($this->post_thumbnail_html($thumbnail_id));
			}
		}

		die('0');
	}

	/**
	 * Set thumbnail meta
	 *
	 * @param int    $post_ID
	 * @param string $thumb_id      ID used to register the thumbnail
	 * @param int    $thumb_post_id ID of the attachment to use as the thumbnail
	 * @return bool  Result of update_post_meta
	 */
	public static function set_meta($post_ID, $thumb_id, $thumb_post_id)
	{
		return update_post_meta($post_ID, "_{$thumb_id}_thumbnail_id", $thumb_post_id);
	}

	/**
	 * Loads translation file.
	 *
	 * @param  string $domain
	 * @return void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain( $domain, FALSE, PT_PATH . 'lang' );
	}
}
