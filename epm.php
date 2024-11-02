<?php

/*
* Plugin Name: External Post Manager
* Description: Allows you to create and manage external posts
* Version: 1.0
* Author: Mofizul
* Author URI: https://mofizul.com
* Text Domain: external-post-manager
*/

if (!defined('ABSPATH')) {
    exit();
}

// External Post Manager Class
class ExternalPostManager {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    public function register_endpoints() {
        // Create post endpoint
        register_rest_route('external-post-manager/v1', '/create-post', [
            'methods' => 'POST',
            'callback' => [$this, 'create_post'],
            'permission_callback' => [$this, 'authenticate_request'],
        ]);

        // Edit post endpoint
        register_rest_route('external-post-manager/v1', '/edit-post', [
            'methods' => 'POST',
            'callback' => [$this, 'edit_post'],
            'permission_callback' => [$this, 'authenticate_request'],
        ]);

        // Delete post
        register_rest_route('external-post-manager/v1', '/delete-post', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_post'],
            'permission_callback' => [$this, 'authenticate_request'],
        ]);
    }


    // Show response
    private function respond($message, $success = true) {
        return new WP_REST_Response([
            'success' => $success,
            'message' => $message,
        ]);
    }

    // Create Post
    public function create_post($request) {
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $status  = $request->get_param('status') ?: 'draft';
        $category = $request->get_param('category');

        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_category' => $category ? [$category] : [],
        ];

        $post_id = wp_insert_post($post_data);

        // Display error if occur
        if (is_wp_error($post_id)) {
            return $this->respond('Failed to create post', false);
        }

        return $this->respond("Post created successfully. Post ID: $post_id");
    }

    // Edit Post
    public function edit_post($request) {
        $post_id = $request->get_param('id');
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $status = $request->get_param('status');

        if (! get_post($post_id)) {
            return $this->respond('Post ID not found', false);
        }

        $post_data = [
            'ID'            => $post_id,
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => $status,
        ];

        $updated_id = wp_update_post($post_data);

        // Display error if occur
        if (is_wp_error($updated_id)) {
            return $this->respond('Failed to update post', false);
        }

        return $this->respond("Post updated successfully. Post ID: $post_id");
    }

    // Delete post
    public function delete_post($request) {
        $post_id = $request->get_param('id');

        if (! get_post($post_id)) {
            return $this->respond('Post ID not found', false);
        }

        $deleted = wp_delete_post($post_id, true);

        // Display error if occur
        if (!$deleted) {
            return $this->respond('Failed to delete post', false);
        }

        // success
        return $this->respond("Post deleted successfully with ID: $post_id");
    }

    // Authenticate request
    public function authenticate_request($request) {
        $api_key = $request->get_header('api_key');
        $valid_api_key = EXTERNAL_POST_MANAGER_API_KEY;

        return $api_key && $api_key === $valid_api_key;
    }
}

new ExternalPostManager();
