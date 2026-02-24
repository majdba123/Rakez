<?php

namespace Database\Seeders;

use App\Models\AiCallScript;
use Illuminate\Database\Seeder;

class AiCallScriptSeeder extends Seeder
{
    public function run(): void
    {
        AiCallScript::updateOrCreate(
            ['name' => 'تأهيل العميل المحتمل'],
            [
                'target_type' => 'lead',
                'language' => 'ar',
                'greeting_text' => 'السلام عليكم {customer_name}. معاك من شركة راكز العقارية. عندي كم سؤال بسيط عشان نقدر نخدمك بشكل أفضل.',
                'closing_text' => 'خلصنا الأسئلة. بيتواصل معاك أحد من فريقنا قريب. مع السلامة.',
                'max_retries_per_question' => 2,
                'is_active' => true,
                'questions' => [
                    [
                        'key' => 'full_name',
                        'text_ar' => 'ايش اسمك الكامل؟',
                        'text_en' => 'What is your full name?',
                        'required' => true,
                        'type' => 'text',
                    ],
                    [
                        'key' => 'budget',
                        'text_ar' => 'كم ميزانيتك للشراء؟ عطني رقم تقريبي.',
                        'text_en' => 'What is your budget? Give me an approximate number.',
                        'required' => true,
                        'type' => 'number',
                    ],
                    [
                        'key' => 'timeline',
                        'text_ar' => 'متى تخطط تشتري؟ خلال شهر، ثلاث شهور، أو أكثر؟',
                        'text_en' => 'When do you plan to buy? Within a month, three months, or more?',
                        'required' => true,
                        'type' => 'choice',
                    ],
                    [
                        'key' => 'location',
                        'text_ar' => 'وين تفضل الموقع؟ أي مدينة وأي حي؟',
                        'text_en' => 'Where do you prefer the location? Which city and district?',
                        'required' => true,
                        'type' => 'text',
                    ],
                    [
                        'key' => 'property_type',
                        'text_ar' => 'تبي شقة، فيلا، أو أرض؟',
                        'text_en' => 'Do you want an apartment, villa, or land?',
                        'required' => true,
                        'type' => 'choice',
                    ],
                    [
                        'key' => 'family_size',
                        'text_ar' => 'كم عدد أفراد الأسرة؟',
                        'text_en' => 'What is your family size?',
                        'required' => true,
                        'type' => 'number',
                    ],
                    [
                        'key' => 'rooms_needed',
                        'text_ar' => 'كم غرفة تحتاج؟',
                        'text_en' => 'How many rooms do you need?',
                        'required' => true,
                        'type' => 'number',
                    ],
                    [
                        'key' => 'employment',
                        'text_ar' => 'وش طبيعة شغلك؟ حكومي، خاص، أو حر؟',
                        'text_en' => 'What is your employment type? Government, private, or self-employed?',
                        'required' => true,
                        'type' => 'choice',
                    ],
                    [
                        'key' => 'monthly_income',
                        'text_ar' => 'كم راتبك الشهري تقريباً؟',
                        'text_en' => 'What is your approximate monthly salary?',
                        'required' => true,
                        'type' => 'number',
                    ],
                    [
                        'key' => 'financing_status',
                        'text_ar' => 'عندك موافقة تمويل من بنك أو لا بعد؟',
                        'text_en' => 'Do you have bank financing approval or not yet?',
                        'required' => true,
                        'type' => 'yes_no',
                    ],
                    [
                        'key' => 'decision_maker',
                        'text_ar' => 'أنت صاحب القرار بالشراء أو فيه أحد ثاني يقرر معاك؟',
                        'text_en' => 'Are you the decision maker or is there someone else involved?',
                        'required' => true,
                        'type' => 'text',
                    ],
                    [
                        'key' => 'preferred_contact',
                        'text_ar' => 'تفضل نتواصل معاك عن طريق اتصال، واتساب، أو إيميل؟',
                        'text_en' => 'Do you prefer to be contacted by call, WhatsApp, or email?',
                        'required' => false,
                        'type' => 'choice',
                    ],
                ],
            ]
        );

        AiCallScript::updateOrCreate(
            ['name' => 'متابعة العميل الحالي'],
            [
                'target_type' => 'customer',
                'language' => 'ar',
                'greeting_text' => 'السلام عليكم {customer_name}. معاك من شركة راكز العقارية. نبي نتأكد إن كل شي تمام معاك. عندي كم سؤال سريع.',
                'closing_text' => 'شكراً على وقتك. لو تحتاج أي شي تواصل معانا. مع السلامة.',
                'max_retries_per_question' => 2,
                'is_active' => true,
                'questions' => [
                    [
                        'key' => 'satisfaction',
                        'text_ar' => 'كيف تقيّم تجربتك معانا من 1 لـ 10؟',
                        'text_en' => 'How would you rate your experience with us from 1 to 10?',
                        'required' => true,
                        'type' => 'number',
                    ],
                    [
                        'key' => 'issues',
                        'text_ar' => 'عندك أي مشكلة أو شكوى تبي تبلغنا فيها؟',
                        'text_en' => 'Do you have any issues or complaints to report?',
                        'required' => true,
                        'type' => 'text',
                    ],
                    [
                        'key' => 'payment_status',
                        'text_ar' => 'هل الدفعات ماشية بشكل طبيعي بدون أي مشاكل؟',
                        'text_en' => 'Are the payments going smoothly without any issues?',
                        'required' => true,
                        'type' => 'yes_no',
                    ],
                    [
                        'key' => 'documentation',
                        'text_ar' => 'هل كل الأوراق والمستندات مكتملة عندك؟',
                        'text_en' => 'Is all your documentation complete?',
                        'required' => true,
                        'type' => 'yes_no',
                    ],
                    [
                        'key' => 'upgrade_interest',
                        'text_ar' => 'هل تفكر تشتري وحدة ثانية أو تستثمر بعقار إضافي؟',
                        'text_en' => 'Are you considering buying another unit or investing in additional property?',
                        'required' => true,
                        'type' => 'yes_no',
                    ],
                    [
                        'key' => 'referrals',
                        'text_ar' => 'هل عندك أحد من معارفك يدور عقار ممكن نتواصل معه؟',
                        'text_en' => 'Do you have any acquaintances looking for property that we can contact?',
                        'required' => false,
                        'type' => 'text',
                    ],
                    [
                        'key' => 'timeline_next',
                        'text_ar' => 'متى تبي نتواصل معاك المرة الجاية؟',
                        'text_en' => 'When would you like us to contact you next?',
                        'required' => false,
                        'type' => 'text',
                    ],
                    [
                        'key' => 'additional_needs',
                        'text_ar' => 'فيه أي شي ثاني تحتاجه منا؟',
                        'text_en' => 'Is there anything else you need from us?',
                        'required' => false,
                        'type' => 'text',
                    ],
                ],
            ]
        );
    }
}
