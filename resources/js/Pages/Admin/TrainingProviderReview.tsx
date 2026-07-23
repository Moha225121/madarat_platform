import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Card, DashboardLayout } from '@/Components/Madarat';
import DecisionModal from '@/Components/DecisionModal';
import { arabicLabel } from '@/lib/arabicLabels';

export default function Review({ provider }: any) {
    const [decision, setDecision] = useState<'verify' | 'reject' | null>(null);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const submit = () => {
        if (!decision) return;
        setProcessing(true);
        router.post(`/admin/training/providers/${provider.id}/${decision}`, decision === 'reject' ? { reason: reason.trim() } : {}, {
            onFinish: () => setProcessing(false),
        });
    };

    return <DashboardLayout title="مراجعة مقدم التدريب">
        <Card>
            <div className="flex justify-between"><h2 className="text-xl font-black">{provider.display_name}</h2><Badge tone="cyan">{arabicLabel('providerStatus', provider.verification_status)}</Badge></div>
            <dl className="mt-5 grid gap-3 md:grid-cols-2">
                <div>النوع: {arabicLabel('providerType', provider.provider_type)}</div><div>المدينة: {provider.city}</div>
                <div>البريد: {provider.email}</div><div>الهاتف: {provider.phone}</div>
                <div className="md:col-span-2">الوصف: {provider.description}</div>
                <div>التخصصات: {provider.specializations?.join('، ')}</div><div>السجل التجاري: {provider.commercial_registration_number || '—'}</div>
            </dl>
            {provider.rejection_reason && <p className="mt-4 text-red-600">{provider.rejection_reason}</p>}
            {provider.verification_status === 'pending' && <div className="mt-5 flex gap-2">
                <button onClick={() => setDecision('verify')} className="rounded bg-green-600 px-4 py-2 text-white">توثيق</button>
                <button onClick={() => setDecision('reject')} className="rounded bg-red-600 px-4 py-2 text-white">رفض مع السبب</button>
            </div>}
        </Card>
        <DecisionModal show={decision !== null} title={decision === 'verify' ? 'تأكيد توثيق مقدم التدريب' : 'تأكيد رفض مقدم التدريب'}
            message={decision === 'verify' ? `هل أنت متأكد من توثيق «${provider.display_name}»؟` : `هل أنت متأكد من رفض طلب «${provider.display_name}»؟`}
            confirmLabel={decision === 'verify' ? 'نعم، وثّق الحساب' : 'نعم، ارفض الطلب'} danger={decision === 'reject'}
            requireReason={decision === 'reject'} reason={reason} onReasonChange={setReason} processing={processing}
            onClose={() => { setDecision(null); setReason(''); }} onConfirm={submit} />
    </DashboardLayout>;
}
