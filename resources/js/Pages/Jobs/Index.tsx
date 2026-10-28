import { FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    GraduationCap,
    Landmark,
    LayoutGrid,
    Megaphone,
    Palette,
    RotateCcw,
    Search,
    Stethoscope,
    Truck,
    Utensils,
    type LucideIcon,
} from 'lucide-react';
import { AppLayout, Badge, Button, EmptyState, Job, JobCard, Pagination } from '@/Components/Madarat';

type JobSubcategory = {
    key: string;
    label: string;
    label_en: string;
    count: number;
};

type JobCategory = {
    key: string;
    label: string;
    label_en: string;
    count: number;
    subcategories: JobSubcategory[];
};

type JobFilters = {
    search: string;
    location: string;
    job_type: string;
    experience_level: string;
    category: string;
    subcategory: string;
};

type JobPaginator = {
    data: Job[];
    current_page: number;
    last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const categoryIcons: Record<string, LucideIcon> = {
    finance: Landmark,
    marketing_sales: Megaphone,
    content_design_media: Palette,
    administration_services: BriefcaseBusiness,
    medical: Stethoscope,
    hospitality_catering_travel: Utensils,
    education: GraduationCap,
    transport_delivery: Truck,
};

const emptyFilters: JobFilters = {
    search: '',
    location: '',
    job_type: '',
    experience_level: '',
    category: '',
    subcategory: '',
};

function categoryLabels(job: Job, categories: JobCategory[]) {
    const category = categories.find((item) => item.key === job.category);
    const subcategory = category?.subcategories.find((item) => item.key === job.subcategory);

    return {
        category: job.category_label || category?.label || null,
        subcategory: job.subcategory_label || subcategory?.label || null,
    };
}

function CategorizedJobCard({ job, categories }: { job: Job; categories: JobCategory[] }) {
    const labels = categoryLabels(job, categories);

    return (
        <div className="flex min-w-0 flex-col">
            {(labels.category || labels.subcategory) && (
                <div className="mb-2 flex flex-wrap gap-2">
                    {labels.category && <Badge tone="cyan">{labels.category}</Badge>}
                    {labels.subcategory && <Badge tone="gray">{labels.subcategory}</Badge>}
                </div>
            )}
            <JobCard job={job} />
        </div>
    );
}

function JobGrid({ jobs, categories }: { jobs: Job[]; categories: JobCategory[] }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {jobs.map((job) => <CategorizedJobCard key={job.id} job={job} categories={categories} />)}
        </div>
    );
}

