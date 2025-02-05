<?php
/*
Plugin Name: Really Simple Events
Plugin URI: http://huntlyc.github.io/really-simple-events/
Description: Simple event module, just a title and start date/time needed!  You can, of course, provide extra information about the event if you wish.  This plugin was created for a bands/performers who do one off shows lasting a couple of hours rather than a few days, so event date ranges, custom post type and so on are not included.
Version: 1.5.3
Author: Huntly Cameron
Author URI: http://www.huntlycameron.co.uk
License: GPL2
*/
////////////////////////////////////////////////////////////////////////////////
/*  Copyright 2012 Huntly Cameron (email : huntly.cameron@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
////////////////////////////////////////////////////////////////////////////////


//Let's go!
load_plugin_textdomain('hc_rse', '', 'really-simple-events/translations');
/*
 * Following install update code based heavily on the examples provided
 * in the wordpress codex:
 * http://codex.wordpress.org/Creating_Tables_with_Plugins
 */
global $hc_rse_db_version;
$hc_rse_db_version = "1.4";

define( 'HC_RSE_TABLE_NAME' , 'reallysimpleevents' );

//When the plugin is activated, install the database
register_activation_hook( __FILE__ , 'hc_rse_plugin_install' );

//When the plugin is loaded, check for DB updates and first run
add_action( 'plugins_loaded' , 'hc_rse_load_translations' );
add_action( 'plugins_loaded' , 'hc_rse_update_db_check' );
add_action( 'plugins_loaded' , 'hc_rse_first_run_check' );
add_action( 'admin_menu' , 'hc_rse_build_admin_menu' );
add_action( 'admin_init' , 'hc_rse_setup_custom_assets' );

//Add Sidebar widget
wp_register_sidebar_widget( 'HC_RSE_EVENT_WIDGET',
                            'RSE Event Widget',
                            'widget_hc_rse_event_widget',
                            array( 'description' =>
                            	   __( 'Shows upcoming events' , 'hc_rse' ) )
                          );
wp_register_widget_control(
	'HC_RSE_EVENT_WIDGET',		// id
	'RSE Event Widget',		// name
	'hc_rse_widget_control'	// callback function
);

//Add shortcode handler for our shortcode "[hc_rse_events]"
add_shortcode( 'hc_rse_events' , 'hc_rse_display_events' );


/**
 * Build a link
 *
 * If link has the from (title)[link] get those vars and use them
 * else just hope for the best and use what's provided.
 *
 * @param  string $link Can be (title)[link] or just a link
 * @return string $html HTML anchor tag or empty string if no link
 */
function hc_rse_display_link($link){
	$html = '<a href="{{link}}" title="{{title}}" target="_blank">{{title}}</a>';

	$linkPieces = hc_rse_parse_link($link);

	//return $link;
	$html = preg_replace('#{{link}}#', ( ( isset( $linkPieces['link'] ) ) ? $linkPieces['link'] : '' ), $html);
	$html = preg_replace('#{{title}}#', ( ( isset( $linkPieces['title'] ) ) ? $linkPieces['title'] : '' ), $html);

	return $html;
}



/**
 * Build a link
 *
 * Try to parse out a link and return the components as an assoc array.
 *
 * @param  string $link Can be (title)[link] or just a link
 * @return assoc array with title and link
 */
function hc_rse_parse_link($link){
	$linkPieces = array('title' => '', 'link' => '');

	$matches = array();
	if(preg_match('#\((.*)\)\[(.*)\]#', $link, $matches)){
		$link = $matches[2];
		if(!preg_match('#(http|https):\/\/.*#', $matches[2])){
			$link = 'http://' . $link;
		}
		$linkPieces['link'] = $link;
		$linkPieces['title'] = $matches[1];
	}else{ //Take what they've given us as the link
		if($link !== '' && !preg_match('#(http|https):\/\/.*#', $link)){
			$link = 'http://' . $link;
		}
		$linkPieces['link'] = $link;
		$linkPieces['title'] = $link;
	}

	return $linkPieces;
}

/**
 * Create a sidebar widget
 *
 * @param mixed $args
 * @return void
 */
