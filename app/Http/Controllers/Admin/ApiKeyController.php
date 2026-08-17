<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiKeyController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.index', ['tab' => 'api-keys']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Create token for the authenticated admin
        $token = auth()->user()->createToken((string) $request->name);

        return back()->with('success', 'API Key สร้างสำเร็จ กรุณาคัดลอก Token ด้านล่างเก็บไว้ เพราะจะแสดงเพียงครั้งเดียว: ' . $token->plainTextToken)
                     ->with('new_token', $token->plainTextToken);
    }

    public function destroy(PersonalAccessToken $apiKey): RedirectResponse
    {
        if ($apiKey->tokenable_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403, 'ไม่มีสิทธิ์ลบ API Key นี้');
        }

        $apiKey->delete();

        return back()->with('success', 'ลบ API Key เรียบร้อยแล้ว');
    }
}
