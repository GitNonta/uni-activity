<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'job_id',
        'created_by',
        'creator_id',
    ];

    /**
     * The users that belong to the room.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'last_read_at', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the messages for the room.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the job listing associated with the room.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_id');
    }

    /**
     * Get the user who created the room.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Snapshot of the job creator at room creation time — survives job
     * deletion so staff can still see their archived chat threads.
     */
    public function jobCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * True when the room belongs to a job/announcement that has been
     * deleted: job_id is still set but the job row no longer exists.
     * Archived rooms are read-only — history stays viewable.
     */
    public function isJobDeleted(): bool
    {
        return $this->job_id !== null
            && ($this->relationLoaded('job') ? $this->job === null : !$this->job()->exists());
    }
}
