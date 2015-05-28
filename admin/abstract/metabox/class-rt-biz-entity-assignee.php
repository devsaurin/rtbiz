<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Rt_Biz_Entity_Assignee' ) ) {

	class Rt_Biz_Entity_Assignee extends Rt_Biz_Metabox {


		public static function ui( $post ) {

			$assigned = rt_biz_get_entity_meta( $post->ID, 'assgin_to', true );
			$assignedHTML = '';
			if ( $assigned && ! empty( $assigned ) ) {
				$author = get_user_by( 'id', $assigned );
				$assignedHTML = "<li id='assign-auth-" . $author->ID . "' class='contact-list'>" .
				                get_avatar( $author->user_email, 24 ) .
				                "<a href='#removeAssign' class='delete_row'>×</a>" .
				                "<br/><a target='_blank' class='assign-title heading' title='" . $author->display_name . "' href='" . get_edit_user_link( $author->ID ) . "'>" . $author->display_name . '</a>' .
				                "<input type='hidden' name='assign_to' value='" . $author->ID . "' /></li>";
			}
			$emps = rt_biz_get_module_employee( RT_BIZ_TEXT_DOMAIN );

			$arrSubscriberUser = array();
			foreach ( $emps as $author ) {
				$arrSubscriberUser[] = array(
					'id' => $author->ID,
					'label' => $author->display_name,
					'imghtml' => get_avatar( $author->user_email, 24 ),
					'user_edit_link' => get_edit_user_link( $author->ID ),
				);
			}?>
			<div>
				<span class="prefix"
				      title="<?php __( 'Assign to' ); ?>"><label><strong><?php __( 'Assign to' ); ?></strong></label></span>
				<script>
					var arr_assign_user =<?php echo json_encode( $arrSubscriberUser ); ?>;
				</script>
				<input type="text" placeholder="Type assignee name to select" id="assign_user_ac"/>
				<ul id="divAssignList" class="">
					<?php echo balanceTags( $assignedHTML ); ?>
				</ul>
			</div> <?php
			do_action( 'rt_biz_metabox_assignee', $post, $post->post_type );
		}


		public static function save( $post_id, $post ) {
			if ( isset( $_POST['assign_to'] ) ) {
				rt_biz_update_entity_meta( $post_id, 'assgin_to', $_POST['assign_to'] );
			} else {
				rt_biz_update_entity_meta( $post_id, 'assgin_to', '' );
			}
		}

	}

}
