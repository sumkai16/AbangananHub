{{-- Formatting toolbar for <x-lease-editor>. Needs $name. --}}
<div x-show="tab === 'edit'" class="flex flex-shrink-0 items-center gap-1 overflow-x-auto border-b border-[#E2E4EC] bg-white px-3 py-2 sm:justify-center">
    @foreach([
        ['toggleBold', 'bold', 'Bold', 'B', 'font-bold'],
        ['toggleItalic', 'italic', 'Italic', 'I', 'italic'],
        ['toggleUnderline', 'underline', 'Underline', 'U', 'underline'],
    ] as [$cmd, $mark, $title, $glyph, $style])
        <button type="button" @click="run('{{ $cmd }}')" title="{{ $title }}" aria-label="{{ $title }}" :aria-pressed="active('{{ $mark }}')"
            :class="active('{{ $mark }}') ? 'bg-[#060D26] text-white' : 'bg-white text-[#060D26]'"
            class="h-9 w-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] text-[13px] {{ $style }}">{{ $glyph }}</button>
    @endforeach
    <button type="button" @click="run('toggleHeading', { level: 3 })" title="Clause heading" aria-label="Clause heading" :aria-pressed="active('heading')"
        :class="active('heading') ? 'bg-[#060D26] text-white' : 'bg-white text-[#060D26]'"
        class="h-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] px-3 text-[12px] font-bold">Heading</button>
    <button type="button" @click="run('toggleBulletList')" title="Bulleted list" aria-label="Bulleted list" :aria-pressed="active('bulletList')"
        :class="active('bulletList') ? 'bg-[#060D26] text-white' : 'bg-white text-[#060D26]'"
        class="h-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] px-3 text-[12px] font-bold">&bull; List</button>
    <button type="button" @click="run('toggleOrderedList')" title="Numbered list" aria-label="Numbered list" :aria-pressed="active('orderedList')"
        :class="active('orderedList') ? 'bg-[#060D26] text-white' : 'bg-white text-[#060D26]'"
        class="h-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] px-3 text-[12px] font-bold">1. List</button>
    <button type="button" @click="run('undo')" title="Undo" aria-label="Undo" class="h-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12px] font-bold text-[#060D26]">Undo</button>
    <button type="button" @click="run('redo')" title="Redo" aria-label="Redo" class="h-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12px] font-bold text-[#060D26]">Redo</button>
    <label class="sr-only" for="lease-field-picker-{{ $name }}">Insert a field</label>
    <select id="lease-field-picker-{{ $name }}" @change="insertField($event.target.value); $event.target.value = ''"
        class="h-9 flex-shrink-0 rounded-lg border border-[#E2E4EC] bg-white px-2 text-[12px] font-bold text-[#060D26]">
        <option value="">Insert field</option>
        <template x-for="[key, label] in Object.entries(fields)" :key="key"><option :value="key" x-text="label"></option></template>
    </select>
</div>

