document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('quick-exif-test-button');
    const status = document.getElementById('quick-exif-status');

    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            status.textContent = '📷 Extracting EXIF...';

            fetch(QuickExifData.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'quick_exif_extract',
                    nonce: QuickExifData.nonce,
                    postId: QuickExifData.postId,
                }),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        status.textContent = '✅ EXIF data saved. Reloading...';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        status.textContent = '❌ Error: ' + data.data;
                    }
                })
                .catch(err => {
                    status.textContent = '❌ Request failed.';
                    console.error(err);
                });
        });
    }
});
