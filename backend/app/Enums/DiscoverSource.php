<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoverSource: int
{
    case Unknown = 0;
    case Search = 1;
    case Trending = 2;
    case PublisherApps = 3;
    case Register = 4;
    case Import = 5;
    case Similar = 6;
    case Category = 7;
    case DirectVisit = 8;

    public function configKey(): string
    {
        return match ($this) {
            self::Unknown => 'on_unknown',
            self::Search => 'on_search',
            self::Trending => 'on_trending',
            self::PublisherApps => 'on_publisher_apps',
            self::Register => 'on_register',
            self::Import => 'on_import',
            self::Similar => 'on_similar',
            self::Category => 'on_category',
            self::DirectVisit => 'on_direct_visit',
        };
    }

    public function isEnabled(string $platform): bool
    {
        return (bool) config("appstorecat.discover.{$platform}.{$this->configKey()}");
    }
}