function widget_hc_rse_event_widget($args) {
    global $wpdb;
    extract($args);
	$showevents = '';
	$table_name = $wpdb->prefix . HC_RSE_TABLE_NAME;

	//By default include the custom CSS and JS

	wp_enqueue_style( "hc_rse_styles" ,
					  plugin_dir_url( __FILE__ ) . "style.css" );
	wp_enqueue_script( "hc_rse_event_table" ,
					   plugin_dir_url( __FILE__ ) . "js/event-table.js" ,
					   array( 'jquery' ) ,
					   '1' ,
					   true );
	wp_localize_script( "hc_rse_event_table" ,
					    'objectL10n' ,
					    array( 'MoreInfo' => get_option( 'hc_rse_more_info_link' , __( 'More Info' , 'hc_rse' ) ),
						 	   'HideInfo' => get_option( 'hc_rse_hide_info_link' , __( 'Hide Info' , 'hc_rse' ) )
							     )
					  );


	//Get options (default if not set: all upcoming evebts)
	$widgetTitle = stripslashes(get_option( 'hc_rse_widget_title' , 'Upcoming Events' ) );
	$titleLink = get_option( 'hc_rse_widget_title_page', -1 );
	$listType = get_option( 'hc_rse_widget_events' , 'upcoming' );
	$showEvents = get_option( 'hc_rse_widget_event_limit' , -1 );

	if($titleLink != -1){ //Page was selected
		$widgetTitle = '<a href="' . get_page_link( $titleLink ) . '" title="' . __( 'View Events' , 'hc_rse') . '">' . $widgetTitle . '</a>';
	}

	$events = hc_rse_get_events($listType, $showEvents);

	$eventHTML = "";

	if( $events ){
		$eventHTML .= '<ul>';

		foreach( $events as $event ){
			$eventDate = date_i18n( get_option( 'hc_rse_date_format' ) ,
					            strtotime( $event->start_date ));
			$eventTitle = htmlentities( stripslashes( $event->title ) , ENT_COMPAT , 'UTF-8' ) ;


			if($event->link !== ''){
				$eventLink = hc_rse_parse_link($event->link);
				$eventHTML .= '    <li>';
				$eventHTML .=          '<a href="' . $eventLink['link'] . '" title="' . stripslashes( $event->title ) . '">' . "$eventDate - $eventTitle" . '</a>';
				$eventHTML .= '    </li>';
			}else{ //No link
				$eventHTML .= '    <li>';
				$eventHTML .=          "$eventDate - $eventTitle";
				$eventHTML .= '    </li>';
			}
		}
		$eventHTML .= '</ul>';

		//Show link to events page if it's been selected in the widget options
		$eventsPage = get_option( 'hc_rse_widget_event_page' , -1 );

		if($eventsPage != -1){ //Page was selected
			$eventHTML .= '<a href="' . get_page_link( $eventsPage ) . '" title="' . __( 'View Events' , 'hc_rse') . '">' . stripslashes(get_option( 'hc_rse_view_events_link' , __( 'View Events' , 'hc_rse' ) ) ). '</a>';		}

	}else{
		$eventHTML = __( "No Events", 'hc_rse' );
	}

    echo $before_widget;
    echo $before_title . stripslashes($widgetTitle) . $after_title;
    echo $eventHTML;
    echo $after_widget;

}

/**
 * handles saving/displaying the widget options in the admin menu
 *
 * @param array $args - list of arguments
 * @param array $params - list of parameters
 * @return void
 */
