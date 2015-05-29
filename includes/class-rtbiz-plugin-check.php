<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RtBiz_Plugin_Check' ) ) {

	/**
	 * Class Rt_Biz_Helpdesk
	 * Check Dependency
	 * Main class that initialize the rt-helpdesk Classes.
	 * Load Css/Js for front end
	 *
	 * @since  0.1
	 *
	 * @author udit
	 */
	class RtBiz_Plugin_Check {

		private $plugins_dependency = array();

		public function __construct( $plugins_dependency ) {
			$this->plugins_dependency = $plugins_dependency;
		}

		public function rt_biz_check_plugin_dependency() {
			$flag = true;
			foreach ( $this->plugins_dependency as $plugin ) {
				if ( ! $plugin['active'] ) {
					add_action( 'admin_enqueue_scripts', array(
						$this,
						'rt_biz_plugins_dependency_enqueue_js',
					) );
					add_action( 'wp_ajax_rtbiz_install_plugin', array( $this, 'rt_biz_install_plugin_ajax' ) );
					add_action( 'wp_ajax_rtbiz_activate_plugin', array(
						$this,
						'rt_biz_activate_plugin_ajax',
					) );
					add_action( 'admin_notices', array( $this, 'rt_biz_plugin_not_installed_admin_notice' ) );
					$flag = false;
				}
			}

			return $flag;
		}

		public function rt_biz_plugins_dependency_enqueue_js() {
			wp_enqueue_script( RT_BIZ_TEXT_DOMAIN . '-plugins-dependency', RT_BIZ_URL . 'admin/js/rtbiz-plugin-check.js', '', RT_BIZ_VERSION, true );
			wp_localize_script( RT_BIZ_TEXT_DOMAIN . '-plugins-dependency', 'rtbiz_ajax_url', admin_url( 'admin-ajax.php' ) );
		}

		public function rt_biz_install_plugin_ajax() {

			if ( empty( $_POST['plugin_slug'] ) ) {
				die( __( 'ERROR: No slug was passed to the AJAX callback.', RT_BIZ_TEXT_DOMAIN ) );
			}
			check_ajax_referer( 'rtbiz_install_plugin_' . $_POST['plugin_slug'] );

			if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
				die( __( 'ERROR: You lack permissions to install and/or activate plugins.', RT_BIZ_TEXT_DOMAIN ) );
			}
			$this->rt_biz_install_plugin( $_POST['plugin_slug'] );

			echo 'true';
			die();
		}

		public function rt_biz_install_plugin( $plugin_slug ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

			$api = plugins_api( 'plugin_information', array(
				'slug'   => $plugin_slug,
				'fields' => array( 'sections' => false )
			) );

			if ( is_wp_error( $api ) ) {
				die( sprintf( __( 'ERROR: Error fetching plugin information: %s', RT_BIZ_TEXT_DOMAIN ), $api->get_error_message() ) );
			}

			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
			}

			if ( ! class_exists( 'Rt_Biz_Plugin_Upgrader_Skin' ) ) {
				require_once( RT_BIZ_PATH . 'admin/abstract/class-rt-biz-plugin-upgrader-skin.php' );
			}

			$upgrader = new Plugin_Upgrader( new Rt_Biz_Plugin_Upgrader_Skin( array(
				'nonce'  => 'install-plugin_' . $plugin_slug,
				'plugin' => $plugin_slug,
				'api'    => $api,
			) ) );

			$install_result = $upgrader->install( $api->download_link );

			if ( ! $install_result || is_wp_error( $install_result ) ) {
				// $install_result can be false if the file system isn't writeable.
				$error_message = __( 'Please ensure the file system is writeable', RT_BIZ_TEXT_DOMAIN );

				if ( is_wp_error( $install_result ) ) {
					$error_message = $install_result->get_error_message();
				}

				die( sprintf( __( 'ERROR: Failed to install plugin: %s', RT_BIZ_TEXT_DOMAIN ), $error_message ) );
			}

			$activate_result = activate_plugin( $this->rt_biz_get_path_for_plugin( $plugin_slug ) );
			if ( is_wp_error( $activate_result ) ) {
				die( sprintf( __( 'ERROR: Failed to activate plugin: %s', RT_BIZ_TEXT_DOMAIN ), $activate_result->get_error_message() ) );
			}
		}

		public function rt_biz_get_path_for_plugin( $slug ) {

			$filename = ( ! empty( $this->plugins_dependency[ $slug ]['filename'] ) ) ? $this->plugins_dependency[ $slug ]['filename'] : $slug . '.php';

			return $slug . '/' . $filename;
		}

		function rt_biz_activate_plugin_ajax() {
			if ( empty( $_POST['path'] ) ) {
				die( __( 'ERROR: No slug was passed to the AJAX callback.', RT_BIZ_TEXT_DOMAIN ) );
			}
			check_ajax_referer( 'rtbiz_activate_plugin_' . $_POST['path'] );

			if ( ! current_user_can( 'activate_plugins' ) ) {
				die( __( 'ERROR: You lack permissions to activate plugins.', RT_BIZ_TEXT_DOMAIN ) );
			}

			$this->rt_biz_activate_plugin( $_POST['path'] );

			echo 'true';
			die();
		}

		function rt_biz_activate_plugin( $plugin_path ) {

			$activate_result = activate_plugin( $plugin_path );
			if ( is_wp_error( $activate_result ) ) {
				die( sprintf( __( 'ERROR: Failed to activate plugin: %s', RT_BIZ_TEXT_DOMAIN ), $activate_result->get_error_message() ) );
			}
		}

		public function rt_biz_plugin_not_installed_admin_notice() { ?>
			<div class="error rtbiz-plugin-not-installed-error"><?php
			foreach ( $this->plugins_dependency as $plugin_slug => $plugin ) {
				if ( ! $this->rt_biz_is_plugin_installed( $plugin_slug ) ) {
					$nonce = wp_create_nonce( 'rtbiz_install_plugin_' . $plugin_slug ); ?>
					<p>
					<b><?php _e( 'rtBiz:' ); ?></b><?php _e( 'Click' ) ?>
				<a href="#"
				   onclick="install_rtbiz_plugin( '<?php echo $plugin_slug; ?>', 'rtbiz_install_plugin', '<?php echo $nonce ?>' )">
						here</a><?php
						_e( ' to install ' . $plugin['name'] . '.', $plugin_slug ) ?>
					</p><?php
				} elseif ( $this->rt_biz_is_plugin_installed( $plugin_slug ) && ! $this->rt_biz_is_plugin_active( $plugin_slug ) ) {
					$path  = $this->rt_biz_get_path_for_plugin( $plugin_slug );
					$nonce = wp_create_nonce( 'rtbiz_activate_plugin_' . $path ); ?>
					<p>
					<b><?php _e( 'rtBiz:' ); ?></b><?php _e( 'Click' ) ?>
					<a href="#"
					   onclick="activate_rtbiz_plugin( '<?php echo $path ?>', 'rtbiz_activate_plugin', '<?php echo $nonce; ?>' )">
						here</a> <?php
						_e( ' to activate ' . $plugin['name'] . '.', $plugin_slug ) ?>
					</p><?php
				}
			} ?>
			</div> <?php
		}

		public function rt_biz_is_plugin_installed( $slug ) {

			if ( empty( $this->plugins_dependency[ $slug ] ) ) {
				return false;
			}

			if ( $this->rt_biz_is_plugin_active( $slug ) || file_exists( WP_PLUGIN_DIR . '/' . $this->rt_biz_get_path_for_plugin( $slug ) ) ) {
				return true;
			}

			return false;
		}

		public function rt_biz_is_plugin_active( $slug ) {

			if ( empty( $this->plugins_dependency[ $slug ] ) ) {
				return false;
			}

			return $this->plugins_dependency[ $slug ]['active'];
		}

	}
}
