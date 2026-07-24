@props([
    'user',
    'size' => 'medium',
])

<span
    {{ $attributes->class([
        'user-avatar',
        'user-avatar-'.$size,
        'has-symbol' => $user->avatarSymbol() !== null,
    ]) }}
    title="{{ $user->avatarLabel() ?: $user->name }}"
    aria-label="{{ $user->avatarLabel() ?: 'Avatar de '.$user->name }}"
>
    @if ($user->avatarSymbol())
        <span class="user-avatar-symbol" aria-hidden="true">
            {{ $user->avatarSymbol() }}
        </span>
    @else
        <span class="user-avatar-initials" aria-hidden="true">
            {{ $user->initials() }}
        </span>
    @endif
</span>
