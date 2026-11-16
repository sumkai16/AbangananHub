{{--
    A file input that shows what was picked before the form is sent: name and size, a thumbnail for an image or an
    embedded view for a PDF, and a Remove button. Checks the type (PDF, JPG, PNG) and the size on the device, the
    server still validates both. The input keeps its real name, so it submits like a plain file field.

    Props: name, id, accept, maxMb (default 10), required.
--}}
@props(['name' => 'file', 'id' => null, 'accept' => '.pdf,.jpg,.jpeg,.png', 'maxMb' => 10, 'required' => false])

<div class="grid gap-2.5"
    x-data="{
        fileName: '', fileSize: '', kind: '', url: null, error: '',
        max: {{ (int) $maxMb }} * 1024 * 1024,
        pick(event) {
            this.release();
            const file = event.target.files[0];
            if (! file) return;
            if (! /\.(pdf|jpe?g|png)$/i.test(file.name)) { this.error = 'Use a PDF, JPG or PNG file.'; event.target.value = ''; return; }
            if (file.size > this.max) { this.error = 'That file is over {{ (int) $maxMb }} MB. Choose a smaller one.'; event.target.value = ''; return; }
            this.fileName = file.name;
            this.fileSize = file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB';
            this.kind = /\.pdf$/i.test(file.name) ? 'pdf' : 'image';
            this.url = URL.createObjectURL(file);
        },
        release() { if (this.url) URL.revokeObjectURL(this.url); this.url = null; this.fileName = ''; this.fileSize = ''; this.kind = ''; this.error = ''; },
        remove() { this.release(); this.$refs.input.value = ''; },
    }"
    x-on:beforeunload.window="release()">

    <input x-ref="input" type="file" name="{{ $name }}" @if($id) id="{{ $id }}" @endif accept="{{ $accept }}" @if($required) required @endif
        @change="pick($event)"
        class="block w-full text-[13.5px] text-[#060D26] file:mr-3 file:h-10 file:px-4 file:rounded-full file:border-0 file:bg-[#ECEEF6] file:text-[#060D26] file:font-semibold file:cursor-pointer hover:file:bg-[#E2E4EC] file:transition-colors file:duration-200">
    <p class="text-[12px] text-[#5B6A8E]" x-show="!url && !error">PDF, JPG or PNG, up to {{ (int) $maxMb }} MB</p>
    <p class="text-[12px] font-semibold text-[#DC2626]" x-show="error" x-cloak x-text="error" role="alert"></p>

    <div x-show="url" x-cloak class="rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] overflow-hidden">
        <div class="flex items-center gap-3 px-3.5 py-2.5">
            <span class="w-9 h-9 rounded-lg bg-white border border-[#E2E4EC] flex items-center justify-center shrink-0 text-[#5B6A8E]" aria-hidden="true">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63A3.38 3.38 0 0 0 16.13 8.25h-1.5a1.13 1.13 0 0 1-1.13-1.13v-1.5A3.38 3.38 0 0 0 10.13 2.25H8.25m2.25 0H5.63c-.62 0-1.13.5-1.13 1.13v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12V11.25a9 9 0 0 0-9-9z" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-semibold text-[#060D26] truncate" x-text="fileName"></p>
                <p class="text-[12px] text-[#5B6A8E] tabular-nums" x-text="fileSize"></p>
            </div>
            <a :href="url" target="_blank" rel="noopener" class="text-[12.5px] font-semibold text-[#B35A3D] hover:underline underline-offset-2 whitespace-nowrap">Open</a>
            <button type="button" @click="remove()" class="text-[12.5px] font-semibold text-[#B91C1C] hover:underline underline-offset-2 cursor-pointer whitespace-nowrap">Remove</button>
        </div>
        <div class="border-t border-[#E2E4EC] bg-white">
            <img x-show="kind === 'image'" :src="kind === 'image' ? url : ''" alt="Preview of the chosen file" class="block w-full max-h-72 object-contain">
            <template x-if="kind === 'pdf'">
                <iframe :src="url + '#toolbar=0&navpanes=0'" title="Preview of the chosen file" class="block w-full h-72 border-0"></iframe>
            </template>
        </div>
    </div>
</div>
