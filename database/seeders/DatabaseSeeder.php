<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\InterviewInvitation;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'مدير منصة مدارات',
            'email' => 'admin@madarat.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $employers = collect([
            ['name' => 'شركة أفق الرقمية', 'email' => 'employer@madarat.test', 'company' => 'أفق الرقمية', 'industry' => 'تقنية المعلومات', 'city' => 'طرابلس'],
            ['name' => 'مدارات للتسويق', 'email' => 'marketing@madarat.test', 'company' => 'مدارات للتسويق', 'industry' => 'التسويق', 'city' => 'بنغازي'],
            ['name' => 'بيانات ليبيا', 'email' => 'data@madarat.test', 'company' => 'بيانات ليبيا', 'industry' => 'المحاسبة', 'city' => 'مصراتة'],
        ])->map(function (array $item) {
            $user = User::factory()->create([
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => 'password',
                'role' => 'employer',
            ]);

            return CompanyProfile::create([
                'user_id' => $user->id,
                'company_name' => $item['company'],
                'industry' => $item['industry'],
                'headquarters' => $item['city'],
                'description' => 'شركة ليبية تعمل على بناء فرق عالية الكفاءة باستخدام أدوات رقمية حديثة.',
                'verification_status' => 'verified',
                'verified_at' => now(),
            ]);
        });

        $seekers = collect([
            ['name' => 'سارة المنصوري', 'email' => 'seeker@madarat.test', 'field' => 'تقنية المعلومات', 'city' => 'طرابلس', 'skills' => ['Laravel', 'React', 'TypeScript', 'SQL']],
            ['name' => 'محمد الورفلي', 'email' => 'mohamed@madarat.test', 'field' => 'التسويق', 'city' => 'بنغازي', 'skills' => ['التسويق الرقمي', 'SEO', 'كتابة المحتوى']],
            ['name' => 'ليلى السنوسي', 'email' => 'layla@madarat.test', 'field' => 'التصميم', 'city' => 'عن بعد', 'skills' => ['Figma', 'تصميم واجهات', 'هوية بصرية']],
        ])->map(function (array $item) {
            $user = User::factory()->create([
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => 'password',
                'role' => 'job_seeker',
            ]);

            JobSeekerProfile::create([
                'user_id' => $user->id,
                'headline' => 'مرشح مهني يبحث عن فرصة مناسبة',
                'city' => $item['city'],
                'field' => $item['field'],
                'bio' => 'ملف مهني تجريبي ضمن بيانات منصة مدارات.',
                'cv_status' => 'analyzed',
                'profile_score' => 82,
                'extracted_skills' => $item['skills'],
                'missing_skills' => ['إدارة المنتجات', 'العرض التقديمي'],
                'education_summary' => 'تعليم جامعي مناسب للمجال.',
                'experience_summary' => 'خبرة عملية من سنتين إلى خمس سنوات.',
                'ai_recommendations' => ['recommendations' => ['أضف إنجازات رقمية.', 'رتب المهارات حسب الوظيفة.']],
            ]);

            return $user;
        });

        $jobs = collect([
            ['company' => 0, 'title' => 'مطور Laravel و React', 'city' => 'طرابلس', 'skills' => ['Laravel', 'React', 'SQL'], 'level' => 'متوسط', 'status' => 'published'],
            ['company' => 0, 'title' => 'محلل نظم أعمال', 'city' => 'عن بعد', 'skills' => ['تحليل المتطلبات', 'SQL', 'التواصل'], 'level' => 'متوسط', 'status' => 'pending_review'],
            ['company' => 1, 'title' => 'أخصائي تسويق رقمي', 'city' => 'بنغازي', 'skills' => ['التسويق الرقمي', 'SEO', 'تحليل الحملات'], 'level' => 'مبتدئ', 'status' => 'published'],
            ['company' => 2, 'title' => 'محاسب تكاليف', 'city' => 'مصراتة', 'skills' => ['Excel', 'تحليل التكاليف', 'إعداد التقارير'], 'level' => 'متوسط', 'status' => 'published'],
        ])->map(function (array $item) use ($employers) {
            return Job::create([
                'company_profile_id' => $employers[$item['company']]->id,
                'title' => $item['title'],
                'slug' => (Str::slug($item['title']) ?: Str::random(8)).'-'.Str::random(4),
                'description' => 'وصف وظيفي عربي تجريبي يوضح المسؤوليات والمهارات المطلوبة ضمن منصة مدارات الذكية.',
                'responsibilities' => ['تنفيذ المهام اليومية', 'التعاون مع الفريق', 'تحسين جودة العمل'],
                'required_skills' => $item['skills'],
                'location' => $item['city'],
                'job_type' => 'دوام كامل',
                'contract_type' => 'عقد سنوي',
                'experience_level' => $item['level'],
                'salary_min' => 2500,
                'salary_max' => 5500,
                'status' => $item['status'],
                'generated_description' => true,
            ]);
        });

        $application = Application::create([
            'job_id' => $jobs[0]->id,
            'user_id' => $seekers[0]->id,
            'cover_letter' => 'أرغب في الانضمام إلى فريقكم والمساهمة في بناء منتجات قوية.',
            'status' => 'shortlisted',
            'match_score' => 92,
            'match_summary' => 'تطابق قوي في Laravel وReact وSQL.',
        ]);

        InterviewInvitation::create([
            'application_id' => $application->id,
            'message' => 'دعوة لمقابلة مبدئية مع فريق التوظيف.',
            'status' => 'pending',
        ]);
    }
}
