import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const fields = [
    ['display_name', 'الاسم الظاهر'],
    ['legal_name', 'الاسم القانوني'],
    ['email', 'البريد الإلكتروني'],
    ['phone', 'رقم الهاتف'],
    ['website', 'الموقع الإلكتروني'],
    ['city', 'المدينة'],
    ['address', 'العنوان'],
    ['years_of_experience', 'سنوات الخبرة'],
    ['commercial_registration_number', 'رقم السجل التجاري'],
] as const;

export default function Profile({ provider }: any) {
    const form = useForm<any>({
        ...provider,
        specializations: (provider.specializations || []).join('، '),
        certifications: (provider.certifications || []).join('، '),
        logo: null,
        profile_image: null,
    });
    const inputClass = 'mt-1 w-full rounded-lg border-slate-200';

    return (
        <AuthenticatedLayout header={<h1 className="text-xl font-black">الملف التعريفي لمقدم التدريب</h1>}>
            <Head title="ملف مقدم التدريب" />
            <form className="mx-auto grid max-w-4xl gap-4 p-6 md:grid-cols-2" onSubmit={(event) => {
                event.preventDefault();
                form.post('/training/profile', { forceFormData: true });
            }}>
                <label>
                    نوع مقدم التدريب
                    <select className={inputClass} value={form.data.provider_type} onChange={(event) => form.setData('provider_type', event.target.value)}>
                        <option value="company">شركة تدريب</option>
                        <option value="trainer">مدرب مستقل</option>
                    </select>
                </label>
                {fields.map(([key, label]) => (
                    <label key={key}>
                        {label}
                        <input
                            className={inputClass}
                            type={key === 'email' ? 'email' : key === 'website' ? 'url' : key === 'years_of_experience' ? 'number' : 'text'}
                            value={form.data[key] || ''}
                            onChange={(event) => form.setData(key, event.target.value)}
                        />
                        {form.errors[key] && <small className="mt-1 block font-bold text-red-600">{form.errors[key]}</small>}
                    </label>
                ))}
                <label className="md:col-span-2">الوصف<textarea className={inputClass} rows={5} value={form.data.description || ''} onChange={(event) => form.setData('description', event.target.value)} /></label>
                <label>التخصصات<input className={inputClass} value={form.data.specializations} onChange={(event) => form.setData('specializations', event.target.value)} /></label>
                <label>الشهادات والاعتمادات<input className={inputClass} value={form.data.certifications} onChange={(event) => form.setData('certifications', event.target.value)} /></label>
                <label>شعار شركة التدريب<input className="mt-1 block" type="file" accept="image/*" onChange={(event) => form.setData('logo', event.target.files?.[0])} /></label>
                <label>الصورة الشخصية للمدرب<input className="mt-1 block" type="file" accept="image/*" onChange={(event) => form.setData('profile_image', event.target.files?.[0])} /></label>
                <div className="md:col-span-2 flex gap-3">
                    <button disabled={form.processing} className="rounded-lg bg-madarat-blue px-5 py-2 font-bold text-white">حفظ الملف</button>
                    <button type="button" onClick={() => router.post('/training/profile/request-verification')} className="rounded-lg border px-5 py-2 font-bold">طلب التوثيق</button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
