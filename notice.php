<?php
/**
 * Plugin Name: Custom Notice Manager
 * Plugin URI: http://example.com/custom-notice-manager
 * Description: A simple notice management plugin for WordPress with shortcode support (Network Sites Compatible)
 * Version: 2.0.1
 * Author: ari0329
 * Text Domain: custom-notice-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Using CNTM prefix for all constants and functions
define('CNTM_VERSION', '1.0.1');
define('CNTM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CNTM_PLUGIN_URL', plugin_dir_url(__FILE__));

function cntm_register_notice_post_type() {
    $labels = array(
        'name'               => __('Notices', 'custom-notice-manager'),
        'singular_name'      => __('Notice', 'custom-notice-manager'),
        'menu_name'          => __('Notices', 'custom-notice-manager')
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'menu_icon'           => 'dashicons-megaphone'
    );

    register_post_type('notice', $args);
    
    register_post_meta('notice', 'cntm_show_author', array(
        'type' => 'boolean',
        'description' => 'Show author name',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
    
    register_post_meta('notice', 'cntm_show_date', array(
        'type' => 'boolean',
        'description' => 'Show publication date',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
    
    register_post_meta('notice', 'cntm_show_site', array(
        'type' => 'boolean',
        'description' => 'Show site name',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
    
    // Add new meta field for homepage request
    register_post_meta('notice', 'cntm_homepage_request', array(
        'type' => 'boolean',
        'description' => 'Request to display on network homepage',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
}
add_action('init', 'cntm_register_notice_post_type');

// Add meta box for display options
function cntm_add_meta_boxes() {
    add_meta_box(
        'cntm_display_options',
        __('Display Options', 'custom-notice-manager'),
        'cntm_display_options_callback',
        'notice',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'cntm_add_meta_boxes');

function cntm_get_meta_display_options($post_id) {
    return array(
        'show_author' => get_post_meta($post_id, 'cntm_show_author', true) === '1',
        'show_date' => get_post_meta($post_id, 'cntm_show_date', true) === '1',
        'show_site' => get_post_meta($post_id, 'cntm_show_site', true) === '1'
    );
}
// Helper function to truncate text
function cntm_truncate_text($text, $length = 35) {
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}
// Modified Shortcode Function for admin site
function cntm_display_notices_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => -1,
        'site_id' => get_current_blog_id(),
    ), $atts, 'display_notices');
    
    $site_id = intval($atts['site_id']);
    $limit = intval($atts['limit']);
    
    $switch_site = is_multisite() && $site_id !== get_current_blog_id();
    
    if ($switch_site) {
        switch_to_blog($site_id);
    }
    
    $query_args = array(
        'post_type' => 'notice', 
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($query_args);
    $output = '';
    
    if ($query->have_posts()) {
        $output .= '<div class="cntm-notices-list">';
        
        $output .= '<ul>';
        $items = array();
        
        while ($query->have_posts()) {
            $query->the_post();
            $title = cntm_truncate_text(get_the_title(), 35);
            $item = '<li>';
            $item .= '<a href="' . get_permalink() . '">' . esc_html($title) . '</a>';
            $item .= '</li>';
            $items[] = $item;
        }
        
        $output .= implode('', $items);
        $output .= '</ul>';
        $output .= '</div>';
    } else {
        $output .= '<div class="cntm-notices-list">';
        $output .= '<p>No notices found.</p>';
        $output .= '</div>';
    }
    
    wp_reset_postdata();
    
    if ($switch_site) {
        restore_current_blog();
    }
    
    $output .= '<style>
        .cntm-notices-list ul li::before {
            content: "";
            display: inline-block;
            width: 0;
            height: 0;
            border-top: 5px solid transparent;
            border-bottom: 5px solid transparent;
            border-left: 5px solid #6c757d;
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.4s ease;
            z-index: 1;
        }
        /* Add other relevant styles */
    </style>';
    return $output;
}
add_shortcode('display_notices', 'cntm_display_notices_shortcode');

