<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Import TaskPhoto
// Import PlanningTask

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'location_id',
        'title',
        'description',
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
        'recurring_interval_value' => 'integer',
    ];

    /**
     * Get the location that owns the task.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the photos for the task.
     */
    public function taskPhotos(): HasMany
    {
        return $this->hasMany(TaskPhoto::class);
    }

    /**
     * Get the planning tasks associated with this task.
     */
    public function planningTasks(): HasMany
    {
        return $this->hasMany(PlanningTask::class);
    }

    /**
     * Get the user who created the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the completions for the task.
     */
    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    /**
     * Get the benodigdheden for the task.
     */
    public function benodigdheden(): BelongsToMany
    {
        return $this->belongsToMany(Benodigdheid::class, 'task_benodigdheden');
    }

    /**
     * Get the parent recurring task (if this is a generated recurring task).
     */
    public function parentRecurringTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_recurring_task_id');
    }

    /**
     * Get the child recurring tasks (if this is a parent recurring task).
     */
    public function childRecurringTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_recurring_task_id');
    }

    /**
     * Calculate the next recurring date based on completion date.
     */
    public function calculateNextRecurringDate(\DateTime $fromDate = null): ?\DateTime
    {
        if (!$this->is_recurring || !$this->recurring_interval_type || !$this->recurring_interval_value) {
            return null;
        }

        $date = $fromDate ?? new \DateTime();
        
        switch ($this->recurring_interval_type) {
            case 'days':
                $date->add(new \DateInterval('P' . $this->recurring_interval_value . 'D'));
                break;
            case 'weeks':
                $date->add(new \DateInterval('P' . ($this->recurring_interval_value * 7) . 'D'));
                break;
            case 'months':
                $date->add(new \DateInterval('P' . $this->recurring_interval_value . 'M'));
                break;
            case 'years':
                $date->add(new \DateInterval('P' . $this->recurring_interval_value . 'Y'));
                break;
        }

        return $date;
    }

    /**
     * Get human readable recurring interval description.
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
    public function createRecurringInstance(\DateTime $newDeadline = null): Task
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
        $newTask->deadline = $newDeadline;
        $newTask->save();

        // Copy benodigdheden relationship
        if ($this->benodigdheden()->exists()) {
            $newTask->benodigdheden()->sync($this->benodigdheden->pluck('id'));
        }

        return $newTask;
    }
}