function hc_rse_widget_control( $args = array() , $params = array() ) {
	//the form is submitted, save into database
	if ( isset( $_POST['submitted'] ) ) {
		update_option( 'hc_rse_widget_title' , $_POST['hc_rse_widget_title'] );
		update_option( 'hc_rse_widget_title_page' , $_POST['hc_rse_widget_title_page'] );
		update_option( 'hc_rse_widget_event_limit' , $_POST['hc_rse_widget_event_limit'] );
		update_option( 'hc_rse_widget_events' , $_POST['hc_rse_widget_events'] );
		update_option( 'hc_rse_widget_event_page' , $_POST['hc_rse_widget_event_page'] );
	}

	//load options
	$hc_rse_widget_title = get_option( 'hc_rse_widget_title' , 'Upcoming Events' );
	$hc_rse_widget_title_page = get_option( 'hc_rse_widget_title_page' , -1 );
	$hc_rse_widget_event_limit = get_option( 'hc_rse_widget_event_limit' , -1 );
	$hc_rse_widget_events = get_option( 'hc_rse_widget_events' , 'upcoming' );
	$hc_rse_widget_event_page = get_option( 'hc_rse_widget_event_page' , -1 );

	?>
	<?php _e( 'Widget Title' , 'hc_rse' ); ?>:<br />
	<input type="text" class="widefat" name="hc_rse_widget_title" value="<?php echo stripslashes($hc_rse_widget_title); ?>"/>
	<br /><br />

	<?php _e( 'Widget Title Link' , 'hc_rse' ); ?>:<br />
	<?php
		$args = array('selected' => $hc_rse_widget_title_page,
	    			  'name' => 'hc_rse_widget_title_page',
	    			  'show_option_none' => __( 'No Link' , 'hc_rse' ),
	    			  'option_none_value' => -1
	    			  );
	    wp_dropdown_pages( $args );
	?>
	<br /><br />

	<?php _e( 'Show Events' , 'hc_rse' ); ?>:<br />
	<select class="widefat" name="hc_rse_widget_events">
		<option <?php if( $hc_rse_widget_events == 'all' ) echo 'selected="selected"'; ?> value="all"><?php _e( 'All' , 'hc_rse' ); ?></option>
		<option <?php if( $hc_rse_widget_events == 'all-reverse' ) echo 'selected="selected"'; ?> value="all-reverse"><?php _e( 'All (Reverse Order)' , 'hc_rse' ); ?></option>
		<option <?php if( $hc_rse_widget_events == 'upcoming' ) echo 'selected="selected"'; ?> value="upcoming"><?php _e( 'Upcoming' , 'hc_rse' ); ?></option>
		<option <?php if( $hc_rse_widget_events == 'upcoming-reverse' ) echo 'selected="selected"'; ?> value="upcoming-reverse"><?php _e( 'Upcoming (Reverse Order)' , 'hc_rse' ); ?></option>
		<option <?php if( $hc_rse_widget_events == 'past' ) echo 'selected="selected"'; ?> value="past"><?php _e( 'Past' , 'hc_rse' ); ?></option>
		<option <?php if( $hc_rse_widget_events == 'past-reverse' ) echo 'selected="selected"'; ?> value="past-reverse"><?php _e( 'Past (Reverse Order)' , 'hc_rse' ); ?></option>
	</select>
	<br /><br />

	<?php _e( 'Number of Events' , 'hc_rse' ); ?>:<br />
	<select class="widefat" name="hc_rse_widget_event_limit">
		<option <?php if( $hc_rse_widget_event_limit == -1 ) echo 'selected="selected"'; ?>  value="-1"><?php _e( 'All' , 'hc_rse' ); ?></option>
		<?php for( $i = 1; $i <= 99; $i++ ): ?>
			<?php if( $hc_rse_widget_event_limit == $i ): ?>
				<option selected="selected" value="<?php echo $i; ?>"><?php echo $i; ?></option>
			<?php else: ?>
				<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
			<?php endif; ?>
		<?php endfor; ?>
	</select>
	<br /><br />


	<?php _e( 'Event Page Link' , 'hc_rse' ); ?>:<br />
	<?php
		$args = array('selected' => $hc_rse_widget_event_page,
	    			  'name' => 'hc_rse_widget_event_page',
	    			  'show_option_none' => __( 'No Link' , 'hc_rse' ),
	    			  'option_none_value' => -1
	    			  );
	    wp_dropdown_pages( $args );
	?>

	<input type="hidden" name="submitted" value="1" />
	<?php
}

/**
 * Parses the shortcode and displays the events.  The defalt is to only show
 * events which are happening from the current time onwards.  To change this
 * the user can suppy the 'showevents' attribute with one of the following
 * values:
 *
 * 'all' - past AND upcoming events will be displayed
 * 'past' - past events only will be displayed
 * 'upcoming' only upcoming events will be displayed (the default action)
 *
 * Note: Adding '-reverse' will reverse the order of any of these values
 *
 * For advanced useers there's also the 'noassets' attribute which when set to
 * 'true' will not include the custom js and css to make the showing and hiding
 * of the extra info work.
 *
 * @global type $wpdb
 * @param type $attibutes
 * @return string
 */
