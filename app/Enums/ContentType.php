<?php

namespace App\Enums;

/**
 * Integer enum for contents.content_type (Step 3).
 * Values are the legacy contract — do NOT renumber once shipped.
 */
enum ContentType: int
{
    case Image = 1;
    case Video = 2;
    case Text = 3;
    case Flash = 4;
    case Url = 5;

    public function labelFa(): string
    {
        return match ($this) {
            self::Image => 'عکس',
            self::Video => 'فیلم',
            self::Text => 'متن',
            self::Flash => 'فلش',
            self::Url => 'آدرس',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Video => 'Video',
            self::Text => 'Text',
            self::Flash => 'Flash',
            self::Url => 'URL',
        };
    }
}
