<?php
/**
 * class-revisr-settings-fields.php
 *
 * Displays (and updates) the settings fields.
 *
 * @package   Revisr
 * @license   GPLv3
 * @link      https://revisr.io
 * @copyright 2014 Expanded Fronts, LLC
 */

// Disallow direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

class Revisr_Settings_Fields {

	/**
	 * The main Git class.
	 * @var Revisr_Git()
	 */
	private $git;

	/**
	 * User options and preferences.
	 * @var array
	 */
	private $options;

	/**
	 * Initialize the class.
	 * @access public
	 */
	public function __construct() {
		$this->git 		= new Revisr_Git();
		$this->options 	= Revisr::get_options();
	}

	/**
	 * Checks if a setting has been saved and is not empty.
	 * Used to determine if we should update the .git/config.
	 * @access private
	 * @param  string $option The option to check.
	 * @return boolean
	 */
	private function is_updated( $option ) {
		if ( isset( $_GET['settings-updated'] ) ) {
			if ( isset( $this->options[$option] ) && $this->options[$option] != '' ) {
				return true;
			}			
		}
		return false;
	}

	/**
	 * Displays the description for the "General Settings" tab.
	 * @access public
	 */
	public function revisr_general_settings_callback() {
		_e( 'These settings configure the local repository, and may be required for Revisr to work correctly.', 'revisr' );
	}

	/**
	 * Displays the description for the "Remote Settings" tab.
	 * @access public
	 */
	public function revisr_remote_settings_callback() {
		_e( 'These settings are optional, and only need to be configured if you plan to push your website to a remote repository like Bitbucket or Github.', 'revisr' );
	}

	/**
	 * Displays the description for the "Database Settings" tab.
	 * @access public
	 */
	public function revisr_database_settings_callback() {

	}
	/**
	 * Displays/updates the "Username" settings field.
	 * @access public
	 */
	public function username_callback() {
		printf(
            '<input type="text" id="username" name="revisr_general_settings[username]" value="%s" class="regular-text" />
            <br><span class="description">%s</span>',
            isset( $this->options['username'] ) ? esc_attr( $this->options['username']) : '',
            __( 'The username to commit with in Git.', 'revisr' )
        );

        if ( $this->is_updated( 'username' ) ) {
        	$this->git->config_user_name( $this->options['username'] );
        }
	}

	/**
	 * Displays/updates the "Email" settings field.
	 * @access public
	 */
	public function email_callback() {
		printf(
            '<input type="text" id="email" name="revisr_general_settings[email]" value="%s" class="regular-text" />
            <br><span class="description">%s</span>',
            isset( $this->options['email'] ) ? esc_attr( $this->options['email']) : '',
            __( 'The email address associated to your Git username. Also used for notifications (if enabled).', 'revisr' )
        );

        if ( $this->is_updated( 'email' ) ) {
        	$this->git->config_user_email( $this->options['email'] );
        }
	}

	/**
	 * Displays/updates the ".gitignore" settings field.
	 * @access public
	 */
	public function gitignore_callback() {
		// Write the updated setting to the .gitignore.
		if ( $this->is_updated( 'gitignore' ) ) {
			chdir( ABSPATH );
			file_put_contents( '.gitignore', $this->options['gitignore'] );
			$this->git->run( 'add .gitignore' );
			$commit_msg = __( 'Updated .gitignore.', 'revisr' );
			$this->git->run("commit -m \"$commit_msg\"");
			$this->git->auto_push();
		}
		
		chdir( ABSPATH );
		if ( isset( $this->options['gitignore'] ) ) {
			$gitignore = $this->options['gitignore'];
		} elseif ( file_exists( '.gitignore' ) ) {
			$gitignore = file_get_contents( '.gitignore' );
		} else {
			$gitignore = '';
		}
		printf(
            '<textarea id="gitignore" name="revisr_general_settings[gitignore]" rows="6" />%s</textarea>
            <br><span class="description">%s</span>',
            $gitignore,
            __( 'Add files or directories that you don\'t want to show up in Git here, one per line.<br>This will update the ".gitignore" file for this repository.', 'revisr' )
		);
	}

