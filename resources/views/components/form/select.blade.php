@props(['label', 'wire', 'options' => []])

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    <select wire:model="{{ $wire }}" {{ $attributes->merge(['class' => 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500']) }}>
        <option value="">-- Select --</option>
        @foreach($options as $value => $display)
        <option value="{{ $value }}">{{ $display }}</option>
        @endforeach
    </select>
</div>
