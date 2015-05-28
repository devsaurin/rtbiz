<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of class-rt-entity
 *
 * @author udit
 */
if ( ! class_exists( 'Rt_Biz_Entity' ) ) {

	/**
	 * Class Rt_Biz_Entity
	 *
	 * An abstract class for Rt_Biz_Contact & Rt_Biz_Company - Core Modules of rtBiz.
	 * This will handle most of the functionalities of these two entities.
	 *
	 * If at all any individual entity wants to change the behavior for itself
	 * then it will override that particular method in the its child class
	 */
	abstract class Rt_Biz_Entity {

		/**
		 * @var - Entity Core Post Type (Organization / Person)
		 */
		public $post_type;

		/**
		 * @var - Post Type Labels (Organization / Person)
		 */
		public $labels;

		/**
		 * @var array - Meta Fields Keys for Entity (Organzation / Person)
		 */
		public $meta_fields = array();

		/**
		 * @var string - Meta Key Prefix
		 */
		public static $meta_key_prefix = 'rt_biz_';

		/**
		 * @param $post_type
		 */
		public function __construct( $post_type ) {
			$this->post_type = $post_type;
			$this->rt_biz_hooks();
		}

		/**
		 *  Register Rt_Biz_Entity Core Post Type
		 */
		public function rt_biz_init_entity() {
			$this->rt_biz_register_post_type( $this->post_type, $this->labels );
		}

		/**
		 * @param $name
		 * @param array $labels
		 */
		private function rt_biz_register_post_type( $name, $labels = array() ) {
			$args = apply_filters( 'rt_entity_register_post_type_args', array(
				'labels' => $labels,
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true, // Show the UI in admin panel
				'show_in_nav_menus' => false,
				'show_in_menu' => Rt_Biz_Dashboard::$page_slug,
				'show_in_admin_bar' => false,
				'supports' => array( 'title', 'comments', 'thumbnail' ),
				'capability_type' => $name,
				'map_meta_cap' => true, //Required For ACL Without map_meta_cap Cap ACL isn't working.
				//Default WordPress check post capability on admin page so we need to map custom post type capability with post capability.
			), $name );
			register_post_type( $name, $args );
		}

		/**
		 *  Actions/Filtes used by Rt_Biz_Entity
		 */
		private function rt_biz_hooks() {

			if ( is_admin() ) {
				Rt_Biz::$loader->add_action( 'manage_' . $this->post_type . '_posts_columns', $this, 'rt_biz_post_table_columns' );
				Rt_Biz::$loader->add_action( 'manage_' . $this->post_type . '_posts_custom_column', $this, 'rt_biz_manage_post_table_columns', 10, 2 );
				Rt_Biz::$loader->add_action( 'manage_edit-' . $this->post_type, $this, 'rt_biz_rearrange_columns', 20 );

				Rt_Biz::$loader->add_action( 'add_meta_boxes', $this, 'rt_biz_entity_meta_boxes' );
				Rt_Biz::$loader->add_action( 'admin_init', $this, 'rt_biz_entity_meta_boxes' );
				Rt_Biz::$loader->add_action( 'add_meta_boxes', $this, 'rt_biz_remove_metabox' );
				Rt_Biz::$loader->add_action( 'save_post', $this, 'rt_biz_save_entity_details', 10, 2 );
				Rt_Biz::$loader->add_action( 'pre_post_update', $this, 'rt_biz_save_old_data' );

				/* add_filter( 'gettext', array( $this, 'change_publish_button' ), 10, 2 ); */

				Rt_Biz::$loader->add_action( 'bulk_post_updated_messages', $this, 'rt_biz_bulk_entity_update_messages', 10, 2 );
				Rt_Biz::$loader->add_action( 'post_updated_messages', $this, 'rt_biz_entity_updated_messages', 10, 2 );

			}

			Rt_Biz::$loader->add_action( 'comment_feed_where', $this, 'rt_biz_skip_feed_comments' );
			Rt_Biz::$loader->add_action( 'pre_get_comments', $this, 'rt_biz_preprocess_comment_handler' );

			do_action( 'rt_biz_entity_hooks', $this );
		}

		/**
		 *
		 * Overridden in Child Classes
		 *
		 * @param $columns
		 * @return mixed|void
		 */
		public function rt_biz_post_table_columns( $columns ) {
			return apply_filters( 'rt_entity_columns', $columns, $this );
		}

		/**
		 *
		 * Overridden in Child Classes
		 *
		 * @param $column
		 * @param $post_id
		 */
		public function rt_biz_manage_post_table_columns( $column, $post_id ) {
			do_action( 'rt_entity_manage_columns', $column, $post_id, $this );
		}

		/**
		 * Overridden in Child Classes
		 * @param $columns
		 */
		public function rt_biz_rearrange_columns( $columns ) {
			return apply_filters( 'rt_entity_rearrange_columns', $columns, $this );
		}

		/**
		 * Registers Meta Box for Rt_Biz_Entity Meta Fields - Additional Information for Rt_Biz_Entity
		 */
		public function rt_biz_entity_meta_boxes() {
			add_meta_box( 'rt-biz-entity-details', __( 'Additional Details' ), 'Rt_Biz_Entity_Additional_Detail::ui', $this->post_type, 'normal', 'default' );
			add_meta_box( 'rt-biz-entity-assigned_to', __( 'Assigned To' ), 'Rt_Biz_Entity_Assignee::ui', $this->post_type, 'side', 'default' );
			do_action( 'rt_biz_entity_meta_boxes-' . $this->post_type , $this->post_type );
		}

		/**
		 * remove metabox of contact
		 */
		public function rt_biz_remove_metabox() {
			$metabox_ids = apply_filters( 'rt_entity_remove_meta_box', array( 'commentstatusdiv' ) );
			foreach ( $metabox_ids as $metabox_id ) {
				remove_meta_box( $metabox_id[0], $this->post_type, $metabox_id[1] );
			}
		}

		/**
		 *
		 * Saves Additional Info from MetaBox
		 *
		 * @param $post_id
		 */
		public function rt_biz_save_entity_details( $post_id, $post ) {
			/*
			 * We need to verify this came from the our screen and with proper authorization,
			 * because save_post can be triggered at other times.
			 */

			// Check if our nonce is set.
			if ( ! isset( $_POST['rt_biz_additional_details_metabox_nonce'] ) ) {
				return;
			}

			$nonce = $_POST['rt_biz_additional_details_metabox_nonce'];

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( $nonce, 'rt_biz_additional_details_metabox' ) ) {
				return;
			}

			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			/* OK, its safe for us to save the data now. */
			Rt_Biz_Entity_Assignee::save( $post_id, $post );
			$this->rt_biz_save_meta_values( $post_id, $post );
		}

		/**
		 *
		 * Overridden in Child Classes
		 *
		 * @param $post_id
		 */
		protected function rt_biz_save_meta_values( $post_id, $post ) {
			do_action( 'rt_biz_save_entity_meta', $post_id, $post, $this );
			do_action( 'rt_biz_save_entity_meta-' . $this->post_type , $post_id, $post, $this );
		}

		public function rt_biz_save_old_data( $post_id ) {
			if ( ! isset( $_POST['post_type'] ) ) {
				return;
			}
			if ( $this->post_type != $_POST['post_type'] ) {
				return;
			}
			$body = '';
			$flag = false;
			$post = get_post( $post_id );
			if ( $_POST['post_title'] != $post->post_title ) {
				$body = '<strong>' . __( 'Contact Title' ) . '</strong> : ';
				$body .= rt_biz_text_diff( $post->post_title, $_POST['post_title'] );
			}

			if ( isset( $_POST['tax_input'] ) ) {
				foreach ( $_POST['tax_input'] as $key => $val ) {
					$tmp = rt_biz_get_tex_diff( $post_id, $key );
					if ( '' != $tmp ) {
						$body .= $tmp;
						$flag = true;
					}
				}
			}

			$meta_key = '';
			switch ( $_POST['post_type'] ) {
				case rt_biz_get_contact_post_type():
					$meta_key = 'contact_meta';
					break;
				case rt_biz_get_company_post_type():
					$meta_key = 'company_meta';
					break;
			}

			foreach ( $this->meta_fields as $field ) {
				if ( ! isset( $_POST[ $meta_key ][ $field['key'] ] ) ) {
					continue;
				}

				if ( 'contact_primary_email' == $field['key'] ) {
					if ( ! rt_biz_is_primary_email_unique( $_POST['contact_meta'][ $field['key'] ] ) ) {
						continue;
					}
				}

				if ( Rt_Biz_Company::$primary_email == $field['key'] ) {
					if ( ! rt_biz_is_primary_email_unique_company( $_POST['company_meta'][ $field['key'] ] ) ) {
						continue;
					}
				}

				if ( 'true' == $field['is_multiple'] ) {
					$val = self::get_meta( $post_id, $field['key'] );
					$filerval = array_filter( $val );
					$filerpost = array_filter( $_POST[ $meta_key ][ $field['key'] ] );
					$diff = array_diff( $filerval, $filerpost );
					$diff2 = array_diff( $filerpost, $filerval );
					$difftxt = rt_biz_text_diff( implode( ' ', $diff ), implode( ' ', $diff2 ) );
					if ( ! empty( $difftxt ) || '' != $difftxt ) {
						$skip_enter = str_replace( 'Enter', '', $field['label'] );
						$body .= "<strong>{ $skip_enter }</strong> : " . $difftxt;
						$flag = true;
					}
				} else {
					$val = self::get_meta( $post_id, $field['key'], true );
					$newval = $_POST[ $meta_key ][ $field['key'] ];
					if ( $val != $newval ) {
						$difftxt = rt_biz_text_diff( $val, $newval );
						$skip_enter = str_replace( 'Enter', '', $field['label'] );
						$body .= "<strong>{ $skip_enter }</strong> : " . $difftxt;
						$flag = true;
					}
				}
			}
			if ( $flag ) {
				$user = wp_get_current_user();
				$body = 'Updated by <strong>' . $user->display_name . '</strong> <br/>' . $body;
				$settings = rt_biz_get_redux_settings();
				$label = $settings['menu_label'];
				$data = array(
					'comment_post_ID' => $post_id,
					'comment_content' => $body,
					'comment_type' => 'rt_bot',
					'comment_approved' => 1,
					'comment_author' => $label . ' Bot',
				);
				wp_insert_comment( $data );
			}
		}

		/**
		 * Filter the bulk action updated messages for '. $singular .'.
		 * @param $bulk_messages
		 * @param $bulk_counts
		 *
		 * @return $bulk_messages
		 */
		public function rt_biz_bulk_entity_update_messages( $bulk_messages, $bulk_counts ) {
			$singular = strtolower( $this->labels['singular_name'] );
			$plural = strtolower( $this->labels['name'] );
			$bulk_messages[ $this->post_type ] = array(
				'updated' => _n( '%s ' . $singular . ' updated.', '%s ' . $plural . ' updated.', $bulk_counts['updated'] ),
				'locked' => _n( '%s ' . $singular . ' not updated, somebody is editing it.', '%s ' . $plural . ' not updated, somebody is editing them.', $bulk_counts['locked'] ),
				'deleted' => _n( '%s ' . $singular . ' permanently deleted.', '%s ' . $plural . ' permanently deleted.', $bulk_counts['deleted'] ),
				'trashed' => _n( '%s ' . $singular . ' moved to the Trash.', '%s ' . $plural . ' moved to the Trash.', $bulk_counts['trashed'] ),
				'untrashed' => _n( '%s ' . $singular . ' restored from the Trash.', '%s ' . $plural . ' restored from the Trash.', $bulk_counts['untrashed'] ),
			);
			return $bulk_messages;
		}

		/**
		 * Added message when entity update
		 * @param $messages
		 *
		 * @return mixed
		 */
		public function rt_biz_entity_updated_messages( $messages ) {
			$singular = $this->labels['singular_name'];
			$messages[ $this->post_type ] = array(
				0 => '', // Unused. Messages start at index 1.
				1 => __( $singular . ' updated.', RT_BIZ_TEXT_DOMAIN ),
				2 => __( 'Custom field updated.', RT_BIZ_TEXT_DOMAIN ),
				3 => __( 'Custom field deleted.', RT_BIZ_TEXT_DOMAIN ),
				4 => __( $singular . ' updated.', RT_BIZ_TEXT_DOMAIN ),
				/* translators: %s: date and time of the revision */
				5 => isset( $_GET['revision'] ) ? sprintf( __( $singular . ' restored to revision from %s', RT_BIZ_TEXT_DOMAIN ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => __( $singular . ' published.', RT_BIZ_TEXT_DOMAIN ),
				7 => __( $singular . ' saved.', RT_BIZ_TEXT_DOMAIN ),
				8 => __( $singular . ' submitted.', RT_BIZ_TEXT_DOMAIN ),
				10 => __( $singular . ' draft updated.', RT_BIZ_TEXT_DOMAIN )
			);

			return $messages;
		}

		/**
		 * @param $where
		 * skip rtbot comments from feeds
		 * @return string
		 */
		public function rt_biz_skip_feed_comments( $where ) {
			global $wpdb;
			$where .= $wpdb->prepare( ' AND comment_type != %s', 'rt_bot' );
			return $where;
		}

		public function rt_biz_preprocess_comment_handler( $commentdata ) {
			// Contact and company comments needed on from end in furture need to remove else condition.
			if ( is_admin() ) {
				$screen = get_current_screen();
				if ( ( isset( $screen->post_type ) && ( rt_biz_get_contact_post_type() != $screen->post_type && rt_biz_get_company_post_type() != $screen->post_type ) ) && $screen->id != Rt_Biz_Dashboard::$page_slug ) {
					$types = isset( $commentdata->query_vars['type__not_in'] ) ? $commentdata->query_vars['type__not_in'] : array();
					if ( ! is_array( $types ) ) {
						$types = array( $types );
					}
					$types[] = 'rt_bot';
					$commentdata->query_vars['type__not_in'] = $types;
				}
			} else {
				$types = isset( $commentdata->query_vars['type__not_in'] ) ? $commentdata->query_vars['type__not_in'] : array();
				if ( ! is_array( $types ) ) {
					$types = array( $types );
				}
				$types[] = 'rt_bot';
				$commentdata->query_vars['type__not_in'] = $types;
			}
			return $commentdata;
		}


		/**
		 * @param $id
		 * @param $key
		 * @param $value
		 * @param bool $unique
		 */
		public static function add_meta( $id, $key, $value, $unique = false ) {
			add_post_meta( $id, self::$meta_key_prefix . $key, $value, $unique );
		}

		/**
		 * @param $id
		 * @param $key
		 * @param bool $single
		 * @return mixed
		 */
		public static function get_meta( $id, $key, $single = false ) {
			return get_post_meta( $id, self::$meta_key_prefix . $key, $single );
		}

		/**
		 * @param $id
		 * @param $key
		 * @param $value
		 * @param string $prev_value
		 */
		public static function update_meta( $id, $key, $value, $prev_value = '' ) {
			update_post_meta( $id, self::$meta_key_prefix . $key, $value, $prev_value );
		}

		/**
		 * @param $id
		 * @param $key
		 * @param string $value
		 */
		public static function delete_meta( $id, $key, $value = '' ) {
			delete_post_meta( $id, self::$meta_key_prefix . $key, $value );
		}


		/**
		 * @param $query
		 * @param $args
		 * @return array
		 */
		public function rt_biz_search_entity( $query, $args = array() ) {
			$query_args = array(
				'post_type' => $this->post_type,
				'post_status' => 'any',
				'posts_per_page' => 10,
				's' => $query,
			);
			$args = array_merge( $query_args, $args );
			$entity = new WP_Query( $args );

			return $entity->posts;
		}


		/**
		 * @param $translation
		 * @param $text
		 * @return string
		 */
		/*public function change_publish_button( $translation, $text ) {
			if ( $this->post_type == get_post_type() && 'Publish' == $text ) {
				return 'Add';
			}
			return $translation;
		}*/

	}

}
