<?php
/**
 * Plugin Name: Message Templates Manager
 * Description: Adds a "Message Templates" post type to the admin menu.
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

class MessageTemplatesManager {

    public function __construct() {
        add_action('init', [$this, 'register_cpt_message_template']);
    }

    public function register_cpt_message_template() {
        $labels = [
            'name'                  => _x('Message Templates', 'Post type general name', 'textdomain'),
            'singular_name'         => _x('Message Template', 'Post type singular name', 'textdomain'),
            'menu_name'             => _x('Message Templates', 'Admin Menu text', 'textdomain'),
            'name_admin_bar'        => _x('Message Template', 'Add New on Toolbar', 'textdomain'),
            'add_new'               => __('Add New', 'textdomain'),
            'add_new_item'          => __('Add New Template', 'textdomain'),
            'new_item'              => __('New Template', 'textdomain'),
            'edit_item'             => __('Edit Template', 'textdomain'),
            'view_item'             => __('View Template', 'textdomain'),
            'all_items'             => __('All Templates', 'textdomain'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false, // Not visible on the front-end
            'publicly_queryable' => false,
            'show_ui'            => true,  // Visible in the admin panel
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 27,
            'menu_icon'          => 'dashicons-format-chat',
            'supports'           => ['title', 'editor'], // Title for template name, Editor for message body
        ];

        register_post_type('message_template', $args);
    }
    
    public static function on_activation() {
        // Flush rewrite rules to ensure the CPT is recognized immediately.
        flush_rewrite_rules();
    }
}

new MessageTemplatesManager();
register_activation_hook(__FILE__, ['MessageTemplatesManager', 'on_activation']);