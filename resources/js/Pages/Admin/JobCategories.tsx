import { FormEvent, useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { BriefcaseBusiness, Plus, Tags, Trash2 } from 'lucide-react';
import { Button, Card, DashboardLayout } from '@/Components/Madarat';
import { useLanguage } from '@/lib/language';

type JobCategory = {
    id: number;
    key: string;
    label: string;
    label_en: string | null;
    jobs_count: number;
    subcategories_count: number;
};

export default function JobCategories({ categories }: { categories: JobCategory[] }) {
    const { locale, t } = useLanguage();
    const form = useForm({ name: '' });
    const [showForm, setShowForm] = useState(false);
    const deletingIds = useRef<Set<number>>(new Set());
    const [visibleDeletingIds, setVisibleDeletingIds] = useState<Set<number>>(() => new Set());

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.post('/admin/job-categories', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    };

    const deleteCategory = (category: JobCategory) => {
        if (deletingIds.current.has(category.id)) {
            return;
        }

        const confirmed = window.confirm(t(
            `هل أنت متأكد من حذف فئة «${category.label}»؟`,
            `Are you sure you want to delete “${category.label_en || category.label}”?`,
        ));

        if (!confirmed) {
            return;
        }

        deletingIds.current.add(category.id);
        setVisibleDeletingIds(new Set(deletingIds.current));

        router.delete(`/admin/job-categories/${category.id}`, {
            preserveScroll: true,
            onFinish: () => {
                deletingIds.current.delete(category.id);
                setVisibleDeletingIds(new Set(deletingIds.current));
            },
        });
    };

    return (
        <DashboardLayout title={t('فئات الوظائف', 'Job categories')}>
            <Head title={t('فئات الوظائف', 'Job categories')} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-black text-madarat-navy">{t('فئات الوظائف', 'Job categories')}</h2>
                    <p className="mt-1 text-sm leading-7 text-slate-500">
                        {t('أدر الفئات المتاحة لأصحاب العمل وفي صفحة الوظائف العامة.', 'Manage the categories available to employers and on the public jobs page.')}
                    </p>
                </div>
                <Button type="button" onClick={() => setShowForm((current) => !current)}>
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    {t('إضافة فئة', 'Add category')}
                </Button>
            </div>

            {showForm && (
                <Card className="mt-5">
                    <form onSubmit={submit} className="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <label className="min-w-0 flex-1">
                            <span className="text-sm font-black text-madarat-navy">{t('اسم الفئة', 'Category name')}</span>
                            <input
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                disabled={form.processing}
                                className="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-madarat-cyan focus:ring-madarat-cyan"
                                placeholder={t('مثال: التقنية والبرمجة', 'Example: Technology and programming')}
                                autoFocus
                            />
                            {form.errors.name && <span className="mt-1 block text-xs font-bold text-red-600">{form.errors.name}</span>}
                        </label>
                        <Button disabled={form.processing} className="sm:min-w-28">
                            {form.processing ? t('جاري الحفظ...', 'Saving...') : t('حفظ', 'Save')}
                        </Button>
                    </form>
                </Card>
            )}

            <Card className="mt-5">
                <div className="flex items-center gap-3">
                    <span className="grid h-11 w-11 place-items-center rounded-full bg-madarat-sky text-madarat-blue">
                        <Tags className="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 className="font-black text-madarat-navy">{t('الفئات الحالية', 'Current categories')}</h2>
                        <p className="text-sm text-slate-500">{categories.length} {t('فئة', 'categories')}</p>
                    </div>
                </div>

                {categories.length > 0 ? (
                    <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {categories.map((category) => {
                            const isDeleting = visibleDeletingIds.has(category.id);

                            return (
                                <article key={category.id} className="flex min-w-0 flex-col rounded-lg border border-cyan-100 bg-white p-4 shadow-sm shadow-madarat-blue/5">
                                    <div className="flex items-start gap-3">
                                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-madarat-sky text-madarat-blue">
                                            <BriefcaseBusiness className="h-5 w-5" aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0">
                                            <h3 className="break-words font-black leading-7 text-madarat-navy">
                                                {locale === 'en' && category.label_en ? category.label_en : category.label}
                                            </h3>
                                            <p className="mt-1 text-sm font-bold text-slate-500">
                                                {category.jobs_count} {t('وظيفة مرتبطة', 'linked jobs')}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                {category.subcategories_count} {t('فئة فرعية', 'subcategories')}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={isDeleting}
                                        onClick={() => deleteCategory(category)}
                                        className="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-black text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                                        {isDeleting ? t('جاري الحذف...', 'Deleting...') : t('حذف', 'Delete')}
                                    </button>
                                </article>
                            );
                        })}
                    </div>
                ) : (
                    <div className="mt-5 rounded-lg border border-dashed border-cyan-200 bg-madarat-sky/50 p-8 text-center text-sm font-bold text-slate-500">
                        {t('لا توجد فئات وظيفية حاليًا.', 'There are no job categories yet.')}
                    </div>
                )}
            </Card>
        </DashboardLayout>
    );
}
