<?php
/*
Plugin Name: Proxy Vote 
Plugin URI:  https://github.com/unsaturated/ProxyVote
Description: Collects proxy voting information for small events.  
Version:     1.1
Author:      Matthew Crumley
Author URI:  https://matt.unsaturated.com
License:     GPL3

Proxy Vote is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Proxy Vote is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Proxy Vote. If not, see 
https://github.com/unsaturated/ProxyVote/blob/master/LICENSE.
*/

// BEGINNING of USER-CHANGEABLE VARIABLES 
// The End-User License Agreement allows you to change the text of the following ten variables.
$proxy_userform_expires_label     = "Expiration date/time:";
$proxy_userform_key_label         = "Enter your unique key:";
$proxy_userform_name_label        = "Enter your full name:";
$proxy_userform_proxyname_label   = "Enter your proxy's full name:";
$proxy_userform_expired_message   = "Sorry, the time for submitting a proxy has expired.";
$proxy_userform_thankyou_message  = "Thank you! Your proxy was successfully recorded.";
$proxy_userform_error_key         = "The submitted key is invalid.";
$proxy_userform_error_missing     = "Please enter information for all form fields.";
$proxy_userform_error_keyused     = "A proxy was already submitted using that key.";
$proxy_userform_submit_button     = "Submit Proxy";
// END of USER-CHANGEABLE VARIABLES


$proxy_db_version = "1.0";
$proxy_table_name_votes = "proxyvotes";
$proxy_table_name_events = "proxyevents";
$proxy_locale_domain = "proxyvote";
$proxy_event_message = "";
$proxy_event_message_class = "";
$proxy_is_editing = -1;
$proxy_is_exporting = -1;
$proxy_is_registering = -1;
$proxy_has_errors = false;
$proxy_error_message = "";
$proxy_print_friendly;

/** 
 * All the requisite WP registrations and action hooks.
 */
register_activation_hook(plugin_basename(__FILE__), 'proxy_event_activate');
register_deactivation_hook(plugin_basename(__FILE__), 'proxy_event_deactivate');
add_action('admin_head', 'proxy_style');
add_action('admin_menu', 'proxy_action_admin_menu');
add_filter('the_content', 'proxy_check_content');

/**
 * If not previously activated, this will set the plugin's database 
 * version as a WordPress option.   
 *
 * This is called by the WP action hook "register_activation_hook".
 */         
function proxy_event_activate()
{
  global $proxy_db_version;
  
  $installed_version = get_option($proxy_db_version);
  
  if(empty($installed_version))
  {
    // Set the option to 0.0 so the plugin knows that tables should be created
    add_option("proxy_db_version", "0.0");
  }
}

/**
 * Removes the plugin's version number from the database.  At this point the 
 * database tables may be dropped.  If so, remove the WP option, otherwise keep 
 * the option for later reactivation or upgrade.
 *
 * This is called by the WP action hook "register_deactivation_hook".
 */
function proxy_event_deactivate()
{
  $installed_version = get_option("proxy_db_version");
  
  // Check if the tables were removed (db version 0.0) then remove the option
  if($installed_version == "0.0")
  {
    delete_option("proxy_db_version");
  }
}

/**
 * Overrides the WP administration CSS with something suitable for printing 
 * numerous pages of unique proxy voting keys.  Specifically, the .pagecontent
 * class includes page breaks.  It also has specialized CSS for critical 
 * events like dropping tables.
 *
 * This is called by the WP action "admin_head".
 */ 
function proxy_style()
{
  if($_GET['printfriendly'] > 0)
  {
    print "<style type=\"text/css\" media=\"screen\">\n";
    print "  .pagecontent {display: none; }\n";
    print "  #printremind {background: #fffeeb; border: 1px dotted; margin: 20px; padding: 10px; }\n";
    print "</style>\n"; 
        
    print "<style type=\"text/css\" media=\"print\">\n";
    print "  @page        { size: auto; }\n";
    print "   #wphead     { display: none; }\n";
    print "   #wpbody     { margin: 0px; padding: 0px; }\n";
    print "   #user_info  { display: none; }\n";
    print "   #adminmenu  { display: none; }\n";
    print "   #dashmenu   { display: none; }\n";
    print "   #submenu    { display: none; }\n";
    print "   #sidemenu   { display: none; }\n";
    print "   #footer     { display: none; }\n";
    print "   #update-nag { display: none; }\n";
    print "   #printremind{ display: none; }\n";
    print "   .pagecontent{ border: 1px solid transparent; page-break-inside: avoid; height: auto;}\n";
    print "   #pagebreak  { page-break-after: always; }\n";
    print "</style>\n"; 
  }
}

/**
 * Adds the management page to the WP menu.
 * 
 * This is called by the WP action "admin_menu". 
 */
function proxy_action_admin_menu()
{
  // It seems order matters, otherwise expect strange menu behavior
  add_options_page("Proxy Votes", "Proxy Votes", 1, plugin_basename(__FILE__), 'proxy_show_options_page'); 
  add_management_page("Proxy Votes", "Proxy Votes", 1, plugin_basename(__FILE__), 'proxy_show_management_page');  
}

/**
 * Display and process the Management page.
 */     
