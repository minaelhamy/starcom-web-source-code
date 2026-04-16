<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ThemeSetting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = "settings";

    public function getLogoAttribute(): string
    {
        $url = $this->getFirstMediaUrl('theme-logo');
        if (!empty($url)) {
            return str_starts_with($url, 'http') ? $url : asset($url);
        }
        return asset('images/required/theme-logo.png');
    }

    public function getFaviconLogoAttribute(): string
    {
        $url = $this->getFirstMediaUrl('theme-favicon-logo');
        if (!empty($url)) {
            return str_starts_with($url, 'http') ? $url : asset($url);
        }
        return asset('images/required/theme-favicon-logo.png');
    }

    public function getFooterLogoAttribute(): string
    {
        $url = $this->getFirstMediaUrl('theme-footer-logo');
        if (!empty($url)) {
            return str_starts_with($url, 'http') ? $url : asset($url);
        }
        return asset('images/required/theme-footer-logo.png');
    }
}
