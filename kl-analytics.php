<?php
/*
Plugin Name: KL Access Logs Analytics
Plugin URI: https://github.com/educate-sysadmin/kl-analytics
Description: Wordpress plugin to provide (Combined) Common Log Format analytics (from kl-access-logs)
Version: 0.1.1
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

/*
$klala_log_fields_ = array(
    'remote_host' => $remote_host,
    'client' => $client,
    'userid' => $userid,
    'groups' => $groups,
    'time' => $time,
    'method' => $method,
    'request' => $request,
    'protocol' => $protocol,
    'status' => $status,
    'referer' => $referer,
    'useragent' => $useragent,
);
*/

require_once('kl-analytics-options.php');

/* backend configs */
$klala_config = array(
    'roles' => array(), // roles to merge into user-based results e.g. user logins (kl-specific)
    'roles_populate' => '', // roles to populate roles field with when merging (kl-specific)
    'groups' => '', // groups to merge into user-based results e.g. user logins (kl-specific) comma-delimited, defaults to get_option('klala_add_groups')    
    'klala_tables' => array('kl_access_logs','kl_access_logs_archive'), // default available (and allowed) log tables
    'klala_table' => null, // current table
);

function klala_install() {
    // set default options
    update_option('klala_limit','15');    
}

function klala_init() {
    global $klala_config;
    
    // try get allowed tables from kl-access-logs 
    if (get_option('klal_tables')) {
        $config['klala_tables'] = explode(",",get_option('klal_tables'));
    }
       
    // set groups to same as kl-access-logs
    if ($klala_config['groups'] == '') { $klala_config['groups'] = get_option('klal_add_groups'); }
    if ($klala_config['roles_populate'] == '') { $klala_config['roles_populate'] = get_option('klal_add_roles'); } 
}

/* helper to get array of users' groups, with filter */
function klala_get_user_groups($filter, $user_id) {
    global $wpdb;
    // ref http://docs.itthinx.com/document/groups/api/examples/
    $groups = array(); // to populate
    $groups_user = new Groups_User( $user_id );
    // get group objects
    $user_groups = $groups_user->groups;
    // get group ids (user is direct member)
    $user_group_ids = $groups_user->group_ids;
    // get group ids (user is direct member or by group inheritance)
    $user_group_ids_deep = $groups_user->group_ids_deep;
    foreach ($user_group_ids_deep as $group_id) {
	    $sql = 'SELECT name FROM '.$wpdb->prefix .'groups_group WHERE group_id='.$group_id;
	    $row = $wpdb->get_row( $sql );
	    $group_name = $row->name;
        $filter_groups = explode(",",$filter);
        foreach ($filter_groups as $filter_group) {
	        if (strpos($group_name,$filter_group) !== false) { 
        	    $groups[] = $group_name;
        	}
        }
    }
    return $groups;
}

/* helper to get array of users' roles, with filter */
function klala_get_user_roles($filter, $user_id) {
    $roles = array();
    $user = get_user_by( 'id', $user_id);    
    if ( !($user instanceof WP_User)) {
        $roles = array('visitor');
    } else {
       	$roles = $user->roles;
    }
   
    $return_roles = array();
    $filter_roles = explode(",",$filter);    
    foreach ($roles as $role) {
        foreach ($filter_roles as $filter_role) {
            if (strpos($role,$filter_role) !== false) { 
        	    $return_roles[] = $role;
        	}
        }    
    }
    return $return_roles; 
}

/* helper: get user_id for username */
function klala_get_id_for_username($username) {
    global $wpdb;    
    $sql = 'SELECT ID FROM '.$wpdb->prefix.'users WHERE user_login = "'.$username.'"';
	$result = $wpdb->get_row($sql);
    return $result->ID;
}