export default function JobsIndex({
    jobs,
    categories,
    filters,
    counts,
}: {
    jobs: JobPaginator;
    categories: JobCategory[];
    filters: Partial<Record<keyof JobFilters, string | null>>;
    counts: { total: number; other: number };
}) {
    const initialFilters: JobFilters = {
        search: filters.search ?? '',
        location: filters.location ?? '',
        job_type: filters.job_type ?? '',
        experience_level: filters.experience_level ?? '',
        category: filters.category ?? '',
        subcategory: filters.subcategory ?? '',
    };
    const { data, setData, processing } = useForm<JobFilters>(initialFilters);
    const selectedCategory = categories.find((category) => category.key === data.category);
    const selectedSubcategory = selectedCategory?.subcategories.find((subcategory) => subcategory.key === data.subcategory);
    const hasSearchFilters = Boolean(data.search || data.location || data.job_type || data.experience_level);
    const hasAnyFilters = hasSearchFilters || Boolean(data.category || data.subcategory);
    const knownCategoryKeys = new Set(categories.map((category) => category.key));
    const otherJobs = jobs.data.filter((job) => !job.category || !knownCategoryKeys.has(job.category));

    const visit = (nextFilters: JobFilters) => {
        router.get('/jobs', nextFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        visit(data);
    };

    const selectCategory = (category: string) => {
        const nextFilters = { ...data, category, subcategory: '' };
        setData(nextFilters);
        visit(nextFilters);
    };

    const selectSubcategory = (subcategory: string) => {
        const nextFilters = { ...data, subcategory };
        setData(nextFilters);
        visit(nextFilters);
    };

    const clearFilters = () => {
        setData(emptyFilters);
        visit(emptyFilters);
    };

    const emptyTitle = hasSearchFilters
        ? 'لا توجد وظائف مطابقة لبحثك'
        : selectedCategory
            ? 'لا توجد وظائف في هذه الفئة حاليًا'
            : 'لا توجد وظائف مطابقة لبحثك';

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-black text-madarat-navy">تصفح الوظائف</h1>

                <form onSubmit={submit} className="mt-4 grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
                    <input value={data.search} onChange={(event) => setData('search', event.target.value)} placeholder="العنوان أو الشركة" className="min-w-0 rounded-lg border-slate-200 lg:col-span-2" />
                    <input value={data.location} onChange={(event) => setData('location', event.target.value)} placeholder="الموقع" className="min-w-0 rounded-lg border-slate-200" />
                    <input value={data.job_type} onChange={(event) => setData('job_type', event.target.value)} placeholder="نوع العمل" className="min-w-0 rounded-lg border-slate-200" />
                    <input value={data.experience_level} onChange={(event) => setData('experience_level', event.target.value)} placeholder="مستوى الخبرة" className="min-w-0 rounded-lg border-slate-200" />
                    <Button disabled={processing} className="w-full" type="submit">
                        <Search className="h-4 w-4" />
                        بحث
                    </Button>
                </form>
            </div>

            <section aria-labelledby="job-category-heading">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="job-category-heading" className="text-xl font-black text-madarat-navy">تصفح الوظائف حسب الفئة</h2>
                        <p className="mt-1 text-sm text-slate-500">اختر المجال الأقرب إلى خبرتك ثم حدّد التخصص المناسب.</p>
                    </div>
                    {hasAnyFilters && (
                        <button type="button" onClick={clearFilters} className="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-black text-madarat-blue hover:bg-madarat-sky">
                            <RotateCcw className="h-4 w-4" />
                            مسح الفلاتر
                        </button>
                    )}
                </div>

                <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <button
                        type="button"
                        aria-pressed={!data.category}
                        onClick={() => selectCategory('')}
                        className={`min-w-0 rounded-xl border p-4 text-start transition ${!data.category ? 'border-madarat-blue bg-madarat-blue text-white shadow-md shadow-madarat-blue/15' : 'border-cyan-100 bg-white text-madarat-navy hover:border-madarat-cyan hover:bg-madarat-sky'}`}
                    >
                        <span className={`grid h-10 w-10 place-items-center rounded-lg ${!data.category ? 'bg-white/15 text-white' : 'bg-madarat-sky text-madarat-blue'}`}>
                            <LayoutGrid className="h-5 w-5" />
                        </span>
                        <span className="mt-3 block break-words text-sm font-black">جميع الوظائف</span>
                        <span className={`mt-1 block text-xs font-bold ${!data.category ? 'text-cyan-50' : 'text-slate-500'}`}>{counts.total} وظيفة</span>
                    </button>

                    {categories.map((category) => {
                        const Icon = categoryIcons[category.key] || BriefcaseBusiness;
                        const selected = data.category === category.key;

                        return (
                            <button
                                key={category.key}
                                type="button"
                                aria-pressed={selected}
                                onClick={() => selectCategory(category.key)}
                                className={`min-w-0 rounded-xl border p-4 text-start transition ${selected ? 'border-madarat-blue bg-madarat-blue text-white shadow-md shadow-madarat-blue/15' : 'border-cyan-100 bg-white text-madarat-navy hover:border-madarat-cyan hover:bg-madarat-sky'}`}
                            >
                                <span className={`grid h-10 w-10 place-items-center rounded-lg ${selected ? 'bg-white/15 text-white' : 'bg-madarat-sky text-madarat-blue'}`}>
                                    <Icon className="h-5 w-5" />
                                </span>
                                <span className="mt-3 block break-words text-sm font-black leading-6">{category.label}</span>
                                <span className={`mt-1 block text-xs font-bold ${selected ? 'text-cyan-50' : 'text-slate-500'}`}>{category.count} وظيفة</span>
                            </button>
                        );
                    })}
                </div>

                {selectedCategory && (
                    <div className="mt-4 flex flex-wrap gap-2" aria-label="الفئات الفرعية">
                        <button
                            type="button"
                            aria-pressed={!data.subcategory}
                            onClick={() => selectSubcategory('')}
                            className={`rounded-full px-4 py-2 text-sm font-black ring-1 transition ${!data.subcategory ? 'bg-madarat-blue text-white ring-madarat-blue' : 'bg-white text-slate-600 ring-cyan-100 hover:bg-madarat-sky'}`}
                        >
                            <span>الكل</span> <span>({selectedCategory.count})</span>
                        </button>
                        {selectedCategory.subcategories.map((subcategory) => (
                            <button
                                key={subcategory.key}
                                type="button"
                                aria-pressed={data.subcategory === subcategory.key}
                                onClick={() => selectSubcategory(subcategory.key)}
                                className={`max-w-full rounded-full px-4 py-2 text-sm font-black ring-1 transition ${data.subcategory === subcategory.key ? 'bg-madarat-blue text-white ring-madarat-blue' : 'bg-white text-slate-600 ring-cyan-100 hover:bg-madarat-sky'}`}
                            >
                                <span className="break-words">{subcategory.label}</span> ({subcategory.count})
                            </button>
                        ))}
                    </div>
                )}
            </section>

            <section className="mt-8" aria-live="polite">
                {jobs.data.length > 0 ? (
                    selectedCategory ? (
                        <>
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <h2 className="text-xl font-black text-madarat-navy">{selectedCategory.label}</h2>
                                <span className="text-sm font-bold text-slate-500">{selectedSubcategory?.count ?? selectedCategory.count} وظيفة</span>
                            </div>
                            <JobGrid jobs={jobs.data} categories={categories} />
                        </>
                    ) : (
                        <div className="space-y-8">
                            {categories.map((category) => {
                                const categoryJobs = jobs.data.filter((job) => job.category === category.key);
                                if (categoryJobs.length === 0) return null;

                                return (
                                    <section key={category.key} aria-labelledby={`category-${category.key}`}>
                                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-cyan-100 pb-3">
                                            <h2 id={`category-${category.key}`} className="text-xl font-black text-madarat-navy">{category.label}</h2>
                                            <span className="text-sm font-bold text-slate-500">{category.count} وظيفة</span>
                                        </div>
                                        <JobGrid jobs={categoryJobs} categories={categories} />
                                    </section>
                                );
                            })}

                            {otherJobs.length > 0 && (
                                <section aria-labelledby="other-jobs-heading">
                                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-cyan-100 pb-3">
                                        <h2 id="other-jobs-heading" className="text-xl font-black text-madarat-navy">وظائف أخرى</h2>
                                        <span className="text-sm font-bold text-slate-500">{counts.other} وظيفة</span>
                                    </div>
                                    <JobGrid jobs={otherJobs} categories={categories} />
                                </section>
                            )}
                        </div>
                    )
                ) : (
                    <div>
                        <EmptyState title={emptyTitle} text="جرّب تعديل كلمات البحث أو اختر فئة أخرى." />
                        {hasAnyFilters && (
                            <div className="mt-4 text-center">
                                <Button type="button" onClick={clearFilters}>
                                    <RotateCcw className="h-4 w-4" />
                                    مسح الفلاتر
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                <Pagination paginator={jobs} />
            </section>
        </AppLayout>
    );
}
