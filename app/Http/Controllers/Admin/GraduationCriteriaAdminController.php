<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGraduationCriteriaRequest;
use App\Models\ActivityCategory;
use App\Models\GraduationCriteria;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controller สำหรับการตั้งค่าเกณฑ์ชั่วโมงกิจกรรมตามโครงสร้างการสำเร็จการศึกษา
 */
class GraduationCriteriaAdminController extends Controller
{
    /**
     * หน้าแสดงและแก้ไขเกณฑ์การสำเร็จการศึกษา
     */
    public function index(): View
    {
        $criteria = GraduationCriteria::getDefault() ?? GraduationCriteria::first();
        $categories = ActivityCategory::all();

        return view('admin.graduation.criteria', [
            'criteria'   => $criteria,
            'categories' => $categories,
        ]);
    }

    /**
     * บันทึกการปรับปรุงเกณฑ์การสำเร็จการศึกษา
     */
    public function update(UpdateGraduationCriteriaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $criteria = GraduationCriteria::getDefault() ?? GraduationCriteria::first();

        if (!$criteria) {
            $criteria = new GraduationCriteria([
                'code'       => 'CRITERIA-DEFAULT',
                'is_default' => true,
                'is_active'  => true,
            ]);
        }

        $volunteerCat = ActivityCategory::where('name', 'like', '%จิตอาสา%')->first();
        $volunteerKey = $volunteerCat ? (string) $volunteerCat->id : 'volunteer';

        $criteria->name                = $validated['name'];
        $criteria->min_total_hours     = (float) $validated['min_total_hours'];
        $criteria->min_mandatory_hours = (float) $validated['min_mandatory_hours'];
        $criteria->scope_requirements  = [
            'university' => (float) $validated['scope_university'],
            'faculty'    => (float) $validated['scope_faculty'],
        ];
        $criteria->category_requirements = [
            $volunteerKey => (float) $validated['category_volunteer'],
        ];
        $criteria->description         = $validated['description'] ?? null;
        $criteria->save();

        return redirect()
            ->route('admin.graduation.criteria.index')
            ->with('success', 'บันทึกการปรับปรุงเกณฑ์ชั่วโมงกิจกรรมการสำเร็จการศึกษาเรียบร้อยแล้ว');
    }
}
