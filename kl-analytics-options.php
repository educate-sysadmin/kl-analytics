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
}

function klala_plugin_settings_page() {
?>
    <div class="wrap">
    <h1>KL Access Logs Settings</h1>

    <form method="post" action="options.php">
    <?php settings_fields( 'klala-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'klala-plugin-settings-group' ); ?>
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
    'roles' => array(), // roles to merge into user-based results e.g. user logins
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
