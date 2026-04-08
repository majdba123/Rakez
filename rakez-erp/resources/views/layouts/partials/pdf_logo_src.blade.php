@php
    $rakezLogoSrcForMpdf = null;
    foreach (['images/rakez-logo.png', 'images/rakez-logo.jpg', 'images/logo.png', 'images/logo.jpg'] as $rel) {
        $full = public_path($rel);
        if (! is_string($full) || ! is_readable($full)) {
            continue;
        }
        $resolved = realpath($full);
        if ($resolved === false) {
            continue;
        }
        $normalized = str_replace('\\', '/', $resolved);
        if ($normalized === '') {
            continue;
        }
        $rakezLogoSrcForMpdf = strncmp($normalized, '/', 1) === 0
            ? 'file://' . $normalized
            : 'file:///' . $normalized;
        break;
    }
@endphp
