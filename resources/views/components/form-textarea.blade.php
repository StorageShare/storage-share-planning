@props([
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'disabled' => false,
    'rows' => 4,
    'class' => ''
])

@php
$hasError = $errors->has($name);
$baseClasses = 'py-3 px-4 block w-full text-sm border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 disabled:pointer-events-none';
$errorClasses = $hasError ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '';
$textareaClasses = $baseClasses . ' ' . $errorClasses . ' ' . $class;
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-300">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        class="{{ $textareaClasses }}  dark:bg-gray-800  dark:border-gray-600 dark:text-gray-300"
        {{ $attributes }}
    >{{ old($name, $value) }}</textarea>

    @if($hasError)
        <p class="text-xs text-red-600 mt-1">{{ $errors->first($name) }}</p>
    @endif
</div>
