@props(['user', 'size' => null])
<span {{ $attributes->class(['pm-avatar', 'pm-avatar--lg' => $size === 'lg']) }} aria-hidden="true">{{ $user->initials() }}</span>
