<?php
/*
Plugin Name: Last.fm iTunes Mashup
Plugin URI: http://www.beetlejoose.co.uk/lastfm-itunes-mashup
Description: A plugin for Wordpress that displays your most recent and top ten tracks with links to purchase from iTunes.
Author: BeetleJoose
Version: 0.1
Author URI: http://www.beetlejoose.co.uk


    Copyright 2009  Sebastian Grant  (email : seb_grant@me.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class lastFMiTunesPlugin {
    var $lfmit_lfm_key;
    var $lfmit_td_key;
    var $lfmit_lfm_uname;
    var $lfmit_show_artwork;
    var $data;
    var $pluginVersion = "0.1";
    var $pluginPath = "/wp-content/plugins/lfmit/";
    var $panelNames;
    
    function lastFMiTunesPlugin()
    {
        $this->pluginPath = get_bloginfo('wpurl')."/wp-content/plugins/lfmit/";
        $this->panelNames = array('tmrt'=>"Recent Tracks", //TODO: i18n-ise this
                                  'ttt'=>"Top 10 Tracks"); //TODO: add more panels
        
        $this->lfmit_td_key = get_option('lfmit_td_key');
        $this->lfmit_lfm_key = get_option('lfmit_lfm_key');
        $this->lfmit_lfm_uname = get_option('lfmit_lfm_uname');
        $this->lfmit_show_artwork = get_option('lfmit_show_artwork');
        $this->lfmit_last_cache = get_option('lfmit_last_cache');
                                  
        if(get_option('lfmit_cache_results')){
            // Only Cache the data every 5 minutes
                if(($this->lfmit_last_cache - strtotime("-".get_option('lfmit_cache_minutes')." mins",mktime())) < 0){
                    $this->cache_last_fm_data();  
                }
        }
    }
    
    function get_cached_data($panel="tmrt")
    { // get the data from the database
        global $wpdb;
        $SQL = "SELECT * FROM `".$wpdb->prefix."lfmit_data` WHERE `id` LIKE '%".$panel."_%' ORDER BY `rank`";
        $results = $wpdb->get_results($SQL,'ARRAY_A');
        return $results;
    }
    
    function last_fm_path($panel="tmrt")
    { // get the data from last.fm and return it to whoever asked for it
        $path = "http://ws.audioscrobbler.com/2.0/?method=";
        switch ($panel){
            case "tmrt":
                $path.="user.getrecenttracks&limit=10";
            break;
            
            case "ttt":
                $path.="user.gettoptracks";
            break;
        }
        
        $path.="&api_key=".$this->lfmit_lfm_key."&user=".$this->lfmit_lfm_uname;
        
        return $path;
    }
    
    function get_data_from_last_fm($panel="tmrt")
    {
        $path=$this->last_fm_path($panel);
        require_once('LastFMParser.cls.php'); // find out who provided this and give them kudos
        $xml_parser = new XMLReader();
        $xml_parser->open($path);
        $xml=xml2assoc($xml_parser);
        $lfmXML2lfmitArray = new LastFMParser($xml,$panel);
        return $lfmXML2lfmitArray->data;
    }
    
    function cache_last_fm_data()
    { // get the data from above and store in our last.fm iTunes database table
        global $wpdb;
        foreach($this->panelNames as $panelID=>$panelName){
            $data = $this->get_data_from_last_fm($panelID);
            for($i=0,$n=10;$i<$n;$i++){
                $SQL = "REPLACE INTO ".$wpdb->prefix."lfmit_data (id,rank,title,artist,album,large_image_url,image_url,td_url) VALUES ('".$panelID."_".($i+1)."',".($i+1).",'".$data[$i]['title']."','".$data[$i]['artist']."','".$data[$i]['album']."','".$data[$i]['large_image_url']."','".$data[$i]['image_url']."','".$data[$i]['td_url']."')";
                $wpdb->query($SQL);
            }
            update_option('lfmit_last_cache',mktime());    
        }
        
    }
    
    function show_panels()
    {
        $panels = array('tmrt','ttt');
        
        foreach($panels as $panel){ // loop through available panels and only show ones selected in admin
            if(get_option('lfmit_show_panel_'.$panel)) $showPanels[] = $panel;
        }
        
        // shows the panels as "tabs" - TODO: i18n-ise this
        ?><ul id="lfmitPanelTabs"> 
        <?php foreach($showPanels as $showPanelID => $showPanel){ ?>
            <li rel="<?= $showPanel ?>"><a href="http://www.last.fm/user/<?php echo $this->lfmit_lfm_uname; ?>"<?php if($showPanelID==0){  echo "class=\"lfmit_sel_tab\""; } ?>><?= $this->panelNames[$showPanel]; ?></a></li>
        <?php } 
        // TODO: Use JavaScript Library to hide and show panels on click ?>
        </ul>
        
        <?php reset($showPanels);
        foreach($showPanels as $showPanelID => $showPanel){ // layer the panels with pos:abs, then use JS to show one at a time ?>
        
            <div id="lfmitPanel<?= $showPanel; ?>" class="lfmitPanel<?= $showPanelID; ?>"<?php if($showPanelID>0) echo "style=\"display:none;\""; ?>><?php
                if(get_option('lfmit_cache_results')){ // if using the cached results...
                    $panelData = $this->get_cached_data($showPanel); // ...get the results from the database...
                } else { // ... otherwise ...
                    $panelData = $this->get_data_from_last_fm($showPanel); // ...get the results directly from last.fm...
                } // ... but return them both in the same format so they can be used below ?>
                
                <ol><?php

                for($i=0,$n=10;$i<=$n;$i++){
                    if($panelData[$i]) {
                        echo "<li class=\"chartItem".($i+1);
                        if(is_int($i/2)) echo " odd";
                        echo "\">";
                        if($this->lfmit_show_artwork){
                         if($panelData[$i]['large_image_url'] !=""){
                             echo "<img src=\"".$panelData[$i]['large_image_url']."\"/>";
                         } else {
                             echo "<img src=\"".$this->pluginPath."images/nocover.png\"/>";
                         }
                        }  
                        echo "<a href=\"".$panelData[$i]['td_url']."\"><span class=\"lfmit_track\">".$panelData[$i]['title'];
                        echo "</span><br/><span class=\"lfmit_artist\">";
                        echo $panelData[$i]['artist'];
                        echo "</span></a></li>";
                    }
                } ?></ol>
                <div id="lfmit_footer">Powered by <a href="http://last.fm" title="Last.fm">Last.fm</a> and <a href="http://www.apple.com/uk/itunes" title="Apple iTunes">iTunes</a></div>
            </div>
            <?php
        }
    }
    
    function outputMeta()
    { // output some meta data to show the version of last.fm iTunes mashup plugin
        echo "<meta name=\"Last.fm iTunes Mashup Version\" content=\"".$this->pluginVersion."\" />\r\n";
        echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"".$this->pluginPath."lfmit.css\" />";
        return;
    }
}

/* ***** Admin Panel and Pages ***** */

