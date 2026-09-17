<?php

namespace App\Enums;

use App\Support\CollegeCost;

/**
 * The household income bands US colleges publish average net price against.
 *
 * These five brackets are not ours — they are the brackets in the federal
 * IPEDS net-price tables, which is the whole point. A band the government
 * already reports against is a band we can look a real number up in; a band we
 * invented would force us to interpolate, and an interpolated cost figure
 * shown to a seventeen-year-old deciding whether college is possible is the
 * kind of guess that ends a conversation.
 *
 * A student who does not know their household income is not blocked. The cost
 * picture degrades to the published sticker price with that stated plainly —
 * see {@see CollegeCost}.
 */
enum IncomeBand: string
{
    case UpTo30k = 'up_to_30k';
    case From30kTo48k = 'from_30k_to_48k';
    case From48kTo75k = 'from_48k_to_75k';
    case From75kTo110k = 'from_75k_to_110k';
    case Over110k = 'over_110k';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::UpTo30k => 'Up to $30,000',
            self::From30kTo48k => '$30,000 to $48,000',
            self::From48kTo75k => '$48,000 to $75,000',
            self::From75kTo110k => '$75,000 to $110,000',
            self::Over110k => 'Over $110,000',
        };
    }
}
