<?php
/*
	Plugin Name: eHive Object Tags widget
	Plugin URI: http://developers.ehive.com/wordpress-plugins/
	Author: Vernon Systems limited
	Description: A widget that displays tags for an eHive object with the options to add or delete tags. The <a href="http://developers.ehive.com/wordpress-plugins#ehiveaccess" target="_blank">eHiveAccess plugin</a> must be installed.
	Version: 2.1.1
	Author URI: http://vernonsystems.com
	License: GPL2+
*/
/*
	Copyright (C) 2012 Vernon Systems Limited

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

add_action( 'widgets_init', 'ehive_object_tags_widget' );

function ehive_object_tags_widget() {
	return register_widget( 'EHiveObjectTags_Widget' );
}

class EHiveObjectTags_Widget extends WP_Widget {
	
	public function __construct() {
		parent::__construct('ehiveobjecttags_widget',
							'eHive Object Tags', 
							array( 'description' => __('A widget that displays tags for an eHive object with the options to add or delete tags.', 'text_domain'))
		);
		
		add_action('wp_loaded', array(&$this, 'add_tags'));
		add_action('wp_loaded', array(&$this, 'delete_tag'));		
	}
	
	public function widget($args, $instance) {

		global $eHiveAccess, $post;
		$objectDetailPageId = $eHiveAccess->getObjectDetailsPageId();
		
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);
		
		
		if ( $eHiveAccess->getObjectDetailsPageId() == $post->ID ) {
		
			echo $before_widget;
			if (! empty( $title) ) {
				echo $before_title . $title . $after_title;
			}
			
			if ($instance['css_class'] == "") {
				echo '<div class="ehive-object-tags-widget">';
			} else {
				echo '<div class="ehive-object-tags-widget '.$instance['css_class'].'">';
			}
			
			try {		 
				if (isset($instance['widget_css_enabled'])) {
					wp_register_style($handle = 'eHiveObjectTagsWidgetCSS', $src = plugins_url('eHiveObjectTags_Widget.css', '/ehive-object-tags-widget/css/eHiveObjectTags_Widget.css'), $deps = array(), $ver = '0.0.1', $media = 'all');
					wp_enqueue_style( 'eHiveObjectTagsWidgetCSS');
				}	
							
				$object_record_id = ehive_get_var('ehive_object_record_id', false);
				 
				if ($object_record_id) {
					
					global $eHiveSearch;
					 
					$eHiveApi = $eHiveAccess->eHiveApi();
					
					$siteType = $eHiveAccess->getSiteType();
					$accountId = $eHiveAccess->getAccountId();
					$community = $eHiveAccess->getCommunityId();
					
					$objectRecordTagsCollection = $eHiveApi->getObjectRecordTags($object_record_id);
						
					if ($objectRecordTagsCollection != false) {
						include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
						foreach ($objectRecordTagsCollection->objectRecordTags as $objectTag) {
				
							$delete_link = ehive_current_url();
							$add_link = ehive_current_url();
							
							if (isset($eHiveSearch)) {
								$searchOptions = $eHiveSearch->getSearchOptions();
								$search_link = $eHiveAccess->getSearchPageLink( "?{$searchOptions['query_var']}=tag:{$objectTag->rawTagName}" );
							} else {
								$search_link = '#';
							}
							
							if ($instance['delete_tags_enabled'] && $objectRecordTagsCollection->allowTagging == true) {
								echo "<form class='ehive-delete-tag' action='{$delete_link}' method='post' name='ehive_object_tags'>";
								
								echo "<input type='hidden' name='ehive_object_record_id' value='{$object_record_id}'/>";
					            echo "<input type='hidden' name='ehive_delete_tag' value='{$objectTag->rawTagName}'/>";
					        }
							if ($instance['tag_search_link_enabled'] && is_plugin_active('eHiveSearch/EHiveSearch.php')) {
								echo "<a href='{$search_link}'>{$objectTag->rawTagName}</a>";
							} else {
								echo "<span>{$objectTag->rawTagName}</span>";
							}
								 
					        if ($instance['delete_tags_enabled'] && $objectRecordTagsCollection->allowTagging == true)
					        	echo '<button class="ehive-delete-tag" type="submit">'.$instance['delete_button_text'].'</button>';
									 
							if ($instance['delete_tags_enabled'] && $objectRecordTagsCollection->allowTagging == true) {
								echo "</form>";
					        } else {
				          		echo "<br/>";
					        }
						}
				
						if ($instance['add_tags_enabled'] && $objectRecordTagsCollection->allowTagging == true) {
					    	echo "<form class='ehive-add-tag' action='{$add_link}' method='post' name='ehive_add_object_tags'>";
							echo "<input type='hidden' name='ehive_object_record_id' value='$object_record_id'/>";
							echo "<input id='{$this->get_field_id('ehive_add_tags')}' type='text' name='ehive_add_tags' value=''>";
					        if ($instance['add_tags_button_text']) {
					        	echo "<input type='submit' value='{$instance['add_tags_button_text']}'>";
							}
					        echo '</form>';
						}
						if ($instance['explanation_text']) {
					    	echo '<p class="ehive-tag-explanation">'.$instance['explanation_text'].'</p>';
						}
						echo '</div>';
				        echo $args['after_widget'];
					}
				}
			} catch (Exception $exception) {
				error_log('EHive Account Details plugin returned and error while accessing the eHive API: ' . $exception->getMessage());
				$eHiveApiErrorMessage = " ";
				if ($eHiveAccess->getIsErrorNotificationEnabled()) {
					$eHiveApiErrorMessage = $eHiveAccess->getErrorMessage();
				}
			}
		}
	}
	
	function add_tags() {
						
		$object_record_id = $_POST['ehive_object_record_id'];
		$tags = stripslashes_deep($_POST['ehive_add_tags']);
		
		if ( $object_record_id && $tags ) {
			
			global $eHiveAccess;
			require_once  plugin_dir_path(__FILE__).'../ehive-access/ehive_api_client-php/domain/objectrecordtags/ObjectRecordTag.php';
			
			$eHiveApi = $eHiveAccess->eHiveApi();
			
			if (strstr($tags, ',')) {
				$tags = explode(',', $tags); 
				
				foreach ($tags as $key => $tagString) {
					$objectRecordTag = new ObjectRecordTag();
					$objectRecordTag->rawTagName = trim($tagString);
					
					$eHiveApi->addObjectRecordTag($object_record_id, $objectRecordTag);
				}
			} else {
				$objectRecordTag = new ObjectRecordTag();
				$objectRecordTag->rawTagName = trim($tags);
				
				$eHiveApi->addObjectRecordTag($object_record_id, $objectRecordTag);
			}
		}
	}
	
	function delete_tag() {
				
		$object_record_id = $_POST['ehive_object_record_id'];
		$tagString = $_POST['ehive_delete_tag'];
		
		if ($object_record_id && $tagString) {

			global $eHiveAccess;
			require_once  plugin_dir_path(__FILE__).'../ehive-access/ehive_api_client-php/domain/objectrecordtags/ObjectRecordTag.php';

			$eHiveApi = $eHiveAccess->eHiveApi();
			
			$objectRecordTag = new ObjectRecordTag();
			$objectRecordTag->rawTagName = trim($tagString);
			
			$eHiveApi->deleteObjectRecordTag($object_record_id, $objectRecordTag);
		}
	}	
		
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		
		$instance['title'] = strip_tags( $new_instance['title']);
		$instance['delete_button_text'] = strip_tags( $new_instance['delete_button_text']);
		$instance['add_tags_button_text'] = strip_tags( $new_instance['add_tags_button_text']);
		$instance['add_tags_enabled'] = $new_instance['add_tags_enabled'];
		$instance['delete_tags_enabled'] = $new_instance['delete_tags_enabled'];
		$instance['tag_search_link_enabled'] = $new_instance['tag_search_link_enabled'];
		$instance['explanation_text'] = strip_tags( $new_instance['explanation_text']);		
		$instance['widget_css_enabled'] = $new_instance['widget_css_enabled'];
		$instance['css_class'] = $new_instance['css_class'];
		
		return $instance;
	}
		
	public function form($instance) {
				
		$defaults = array( 
				'title' => 'Object Tags',
				'delete_button_text' => 'Delete',
				'add_tags_button_text' => 'Add',
				'add_tags_enabled' => true,
				'delete_tags_enabled' => true,
				'tag_search_link_enabled' => true,
				'explanation_text' => 'Include tags such as place names, people, dates, events and colours. Use commas to separate multiple tags. e.g. Pablo Picasso, Madrid, red, 1930s.',
				'widget_css_enabled' => true,
				'css_class' => '' );
		
		$instance = wp_parse_args( $instance, $defaults ); 		
				 
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" type="text" value="<?php echo $instance['title']; ?>" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" />
		</p>

        <p>
		<label for="<?php echo $this->get_field_id('delete_button_text'); ?>"><?php _e('Delete a tag button text:'); ?></label>
		<input class="widefat" type="text" value="<?php echo $instance['delete_button_text']; ?>" id="<?php echo $this->get_field_id('delete_button_text'); ?>" name="<?php echo $this->get_field_name('delete_button_text'); ?>" />
		</p>
		
        <p>
		<label for="<?php echo $this->get_field_id('add_tags_button_text'); ?>"><?php _e('Add a tag button text:'); ?></label>
		<input class="widefat" type="text" value="<?php echo $instance['add_tags_button_text']; ?>" id="<?php echo $this->get_field_id('add_tags_button_text'); ?>" name="<?php echo $this->get_field_name('add_tags_button_text'); ?>" />
		</p>
        
        <p>
        <input class="checkbox" type="checkbox" value="1" <?php checked( $instance['add_tags_enabled'], true ); ?> id="<?php echo $this->get_field_id('add_tags_enabled'); ?>" name = "<?php echo $this->get_field_name('add_tags_enabled'); ?>" />
		<label for="<?php echo $this->get_field_id('add_tags_enabled'); ?>"><?php _e( 'Enable adding tags:' ); ?></label>        
		</p>				
        
        <p>
        <input class="checkbox" type="checkbox" value="1" <?php checked( $instance['delete_tags_enabled'], true ); ?> id="<?php echo $this->get_field_id('delete_tags_enabled'); ?>" name = "<?php echo $this->get_field_name('delete_tags_enabled'); ?>" />
		<label for="<?php echo $this->get_field_id('delete_tags_enabled'); ?>"><?php _e( 'Enable deleting tags:' ); ?></label>        
		</p>

		<?php if (is_plugin_active('eHiveSearch/EHiveSearch.php')) {?>
	        <p>
	        <input class="checkbox" type="checkbox" value="1" <?php checked( $instance['tag_search_link_enabled'], true ); ?> id="<?php echo $this->get_field_id('tag_search_link_enabled'); ?>" name = "<?php echo $this->get_field_name('tag_search_link_enabled'); ?>" />
			<label for="<?php echo $this->get_field_id('tag_search_link_enabled'); ?>"><?php _e( 'Enable searching by tag:' ); ?></label>        
			</p>
		<?php }?>
		
        <p>				
		<label for="<?php echo $this->get_field_id('explanation_text'); ?>"><?php _e('Explanation text:'); ?></label>
		<input class="widefat" type="text" value="<?php echo $instance['explanation_text']; ?>" id="<?php echo $this->get_field_id('explanation_text'); ?>" name="<?php echo $this->get_field_name('explanation_text'); ?>" />				
		</p>	
		
		<hr class="div"/>				
        <p>
	        <input class="checkbox" type="checkbox" value="1" <?php checked( $instance['widget_css_enabled'], true ); ?> id="<?php echo $this->get_field_id('widget_css_enabled'); ?>" name = "<?php echo $this->get_field_name('widget_css_enabled'); ?>" />
			<label for="<?php echo $this->get_field_id('widget_css_enabled'); ?>"><?php _e( 'Use widget css' ); ?></label>        
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'css_class' ); ?>"><?php _e( 'Custom CSS Class:' ); ?></label>
			<input class="widefat" type="text" value="<?php echo $instance['css_class']; ?>" id="<?php echo $this->get_field_id( 'css_class' ); ?>" name="<?php echo $this->get_field_name( 'css_class' ); ?>" />
		</p>				
		<?php
	}
}
?>