<?php

namespace Tests\Unit;

use App\Domains\Analytics\Services\HumanDurationFormatter;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class HumanDurationFormatterTest extends TestCase
{
    #[TestWith([0, '0 minutes'])]
    #[TestWith([1, '1 minute'])]
    #[TestWith([8, '8 minutes'])]
    #[TestWith([59, '59 minutes'])]
    #[TestWith([60, '1 hour'])]
    #[TestWith([61, '1 hour, 1 minute'])]
    #[TestWith([1439, '23 hours, 59 minutes'])]
    #[TestWith([1440, '1 day'])]
    #[TestWith([1441, '1 day, 1 minute'])]
    #[TestWith([4520, '3 days, 3 hours, 20 minutes'])]
    public function test_formats_minutes_from_the_largest_nonzero_unit(int $minutes, string $expected): void
    {
        $this->assertSame($expected, HumanDurationFormatter::minutes($minutes));
    }
}