	/**
	 * Displays/updates the "Automatic Backups" settings field.
	 * @access public
	 */
	public function automatic_backups_callback() {
		if ( isset( $this->options['automatic_backups'] ) ) {
			$schedule = $this->options['automatic_backups'];
		} else {
			$schedule = 'none';
		}
		?>
			<select id="automatic_backups" name="revisr_general_settings[automatic_backups]">
				<option value="none" <?php selected( $schedule, 'none' ); ?>><?php _e( 'None', 'revisr' ); ?></option>
				<option value="daily" <?php selected( $schedule, 'daily' ); ?>><?php _e( 'Daily', 'revisr' ); ?></option>
				<option value="weekly" <?php selected( $schedule, 'weekly' ); ?>><?php _e( 'Weekly', 'revisr' ); ?></option>
			</select>
			<span class="description"><?php _e( 'Automatic backups will backup both the files and database at the interval of your choosing.', 'revisr' ); ?></span>
		<?php

		// Update the cron settings/clear if necessary on save.
		if ( $this->is_updated( 'automatic_backups' ) ) {

			if ( isset( $this->options['automatic_backups'] ) && $this->options['automatic_backups'] != 'none' ) {
				$timestamp 	= wp_next_scheduled( 'revisr_cron' );
				if ( $timestamp == false ) {
					wp_schedule_event( time(), $this->options['automatic_backups'], 'revisr_cron' );
				} else {
					wp_clear_scheduled_hook( 'revisr_cron' );
					wp_schedule_event( time(), $this->options['automatic_backups'], 'revisr_cron' );
				}
			} else {
				wp_clear_scheduled_hook( 'revisr_cron' );
			}
		}
	}

	/**
	 * Displays/updates the "Notifications" settings field.
	 * @access public
	 */
	public function notifications_callback() {
		printf(
			'<input type="checkbox" id="notifications" name="revisr_general_settings[notifications]" %s />
			<span class="description">%s</span>',
			isset( $this->options['notifications'] ) ? "checked" : '',
			__( 'Enabling notifications will send updates about new commits, pulls, and pushes to the email address above.', 'revisr' )
		);
	}

	/**
	 * Displays/updates the "Remote Name" settings field.
	 * @access public
	 */
	public function remote_name_callback() {
		printf(
			'<input type="text" id="remote_name" name="revisr_remote_settings[remote_name]" value="%s" class="regular-text" placeholder="origin" />
			<br><span class="description">%s</span>',
			isset( $this->options['remote_name'] ) ? esc_attr( $this->options['remote_name']) : '',
			__( 'Git sets this to "origin" by default when you clone a repository, and this should be sufficient in most cases.<br>If you\'ve changed the remote name or have more than one remote, you can specify that here.', 'revisr' )
		);
		if ( $this->is_updated( 'remote_name' ) ) {
			$remote_name = $this->options['remote_name'];
		} else {
			$remote_name = 'origin';
		}

		// Sets the remote name and/or URL if necessary.
		$add = $this->git->run( "remote add $remote_name {$this->options['remote_url']}" );
		if ( $add == false ) {
			$this->git->run( "remote set-url $remote_name {$this->options['remote_url']}" );
		}
	}

	/**
	 * Displays/updates the "Remote URL" settings field.
	 * @access public
	 */
	public function remote_url_callback() {

		$check_remote 	= $this->git->run( 'config --get remote.origin.url' );
		
		if ( isset( $this->options['remote_url'] ) && $this->options['remote_url'] != '' ) {
			$remote_url = esc_attr( $this->options['remote_url'] );
		} elseif ( $check_remote !== false ) {
			$remote_url = $check_remote[0];
		} else {
			$remote_url = '';
		}
		printf(
			'<input type="text" id="remote_url" name="revisr_remote_settings[remote_url]" value="%s" class="regular-text" placeholder="https://user:pass@host.com/user/example.git" /><span id="verify-remote"></span>
			<br><span class="description">%s</span>',
			$remote_url,
			__( 'Useful if you need to authenticate over "https://" instead of SSH, or if the remote has not already been set through Git.', 'revisr' )
		);		
	}

