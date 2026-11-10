<?php

namespace App\Domains\Students\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentIntendedDestination
{
    public const SESSION_KEY = 'student.intended';

    private const ROUTES = [
        'student.home', 'student.dashboard', 'student.learning.index', 'student.learning.notes.index',
        'student.learning.assignments.show', 'student.learning.courses.show', 'student.learning.lessons.show',
        'student.evidence.show', 'student.teaching.index', 'student.scheduling.index', 'student.bins.index',
        'student.bins.edit', 'student.bookmarks.index', 'student.profile', 'student.security.show',
        'student.video.devices', 'student.notifications.index', 'student.statements.index', 'student.statements.show',
        'student.receipts.show', 'student.bookings.create', 'student.bookings.show', 'student.bookings.reschedule',
        'student.lessons.show', 'student.forms.show', 'student.progress-reports.show',
        'student.feedback.index', 'student.feedback.create', 'student.feedback.show',
        'student.insights.index',
    ];

    public function __construct(private Router $router) {}

    public function capture(Request $request): void
    {
        if ($request->isMethod('GET') && ! $request->expectsJson() && in_array($request->route()?->getName(), self::ROUTES, true)) {
            $url = $this->safeRelativeUrl($request->getRequestUri());
            if ($url !== null) {
                $request->session()->put(self::SESSION_KEY, $url);
            }
        }
    }

    public function consume(Request $request): string
    {
        return $this->safeRelativeUrl($request->session()->pull(self::SESSION_KEY)) ?? route('student.home', [], false);
    }

    public function safeRelativeUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 2048 || ! str_starts_with($url, '/student')
            || preg_match('/[\\\\\x00-\x1f\x7f]/', rawurldecode($url)) || str_starts_with($url, '//')) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (str_contains(rawurldecode($path), '..') || str_contains(rawurldecode($path), '//')) {
            return null;
        }
        try {
            $route = $this->router->getRoutes()->match(Request::create($path, 'GET'));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return null;
        }
        if (! in_array($route->getName(), self::ROUTES, true)) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        $allowed = $route->getName() === 'student.insights.index'
            ? ['range', 'start_date', 'end_date', 'timezone', 'category', 'month', 'lang']
            : ['q', 'page', 'timezone', 'date', 'session_type_id'];
        $query = array_filter(array_intersect_key($query, array_flip($allowed)),
            fn (mixed $value): bool => is_string($value) && mb_strlen($value) <= 255);
        $fragment = $parts['fragment'] ?? '';

        return $path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '')
            .($fragment !== '' && preg_match('/\A[a-zA-Z0-9_-]+\z/', $fragment) ? '#'.$fragment : '');
    }
}
