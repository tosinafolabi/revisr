<?php
/**
 * class-revisr-admin.php
 *
 * Handles admin-specific functionality.
 *
 * @package   	Revisr
 * @license   	GPLv3
 * @link      	https://revisr.io
 * @copyright 	Expanded Fronts, LLC
 */

// Disallow direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

class Revisr_Admin {

	/**
	 * A reference back to the main Revisr instance.
	 * @var object
	 */
	protected $revisr;

	/**
	 * User options and preferences.
	 * @var array
	 */
	protected $options;

	/**
	 * An array of page hooks returned by add_menu_page and add_submenu_page.
	 * @var array
	 */
	public $page_hooks = array();

	/**
	 * Initialize the class.
	 * @access public
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb 	= $wpdb;
		$this->options 	= Revisr::get_options();
		$this->revisr 	= Revisr::get_instance();
	}

	/**
	 * Registers and enqueues css and javascript files.
	 * @access public
	 * @param string $hook The page to enqueue the styles/scripts.
	 */
	public function revisr_scripts( $hook ) {
		
		// Register all CSS files used by Revisr.
		wp_register_style( 'revisr_dashboard_css', REVISR_URL . 'assets/css/dashboard.css', array(), '07052014' );
		wp_register_style( 'revisr_commits_css', REVISR_URL . 'assets/css/commits.css', array(), '08202014' );
		wp_register_style( 'revisr_octicons_css', REVISR_URL . 'assets/octicons/octicons.css', array(), '01152015' );
		
		// Register all JS files used by Revisr.
		wp_register_script( 'revisr_dashboard', REVISR_URL . 'assets/js/revisr-dashboard.js', 'jquery',  '09232014', true );
		wp_register_script( 'revisr_staging', REVISR_URL . 'assets/js/revisr-staging.js', 'jquery', '07052014', false );
		wp_register_script( 'revisr_committed', REVISR_URL . 'assets/js/revisr-committed.js', 'jquery', '07052014', false );
		wp_register_script( 'revisr_settings', REVISR_URL . 'assets/js/revisr-settings.js', 'jquery', '08272014', true );
		
		// An array of pages that most scripts can be allowed on.
		$allowed_pages = array( 'revisr', 'revisr_settings', 'revisr_branches' );
		
		// Enqueue common scripts and styles.
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $allowed_pages ) ) {

			wp_enqueue_style( 'revisr_dashboard_css' );
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'revisr_settings' );
			wp_enqueue_style( 'revisr_octicons_css' );

		} 

		// Enqueue scripts and styles for the 'revisr_commits' custom post type.
		if ( 'revisr_commits' === get_post_type() ) {

			if ( 'post-new.php' === $hook ) {

				// Enqueue scripts for the "New Commit" screen.
				wp_enqueue_script( 'revisr_staging' );
				wp_localize_script( 'revisr_staging', 'pending_vars', array(
					'ajax_nonce' 		=> wp_create_nonce( 'pending_nonce' ),
					'empty_title_msg' 	=> __( 'Please enter a message for your commit.', 'revisr' ),
					'empty_commit_msg' 	=> __( 'Nothing was added to the commit. Please use the section below to add files to use in the commit.', 'revisr' ),
					'error_commit_msg' 	=> __( 'There was an error committing the files. Make sure that your Git username and email is set, and that Revisr has write permissions to the ".git" directory.', 'revisr' ),
					'view_diff' 		=> __( 'View Diff', 'revisr' ),
					)
				);

			} elseif ( 'post.php' === $hook ) {

				// Enqueue scripts for the "View Commit" screen.
				wp_enqueue_script( 'revisr_committed' );
				wp_localize_script( 'revisr_committed', 'committed_vars', array(
					'post_id' 		=> $_GET['post'],
					'ajax_nonce' 	=> wp_create_nonce( 'committed_nonce' ),
					)
				);

			}

			wp_enqueue_style( 'revisr_commits_css' );
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( 'revisr_octicons_css' );
			wp_enqueue_script( 'thickbox' );
			wp_dequeue_script( 'autosave' );
		}

	}
	
	/**
	 * Registers the menus used by Revisr.
	 * @access public
	 */
	public function menus() {
		$this->page_hooks['menu'] 		= add_menu_page( __( 'Dashboard', 'revisr' ), 'Revisr', 'manage_options', 'revisr', array( $this, 'revisr_dashboard' ), REVISR_URL . 'assets/img/white_18x20.png' );
		$this->page_hooks['dashboard'] 	= add_submenu_page( 'revisr', __( 'Revisr - Dashboard', 'revisr' ), __( 'Dashboard', 'revisr' ), 'manage_options', 'revisr', array( $this, 'revisr_dashboard' ) );
		$this->page_hooks['branches'] 	= add_submenu_page( 'revisr', __( 'Revisr - Branches', 'revisr' ), __( 'Branches', 'revisr' ), 'manage_options', 'revisr_branches', array( $this, 'revisr_branches' ) );
		$this->page_hooks['settings'] 	= add_submenu_page( 'revisr', __( 'Revisr - Settings', 'revisr' ), __( 'Settings', 'revisr' ), 'manage_options', 'revisr_settings', array( $this, 'revisr_settings' ) );
	}

	/**
	 * Filters the display order of the menu pages.
	 * @access public
	 */
	public function revisr_submenu_order( $menu_ord ) {
		global $submenu;
	    $arr = array();
	    
		if ( isset( $submenu['revisr'] ) ) {
		    $arr[] = $submenu['revisr'][0];
		    $arr[] = $submenu['revisr'][3];
		    $arr[] = $submenu['revisr'][1];
		    $arr[] = $submenu['revisr'][2];
		    $submenu['revisr'] = $arr;
		}
	    return $menu_ord;
	}

	/**
	 * Stores an alert to be rendered on the dashboard.
	 * @access public
	 * @param  string  $message 	The message to display.
	 * @param  bool    $is_error Whether the message is an error.
	 */
	public static function alert( $message, $is_error = false ) {
		if ( $is_error == true ) {
			set_transient( 'revisr_error', $message, 10 );
		} else {
			set_transient( 'revisr_alert', $message, 3 );
		}
	}

	/**
	 * Displays the number of files changed in the admin bar.
	 * @access public
	 */
	public function admin_bar( $wp_admin_bar ) {
		if ( $this->revisr->git->count_untracked() != 0 ) {
			$untracked 	= $this->revisr->git->count_untracked();
			$text 		= sprintf( _n( '%s Untracked File', '%s Untracked Files', $untracked, 'revisr' ), $untracked );
			$args 		= array(
				'id'    => 'revisr',
				'title' => $text,
				'href'  => get_admin_url() . 'post-new.php?post_type=revisr_commits',
				'meta'  => array( 'class' => 'revisr_commits' ),
			);
			$wp_admin_bar->add_node( $args );
		} 
	}

	/**
	 * Returns the data for the AJAX buttons.
	 * @access public
	 */
	public function ajax_button_count() {
		if ( $_REQUEST['data'] == 'unpulled' ) {
			echo $this->revisr->git->count_unpulled();
		} else {
			echo $this->revisr->git->count_unpushed();
		}
		exit();
	}

	/**
	 * Deletes existing transients.
	 * @access public
	 */
	public static function clear_transients( $errors = true ) {
		if ( $errors == true ) {
			delete_transient( 'revisr_error' );
		} else {
			delete_transient( 'revisr_alert' );
		}
	}

	/**
	 * Counts the number of commits in the database on a given branch.
	 * @access public
	 * @param  string $branch The name of the branch to count commits for.
	 */
	public static function count_commits( $branch ) {
		global $wpdb;
		if ( $branch == 'all' ) {

			$num_commits = $wpdb->get_results( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = 'branch'" );
		} else {
			$num_commits = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = 'branch' AND meta_value = %s", $branch ) );
		}
		return count( $num_commits );
	}

	/**
	 * Gets an array of details on a saved commit.
	 * @access public
	 * @param  string $id The WordPress Post ID associated with the commit.
	 * @return array
	 */
	public static function get_commit_details( $id ) {

		// Grab the values from the post meta.
		$branch 			= get_post_meta( $id, 'branch', true );
		$hash 				= get_post_meta( $id, 'commit_hash', true );
		$db_hash 			= get_post_meta( $id, 'db_hash', true );
		$db_backup_method	= get_post_meta( $id, 'backup_method', true );
		$files_changed 		= get_post_meta( $id, 'files_changed', true );
		$committed_files 	= get_post_meta( $id, 'committed_files' );
		$git_tag 			= get_post_meta( $id, 'git_tag', true );

		// Store the values in an array.
		$commit_details = array(
			'branch' 			=> $branch ? $branch : __( 'Unknown', 'revisr' ),
			'commit_hash' 		=> $hash ? $hash : __( 'Unknown', 'revisr' ),
			'db_hash' 			=> $db_hash ? $db_hash : '',
			'db_backup_method'	=> $db_backup_method ? $db_backup_method : '',
			'files_changed' 	=> $files_changed ? $files_changed : 0,
			'committed_files' 	=> $committed_files ? $committed_files : array(),
			'tag'				=> $git_tag ? $git_tag : ''
		);

		// Return the array.
		return $commit_details;
	}

	/**
	 * Logs an event to the database.
	 * @access public
	 * @param  string $message The message to show in the Recent Activity. 
	 * @param  string $event   Will be used for filtering later. 
	 */
	public static function log( $message, $event ) {
		global $wpdb;
		$time  = current_time( 'mysql' );
		$table = $wpdb->prefix . 'revisr';
		$wpdb->insert(
			"$table",
			array( 
				'time' 		=> $time,
				'message'	=> $message,
				'event' 	=> $event,
			),
			array(
				'%s',
				'%s',
				'%s',
			)
		);		
	}

	/**
	 * Notifies the admin if notifications are enabled.
	 * @access private
	 * @param  string $subject The subject line of the email.
	 * @param  string $message The message for the email.
	 */
	public static function notify( $subject, $message ) {
		$options 	= Revisr::get_options();
		$url 		= get_admin_url() . 'admin.php?page=revisr';

		if ( isset( $options['notifications'] ) ) {
			$email 		= $options['email'];
			$message	.= '<br><br>';
			$message	.= sprintf( __( '<a href="%s">Click here</a> for more details.', 'revisr' ), $url );
			$headers 	= "Content-Type: text/html; charset=ISO-8859-1\r\n";
			wp_mail( $email, $subject, $message, $headers );
		}
	}

	/**
	 * Renders an alert and removes the old data. 
	 * @access public
	 */
	public function render_alert() {
		$alert = get_transient( 'revisr_alert' );
		$error = get_transient( 'revisr_error' );
		if ( $error ) {
			echo "<div class='revisr-alert error'>" . wpautop( $error ) . "</div>";
		} else if ( $alert ) {
			echo "<div class='revisr-alert updated'>" . wpautop( $alert ) . "</div>";
		} else {
			if ( $this->revisr->git->count_untracked() == '0' ) {
				printf( __( '<div class="revisr-alert updated"><p>There are currently no untracked files on branch %s.', 'revisr' ), $this->revisr->git->branch );
			} else {
				$commit_link = get_admin_url() . 'post-new.php?post_type=revisr_commits';
				printf( __('<div class="revisr-alert updated"><p>There are currently %s untracked files on branch %s. <a href="%s">Commit</a> your changes to save them.</p></div>', 'revisr' ), $this->revisr->git->count_untracked(), $this->revisr->git->branch, $commit_link );
			}
		}
		exit();
	}

	/**
	 * Processes a diff request.
	 * @access public
	 */
	public function view_diff() {
		?>
		<html>
		<head>
			<title><?php _e( 'View Diff', 'revisr' ); ?></title>
		</head>
		<body>
		<?php

			if ( isset( $_REQUEST['commit'] ) ) {
				$diff = $this->revisr->git->run( 'show', array( $_REQUEST['commit'], $_REQUEST['file'] ) );
			} else {
				$diff = $this->revisr->git->run( 'diff', array( $_REQUEST['file'] ) );
			}

			if ( is_array( $diff ) ) {

				// Loop through the diff and echo the output.
				foreach ( $diff as $line ) {
					if ( substr( $line, 0, 1 ) === '+' ) {
						echo '<span class="diff_added" style="background-color:#cfc;">' . htmlspecialchars( $line ) . '</span><br>';
					} else if ( substr( $line, 0, 1 ) === '-' ) {
						echo '<span class="diff_removed" style="background-color:#fdd;">' . htmlspecialchars( $line ) . '</span><br>';
					} else {
						echo htmlspecialchars( $line ) . '<br>';
					}	
				}

			} else {
				_e( 'Oops! Revisr ran into an error rendering the diff.', 'revisr' );
			}
		?>
		</body>
		</html>
		<?php
		exit();
	}

	/**
	 * Updates user settings to be compatible with 1.8.
	 * @access public
	 */
	public function do_upgrade() {

		// Check for the "auto_push" option and save it to the config.
		if ( isset( $this->options['auto_push'] ) ) {
			$this->revisr->git->set_config( 'revisr', 'auto-push', 'true' );
		}

		// Check for the "auto_pull" option and save it to the config.
		if ( isset( $this->options['auto_pull'] ) ) {
			$this->revisr->git->set_config( 'revisr', 'auto-pull', 'true' );
		}

		// Check for the "reset_db" option and save it to the config.
		if ( isset( $this->options['reset_db'] ) ) {
			$this->revisr->git->set_config( 'revisr', 'import-checkouts', 'true' );
		}

		// Check for the "mysql_path" option and save it to the config.
		if ( isset( $this->options['mysql_path'] ) ) {
			$this->revisr->git->set_config( 'revisr', 'mysql-path', $this->options['mysql_path'] );
		}

		// Configure the database tracking to use all tables, as this was how it behaved in 1.7.
		$this->revisr->git->set_config( 'revisr', 'db_tracking', 'all_tables' );

		// We're done here.
		update_option( 'revisr_db_version', '1.1' );
	}

	/**
	 * Displays the "Sponsored by Site5" logo.
	 * @access public
	 */
	public function site5_notice() {
		$allowed_on = array( 'revisr', 'revisr_settings', 'revisr_commits', 'revisr_settings', 'revisr_branches' );
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $allowed_on ) ) {
			$output = true;
		} else if ( isset( $_GET['post_type'] ) && in_array( $_GET['post_type'], $allowed_on ) || get_post_type() == 'revisr_commits' ) {
			$output = true;
		} else {
			$output = false;
		}
		if ( $output === true ) {
			?>
			<div id="site5_wrapper">
				<?php _e( 'Sponsored by', 'revisr' ); ?>
				<a href="http://www.site5.com/" target="_blank"><img id="site5_logo" src="<?php echo REVISR_URL . 'assets/img/site5.png'; ?>" width="80" /></a>
			</div>
			<?php
		}
	}

	/**
	 * Includes the template for the main dashboard.
	 * @access public
	 */
	public function revisr_dashboard() {
		include_once REVISR_PATH . 'templates/dashboard.php';
	}

	/**
	 * Includes the template for the branches page.
	 * @access public
	 */
	public function revisr_branches() {
		include_once REVISR_PATH . 'templates/branches.php';
	}

	/**
	 * Includes the template for the settings page.
	 * @access public
	 */
	public function revisr_settings() {
		include_once REVISR_PATH . 'templates/settings.php';
	}

	/**
	 * Displays the form to delete a branch.
	 * @access public
	 */
	public function delete_branch_form() {
		include_once REVISR_PATH . 'assets/partials/delete-branch-form.php';
	}

	/**
	 * Displays the form to merge a branch.
	 * @access public
	 */
	public function merge_branch_form() {
		include_once REVISR_PATH . 'assets/partials/merge-form.php';
	}

	/**
	 * Displays the form to pull a remote branch.
	 * @access public
	 */
	public function import_tables_form() {
		include_once REVISR_PATH . 'assets/partials/import-tables-form.php';
	}

	/**
	 * Displays the form to revert a commit.
	 * @access public
	 */
	public function revert_form() {
		include_once REVISR_PATH . 'assets/partials/revert-form.php';
	}
}