function hc_rse_display_events( $attibutes ){
	global $wpdb;
	$defaultColList = array( 'date','time','title','moreinfo');

	$showevents = '';
	$noassets = '';
	$columns = '';
	$numevents = -1;

	extract( shortcode_atts( array( 'showevents' => 'upcoming' ,
		                            'noassets' => 'false',
		                            'columns' => 'date,time,title,link,moreinfo',
		                            'numevents' => -1,
                                    'startdate' => -1,
                                    'enddate' => -1
		                          ) ,
			                 $attibutes
			               )
		   );

	$showevents = strip_tags($showevents);

	//By default include the custom CSS and JS
	if( $noassets == 'false' ){
		wp_enqueue_style( "hc_rse_styles" ,
						  plugin_dir_url( __FILE__ ) . "style.css" );
		wp_enqueue_script( "hc_rse_event_table" ,
						   plugin_dir_url( __FILE__ ) . "js/event-table.js" ,
						   array( 'jquery' ) ,
						   '1' ,
						   true );
		wp_localize_script( "hc_rse_event_table" ,
						    'objectL10n' ,
						    array( 'MoreInfo' => stripslashes ( get_option( 'hc_rse_more_info_link' , __( 'More Info' , 'hc_rse' ) ) ),
						 	       'HideInfo' => stripslashes ( get_option( 'hc_rse_hide_info_link' , __( 'Hide Info' , 'hc_rse' ) ) )
							     )
						  );
	}

	$upcoming_events = hc_rse_get_events( $showevents , $numevents, $startdate, $enddate );


	//If the user has passed something into the columns argument, use it
	$useDefaultColumns = false;

	if ($columns !== '' ){
		//If we've got a list of cols, split them out
		if( mb_strpos( $columns , ',' ) === false ){
			//We might only have one column to show, make sure it's valid
			if( !in_array( $columns , $defaultColList ) ){
				$useDefaultColumns = true;
			}
		}elseif( mb_strpos( $columns , ',' ) !== 0 ){
			$columns = explode( ',' , $columns );
		}else{
			$useDefaultColumns = true;
		}
	}else{ //Default to showing everything
		$useDefaultColumns = true;
	}

	//Something's not right, just show the defaults
	if( $useDefaultColumns ){
		$columns = $defaultColList;
	}


	$eventHTML = "";

	if( $upcoming_events ){
		$eventHTML .= '<table class="hc_rse_events_table">';
		$isShowingATime = false;

		//Loop through and see if we're showing a time
		foreach( $upcoming_events as $event ){
			foreach( $columns as $column ){
				if( $column == 'time' && $event->show_time == 1 ){
					$isShowingATime = true;
				}
			}
		}

		//For each event, output the correct columns
		foreach( $upcoming_events as $event ){
			//Show only the relevent columns
			$showMoreInfo = false;
			$eventHTML .= '<tr>';
			foreach( $columns as $column ) {
				switch( $column ){
					case 'date':
						$eventHTML .= '    <td class="hc_rse_date">';
						$eventHTML .=          date_i18n( get_option( 'hc_rse_date_format' ) ,
								                     strtotime( $event->start_date ) );
						$eventHTML .= '    </td>';
						break;
					case 'time':
						//Add column if we're showing a time
						if( $isShowingATime ) $eventHTML .= '    <td class="hc_rse_time">';
						//Only show time if it has been set in the event settings
						if( $event->show_time == 1 ){

							$eventHTML .=          date_i18n( get_option( 'hc_rse_time_format' ) ,
									                     strtotime( $event->start_date ) );
						}
						//close column if we're showing a time
						if( $isShowingATime ) $eventHTML .= '    </td>';
						break;
					case 'title':
						$eventHTML .= '    <td class="hc_rse_title">';
						$eventHTML .=          apply_filters( 'the_content' , stripslashes( $event->title ) );
						$eventHTML .= '    </td>';
						break;
					case 'link':
						$eventHTML .= '    <td class="hc_rse_link">';
						$eventHTML .=          hc_rse_display_link( $event->link );
						$eventHTML .= '    </td>';
						break;
					case 'moreinfo':
						$showMoreInfo = true;
						$eventHTML .= '    <td>';
						$eventHTML .=          ( $event->extra_info != "" ) ? '<a id="' . $showevents . '_more_' . $event->id . '" class="hc_rse_more_info" href="#more">' . stripslashes( get_option( 'hc_rse_more_info_link' , __( 'More Info' , 'hc_rse' ) ) ). '</a>': '&nbsp';
						$eventHTML .= '    </td>';
						break;
				}
			}
			//Add the info if we're showing it...
			if( $showMoreInfo ){
				$eventHTML .= '</tr>';
				$eventHTML .= '<tr>';
				$eventHTML .= '    <td colspan="4" id="hc_rse_extra_info_' . $showevents . '_' . $event->id . '" class="hc_rse_extra_info hidden">';
				$eventHTML .=          apply_filters( 'the_content' , stripslashes( $event->extra_info ) );
				$eventHTML .= '    </td>';
			}
			$eventHTML .= '</tr>';
		}
		$eventHTML .= '</table>';
	}
	return $eventHTML;
}

