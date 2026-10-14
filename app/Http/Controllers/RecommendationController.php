<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Recommender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * (7) หน้ากิจกรรมที่ระบบแนะนำให้ผู้ใช้แต่ละคน
 */
class RecommendationController extends Controller
{
    public function index(Request $request, Recommender $recommender)
    {
        $activities = $recommender->forUser(Auth::user(), 6);

        return view('recommendations.index', [
            'activities' => $activities,
        ]);
    }
}