function proxy_show_management_page()
{
  global $proxy_print_friendly, $proxy_db_version;
  
  $installed_version = get_option("proxy_db_version");
  
  if($installed_version == "0.0")
  {
    // Show reminder to first create the plugin tables; maybe use this space for database updates
    print "<div class=\"wrap\">\n";       
    print   "<h2>Installation Reminder</h2></p>\n";
    print   "<p>Plugin installation is almost complete.  Click on the <a href=\"options-general.php?page=".plugin_basename(__FILE__)."\">Settings</a> page for this plugin.  In less than 30 seconds you'll be ready to go!</p> \n";      
    print "</div>\n";
    
    return;
  }
  
  proxy_event_handler();
  
  // If printer friendly, just display the custom proxies
  if($proxy_print_friendly)
    return;
  
  proxy_show_event_message();

  // Display sections for editing, exporting, or for regular management page
  if(proxy_is_editing())
  {
    proxy_management_page_view_event(proxy_get_editing_id());    
  }
  else if(proxy_is_exporting())
  {
    proxy_management_page_export_event(proxy_get_exporting_id());
  }
  else
  {
    proxy_management_page_show_events();
    proxy_management_page_add_event();
  }
}

/**
 *  Shows the option to create or drop the database tables for this plugin. 
 */
function proxy_show_options_page()
{
  proxy_event_handler();
  proxy_show_event_message();
  
  $installed_version = get_option("proxy_db_version"); 
  
  if($installed_version == "0.0")
  {
    // Show section to create tables
    print "<div class=\"wrap\">\n";
    print "<h2>Add Proxy Vote Tables</h2></p>\n";
    print "<p>Two tables will be created for this plugin.  One stores the list of events and the other stores submitted proxy information.\n";            
    print "To complete the activation process for this plugin, you must create the database tables.</p> \n";
    print "<form name=\"setupplugin\" id=\"setupplugin\" method=\"post\">\n";
    print "<p class=\"submit\">\n";
    print "<input type=\"hidden\" name=\"proxy_event\" value=\"setupplugin\" />\n";
    print "<input type=\"submit\" name=\"submit\" tabindex=\"4\" value=\"Create Tables\" />\n";
    print "</p>\n";
    print "</form>\n";
    print "</div>\n";  
  }
  else
  {
    // Show section to remove tables
    print "<div class=\"wrap\">\n";
    print "<h2>Remove Proxy Vote Tables</h2></p>\n";
    print "<p>Two tables were created for this plugin. You can deactivate this plugin and leave all data intact or remove the tables and their data. \n";
    print "Remember, you can export your events to XML files by going to the 'Edit' page for each event.\n";
    print "However, there is no feature which allows that data to be re-imported.</p> \n";
    print "<p><b>To remove all Proxy Vote tables click the button below.  This procedure cannot be undone.</b></p> \n";
    print "<form name=\"removeplugin\" id=\"removeplugin\" method=\"post\">\n";
    print "<p class=\"submit\">\n";
    print "<input type=\"hidden\" name=\"proxy_event\" value=\"removeplugin\" />\n";
    print "<input type=\"submit\" name=\"submit\" tabindex=\"4\" value=\"Remove Tables\" />\n";
    print "</p>\n";
    print "</form>\n";
    print "</div>\n";       
  }
}
 
/**
 * Handles all POST and GET events associated with this plugin.  
 */   
function proxy_event_handler()
{   
  global $proxy_print_friendly; 
  
  $proxy_event = $_POST['proxy_event'];
      
  if(empty($proxy_event)) 
    $proxy_event = $_GET['proxy_event'];             
  
  if(empty($proxy_event)) 
    $proxy_event = '';
  
  switch($proxy_event)
  {
    // Add a new proxy voting event
    case "addevent":
      $title = $_POST['title'];
      $desc = $_POST['description'];
      $voters = $_POST['voter_count'];
      $expires = $_POST['expires'];
      proxy_add_event($title, $desc, $voters, $expires);        
    break;
    
    // Delete an existing event
    case "deleteevent":
      $id = $_GET['eventid'];
      proxy_delete_event($id);
    break;
    
	  // Update an existing event
    case "updateevent":
      $id = $_POST['eventid'];
      $title = $_POST['title'];
      $desc = $_POST['description'];
      $expires = $_POST['expires'];
      proxy_update_event($id, $title, $desc, $expires);
    break;
    
	  // Show the printer friendly key cards for each proxy voter
    case "showkeycards":
      $id = $_GET['eventid'];
      $proxy_print_friendly = $_GET['printfriendly'];
      proxy_show_key_cards($id);        
    break;
	
    // Show the results of the proxy event
    case "showproxyresults":
      $id = $_GET['eventid'];
      $proxy_print_friendly = $_GET['printfriendly'];
      proxy_show_results($id);
    break;
    
	  // Edit the specified proxy event
    case "editevent":
      $id = $_GET['eventid'];
      proxy_edit_event($id);
    break;
	
    case "exportxml":
      $id = $_GET['eventid'];
      proxy_export_event($id);
    break;
    
	  // Creates database tables, prepares plugin for usage, etc.
    case "setupplugin":
      proxy_vote_setup();
    break;
    
	  // Drops database tables
    case "removeplugin":
      proxy_remove_plugin();
    break;
    
	  // Accepts a user's input as 
    case "registerproxy":
      $id = $_POST['eventid'];
      $votekey = $_POST['votekey'];
      $voter = $_POST['voter'];
      $proxy = $_POST['proxy'];      
      unset($_POST['eventid']);
      unset($_POST['votekey']);
      unset($_POST['voter']);
      unset($_POST['proxy']);  
      proxy_register($id, $votekey, $voter, $proxy);
    break;
 }    
} 
 
/**
 * Creates or upgrades database tables.
 */
