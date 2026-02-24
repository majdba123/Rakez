<?php

namespace App\Domain\Ads\ValueObjects;

enum Platform: string
{
    case Meta = 'meta';
    case Snap = 'snap';
    case TikTok = 'tiktok';
}
