import './bootstrap';

/* ====================================================================
   Folder upload handler for network drive
   ==================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    const folderUploadForm = document.querySelector('[data-folder-upload-form]');

    if (folderUploadForm) {
        const input = folderUploadForm.querySelector('input[type="file"]');

        input.addEventListener('change', async () => {
            if (!input.files.length) return;

            const fileCount = input.files.length;
            let modalBackdrop = null;

            // Show uploading modal
            const showUploadModal = () => {
                modalBackdrop = document.createElement('div');
                modalBackdrop.className = 'modal-backdrop';
                modalBackdrop.innerHTML = `
                    <div class="modal" role="dialog" aria-modal="true" aria-label="Uploading">
                        <div class="modal-head">
                            <h3>Uploading folder...</h3>
                        </div>
                        <div class="modal-body">
                            <p>Uploading ${fileCount} ${fileCount === 1 ? 'file' : 'files'} to the network drive.</p>
                            <p class="small muted">Please wait...</p>
                        </div>
                    </div>`;
                document.body.appendChild(modalBackdrop);
            };

            const closeModal = () => {
                if (modalBackdrop) modalBackdrop.remove();
            };

            const showErrorModal = (message) => {
                if (window.SM && window.SM.modal) {
                    window.SM.modal({
                        title: 'Upload Failed',
                        body: `<p>${message}</p>`,
                        confirmLabel: 'OK',
                        danger: true,
                        onConfirm: () => {}
                    });
                } else {
                    alert(message);
                }
            };

            showUploadModal();

            // Extract relative paths from files
            const formData = new FormData();

            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                formData.append('_token', csrfToken);
            }

            // Add files and their relative paths
            Array.from(input.files).forEach((file, index) => {
                formData.append(`folder_files[${index}]`, file);
                // webkitRelativePath contains the full path including folder name
                formData.append(`folder_paths[${index}]`, file.webkitRelativePath || file.name);
            });

            try {
                const response = await fetch(folderUploadForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                closeModal();

                if (response.ok) {
                    // Redirect on success
                    window.location.reload();
                } else {
                    const data = await response.json();
                    const errorMessage = data.message || Object.values(data.errors || {})[0]?.[0] || 'Upload failed. Please try again.';
                    showErrorModal(errorMessage);
                }
            } catch (error) {
                console.error('Upload error:', error);
                closeModal();
                showErrorModal('Network error. Please check your connection and try again.');
            } finally {
                // Reset the input so the same folder can be selected again
                input.value = '';
            }
        });
    }
});
