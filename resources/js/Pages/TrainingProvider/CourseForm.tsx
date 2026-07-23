import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { FormEvent } from 'react';

const inputClass = 'mt-1 w-full rounded-lg border-slate-200';

function ErrorText({ message }: { message?: string }) {
    return message ? <small className="mt-1 block font-bold text-red-600">{message}</small> : null;
}

export default function CourseForm({ course }: any) {
    const form = useForm<any>({
        title: course?.title || '',
        short_description: course?.short_description || '',
        description: course?.description || '',
        learning_outcomes: (course?.learning_outcomes || []).join('\n'),
        skills_taught: (course?.skills_taught || []).join('، '),
        target_audience: course?.target_audience || '',
        prerequisites: (course?.prerequisites || []).join('، '),
        difficulty_level: course?.difficulty_level || 'beginner',
        delivery_method: course?.delivery_method || 'online',
        city: course?.city || '',
        location: course?.location || '',
        is_remote: course?.is_remote || false,
        duration_value: course?.duration_value || '',
        duration_unit: course?.duration_unit || 'hours',
        start_date: course?.start_date?.slice(0, 10) || '',
        end_date: course?.end_date?.slice(0, 10) || '',
        registration_deadline: course?.registration_deadline?.slice(0, 10) || '',
        price: course?.price || 0,
        currency: course?.currency || 'LYD',
        capacity: course?.capacity || '',
        contact_email: course?.contact_email || '',
        contact_phone: course?.contact_phone || '',
        registration_url: course?.registration_url || '',
        certificate_available: course?.certificate_available || false,
        cover_image: null,
        submission_action: 'draft',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        save('draft');
    };

    const save = (action: 'draft' | 'review') => {
        form.setData('submission_action', action);
        form.transform((data: any) => ({ ...data, submission_action: action }));
        course
            ? form.put(`/training/courses/${course.id}`)
            : form.post('/training/courses', { forceFormData: true });
    };

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-black">{course ? 'تعديل الدورة' : 'إضافة دورة تدريبية'}</h1>}>
            <Head title={course ? 'تعديل الدورة' : 'إضافة دورة تدريبية'} />
            <form onSubmit={submit} className="mx-auto grid max-w-5xl gap-4 p-6 md:grid-cols-2">
                <label>عنوان الدورة<input className={inputClass} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /><ErrorText message={form.errors.title} /></label>
                <label>الوصف المختصر<input className={inputClass} value={form.data.short_description} onChange={(e) => form.setData('short_description', e.target.value)} /><ErrorText message={form.errors.short_description} /></label>
                <label className="md:col-span-2">الوصف التفصيلي<textarea rows={6} className={inputClass} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /><ErrorText message={form.errors.description} /></label>
                <label>المهارات التي تقدمها الدورة<input className={inputClass} value={form.data.skills_taught} onChange={(e) => form.setData('skills_taught', e.target.value)} /><ErrorText message={form.errors.skills_taught} /></label>
                <label>المتطلبات السابقة<input className={inputClass} value={form.data.prerequisites} onChange={(e) => form.setData('prerequisites', e.target.value)} /><ErrorText message={form.errors.prerequisites} /></label>
                <label className="md:col-span-2">مخرجات التعلّم<textarea rows={4} className={inputClass} value={form.data.learning_outcomes} onChange={(e) => form.setData('learning_outcomes', e.target.value)} /><ErrorText message={form.errors.learning_outcomes} /></label>
                <label className="md:col-span-2">الفئة المستهدفة<textarea rows={3} className={inputClass} value={form.data.target_audience} onChange={(e) => form.setData('target_audience', e.target.value)} /><ErrorText message={form.errors.target_audience} /></label>

                <label>مستوى الدورة<select className={inputClass} value={form.data.difficulty_level} onChange={(e) => form.setData('difficulty_level', e.target.value)}><option value="beginner">مبتدئ</option><option value="intermediate">متوسط</option><option value="advanced">متقدم</option><option value="all_levels">جميع المستويات</option></select></label>
                <label>طريقة تقديم الدورة<select className={inputClass} value={form.data.delivery_method} onChange={(e) => form.setData('delivery_method', e.target.value)}><option value="online">عن بُعد</option><option value="in_person">حضوري</option><option value="hybrid">هجين</option></select></label>
                <label>المدينة<input className={inputClass} value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} /><ErrorText message={form.errors.city} /></label>
                <label>مكان انعقاد الدورة<input className={inputClass} value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} /><ErrorText message={form.errors.location} /></label>
                <label>مدة الدورة<input type="number" min="1" className={inputClass} value={form.data.duration_value} onChange={(e) => form.setData('duration_value', e.target.value)} /><ErrorText message={form.errors.duration_value} /></label>
                <label>وحدة المدة<select className={inputClass} value={form.data.duration_unit} onChange={(e) => form.setData('duration_unit', e.target.value)}><option value="hours">ساعات</option><option value="days">أيام</option><option value="weeks">أسابيع</option><option value="months">أشهر</option></select></label>
                <label>تاريخ البداية<input type="date" className={inputClass} value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} /><ErrorText message={form.errors.start_date} /></label>
                <label>تاريخ النهاية<input type="date" className={inputClass} value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} /><ErrorText message={form.errors.end_date} /></label>
                <label>آخر موعد للتسجيل<input type="date" className={inputClass} value={form.data.registration_deadline} onChange={(e) => form.setData('registration_deadline', e.target.value)} /><ErrorText message={form.errors.registration_deadline} /></label>
                <label>العدد الأقصى للمشاركين<input type="number" min="1" className={inputClass} value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} /><ErrorText message={form.errors.capacity} /></label>
                <label>سعر الدورة<input type="number" min="0" step="0.01" className={inputClass} value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} /><ErrorText message={form.errors.price} /></label>
                <label>العملة<select className={inputClass} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)}><option value="LYD">دينار ليبي</option><option value="USD">دولار أمريكي</option><option value="EUR">يورو</option></select></label>
                <label>البريد الإلكتروني للتواصل<input type="email" className={inputClass} value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} /><ErrorText message={form.errors.contact_email} /></label>
                <label>رقم الهاتف للتواصل<input className={inputClass} value={form.data.contact_phone} onChange={(e) => form.setData('contact_phone', e.target.value)} /><ErrorText message={form.errors.contact_phone} /></label>
                <label className="md:col-span-2">رابط التسجيل الخارجي<input type="url" className={inputClass} value={form.data.registration_url} onChange={(e) => form.setData('registration_url', e.target.value)} /><ErrorText message={form.errors.registration_url} /></label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_remote} onChange={(e) => form.setData('is_remote', e.target.checked)} /> الدورة متاحة عن بُعد</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.certificate_available} onChange={(e) => form.setData('certificate_available', e.target.checked)} /> شهادة إتمام متاحة</label>
                <label className="md:col-span-2">صورة غلاف الدورة<input className="mt-1 block" type="file" accept="image/*" onChange={(e) => form.setData('cover_image', e.target.files?.[0])} /><ErrorText message={form.errors.cover_image} /></label>

                <div className="flex flex-wrap gap-3 md:col-span-2">
                    <button type="button" onClick={() => save('draft')} disabled={form.processing} className="rounded-lg border border-madarat-blue bg-white px-6 py-2 font-bold text-madarat-blue disabled:opacity-60">
                        {course ? 'حفظ التعديلات' : 'حفظ كمسودة'}
                    </button>
                    <button type="button" onClick={() => save('review')} disabled={form.processing} className="rounded-lg bg-madarat-blue px-6 py-2 font-bold text-white disabled:opacity-60">
                        حفظ وإرسال للمراجعة
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