function proxy_vote_setup()
{
  global $wpdb, $proxy_db_version, $proxy_table_name_votes, $proxy_table_name_events, $proxy_db_version;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;
  
  $installed_version = get_option("proxy_db_version");
  
  // Create tables; they should not yet exist
  if($installed_version == "0.0")
  {
    $tables = $wpdb->get_results('SHOW TABLES',ARRAY_N);
    if(!$tables)
      die("Proxy Vote plugin cannot query the database.");
    
    $table_exists = false;

    foreach($tables as $t)
    {
      if($table_name_votes == $t[0])
      {
        $table_exists = true;
        break;
      }          
    }
              
    // Set the option and install
    update_option("proxy_db_version", $proxy_db_version);
    
    if(!$table_exists)
    {      
      // Actual proxy info
      $wpdb->query(
              "CREATE TABLE $table_name_votes 
              (
              id      BIGINT(9) NOT NULL,
              votekey VARCHAR(10) NOT NULL,
              proxy   VARCHAR(50) NOT NULL,
              voter   VARCHAR(50) NOT NULL,
              ip      VARCHAR(15) NOT NULL,
              submit  DATETIME
              )");
              
      // Proxy event info 
      $wpdb->query(
             "CREATE TABLE  $table_name_events
             (
             id           BIGINT(9) NOT NULL AUTO_INCREMENT UNIQUE KEY,
             voter_count  MEDIUMINT(20) NOT NULL,
             title        VARCHAR(200) NOT NULL, 
             description  TEXT,
             expires      DATETIME NOT NULL
             )");
      
      proxy_set_event_message("Proxy Vote tables were created; go to the <a href=\"edit.php?page=".plugin_basename(__FILE__)."\">Manage</a> tab to create your first proxy event.");
    }
  }
  else
  {
    // Perform an upgrade as needed
    if($installed_version != $proxy_db_version)
    {
      // Add upgrade code when we pass version 1.0 of the db
      update_option("proxy_db_version", $proxy_db_version);
    }      
  }
}

/** 
 * Drops all database tables created for this plugin.
 */
function proxy_remove_plugin()
{
  global $wpdb, $proxy_db_version, $proxy_table_name_votes, $proxy_table_name_events;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;
  
  $wpdb->query("DROP TABLE $table_name_votes, $table_name_events");
  
  update_option("proxy_db_version", "0.0");
  
  proxy_set_event_message("Proxy Vote tables have been removed from the database.");
}

/**
 * Displays any event messages that are necessary.
 */
function proxy_show_event_message()
{
  global $proxy_event_message, $proxy_event_message_class;
  
  if(!empty($proxy_event_message))
  {
    print "<div id=\"message\" class=\"".$proxy_event_message_class."\"><p>".$proxy_event_message."</p></div>\n";
    $proxy_event_message = "";
  }
}

/**
 * Sets an event message.
 */
function proxy_set_event_message($message = '', $css_admin_class = 'updated fade', $append = FALSE)
{
  global $proxy_event_message, $proxy_event_message_class;
  
  if($append)
    $proxy_event_message = $proxy_event_message ."<br/>\n". $message;
  else
    $proxy_event_message = $message;
    
  $proxy_event_message_class = $css_admin_class;
}


/**
 * Creates the text and form elements necessary to add a new chart.
 */     
function proxy_management_page_add_event()
{      
  print "<div class=\"wrap\">\n";
  
  print "<h2>Add Proxy Event</h2>\n";
  
  print "<p>Add a proxy event by giving it a title, selecting the number of voters, and when the event should expire. \n";
  print "The description is used to generate a custom message for each voter, showing him his unique key and when the event expires.</p> \n";

  print "<form name=\"addevent\" id=\"addevent\" method=\"post\">\n";
  print "<table class=\"form-table\">\n";
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Title</label></th><td><input name=\"title\" id=\"title\" size=\"50\" maxlength=\"200\" tabindex=\"1\" aria-required=\"true\"/><br/>Brief title for your reference.</td></tr>\n";
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Description</label></th><td><textarea name=\"description\" id=\"content\" rows=\"5\" cols=\"50\" style=\"width: 97%;\" tabindex=\"2\" wrap=\"on\" aria-required=\"true\"/></textarea><br/>Can contain HTML, image links, etc. To insert the expiration time and proxy key use <tt>[expires]</tt> and <tt>[key]</tt> wherever you prefer.</td></tr>\n";    
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Expiration</label></th><td><input name=\"expires\" id=\"expires\" size=\"30\" maxlength=\"30\" tabindex=\"3\" aria-required=\"true\"/><br/>Year-Month-Day Hour:Minute:Second (or natural language, e.g., next Tuesday 4:30PM)</td></tr>\n";
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Number of Voters</label></th><td><input name=\"voter_count\" id=\"voter_count\" size=\"5\" maxlength=\"5\" tabindex=\"4\" aria-required=\"true\"/></td></tr>\n";
  print "</table>\n";
  print "<p class=\"submit\">\n";
  print "<input type=\"hidden\" name=\"proxy_event\" value=\"addevent\" />\n";
  print "<input type=\"submit\" name=\"submit\" tabindex=\"4\" value=\"Add Event\" />\n";
  print "</p>\n";
  print "</form>\n";
  
  print "</div>\n";
}

/**
 * Displays all proxy votes and activities associated with a proxy event.
 */
function proxy_management_page_view_event($id)
{
  global $wpdb, $proxy_table_name_votes;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  
  $event = proxy_get_event($id);
  
  print "<div class=\"wrap\">\n";
  print "<h2>Activities for $event->title</h2>\n";
  print "<ul>\n";    
  print "  <li>Add the proxy form to a post or page by inserting the following text: [proxy$id]</li>\n";
  print "  <li><a href=\"edit.php?page=".plugin_basename(__FILE__)."&proxy_event=showkeycards&eventid=".$id."&printfriendly=1\">View proxy messages</a> in a printer-friendly list.  The proxy event description will be printed along with the custom proxy keys and expiration date.  When you go to your browser's Print Preview option, you will see each proxy message on a single page.</li>\n";
  print "  <li><a href=\"edit.php?page=".plugin_basename(__FILE__)."&proxy_event=showproxyresults&eventid=".$id."&printfriendly=1\">View proxy results</a> in a printer-friendly list.</li>\n";  
  print "  <li><a href=\"#updatesection\">Update</a> the description, title, or expiration date.</li>\n";
  print "  <li><a href=\"edit.php?page=".plugin_basename(__FILE__)."&proxy_event=exportxml&eventid=".$id."\">Export to XML</a> so you can archive or open the results using a separate application.</li>\n";
  print "</ul>\n"; 
  print "</div>\n";    
  
  print "<div class=\"wrap\">\n";
  print "<h2>Results for $event->title</h2><br/>\n";
  print "<table class=\"widefat\">\n";
  print " <thead>\n";
  print " <tr>\n";
  print "  <th width=\"10%\">Key</th>\n";
  print "  <th width=\"25%\">Voter</th>\n";
  print "  <th width=\"25%\">Proxy</th>\n";
  print "  <th width=\"12%\">IP</th>\n";
  print "  <th width=\"28%\">Submit Time</th>\n";
  print " </tr>\n";
  print " </thead>\n";
  print " <tbody>\n";

  $results = $wpdb->get_results(
                                "SELECT 
                                votekey, 
                                voter, 
                                proxy, 
                                ip,
                                submit  
                                FROM $table_name_votes  
                                WHERE id = $id AND submit IS NOT NULL
                                ORDER BY submit DESC");
    
  if($results)
  {
    $class = 'alternate';
    foreach($results as $result)
    {
      $class = ('alternate' == $class) ? '' : 'alternate';
      $formatted_date = mysql2date(get_option('links_updated_date_format'), $result->submit);
      print "<tr class=\"".$class."\">";
      print "<td><b>".$result->votekey."</b></td>";
      print "<td>".stripslashes($result->voter)."</td>";
      print "<td>".stripslashes($result->proxy)."</td>";
      print "<td>".stripslashes($result->ip)."</td>";
      print "<td>".$formatted_date."</td>";
      print "</tr>\n";
    }
  } 
  else
  {
    print "<tr><td colspan=\"5\">No proxies have been submitted for this event.</td></tr>\n";
  }

  print " </tbody>\n";
  print " </table>\n";    
  print "</div>\n";        
  
  print "<a name=\"updatesection\"></a>\n";
  print "<div class=\"wrap\">\n";
  print "<h2>Update Proxy Event</h2></p>\n";
  print "<p>Update a proxy event by changing the title, description, or its expiration date. \n";
  print "The description is used to generate a custom message, which can be <a href=\"edit.php?page=".plugin_basename(__FILE__)."&proxy_event=showkeycards&eventid=".$id."&printfriendly=1\">printed</a> and distributed to each voter. \n";

  print "<form name=\"updateevent\" id=\"updateevent\" method=\"post\">\n";
  print " <table class=\"form-table\">\n";
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Title</label></th><td><input name=\"title\" id=\"title\" size=\"100\" maxlength=\"200\" tabindex=\"1\" value=\"". stripslashes($event->title) ."\" /><br/>Brief title for your reference.</td></tr>\n";
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Description</label></th><td><textarea name=\"description\" id=\"content\" rows=\"3\" cols=\"80\" tabindex=\"2\" wrap=\"on\" />". stripslashes($event->description) ."</textarea><br/>Can contain HTML, image links, etc. To insert the expiration time and proxy key use <tt>[expires]</tt> and <tt>[key]</tt> wherever you prefer.</td></tr>\n";    
  print "<tr class=\"form-field form-required\"><th><label for=\"cat_name\">Expiration</label></th><td><input name=\"expires\" id=\"expires\" size=\"30\" maxlength=\"30\" tabindex=\"3\" value=\"". stripslashes($event->expires) ."\" /><br/>Year-Month-Day Hour:Minute:Second (or natural language, e.g., next Tuesday 4:30PM)</td></tr>\n";
  print " </table>\n";
  print "<p class=\"submit\">\n";
  print "<input type=\"hidden\" name=\"proxy_event\" value=\"updateevent\" />\n";
  print "<input type=\"hidden\" name=\"eventid\" value=\"". $id . "\" />\n";
  print "<input type=\"submit\" name=\"submit\" tabindex=\"4\" value=\"Update Event\" />\n";
  print "</p>\n";
  print "</form>\n";
  print "</div>\n";
}

/**
 * Exports the event data to an XML file.
 */
function proxy_management_page_export_event($event_id)
{
  global $wpdb, $proxy_table_name_votes, $proxy_table_name_events;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  
  $event = proxy_get_event($event_id);
  
  $xml_export = "";
  
  $results = $wpdb->get_results(
                                "SELECT 
                                votekey, 
                                voter, 
                                proxy, 
                                ip,
                                submit  
                                FROM $table_name_votes  
                                WHERE id = $event_id
                                ORDER BY proxy ASC");

  $xml_export .= "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
  $xml_export .= "<event id=\"".stripslashes($event_id)."\" title=\"".stripslashes($event->title)."\" expires=\"".stripslashes($event->expires)."\">\n";
  $xml_export .= "<description><![CDATA[".stripslashes($event->description)."]]></description>\n";
  foreach($results as $result)
  {
	  $xml_export .= "\t<proxyvote key=\"".$result->votekey."\" voter=\"".stripslashes($result->voter)."\" proxy=\"".stripslashes($result->proxy)."\" ipaddress=\"".stripslashes($result->ip)."\" submitted=\"".$result->submit."\" />\n";
  }
  $xml_export .= "</event>";
  
  $plugin_path = ABSPATH . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__)) . '/proxyvote_export.php';
  $plugin_url = get_option('siteurl') . '/wp-content/plugins/' . plugin_basename(dirname(__FILE__)) . '/proxyvote_export.php';

  print "<div class=\"wrap\">\n";
  print "<h2>Exporting ". stripslashes($event->title). "</h2></p>\n";
 
  print "<form method=\"post\" action=\"$plugin_url\">";
  print " <table class=\"form-table\">\n";
  print "<tr><td><textarea name=\"export_xml\" id=\"export_xml\" rows=\"20\" cols=\"100\" tabindex=\"2\" wrap=\"off\" />". stripslashes($xml_export) ."</textarea></td></tr>\n";    
  print " </table>\n";
  print "<p class=\"submit\">\n";
  print "<input type=\"hidden\" name=\"event\" value=\"$event_id\" />\n";
  print "<input type=\"submit\" name=\"submit\" value=\"Export to XML File\" />\n";
  print "</p>\n";
  print "</form>\n";
  print "</div>\n";
}

/**
 * Shows the proxy information in printable "key card" format so proxy voters
 * can be given a unique page.
 */
function proxy_show_key_cards($event_id)
{
  global $wpdb, $proxy_table_name_votes, $proxy_table_name_events;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  
  $event = proxy_get_event($event_id);
    
  $results = $wpdb->get_results(
                                "SELECT 
                                votekey 
                                FROM $table_name_votes  
                                WHERE id = $event_id");
    
  if($results)
  {
    print "<div id=\"printremind\">\n";
    print "Proxy reminders will be printed to ". count($results). " separate pages. Go to your web brower's <b>Print Preview</b> feature to view the pages.\n<br/><br/>";
    print "For a cleaner presentation remove all the default headers (URL, date, page numbers, etc).\n";
    print "This setting is usually under File->Page Setup on your web browser.\n";
    print "</div>\n";
    
    $totalCount = count($results);
    $index = 1;
    foreach($results as $result)
    {      
      $formatted_date = mysql2date(get_option('links_updated_date_format'), $event->expires);
      $clean_desc = stripslashes($event->description);
      $clean_votekey = stripslashes($result->votekey);
      print "<div class=\"pagecontent\">\n";
      print "<p>".proxy_replace_description($clean_desc, $formatted_date, $clean_votekey)."</p>\n";
      print "</div>\n";
	  
      // No pagebreak at the end
      if($index != $totalCount)
      {
        print "<div id=\"pagebreak\"></div>\n";
      }
	    $index++;
    }
  }   
}

/**
 * Shows the proxy voter table in a printable format.
 */
function proxy_show_results($event_id)
{
  global $wpdb, $proxy_table_name_votes, $proxy_table_name_events;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  
  $event = proxy_get_event($event_id);

  print "<div id=\"printremind\">\n";
  print "The following table will be printed on multiple pages if necessary. ";
  print "For a cleaner presentation remove all the default headers (URL, date, page numbers, etc).  This setting is usually under File->Page Setup on your web browser.\n";
  print "</div>\n";
  
  print "<div class=\"pagecontainer\">\n";
  print "<h2>Proxy Results for $event->title</h2>\n";
  print "<table class=\"widefat\">\n";
  print " <thead>\n";
  print " <tr>\n";
  print "  <th width=\"10%\">Key</th>\n";
  print "  <th width=\"25%\">Voter</th>\n";
  print "  <th width=\"25%\">Proxy</th>\n";
  print "  <th width=\"12%\">IP</th>\n";
  print "  <th width=\"28%\">Submit Time</th>\n";
  print " </tr>\n";
  print " </thead>\n";
  print " <tbody>\n";

  $results = $wpdb->get_results(
                                "SELECT 
                                votekey, 
                                voter, 
                                proxy, 
                                ip,
                                submit  
                                FROM $table_name_votes  
                                WHERE id = $event_id AND submit IS NOT NULL
                                ORDER BY proxy ASC");
    
  if($results)
  {
    $class = 'alternate';
    foreach($results as $result)
    {
      $class = ('alternate' == $class) ? '' : 'alternate';
      $formatted_date = mysql2date(get_option('links_updated_date_format'), $result->submit);
      print "<tr class=\"".$class."\" id=\"breakrow\" >";
      print "<td><b>".$result->votekey."</b></td>";
      print "<td>".stripslashes($result->voter)."</td>";
      print "<td>".stripslashes($result->proxy)."</td>";
      print "<td>".stripslashes($result->ip)."</td>";
      print "<td>".$formatted_date."</td>";
      print "</tr>\n";
    }
  } 
  else
  {
    print "<tr><td colspan=\"5\">No proxies were submitted for this event.</td></tr>\n";
  }
  
  print " </tbody>\n";
  print "</table>\n";   
  print "</div>\n";
}

/**
 * Replaces the "expires" and "key" place holders with the actual data 
 * specific to a proxy event.
 */
function proxy_replace_description($description, $expiration, $votekey)
{
  $reg_expires = '/\[expires\]/i';
  $reg_votekey = '/\[key\]/i';
  // Case and space insensitive search for  "[expires]" and "[key]"
  if(preg_match($reg_expires, $description) > 0) 
  {
    $description = preg_replace($reg_expires, $expiration, $description);
  }
  if(preg_match($reg_votekey, $description) > 0) 
  {
    $description = preg_replace($reg_votekey, $votekey, $description);
  }
  		
  return $description;
}

/** 
 * Displays all proxy events in the database.
 */
function proxy_management_page_show_events()
{
  global $wpdb, $proxy_table_name_events, $proxy_table_name_votes;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;

  print "<div class=\"wrap\">\n";

  print "<h2>Proxy Events</h2><br/>\n";
  print "<table class=\"widefat\" >\n";
  print " <thead>\n";
  print " <tr>\n";
  print "  <th width=\"5%\">ID</th>\n";
  print "  <th width=\"45%\">Title</th>\n";
  print "  <th width=\"10%\">Proxies</th>\n";
  print "  <th width=\"10%\">Submitted</th>\n";
  print "  <th width=\"30%\">Expire Date</th>\n";
  print "  <th colspan=\"3\" width=\"20%\">Actions</th>\n";
  print " </tr>\n";
  print " </thead>\n";
  print " <tbody>\n";

  $results = $wpdb->get_results(
                                "SELECT 
                                id, 
                                title, 
                                voter_count, 
                                expires 
                                FROM ".$table_name_events." 
                                ORDER BY expires ASC");
                                
  if($results)
  {
    $class = 'alternate';
    foreach($results as $result)
    {
      // Get the number of submitted proxies
      $res = $wpdb->get_var("SELECT COUNT(submit) FROM $table_name_votes WHERE submit IS NOT NULL AND id = $result->id");
      
      $class = ('alternate' == $class) ? '' : 'alternate';
      $formatted_date = mysql2date(get_option('links_updated_date_format'), $result->expires);
      print "<tr class=\"".$class."\">";
      print "<td><b>".$result->id."</b></td>";
      print "<td>".stripslashes($result->title)."</td>";
      print "<td>".stripslashes($result->voter_count)."</td>";
      print "<td>".$res."</td>"; /*$res[0]['submitted']*/
      print "<td>".$formatted_date."</td>";
      print "<td><a href=\"edit.php?page=".plugin_basename(__FILE__)."&proxy_event=editevent&eventid=".$result->id."\" class=\"edit\">Edit</a></td>";
      print "<td><a href=\"edit.php?page=".plugin_basename(__FILE__)."&proxy_event=deleteevent&eventid=".$result->id."\" class=\"delete\" onclick=\"javascript:return confirm('Delete this event and all submitted proxies?')\">Delete</a></td>";
      print "</tr>\n";
    }
  } 
  else
  {
    print "<tr><td colspan=\"6\">There are no proxy events in the database.</td></tr>\n";
  }

  print " </tbody>\n";
  print " </table>\n";
  print " </div>\n";
}

/** 
 * Simple test for a positive number of proxies.
 */
function is_positive_number($iNumber) 
{
  if(preg_match("/^([0-9]+)$/i", $iNumber, $matches))
  {    
    return intval($matches[1]) > 0 ? true : false;
  }
         
  return false;
}

/**
 * Updates the proxy event details.
 */
function proxy_update_event($event_id, $title, $description, $expires)
{
  global $proxy_table_name_votes, $proxy_table_name_events, $wpdb;
  
  if(!strlen($title) || !strlen($expires))
  {
    proxy_set_event_message("The proxy event must have a title, voter count, and expiration date.", "error");
    return;
  }

  $clean_title = $wpdb->escape($title);
  $clean_voter_count = $wpdb->escape($voter_count);
  $clean_expires = $wpdb->escape($expires);
  $clean_description = $wpdb->escape($description);
  
  if(($unixtime = strtotime($clean_expires)) === false) 
  {
    // Time entered was garbage
    proxy_set_event_message("The expiration time is not a valid date/time.", "error");
    return;
  }
  
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;
  $sql = "UPDATE $table_name_events SET ".
         "title = '$clean_title',".
         "description = '$clean_description', ".
         "expires = FROM_UNIXTIME($unixtime) ".
         "WHERE id = '$event_id' ".
         "LIMIT 1";
  $wpdb->query($sql);
        
  proxy_set_event_message("Event updated in database.");    
}
  
/** 
 * Adds a proxy event to the database.
 */
function proxy_add_event($title, $description, $voter_count, $expires)
{
  global $proxy_table_name_votes, $proxy_table_name_events, $wpdb;
  
  if(!strlen($title) || !strlen($voter_count) || !strlen($expires))
  {
    proxy_set_event_message("The proxy event must have a title, voter count, and expiration date.", "error");
    return;
  }

  $clean_title = $wpdb->escape($title);
  $clean_voter_count = $wpdb->escape($voter_count);
  $clean_expires = $wpdb->escape($expires);
  $clean_description = $wpdb->escape($description);
  
  if(!is_positive_number($clean_voter_count))
  {
    // Definitely not a number
    proxy_set_event_message("The number of proxy voters must be greater than zero.", "error");
    return;
  }
  
  if(($unixtime = strtotime($clean_expires)) === false) 
  {
    // Time entered was garbage
    proxy_set_event_message("The expiration time is not a valid date/time.", "error");
    return;
  }
  
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;
  $sql = "INSERT INTO $table_name_events ".
         "(title, description, voter_count, expires) ".
         "VALUES('$clean_title', '$clean_description', '$clean_voter_count', FROM_UNIXTIME($unixtime))";
  $wpdb->query($sql);
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;

  $results = $wpdb->get_var("SELECT MAX(id) FROM $table_name_events");

  $votekeys = proxy_array_rand_keys($clean_voter_count);
  
  for($i = 0; $i < $clean_voter_count; $i++)
  {      
    $sql = "INSERT INTO $table_name_votes ".
           "(id, votekey)".
           "VALUES('$results', '$votekeys[$i]')";
    $wpdb->query($sql);
  }
        
  proxy_set_event_message("Event added to database; $clean_voter_count keys generated.");
}

function proxy_export_event($id)
{
  global $proxy_is_exporting;
  $proxy_is_exporting = $id;
}

function proxy_is_exporting()
{
  global $proxy_is_exporting;
  $ret = $proxy_is_exporting > -1 ? true : false;
  return $ret;
}

function proxy_get_exporting_id()
{
  global $proxy_is_exporting;
  return $proxy_is_exporting;
}

function proxy_edit_event($id)
{
  global $proxy_is_editing;
  $proxy_is_editing = $id;
}

function proxy_is_editing()
{
  global $proxy_is_editing;
  $ret = $proxy_is_editing > -1 ? true : false;
  return $ret;
}

function proxy_done_editing()
{
  global $proxy_is_editing;
  $proxy_is_editing = -1;
}

function proxy_get_editing_id()
{
  global $proxy_is_editing;
  return $proxy_is_editing;
}

/**
 * Deletes a specific proxy event from the database.
 */
function proxy_delete_event($id)
{
  global $proxy_table_name_votes, $proxy_table_name_events, $wpdb;
  
  if(empty($id))
    return;
    
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;
  $results = $wpdb->query("DELETE FROM $table_name_votes WHERE id = $id");
  $results = $wpdb->query("DELETE FROM $table_name_events WHERE id = $id");
  proxy_set_event_message("Event deleted from database."); 
}

/**
 * Gets a specific proxy event.
 */
function proxy_get_event($id)
{
  global $wpdb, $proxy_table_name_events;
  
  $table_name_votes = $wpdb->prefix . $proxy_table_name_events;
  $result = $wpdb->get_row("SELECT * FROM $table_name_votes WHERE id = $id LIMIT 1");
  return $result;
}

/**
 * Provides the requested number of random proxy keys.
 */
function proxy_array_rand_keys($count)
{
  $vals = array();
  for($i = 0; $i < $count; $i++)
  {
    $temp = proxy_str_makerand();
    while(in_array($temp, $vals))
    {
      $temp = proxy_str_makerand();         
    }
    $vals[] = $temp;
  }
  
  return $vals;
}

/**
 * Gets a random character set using the specified properties.
*/	
function proxy_str_makerand($minlength = 10, $maxlength = 10, $upper = FALSE, $numbers = TRUE)
{
  // Eliminated characters 'l', and 'o' to avoid numeric similarities in sans-serif fonts, 'v' and 'w' to avoid confusion 
  // when they are adjacent to each other, and 'q' because it is too similar to a 'g'
  $allchars = "abcdefghijkmnprstuxyz";  
  
  // Eliminated characters 'I' and 'O' to avoid numeric similarities, 'V' and 'W' to avoid confusion when adjacent to each other
  if($upper) $allchars .= "ABCDEFGHJKLMNPQRSTUVWXYZ";
  
  // Eliminated numbers '0', '1' to avoid alpha similarities
  if($numbers) $allchars .= "23456789";
  
  if($minlength > $maxlength) 
    $length = mt_rand($maxlength, $minlength);
  else 
    $length = mt_rand($minlength, $maxlength);
    
  for($i = 0; $i < $length; $i++) 
    $key .= $allchars[(mt_rand(0,(strlen($allchars)-1)))];
  
  return $key;
}

/**
 * Look for trigger text 
 */
function proxy_check_content($content) 
{
  global $proxy_is_registering;
  
  // Regular expression if using a standard HTML post/page editor
  $regex1 = '/\[proxy([0-9]{1,})\]/i';
  
  // Case and space insensitive search for  "[proxyN]"  , where N is a positive integer
  if(preg_match($regex1, $content, $matches)) 
  {
    if(!empty($matches[1]) && ($proxy_is_registering < 0))
      $content = preg_replace($regex1, proxy_show_form($matches[0], $matches[1]), $content);
	
	  return $content;
  }

  return $content;
}

/**
 * Builds the form used for submitting proxy information.
 */
function proxy_show_form($event_string, $event_id)
{
  global 
  $wpdb, 
  $proxy_is_registering, 
  $proxy_error_message,
  $proxy_table_name_votes, 
  $proxy_table_name_events,
  $proxy_has_errors,
  $proxy_userform_expires_label,
  $proxy_userform_key_label,
  $proxy_userform_name_label,
  $proxy_userform_proxyname_label,
  $proxy_userform_expired_message,
  $proxy_userform_thankyou_message,
  $proxy_userform_submit_button;
  
  // Build table names    
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;    
      
  proxy_event_handler();
  
  $is_valid_id = $wpdb->get_var("SELECT id FROM $table_name_events WHERE id = $event_id");
  
  // Proxy id is not valid, so return the matched regulared expression string
  if(empty($is_valid_id))
    return $event_string;

  $proxy_expired = false;
  
  // Get the expiration time from the database
  $expires = $wpdb->get_var("SELECT UNIX_TIMESTAMP(expires) FROM $table_name_events WHERE id = $event_id");
  
  $blog_time = proxy_blog_time();
  
  // If the local blog time is greater than the proxy expiration date/time, then inform the proxy registrant.
  if($blog_time > $expires) 
  {
    $proxy_has_errors = true;
    $proxy_error_message = $proxy_userform_expired_message;
    $proxy_is_registering = -1;    
    $proxy_expired = true;
  }

  $proxy_success = false;
  if(proxy_is_registering() && !proxy_has_errors())
  {
    $str = "<p style=\"color: green; font-weight: bold\">$proxy_userform_thankyou_message</p>\n";
    proxy_register_done();
    $proxy_success = true;
    
  } 
  else if(proxy_has_errors())
  {
    $str = "<p style=\"color: red; font-weight: bold\">$proxy_error_message</p>\n";
  }

  if(!$proxy_expired && !$proxy_success)
  {
    $str .= "<form name=\"registerproxy\" method=\"post\">\n";  
    $str .= "<table cellpadding=\"3\" cellspacing=\"3\">\n";
    $str .= " <tr><td style=\"text-align: left\">$proxy_userform_expires_label</td><td style=\"text-align: left\">". date(get_option('links_updated_date_format'), $expires). "</td></tr>\n";
    $str .= " <tr><td style=\"text-align: left\">$proxy_userform_key_label</td><td style=\"float: left\"><input name=\"votekey\" id=\"votekey\" size=\"30\" maxlength=\"10\" tabindex=\"1\"/></td></tr>\n";
    $str .= " <tr><td style=\"text-align: left\">$proxy_userform_name_label</td><td style=\"float: left\"><input name=\"voter\" id=\"voter\" size=\"30\" maxlength=\"50\" tabindex=\"2\"/></td></tr>\n";
    $str .= " <tr><td style=\"text-align: left\">$proxy_userform_proxyname_label</td><td style=\"float: left\"><input name=\"proxy\" id=\"proxy\" size=\"30\" maxlength=\"50\" tabindex=\"3\"/></td></tr>\n";
    $str .= " <tr><td style=\"text-align: left\"></td>\n";
    $str .= " <td>\n";    
    $str .= "  <input type=\"hidden\" name=\"proxy_event\" value=\"registerproxy\" />\n";
    $str .= "  <input type=\"hidden\" name=\"eventid\" value=\"$event_id\" />\n";
    $str .= "  <input type=\"submit\" name=\"submit\" tabindex=\"4\"  style=\"float: left\" value=\"$proxy_userform_submit_button\" />\n";
    $str .= " </td></tr></table></p>\n";
    $str .= "</form>\n";
  }

  return $str;
}

/**
 * Registers the proxy using the submitted information.
 */
function proxy_register($event_id, $votekey, $voter, $proxy)
{
  global 
  $wpdb, 
  $proxy_is_registering, 
  $proxy_has_errors, 
  $proxy_error_message, 
  $proxy_table_name_votes, 
  $proxy_table_name_events,
  $proxy_userform_expired_message,
  $proxy_userform_error_key,
  $proxy_userform_error_missing,
  $proxy_userform_error_keyused;
  
  // Build table names    
  $table_name_votes = $wpdb->prefix . $proxy_table_name_votes;
  $table_name_events = $wpdb->prefix . $proxy_table_name_events;    
      
  $proxy_is_registering = $event_id;
  
  $votekey_clean = $wpdb->escape(trim($votekey));
  $voter_clean = $wpdb->escape(trim($voter));
  $proxy_clean = $wpdb->escape(trim($proxy));
  
  // Get the poster's IP address for logging purposes    
  if ($ip = getenv('HTTP_CLIENT_IP')) {}
    elseif ($ip = getenv('HTTP_X_FORWARDED_FOR')) {}
    elseif ($ip = getenv('HTTP_X_FORWARDED')) {}
    elseif ($ip = getenv('HTTP_FORWARDED_FOR')) {}
    elseif ($ip = getenv('HTTP_FORWARDED')) {}
    else {
      $ip = $_SERVER['REMOTE_ADDR'];
  }
  
  // Test for valid input - All fields must have data and the date submitted must be
  // before the expire date set for this proxy
  if(empty($votekey_clean) || empty($voter_clean) || empty($proxy_clean) )
  {
    $proxy_has_errors = true;
    $proxy_error_message = $proxy_userform_error_missing;
    $proxy_is_registering = -1;
    return;
  }
  
  // Get the expiration time from the database
  $expires = $wpdb->get_var("SELECT UNIX_TIMESTAMP(expires) FROM $table_name_events WHERE id = $event_id");

  $blog_time = proxy_blog_time();  
  
  // If the local blog time is greater than the proxy expiration date/time, then inform the proxy registrant.
  if($blog_time > $expires) 
  {
    $proxy_has_errors = true;
    $proxy_error_message = $proxy_userform_expired_message;
    $proxy_is_registering = -1;
    return;    
  }
  
  // Check for a valid voter proxy id
  $keyvalid = $wpdb->get_var("SELECT votekey FROM $table_name_votes WHERE votekey = '$votekey_clean'");
  if(empty($keyvalid))
  {
    $proxy_has_errors = true;
    $proxy_error_message = $proxy_userform_error_key;
    $proxy_is_registering = -1;
    return;    
  }

  // Ensure the proxy is not re-submitted (the submit column will not be NULL, if submitted)    
  $submitted = $wpdb->get_var("SELECT submit FROM $table_name_votes WHERE id = '$event_id' AND votekey = '$votekey_clean'");
  if(!empty($submitted))
  {
    $proxy_has_errors = true;
    $proxy_error_message = $proxy_userform_error_keyused;
    $proxy_is_registering = -1;
    return;      
  }
  
  $sql = "UPDATE $table_name_votes 
          SET proxy = '$proxy_clean', 
              voter = '$voter_clean', 
              ip    = '$ip', 
              submit = FROM_UNIXTIME($blog_time) 
          WHERE votekey = '$votekey_clean'";
          
  $wpdb->query($sql);
  $proxy_has_errors = false;
}

function proxy_is_registering()
{
  global $proxy_is_registering;
  $ret = $proxy_is_registering > -1 ? true : false;
  return $ret;
}

function proxy_register_done()
{
  global $proxy_is_registering;    
  $proxy_is_registering = -1;  
}

function proxy_has_errors()
{
  global $proxy_has_errors;
  return $proxy_has_errors;
}

/**
 * Gets the UNIX timestamp of the blog, which accounts for UTC offsets specified 
 * in the WP administrative properties.
 */
function proxy_blog_time()
{
  $server_time = time();
  $gmt_time = $server_time - date('Z', $server_time);
  // Subtract the blog time offset in hours (then multiply to convert to seconds)
  $blog_time = $gmt_time + get_option('gmt_offset') * 60 * 60;
  
  return $blog_time;
}
