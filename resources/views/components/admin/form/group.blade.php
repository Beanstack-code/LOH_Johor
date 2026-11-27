@props(['label' => null])

<div>
    @if ($label)
        <label class="pk-label">{{ __($label) }}</label>
    @endif

    {{ $slot }}
</div>
