<?php

/**
 * Plugin Name: Quick EXIF
 * Plugin URI: https://github.com/FeXd/wordpress-plugin-quick-exif
 * Description: A simple WordPress plugin to extract EXIF data from the featured image and save it as custom fields.
 * Version: 0.0.1
 * Author: Arlin Schaffel
 * Author URI: https://github.com/FeXd
 * License: MIT
 * License URI: https://github.com/FeXd/wordpress-plugin-quick-exif/blob/main/LICENSE
 * Text Domain: quick-exif
 */

// Prevent direct file access
if (!defined('ABSPATH')) exit;

// Add a custom meta box to the post editor screen
add_action('add_meta_boxes', function () {
    add_meta_box(
        'quick_exif_meta_box',         // ID of the meta box
        'Quick EXIF',                  // Title of the meta box
        'quick_exif_meta_box_render', // Callback function to display the box
        'post',                        // Post type to show this on
        'normal',                      // Context: normal, side, or advanced
        'high'                         // Priority of the box
    );
});

// Load JavaScript only on the post editing screens
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script(
            'quick-exif-script',
            plugin_dir_url(__FILE__) . 'quick-exif.js',
            ['jquery'],
            null,
            true
        );

        // Make PHP data available to JS
        wp_localize_script('quick-exif-script', 'QuickExifData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('quick_exif_nonce'),
            'postId'  => get_the_ID(), // Current post ID
        ]);
    }
});

// Display the meta box content (button + instructions)
function quick_exif_meta_box_render($post)
{
    echo '<p><strong>WARNING:</strong> This will <strong>reload</strong> the page.</p>';
    echo '<p>Extract <strong>EXIF data</strong> from the <strong>featured image</strong> and store it as custom fields. The post must have a <strong>featured image</strong> and be <strong>saved</strong>.</p>';
    echo '<p><button id="quick-exif-test-button" class="button button-primary">Extract EXIF from Featured Image</button></p>';
    echo '<p id="quick-exif-status"></p>';
}

// Handle the AJAX request to extract and save EXIF data
add_action('wp_ajax_quick_exif_extract', function () {
    check_ajax_referer('quick_exif_nonce', 'nonce'); // Security check

    $post_id = intval($_POST['postId'] ?? 0);
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Invalid post. Be sure to save post.');
    }

    $thumb_id = get_post_thumbnail_id($post_id);
    $image_path = get_attached_file($thumb_id);

    if (!file_exists($image_path)) {
        wp_send_json_error('Featured Image not found. Be sure to save post.');
    }

    // Try to read EXIF data from the image
    $exif = @exif_read_data($image_path, 'IFD0');
    if (!$exif) {
        wp_send_json_error('No EXIF data found.');
    }

    // Build camera and lens description
    $camera = trim(($exif['Make'] ?? '') . ' ' . ($exif['Model'] ?? ''));
    $lens = $exif['UndefinedTag:0xA434'] ?? '';
    if ($camera && $lens) {
        $camera = "$camera, $lens";
    } elseif ($lens) {
        $camera = $lens;
    }

    // Build exposure string (f-stop, shutter, ISO, focal length)
    $exposure_time = $exif['ExposureTime'] ?? '';
    $shutter = $exposure_time ? $exposure_time . 'sec' : '';
    $f_number = isset($exif['FNumber']) ? 'f/' . round(eval('return ' . $exif['FNumber'] . ';'), 1) : '';
    $iso = isset($exif['ISOSpeedRatings']) ? 'ISO ' . $exif['ISOSpeedRatings'] : '';

    $focal_length = '';
    if (isset($exif['FocalLength'])) {
        $parts = explode('/', $exif['FocalLength']);
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[1] != 0) {
            $focal_length = round($parts[0] / $parts[1], 1) . 'mm';
        }
    }

    $exposure_parts = array_filter([$f_number, $shutter, $iso, $focal_length]);
    $exposure = implode(', ', $exposure_parts);

    // Extract GPS coordinates (very simplified)
    $location = '';
    if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLongitude'])) {
        $location = implode(',', [
            implode(':', $exif['GPSLatitude']),
            implode(':', $exif['GPSLongitude']),
        ]);
    }

    // Format original capture date
    $date = $exif['DateTimeOriginal'] ?? '';
    if ($date) {
        $date = date('Y-m-d', strtotime($date));
    }

    // Save custom fields
    update_post_meta($post_id, 'camera', trim($camera));
    update_post_meta($post_id, 'exposure', $exposure);
    update_post_meta($post_id, 'location', $location ?: 'N/A');
    update_post_meta($post_id, 'date', $date ?: 'N/A');

    wp_send_json_success('EXIF data saved.');
});

// Shortcode to display EXIF info on front-end
add_shortcode('quick-exif', function () {
    if (!is_singular('post')) return '';

    $post_id = get_the_ID();

    // Load values from custom fields, fallback to "N/A" if empty
    $camera = get_post_meta($post_id, 'camera', true) ?: 'N/A';
    $exposure = get_post_meta($post_id, 'exposure', true) ?: 'N/A';
    $location = get_post_meta($post_id, 'location', true) ?: 'N/A';
    $date_raw = get_post_meta($post_id, 'date', true);
    if ($date_raw) {
        $timestamp = strtotime($date_raw);
        $date = $timestamp ? date('F j, Y', $timestamp) : 'N/A';
    } else {
        $date = 'N/A';
    }

    // Display the EXIF info
    return "<h3>Photo Information</h3>
    <p>
        <strong>Camera:</strong> {$camera}<br>
        <strong>Exposure:</strong> {$exposure}<br>
        <strong>Location:</strong> {$location}<br>
        <strong>Date:</strong> {$date}
    </p>";
});
