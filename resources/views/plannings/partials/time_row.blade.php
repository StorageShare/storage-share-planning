<div class="ml-3 text-xs text-gray-700 dark:text-gray-300 mt-1" x-data="{ editing: false }">
  <div class="flex items-center justify-between">
    <div class="flex items-center min-w-0">
      <svg class="w-3 h-3 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <span class="truncate">{{ $label }}</span>
    </div>

    <div x-show="!editing" class="flex items-center space-x-2 justify-end">
      <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">{{ $display }}</span>
      @if($canEdit)
        <button type="button"
                x-ref="editBtn"
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                @click.prevent="editing = true"
                aria-label="Bewerken" title="Bewerken">
          <svg class="w-4 h-4 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h6a1 1 0 110 2H4v10h10v-6a1 1 0 112 0v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
          </svg>
        </button>
      @endif
    </div>
  </div>

  @if($canEdit)
    <form method="POST"
          action="{{ $action }}"
          class="mt-1 flex items-center space-x-2 justify-end"
          x-show="editing"
          x-cloak
          @keydown.escape.prevent.stop="editing = false; $nextTick(() => $refs.editBtn?.focus())">
      @csrf
      @method('PATCH')
      <input type="text" name="time" value="{{ $display }}"
             class="w-20 px-1 py-0.5 text-xs border rounded dark:bg-gray-800 font-mono"
             placeholder="HH:mm"
             x-ref="input"
             x-init="$watch('editing', v => { if (v) $nextTick(() => $refs.input?.focus()) })"
             aria-label="{{ $ariaLabel }}">
      <button type="submit" class="px-2 py-0.5 text-xs bg-blue-600 text-white rounded">Opslaan</button>
      <button type="button" class="px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300"
              @click.prevent="editing = false; $nextTick(() => $refs.editBtn?.focus())">Annuleren</button>
    </form>
  @endif
</div>
