<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * ดึงรายการกิจกรรมทั้งหมดที่อนุญาตให้เปิดเผยได้
     */
    public function index(Request $request)
    {
        $query = Activity::query()->where('is_published', true);

        // ค้นหาตามหมวดหมู่
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // จัดเรียง
        $query->orderBy('start_time', 'desc');

        $activities = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ]
        ]);
    }
}