// Network shortcode for displaying notices from specified site ID
function cntm_display_network_site_notices_shortcode($atts) {
    if (!is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 5,
        'site_id' => 0, // Specific site ID to show posts from
    ), $atts, 'display_site_notices');
    
    $limit = intval($atts['limit']);
    $site_id = intval($atts['site_id']);
    
    if ($site_id <= 0) {
        return '<p>Please specify a valid site_id parameter.</p>';
    }
    
    ob_start();
    echo '<div class="notices-list">'; // Changed class to match CSS
    
    switch_to_blog($site_id);
    
    $query_args = array(
        'post_type' => 'notice', 
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($query_args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            echo '<div class="notice-item">'; // Changed class to match CSS
            
            // Display featured image
            if (has_post_thumbnail()) {
                echo '<div class="notice-image">'; // Changed class to match CSS
                the_post_thumbnail('thumbnail');
                echo '</div>';
            }
            
            echo '<div class="notice-content">'; // Added wrapper div for content
            
            // Truncate title to 35 characters
            $title = cntm_truncate_text(get_the_title(), 35);
            echo '<h3 class="notice-title"><a href="' . get_permalink() . '">' . $title . '</a></h3>'; // Changed class
            
            // Display meta information based on settings
            $meta_info = array();

            if (get_post_meta($post_id, 'cntm_show_author', true)) {
                $meta_info[] = 'Author: ' . get_the_author();
            }

            if (get_post_meta($post_id, 'cntm_show_date', true)) {
                $meta_info[] = 'Date: ' . get_the_date();
            }

            if (get_post_meta($post_id, 'cntm_show_site', true)) {
                $meta_info[] = 'Dept: ' . cntm_get_custom_site_name();
            }
            
            if (!empty($meta_info)) {
                echo '<div style="font-size: 9px;" class="notice-meta">' . implode(' | ', $meta_info) . '</div>'; // Changed class
            }
            
            // Get excerpt and truncate to 100 characters
            $excerpt = get_the_excerpt();
            $excerpt = cntm_truncate_text($excerpt, 100);
            echo '<div class="notice-excerpt">' . $excerpt . '</div>'; // Changed class
            
            echo '<a href="' . get_permalink() . '" class="cntm-read-more">Read More</a>';
            echo '</div>'; // Close notice-content div
            echo '</div>'; // Close notice-item div
        }
    } else {
        echo '<p>No notices found for this site.</p>';
    }
    
    wp_reset_postdata();
    restore_current_blog();
    
    echo '</div>'; // Close notices-list div
    
    // Updated CSS styles to match the image
    echo '<style>
        .notices-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        .notice-item {
            flex: 1;
            min-width: 300px;
            max-width: 380px;
            margin-bottom: 20px;
            background: #ffffff;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .notice-banner {
            background-color: #993333;
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
        }
        .notice-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        .notice-content {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .notice-title {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        .notice-title a {
            color: #333;
            text-decoration: none;
        }
        .notice-excerpt {
            color: #555;
            margin-bottom: 15px;
            font-size: 14px;
            flex-grow: 1;
        }
        .notice-meta {
            font-size: 0.85em;
            color: #777;
            margin-bottom: 10px;
        }
        .cntm-read-more {
            display: inline-block;
            padding: 5px 10px;
            background: #993333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
            align-self: flex-start;
        }
        .cntm-read-more:hover {
            background:rgb(95, 32, 32);
        }
        @media (max-width: 768px) {
            .notices-list {
                flex-direction: column;
            }
            .notice-item {
                max-width: 100%;
            }
        }
    </style>';
    
    return ob_get_clean();
}
add_shortcode('display_site_notices', 'cntm_display_network_site_notices_shortcode');
// Network shortcode for displaying notices from all sites
function cntm_display_network_notices_shortcode($atts) {
    if (!is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 5,
        'sites' => '', // Comma-separated list of site IDs, empty means all sites
        'homepage_requests_only' => 'no', // Only show notices requested for homepage
    ), $atts, 'display_network_notices');
    
    $limit = intval($atts['limit']);
    $sites_list = !empty($atts['sites']) ? array_map('intval', explode(',', $atts['sites'])) : array();
    $homepage_requests_only = ($atts['homepage_requests_only'] === 'yes');
    
    $sites = get_sites(array(
        'site__in' => !empty($sites_list) ? $sites_list : array(),
    ));
    
    ob_start();
    echo '<div class="cntm-network-notices">';
    
    if (!empty($sites)) {
        $all_notices = array();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $query_args = array(
                'post_type' => 'notice', 
                'posts_per_page' => $limit,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            // Add meta query for homepage requests if needed
            if ($homepage_requests_only) {
                $query_args['meta_query'] = array(
                    array(
                        'key' => 'cntm_homepage_request',
                        'value' => '1',
                        'compare' => '='
                    )
                );
            }
            
            $query = new WP_Query($query_args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    $all_notices[] = array(
                        'ID' => $post_id,
                        'title' => get_the_title(),
                        'permalink' => get_permalink(),
                        'date' => get_the_date('Y-m-d H:i:s'),
                        'excerpt' => get_the_excerpt(),
                        'author' => get_the_author(),
                        'site_name' => get_bloginfo('name'),
                        'site_id' => $site->blog_id,
                        'show_author' => get_post_meta($post_id, 'cntm_show_author', true),
                        'show_date' => get_post_meta($post_id, 'cntm_show_date', true),
                        'show_site' => get_post_meta($post_id, 'cntm_show_site', true),
                        'homepage_request' => get_post_meta($post_id, 'cntm_homepage_request', true),
                        'timestamp' => get_post_time('U', true),
                        'has_thumbnail' => has_post_thumbnail(),
                        'thumbnail_html' => get_the_post_thumbnail(null, 'thumbnail')
                    );
                }
            }
            
            wp_reset_postdata();
            restore_current_blog();
        }
        
        // Sort by date (newest first)
        usort($all_notices, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Limit the total number of notices
        $all_notices = array_slice($all_notices, 0, $limit);
        
        if (!empty($all_notices)) {
            foreach ($all_notices as $notice) {
                echo '<div class="cntm-notice-item">';
                
                // Display featured image
                if ($notice['has_thumbnail']) {
                    echo '<div class="cntm-notice-thumbnail">';
                    echo $notice['thumbnail_html'];
                    echo '</div>';
                }
                
                // Truncate title to 35 characters
                $title = cntm_truncate_text($notice['title'], 35);
                echo '<h3 class="cntm-notice-title"><a href="' . esc_url($notice['permalink']) . '">' . esc_html($title) . '</a></h3>';
                
                // Display meta information based on settings
                $meta_info = array();
                
                if ($notice['show_author']) {
                    $meta_info[] = 'Author: ' . esc_html($notice['author']);
                }
                
                if ($notice['show_date']) {
                    $meta_info[] = 'Date: ' . date_i18n(get_option('date_format'), $notice['timestamp']);
                }
                
                if ($notice['show_site']) {
                    $meta_info[] = 'Dept: ' . cntm_get_custom_site_name();
                }
                
                if (!empty($meta_info)) {
                    echo '<div style="font-size: 9px;" class="cntm-notice-meta">' . implode(' | ', $meta_info) . '</div>';
                }
                
                // Truncate excerpt to 100 characters
                $excerpt = cntm_truncate_text($notice['excerpt'], 100);
                echo '<div class="cntm-notice-excerpt">' . wp_kses_post($excerpt) . '</div>';
                echo '<a href="' . esc_url($notice['permalink']) . '" class="cntm-read-more">Read More</a>';
                echo '</div>';
            }
        } else {
            echo '<p>No notices found.</p>';
        }
    } else {
        echo '<p>No sites found.</p>';
    }
    
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('display_network_notices', 'cntm_display_network_notices_shortcode');
function cntm_enqueue_styles() {
    wp_enqueue_style('cntm-styles', CNTM_PLUGIN_URL . 'assets/css/style.css', array(), CNTM_VERSION);
}
add_action('wp_enqueue_scripts', 'cntm_enqueue_styles');

// Admin styles
function cntm_admin_styles() {
    wp_enqueue_style('dashicons');
}
add_action('admin_enqueue_scripts', 'cntm_admin_styles');

// Admin notice
function cntm_admin_notice() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'notice') {
        ?>
        <div class="notice notice-info">
            <p><strong>Notice Tips:</strong></p>
            <p>Use shortcode <code>[display_notices]</code> to display notices from this site.</p>
            <?php if (is_multisite()): ?>
                <p>For multisite networks:</p>
                <ul>
                <li>Use <code>[display_homepage_notices]</code> to show notices only that are approved by admin network sites.</li>
                <li>Use <code>[display_network_notices]</code> to show notices from all network sites.</li>
                    <li>Use <code>[display_site_notices site_id="<?php echo get_current_blog_id(); ?>"]</code> to show notices from this specific site like a post (With the title description and Thumbnail).</li>
                </ul>
                <p>Check "Request Homepage Display" if you want this notice to be eligible for display on the network homepage.</p>
            <?php endif; ?>
            <p>Control which metadata shows (author, date, site) using the Display Options in the sidebar.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'cntm_admin_notice');

// Add network admin menu if super admin
function cntm_add_network_admin_menu() {
    if (is_multisite() && is_super_admin()) {
        add_menu_page(
            __('Network Notices', 'custom-notice-manager'),
            __('Network Notices', 'custom-notice-manager'),
            'manage_network',
            'network-notices',
            'cntm_network_notices_page',
            'dashicons-megaphone',
            25
        );
    }
}
add_action('network_admin_menu', 'cntm_add_network_admin_menu');
// Activation Hook
function cntm_activate() {
    cntm_register_notice_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'cntm_activate');

// Deactivation Hook
function cntm_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cntm_deactivate');

