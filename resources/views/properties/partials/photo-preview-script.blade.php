<script>
    function previewSelectedPhotos(input) {
        const previewGrid = document.getElementById('live-preview-grid');
        const labelText = document.getElementById('upload-label');
        const defaultLabel = labelText.dataset.defaultLabel || 'Select photos';

        previewGrid.innerHTML = '';

        if (input.files && input.files.length > 0) {
            previewGrid.classList.remove('hidden');
            labelText.textContent = input.files.length === 1
                ? '1 photo selected'
                : `${input.files.length} photos selected`;
            labelText.classList.add('text-[#61B2F0]');

            Array.from(input.files).forEach(file => {
                if (!file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = function (e) {
                    const wrapper = document.createElement('div');
                    wrapper.className = "relative aspect-square rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shadow-sm";

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = "w-full h-full object-cover";

                    wrapper.appendChild(img);
                    previewGrid.appendChild(wrapper);
                };
                reader.readAsDataURL(file);
            });
        } else {
            previewGrid.classList.add('hidden');
            labelText.textContent = defaultLabel;
            labelText.classList.remove('text-[#61B2F0]');
        }
    }
</script>