<?php
/**
 * Plugin Name: Quick Exif
 * Description: Adds a button to the post editor to extract EXIF data from the featured image.
 * Version: 0.5
 * Author: Arlin Schaffel
 */

if (!defined('ABSPATH')) exit;

// Add meta box to post editor
add_action('add_meta_boxes', function () {
    add_meta_box(
        'quick_exif_meta_box',
        'Quick EXIF',
        'quick_exif_meta_box_render',
        'post',
        'normal',
        'default'
    );
});

// Enqueue script for admin
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('quick-exif-script', plugin_dir_url(__FILE__) . 'quick-exif.js', ['jquery'], null, true);
        wp_localize_script('quick-exif-script', 'QuickExifData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quick_exif_nonce'),
            'postId' => get_the_ID(),
        ]);
    }
});

// Render meta box with button
function quick_exif_meta_box_render($post) {
    echo '<p><button id="quick-exif-test-button" class="button button-primary">Extract EXIF</button></p>';
    echo '<p id="quick-exif-status"></p>';
}

// Handle AJAX request
add_action('wp_ajax_quick_exif_extract', function () {
    check_ajax_referer('quick_exif_nonce', 'nonce');

    $post_id = intval($_POST['postId'] ?? 0);
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Invalid post.');
    }

    $thumb_id = get_post_thumbnail_id($post_id);
    $image_path = get_attached_file($thumb_id);

    if (!file_exists($image_path)) {
        wp_send_json_error('Image not found.');
    }

    $exif = @exif_read_data($image_path, 'IFD0');
    if (!$exif) wp_send_json_error('No EXIF data found.');

    $camera = trim(($exif['Make'] ?? '') . ' ' . ($exif['Model'] ?? ''));
    $lens = $exif['UndefinedTag:0xA434'] ?? '';
    if ($camera && $lens) {
        $camera = "$camera, $lens";
    } elseif ($lens) {
        $camera = $lens;
    }

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

    $location = '';
    if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLongitude'])) {
        $location = implode(',', [
            implode(':', $exif['GPSLatitude']),
            implode(':', $exif['GPSLongitude']),
        ]);
    }

    $date = $exif['DateTimeOriginal'] ?? '';
    if ($date) {
        $date = date('Y-m-d', strtotime($date));
    }

    update_post_meta($post_id, 'camera', trim($camera));
    update_post_meta($post_id, 'exposure', $exposure);
    update_post_meta($post_id, 'location', $location ?: 'N/A');
    update_post_meta($post_id, 'date', $date ?: 'N/A');

    wp_send_json_success('EXIF data saved.');
});