// Add new meta field for approval status
function cntm_register_approval_meta() {
    register_post_meta('notice', 'cntm_homepage_approved', array(
        'type' => 'boolean',
        'description' => 'Approved for display on network homepage',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
}
add_action('init', 'cntm_register_approval_meta');
// Update the network admin page to include approval functionality
function cntm_network_notices_page() {
    if (!is_super_admin()) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Process bulk approval if submitted
    if (isset($_POST['cntm_approve_selected']) && isset($_POST['cntm_notices']) && is_array($_POST['cntm_notices'])) {
        $approved_count = 0;
        
        foreach ($_POST['cntm_notices'] as $notice) {
            list($site_id, $post_id) = explode('_', $notice);
            $site_id = intval($site_id);
            $post_id = intval($post_id);
            
            if ($site_id > 0 && $post_id > 0) {
                switch_to_blog($site_id);
                update_post_meta($post_id, 'cntm_homepage_approved', '1');
                restore_current_blog();
                $approved_count++;
            }
        }
        
        if ($approved_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                sprintf(_n('%d notice approved for homepage display.', '%d notices approved for homepage display.', $approved_count, 'custom-notice-manager'), $approved_count) . 
                '</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Network Notices Overview', 'custom-notice-manager'); ?></h1>
        
        <h2><?php _e('Homepage Notices Requests', 'custom-notice-manager'); ?></h2>
        <p><?php _e('These notices have been requested for display on the network homepage.', 'custom-notice-manager'); ?></p>
        
        <?php
        // Get all sites
        $sites = get_sites();
        $homepage_requests = array();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $query_args = array(
                'post_type' => 'notice',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'cntm_homepage_request',
                        'value' => '1',
                        'compare' => '='
                    )
                )
            );
            
            $query = new WP_Query($query_args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $homepage_approved = get_post_meta($post_id, 'cntm_homepage_approved', true);
                    
                    $homepage_requests[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'site_id' => $site->blog_id,
                        'site_name' => get_bloginfo('name'),
                        'date' => get_the_date(),
                        'permalink' => get_permalink(),
                        'author' => get_the_author(),
                        'approved' => $homepage_approved
                    );
                }
            }
            
            wp_reset_postdata();
            restore_current_blog();
        }
        
        if (!empty($homepage_requests)) {
            ?>
            <form method="post" action="">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="cntm-select-all"></th>
                            <th><?php _e('Title', 'custom-notice-manager'); ?></th>
                            <th><?php _e('Site', 'custom-notice-manager'); ?></th>
                            <th><?php _e('Author', 'custom-notice-manager'); ?></th>
                            <th><?php _e('Date', 'custom-notice-manager'); ?></th>
                            <th><?php _e('Status', 'custom-notice-manager'); ?></th>
                            <th><?php _e('Actions', 'custom-notice-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($homepage_requests as $request): ?>
                        <tr>
                            <td>
                                <?php if ($request['approved'] != '1'): ?>
                                <input type="checkbox" name="cntm_notices[]" value="<?php echo $request['site_id'] . '_' . $request['id']; ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($request['title']); ?></td>
                            <td><?php echo esc_html($request['site_name']); ?></td>
                            <td><?php echo esc_html($request['author']); ?></td>
                            <td><?php echo esc_html($request['date']); ?></td>
                            <td>
                                <?php if ($request['approved'] == '1'): ?>
                                <span style="color:green;"><span class="dashicons dashicons-yes"></span> <?php _e('Approved', 'custom-notice-manager'); ?></span>
                                <?php else: ?>
                                <span style="color:orange;"><span class="dashicons dashicons-clock"></span> <?php _e('Pending Approval', 'custom-notice-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($request['permalink']); ?>" target="_blank"><?php _e('View', 'custom-notice-manager'); ?></a>
                                <?php if ($request['approved'] != '1'): ?>
                                | <a href="<?php echo get_admin_url($request['site_id'], 'post.php?post=' . $request['id'] . '&action=edit'); ?>" target="_blank"><?php _e('Edit', 'custom-notice-manager'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <input type="submit" name="cntm_approve_selected" id="cntm_approve_selected" class="button button-primary" value="<?php _e('Approve Selected', 'custom-notice-manager'); ?>">
                    </div>
                </div>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                // Select all checkbox functionality
                $('#cntm-select-all').on('change', function() {
                    $('input[name="cntm_notices[]"]').prop('checked', $(this).prop('checked'));
                });
            });
            </script>
            
            <h3><?php _e('Approved Homepage Notices', 'custom-notice-manager'); ?></h3>
            <p><?php _e('These notices are currently approved for display on the network homepage.', 'custom-notice-manager'); ?></p>
            
            <?php
            $approved_count = 0;
            foreach ($homepage_requests as $request) {
                if ($request['approved'] == '1') {
                    $approved_count++;
                }
            }
            
            if ($approved_count > 0) {
                ?>
                <p><?php echo sprintf(_n('There is %d approved notice.', 'There are %d approved notices.', $approved_count, 'custom-notice-manager'), $approved_count); ?></p>
                <?php
            } else {
                ?>
                <p><?php _e('No notices have been approved yet.', 'custom-notice-manager'); ?></p>
                <?php
            }
            ?>
            
            <h3><?php _e('Homepage Display Shortcode', 'custom-notice-manager'); ?></h3>
            <p><?php _e('Use this shortcode on your network homepage to display the approved notices:', 'custom-notice-manager'); ?></p>
            <code>[display_homepage_notices limit="3"]</code>
            <?php
        } else {
            echo '<p>' . __('No homepage notice requests found.', 'custom-notice-manager') . '</p>';
        }
        ?>
    </div>
    <?php
}
// Modify the display options meta box to show approval status for main site admin only
// Modify the display options meta box to show original site information on main site
function cntm_display_options_callback($post) {
    // Security nonce
    wp_nonce_field('cntm_save_meta_box_data', 'cntm_meta_box_nonce');
    
    // Get meta values with proper default handling
    $show_author = get_post_meta($post->ID, 'cntm_show_author', true) ? '1' : '0';
    $show_date = get_post_meta($post->ID, 'cntm_show_date', true) ? '1' : '0';
    $show_site = get_post_meta($post->ID, 'cntm_show_site', true) ? '1' : '0';
    $homepage_request = get_post_meta($post->ID, 'cntm_homepage_request', true) ? '1' : '0';
    $homepage_approved = get_post_meta($post->ID, 'cntm_homepage_approved', true) ? '1' : '0';
    
    // Get network information if this is a synced post
    $network_post_id = get_post_meta($post->ID, 'cntm_network_post_id', true);
    $network_site_id = get_post_meta($post->ID, 'cntm_network_site_id', true);
    $original_site_name = get_post_meta($post->ID, 'cntm_original_site_name', true);
    $original_author = get_post_meta($post->ID, 'cntm_original_author', true);
    
    // Check if we're on the main site (site_id = 1) AND the user is an admin
    $is_main_site_admin = false;
    if (is_multisite()) {
        $current_site_id = get_current_blog_id();
        $is_main_site = ($current_site_id == 1);
        
        if ($is_main_site) {
            $is_main_site_admin = current_user_can('manage_options');
        }
    }
    
    // Display original site information if this is a synced post on the main site
    if ($is_main_site && !empty($network_post_id) && !empty($network_site_id)) {
        // Validate that the stored site name matches the network_site_id
        switch_to_blog($network_site_id);
        $expected_site_name = get_bloginfo('name');
        restore_current_blog();

        // If the stored site name doesn't match, update it or log a warning
        if ($original_site_name !== $expected_site_name) {
            error_log("CNTM DEBUG: Site name mismatch for post {$post->ID}. Stored: {$original_site_name}, Expected: {$expected_site_name}. Using expected name.");
            $original_site_name = $expected_site_name;
            update_post_meta($post->ID, 'cntm_original_site_name', $original_site_name);
        }

        echo '<div class="cntm-original-post-info" style="background-color: #f8f8f8; padding: 10px; margin-bottom: 15px; border-left: 4px solid #0073aa;">';
        echo '<h4 style="margin-top: 0;">' . __('Original Notice Information', 'custom-notice-manager') . '</h4>';
        
        if (!empty($original_site_name)) {
            echo '<p><strong>' . __('Original Site:', 'custom-notice-manager') . '</strong> ' . esc_html($original_site_name) . '</p>';
        }
        
        if (!empty($original_author)) {
            echo '<p><strong>' . __('Original Author:', 'custom-notice-manager') . '</strong> ' . esc_html($original_author) . '</p>';
        }
        
        echo '<p><strong>' . __('Network Site ID:', 'custom-notice-manager') . '</strong> ' . intval($network_site_id) . '</p>';
        echo '<p><strong>' . __('Original Post ID:', 'custom-notice-manager') . '</strong> ' . intval($network_post_id) . '</p>';
        
        // View link to original post
        echo '<p><a href="' . esc_url(get_admin_url($network_site_id, 'post.php?post=' . $network_post_id . '&action=edit')) . '" target="_blank" class="button button-secondary">';
        echo __('View Original Post', 'custom-notice-manager');
        echo '</a></p>';
        
        echo '</div>';
    }
    
    // Display the normal meta options
    ?>
    <p>
        <label>
            <input type="checkbox" name="cntm_show_author" value="1" <?php checked($show_author, '1'); ?> />
            <?php _e('Show Author Name', 'custom-notice-manager'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="cntm_show_date" value="1" <?php checked($show_date, '1'); ?> />
            <?php _e('Show Publication Date', 'custom-notice-manager'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="cntm_show_site" value="1" <?php checked($show_site, '1'); ?> />
            <?php _e('Show Site Name', 'custom-notice-manager'); ?>
        </label>
    </p>
    <?php if (is_multisite()): ?>
    <hr>
    <p>
        <label>
            <input type="checkbox" name="cntm_homepage_request" value="1" <?php checked($homepage_request, '1'); ?> 
                   id="cntm_homepage_request_checkbox" />
            <?php _e('Request Homepage Display', 'custom-notice-manager'); ?>
        </label>
        <br>
        <small><?php _e('Request this notice to be displayed on the network homepage', 'custom-notice-manager'); ?></small>
    </p>
    
    <?php if ($is_main_site_admin): ?>
    <p>
        <label>
            <input type="checkbox" name="cntm_homepage_approved" value="1" <?php checked($homepage_approved, '1'); ?> />
            <?php _e('Approve for Homepage Display', 'custom-notice-manager'); ?>
        </label>
        <br>
        <small><?php _e('Approve this notice for display on the network homepage (main site admin only)', 'custom-notice-manager'); ?></small>
    </p>
    <?php elseif ($homepage_approved == '1'): ?>
    <p>
        <span class="dashicons dashicons-yes" style="color:green;"></span>
        <?php _e('Approved for homepage display', 'custom-notice-manager'); ?>
    </p>
    <?php elseif ($homepage_request == '1'): ?>
    <p>
        <span class="dashicons dashicons-clock" style="color:orange;"></span>
        <?php _e('Pending approval for homepage display', 'custom-notice-manager'); ?>
    </p>
    <?php endif; ?>
    
    <?php endif; 
    // Add JavaScript to redirect to admin section when request checkbox is clicked
    if (is_multisite() && !$is_main_site_admin): ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Store the initial state of the checkbox
        var initialRequestState = $('#cntm_homepage_request_checkbox').is(':checked');
        
        // When the form is submitted
        $('form#post').on('submit', function() {
            // If the checkbox is checked and it wasn't initially checked
            if ($('#cntm_homepage_request_checkbox').is(':checked') && !initialRequestState) {
                // Save this information to notify the admin
                localStorage.setItem('cntm_new_homepage_request', 'true');
            }
        });
    });
    </script>
    <?php endif;
} 
// Add a separate function to handle deletion after save
function cntm_handle_homepage_request_removal($post_id, $post, $update) {
    if ($post->post_type !== 'notice' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    if (!is_multisite() || get_current_blog_id() == 1) {
        return;
    }

    $old_homepage_request = get_post_meta($post_id, 'cntm_homepage_request', true);
    $new_homepage_request = get_post_meta($post_id, 'cntm_homepage_request', true); // Get updated value

    if ($new_homepage_request === '0' && $old_homepage_request === '1') {
        $current_site_id = get_current_blog_id();
        error_log("CNTM DEBUG: Post-save check - Homepage request removed for post $post_id on site $current_site_id");

        switch_to_blog(1);
        $existing_posts = get_posts([
            'post_type'   => 'notice',
            'meta_query'  => [
                [
                    'key'     => 'cntm_network_post_id',
                    'value'   => $post_id,
                    'compare' => '='
                ],
                [
                    'key'     => 'cntm_network_site_id',
                    'value'   => $current_site_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status'    => 'any'
        ]);

        if (!empty($existing_posts)) {
            $main_post_id = $existing_posts[0]->ID;
            wp_delete_post($main_post_id, true);
            error_log("CNTM DEBUG: Post-save deletion - Removed post $main_post_id from main site.");
        } else {
            error_log("CNTM DEBUG: Post-save check - No synced post found on main site for $post_id.");
        }
        restore_current_blog();
    }
}
add_action('save_post', 'cntm_handle_homepage_request_removal', 20, 3); // Priority 20 to run after meta updates
// Update the save_meta_box function to handle the approval field, notification, and deletion
function cntm_save_meta_box_data($post_id) {
    if (!isset($_POST['cntm_meta_box_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['cntm_meta_box_nonce'], 'cntm_save_meta_box_data')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Update meta fields
    update_post_meta($post_id, 'cntm_show_author', isset($_POST['cntm_show_author']) ? '1' : '0');
    update_post_meta($post_id, 'cntm_show_date', isset($_POST['cntm_show_date']) ? '1' : '0');
    update_post_meta($post_id, 'cntm_show_site', isset($_POST['cntm_show_site']) ? '1' : '0');
    
    // Handle homepage request and deletion logic if multisite
    if (is_multisite()) {
        $old_homepage_request = get_post_meta($post_id, 'cntm_homepage_request', true);
        $new_homepage_request = isset($_POST['cntm_homepage_request']) ? '1' : '0';
        
        // Update homepage request field
        update_post_meta($post_id, 'cntm_homepage_request', $new_homepage_request);
        
        $current_site_id = get_current_blog_id();
        $is_main_site = ($current_site_id == 1);
        
        // Debug logging
        error_log("CNTM DEBUG: Saving post $post_id on site $current_site_id. Old request: $old_homepage_request, New request: $new_homepage_request");
        
        // If this is not the main site, handle homepage request changes
        if (!$is_main_site) {
            // New homepage request
            if ($new_homepage_request === '1' && $old_homepage_request !== '1') {
                error_log("CNTM DEBUG: New homepage request for post $post_id on site $current_site_id");
                $site_name = get_bloginfo('name');
                $post_title = get_the_title($post_id);
                $post_link = get_permalink($post_id);
                $author_id = get_post_field('post_author', $post_id);
                $author_name = get_the_author_meta('display_name', $author_id);
                
                $subject = sprintf(__('[%s] New Homepage Notice Request', 'custom-notice-manager'), $site_name);
                $message = sprintf(
                    __('A new notice has been requested for the network homepage:\n\nTitle: %s\nSite: %s\nAuthor: %s\nLink: %s\n\nPlease review this request in the Network Notices section.', 'custom-notice-manager'),
                    $post_title,
                    $site_name,
                    $author_name,
                    $post_link
                );
                
                switch_to_blog(1);
                $admin_email = get_option('admin_email');
                restore_current_blog();
                
                wp_mail($admin_email, $subject, $message);
            }
            
            // Homepage request removed
            if ($new_homepage_request === '0' && $old_homepage_request === '1') {
                error_log("CNTM DEBUG: Homepage request removed for post $post_id on site $current_site_id. Attempting to delete from main site.");
                
                // Switch to main site
                switch_to_blog(1);
                
                // Find the synced post
                $existing_posts = get_posts([
                    'post_type'   => 'notice',
                    'meta_query'  => [
                        [
                            'key'     => 'cntm_network_post_id',
                            'value'   => $post_id,
                            'compare' => '='
                        ],
                        [
                            'key'     => 'cntm_network_site_id',
                            'value'   => $current_site_id,
                            'compare' => '='
                        ]
                    ],
                    'posts_per_page' => 1,
                    'post_status'    => 'any' // Look for any status in case it's not published
                ]);
                
                if (!empty($existing_posts)) {
                    $main_post_id = $existing_posts[0]->ID;
                    error_log("CNTM DEBUG: Found synced post $main_post_id on main site. Deleting...");
                    
                    // Force delete the post
                    $result = wp_delete_post($main_post_id, true);
                    if ($result) {
                        error_log("CNTM DEBUG: Successfully deleted post $main_post_id from main site.");
                    } else {
                        error_log("CNTM DEBUG: Failed to delete post $main_post_id from main site.");
                    }
                } else {
                    error_log("CNTM DEBUG: No synced post found on main site for post $post_id from site $current_site_id.");
                }
                
                // Restore original site
                restore_current_blog();
            }
        }
        
        // Only main site admin can update the approval status
        $is_main_site_admin = $is_main_site && current_user_can('manage_options');
        if ($is_main_site_admin) {
            update_post_meta($post_id, 'cntm_homepage_approved', isset($_POST['cntm_homepage_approved']) ? '1' : '0');
        }
    }
}
add_action('save_post', 'cntm_save_meta_box_data');
// Register the homepage notices shortcode
add_shortcode('display_homepage_notices', 'cntm_display_homepage_notices_shortcode');
function cntm_sync_notice_to_main($post_id, $post, $update) {
    // Ensure it's an notice post type and not an auto-save
    if ($post->post_type !== 'notice' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    // If it's a revision, get the real post ID
    if (wp_is_post_revision($post_id)) {
        $post_id = wp_get_post_parent_id($post_id);
    }

    // Get the "Request Homepage Display" status
    $homepage_request = get_post_meta($post_id, 'cntm_homepage_request', true);

    // If "Request Homepage Display" is not checked, do nothing
    if ($homepage_request != '1') {
        return;
    }

    // Only sync published posts
    if ($post->post_status !== 'publish') {
        return;
    }

    // Check if this post has already been processed to prevent infinite loops
    $currently_syncing = get_transient('cntm_syncing_' . $post_id);
    if ($currently_syncing) {
        return;
    }
    
    // Set a transient to prevent loop
    set_transient('cntm_syncing_' . $post_id, true, 30);

    // Get the current blog ID (original site)
    $current_blog_id = get_current_blog_id();

    // Don't sync if we're already on the main site
    if ($current_blog_id == 1) {
        delete_transient('cntm_syncing_' . $post_id);
        return;
    }

    // Get the original site name BEFORE switching to the main site
    $original_site_name =  cntm_get_custom_site_name();

    // Get ALL meta data from the original post to sync
    $all_meta = get_post_meta($post_id);
    
    // Get specific display options from the original post
    $show_author = isset($all_meta['cntm_show_author'][0]) ? $all_meta['cntm_show_author'][0] : '0';
    $show_date = isset($all_meta['cntm_show_date'][0]) ? $all_meta['cntm_show_date'][0] : '0';
    $show_site = isset($all_meta['cntm_show_site'][0]) ? $all_meta['cntm_show_site'][0] : '0';

    // Switch to the main site (Admin Site ID = 1)
    switch_to_blog(1);

    // Check if the post already exists in the main site
    $existing_posts = get_posts([
        'post_type'   => 'notice',
        'meta_query'  => [
            [
                'key'   => 'cntm_network_post_id',
                'value' => $post_id,
                'compare' => '='
            ],
            [
                'key'   => 'cntm_network_site_id',
                'value' => $current_blog_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    if (!empty($existing_posts)) {
        // Update existing post in admin site
        $main_post_id = $existing_posts[0]->ID;
        wp_update_post([
            'ID'           => $main_post_id,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'pending' // Keep it pending until approved
        ]);
        
        // Update meta fields - ensure all important meta is copied
        update_post_meta($main_post_id, 'cntm_show_author', $show_author);
        update_post_meta($main_post_id, 'cntm_show_date', $show_date);
        update_post_meta($main_post_id, 'cntm_show_site', $show_site);
        update_post_meta($main_post_id, 'cntm_homepage_request', '1');
        
        // Copy over any existing approval status
        $homepage_approved = isset($all_meta['cntm_homepage_approved'][0]) ? $all_meta['cntm_homepage_approved'][0] : '0';
        update_post_meta($main_post_id, 'cntm_homepage_approved', $homepage_approved);
        
        // Store the original site name for reference
        update_post_meta($main_post_id, 'cntm_original_site_name', $original_site_name);
        
        // Store the original author name
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        update_post_meta($main_post_id, 'cntm_original_author', $author_name);
    } else {
        // Insert a new post into the main site
        $main_post_id = wp_insert_post([
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'pending',
            'post_type'    => 'notice',
            'post_author'  => 1, // Default to admin user on main site
        ]);
        
        if (!is_wp_error($main_post_id)) {
            // Add the meta data to connect the posts
            update_post_meta($main_post_id, 'cntm_network_post_id', $post_id);
            update_post_meta($main_post_id, 'cntm_network_site_id', $current_blog_id);
            
            // Copy display options
            update_post_meta($main_post_id, 'cntm_show_author', $show_author);
            update_post_meta($main_post_id, 'cntm_show_date', $show_date);
            update_post_meta($main_post_id, 'cntm_show_site', $show_site);
            update_post_meta($main_post_id, 'cntm_homepage_request', '1');
            
            // Store the original site name for reference
            update_post_meta($main_post_id, 'cntm_original_site_name', $original_site_name);
            
            // Store the original author name
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            update_post_meta($main_post_id, 'cntm_original_author', $author_name);
        }
    }
    
    // Sync featured image if available
    if (has_post_thumbnail($post_id)) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $attachment_path = get_attached_file($thumbnail_id);
        
        if ($attachment_path && file_exists($attachment_path)) {
            error_log("CNTM DEBUG: Attempting to sync featured image for post $post_id from site $current_blog_id to main site.");
            
            // Check if we've already synced this image
            $existing_attachment_id = get_post_meta($main_post_id, 'cntm_synced_thumbnail_id', true);
            
            if (!$existing_attachment_id) {
                // Prepare the file array for sideload
                $file_array = array(
                    'name' => basename($attachment_path),
                    'tmp_name' => $attachment_path,
                );
                
                // Let WordPress handle the upload
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                
                $attachment_id = media_handle_sideload($file_array, $main_post_id);
                
                if (!is_wp_error($attachment_id)) {
                    // Set as featured image
                    $result = set_post_thumbnail($main_post_id, $attachment_id);
                    
                    if ($result) {
                        update_post_meta($main_post_id, 'cntm_synced_thumbnail_id', $attachment_id);
                        error_log("CNTM DEBUG: Successfully synced featured image for post $main_post_id. New attachment ID: $attachment_id");
                    } else {
                        error_log("CNTM DEBUG: Failed to set thumbnail for post $main_post_id with attachment ID $attachment_id");
                    }
                } else {
                    error_log("CNTM DEBUG: Failed to sideload image for post $main_post_id: " . $attachment_id->get_error_message());
                }
            } else {
                // Re-attach existing image if it exists
                $result = set_post_thumbnail($main_post_id, $existing_attachment_id);
                if ($result) {
                    error_log("CNTM DEBUG: Re-attached existing image ID $existing_attachment_id to post $main_post_id");
                } else {
                    error_log("CNTM DEBUG: Failed to re-attach existing image ID $existing_attachment_id to post $main_post_id");
                }
            }
        } else {
            error_log("CNTM DEBUG: Featured image path not found or inaccessible for post $post_id: $attachment_path");
            
            // Fallback: Try fetching via URL
            $image_url = wp_get_attachment_url($thumbnail_id);
            if ($image_url) {
                $response = wp_remote_get($image_url);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                    $file_array = array(
                        'name' => basename($attachment_path),
                        'tmp_name' => wp_tempnam(),
                    );
                    file_put_contents($file_array['tmp_name'], wp_remote_retrieve_body($response));
                    
                    $attachment_id = media_handle_sideload($file_array, $main_post_id);
                    if (!is_wp_error($attachment_id)) {
                        $result = set_post_thumbnail($main_post_id, $attachment_id);
                        if ($result) {
                            update_post_meta($main_post_id, 'cntm_synced_thumbnail_id', $attachment_id);
                            error_log("CNTM DEBUG: Successfully synced featured image via URL for post $main_post_id. New attachment ID: $attachment_id");
                        }
                    } else {
                        error_log("CNTM DEBUG: Failed to sideload image via URL for post $main_post_id: " . $attachment_id->get_error_message());
                    }
                    @unlink($file_array['tmp_name']); // Clean up temporary file
                } else {
                    error_log("CNTM DEBUG: Failed to fetch image via URL for post $post_id: " . ($response->get_error_message() ?: 'HTTP error'));
                }
            }
        }
    }
    
    // Restore original site context
    restore_current_blog();
    
    // Mark as sent to main site
    update_post_meta($post_id, 'cntm_sent_to_main', '1');
    
    // Clean up transient
    delete_transient('cntm_syncing_' . $post_id);
}
add_action('save_post', 'cntm_sync_notice_to_main', 10, 3);
function cntm_display_homepage_notices_shortcode($atts) {
    if (!is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 5,
    ), $atts, 'display_homepage_notices');
    
    $limit = intval($atts['limit']);
    
    ob_start();
    echo '<div class="cntm-homepage-notices">';
    
    // Get all sites
    $sites = get_sites();
    $approved_notices = array();
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        $query_args = array(
            'post_type' => 'notice',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => 'cntm_homepage_approved',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($query_args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $approved_notices[] = array(
                    'ID' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'excerpt' => get_the_excerpt(),
                    'author' => get_the_author(),
                    'site_name' => get_bloginfo('name'),
                    'site_id' => $site->blog_id,
                    'show_author' => get_post_meta($post_id, 'cntm_show_author', true),
                    'show_date' => get_post_meta($post_id, 'cntm_show_date', true),
                    'show_site' => get_post_meta($post_id, 'cntm_show_site', true),
                    'timestamp' => get_post_time('U', true),
                    'has_thumbnail' => has_post_thumbnail(),
                    'thumbnail_html' => get_the_post_thumbnail(null, 'thumbnail')
                );
            }
        }
        
        wp_reset_postdata();
        restore_current_blog();
    }
    
    // Sort by date (newest first)
    usort($approved_notices, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Limit the total number of notices
    $approved_notices = array_slice($approved_notices, 0, $limit);
    
    if (!empty($approved_notices)) {
        echo '<div class="cntm-notices-grid">';
        foreach ($approved_notices as $notice) {
            echo '<div class="cntm-notice-card">';
            
            // Display featured image
            if ($notice['has_thumbnail']) {
                echo '<div class="cntm-notice-thumbnail">';
                echo $notice['thumbnail_html'];
                echo '</div>';
            }
            
            // Title
            echo '<h5 class="cntm-notice-title"><a href="' . esc_url($notice['permalink']) . '">' . esc_html($notice['title']) . '</a></h5>';
            
            // Meta information
            $meta_info = array();
            
            if ($notice['show_author']) {
                $meta_info[] = 'Author: ' . esc_html($notice['author']);
            }
            
            if ($notice['show_date']) {
                $meta_info[] = 'Date: ' . date_i18n(get_option('date_format'), $notice['timestamp']);
            }
            
            if ($notice['show_site']) {
                $meta_info[] = 'Dept: ' . cntm_get_custom_site_name();
            }
            
            if (!empty($meta_info)) {
                echo '<div style="font-size: 9px;" class="cntm-notice-meta">' . implode(' | ', $meta_info) . '</div>';
            }
            
            // Excerpt
            echo '<div class="cntm-notice-excerpt">' . wp_kses_post(cntm_truncate_text($notice['excerpt'], 100)) . '</div>';
            echo '<a href="' . esc_url($notice['permalink']) . '" class="cntm-read-more">Read More</a>';
            echo '</div>'; // .cntm-notice-card
        }
        echo '</div>'; // .cntm-notices-grid
    } else {
        echo '<p>No approved homepage notices found.</p>';
    }
    
    echo '</div>'; // .cntm-homepage-notices
    
    // Add custom CSS for homepage notices
    echo '<style>
        .cntm-notices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .cntm-notice-card {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .cntm-notice-thumbnail img {
            width: 100%;
            height: auto;
            display: block;
        }
        .cntm-notice-title {
            margin: 15px 15px 10px;
            font-size: 18px;
        }
        .cntm-notice-title a {
            color: #333;
            text-decoration: none;
        }
        .cntm-notice-meta {
            margin: 0 15px 10px;
            font-size: 9px;
            color: #666;
        }
        .cntm-notice-excerpt {
            margin: 0 15px 15px;
            font-size: 15px;
            line-height: 1.5;
            flex-grow: 1;
        }
        .cntm-read-more {
            display: inline-block;
            margin: 0 15px 15px;
            padding: 8px 15px;
            background: #993333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
            align-self: flex-start;
        }
        .cntm-read-more:hover {
            background: #7a2828;
        }
        @media (max-width: 768px) {
            .cntm-notices-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>';
    
    return ob_get_clean();
}


// Connect the column display function
add_action('manage_notice_posts_custom_column', 'cntm_display_shortcode_column', 10, 2);
// Create folder structure during activation
function cntm_create_folders() {
    // Create assets/css folder if it doesn't exist
    $css_dir = CNTM_PLUGIN_DIR . 'assets/css';
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    
    // Create CSS file if it doesn't exist
    $css_file = $css_dir . '/style.css';
    if (!file_exists($css_file)) {
        $default_css = "/* Custom Notice Manager Styles */

.cntm-notice-meta {
    font-size: 9px;
}
";
        
        file_put_contents($css_file, $default_css);
    }
}

// Update activation hook to create folders
register_activation_hook(__FILE__, function() {
    cntm_register_notice_post_type();
    cntm_create_folders();
    flush_rewrite_rules();
});
// Make homepage request and approval columns sortable
function cntm_sortable_notice_columns($columns) {
    $columns['homepage_request'] = 'homepage_request';
    $columns['homepage_approved'] = 'homepage_approved';
    return $columns;
}
add_filter('manage_edit-notice_sortable_columns', 'cntm_sortable_notice_columns');

// Handle sorting
function cntm_notice_request_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('homepage_request' === $orderby) {
        $query->set('meta_key', 'cntm_homepage_request');
        $query->set('orderby', 'meta_value_num');
    }
    
    if ('homepage_approved' === $orderby) {
        $query->set('meta_key', 'cntm_homepage_approved');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'cntm_notice_request_orderby');
// Add this to your plugin file
function cntm_filter_notice_content($content) {
    global $post;
    
    // Only apply to single notice posts
    if (!is_singular('notice') || !is_main_query()) {
        return $content;
    }
    
    // Check if this is a synced post on the main site
    $is_main_site = (is_multisite() && get_current_blog_id() == 1);
    $network_post_id = get_post_meta($post->ID, 'cntm_network_post_id', true);
    $network_site_id = get_post_meta($post->ID, 'cntm_network_site_id', true);
    $is_synced_post = $is_main_site && !empty($network_post_id) && !empty($network_site_id);
    
    // Get our custom post meta
    $show_author = get_post_meta($post->ID, 'cntm_show_author', true);
    $show_date = get_post_meta($post->ID, 'cntm_show_date', true);
    $show_site = get_post_meta($post->ID, 'cntm_show_site', true);
    
    // For synced posts, fetch the original site name using cntm_get_custom_site_name()
    if ($is_synced_post) {
        // Use the original site ID for getting the custom site name
        $site_name = get_post_meta($post->ID, 'cntm_original_site_name', true);
        if (empty($site_name) && function_exists('cntm_get_custom_site_name')) {
            // If site name not in meta, try to get it using the custom function and network site ID
            $site_name = cntm_get_custom_site_name($network_site_id);
        }
    } else {
        // For non-synced posts, get the current site's custom name
        $site_name = function_exists('cntm_get_custom_site_name') ? 
                    cntm_get_custom_site_name(get_current_blog_id()) : 
                    get_bloginfo('name');
    }
    
    // Start with the original content
    $filtered_content = $content;
    
    // Add a wrapper for styling
    $filtered_content = '<div class="cntm-single-notice">' . $filtered_content . '</div>';
    
    // Remove the default author/date display by adding custom CSS
    $css = '<style>
        .cntm-single-notice .entry-meta,
        .cntm-single-notice .post-meta,
        .cntm-single-notice .entry-header .posted-on,
        .cntm-single-notice .entry-header .byline {
            display: none !important;
        }
    </style>';
    
    // Only display the meta info if explicitly enabled
    $meta_html = '';
    $meta_items = array();
    
    if ($show_author == '1') {
        $meta_items[] = 'Author: ' . get_the_author();
    }
    
    if ($show_date == '1') {
        $meta_items[] = 'Date: ' . get_the_date();
    }
    
    if ($show_site == '1' && is_multisite()) {
        $meta_items[] = 'Dept: ' . esc_html($site_name);
    }
    
    if (!empty($meta_items)) {
        $meta_html = '<div style="font-size: 9px;" class="cntm-notice-meta">' . implode(' | ', $meta_items) . '</div>';
    }
    
    return $css . $meta_html . $filtered_content;
}
add_filter('the_content', 'cntm_filter_notice_content');
// Hide author and date for notice posts
function cntm_hide_notice_meta($content) {
    if (is_singular('notice')) {
        // Add CSS to hide common theme author/date elements
        $style = '<style>
            .entry-meta, 
            .post-meta, 
            .entry-footer,
            .posted-on,
            .byline,
            .post-author,
            .post-date { 
                display: none !important; 
            }
        </style>';
        
        // Also try to remove the specific "By author | Date" line from content
        $pattern = '/By\s+\w+\s+\|\s+[A-Za-z]+\s+\d+,\s+\d{4}\s+\|/';
        $content = preg_replace($pattern, '', $content);
        
        // Remove the specific "Author: author | Dept:" line
        $pattern2 = '/Author:\s+\w+\s+\|\s+Dept:.+/';
        $content = preg_replace($pattern2, '', $content);
        
        return $style . $content;
    }
    return $content;
}
add_filter('the_content', 'cntm_hide_notice_meta', 1); // Priority 1 makes it run early

// Add Settings menu in admin
function cntm_add_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=notice',  // Parent menu (Notices)
        __('Notice Settings', 'custom-notice-manager'),  // Page title
        __('Settings', 'custom-notice-manager'),  // Menu title
        'manage_options',  // Capability required
        'notice-settings',  // Menu slug
        'cntm_settings_page_callback'  // Callback function
    );
}
add_action('admin_menu', 'cntm_add_settings_menu');

// Settings page callback function
function cntm_settings_page_callback() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Save settings if form is submitted
    if (isset($_POST['cntm_settings_submit']) && check_admin_referer('cntm_settings_nonce')) {
        // Get and sanitize the custom site name
        $site_name = isset($_POST['cntm_custom_site_name']) ? 
                    sanitize_text_field($_POST['cntm_custom_site_name']) : 
                    get_bloginfo('name');
        
        // Save the custom site name
        update_option('cntm_custom_site_name', $site_name);
        
        // Get and sanitize default number of notices
        $default_limit = isset($_POST['cntm_default_limit']) ? 
                        intval($_POST['cntm_default_limit']) : 
                        5;
        
        // Save the default limit
        update_option('cntm_default_limit', $default_limit);
        
        // Get and sanitize custom CSS
        $custom_css = isset($_POST['cntm_custom_css']) ? 
                     wp_strip_all_tags($_POST['cntm_custom_css']) : 
                     '';
        
        // Save the custom CSS
        update_option('cntm_custom_css', $custom_css);
        
        // Display a success message
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'custom-notice-manager') . '</p></div>';
    }
    
    // Get current settings
    $current_site_name = get_option('cntm_custom_site_name', get_bloginfo('name'));
    $default_limit = get_option('cntm_default_limit', 5);
    $custom_css = get_option('cntm_custom_css', '');
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Notice Settings', 'custom-notice-manager'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('cntm_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Custom Site Name', 'custom-notice-manager'); ?></th>
                    <td>
                        <input type="text" name="cntm_custom_site_name" value="<?php echo esc_attr($current_site_name); ?>" class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('This name will be displayed when an author checks "Show Site Name" for an notice.', 'custom-notice-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Number of Notices', 'custom-notice-manager'); ?></th>
                    <td>
                        <input type="number" name="cntm_default_limit" value="<?php echo esc_attr($default_limit); ?>" min="1" max="20">
                        <p class="description">
                            <?php echo esc_html__('Default number of notices to display when using shortcodes.', 'custom-notice-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Custom CSS', 'custom-notice-manager'); ?></th>
                    <td>
                        <textarea name="cntm_custom_css" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Add custom CSS to style the [display_notices] shortcode output.', 'custom-notice-manager'); ?>
                            <br>
                            <?php echo esc_html__('Example: .cntm-notices-list { background: #f5f5f5; padding: 15px; }', 'custom-notice-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="cntm_settings_submit" class="button button-primary" value="<?php echo esc_attr__('Save Settings', 'custom-notice-manager'); ?>">
            </p>
        </form>
    </div>
    <?php
}

// Modify the function that displays site name to use the custom site name
function cntm_get_custom_site_name() {
    return get_option('cntm_custom_site_name', get_bloginfo('name'));
}

// Apply the custom site name when displaying site information
function cntm_display_custom_site_name($site_name) {
    // If we're on the main site and the custom site name is set
    if (is_main_site() && get_option('cntm_custom_site_name')) {
        return get_option('cntm_custom_site_name');
    }
    return $site_name;
}
add_filter('bloginfo', 'cntm_display_custom_site_name', 10, 1);

// Function to output custom CSS in the header
function cntm_output_custom_css() {
    $custom_css = get_option('cntm_custom_css');
    
    if (!empty($custom_css)) {
        echo '<style type="text/css">' . "\n";
        echo esc_html($custom_css) . "\n";
        echo '</style>' . "\n";
    }
}
add_action('wp_head', 'cntm_output_custom_css');

function cntm_add_notice_columns($columns) {
    $new_columns = array();
    
    // Insert columns after title
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['site_name'] = __('Site Name', 'custom-notice-manager'); // Add new column
            $new_columns['shortcode'] = __('Shortcode', 'custom-notice-manager');
            
            if (is_multisite()) {
                $new_columns['homepage_request'] = __('Homepage Request', 'custom-notice-manager');
                
                if (is_super_admin()) {
                    $new_columns['homepage_approved'] = __('Approval Status', 'custom-notice-manager');
                }
            }
        }
    }
    
    return $new_columns;
}
add_filter('manage_notice_posts_columns', 'cntm_add_notice_columns');
function cntm_display_shortcode_column($column, $post_id) {
    // Check if this is a synced post on the main site (multisite only)
    $is_main_site = is_multisite() && get_current_blog_id() == 1;
    $network_site_id = get_post_meta($post_id, 'cntm_network_site_id', true);
    $is_synced_post = $is_main_site && !empty($network_site_id);

    // Handle the new site_name column
    if ($column === 'site_name') {
        if ($is_synced_post) {
            // For synced posts on the main site, use the stored site name or fetch it
            $site_name = get_post_meta($post_id, 'cntm_original_site_name', true);
            if (empty($site_name)) {
                $site_name = cntm_get_custom_site_name($network_site_id);
            }
            echo esc_html($site_name);
        } else {
            // For non-synced posts or single site, use the current site's custom name
            echo esc_html(cntm_get_custom_site_name());
        }
    }

    // Existing logic for other columns
    if ($column === 'shortcode') {
        echo '<code>[display_notices]</code>';
        
        // For multisite, show shortcode with site ID
        if (is_multisite()) {
            $site_id = get_current_blog_id();
            echo '<br><code>[display_site_notices site_id="'.$site_id.'"]</code>';
        }
    }
    
    // Display homepage request status
    if (is_multisite() && $column === 'homepage_request') {
        $homepage_request = get_post_meta($post_id, 'cntm_homepage_request', true);
        if ($homepage_request) {
            echo '<span style="color:green;"><span class="dashicons dashicons-yes"></span> ' . __('Requested', 'custom-notice-manager') . '</span>';        
        } 
        else {
                echo '<span style="color:gray;"><span class="dashicons dashicons-no"></span> ' . __('Not Requested', 'custom-notice-manager') . '</span>';
            }
        }
        
        // Display approval status for super admins
        if (is_multisite() && is_super_admin() && $column === 'homepage_approved') {
            $homepage_approved = get_post_meta($post_id, 'cntm_homepage_approved', true);
            $homepage_request = get_post_meta($post_id, 'cntm_homepage_request', true);
            
            if ($homepage_approved) {
                echo '<span style="color:green;"><span class="dashicons dashicons-yes"></span> ' . __('Approved', 'custom-notice-manager') . '</span>';
            } elseif ($homepage_request) {
                echo '<span style="color:orange;"><span class="dashicons dashicons-clock"></span> ' . __('Pending', 'custom-notice-manager') . '</span>';
            } else {
                echo '<span style="color:gray;"><span class="dashicons dashicons-minus"></span> ' . __('Not Applicable', 'custom-notice-manager') . '</span>';
            }
        }
    }
    add_action('manage_notice_posts_custom_column', 'cntm_display_shortcode_column', 10, 2);
    ?>