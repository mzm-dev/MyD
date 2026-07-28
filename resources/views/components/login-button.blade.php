@props([
    'label' => 'Log Masuk dengan MyDigital ID',
    'icon' => 'bi-shield-lock',
])

<a
    href="{{ route('mydigitalid.login') }}"
    {{ $attributes->merge(['class' => 'btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2']) }}
>
    @if ($icon)
        <i class="bi {{ $icon }}"></i>
    @endif
    {{ $label }}
</a>
