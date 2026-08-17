<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_csv_import_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.students.import.template'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('student_id', $response->getContent());
        $this->assertStringContainsString('full_name', $response->getContent());
    }

    public function test_admin_can_bulk_import_students_via_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Existing student to test upsert
        $existingStudent = User::factory()->create([
            'student_id' => '6710881111',
            'full_name'  => 'นายเดิม เก่า',
            'faculty'    => 'คณะครุศาสตร์',
            'role'       => 'student',
        ]);

        $csvContent = "student_id,full_name,email,faculty,department,year,program\n";
        $csvContent .= "6710881111,นายเดิม อัปเดตใหม่,s6710881111@pkru.ac.th,คณะครุศาสตร์,สาขาวิชาคณิตศาสตร์,2,ภาคปกติ\n";
        $csvContent .= "6710882222,นางสาวใหม่ มุ่งมั่น,s6710882222@pkru.ac.th,คณะวิทยาศาสตร์,สาขาวิชาเคมี,1,ภาคปกติ\n";
        $csvContent .= "6710883333,นายสาม พัฒนา,,คณะวิทยาการจัดการ,สาขาวิชาการจัดการ,1,ภาคปกติ\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('admin.students.import.upload'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success');

        // Assert existing student updated
        $this->assertDatabaseHas('users', [
            'student_id' => '6710881111',
            'full_name'  => 'นายเดิม อัปเดตใหม่',
            'department' => 'สาขาวิชาคณิตศาสตร์',
        ]);

        // Assert new students created
        $this->assertDatabaseHas('users', [
            'student_id' => '6710882222',
            'full_name'  => 'นางสาวใหม่ มุ่งมั่น',
            'role'       => 'student',
        ]);

        // Assert auto-generated email when blank
        $this->assertDatabaseHas('users', [
            'student_id' => '6710883333',
            'email'      => 's6710883333@pkru.ac.th',
        ]);
    }
}
