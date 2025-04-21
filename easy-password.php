<?php
/**
 * Plugin Name: Easy Password
 * Description: A plugin that creates a shortcode for generating and managing user passwords
 * Version: 1.0
 * Author: WordPress User
 * Text Domain: easy-password
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Easy_Password
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register shortcode
        add_shortcode('easy_password', array($this, 'render_password_form'));

        // Register AJAX handler
        add_action('wp_ajax_easy_password_process', array($this, 'process_password_request'));
        add_action('wp_ajax_nopriv_easy_password_process', array($this, 'process_password_request'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'easy-password-js',
            plugin_dir_url(__FILE__) . 'easy-password.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script(
            'easy-password-js',
            'easy_password_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('easy_password_nonce')
            )
        );

        // Register and enqueue our custom styles
        wp_enqueue_style(
            'easy-password-css',
            plugin_dir_url(__FILE__) . 'easy-password.css',
            array(),
            '1.0'
        );

        // Add Dashicons for the copy button
        wp_enqueue_style('dashicons');
    }

    /**
     * Render the password form
     */
    public function render_password_form()
    {
        ob_start();
        ?>
        <div class="easy-password-container">
            <form id="easy-password-form" method="post">
                <div class="form-group">
                    <label for="user_email">Your Email</label>
                    <input type="email" name="user_email" id="user_email" required placeholder="Enter your email address">
                </div>
                <div class="form-group">
                    <button type="submit" id="submit-password">
                        <span class="button-text">Generate Password</span>
                        <span class="spinner"></span>
                    </button>
                </div>
            </form>
            <div id="easy-password-result" class="password-result" style="display: none;">
                <div class="password-box">
                    <span id="password-text"></span>
                    <button id="copy-password" class="copy-btn" title="Copy to clipboard">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <div class="password-message"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Process the password request
     */
    public function process_password_request()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'easy_password_nonce')) {
            wp_send_json_error('Security check failed');
            die();
        }

        // Get email
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error('Email is required');
            die();
        }

        // Check if user exists
        $user = get_user_by('email', $email);

        // Generate a random password
        $random_password = $this->generate_random_password();

        // If user exists, update password
        if ($user) {
            wp_set_password($random_password, $user->ID);
            wp_send_json_success(array(
                'message' => 'Password: ' . $random_password
            ));
        } else {
            // If user doesn't exist, create a new user
            $username = $this->generate_username_from_email($email);

            $user_id = wp_create_user($username, $random_password, $email);

            if (is_wp_error($user_id)) {
                wp_send_json_error('Failed to create user: ' . $user_id->get_error_message());
                die();
            }

            wp_send_json_success(array(
                'message' => 'Password: ' . $random_password
            ));
        }

        die();
    }

    /**
     * Generate a random password
     */
    private function generate_random_password($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= substr($chars, wp_rand(0, strlen($chars) - 1), 1);
        }

        return $password;
    }

    /**
     * Generate username from email
     */
    private function generate_username_from_email($email)
    {
        $username = substr($email, 0, strpos($email, '@'));

        // Check if username exists
        if (username_exists($username)) {
            $username .= wp_rand(100, 999);
        }

        return $username;
    }
}

// Initialize the plugin
new Easy_Password();

// Create the JS file if it doesn't exist
function easy_password_create_js_file()
{
    $js_file = plugin_dir_path(__FILE__) . 'easy-password.js';

    if (!file_exists($js_file)) {
        $js_content = '
jQuery(document).ready(function($) {
    $("#easy-password-form").on("submit", function(e) {
        e.preventDefault();
        
        var email = $("#user_email").val();
        
        $.ajax({
            url: easy_password_ajax.ajax_url,
            type: "POST",
            data: {
                action: "easy_password_process",
                email: email,
                nonce: easy_password_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $("#easy-password-result").html("<p>" + response.data.message + "</p>").show();
                } else {
                    $("#easy-password-result").html("<p>Error: " + response.data + "</p>").show();
                }
            },
            error: function() {
                $("#easy-password-result").html("<p>Something went wrong. Please try again.</p>").show();
            }
        });
    });
});';

        file_put_contents($js_file, $js_content);
    }
}

register_activation_hook(__FILE__, 'easy_password_create_js_file');