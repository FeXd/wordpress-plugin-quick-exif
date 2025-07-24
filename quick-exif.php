<?php
/**
 * Plugin Name: Quick Exif
 * Description: Adds a button to the post editor to extract EXIF data from the featured image.
 * Version: 0.3
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

    echo '<p><button id="quick-exif-test-button" class="button button-primary">Show EXIF</button></p>';

    if ($is_saved && $has_thumb) {
        $thumb_id = get_post_thumbnail_id($post->ID);
        $image_path = get_attached_file($thumb_id);

        if (file_exists($image_path)) {
            $exif = @exif_read_data($image_path, 'IFD0');

            $camera = trim(($exif['Make'] ?? '') . ' ' . ($exif['Model'] ?? ''));
            $lens = $exif['UndefinedTag:0xA434'] ?? '';
            $exposure = trim(($exif['ExposureTime'] ?? '') . ' ' . ($exif['FNumber'] ?? '') . ' ' . ($exif['ISOSpeedRatings'] ?? 'ISO N/A'));

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

            $exif_data = [
                'Camera' => trim("{$camera} {$lens}"),
                'Exposure' => $exposure,
                'Location' => $location ?: 'N/A',
                'Date' => $date ?: 'N/A'
            ];

            $json = json_encode($exif_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

            echo <<<HTML
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const btn = document.getElementById('quick-exif-test-button');
                    if (btn) {
                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            const exif = JSON.parse('$json');
                            let message = '';
                            for (let key in exif) {
                                message += key + ': ' + exif[key] + '';
                            }
                            alert(message);
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
