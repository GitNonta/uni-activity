<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateGenerationAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_claim_category_certificate_when_hours_requirement_is_met(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $category = ActivityCategory::create([
            'name'           => 'กิจกรรมบำเพ็ญประโยชน์',
            'required_hours' => 6.0,
        ]);

        $activity = Activity::factory()->create([
            'category_id'    => $category->id,
            'activity_hours' => 6.0,
        ]);

        Attendance::create([
            'activity_id' => $activity->id,
            'user_id'     => $student->id,
            'method'      => 'qr_scan',
            'status'      => 'approved',
            'check_in_at' => now(),
        ]);

        // Student claims certificate for this category
        $response = $this->actingAs($student)->post(route('student.certificates.claim'), [
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('student.certificates.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('certificates', [
            'user_id'     => $student->id,
            'category_id' => $category->id,
        ]);

        $cert = Certificate::where('user_id', $student->id)->first();
        $this->assertNotNull($cert);
        $this->assertEquals(6.0, (float) $cert->hours_completed);

        // Test PDF download
        $pdfResponse = $this->actingAs($student)->get(route('student.certificates.download', $cert->id));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');

        // Test Public Verification Webpage
        $verifyResponse = $this->get(route('certificates.verify', $cert->certificate_code));
        $verifyResponse->assertOk();
        $verifyResponse->assertSee('ใบรับรองถูกต้องตามระบบ');
        $verifyResponse->assertSee($student->full_name);
        $verifyResponse->assertSee($cert->certificate_code);
    }

    public function test_student_cannot_claim_certificate_if_hours_requirement_is_not_met(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $category = ActivityCategory::create([
            'name'           => 'กิจกรรมวิชาการ',
            'required_hours' => 12.0,
        ]);

        // Only 4 hours approved
        $activity = Activity::factory()->create([
            'category_id'    => $category->id,
            'activity_hours' => 4.0,
        ]);

        Attendance::create([
            'activity_id' => $activity->id,
            'user_id'     => $student->id,
            'method'      => 'qr_scan',
            'status'      => 'approved',
            'check_in_at' => now(),
        ]);

        $response = $this->actingAs($student)->post(route('student.certificates.claim'), [
            'category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('certificates', [
            'user_id'     => $student->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_public_verification_handles_invalid_or_fake_codes(): void
    {
        $response = $this->get(route('certificates.verify', 'FAKE-INVALID-CODE-999'));

        $response->assertOk();
        $response->assertSee('ไม่พบข้อมูลใบรับรอง');
        $response->assertSee('FAKE-INVALID-CODE-999');
    }
}