/**
 * Depending on the list type, get the events
 *
 * @param str $listType - [all|all-reverse|upcoming|upcoming-reverse|past|past-reverse]
 * @param int $showEvents (optional) - the number of events to show
 * @return $wpdb result set $events - Result set of the query
 */
function hc_rse_get_events($listType, $showEvents = -1, $startdate = -1, $enddate = -1){
	global $wpdb;

	$table_name = $wpdb->prefix . HC_RSE_TABLE_NAME;

	switch( $listType ){
		case 'all':
			$eventQuery = "SELECT * FROM $table_name ORDER BY start_date ASC";
			break;
		case 'all-reverse':
			$eventQuery = "SELECT * FROM $table_name ORDER BY start_date DESC";
			break;
		case 'past':
			$eventQuery = "SELECT * FROM $table_name WHERE start_date < NOW() ORDER BY start_date DESC";
			break;
		case 'past-reverse':
			$eventQuery = "SELECT * FROM $table_name WHERE start_date < NOW() ORDER BY start_date ASC";
			break;
		case 'upcoming-reverse':
			$eventQuery = "SELECT * FROM $table_name WHERE start_date >= NOW() ORDER BY start_date DESC";
			break;
        case 'daterange':
            //Just start date provided, everything after it
            if($startdate != -1 && $enddate == -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date >= '$startdate' ORDER BY start_date ASC";
            }

            //Just end date provided, everything before it
            if($startdate == -1 && $enddate != -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date <= '$enddate' ORDER BY start_date ASC";
            }

            //Start AND end date provided, get all in between
            if($startdate != -1 && $enddate != -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date BETWEEN '$startdate' AND '$enddate' ORDER BY start_date ASC";
                var_dump("$eventQuery");
            }


            //No start date or enddate provided, show the default
            if($startdate == -1 && $enddate == -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date >= NOW() ORDER BY start_date ASC";
            }

            break;
        case 'daterange-reverse':
            //Just start date provided, everything after it
            if($startdate != -1 && $enddate == -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date >= '$startdate' ORDER BY start_date DESC";
            }

            //Just end date provided, everything before it
            if($startdate == -1 && $enddate != -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date <= '$enddate' ORDER BY start_date DESC";
            }

            //Start AND end date provided, get all in between
            if($startdate != -1 && $enddate != -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date BETWEEN '$startdate' AND '$enddate' ORDER BY start_date DESC";
                var_dump("$eventQuery");
            }

            //No start date or enddate provided, show the default
            if($startdate == -1 && $enddate == -1){
                $eventQuery = "SELECT * FROM $table_name WHERE start_date >= NOW() ORDER BY start_date DESC";
            }

            break;
		default:
			$eventQuery = "SELECT * FROM $table_name WHERE start_date >= NOW() ORDER BY start_date ASC";
			break;
	}

	//If there's a limit set, obey it!
	if( $showEvents != -1 && is_numeric( $showEvents ) ){
		$eventQuery .= ' LIMIT ' . $showEvents;
	}

	return $wpdb->get_results( $eventQuery );
}

function hc_rse_setup_custom_assets(){
	wp_enqueue_style( "hc_rse_styles" ,
			          plugin_dir_url( __FILE__ ) . "style.css" );
	wp_enqueue_style( "jquery-ui-custom" ,
			          plugin_dir_url( __FILE__ ) . "css/jquery-ui-1.8.22.custom.css" );
	wp_enqueue_script("time-picker-addon" ,
			          plugin_dir_url( __FILE__ ) . "js/jquery-ui-timepicker-addon.js" ,
			          array( 'jquery' ,
						     'jquery-ui-core' ,
						     'jquery-ui-slider' ,
						     'jquery-ui-datepicker'
 						   ) ,
			          '1' ,
			          true);
	wp_enqueue_script("hc_rse_js" ,
			          plugin_dir_url( __FILE__ ) . "js/script.js" ,
			          array( 'jquery' ,
						     'jquery-ui-core' ,
						     'jquery-ui-datepicker' ,
						     'time-picker-addon'
 						   ) ,
			          '1' ,
			          true);
	wp_localize_script("hc_rse_js" ,
			           'objectL10n' ,
					   array('UpcomingEvents' => __('Upcoming Events', 'hc_rse'),
						     'EventsUpcoming' => __('Events (Upcoming)', 'hc_rse'),
						     'PastEvents' => __('Past Events', 'hc_rse'),
						     'EventsPast' => __('Events (Past)', 'hc_rse'),
 						     'DeleteConfirm' => __('Are you sure you want to delete this event?', 'hc_rse'))
			          );

	wp_enqueue_script("hc_rse_options_js" ,
			          plugin_dir_url( __FILE__ ) . "js/options.js" ,
			          array( 'jquery' ) ,
			          '1' ,
			          true);
}

