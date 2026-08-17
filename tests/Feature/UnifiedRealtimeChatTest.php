<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Models\ActivityCategory;
use App\Models\JobListing;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnifiedRealtimeChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private User $otherStudent;
    private JobListing $job;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role'       => 'admin',
            'email'      => 'admin_chat@test.com',
            'full_name'  => 'Admin Chat',
            'student_id' => 'ADM001',
        ]);

        $this->student = User::factory()->create([
            'role'       => 'student',
            'email'      => 'student_chat@test.com',
            'full_name'  => 'Student Chat User',
            'student_id' => 'STU9901',
            'faculty'    => 'คณะวิทยาศาสตร์และเทคโนโลยี',
        ]);

        $this->otherStudent = User::factory()->create([
            'role'       => 'student',
            'email'      => 'other_chat@test.com',
            'full_name'  => 'Other Student',
            'student_id' => 'STU9902',
        ]);

        $category = ActivityCategory::create([
            'name'           => 'วิชาการ',
            'required_hours' => 10,
        ]);

        $this->job = JobListing::create([
            'title'        => 'ผู้ช่วยงานวิจัย Real-Time',
            'position'     => 'ผู้ช่วยวิจัย',
            'category_id'  => $category->id,
            'description'  => 'รายละเอียดงานวิจัย',
            'requirements' => 'เขียนโค้ดได้',
            'contact_info' => 'contact@test.com',
            'location'     => 'อาคาร 1',
            'status'       => 'open',
            'start_date'   => now()->addDays(2),
            'created_by'   => $this->admin->id,
        ]);
    }

    public function test_student_can_send_message_and_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->student)->post(route('chat.send', $this->job->id), [
            'message'     => 'สวัสดีครับ มีข้อสงสัยเรื่องงานวิจัยครับ',
            'attachments' => [$file],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => [
                'message' => 'สวัสดีครับ มีข้อสงสัยเรื่องงานวิจัยครับ',
            ],
        ]);

        $this->assertDatabaseHas('messages', [
            'body'    => 'สวัสดีครับ มีข้อสงสัยเรื่องงานวิจัยครับ',
            'user_id' => $this->student->id,
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
            return $event->message->body === 'สวัสดีครับ มีข้อสงสัยเรื่องงานวิจัยครับ'
                && count($event->message->attachments) === 1;
        });
    }

    public function test_admin_can_send_message_to_student(): void
    {
        Event::fake([MessageSent::class]);

        $response = $this->actingAs($this->admin)->post(route('admin.inbox.send', [
            'jobId'   => $this->job->id,
            'userId'  => $this->student->id,
        ]), [
            'message' => 'ยินดีต้อนรับครับ สามารถเริ่มงานได้สัปดาห์หน้า',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('messages', [
            'body'    => 'ยินดีต้อนรับครับ สามารถเริ่มงานได้สัปดาห์หน้า',
            'user_id' => $this->admin->id,
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_user_can_edit_own_message_and_broadcast_event(): void
    {
        Event::fake([MessageEdited::class]);

        // Create room and message
        $room = Room::create(['type' => 'direct', 'job_id' => $this->job->id, 'created_by' => $this->student->id]);
        $room->users()->attach([$this->student->id, $this->admin->id]);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $this->student->id,
            'body'    => 'ข้อความต้นฉบับ',
        ]);

        $response = $this->actingAs($this->student)->put(route('chat.messages.edit', $message->id), [
            'message' => 'ข้อความที่ถูกแก้ไขแล้ว',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('messages', [
            'id'   => $message->id,
            'body' => 'ข้อความที่ถูกแก้ไขแล้ว',
        ]);

        Event::assertDispatched(MessageEdited::class);
    }

    public function test_user_can_delete_own_message_and_broadcast_event(): void
    {
        Event::fake([MessageDeleted::class]);

        $room = Room::create(['type' => 'direct', 'job_id' => $this->job->id, 'created_by' => $this->student->id]);
        $room->users()->attach([$this->student->id, $this->admin->id]);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $this->student->id,
            'body'    => 'ข้อความที่จะลบ',
        ]);

        $response = $this->actingAs($this->student)->delete(route('chat.messages.delete', $message->id));

        $response->assertOk();
        $this->assertSoftDeleted('messages', ['id' => $message->id]);

        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_other_student_cannot_edit_or_delete_different_user_message(): void
    {
        $room = Room::create(['type' => 'direct', 'job_id' => $this->job->id, 'created_by' => $this->student->id]);
        $room->users()->attach([$this->student->id, $this->admin->id]);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $this->student->id,
            'body'    => 'ข้อความของนักศึกษาคนที่หนึ่ง',
        ]);

        // Attempt edit
        $response = $this->actingAs($this->otherStudent)->put(route('chat.messages.edit', $message->id), [
            'message' => 'แอบแก้ไขข้อความ',
        ]);
        $response->assertForbidden();

        // Attempt delete
        $response = $this->actingAs($this->otherStudent)->delete(route('chat.messages.delete', $message->id));
        $response->assertForbidden();
    }

    public function test_student_can_mark_read_on_general_inquiry_room_zero(): void
    {
        Event::fake();

        $room = Room::create(['type' => 'direct', 'job_id' => null, 'created_by' => $this->student->id]);
        $room->users()->attach([$this->student->id, $this->admin->id]);

        $response = $this->actingAs($this->student)->post(route('chat.read', 0));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }
}