	/**
	 * Displays/updates the "Revisr Webhook URL" settings field.
	 * @access public
	 */
	public function webhook_url_callback() {
		// Allow the user to unset the Webhook URL.
		if ( isset( $_GET['settings-updated'] ) ) {
			if ( $this->is_updated( 'webhook_url' ) ) {
				$this->git->config_revisr_url( 'webhook', $this->options['webhook_url'] );
			} else {
				$this->git->run( 'config --unset revisr.webhook-url' );
			}
		}

		// Grab the URL from the .git/config as it MAY be replaced in the database.
		$get_url = $this->git->config_revisr_url( 'webhook' );
		if ( is_array( $get_url ) ) {
			$webhook_url = $get_url[0];
		} else {
			$webhook_url = '';
		}
		printf(
			'<input type="text" name="revisr_remote_settings[webhook_url]" value="%s" class="regular-text" /><br><span class="description">%s</span>',
			$webhook_url,
			__( 'If you have Revisr installed on another server using the same repository,<br> you can add the Revisr Webhook from that server here to trigger an update when pushing.', 'revisr' )
		);
	}

	/**
	 * Displays/updates the "Auto Push" settings field.
	 * @access public
	 */
	public function auto_push_callback() {
		printf(
			'<input type="checkbox" id="auto_push" name="revisr_remote_settings[auto_push]" %s />
			<label for="auto_push">%s</label>',
			isset( $this->options['auto_push'] ) ? "checked" : '',
			__( 'Check to automatically push new commits to the remote repository.', 'revisr' )
		);		
	}

	/**
	 * Displays/updates the "Auto Pull" settings field.
	 * @access public
	 */
	public function auto_pull_callback() {
		printf(
			'<input type="checkbox" id="auto_pull" name="revisr_remote_settings[auto_pull]" %s />
			<label for="auto_pull">%s</label>',
			isset( $this->options['auto_pull'] ) ? "checked" : '',
			__( 'Check to allow Revisr to automatically pull commits from a remote repository.', 'revisr' )
		);
		$remote 	= new Revisr_Remote();
		$token 		= $remote->get_token();

		if ( $token ) {
			$post_hook 	= get_admin_url() . 'admin-post.php?action=revisr_update&token=' . $remote->get_token();

			// Display the generated webhook.
			printf(
				'<br><br><span id="post-hook" class="description">%s<br>
				<input id="post-hook-input" type="text" value="%s" disabled /><br>
				<br>%s</span>',
				__( 'Revisr Webhook:', 'revisr' ),
				$post_hook,
				__( 'You can add the above webhook to Bitbucket, GitHub, or another instance of Revisr to automatically update this repository.', 'revisr' )
			);
		}
		else {
			echo '<p id="post-hook" class="description">' . __( 'There was an error generating the webhook. Please make sure that Revisr has write access to the ".git/config" and try again.', 'revisr' ) . '</p>';
		}
	
	}			

