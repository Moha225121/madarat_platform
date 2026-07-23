import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type AccountType = 'job-seeker' | 'employer' | 'trainer' | 'training-company';

const accountContent: Record<AccountType, { title: string; description: string; nameLabel: string }> = {
    'job-seeker': {
        title: 'إنشاء حساب باحث عن عمل',
        description: 'أنشئ ملفك المهني وابحث عن الفرص المناسبة لمهاراتك.',
        nameLabel: 'الاسم',
    },
    employer: {
        title: 'إنشاء حساب صاحب عمل',
        description: 'عرّف بشركتك وانشر الوظائف للوصول إلى الكفاءات المناسبة.',
        nameLabel: 'اسم المسؤول أو الشركة',
    },
    trainer: {
        title: 'إنشاء حساب مدرب مستقل',
        description: 'اعرض خبرتك وأنشئ دوراتك لتصل إلى المتعلمين المناسبين.',
        nameLabel: 'اسم المدرب',
    },
    'training-company': {
        title: 'إنشاء حساب شركة تدريب',
        description: 'قدّم برامج مؤسستك وأدِر دوراتك من مكان واحد.',
        nameLabel: 'اسم شركة التدريب',
    },
};

export default function Register({ accountType = 'job-seeker' }: { accountType?: AccountType }) {
    const content = accountContent[accountType];
    const { data, setData, post, processing, errors, reset } = useForm<any>({
        name: '',
        email: '',
        phone: '',
        account_type: accountType,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title={content.title} />

            <form onSubmit={submit}>
                <div className="mb-5 rounded-lg bg-madarat-sky p-4 text-center ring-1 ring-cyan-100">
                    <h1 className="text-xl font-black text-madarat-navy">{content.title}</h1>
                    <p className="mt-2 text-sm font-bold leading-6 text-slate-600">{content.description}</p>
                </div>

                <div>
                    <InputLabel htmlFor="name" value={content.nameLabel} />
                    <TextInput id="name" name="name" value={data.name} className="mt-1 block w-full" autoComplete="name" isFocused={true} onChange={(e) => setData('name', e.target.value)} required />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="email" value="البريد الإلكتروني" />
                    <TextInput id="email" type="email" name="email" value={data.email} className="mt-1 block w-full" autoComplete="username" onChange={(e) => setData('email', e.target.value)} required />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="phone" value="رقم الهاتف" />
                    <TextInput id="phone" name="phone" value={data.phone} className="mt-1 block w-full" onChange={(e) => setData('phone', e.target.value)} />
                    <InputError message={errors.phone} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="كلمة المرور" />
                    <TextInput id="password" type="password" name="password" value={data.password} className="mt-1 block w-full" autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} required />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password_confirmation" value="تأكيد كلمة المرور" />
                    <TextInput id="password_confirmation" type="password" name="password_confirmation" value={data.password_confirmation} className="mt-1 block w-full" autoComplete="new-password" onChange={(e) => setData('password_confirmation', e.target.value)} required />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <Link href={route('login')} className="rounded-lg text-sm font-bold text-madarat-blue underline hover:text-madarat-navy focus:outline-none focus:ring-2 focus:ring-madarat-cyan focus:ring-offset-2">
                        لديك حساب بالفعل؟
                    </Link>

                    <PrimaryButton className="ms-4" disabled={processing}>
                        إنشاء حساب
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
