<?php
/*
 Plugin Name: 15CC Tag Menu
 Plugin URI: http://club15cc.com/
 Description: A customisable tag menu (not a cloud!) with active term highlighting and other features
 Version: 0.2
 Author: Hari Karam Singh
 Author URI: http://club15cc.com
 License: MIT
*/

/**
 * FEATURES:
 * - 15cc-hidden-tags plugin bridge!
 * - Custom taxonomies selection
 * - Auto-detection of custom tag taxonomies for custom post types
 * - Tag specifed by name or slug, newline or comma separated and '#' for line commenting
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
        $instance['orderby'] = $instance['orderby'] ? $instance['orderby'] : 'count';
        $instance['order'] = $instance['order'] ? $instance['order'] : 'DESC';
        
        
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
        <input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if (isset ( $instance['title'])) {echo esc_attr( $instance['title'] );} ?>" /></p>
        
        <hr/>
        <h3>Filtering</h3>
        
        <p><label title="Minimum number of posts required for the tag to show." for="<?php echo $this->get_field_id('min_post_count'); ?>"><?php _e('Minimum Post Count:') ?></label>
        <input type="number" class="" id="<?php echo $this->get_field_id('min_post_count'); ?>" name="<?php echo $this->get_field_name('min_post_count'); ?>" value="<?php if (isset ( $instance['min_post_count'])) {echo esc_attr( $instance['min_post_count'] );} ?>" /></p>
        
        <p><label title="Exclude the tags with the following comma/newline seperated list. Names or slugs accepted. Use '#' to mark a line as a comment." for="<?php echo $this->get_field_id('exclude_tags'); ?>"><?php _e('Excluded these tags:') ?></label>
        <textarea class="widefat" id="<?php echo $this->get_field_id('exclude_tags'); ?>" name="<?php echo $this->get_field_name('exclude_tags'); ?>"><?php if (isset ( $instance['exclude_tags'])) {echo esc_attr( $instance['exclude_tags'] );} ?></textarea></p>
        
        <p><label title="Include only these tags (provided they meet the min post count). Names or slugs accepted. Use '#' to mark a line as a comment." for="<?php echo $this->get_field_id('include_tags'); ?>"><?php _e('Include only these tags:') ?></label>
        <textarea class="widefat" id="<?php echo $this->get_field_id('include_tags'); ?>" name="<?php echo $this->get_field_name('include_tags'); ?>"><?php if (isset ( $instance['include_tags'])) {echo esc_attr( $instance['include_tags'] );} ?></textarea></p>
        
        <h3>Order By</h3>
        
        <select id="<?php echo $this->get_field_id('orderby'); ?>" name="<?php echo $this->get_field_name('orderby'); ?>">
            <?php foreach (array(   'id'=>'ID',
                                    'count' => 'Count',
                                    'name' => 'Name',
                                    'slug' => 'Slug',
                                    'term_group' => 'Term Group',
                                    'none' => 'None' ) as $value => $label): ?>
                <option value="<?php esc_attr_e($value)?>" <?php echo $instance['orderby'] == $value ? 'selected="selected"' : ''?>><?php esc_html_e($label)?></option>
            <?php endforeach; ?> 
        </select> 

        <select id="<?php echo $this->get_field_id('order'); ?>" name="<?php echo $this->get_field_name('order'); ?>">
            <?php foreach (array(   'ASC'=>'Ascending',
                                    'DESC' => 'Descending',
                                 ) as $value => $label): ?>
                <option value="<?php esc_attr_e($value)?>" <?php echo $instance['order'] == $value ? 'selected="selected"' : ''?>><?php esc_html_e($label)?></option>
            <?php endforeach; ?>  
        </select> 
        
        <hr/>
        <h3>Advanced</h3>
        <p><label title="Choose the taxonomy to display.  Auto-detect looks for a 'tags' derivative taxonomy for the current (custom) post type." for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Taxonomy:') ?></label>
        <select id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>">
            <option value="TAXONOMY_AUTO_DETECT">(auto-detect)</option> 
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
        $instance['min_post_count'] = (int)$new_instance['min_post_count'];
        $instance['exclude_tags'] = strip_tags(stripslashes($new_instance['exclude_tags']));
        $instance['include_tags'] = strip_tags(stripslashes($new_instance['include_tags']));
        $instance['taxonomy'] = strip_tags(stripslashes($new_instance['taxonomy']));
        $instance['order'] = strip_tags(stripslashes($new_instance['order']));
        $instance['orderby'] = strip_tags(stripslashes($new_instance['orderby']));
        return $instance;     
    }
    
    public function widget($args, $instance) 
    {
        extract($args);
        extract($instance);
        
        if ($taxonomy == 'TAXONOMY_AUTO_DETECT') {
            $taxonomy = self::_autodetect_taxonomy();
        }
        
        // Bridge with 15cc hidden tags plugin list if available
        if (defined('FIFTEENCC_HIDDEN_TAGS_PLUGIN')) {
            $plugin_hidden_tags = get_option('fifteencc_hidden_tags');
            $exclude_tags = trim($exclude_tags) 
                ? "$exclude_tags,$plugin_hidden_tags" 
                : $plugin_hidden_tags; 
        }
        
        // Convert to array and clean
        $exclude_tags = self::_process_tags_list_string($exclude_tags);
        $include_tags = self::_process_tags_list_string($include_tags);
       
        // Get the active tags list..
        // For tags page, its the tag we're looking at...
        // For home & non-tag archive, etc, it's empty
        $active_tagnames = array();
        if (is_tag()) {
            $active_tagnames = array(single_tag_title("", false)); 
        } elseif (is_single()) {
            $tmp = get_the_terms( 0, $taxonomy );
            if (!empty($tmp)) {
                foreach ($tmp as $tag) {
                    $active_tagnames[] = $tag->name;   
                }
            }
        }
        
        // Get all the tags for the taxonomy, using the 'include' list if specified
        // or querying the system if not
        $tags = array();
        if (count($include_tags)) {
            foreach ($include_tags as $slug_or_name) {
                if ($t = self::_get_term_for_taxonomy($slug_or_name, $taxonomy)) {
                    $tags[] = $t;
                }
            }
        } else {
            
            $tags = get_terms($taxonomy, array(
                'orderby'       => $orderby,
                'order'         => $order,
                'hide_empty'    => FALSE    // let the min post count define this
            ));
        }

        // Exclude specified tags and those w/o minimum post requirement
        foreach ($tags as $idx => $t) {
            // Match on either name or slug
            if ( (in_array($t->name, $exclude_tags, true) ||
                  in_array($t->slug, $exclude_tags, true)) ) {
                    
                unset($tags[$idx]);
                
            // Also remove terms which dont have the minimum post count
            } elseif ($min_post_count && $t->count < $min_post_count) {
                unset($tags[$idx]);
            }
        }
        $tags = array_values(array_filter($tags));  // clean empties & re-index
            
        //
        // WIDGET OUTPUT
        //
        
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
     * Get the reduced (integer, 0..$modulus) checksum for a string. 
     * 
     * Allows consistent custom styling for things like tags.
     * @return  int 0 <= return <= $modulus 
     */
    protected function _get_checksum_modulus($str, $modulus)
    {
        return abs(crc32(md5($str)) % $modulus) + 1;
    }
    
    /**
     * Uses the custom post type name to guess and check for the a custom taxonomy 'tag'
     * 
     * Plain WP posts return plain tags.  Also defaults to 'tag' if no custom tax is found.  
     * Otherwise it looks for a taxonomy ending in a non-alphanumeric + 'tag', for ex. 'myposttype-tag'
     */
    protected static function _autodetect_taxonomy()
    {
        $post_type = get_post_type();
        
        // Just plain 'posts'
        if ($post_type == 'post')
            return 'post_tag';
        
        foreach (get_object_taxonomies($post_type) as $tax) {
            if (preg_match('/[^a-zA-Z0-9]{1}tag$/', $tax)) {
                return $tax;
            }
        }
        
        return 'post_tag';
    }    
    
    /**
     * Returns a list of tag strings (names or slugs) from an input string
     * 
     * Tags can be separated by newline or comma.  '#' marks the beginning of comment line
     */
    protected static function _process_tags_list_string($input)
    {
        // Split by newline and ','
        $input = preg_replace('/#(.*?)$/m', '', $input);
        $input = preg_split('/[\r\n,]+/', $input);
        
        // Trim and clean up any empty entries and duplicates and finally re-index
        return array_values(array_unique(array_filter(array_map('trim', $input))));
    }
    
    /**
     * Checks whether a given term name or slug exists for a specified taxonomy.
     * @return false if none exist
     */
    protected static function _get_term_for_taxonomy($slug_or_name, $taxonomy) 
    {
        $term = get_term_by('slug', $slug_or_name, $taxonomy);
        if (!$term)    
            $term = get_term_by('name', $slug_or_name, $taxonomy);
        return $term;
    }
}
add_action('widgets_init', create_function('', "register_widget('l5CC_Tag_Menu_Widget');"));