	/**
	 * Displays/updates the "DB Tracking" settings field.
	 * @access public
	 */
	public function tracked_tables_callback() {
		$selected = '';
		if ( isset( $this->options['db_tracking'] ) && $this->options['db_tracking'] == 'custom' ) {
			$selected = ' selected';
		}
		printf(
			'<select id="db-tracking-select" name="revisr_database_settings[db_tracking]">
				<option value="all_tables">%s</option>
				<option value="custom"%s>%s</option>
			</select>',
			__( 'Track all tables', 'revisr' ),
			$selected,
			__( 'Let me decide...', 'revisr' )
		);

		// Allows the user to select the tables they want to track.
		$db 	= new Revisr_DB();
		$tables = $db->get_tables();
		echo '<div id="advanced-db-tracking"><br><select name="revisr_database_settings[tracked_tables][]" multiple="multiple" style="width:350px;height:250px;">';
		if ( is_array( $tables ) ) {
			foreach ( $tables as $table ) {
				$table_selected = '';
				if ( in_array( $table, $db->get_tracked_tables() ) ) {
					$table_selected = ' selected';
				}
				echo "<option value='$table'$table_selected>$table</option>";
			}
		}
		echo '</select></div>';		
	}

	/**
	 * Displays/updates the "Development URL" settings field.
	 * NOTE: DO NOT USE THE OPTION AS STORED IN THE DATABASE!
	 * @access public
	 */
	public function development_url_callback() {
		// Allow the user to unset the dev URL.
		if ( isset( $_GET['settings-updated'] ) ) {
			if ( $this->is_updated( 'development_url' ) ) {
				$this->git->config_revisr_url( 'dev', $this->options['development_url'] );
			} else {
				$this->git->run( 'config --unset revisr.dev-url' );
			}
		}

		// Grab the URL from the .git/config as it will be replaced in the database.
		$get_url = $this->git->config_revisr_url( 'dev' );
		if ( is_array( $get_url ) ) {
			$dev_url = $get_url[0];
		} else {
			$dev_url = '';
		}

		printf(
			'<input type="text" id="development_url" name="revisr_database_settings[development_url]" class="regular-text" value="%s" />
			<br><p class="description">%s</p>',
			$dev_url,
			__( 'If you\'re importing the database from a separate environment, enter the URL for the development environment here and it will be replaced in the database during import.', 'revisr' )
		);
	}

	/**
	 * Displays/updates the "Path to MySQL" settings field.
	 * @access public
	 */
	public function mysql_path_callback() {
		if ( isset( $_GET['settings-updated'] ) ) {
			if ( $this->is_updated( 'mysql_path' ) ) {
				$this->git->config_revisr_path( 'mysql', $this->options['mysql_path'] );
			} else {
				$this->git->run( 'config --unset revisr.mysql-path' );
			}
		}

		$get_path = $this->git->config_revisr_path( 'mysql' );
		if ( is_array( $get_path) ) {
			$mysql_path = $get_path[0];
		} else {
			$mysql_path = '';
		}
		printf(
			'<input type="text" id="mysql_path" name="revisr_database_settings[mysql_path]" value="%s" class="regular-text" placeholder="" />
			<br><p class="description">%s</p>',
			$mysql_path,
			__( 'Leave blank if the full path to MySQL has already been set on the server. Some possible settings include:
			<br><br>For MAMP: /Applications/MAMP/Library/bin/<br>
			For WAMP: C:\wamp\bin\mysql\mysql5.6.12\bin\ ', 'revisr' )
		);		
	}

	/**
	 * Displays/updates the "Reset DB" settings field.
	 * @access public
	 */
	public function reset_db_callback() {
		printf(
			'<input type="checkbox" id="reset_db" name="revisr_database_settings[reset_db]" %s /><label for="reset_db">%s</label><br><br>
			<input type="checkbox" id="import_db" name="revisr_database_settings[import_db]" %s /><label for="import_db">%s</label><br><br>
			<p class="description">%s</p>',
			isset( $this->options['reset_db'] ) ? "checked" : '',
			__( 'Import database when changing branches?', 'revisr' ),
			isset( $this->options['import_db'] ) ? "checked" : '',
			__( 'Import database when pulling commits?', 'revisr' ),
			__( 'If checked, Revisr will automatically import the tracked tables and run any necessary find/replaces. Useful if using Revisr accross multiple environments.', 'revisr' )
		);		
	}
}