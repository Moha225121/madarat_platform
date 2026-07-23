const labels: Record<string, Record<string, string>> = {
    courseStatus: {
        draft: 'مسودة',
        pending_review: 'قيد المراجعة',
        published: 'منشورة',
        rejected: 'مرفوضة',
        closed: 'مغلقة',
        archived: 'مؤرشفة',
    },
    providerStatus: {
        incomplete: 'الملف غير مكتمل',
        pending: 'قيد المراجعة',
        verified: 'موثّق',
        rejected: 'مرفوض',
    },
    providerType: {
        trainer: 'مدرب مستقل',
        company: 'شركة تدريب',
    },
    difficulty: {
        beginner: 'مبتدئ',
        intermediate: 'متوسط',
        advanced: 'متقدم',
        all_levels: 'جميع المستويات',
    },
    delivery: {
        online: 'عن بُعد',
        in_person: 'حضوري',
        hybrid: 'هجين',
    },
    duration: {
        hours: 'ساعات',
        days: 'أيام',
        weeks: 'أسابيع',
        months: 'أشهر',
    },
    currency: {
        LYD: 'دينار ليبي',
        USD: 'دولار أمريكي',
        EUR: 'يورو',
    },
    applicationStatus: {
        pending: 'قيد المراجعة',
        shortlisted: 'في القائمة المختصرة',
        interview_invited: 'تمت دعوته للمقابلة',
        rejected: 'مرفوض',
        accepted: 'مقبول',
    },
};

export function arabicLabel(group: keyof typeof labels, value?: string | null): string {
    if (!value) return 'غير محدد';

    return labels[group][value] ?? 'غير محدد';
}
