<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model สำหรับเกณฑ์ชั่วโมงกิจกรรมตามโครงสร้างการสำเร็จการศึกษา (Graduation Criteria)
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $faculty
 * @property string|null $department
 * @property int|null $academic_year_start
 * @property float $min_total_hours
 * @property float $min_mandatory_hours
 * @property array<string, float>|null $scope_requirements
 * @property array<string, float>|null $category_requirements
 * @property bool $require_all_mandatory_activities
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GraduationCriteria extends Model
{
    use HasFactory;

    protected $table = 'graduation_criteria';

    protected $fillable = [
        'name',
        'code',
        'faculty',
        'department',
        'academic_year_start',
        'min_total_hours',
        'min_mandatory_hours',
        'scope_requirements',
        'category_requirements',
        'require_all_mandatory_activities',
        'is_default',
        'is_active',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'academic_year_start'              => 'integer',
            'min_total_hours'                  => 'float',
            'min_mandatory_hours'              => 'float',
            'scope_requirements'               => 'array',
            'category_requirements'            => 'array',
            'require_all_mandatory_activities' => 'boolean',
            'is_default'                       => 'boolean',
            'is_active'                        => 'boolean',
        ];
    }

    /**
     * ดึงเกณฑ์เริ่มต้นของมหาวิทยาลัย
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
