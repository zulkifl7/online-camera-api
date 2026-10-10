<?php

namespace App\Enums;

enum DownloadMode: string
{
    case Single = 'single';
    case Archive = 'archive';
}
