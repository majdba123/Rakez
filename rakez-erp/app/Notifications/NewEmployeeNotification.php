<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewEmployeeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $employee;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $employee)
    {
        $this->employee = $employee;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'employee_email' => $this->employee->email,
            'employee_phone' => $this->employee->phone,
            'employee_type' => $this->employee->type,
            'message' => 'تم إضافة موظف جديد: ' . $this->employee->name,
            'created_at' => $this->employee->created_at->toISOString(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'employee_email' => $this->employee->email,
            'employee_phone' => $this->employee->phone,
            'employee_type' => $this->employee->type,
            'message' => 'تم إضافة موظف جديد: ' . $this->employee->name,
            'created_at' => $this->employee->created_at->toISOString(),
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'employee.created';
    }
}

