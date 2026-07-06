import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="تأكيد البريد الإلكتروني" />

            <div className="mb-4 text-sm leading-6 text-slate-600">
                شكرا لتسجيلك. قبل البدء، يرجى تأكيد بريدك الإلكتروني عبر الرابط الذي أرسلناه لك. إذا لم يصلك الرابط يمكننا إرسال رابط جديد.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    تم إرسال رابط تأكيد جديد إلى بريدك الإلكتروني.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                        إرسال رابط جديد
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-lg text-sm font-bold text-madarat-blue underline hover:text-madarat-navy focus:outline-none focus:ring-2 focus:ring-madarat-cyan focus:ring-offset-2"
                    >
                        خروج
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