function lfmit_menu()
{
  add_options_page('Last.fm iTunes Mashup', 'Last.fm iTunes Mashup', 8, __FILE__, 'lfmit_options_page');
}

function lfmit_options_page()
{  //  displays the page content for the Test Options submenu
    $options = array("lfmit_show_panel_tmrt",
                     "lfmit_show_panel_ttt",
                     "lfmit_lfm_uname",
                     "lfmit_show_artwork",
                     "lfmit_cache_results",
                     "lfmit_cache_minutes",
                     "lfmit_lfm_key",
                     "lfmit_lfm_is_authd",
                     "lfmit_td_key");
    
    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'true'

    if( $_POST[ 'isPostBack' ] == "true" ) {
        // Read their posted value
        
        reset ($options);
        foreach($options as $option){  // loop through the array of expected option keys
            $opt_val = $_POST[ $option ];
            $opt_name = $option;
            
            // Save the posted value in the database
            update_option( $opt_name, $opt_val );
        
        }
        // Put an options updated message on the screen
        
?>
<div class="updated"><p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p></div>
<?php }

    // Read in option values from database - safest to make sure new values and old are merged
    foreach($options as $option){
      $option_value[$option] = get_option($option);
    }


    // Now display the options editing screen

    echo '<div class="wrap">';

    // header

    echo "<h2>" . __( 'Last.fm iTunes Mashup', 'mt_trans_domain' ) . "</h2>";

    // options form
    
    ?>

    <style type="text/css">
        fieldset { border: 1px solid #8CBDD5; margin:5px 10px; padding:5px 10px; }
        legend { font-weight:bold;}
    </style>

<form name="lfmit_options" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="isPostBack" value="true"/>

<fieldset><legend>Preferences</legend>
<table class="form-table">
    <tbody>
      <tr>
        <th><?php _e("Show Panels:", 'mt_trans_domain'); ?></th>
        <td><label><input type="checkbox" name="lfmit_show_panel_tmrt" value="true" <?php if($option_value['lfmit_show_panel_tmrt']) echo "checked=\"checked\""; ?>/> Ten Most Recent Tracks</label><br/>
            <label><input type="checkbox" name="lfmit_show_panel_ttt" value="true" <?php if($option_value['lfmit_show_panel_ttt']) echo "checked=\"checked\""; ?>/> Top Ten Tracks</label></td>
      </tr>
      <tr>
          <th></th>
          <td><strong>(Currently only shows one panel - JavaScript coming in 0.2)</strong></td>
        </tr>
      <tr>
          <th><?php _e("Show Artwork:", 'mt_trans_domain'); ?></th>
          <td><label><input type="checkbox" name="lfmit_show_artwork" value="true" <?php if($option_value['lfmit_show_artwork']) echo "checked=\"checked\""; ?>/> Show album artwork</label></td>
      </tr>
      <tr>
        <th><?php _e("Cache Results:", 'mt_trans_domain'); ?></th>
        <td><label><input type="checkbox" name="lfmit_cache_results" value="true" <?php if($option_value['lfmit_cache_results']) echo "checked=\"checked\""; ?>/> Cache results from Last.fm</label></td>
      </tr>
      <tr>
          <th><?php _e("Cache Length:", 'mt_trans_domain'); ?></th>
          <td><label><input type="text" name="lfmit_cache_minutes" size="1" maxlength="2" value="<?php  echo ($option_value['lfmit_cache_minutes']) ? $option_value['lfmit_cache_minutes'] : 3; ?>" /> Minutes (Number of minutes between re-caching the data)</label></td>
        </tr>
    </tbody>
</table>
</fieldset>

<fieldset><legend>Last.fm Authentication</legend>
<table class="form-table">
    <tbody>
      <tr>
        <th><?php _e("Last.fm API Key:", 'mt_trans_domain' ); ?></th>
        <td><input type="text" name="lfmit_lfm_key" value="<?php echo $option_value['lfmit_lfm_key']; ?>" size="30"/> (Your Last.fm API key, sign up at <a href="http://www.last.fm/" target="_blank">last.fm</a>)</td>
      </tr>
      <tr>
          <th><?php _e("Last.fm Username:", 'mt_trans_domain' ); ?></th>
          <td><input type="text" name="lfmit_lfm_uname" value="<?php echo $option_value['lfmit_lfm_uname']; ?>" size="30"/> (Your Last.fm Username)</td>
        </tr>
      <tr>
        <th><?php _e("Authenticate with Last.fm:",'mt_trans_domain'); ?></th>
        <td><?php if(!$option_value['lfmit_lfm_is_authd']){ ?><a href="http://www.last.fm/api/auth/?api_key=<?php echo $option_value['lfmit_lfm_key']; ?>">Click here to allow this plugin to access your last.fm data</a>.<?php } ?> <input type="checkbox" name="lfmit_lfm_is_authd" value="true" <?php if($option_value['lfmit_lfm_is_authd'] == "true"){echo "checked=\"checked\""; } ?>/> Tick here once auth'd</td>
      </tr>
    </tbody>
</table>
</fieldset>

<fieldset><legend>Affiliate Linking</legend>
    <table class="form-table">
        <tbody>
            <tr>
                <th><?php _e("TradeDoubler Key:", 'mt_trans_domain'); ?></th>
                <td><input type="text" name="lfmit_td_key" value="<?php echo $option_value['lfmit_td_key']; ?>" size="30"/> (If you don't have one, please leave mine in here!)</td>
            </tr>
        </tbody>
    </table>
</fieldset>

<p class="submit">
    <input type="submit" name="Submit" value="<?php _e('Update Options', 'mt_trans_domain' ) ?>" />
</p>

</form>
</div>

<?php } 

function lfmit_install()
{ // create lfmit table to store cached l.fm data
    global $wpdb;
    $panel_keys = array("tmrt","ttt");
    $table_name = $wpdb->prefix."lfmit_data";
   
    // if not exists 'wp_lfmit_data', create table 'wp_lfmit_data': key, url, title, artist, album, image_path
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // table structure
        $SQL = "CREATE TABLE ".$table_name." (
                  id VARCHAR( 32 ) NOT NULL,
                  rank TINYINT NOT NULL,
                  title VARCHAR( 128 ) NOT NULL,
                  artist VARCHAR( 64 ) NOT NULL,
                  album VARCHAR( 128 ) NOT NULL,
                  image_url VARCHAR( 128 ) NOT NULL,
                  large_image_url VARCHAR( 128 ) NOT NULL,
                  td_url VARCHAR( 255 ) NOT NULL,
                  UNIQUE KEY id (id)
                  ); ";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($SQL);
        // blank top tens
        foreach($panel_keys as $panel_key){
            for($i=1;$i<11;$i++){
                $wpdb->query("insert into $table_name (id,rank) values ('".$panel_key."_".$i."',".$i.")");
            }
        }
        
        
        // my TradeDoubler key, for those that don't have their own - links will not work without a key here
        update_option('lfmit_td_key','1419778');
       
    } 
    
}