function klala_get_logs($table) {
	global $wp, $wpdb;
    global $klala_config;
    
    if (strpos($table,$wpdb->prefix) === false) { $table = $wpdb->prefix.$table; }
    
    $sql = 'SELECT * FROM '.$table;
    if (isset($_POST['klala_start']) && isset($_POST['klala_end'])) {
        $sql .= ' WHERE datetime >= "'.$_POST['klala_start'].' 00:00:00'.'" AND datetime <= "'.$_POST['klala_end'].' 23:59:59'.'"';   
    }
 	
 	// get results
	$result = $wpdb->get_results( 
		$sql
	);
	
    return $result;
}

/* helper to convert array to csv */
if (!function_exists('klutil_array_to_csv')) {
    function klutil_array_to_csv($array) {
        $output = '';
        // field names row
        $firstrow = $array[0];        
        $record = '';
        foreach ($firstrow as $key => $_) {
            $record .= $key.',';
        }
        if (substr($record,strlen($record)-1) == ',') {
            $record = substr($record,0,strlen($record)-1); // remove final comma
        }
        $output .= $record."\n";
        
        // data rows
        foreach ($array as $row) {
            $record = '';
            foreach ($row as $key => $val) {
                $record .= $val.',';
            }
            if (substr($record,strlen($record)-1) == ',') {
                $record = substr($record,0,strlen($record)-1); // remove final comma
            }
            $output .= $record."\n";
        }
        return $output;             
    }
}

/* helper to convert array to clf */
if (!function_exists('klutil_array_to_clf')) {
    function klutil_array_to_clf($array) {
        $output = '';
        foreach ($array as $row) {
        	$output .= $row->remote_host.' '.$row->client.' '.$row->userid.' '.$row->time.' '.'"'.$row->method.' '.$row->request.' '.$row->protocol.'"'.' '.$row->status.' '.$row->size.' '.'"'.$row->referer.'"'.' '.'"'.$row->useragent.'"'."\n";        
        }
        return $output;             
    }
}

/* helper to output html table */
if (!function_exists('klutil_array_to_table')) {
    function klutil_array_to_table($array, $id = null, $class = null) {
    
        $output = '';
        $firstrow = $array[0];
        
        $output .= '<table';
        if ($id) { $output .= ' id = "'.$id.'" '; }
        if ($class) { $output .= ' class = "'.$class.'" '; }    
        $output .= '>'."\n";
        $output .= '<thead>'."\n";

        $output .= '<tr>'."\n";
        foreach ($firstrow as $key => $_) {
            $output .= '<th class="th_'.$key.'">'.$key.'</th>'."\n";
        }
        $output .= '</tr>'."\n";    
        
        $output .= '</thead>'."\n";
        $output .= '<tbody>'."\n";        
        foreach ($array as $row) {
            $output .= '<tr>';
            foreach ($row as $key => $val) {
                $output .= '<td class="th_'.$key.'">'.($val!==''?$val:'&nbsp;').'</td>'."\n";
            }
            $output .= '</tr>'."\n";
        }
        
        $output .= '</tbody>'."\n";
        $output .= '</table>'."\n";    
        
        return $output;     
    }
}

function klala_user_logins($table, $limit = null) {
    global $wpdb;
    global $klala_config; 
    
    $sql = 'SELECT userid AS`user`, roles, count(userid) AS `count` FROM '.$table;
    $sql .= ' WHERE referer LIKE "%login%" ';
    if (isset($_POST['klala_start']) && isset($_POST['klala_end'])) {
        $sql .= ' AND datetime >= "'.$_POST['klala_start'].' 00:00:00'.'" AND datetime <= "'.$_POST['klala_end'].' 23:59:59'.'" ';   
    }
    $sql .= ' GROUP BY userid ORDER BY count(userid) DESC';
       
 	// get results
	$result = $wpdb->get_results( 
		$sql,
		ARRAY_A
	);    

    return $result;
}

