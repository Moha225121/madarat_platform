import Modal from '@/Components/Modal';
import { CheckCircle2, XCircle } from 'lucide-react';

export default function DecisionModal({ show, title, message, confirmLabel, danger = false, reason, requireReason = false, processing = false, onReasonChange, onClose, onConfirm }: {
    show: boolean; title: string; message: string; confirmLabel: string; danger?: boolean; reason?: string;
    requireReason?: boolean; processing?: boolean; onReasonChange?: (value: string) => void; onClose: () => void; onConfirm: () => void;
}) {
    return <Modal show={show} maxWidth="md" closeable={!processing} onClose={onClose}>
        <div dir="rtl" className="p-6 text-center">
            <span className={`mx-auto grid h-14 w-14 place-items-center rounded-full ${danger ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'}`}>
                {danger ? <XCircle className="h-7 w-7" /> : <CheckCircle2 className="h-7 w-7" />}
            </span>
            <h2 className="mt-4 text-xl font-black text-madarat-navy">{title}</h2>
            <p className="mt-2 leading-7 text-slate-600">{message}</p>
            {requireReason && <div className="mt-4 text-right">
                <label htmlFor="decision-reason" className="mb-2 block text-sm font-black text-slate-700">سبب الرفض</label>
                <textarea id="decision-reason" value={reason || ''} onChange={(event) => onReasonChange?.(event.target.value)} rows={4} autoFocus
                    className="w-full rounded-lg border-slate-300 text-right focus:border-madarat-cyan focus:ring-madarat-cyan" placeholder="اكتب السبب بوضوح..." />
            </div>}
            <div className="mt-6 flex justify-center gap-3">
                <button type="button" disabled={processing} onClick={onClose} className="rounded-lg bg-slate-100 px-5 py-2.5 font-black text-slate-700 hover:bg-slate-200 disabled:opacity-60">إلغاء</button>
                <button type="button" disabled={processing || (requireReason && !reason?.trim())} onClick={onConfirm}
                    className={`rounded-lg px-5 py-2.5 font-black text-white disabled:cursor-not-allowed disabled:opacity-50 ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'}`}>
                    {processing ? 'جارٍ التنفيذ...' : confirmLabel}
                </button>
            </div>
        </div>
    </Modal>;
}
