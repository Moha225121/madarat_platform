import { FormEvent, useEffect, useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2, Upload } from 'lucide-react';
import { Button, Card, DashboardLayout } from '@/Components/Madarat';
import { useLanguage } from '@/lib/language';

type Advertisement = {
    id: number;
    image_url: string | null;
    alt_text: string | null;
    created_at: string | null;
};

type AdvertisementsProps = {
    advertisements: Advertisement[];
};

export default function Advertisements({ advertisements }: AdvertisementsProps) {
    const { locale, t } = useLanguage();
    const form = useForm<{ image: File | null; alt_text: string }>({
        image: null,
        alt_text: '',
    });
    const imageInput = useRef<HTMLInputElement | null>(null);
    const deletingIds = useRef<Set<number>>(new Set());
    const [visibleDeletingIds, setVisibleDeletingIds] = useState<Set<number>>(() => new Set());
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!form.data.image) {
            setPreviewUrl(null);

            return;
        }

        const nextPreviewUrl = URL.createObjectURL(form.data.image);
        setPreviewUrl(nextPreviewUrl);

        return () => URL.revokeObjectURL(nextPreviewUrl);
    }, [form.data.image]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing || !form.data.image) {
            return;
        }

        form.post(route('admin.advertisements.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as { error?: string | null } | undefined;

                if (flash?.error) {
                    return;
                }

                form.reset();

                if (imageInput.current) {
                    imageInput.current.value = '';
                }
            },
        });
    };

    const deleteAdvertisement = (advertisement: Advertisement) => {
        if (deletingIds.current.has(advertisement.id)) {
            return;
        }

        const confirmed = window.confirm(t(
            'هل أنت متأكد من حذف هذه الصورة الإعلانية؟ لن تظهر في الصفحة الرئيسية بعد الحذف، ولا يمكن التراجع عن هذا الإجراء.',
            'Are you sure you want to delete this advertising image? It will no longer appear on the homepage, and this action cannot be undone.',
        ));

        if (!confirmed) {
            return;
        }

        deletingIds.current.add(advertisement.id);
        setVisibleDeletingIds(new Set(deletingIds.current));

        router.delete(route('admin.advertisements.destroy', { advertisement: advertisement.id }), {
            preserveScroll: true,
            onFinish: () => {
                deletingIds.current.delete(advertisement.id);
                setVisibleDeletingIds(new Set(deletingIds.current));
            },
        });
    };

    const formattedDate = (value: string | null) => {
        if (!value) {
            return '—';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-LY' : 'en-US', {
            dateStyle: 'medium',
        }).format(date);
    };

    return (
        <DashboardLayout title={t('الصور الإعلانية', 'Advertisement images')}>
            <Head title={t('الصور الإعلانية', 'Advertisement images')} />

            <Card>
                <div className="flex items-start gap-3">
                    <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-madarat-sky text-madarat-blue">
                        <ImagePlus className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 className="text-xl font-black text-madarat-navy">
                            {t('إضافة الصورة الإعلانية', 'Add advertising image')}
                        </h2>
                        <p className="mt-1 text-sm leading-7 text-slate-500">
                            {t(
                                'يفضل استخدام صورة أفقية واضحة بنسبة قريبة من 4:3 وحجم لا يتجاوز 5 ميجابايت.',
                                'Use a clear horizontal image close to a 4:3 ratio and no larger than 5 MB.',
                            )}
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(260px,360px)]">
                    <div className="space-y-4">
                        <div>
                            <input
                                ref={imageInput}
                                id="homepage-advertisement-image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                disabled={form.processing}
                                className="sr-only"
                                onChange={(event) => {
                                    form.setData('image', event.target.files?.[0] || null);
                                    form.clearErrors('image');
                                }}
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => imageInput.current?.click()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-black text-madarat-blue shadow-sm ring-1 ring-cyan-100 transition hover:bg-madarat-sky focus:outline-none focus:ring-4 focus:ring-madarat-cyan/30 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <Upload className="h-5 w-5" aria-hidden="true" />
                                    {t('اختيار صورة', 'Choose image')}
                                </button>
                                <span className="min-w-0 truncate text-sm text-slate-500">
                                    {form.data.image?.name || t('لم يتم اختيار صورة.', 'No image selected.')}
                                </span>
                            </div>
                            {form.errors.image && (
                                <p className="mt-2 text-sm font-bold text-red-600" role="alert">
                                    {form.errors.image}
                                </p>
                            )}
                        </div>

                        <label className="block">
                            <span className="text-sm font-black text-madarat-navy">
                                {t('الوصف البديل للصورة', 'Image alternative text')}
                            </span>
                            <input
                                type="text"
                                value={form.data.alt_text}
                                maxLength={160}
                                disabled={form.processing}
                                onChange={(event) => {
                                    form.setData('alt_text', event.target.value);
                                    form.clearErrors('alt_text');
                                }}
                                placeholder={t(
                                    'صف محتوى الصورة باختصار لتحسين إمكانية الوصول',
                                    'Briefly describe the image to improve accessibility',
                                )}
                                className="mt-2 w-full rounded-lg border-slate-200 text-sm focus:border-madarat-cyan focus:ring-madarat-cyan disabled:bg-slate-100"
                            />
                            {form.errors.alt_text && (
                                <p className="mt-2 text-sm font-bold text-red-600" role="alert">
                                    {form.errors.alt_text}
                                </p>
                            )}
                        </label>

                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.image}
                            className="min-w-48"
                        >
                            {form.processing
                                ? t('جاري رفع الصورة...', 'Uploading image...')
                                : t('إضافة الصورة الإعلانية', 'Add advertising image')}
                        </Button>
                    </div>

                    <div className="aspect-[4/3] overflow-hidden rounded-lg border border-dashed border-cyan-200 bg-madarat-sky/50 p-3">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt={form.data.alt_text.trim() || t('معاينة الصورة الإعلانية', 'Advertising image preview')}
                                className="h-full w-full rounded-md bg-white object-contain"
                            />
                        ) : (
                            <div className="grid h-full place-items-center text-center text-slate-400">
                                <div>
                                    <ImagePlus className="mx-auto h-10 w-10" aria-hidden="true" />
                                    <p className="mt-2 text-sm font-bold">
                                        {t('ستظهر معاينة الصورة هنا.', 'The image preview will appear here.')}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </form>
            </Card>

            <Card className="mt-6">
                <h2 className="text-xl font-black text-madarat-navy">
                    {t('الصور الإعلانية الحالية', 'Current advertising images')}
                </h2>

                {advertisements.length > 0 ? (
                    <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {advertisements.map((advertisement) => {
                            const isDeleting = visibleDeletingIds.has(advertisement.id);

                            return (
                                <article key={advertisement.id} className="overflow-hidden rounded-lg border border-cyan-100 bg-white shadow-sm shadow-madarat-blue/5">
                                    <div className="aspect-[4/3] bg-madarat-sky/40 p-2">
                                        {advertisement.image_url ? (
                                            <img
                                                src={advertisement.image_url}
                                                alt={advertisement.alt_text?.trim() || ''}
                                                loading="lazy"
                                                className="h-full w-full rounded-md bg-white object-contain"
                                            />
                                        ) : (
                                            <div className="grid h-full place-items-center text-sm font-bold text-slate-400">
                                                {t('تعذر عرض الصورة.', 'The image could not be displayed.')}
                                            </div>
                                        )}
                                    </div>
                                    <div className="p-4">
                                        <p className="min-h-12 text-sm font-bold leading-6 text-slate-700">
                                            {advertisement.alt_text || t('لا يوجد وصف بديل.', 'No alternative text provided.')}
                                        </p>
                                        <p className="mt-2 text-xs text-slate-500">
                                            {t('تاريخ الإضافة:', 'Added on:')} {formattedDate(advertisement.created_at)}
                                        </p>
                                        <button
                                            type="button"
                                            disabled={isDeleting}
                                            onClick={() => deleteAdvertisement(advertisement)}
                                            className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            {isDeleting
                                                ? t('جاري الحذف...', 'Deleting...')
                                                : t('حذف', 'Delete')}
                                        </button>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                ) : (
                    <div className="mt-5 rounded-lg border border-dashed border-cyan-200 bg-madarat-sky/60 p-8 text-center">
                        <ImagePlus className="mx-auto h-10 w-10 text-madarat-cyan" aria-hidden="true" />
                        <p className="mt-3 font-bold leading-7 text-slate-600">
                            {t(
                                'لا توجد صور إعلانية حاليًا. ستظهر الرسومات الأصلية في الصفحة الرئيسية حتى تضيف صورة.',
                                'There are no advertising images yet. The original illustration will remain on the homepage until you add one.',
                            )}
                        </p>
                    </div>
                )}
            </Card>
        </DashboardLayout>
    );
}
