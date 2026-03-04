<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Location $location
 * @property-read Collection<int, PlanningTask> $planningTasks
 */
class Task extends Model
{
    /**
     * @use HasFactory<TaskFactory>
     */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'title',
        'description',
        'feedback_information',
        'feedback_owner_name',
        'feedback_emails',
        'is_photo_required',
        'deadline',
        'estimated_time_minutes',
        'status',
        'review_notes',
        'priority',
        'created_by',
        'end_day_action_title',
        'end_day_action_description',
        'is_recurring',
        'recurring_interval_type',
        'recurring_interval_value',
        'parent_recurring_task_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline' => 'date',
        'estimated_time_minutes' => 'integer',
        'priority' => TaskPriority::class,
        'status' => TaskStatus::class,
        'is_recurring' => 'boolean',
        'is_photo_required' => 'boolean',
        'recurring_interval_value' => 'integer',
    ];

    /**
     * Get the location that owns the task.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the photos for the task.
     *
     * @return HasMany<TaskPhoto, $this>
     */
    public function taskPhotos(): HasMany
    {
        return $this->hasMany(TaskPhoto::class);
    }

    /**
     * Get the planning tasks associated with this task.
     *
     * @return HasMany<PlanningTask, $this>
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the user who created the task.
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the completions for the task.
     *
     * @return HasMany<TaskCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    /**
     * Get the requirements for the task.
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'task_requirements');
    }

    /**
     * Get the parent recurring task (if this is a generated recurring task).
     * @return BelongsTo<Task, $this>
     */
    public function parentRecurringTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_recurring_task_id');
    }

    /**
     * Get the child recurring tasks (if this is a parent recurring task).
     * @return HasMany<Task, $this>
     */
    public function childRecurringTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_recurring_task_id');
    }


    /**
     * Calculate the next recurring date based on completion date.
     */
    public function calculateNextRecurringDate(?\DateTimeInterface $fromDate = null): ?\DateTimeInterface
    {
        if (!$this->is_recurring || !$this->recurring_interval_type || !$this->recurring_interval_value) {
            return null;
        }

        // Ensure we always operate on an immutable instance that supports add()
        $date = $fromDate
            ? \DateTimeImmutable::createFromInterface($fromDate)
            : new \DateTimeImmutable();

        switch ($this->recurring_interval_type) {
            case 'days':
                $date = $date->add(new \DateInterval('P' . $this->recurring_interval_value . 'D'));
                break;
            case 'weeks':
                $date = $date->add(new \DateInterval('P' . ($this->recurring_interval_value * 7) . 'D'));
                break;
            case 'months':
                $date = $date->add(new \DateInterval('P' . $this->recurring_interval_value . 'M'));
                break;
            case 'years':
                $date = $date->add(new \DateInterval('P' . $this->recurring_interval_value . 'Y'));
                break;
        }

        return $date;
    }

    /**
     * Get human readable recurring interval description.
     * @return string|null
     */
    public function getRecurringIntervalDescription(): ?string
    {
        if (!$this->is_recurring || !$this->recurring_interval_type || !$this->recurring_interval_value) {
            return null;
        }

        $value = $this->recurring_interval_value;
        $type = $this->recurring_interval_type;

        $typeLabels = [
            'days' => $value === 1 ? 'dag' : 'dagen',
            'weeks' => $value === 1 ? 'week' : 'weken',
            'months' => $value === 1 ? 'maand' : 'maanden',
            'years' => $value === 1 ? 'jaar' : 'jaren',
        ];

        return "Elke {$value} {$typeLabels[$type]}";
    }

    /**
     * Create a new recurring task instance based on this task.
     */
    public function createRecurringInstance(?\DateTimeInterface $newDeadline = null): Task
    {
        $newTask = $this->replicate([
            'id',
            'created_at',
            'updated_at',
            'status',
            'review_notes',
        ]);

        $newTask->parent_recurring_task_id = $this->id;
        $newTask->status = TaskStatus::OPEN;
        // Ensure Carbon|null assignment to match casted property type
        $newTask->deadline = $newDeadline ? \Carbon\Carbon::instance(\DateTime::createFromInterface($newDeadline)) : null;
        $newTask->save();

        // Copy requirements relationship
        if ($this->requirements()->exists()) {
            $newTask->requirements()->sync($this->requirements->pluck('id'));
        }

        return $newTask;
    }
}