function create_td_link($artist,$album,$title)
{
    global $lfmit;
    if($artist) $terms[] = $artist;
    if($album) $terms[] = $album;
    if($title) $terms[] = $title;
    $fullTerms = implode(" ",$terms); 
    $url = "http://clkuk.tradedoubler.com/click?p(23708)a(";
    $url .= $lfmit->lfmit_td_key;
    $url .=")g(11703474)url(http://phobos.apple.com/WebObjects/MZSearch.woa/wa/com.apple.jingle.search.DirectAction/search?term=";
    $url .=$fullTerms.")";
    return $url;
    
    // TODO: Make these links more iTunes friendly
}

/* ***** Widget Code ***** */

function lfmit_widget($args)
{
    // TODO: Make title customisable
    
    global $lfmit;
    extract($args);
    echo $before_widget;
    echo $before_title;
    echo "What I'm listening to...";
    echo $after_title;
    
    $lfmit->show_panels();
    echo $after_widget;
}

function after_plugins_loaded()
{
    if(function_exists('register_sidebar_widget')){
        register_sidebar_widget('Last.fm iTunes Widget','lfmit_widget');
    }
    
}

add_action('wp_head',array(&$lfmit,'outputMeta'),1);
add_action('admin_menu', 'lfmit_menu');
add_action('plugins_loaded','after_plugins_loaded');

register_activation_hook( __FILE__ ,'lfmit_install');


$lfmit = new lastFMiTunesPlugin();

?>