<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

if (! class_exists('Symfony\Component\DomCrawler\Crawler')) {
    require_once __DIR__.'/Support/Crawler.php';
}

abstract class TestCase extends BaseTestCase
{
    //
}
