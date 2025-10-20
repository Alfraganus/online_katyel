<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePayloadRequest;
use App\Http\Requests\Api\V1\UpdatePayloadRequest;
use App\Models\Payload;
use App\Models\KatyelLastUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayloadController extends Controller
{
    /**
     * Display a listing of payloads
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $katyel_id = $request->input('katyel_id');

        $query = Payload::query();

        if ($katyel_id) {
            $query->where('katyel_id', $katyel_id);
        }

        $payloads = $query->orderBy('datetime', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payloads,
        ], 200);
    }

    /**
     * Store a newly created payload
     *
     * @param StorePayloadRequest $request
     * @return JsonResponse
     */
    public function store(StorePayloadRequest $request): JsonResponse
    {
        $currentDateTime = date('Y-m-d H:i:s');

        $payload = Payload::create([
            'katyel_id' => $request->katyel_id,
            'katyel_name' => $request->katyel_name,
            'temp' => $request->temp,
            'datetime' => $currentDateTime,
        ]);

        KatyelLastUpdated::updateOrCreate(
            ['katyel_id' => $request->katyel_id],
            [
                'katyel_name' => $request->katyel_name,
                'temp' => $request->temp,
                'last_updated_at' => $currentDateTime,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Payload created successfully',
            'data' => $payload,
        ], 201);
    }

    /**
     * Display the specified payload
     *
     * @param Payload $payload
     * @return JsonResponse
     */
    public function show(Payload $payload): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $payload,
        ], 200);
    }

    /**
     * Update the specified payload
     *
     * @param UpdatePayloadRequest $request
     * @param Payload $payload
     * @return JsonResponse
     */
    public function update(UpdatePayloadRequest $request, Payload $payload): JsonResponse
    {
        $payload->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payload updated successfully',
            'data' => $payload,
        ], 200);
    }

    /**
     * Remove the specified payload
     *
     * @param Payload $payload
     * @return JsonResponse
     */
    public function destroy(Payload $payload): JsonResponse
    {
        $payload->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payload deleted successfully',
        ], 200);
    }

    /**
     * Get latest payloads for each katyel
     *
     * @return JsonResponse
     */
    public function latest(): JsonResponse
    {
        $payloads = Payload::select('payloads.*')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('payloads')
                    ->groupBy('katyel_id');
            })
            ->orderBy('datetime', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payloads,
        ], 200);
    }
}
