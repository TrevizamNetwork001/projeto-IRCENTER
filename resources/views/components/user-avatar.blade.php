@props([
    'user',
    'size' => 'medium',
])

@if ($user->hasPhotoAvatar())
    <span
        {{ $attributes->class([
            'user-avatar',
            'user-avatar-'.$size,
            'has-photo',
        ]) }}
    >
        <img
            class="user-avatar-photo"
            src="{{ $user->photoUrl() }}"
            alt="Foto de perfil de {{ $user->name }}"
        >
    </span>
@else
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
@endif
