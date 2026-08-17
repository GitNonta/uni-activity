<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Repositories\ChatRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $studentA;
    private User $studentB;
    private JobListing $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->studentA = User::factory()->create(['role' => 'student', 'student_id' => '65000001']);
        $this->studentB = User::factory()->create(['role' => 'student', 'student_id' => '65000002']);

        $this->job = JobListing::create([
            'title'        => 'Test Job',
            'job_type'     => 'parttime',
            'position'     => 'Staff',
            'quota'        => 5,
            'location'     => 'Library',
            'start_date'   => now()->addDays(5)->toDateString(),
            'gender'       => 'any',
            'status'       => 'open',
            'created_by'   => $this->admin->id,
        ]);
    }

    public function test_students_have_isolated_private_chat_threads(): void
    {
        /** @var ChatRepository $chatRepository */
        $chatRepository = app(ChatRepository::class);

        // 1. Student A gets or creates room with Admin for this job
        $roomA = $chatRepository->createRoom(
            [$this->studentA->id, $this->admin->id],
            'direct',
            $this->job->title,
            $this->job->id
        );

        // 2. Student B gets or creates separate room with Admin for the same job
        $roomB = $chatRepository->createRoom(
            [$this->studentB->id, $this->admin->id],
            'direct',
            $this->job->title,
            $this->job->id
        );

        // Messages in Room A
        $chatRepository->sendMessage($roomA, $this->studentA, 'Hello from Student A');
        $chatRepository->sendMessage($roomA, $this->admin, 'Reply to Student A');

        // Messages in Room B
        $chatRepository->sendMessage($roomB, $this->studentB, 'Hello from Student B');

        // 3. Student A retrieves messages for this job
        $responseA = $this->actingAs($this->studentA)->getJson(route('chat.messages', $this->job->id));
        $responseA->assertOk();
        $messagesA = $responseA->json('messages');
        $this->assertCount(2, $messagesA);
        $this->assertEquals('Hello from Student A', $messagesA[0]['message']);
        $this->assertEquals('Reply to Student A', $messagesA[1]['message']);

        // 4. Student B retrieves messages for this job
        $responseB = $this->actingAs($this->studentB)->getJson(route('chat.messages', $this->job->id));
        $responseB->assertOk();
        $messagesB = $responseB->json('messages');
        $this->assertCount(1, $messagesB);
        $this->assertEquals('Hello from Student B', $messagesB[0]['message']);

        // 5. Ensure Student A's room does NOT leak to Student B
        $this->assertNotEquals($responseA->json('room_id'), $responseB->json('room_id'));
    }
}
