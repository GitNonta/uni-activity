<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use App\Models\Setting;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์จัดการหมวดหมู่กิจกรรม (ฝั่ง Admin)
 * รองรับ: ดูรายการ, สร้าง, แก้ไขเกณฑ์ชั่วโมง, ลบ
 */
class CategoryAdminController extends Controller
{
    use LogsAdminActivity;
    /** แสดงรายการหมวดหมู่ทั้งหมด */
    public function index()
    {
        $categories     = ActivityCategory::withCount('activities')->orderBy('name')->get();
        $categorySum    = (float) $categories->sum('required_hours');
        $overrideHours  = Setting::get('total_required_hours');
        $totalRequired  = ($overrideHours !== null) ? (float) $overrideHours : $categorySum;
        $isOverridden   = $overrideHours !== null;

        return view('admin.categories.index', compact('categories', 'categorySum', 'totalRequired', 'isOverridden'));
    }

    /** บันทึกเกณฑ์ชั่วโมงรวมทั้งระบบ (override) */
    public function saveRequiredHours(Request $request)
    {
        $request->validate([
            'total_required_hours' => 'required|numeric|min:1|max:9999',
        ]);

        Setting::set('total_required_hours', $request->total_required_hours);

        return back()->with('success', 'บันทึกเกณฑ์ชั่วโมงรวม ' . number_format((float)$request->total_required_hours, 1) . ' ชม. เรียบร้อยแล้ว');
    }

    /** รีเซ็ตเกณฑ์ชั่วโมงรวมกลับไปใช้ผลรวมจากหมวดหมู่ */
    public function resetRequiredHours()
    {
        Setting::where('key', 'total_required_hours')->delete();
        return back()->with('success', 'รีเซ็ตเกณฑ์ชั่วโมงรวมกลับไปใช้ผลรวมจากหมวดหมู่แล้ว');
    }

    /** บันทึกหมวดหมู่ใหม่ */
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:100|unique:activity_categories,name',
            'description'    => 'nullable|string',
            'required_hours' => 'required|numeric|min:0|max:999',
            'color'          => 'nullable|string|max:20',
            'options'        => 'nullable|array',
        ]);

        // Convert options array to key-value pairs
        $options = [];
        if ($request->has('options')) {
            foreach ($request->options as $option) {
                if (!empty($option['key'])) {
                    $options[$option['key']] = $option['value'] ?? '';
                }
            }
        }

        $cat = ActivityCategory::create([
            'name'           => $request->name,
            'description'    => $request->description,
            'required_hours' => $request->required_hours,
            'color'          => $request->color ?? '#3B82F6',
            'options'        => $options,
        ]);
        $this->auditCreate($cat, "สร้างหมวดหมู่ \"{$cat->name}\"");

        return back()->with('success', 'เพิ่มหมวดหมู่ "' . $request->name . '" เรียบร้อยแล้ว');
    }

    /** อัปเดตข้อมูลหมวดหมู่ (ชื่อ, คำอธิบาย, เกณฑ์ชั่วโมง, สี, ตัวเลือกเพิ่มเติม) */
    public function update(Request $request, int $id)
    {
        $category = ActivityCategory::findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:100|unique:activity_categories,name,' . $id,
            'description'    => 'nullable|string',
            'required_hours' => 'required|numeric|min:0|max:999',
            'color'          => 'nullable|string|max:20',
            'options'        => 'nullable|array',
        ]);

        // Convert options array to key-value pairs
        $options = [];
        if ($request->has('options')) {
            foreach ($request->options as $option) {
                if (!empty($option['key'])) {
                    $options[$option['key']] = $option['value'] ?? '';
                }
            }
        }

        $oldValues = $category->only(['name', 'required_hours', 'color', 'options']);
        $category->update([
            'name'           => $request->name,
            'description'    => $request->description,
            'required_hours' => $request->required_hours,
            'color'          => $request->color ?? $category->color,
            'options'        => $options,
        ]);
        $this->auditUpdate($category, $oldValues, "แก้ไขหมวดหมู่ \"{$category->name}\"");

        return back()->with('success', 'อัปเดตหมวดหมู่ "' . $category->name . '" เรียบร้อยแล้ว');
    }

    /** ลบหมวดหมู่ (ถ้าไม่มีกิจกรรมอยู่) */
    public function destroy(int $id)
    {
        $category = ActivityCategory::withCount('activities')->findOrFail($id);

        if ($category->activities_count > 0) {
            return back()->with('error', 'ไม่สามารถลบได้ เนื่องจากมีกิจกรรม ' . $category->activities_count . ' รายการอยู่ในหมวดหมู่นี้');
        }

        $this->auditDelete($category, "ลบหมวดหมู่ \"{$category->name}\"");
        $category->delete();
        return back()->with('success', 'ลบหมวดหมู่เรียบร้อยแล้ว');
    }
}
