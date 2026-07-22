<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\CourseUserFeedback;
use App\Models\InterviewInvitation;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);

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

        $trainingUser = User::factory()->create(['name' => 'أكاديمية مدارات للتدريب', 'email' => 'training@madarat.test', 'password' => 'password', 'role' => 'training_provider']);
        $trainingProvider = TrainingProviderProfile::create(['user_id' => $trainingUser->id, 'provider_type' => 'company', 'display_name' => 'أكاديمية مدارات للتدريب', 'legal_name' => 'أكاديمية مدارات للتدريب والتطوير', 'description' => 'مقدم تدريب مهني متخصص في المهارات الرقمية المطلوبة في سوق العمل.', 'email' => 'training@madarat.test', 'phone' => '0910000000', 'city' => 'طرابلس', 'specializations' => ['البرمجة', 'تحليل البيانات'], 'commercial_registration_number' => 'TR-2026-100', 'verification_status' => 'verified', 'verified_at' => now()]);
        $trainerUser = User::factory()->create(['name' => 'مدرب مستقل', 'email' => 'trainer@madarat.test', 'password' => 'password', 'role' => 'training_provider']);
        $trainer = TrainingProviderProfile::create(['user_id' => $trainerUser->id, 'provider_type' => 'trainer', 'display_name' => 'أحمد المدرب', 'description' => 'مدرب مستقل في التسويق الرقمي.', 'email' => 'trainer@madarat.test', 'phone' => '0920000000', 'city' => 'بنغازي', 'specializations' => ['التسويق الرقمي'], 'verification_status' => 'pending', 'verification_requested_at' => now()]);
        $courseData = [
            ['provider' => $trainingProvider, 'title' => 'Laravel و React للتطبيقات الحديثة', 'skills' => ['Laravel', 'React', 'TypeScript'], 'method' => 'hybrid', 'level' => 'intermediate', 'price' => 450, 'status' => 'published'],
            ['provider' => $trainingProvider, 'title' => 'أساسيات SQL وتحليل البيانات', 'skills' => ['SQL', 'Excel', 'تحليل البيانات'], 'method' => 'online', 'level' => 'beginner', 'price' => 200, 'status' => 'published'],
            ['provider' => $trainer, 'title' => 'التسويق الرقمي و SEO', 'skills' => ['التسويق الرقمي', 'SEO'], 'method' => 'online', 'level' => 'all_levels', 'price' => 150, 'status' => 'published'],
            ['provider' => $trainingProvider, 'title' => 'إدارة المنتجات الرقمية', 'skills' => ['إدارة المنتجات'], 'method' => 'in_person', 'level' => 'advanced', 'price' => 600, 'status' => 'draft'],
            ['provider' => $trainer, 'title' => 'كتابة المحتوى المهني', 'skills' => ['كتابة المحتوى'], 'method' => 'online', 'level' => 'beginner', 'price' => 100, 'status' => 'pending_review'],
            ['provider' => $trainer, 'title' => 'دورة إعلانية تجريبية', 'skills' => ['الإعلانات'], 'method' => 'online', 'level' => 'beginner', 'price' => 90, 'status' => 'rejected'],
        ];
        $courses = collect($courseData)->map(fn ($item) => TrainingCourse::create(['training_provider_id' => $item['provider']->id, 'title' => $item['title'], 'slug' => Str::slug($item['title']).'-'.Str::lower(Str::random(4)), 'short_description' => 'دورة عملية تربط المهارات باحتياجات سوق العمل.', 'description' => 'برنامج تدريبي تطبيقي يقدم معرفة منظمة وتمارين عملية تساعد المشاركين على تطوير مهارات قابلة للاستخدام.', 'learning_outcomes' => ['تطبيق المهارة عملياً', 'بناء مشروع تدريبي'], 'skills_taught' => $item['skills'], 'prerequisites' => $item['level'] === 'advanced' ? ['خبرة أساسية'] : [], 'difficulty_level' => $item['level'], 'delivery_method' => $item['method'], 'is_remote' => $item['method'] !== 'in_person', 'duration_value' => 4, 'duration_unit' => 'weeks', 'price' => $item['price'], 'currency' => 'LYD', 'certificate_available' => true, 'status' => $item['status'], 'published_at' => $item['status'] === 'published' ? now() : null, 'submitted_at' => in_array($item['status'], ['pending_review', 'rejected']) ? now() : null, 'rejection_reason' => $item['status'] === 'rejected' ? 'الوصف يحتاج إلى تفاصيل أوضح عن المخرجات.' : null]));
        CourseUserFeedback::create(['user_id' => $seekers[0]->id, 'course_id' => $courses[1]->id, 'saved' => true]);
        CourseUserFeedback::create(['user_id' => $seekers[1]->id, 'course_id' => $courses[2]->id, 'completed' => true]);
        CourseUserFeedback::create(['user_id' => $seekers[0]->id, 'course_id' => $courses[2]->id, 'dismissed_at' => now()]);
    }
}
