@props(['label' => null])

<div>
    @if ($label)
        <label class="pk-label">{{ $label }}</label>
    @endif

    {{ $slot }}
</div>