function klala_page_hits($table, $limit = null ) {
    global $wpdb;
    global $klala_config;    
    
    $sql = 'SELECT request AS`page`, count(request) AS `hits` FROM '.$table;
    if (isset($_POST['klala_start']) && isset($_POST['klala_end'])) {
        $sql .= ' WHERE datetime >= "'.$_POST['klala_start'].' 00:00:00'.'" AND datetime <= "'.$_POST['klala_end'].' 23:59:59'.'" ';   
    }        
    $sql .= ' GROUP BY page ORDER BY count(request) DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT '.$limit;
    }
    
 	// get results
	$result = $wpdb->get_results( 
		$sql,
		ARRAY_A
	);    

    return $result;
}

/* intermediate query towards page visits grouping page hits */
function klala_page_hits_by_user_and_date($table, $limit = null, $order_by = 'count(request) DESC') {
    global $wpdb;
    global $klala_config;    
    
    $sql = 'SELECT request AS`page`, SUBSTRING(`datetime`,1,10) as `date`, `userid` as `user`, count(request) AS `hits` FROM '.$table;
    if (isset($_POST['klala_start']) && isset($_POST['klala_end'])) {
        $sql .= ' WHERE datetime >= "'.$_POST['klala_start'].' 00:00:00'.'" AND datetime <= "'.$_POST['klala_end'].' 23:59:59'.'" ';   
    }        
    $sql .= ' GROUP BY page, date, user ORDER BY '.$order_by;
    if ($limit > 0) {
        $sql .= ' LIMIT '.$limit;
    }
        
 	// get results
	$result = $wpdb->get_results( 
		$sql,
		ARRAY_A
	);    

    return $result;
}

function klala_page_visits($table, $limit = null) {
    global $wpdb;
    global $klala_config;   
    
    $klala_page_hits_by_user_and_date = klala_page_hits_by_user_and_date($klala_config['klala_table'], null/*NO_LIMIT*/,' date ');
    $result = array();
    $lastdate = null; 
    $lastusers = array(); // users per date    
    foreach ($klala_page_hits_by_user_and_date as $record) {
        // create date record if necessary
        if (!isset($result[$record['page']])) {
            $result[$record['page']] = 0;
        } 
        // handle memory
        if ($record['date'] != $lastdate) {
            $lastusers = array();
        }        
        // compute if visit
        if ($record['date'] != $lastdate || ($record['date'] == $lastdate && !in_array($record['user'],$lastusers)) ) {
            $result[$record['page']]++;
            $lastusers[] = $record['user'];
        }
        // memory
        $lastdate = $record['date'];
    }
    // sort by visits desc
    arsort($result);        
    
    // convert to array of keys and values, up to limit option if set
    $return = array();    
    foreach ($result as $key => $val) {
        $return[] = ['page'=>$key, 'visits'=>$val];
        if ($limit > 0) {        
            if (count($return) >= $limit) {
                break;
            }
        }
    }
    return $return;
}

function klala_visits_by_date($table, $limit = null) {
    global $wpdb;
    global $klala_config;   
    
    $klala_page_hits_by_user_and_date = klala_page_hits_by_user_and_date($klala_config['klala_table'], null/*NO_LIMIT*/,' date ASC');
    $result = array();
    $lastdate = null; 
    $lastusers = array(); // users per date
    foreach ($klala_page_hits_by_user_and_date as $record) {
        // create date record if necessary
        if (!isset($result[$record['date']])) {
            $result[$record['date']] = 0;
        } 
        // handle memory
        if ($record['date'] != $lastdate) {
            $lastusers = array();
        }
        // compute if visit
        if ($record['date'] != $lastdate || ($record['date'] == $lastdate && !in_array($record['user'],$lastusers)) ) {
            $result[$record['date']]++;
            $lastusers[] = $record['user'];
        }
        // memory
        $lastdate = $record['date'];
    }
    // convert to array of keys and values, up to limit option if set
    $return = array();    
    foreach ($result as $key => $val) {
        $return[] = ['date'=>$key, 'visits'=>$val];
        if ($limit > 0) {        
            if (count($return) >= $limit) {
                break;
            }
        }
    }
    return $return;
}

