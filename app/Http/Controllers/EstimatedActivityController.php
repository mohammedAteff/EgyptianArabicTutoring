<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Services\EstimatedActivityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class EstimatedActivityController extends Controller
{
    public function anchor(Request $request, string $domain, EstimatedActivityService $activity): JsonResponse
    {
        return $this->respond(fn (): array => $activity->anchor($request, $domain));
    }

    public function checkpoint(Request $request, string $domain, EstimatedActivityService $activity): JsonResponse
    {
        return $this->respond(fn (): array => $activity->checkpoint($request, $domain));
    }

    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action())->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : ($exception instanceof ValidationException ? 422 : ($exception instanceof ModelNotFoundException ? 404 : 503));

            $reason = in_array($exception->getMessage(), ['observation_binding', 'context_changed', 'sequence_or_gap'], true) ? $exception->getMessage() : 'unavailable';

            return response()->json(['error' => __('activity.request_unavailable'), 'reason' => $reason], $status)->header('Cache-Control', 'no-store, private');
        }
    }
}
