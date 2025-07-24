<?php
/**
 * Plugin Name: Quick Exif
 * Description: Adds a button to the post editor to extract EXIF data from the featured image.
 * Version: 0.4
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

// Render meta box with button + JS
function quick_exif_meta_box_render($post) {
    $is_saved = ($post->ID && $post->post_status !== 'auto-draft');
    $has_thumb = has_post_thumbnail($post->ID);

    echo '<p><button id="quick-exif-test-button" class="button button-primary">Extract EXIF</button></p>';

    if ($is_saved && $has_thumb) {
        $thumb_id = get_post_thumbnail_id($post->ID);
        $image_path = get_attached_file($thumb_id);

        if (file_exists($image_path)) {
            $exif = @exif_read_data($image_path, 'IFD0');

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

            // Save to custom fields
            update_post_meta($post->ID, 'camera', trim($camera));
            update_post_meta($post->ID, 'exposure', $exposure);
            update_post_meta($post->ID, 'location', $location ?: 'N/A');
            update_post_meta($post->ID, 'date', $date ?: 'N/A');

            echo <<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const btn = document.getElementById('quick-exif-test-button');
                    if (btn) {
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            alert('✅ EXIF data imported into custom fields. Reloading...');
                            location.reload();
                        });
                    }
                });
            </script>
            HTML;
        } else {
            echo '<p style="color:#a00">Image file not found.</p>';
        }
    } else {
        echo '<p style="color:#a00">Save the post and set a featured image to extract EXIF data.</p>';
    }
}
