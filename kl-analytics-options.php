<?php
/*
KL Analytics Settings
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

// create custom plugin settings menu
add_action('admin_menu', 'klala_plugin_create_menu');

function klala_plugin_create_menu() {
	//create options page
	add_options_page('KL Analytics Settings', 'KL Analytics', 'manage_options', __FILE__, 'klala_plugin_settings_page' , __FILE__ );

	//call register settings function
	add_action( 'admin_init', 'register_klala_plugin_settings' );	
}

function register_klala_plugin_settings() {
	//register our settings
	register_setting( 'klala-plugin-settings-group', 'klala_limit' );
	register_setting( 'klala-plugin-settings-group', 'klala_downloads_monitor' ); // whether to report on downloads as provided by Downloads Monitor plugin
	register_setting( 'klala-plugin-settings-group', 'klala_datatables' );	// Try add datatables javascript
	register_setting( 'klala-plugin-settings-group', 'klala_user_filter_source' );	// 'logs' || 'klal_roles_filter' // i.e. log records or klal option
	register_setting( 'klala-plugin-settings-group', 'klala_checkbox_progress_roles' );	// roles of users to include in checkbox progress reporting
	register_setting( 'klala-plugin-settings-group', 'klala_category_progress_roles' );	// roles of users to include in category progress reporting	
	register_setting( 'klala-plugin-settings-group', 'klala_progress_append_users_from_log' );	// append roles/users from log tables (mostly for development)
	register_setting( 'klala-plugin-settings-group', 'klala_progress_milestones' );	// all milestones to populate for checkbox progress reporting	
	register_setting( 'klala-plugin-settings-group', 'klala_progress_category_milestones' );	// all categories to use as milestones to populate for category progress reporting		
}


function klala_plugin_settings_page() {
?>
    <div class="wrap">
    <h1>KL Analytics Settings</h1>

    <form method="post" action="options.php">
    <?php settings_fields( 'klala-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'klala-plugin-settings-group' ); ?>
    <h2>General</h2>
    <table class="form-table">          
        <tr valign="top">
        <th scope="row">Default limit</th>
        <td>
        	<input type="number" name="klala_limit" value="<?php echo esc_attr( get_option('klala_limit') ); ?>"  />
        	<p><small>Default limit for number of rows to show where sensible in summary analytics.</small></p>
        </td>
        </tr>        
        
    	<tr valign="top">
        <th scope="row">Download Monitor plugin support</th>
        <td><input type="checkbox" name="klala_downloads_monitor" value="true" <?php if ( get_option('klala_downloads_monitor') ) echo ' checked '; ?> /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Add JavaScript Datatables</th>
        <td><input type="checkbox" name="klala_datatables" value="true" <?php if ( get_option('klala_datatables') ) echo ' checked '; ?> /></td>
        </tr>
                               
    	<tr valign="top">
        <th scope="row">User filter source</th>        
        <td>
			<select name = "klala_user_filter_source">
			<option value = "logs" <?php if (get_option('klala_user_filter_source') == 'logs') { echo ' selected '; } ?>>logs</option>
			<option value = "klal_roles_filter" <?php if (get_option('klala_user_filter_source') == 'klal_roles_filter') { echo ' selected '; } ?>>klal_roles_filter</option>
			</select>
		</td>
        </tr>        
                
    </table>
    
    <h2>Progress trackers</h2>
		<table class="form-table">
			
        <tr valign="top">
        <th scope="row">Checkbox progress roles</th>
        <td>
        	<input type="text" name="klala_checkbox_progress_roles" value="<?php echo esc_attr( get_option('klala_checkbox_progress_roles') ); ?>"  />
        	<p><small>User roles to include in checkbox progress reporting. Comma-delimited.</small></p>
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Category progress roles</th>
        <td>
        	<input type="text" name="klala_category_progress_roles" value="<?php echo esc_attr( get_option('klala_category_progress_roles') ); ?>"  />
        	<p><small>User roles to include in checkbox progress reporting. Comma-delimited.</small></p>
        </td>
        </tr>        
        
    	<tr valign="top">
        <th scope="row">Append users from logs</th>
        <td><input type="checkbox" name="klala_progress_append_users_from_log" value="true" <?php if ( get_option('klala_progress_append_users_from_log') ) echo ' checked '; ?> /></td>
        </tr>
                    
        <tr valign="top">
        <th scope="row">Milestones</th>
        <td>
        	<input type="text" name="klala_progress_milestones" value="<?php echo esc_attr( get_option('klala_progress_milestones') ); ?>"  />
        	<p><small>Ordered list of all milestones to report against in checkbox progress reporting. Comma-delimited.</small></p>
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Category milestones</th>
        <td>
        	<input type="text" name="klala_progress_category_milestones" value="<?php echo esc_attr( get_option('klala_progress_category_milestones') ); ?>"  />
        	<p><small>Ordered list of all categories to use as milestones to report against in category progress reporting. Comma-delimited.</small></p>
        </td>
        </tr>        
        
    </table>
    
    <?php submit_button(); ?>
    </form>

    <h2>API</h2>

    <table class="form-table">
           
        <tr valign="top">
        <th scope="row">$klala_config</th>
        <td>
        <pre>
$klala_config = array(
    'roles' => array(), // roles to merge into user-based results e.g. user logins (kl-specific)
    'klala_tables' => array('kl_access_logs','kl_access_logs_archive'), // default available (and allowed) log tables
    'klala_table' => null, // current table
);        </pre>
        </td>
        </tr>                        
    
        <tr valign="top">
        <th scope="row">Hooks</th>
        <td>
            klala_pre_init, klala_post_init, 
            <br/>
            with $klala_config argument
        </td>
        </tr>    
                
    </table>

</div>
<?php } ?>
