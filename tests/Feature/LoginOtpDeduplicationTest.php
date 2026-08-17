<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginOtpDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_login_sends_otp_once_and_blocks_duplicate_within_cooldown(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'student_id' => '6512345678',
            'email'      => 's6512345678@pkru.ac.th',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        // Request 1: First login request
        $response1 = $this->post(route('login'), [
            'student_id' => '6512345678',
        ]);

        $response1->assertRedirect(route('login.otp.show'));
        $this->assertEquals($student->id, session('login_otp_user_id'));

        // Assert that exactly 1 OTP mail was sent
        Mail::assertSent(LoginOtpMail::class, 1);

        // Request 2: Immediate second login request (simulating rapid double submit)
        $response2 = $this->post(route('login'), [
            'student_id' => '6512345678',
        ]);

        $response2->assertRedirect(route('login.otp.show'));

        // Assert that NO second email was dispatched (total sent remains 1)
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_staff_login_sends_otp_once_and_blocks_duplicate_within_cooldown(): void
    {
        Mail::fake();

        $staff = User::factory()->create([
            'email'     => 'teststaff@pkru.ac.th',
            'password'  => Hash::make('password123'),
            'role'      => 'staff',
            'is_active' => true,
        ]);

        // Request 1: First login request
        $response1 = $this->post(route('admin.login'), [
            'email'    => 'teststaff@pkru.ac.th',
            'password' => 'password123',
        ]);

        $response1->assertRedirect(route('login.otp.show'));
        Mail::assertSent(LoginOtpMail::class, 1);

        // Request 2: Immediate second submit
        $response2 = $this->post(route('admin.login'), [
            'email'    => 'teststaff@pkru.ac.th',
            'password' => 'password123',
        ]);

        $response2->assertRedirect(route('login.otp.show'));

        // Total emails sent must remain 1
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_resend_otp_is_blocked_during_cooldown_and_allowed_after(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'student_id' => '6598765432',
            'email'      => 's6598765432@pkru.ac.th',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        // Initial login sends 1st OTP
        $this->post(route('login'), ['student_id' => '6598765432']);
        Mail::assertSent(LoginOtpMail::class, 1);

        // Attempting to resend immediately while in cooldown
        $resendResponse1 = $this->post(route('login.otp.resend'));
        $resendResponse1->assertSessionHas('status');
        
        // Total emails sent must NOT increase because cooldown is active
        Mail::assertSent(LoginOtpMail::class, 1);

        // Clear cooldown cache to simulate waiting 60s
        Cache::forget("otp_cooldown_{$student->id}");

        // Now resend is allowed
        $resendResponse2 = $this->post(route('login.otp.resend'));
        $resendResponse2->assertSessionHas('status', 'ส่งรหัส OTP ใหม่เรียบร้อยแล้ว');

        // Total emails sent increases to 2
        Mail::assertSent(LoginOtpMail::class, 2);
    }

    public function test_user_can_verify_otp_and_login_successfully(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'student_id' => '6555555555',
            'email'      => 's6555555555@pkru.ac.th',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        // Login to generate OTP
        $this->post(route('login'), ['student_id' => '6555555555']);

        // Fetch the generated OTP from DB
        $otpRecord = DB::table('password_reset_otps')
            ->where('email', 's6555555555@pkru.ac.th')
            ->first();

        $this->assertNotNull($otpRecord);

        // Submit the valid OTP
        $verifyResponse = $this->post(route('login.otp.verify'), [
            'otp' => $otpRecord->otp,
        ]);

        $verifyResponse->assertRedirect(route('activities.index'));
        $this->assertAuthenticatedAs($student);

        // Assert OTP was deleted after successful verification
        $this->assertDatabaseMissing('password_reset_otps', [
            'email' => 's6555555555@pkru.ac.th',
        ]);
    }

    public function test_user_cannot_verify_with_wrong_or_expired_otp(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'student_id' => '6544444444',
            'email'      => 's6544444444@pkru.ac.th',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        // Login to initiate session
        $this->post(route('login'), ['student_id' => '6544444444']);

        // Submit wrong OTP
        $response = $this->post(route('login.otp.verify'), [
            'otp' => '000000',
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_bruteforce_otp_is_locked_and_invalidated_after_5_failed_attempts(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'student_id' => '6511223344',
            'email'      => 's6511223344@pkru.ac.th',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        $this->post(route('login'), ['student_id' => '6511223344']);

        // Fail 4 times
        for ($i = 1; $i <= 4; $i++) {
            $res = $this->post(route('login.otp.verify'), ['otp' => '11111' . $i]);
            $res->assertSessionHasErrors('otp');
            $this->assertGuest();
        }

        // 5th failure triggers lockout and deletes OTP
        $res5 = $this->post(route('login.otp.verify'), ['otp' => '999999']);
        $res5->assertSessionHasErrors('otp');
        $this->assertGuest();

        // OTP is purged from database
        $this->assertDatabaseMissing('password_reset_otps', [
            'email' => 's6511223344@pkru.ac.th',
        ]);
    }

    public function test_no_hardcoded_student_or_staff_bypass_exists(): void
    {
        Mail::fake();
        config(['auth.otp_bypass_ids' => []]); // Ensure no bypass configured

        // Create student with ID that previously had hardcoded bypass
        $student = User::factory()->create([
            'student_id' => '6710886217',
            'email'      => 'nontawat2546.2546@gmail.com',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        // Attempt login -> MUST redirect to OTP form, NOT dashboard directly
        $response = $this->post(route('login'), [
            'student_id' => '6710886217',
        ]);

        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_otp_bypass_works_dynamically_when_configured_in_env_config(): void
    {
        Mail::fake();
        config(['auth.otp_bypass_ids' => ['6710886217', 'admin_bypass@pkru.ac.th']]);

        $student = User::factory()->create([
            'student_id' => '6710886217',
            'email'      => 'bypass_student@pkru.ac.th',
            'role'       => 'student',
            'is_active'  => true,
        ]);

        // When ID is in config, login succeeds directly without OTP
        $response = $this->post(route('login'), [
            'student_id' => '6710886217',
        ]);

        $response->assertRedirect(route('activities.index'));
        $this->assertAuthenticatedAs($student);
        Mail::assertNothingSent();
    }
}
