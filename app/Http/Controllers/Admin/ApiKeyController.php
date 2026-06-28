<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiKeyController extends Controller
{
    public function index()
    {
        // We will list tokens for the currently authenticated admin
        $tokens = auth()->user()->tokens()->latest()->get();
        return view('admin.api-keys.index', compact('tokens'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Create token for the authenticated admin
        $token = auth()->user()->createToken($request->name);

        return back()->with('success', 'API Key สร้างสำเร็จ กรุณาคัดลอก Token ด้านล่างเก็บไว้ เพราะจะแสดงเพียงครั้งเดียว: ' . $token->plainTextToken)
                     ->with('new_token', $token->plainTextToken);
    }

    public function destroy($id)
    {
        $token = auth()->user()->tokens()->where('id', $id)->firstOrFail();
        $token->delete();

        return back()->with('success', 'ลบ API Key เรียบร้อยแล้ว');
    }
}
