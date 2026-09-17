<?php

namespace App\Http\Controllers;

use App\Domains\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Ingest client-side analytics events safely (rate-limited, allow-listed).
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:64'],
            'page' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $eventName = $validated['event_name'];

        if (in_array($eventName, AnalyticsService::SERVER_ONLY_EVENTS, true)) {
            Log::warning("AnalyticsController: rejected client attempt to trigger server-only event '{$eventName}'");

            return response()->json(['status' => 'rejected', 'message' => 'Event must be generated server-side.'], 403);
        }

        if (! in_array($eventName, AnalyticsService::ALLOWED_EVENTS, true)) {
            return response()->json(['status' => 'rejected', 'message' => 'Unrecognized event.'], 422);
        }

        $metadata = $validated['metadata'] ?? [];

        // Validate metadata schema and limits
        if (! empty($metadata)) {
            $json = json_encode($metadata);
            if (strlen((string) $json) > 2048) {
                return response()->json(['status' => 'rejected', 'message' => 'Metadata size exceeds 2KB limit.'], 422);
            }

            if (count($metadata) > 10) {
                return response()->json(['status' => 'rejected', 'message' => 'Metadata exceeds maximum of 10 keys.'], 422);
            }

            $allowedKeys = AnalyticsService::ALLOWED_CLIENT_METADATA_KEYS[$eventName] ?? [];
            foreach ($metadata as $key => $value) {
                if (! is_string($key) || ! in_array($key, $allowedKeys, true)) {
                    return response()->json(['status' => 'rejected', 'message' => "Disallowed metadata key '{$key}' for event '{$eventName}'."], 422);
                }

                if (is_array($value) || is_object($value)) {
                    return response()->json(['status' => 'rejected', 'message' => 'Nested metadata is not permitted.'], 422);
                }

                if (is_string($value) && mb_strlen($value) > 500) {
                    return response()->json(['status' => 'rejected', 'message' => "Metadata value for '{$key}' exceeds 500 characters."], 422);
                }
            }
        }

        $this->analyticsService->track(
            eventName: $eventName,
            metadata: $metadata,
            request: $request,
            page: $validated['page'] ?? null
        );

        return response()->json(['status' => 'ok']);
    }
}
