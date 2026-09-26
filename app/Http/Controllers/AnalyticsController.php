<?php

namespace App\Http\Controllers;

use App\Domains\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Ingest client-side analytics events safely (rate-limited, allow-listed, bounded, idempotent).
     */
    public function track(Request $request): JsonResponse
    {
        // 1. Server-side payload bounds enforcement: 32 KB maximum payload
        if (strlen((string) $request->getContent()) > 32768) {
            return response()->json([
                'status' => 'rejected',
                'message' => 'Payload exceeds 32KB limit.',
            ], 413);
        }

        $input = $request->all();
        $isBatch = isset($input['events']) && is_array($input['events']);

        if ($isBatch) {
            $events = $input['events'];
            if (count($events) > 20) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Batch exceeds maximum limit of 20 events.',
                ], 422);
            }
        } else {
            $events = [$input];
        }

        foreach ($events as $eventData) {
            if (! is_array($eventData)) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Malformed event data.',
                ], 422);
            }

            $eventName = $eventData['event_name'] ?? null;
            if (! is_string($eventName) || mb_strlen($eventName) > 64) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Missing or invalid event_name.',
                ], 422);
            }

            if (isset($eventData['event_uuid']) && (! is_string($eventData['event_uuid']) || ! Str::isUuid($eventData['event_uuid']))) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Invalid event_uuid.',
                ], 422);
            }

            // The web middleware has already refreshed this visitor's session.
            // A presence ping must not inflate page views or stored event counts.
            if ($eventName === 'session_activity') {
                continue;
            }

            if (in_array($eventName, AnalyticsService::SERVER_ONLY_EVENTS, true)) {
                Log::warning("AnalyticsController: rejected client attempt to trigger server-only event '{$eventName}'");

                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Event must be generated server-side.',
                ], 403);
            }

            if (! in_array($eventName, AnalyticsService::ALLOWED_EVENTS, true)) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => "Unrecognized event '{$eventName}'.",
                ], 422);
            }

            $eventUuid = $eventData['event_uuid'] ?? (string) Str::uuid();

            $page = isset($eventData['page']) && is_string($eventData['page'])
                ? substr($eventData['page'], 0, 500)
                : null;

            $metadata = isset($eventData['metadata']) && is_array($eventData['metadata'])
                ? $eventData['metadata']
                : [];

            if (! empty($metadata)) {
                $json = json_encode($metadata);
                if (strlen((string) $json) > 2048) {
                    return response()->json([
                        'status' => 'rejected',
                        'message' => 'Metadata size exceeds 2KB limit.',
                    ], 422);
                }

                if (count($metadata) > 10) {
                    return response()->json([
                        'status' => 'rejected',
                        'message' => 'Metadata exceeds maximum of 10 keys.',
                    ], 422);
                }

                $allowedKeys = AnalyticsService::ALLOWED_CLIENT_METADATA_KEYS[$eventName] ?? [];
                foreach ($metadata as $key => $value) {
                    if (! is_string($key) || ! in_array($key, $allowedKeys, true)) {
                        return response()->json([
                            'status' => 'rejected',
                            'message' => "Disallowed metadata key '{$key}' for event '{$eventName}'.",
                        ], 422);
                    }

                    if (is_array($value) || is_object($value)) {
                        return response()->json([
                            'status' => 'rejected',
                            'message' => 'Nested metadata is not permitted.',
                        ], 422);
                    }

                    if (is_string($value) && mb_strlen($value) > 500) {
                        return response()->json([
                            'status' => 'rejected',
                            'message' => "Metadata value for '{$key}' exceeds 500 characters.",
                        ], 422);
                    }

                    // Numeric range and format validation
                    if ($key === 'section_id') {
                        if (! is_string($value) || ! preg_match('/^[a-zA-Z0-9_\-]+$/', $value) || strlen($value) > 64) {
                            return response()->json([
                                'status' => 'rejected',
                                'message' => "Invalid section_id '{$value}'.",
                            ], 422);
                        }
                    }

                    if ($key === 'dwell_seconds') {
                        if (! is_numeric($value) || $value < 0 || $value > 86400) {
                            return response()->json([
                                'status' => 'rejected',
                                'message' => 'Out-of-range numeric value for dwell_seconds.',
                            ], 422);
                        }
                    }

                    if (in_array($key, ['score', 'total', 'duration_seconds'], true)) {
                        if (! is_numeric($value) || $value < 0 || $value > 1000000) {
                            return response()->json([
                                'status' => 'rejected',
                                'message' => "Out-of-range numeric value for '{$key}'.",
                            ], 422);
                        }
                    }
                }
            }

            $this->analyticsService->track(
                eventName: $eventName,
                metadata: $metadata,
                request: $request,
                page: $page,
                eventUuid: $eventUuid
            );
        }

        return response()->json(['status' => 'ok']);
    }
}
