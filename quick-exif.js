// Run this code when the page has fully loaded
document.addEventListener('DOMContentLoaded', function () {
    // Get references to the button and status message area
    const btn = document.getElementById('quick-exif-test-button');
    const status = document.getElementById('quick-exif-status');

    // Only continue if the button exists on the page
    if (btn) {
        // Add click event listener to the button
        btn.addEventListener('click', function (e) {
            e.preventDefault(); // Prevent any default button behavior

            // Show loading message
            status.textContent = '📷 Extracting EXIF...';

            // Send AJAX request to WordPress backend using Fetch API
            fetch(QuickExifData.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'quick_exif_extract',          // WordPress action name
                    nonce: QuickExifData.nonce,            // Security nonce
                    postId: QuickExifData.postId,          // Current post ID
                }),
            })
                // Convert response to JSON
                .then(res => res.json())

                // Handle response data
                .then(data => {
                    if (data.success) {
                        // Show success message and reload page after 1 second
                        status.textContent = '✅ EXIF data saved. Reloading...';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        // Show error message from server
                        status.textContent = '❌ Error: ' + data.data;
                    }
                })

                // Handle network or other unexpected errors
                .catch(err => {
                    status.textContent = '❌ Request failed.';
                    console.error(err); // Log details to console for debugging
                });
        });
    }
});
