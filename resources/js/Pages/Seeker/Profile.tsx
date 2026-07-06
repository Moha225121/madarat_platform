import { useForm } from '@inertiajs/react';
import { Button, Card, DashboardLayout } from '@/Components/Madarat';

export default function Profile({ profile }: any) {
    const { data, setData, post, processing } = useForm({ headline: profile.headline || '', city: profile.city || '', field: profile.field || '', bio: profile.bio || '' });
    return <DashboardLayout title="الملف المهني"><Card><form onSubmit={(e) => { e.preventDefault(); post('/seeker/profile'); }} className="grid gap-4 md:grid-cols-2"><input value={data.headline} onChange={(e) => setData('headline', e.target.value)} placeholder="العنوان المهني" className="rounded-lg border-slate-200" /><input value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="المدينة" className="rounded-lg border-slate-200" /><input value={data.field} onChange={(e) => setData('field', e.target.value)} placeholder="المجال" className="rounded-lg border-slate-200" /><textarea value={data.bio} onChange={(e) => setData('bio', e.target.value)} placeholder="نبذة مختصرة" className="rounded-lg border-slate-200 md:col-span-2" rows={5} /><Button disabled={processing}>حفظ الملف</Button></form></Card></DashboardLayout>;
}