/* create js for google charts */
function klala_visits_by_date_chart_dhtml($table, $limit = null) {
    global $wpdb;
    global $klala_config;   

    $return = "";
    $return .= '<script type="text/javascript">';
    $return .= "    
    function klala_js_visits_by_date_chart() {
    
    var data = google.visualization.arrayToDataTable([
    ";
    // add data
    $klala_visits_by_date = klala_visits_by_date($klala_config['klala_table']/*, no_limit*/); 
    $data = "['Date','Visits'],";
    foreach ($klala_visits_by_date as $datum) {
        $formatted_date = date("d M y",strtotime($datum['date']));
        $data .= '['."'".$formatted_date."'".','.$datum['visits'].']'.',';
    }
    $return .= $data;    
    $return .= "]);";

    $return .= "        
        var options = {
          title: 'Page visits by date',
          legend: { position: 'bottom' },
          height: 400,
          hAxis: {
            slantedText: true,  /* Enable slantedText for horizontal axis */
            slantedTextAngle: 90 /* Define slant Angle */    
          },
          'chartArea': { 'width': '90%', height: '100%', top: '9%', left: '5%', right: '3%', bottom: '30%'} /* Adjust chart alignment to fit vertical labels for horizontal axis*/
        };
        var chart = new google.visualization.LineChart(document.getElementById('klala_visits_by_date_chart_chart'));
        chart.draw(data, options);
    }    
    ";
    $return .= '</script>';    
    $return .= '<div id="klala_visits_by_date_chart_chart"></div>';
    
    return $return;
}


/* downloads (using Downloads Monitor plugin) */
// uses role option if set
function klala_downloads($table, $limit = null) {
    global $wpdb;
    global $klala_config;    
    
    $sql = 'SELECT request AS `download`, count(request) AS `count` FROM '.$table;
    $sql .= ' WHERE request LIKE "%download%" ';    
    if (isset($_POST['klala_start']) && isset($_POST['klala_end'])) {
        $sql .= ' AND datetime >= "'.$_POST['klala_start'].' 00:00:00'.'" AND datetime <= "'.$_POST['klala_end'].' 23:59:59'.'" ';   
    }        
    $sql .= ' GROUP BY download ORDER BY count(request) DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT '.$limit;
    }
        
 	// get results
	$result = $wpdb->get_results( 
		$sql,
		ARRAY_A
	);
	
	// resolve download id's to file names
    for ($c = 0; $c < count($result); $c++) {
        $post_id_search = preg_match("/[0-9]+/",$result[$c]['download'],$matches);
        if (count($matches) > 0) {
            $post_id = (int) $matches[0];
            $post = get_post($post_id);
            if ($post) {            
                $result[$c]['download'] = $post->post_title;
            }
        }
    }

    return $result;
}

function kl_analytics( $atts, $content = null ) {
    global $wpdb;
    global $klala_config;
  	//$wpdb->show_errors(); // debug only not production		    
    
  	$hook = apply_filters('klala_pre_init', array('klala_config'=> $klala_config));
  	
    klala_init();
    
    // default table
    if (!$klala_config['klala_table']) { $klala_config['klala_table'] = $klala_config['klala_tables'][0]; } 
    
    // parse parameters 
	$options = shortcode_atts( array( 'table' => '' ), $atts );
	if ($options['table'] != '') {	    
	    if (in_array($options['table'], $klala_config['klala_tables'])) {
	        $klala_config['klala_table'] = $options['table'];
	    }
	} 
    
    // check for post'ed table
    if (isset($_POST['klala_table']) && in_array($_POST['klala_table'],$klala_tables)) {
        $klala_config['klala_table'] = $_POST['klala_table'];
    }      
    // wp prefix
    if (strpos($wpdb->prefix, $klala_config['klala_table']) === false) {
        $klala_config['klala_table'] = $wpdb->prefix.$klala_config['klala_table'];
    }
        
  	$hook = apply_filters('klala_post_init', array('klala_config'=> $klala_config));

	$output = '';

    $title = $klala_config['klala_table'];
    $title = str_replace('wp_','',$title);
    $title = str_replace('_',' ',$title);    
    $title = ucfirst($title);        
    $output .= '<h2 class="klala_table_heading">'.$title.'</h2>';	
    
    // resolve date filters, defaulting to current month
    if (isset($_POST['klala_start'])) {
        // validation
        if (!preg_match("/\d{4}-\d{2}-\d{2}/", $_POST['klala_start']) === 0) {
            $output .= '<p>'.'Invalid request'.'</p>';
            unset($_POST['klala_start']);
        }    
    } 
    if (!isset($_POST['klala_start'])) { 
        $_POST['klala_start'] = date("Y-m")."-"."01";         
    }
    if (isset($_POST['klala_end'])) {
        if (!preg_match("/\d{4}-\d{2}-\d{2}/", $_POST['klala_end']) === 0) {
            $output .= '<p>'.'Invalid request'.'</p>';
            unset($_POST['klala_end']); 
        }        
    }
    if (!isset($_POST['klala_end'])) { 
        $_POST['klala_end'] = date("Y-m-d"); 
    }    
    
    // date filters
    $output .= '<form action="" method="post" class="kl-analytics-filter">';
  	$output .= '<input type="hidden" value="'.$klala_config['klala_table'].'" name="klala_table" />'; 
    $output .= 'From: ';  	  	
  	$output .= '<input type = "text" value="'.$_POST['klala_start'].'" name = "klala_start" class="kl-analytics-filter" size="12" />';
    $output .= ' to: ';
  	$output .= '<input type = "text" value="'.$_POST['klala_end'].'" name = "klala_end" class="kl-analytics-filter" size="12" />';      	
  	$output .= '&nbsp;';
  	$output .= '<input type = "submit" value="update" name = "submit" class="kl-analytics-filter" />';
    $output .= '</form>';       

    /* parse log file option */
    //$klala_log = klala_get_logs($klala_table);     
    //$output .= klutil_array_to_table($klala_log);

    /* add analytics sections */        
    $output .= '<a name = "klala_users_login_counts_a"></a>';              
    $output .= '<div class="klala klala_users_login_counts" id = "klala_users_login_counts">';
    $output .= '<h2>'.'User logins'.'</h2>';
    $klala_users_login_counts = klala_user_logins($klala_config['klala_table'],'klala_table'); 
    
    // merge other roles if set i.e. to show those that haven't logged in too    
    if (!empty($klala_config['roles'])) {
        $users = get_users( array ('role__in'=>$klala_config['roles']) ); 
        foreach ( $users as $user) {
            $found = false;
            foreach ($klala_users_login_counts as $klala_users_login_count) {
                if ($user->user_login == $klala_users_login_count['user']) {
                    $found = true;
                }
            }
            if (!$found) { 
                // add, with roles to populate
           	    $user_id = klala_get_id_for_username($user->user_login);
    	        $roles = implode(",",klala_get_user_roles($klala_config['roles_populate'], $user_id));   	        
                $klala_users_login_counts[] = array ('user'=>$user->user_login, 'roles'=>$roles, 'count'=> '0'); 
            }
        }
    }            
    $output .= klutil_array_to_table($klala_users_login_counts,'klala_users_login_counts_table','klala_table');
    $output .= '</div>';

    $output .= '<a name = "klala_visits_by_date_chart_a"></a>';          
    $output .= '<div class="klala klala_visits_by_date_chart" id="klala_visits_by_date_chart">';
    $output .= '<h2>';
    $output .= 'Visits by date (chart)';
    $output .= '</h2>';    
    $output .= klala_visits_by_date_chart_dhtml($table, $limit = null);
    $output .= '</div>';
    
    $output .= '<a name = "klala_visits_by_date_a"></a>';      
    $output .= '<div class="klala klala_visits_by_date" id="klala_visits_by_date">';
    $output .= '<h2>';
    $output .= 'Visits by date';
    $output .= '</h2>';
    $klala_visits_by_date = klala_visits_by_date($klala_config['klala_table']/*, no_limit*/); 
    $output .= klutil_array_to_table($klala_visits_by_date,'klala_visits_by_date_table','klala_table');
    $output .= '</div>';        

    $output .= '<a name = "klala_page_hits_by_user_and_date_a"></a>';        
    $output .= '<div class="klala klala_page_hits_by_user_and_date" id="klala_page_hits_by_user_and_date">';
    $output .= '<h2>';
    $output .= 'Pages by users and dates';
    if ((int) get_option('klala_limit') > 0) { $output .= ' ('.'top '.get_option('klala_limit').')'; }
    $output .= '</h2>';
    $klala_page_hits_by_user_and_date = klala_page_hits_by_user_and_date($klala_config['klala_table'], get_option('klala_limit')); 
    $output .= klutil_array_to_table($klala_page_hits_by_user_and_date,'klala_page_hits_by_user_and_date_table','klala_table');
    $output .= '</div>';

    $output .= '<a name = "klala_page_visits_a"></a>';    
    $output .= '<div class="klala klala_page_visits" id = "klala_page_visits">';
    $output .= '<h2>';
    $output .= 'Page visits';
    if ((int) get_option('klala_limit') > 0) { $output .= ' ('.'top '.get_option('klala_limit').')'; }
    $output .= '</h2>';
    $output .= '<p>'.'Pages visited by different users or on different days.'.'</p>';
    $klala_page_visits = klala_page_visits($klala_config['klala_table'], get_option('klala_limit')); 
    $output .= klutil_array_to_table($klala_page_visits,'klala_page_visits_table','klala_table');
    $output .= '</div>';
    
    $output .= '<a name = "klala_page_hits_a"></a>';        
    $output .= '<div class="klala klala_page_hits" id="klala_page_hits">';
    $output .= '<h2>';
    $output .= 'Page hits';
    if ((int) get_option('klala_limit') > 0) { $output .= ' ('.'top '.get_option('klala_limit').')'; }
    $output .= '</h2>';
    $klala_page_hits = klala_page_hits($klala_config['klala_table'], get_option('klala_limit')); 
    $output .= klutil_array_to_table($klala_page_hits,'klala_page_hits_table','klala_table');
    $output .= '</div>';    
    
    if ( get_option('klala_downloads_monitor') ) {   
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        if (is_plugin_active("download-monitor/download-monitor.php")) { 
            $output .= '<a name = "klala_downloads_a"></a>';        
            $output .= '<div class="klala klala_downloads" id ="klala_downloads">';
            $output .= '<h2>';
            $output .= 'Media and downloads';
            if ((int) get_option('klala_limit') > 0) { $output .= ' ('.'top '.get_option('klala_limit').')'; }
            $output .= '</h2>';
            $klala_downloads = klala_downloads($klala_config['klala_table'], get_option('klala_limit')); 
            $output .= klutil_array_to_table($klala_downloads,'klala_downloads_table','klala_table');
            $output .= '</div>';    
        }
    }
    
    // download options
    $output .= '<div class="klala klala_download_options">';
    $output .= '<h2>'.'Download'.'</h2>';    
    /* .xlsx doesn't work
    $output .= '<form action="" method="post" class="kl-analytics-download">';
	// nonce: this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
	// $output .= wp_nonce_field('klala_nonce'); // todo
  	$output .= '<input type="hidden" value="xlsx" name="klala_download" />';
  	$output .= '<input type="hidden" value="'.$klala_config['klala_table'].'" name="klala_table" />'; 
  	$output .= '<input type = "submit" value="Download log file (.xlsx)" name = "submit" />';
    $output .= '</form>';        
    */
    $output .= '<form action="" method="post" class="kl-analytics-download">';
  	$output .= '<input type="hidden" value="csv" name="klala_download" />';
  	$output .= '<input type="hidden" value="'.$klala_config['klala_table'].'" name="klala_table" />'; 
  	$output .= '<input type = "submit" value="download .csv" name = "submit" class="kl-analytics-download" />';
    $output .= '</form>';     
    $output .= '&nbsp;|&nbsp;';            
    $output .= '<form action="" method="post" class="kl-analytics-download">';
  	$output .= '<input type="hidden" value="clf" name="klala_download" />';
  	$output .= '<input type="hidden" value="'.$klala_config['klala_table'].'" name="klala_table" />'; 
  	$output .= '<input type = "submit" value="download .clf" name = "submit" class="kl-analytics-download" />';
    $output .= '</form>';
    $output .= '</div>';    
    
       
    wp_enqueue_style('kl-analytics-css', plugins_url('css/kl-analytics.css',__FILE__ ));
    
    // add google charts
    $output .= klala_init_google_charts();
    
       
	return $output;    
}

// handle downloading
// ref: https://www.ibenic.com/programmatically-download-a-file-from-wordpress/
function klala_requests() {
    global $wpdb;
    global $klala_config;
    
    if (isset($_POST['klala_download']) /* && wp_verify_nonce('klala_nonce')*/) {
    
        // resolve table 
        if (isset($_POST['klala_table']) && in_array(str_replace($wpdb->prefix,'',$_POST['klala_table']),$klala_config['klala_tables'])) {
            $klala_config['klala_table'] = $_POST['klala_table'];
        } else {
            echo '<p>'.'Invalid request'.'</p>';
            exit();
        }
    
        if ($_POST['klala_download'] =="xlsx") {  // doesn't work
            header("Content-type: application/force-download",true,200); // or application/force-download
            header("Content-Disposition: attachment; filename=".$klala_config['klala_table'].".xlsx");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo klutil_array_to_table(klala_get_logs($klala_config['klala_table']));
            exit();
        } else if ($_POST['klala_download'] =="csv") { 
            header("Content-type: application/force-download",true,200); // or application/force-download
            header("Content-Disposition: attachment; filename=".$klala_config['klala_table'].".csv");
            header("Pragma: no-cache");
            header("Expires: 0");
            $logs = klala_get_logs($klala_config['klala_table']);
            // remove legacy groups field
            for ($c = 0; $c < count($logs); $c++) {
                if (isset($logs[$c]->groups)) { unset($logs[$c]->groups); }
            }
            echo klutil_array_to_csv($logs);
            exit();
        } else if ($_POST['klala_download'] =="clf") { 
            header("Content-type: application/force-download",true,200); // or application/force-download
            header("Content-Disposition: attachment; filename=".$klala_config['klala_table'].".clf");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo klutil_array_to_clf(klala_get_logs($klala_config['klala_table']));
            exit();
        }                 
    }
}

function klala_init_google_charts() {
    // Ref <!-- https://developers.google.com/chart/interactive/docs/quick_start -->
    // include google charts    
    echo '
    <script type="text/javascript">
    function klala_drawCharts() {
        klala_js_visits_by_date_chart();
    }
    </script>
    ';
    echo '
    <!--Load the AJAX API-->    
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
    ';
    echo "
      // Load the Visualization API and the corechart package.
      google.charts.load('current', {'packages':['corechart','bar']});

      // Set a callback to run when the Google Visualization API is loaded.
      google.charts.setOnLoadCallback(klala_drawCharts);
    </script>";
}

add_action('init','klala_requests'); // for downloads
add_shortcode( 'kl_analytics', 'kl_analytics' );