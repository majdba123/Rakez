<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public $employeeId;
    public $employeeName;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(User $employee)
    {
        $this->employeeId = $employee->id;
        $this->employeeName = $employee->name;
        $this->message = 'تم إضافة موظف جديد برقم: ' . $employee->id;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-notifications'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'employee.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'message' => $this->message,
        ];
    }
}
