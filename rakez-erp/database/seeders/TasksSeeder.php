<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class TasksSeeder extends Seeder
{
    /**
     * أسماء مهام بالعربية السعودية.
     */
    protected array $taskNames = [
        'متابعة عرض الوحدات الجديدة للعميل',
        'إعداد تقرير المبيعات الشهري',
        'تحديث قائمة الأسعار في النظام',
        'التنسيق مع قسم التسويق للحملة القادمة',
        'مراجعة عقود الحجوزات المؤكدة',
        'متابعة استلام العروض من المطور',
        'إعداد عرض تقديمي للمشروع الجديد',
        'متابعة تسليم المفاتيح للعميل',
        'مراجعة مستندات وحدة مباعة',
        'التواصل مع عميل حجز تفاوض',
        'تحديث بيانات المشروع في البوابة',
        'متابعة دفعة العربون مع المحاسبة',
        'إعداد تقرير الأداء الأسبوعي',
        'مراجعة شكوى عميل والرد عليها',
        'تحديث صور الوحدات في المعرض',
        'متابعة موعد تسليم الوحدة مع المطور',
        'إعداد عقد حجز جديد للعميل',
        'مراجعة قائمة الوحدات المتاحة',
        'التنسيق مع الائتمان لتفعيل التمويل',
        'متابعة إجراءات نقل الملكية',
        'إعداد تقرير الحجوزات اليومي',
        'مراجعة تنفيذ خطة التسويق',
        'متابعة عميل مؤهل للشراء',
        'تحديث بيانات العميل في النظام',
        'إعداد عرض سعر للوحدة المطلوبة',
        'متابعة زيارة عميل للمعرض',
        'مراجعة مستندات التمويل المقدمة',
        'التنسيق مع الموارد البشرية للتدريب',
        'إعداد ملخص أداء الفريق',
        'متابعة عميل في قائمة الانتظار',
        'تحديث حالة الحجز بعد الموافقة',
        'مراجعة شروط العقد مع العميل',
        'متابعة استلام دفعة من العميل',
        'إعداد تقرير العمولات الشهرية',
        'التواصل مع المطور بخصوص موعد التسليم',
        'مراجعة قائمة العروض النشطة',
        'متابعة تجديد عقد إيجار المعرض',
        'إعداد عرض للمستثمر الجديد',
        'مراجعة سياسات الحجز والاسترداد',
        'متابعة تدريب الموظفين الجدد',
    ];

    public function run(): void
    {
        $teamIds = Team::query()->pluck('id')->all();
        if (empty($teamIds)) {
            $teamIds = Team::factory()->count(3)->create()->pluck('id')->all();
        }

        $users = User::query()->whereNotNull('type')->get();
        if ($users->isEmpty()) {
            $users = User::factory()->count(10)->create();
        }

        $userIds = $users->pluck('id')->all();
        $statuses = [
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_COMPLETED,
            Task::STATUS_COULD_NOT_COMPLETE,
        ];

        $reasons = [
            'العميل ألغى الرغبة في الشراء',
            'الوحدة تم بيعها لعميل آخر',
            'عدم توفر التمويل المناسب للعميل',
            'العميل لم يرد على المتابعة',
        ];

        for ($i = 0; $i < 40; $i++) {
            $status = Arr::random($statuses);
            $assigneeId = Arr::random($userIds);
            $assignee = $users->firstWhere('id', $assigneeId) ?? $users->first();
            $section = $assignee?->type ?? 'user';

            Task::query()->create([
                'task_name' => $this->taskNames[$i % count($this->taskNames)],
                'section' => $section,
                'team_id' => Arr::random($teamIds),
                'due_at' => now()->addDays(fake()->numberBetween(1, 15))->setTime(
                    fake()->numberBetween(8, 18),
                    fake()->randomElement([0, 15, 30, 45])
                ),
                'assigned_to' => $assigneeId,
                'status' => $status,
                'cannot_complete_reason' => $status === Task::STATUS_COULD_NOT_COMPLETE
                    ? Arr::random($reasons)
                    : '',
                'created_by' => Arr::random($userIds),
            ]);
        }
    }
}
