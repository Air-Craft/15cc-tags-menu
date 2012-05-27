<?php
/*
 Plugin Name: 15CC Tag Menu
 Plugin URI: http://club15cc.com/
 Description: A customisable tag menu (not a cloud!) with active term highlighting and other features
 Version: 0.1
 Author: Hari Karam Singh
 Author URI: http://club15cc.com
 License: MIT
*/

/**
 * FEATURES:
 * - 15cc-hidden-tags plugin bridge!
 */
class l5CC_Tag_Menu_Widget extends WP_Widget 
{
    private static $CSS_CLASS = 'l5cc-tag-menu-widget';
     
    function __construct() 
    {
        $widget_ops = array( 'description' => __( "A specialised menu for site tags.") );
        parent::__construct('tag_menu', 'Tag Menu', $widget_ops);
        
    }
    
    
    public function form($instance) 
    {
        // Defaults
        $instance['taxonomy'] = $instance['taxonomy'] ? $instance['taxonomy'] : 'post_tag';
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
        <input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if (isset ( $instance['title'])) {echo esc_attr( $instance['title'] );} ?>" /></p>
        
        <p><label title="Minimum number of posts required for the tag to show." for="<?php echo $this->get_field_id('min_post_count'); ?>"><?php _e('Minimum Post Count:') ?></label>
        <input type="text" class="" id="<?php echo $this->get_field_id('min_post_count'); ?>" name="<?php echo $this->get_field_name('min_post_count'); ?>" value="<?php if (isset ( $instance['min_post_count'])) {echo esc_attr( $instance['min_post_count'] );} ?>" /></p>
        
        <p><label title="Exclude the tags with the following comma seperated list. Names or slugs accepted." for="<?php echo $this->get_field_id('exclude_tags'); ?>"><?php _e('Excluded these tags:') ?></label>
        <input type="text" class="widefat" id="<?php echo $this->get_field_id('exclude_tags'); ?>" name="<?php echo $this->get_field_name('exclude_tags'); ?>" value="<?php if (isset ( $instance['exclude_tags'])) {echo esc_attr( $instance['exclude_tags'] );} ?>" /></p>
        
        <p><label title="Include only these tags (provided they meet the min post count). Names or slugs accepted." for="<?php echo $this->get_field_id('include_tags'); ?>"><?php _e('Include only these tags:') ?></label>
        <input type="text" class="widefat" id="<?php echo $this->get_field_id('include_tags'); ?>" name="<?php echo $this->get_field_name('include_tags'); ?>" value="<?php if (isset ( $instance['include_tags'])) {echo esc_attr( $instance['include_tags'] );} ?>" /></p>
        
        <hr/>
        <h3>Advanced</h3>
        <p><label title="" for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Taxonomy:') ?></label>
        <select id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>">
            <?php 
            foreach (get_taxonomies(array('public' => true), 'objects') as $tax): ?>
                <option value="<?php esc_attr_e($tax->name)?>" <?php echo $instance['taxonomy'] == $tax->name ? 'selected="selected"' : ''?>><?php esc_html_e($tax->labels->name)?></option>
            <?php endforeach; ?>
        </select>        
        <?php  
    }
    
    public function update($new_instance, $old_instance) 
    {
        $instance['title'] = strip_tags(stripslashes($new_instance['title']));
        $instance['exclude_tags'] = strip_tags(stripslashes($new_instance['exclude_tags']));
        $instance['min_post_count'] = (int)$new_instance['min_post_count'];
        return $instance;     
    }
    
    public function widget($args, $instance) 
    {
        extract($args);
        extract($instance);
        
        // Bridge with 15cc hidden tags plugin list if available
        if (defined('FIFTEENCC_HIDDEN_TAGS_PLUGIN')) {
            $plugin_hidden_tags = get_option('fifteencc_hidden_tags');
            $exclude_tags = trim($exclude_tags) 
                ? "$exclude_tags,$plugin_hidden_tags" 
                : $plugin_hidden_tags; 
        }
        
        // Convert to array and clean
        $exclude_tags = split(',', $exclude_tags);
        $exclude_tags = array_unique(array_filter(array_map('trim', $exclude_tags)));
        
        /*if ( !empty($instance['title']) )        $title = $instance['title'];
        if ( !empty($instance['exclude_tags']) ) $exclude_tags = $instance['exclude_tags'];
        */
       
        // Get the active tags list..
        // For tags page, its the tag we're looking at...
        // For home & non-tag archive, etc, it's empty
        $active_tagnames = array();
        if (is_tag()) {
            $active_tagnames = array(single_tag_title("", false)); 
        } elseif (is_single()) {
            $tmp = get_the_tags();
            if (!empty($tmp)) {
                foreach ($tmp as $tag) {
                    $active_tagnames[] = $tag->name;   
                }
            }
        }
        
        // Get all the tags excluding specified 
        $tags = $this->_get_the_tags_excluding($exclude_tags, $min_post_count);
        
        // don't output the widget if nothing
        if ( empty( $tags ) || is_wp_error( $tags ) )
            return;
        
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        echo $before_widget;
        if ( $title ) echo $before_title . $title . $after_title;
        // Output the tags list
        ?>
        <ul>
            <?php foreach ($tags as $tag): 
                $link = get_term_link( intval($tag->term_id), $tag->taxonomy );
                $term = esc_html($tag->name);
                $css = in_array($tag->name, $active_tagnames)
                    ? ' class="active active'.self::_get_checksum_modulus($tag->name, 10).'"'
                    : ''; 
                    
                ?>
               <li<?php echo $css?>><a href="<?php echo $link?>"><?php echo $term ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php
        echo $after_widget;
    } //widget()   
    
    /**
     * Returns list of available tags which aren't set to be hidden by the widget options
     * @param   array  $tags_to_hide    Array of names or slugs of tags to exclude
     */
    protected function _get_the_tags_excluding($tags_to_hide, $min_post_count = NULL)
    {
        $terms = get_terms('post_tag', array(
            'orderby'       => 'count',
            'order'         => 'DESC',
            'hide_empty'    => true
        ));
        
        // Loop thru terms and exclude matches
        // Match on either name or slug
        foreach ($terms as $idx => $term) {
            if ( (in_array($term->name, $tags_to_hide, true) ||
                  in_array($term->slug, $tags_to_hide, true)) ) {
                    
                unset($terms[$idx]);
                
            // Also remove terms which dont have the minimum post count
            } elseif ($min_post_count && $term->count < $min_post_count) {
                unset($terms[$idx]);
            }
            
        }
        $terms = array_filter($terms);  // re-index
        return $terms; 
    }
    
    /**
     * Get the reduced (integer, 0..$modulus) checksum for a string. 
     * 
     * Allows consistent custom styling for things like tags.
     * @return  int 0 <= return <= $modulus 
     */
    protected function _get_checksum_modulus($str, $modulus)
    {
        return abs(crc32(md5($str)) % $modulus) + 1;
    }
}
add_action('widgets_init', create_function('', "register_widget('l5CC_Tag_Menu_Widget');"));
