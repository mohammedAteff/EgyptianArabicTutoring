<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class VideoProviderUnavailable extends HttpException
{
    public function __construct()
    {
        parent::__construct(503, 'Protected video is temporarily unavailable. Please try again later.');
    }
}
