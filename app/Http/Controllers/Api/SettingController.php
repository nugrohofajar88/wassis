<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * List all settings for the authenticated user as a key => value map.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = $request->user()->settings()->pluck('value', 'key');

        return response()->json(['settings' => $settings]);
    }

    /**
     * Create or update a setting.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'   => 'required|string|max:100',
            'value' => 'nullable|string',
        ]);

        $setting = $request->user()->settings()->updateOrCreate(
            ['key' => $validated['key']],
            ['value' => $validated['value'] ?? null]
        );

        return response()->json(['setting' => $setting]);
    }

    /**
     * Delete a setting.
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $request->user()->settings()->where('key', $key)->delete();

        return response()->json(['message' => 'Setting deleted.']);
    }
}
