const imageExtension = /\.(jpe?g|png|webp|heic|heif)$/i;
const imageType = /^image\/(jpeg|png|webp|heic|heif)$/i;

// Resize on the phone before PHP receives the request, including high-resolution camera photos.
export async function prepareReceiptPhoto(file, { maxSide = 1600, targetBytes = 1024 * 1024 } = {}) {
    const image = new Image();
    const source = URL.createObjectURL(file);
    const canvas = document.createElement('canvas');
    try {
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = () => reject(new Error('image-decode'));
            image.src = source;
        });
        if (!image.naturalWidth || !image.naturalHeight) throw new Error('image-decode');

        if (file.size <= targetBytes && Math.max(image.naturalWidth, image.naturalHeight) <= maxSide &&
            /^(image\/(jpeg|png|webp))$/i.test(file.type)) return file;

        const context = canvas.getContext('2d');
        if (!context) throw new Error('image-canvas');
        let side = maxSide;
        let blob;
        do {
            const scale = Math.min(1, side / image.naturalWidth, side / image.naturalHeight);
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            for (const quality of [.84, .74, .64]) {
                blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                if (!blob || blob.type !== 'image/jpeg') throw new Error('image-encode');
                if (blob.size <= targetBytes) break;
            }
            side = Math.floor(side * .8);
        } while (blob.size > targetBytes && side >= 800);

        if (blob.size > targetBytes) throw new Error('image-size');
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
            type: 'image/jpeg', lastModified: file.lastModified,
        });
    } finally {
        URL.revokeObjectURL(source);
        image.removeAttribute('src');
        canvas.width = canvas.height = 0;
    }
}

export function receiptPicker({ max = 6, existing = 0, maxBytes = 10 * 1024 * 1024, maxSide = 1600 } = {}) {
    return {
        files: [], previews: [], existing, processing: false,
        get count() { return this.files.length + this.existing; },
        async add(event) {
            const picked = Array.from(event.target.files || []);
            if (this.processing || !picked.length) return;
            this.processing = true;
            const form = this.$refs.input.form;
            form.dataset.receiptProcessing = '1';
            this.$dispatch('receipts-processing', true);
            try {
                for (const original of picked) {
                    if (this.count >= max) {
                        window.toast(window.t('Maksimal :max foto per nota.', { max }), 'warning');
                        break;
                    }
                    const photo = imageType.test(original.type) || (!original.type && imageExtension.test(original.name));
                    const pdf = original.type === 'application/pdf' || (!original.type && /\.pdf$/i.test(original.name));
                    if (!photo && !pdf) {
                        window.toast(window.t('Choose a JPG, PNG, WebP, HEIC, or PDF file.'), 'error');
                        continue;
                    }
                    let file = original;
                    if (photo) {
                        try {
                            file = await prepareReceiptPhoto(original, { maxSide, targetBytes: Math.min(1024 * 1024, maxBytes) });
                        } catch {
                            window.toast(window.t('Could not prepare :name. Take another photo or choose a JPG/PNG image.', { name: original.name }), 'error');
                            continue;
                        }
                    }
                    if (file.size > maxBytes) {
                        window.toast(window.t(':name exceeds the :max MB limit.', { name: file.name, max: maxBytes / (1024 * 1024) }), 'error');
                        continue;
                    }
                    this.files.push(file);
                    this.previews.push(photo ? URL.createObjectURL(file) : null);
                }
                this.sync();
            } finally {
                this.processing = false;
                delete form.dataset.receiptProcessing;
                this.$dispatch('receipts-processing', false);
            }
        },
        remove(index) {
            const preview = this.previews.splice(index, 1)[0];
            if (preview) URL.revokeObjectURL(preview);
            this.files.splice(index, 1);
            this.sync();
        },
        sync() {
            const transfer = new DataTransfer();
            this.files.forEach((file) => transfer.items.add(file));
            this.$refs.input.files = transfer.files;
        },
        destroy() {
            this.previews.forEach((preview) => { if (preview) URL.revokeObjectURL(preview); });
        },
    };
}