/**
 * loads tranlation file
 *
 * @return void
 */
function hc_rse_load_translations(){
	$plugin_dir = basename(dirname(__FILE__));
	$x = load_plugin_textdomain( 'hc_rse', false, $plugin_dir . '/translations/' );
	//exit($x);
}

/**
 * Checks if we need to update the db schema
 *
 * If the site_option value doesn't match the version defined at the top of
 * this file, the install routine is run.
 *
 * @global string $hc_rse_db_version
 * @return void
 */
function hc_rse_update_db_check(){
	global $hc_rse_db_version;
	if ( get_site_option( 'hc_rse_db_version' ) != $hc_rse_db_version ) {
		hc_rse_plugin_install();
	}
}

/**
 * Checks for first run
 *
 * @return void
 */
function hc_rse_first_run_check(){
	if ( get_site_option( 'hc_rse_first_run' , 'fasly' ) === 'fasly' ) {
		//Set site option so show we've run this plugin at least once.
		update_site_option( 'hc_rse_first_run' , 'woot' );
	}
}

/**
 * Creates the db schema
 *
 * @global type $wpdb
 * @global string $hc_rse_db_version
 *
 * @return void
 */
function hc_rse_plugin_install(){
	global $wpdb;
	global $hc_rse_db_version;
	$table_name = $wpdb->prefix . HC_RSE_TABLE_NAME;

	$sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        start_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        show_time int(1) DEFAULT NULL,
        title varchar(255) NOT NULL,
        link varchar(255) DEFAULT '' NOT NULL,
        extra_info text,
        UNIQUE KEY id (id)
       )CHARSET=utf8 ";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$ret = dbDelta( $sql );

	update_site_option( 'hc_rse_db_version' , $hc_rse_db_version );
	add_option( 'hc_rse_date_format' , 'jS M Y' );
	add_option( 'hc_rse_time_format' , 'H:i' );
}

////////////////////  ADMIN MENU FUNCTIONALITY ////////////////////////////////
function hc_rse_build_admin_menu(){
	$user_capability = 'manage_options';

	//Add Events main admin menu page
	add_menu_page( __( 'Events' , 'hc_rse' ) ,
		           __( 'Events' , 'hc_rse' ) ,
		           $user_capability ,
		           'hc_rse_event' ,
		           'hc_rse_events' ,
		           plugins_url( 'images/icon.png' , __FILE__ )
		         );

	//Add view events page to main admin menu.
	add_submenu_page( 'hc_rse_event' ,
		              __( 'View Events' , 'hc_rse' ) ,
		              __( 'All Events' , 'hc_rse' ) ,
		              $user_capability ,
		              'hc_rse_event',
		              'hc_rse_events'
		            );

	//The add event page to main admin menu.
	add_submenu_page( 'hc_rse_event' ,
		              __( 'Add Event' , 'hc_rse') ,
		              __( 'Add New' , 'hc_rse' ) ,
		              $user_capability ,
		              'hc_rse_add_event' ,
		              'hc_rse_add_event'
		            );
	add_submenu_page( 'hc_rse_event' ,
		              __( 'Events Settings' , 'hc_rse' ) ,
		              __( 'Settings' , 'hc_rse' ) ,
		              $user_capability ,
		              'hc_rse_settings' ,
		              'hc_rse_settings'
		            );
	add_submenu_page( 'hc_rse_event' ,
		              __( 'Help/Usage' , 'hc_rse' ) ,
		              __( 'Help/Usage' , 'hc_rse' ) ,
		              $user_capability ,
		              'hc_rse_help' ,
		              'hc_rse_help'
		            );
}

//Menu callbacks
function hc_rse_events(){
	require_once plugin_dir_path( __FILE__ ) . 'admin/view_events.php';
}

function hc_rse_add_event(){
	require_once plugin_dir_path( __FILE__ ) . 'admin/add_event.php';
}

function hc_rse_settings(){
	require_once plugin_dir_path( __FILE__ ) . 'admin/options.php';
}

function hc_rse_help(){
	require_once plugin_dir_path( __FILE__ ) . 'admin/help.php';
}
