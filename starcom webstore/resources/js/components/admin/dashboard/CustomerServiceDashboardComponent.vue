<template>
    <LoadingComponent :props="loading" />
    <div class="space-y-4">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">العملاء المكلف بهم</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.assigned_total || 0 }}</h4>
            </div>
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">بانتظار المتابعة</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.callbacks_count || 0 }}</h4>
            </div>
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">في انتظار الأوراق</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.waiting_documents_count || 0 }}</h4>
            </div>
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">تحديثات هذا الأسبوع</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.week_updates_count || 0 }}</h4>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">تم التقديم</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.applications_submitted_count || 0 }}</h4>
            </div>
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">تمت الموافقة</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.approved_count || 0 }}</h4>
            </div>
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">تم إصدار فاتورة</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.invoice_issued_count || 0 }}</h4>
            </div>
            <div class="rounded-lg bg-white p-4 border border-[#E8E8F3]">
                <p class="text-xs text-secondary mb-1">التحصيل مكتمل</p>
                <h4 class="text-2xl font-semibold text-heading">{{ summary.collections_completed_count || 0 }}</h4>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">توزيع الحالات</h3>
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-for="item in summary.status_breakdown || []" :key="item.status" class="rounded-lg border border-[#E8E8F3] p-3">
                    <p class="text-sm text-secondary mb-1">{{ item.label }}</p>
                    <h5 class="text-xl font-semibold text-heading">{{ item.count }}</h5>
                </div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">مراحل دورة العميل</h3>
            </div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="item in pipelineItems" :key="item.stage" class="rounded-lg border border-[#E8E8F3] p-3">
                    <p class="text-sm text-secondary mb-1">{{ item.label }}</p>
                    <h5 class="text-xl font-semibold text-heading">{{ item.count }}</h5>
                </div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">أقرب المتابعات</h3>
            </div>
            <div class="p-4 space-y-3" v-if="(summary.upcoming_callbacks || []).length">
                <router-link
                    v-for="item in summary.upcoming_callbacks"
                    :key="item.id"
                    :to="{ name: 'admin.customerServiceLeads.show', params: { id: item.id } }"
                    class="block rounded-lg border border-[#E8E8F3] p-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-heading">{{ item.user_name || '--' }}</h4>
                            <p class="text-sm text-secondary">{{ item.phone || '--' }}</p>
                        </div>
                        <span class="text-xs text-primary">{{ item.status_label }}</span>
                    </div>
                    <p class="text-xs text-secondary mt-2">{{ item.next_follow_up_at || '--' }}</p>
                </router-link>
            </div>
            <div class="p-4 text-sm text-secondary" v-else>
                لا توجد متابعات مجدولة حالياً.
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";

export default {
    name: "CustomerServiceDashboardComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
        };
    },
    computed: {
        summary() {
            return this.$store.getters["customerServiceCrm/dashboard"] || {};
        },
        pipelineItems() {
            return Object.values(this.summary.pipeline_breakdown || {});
        },
    },
    mounted() {
        this.fetchSummary();
    },
    methods: {
        fetchSummary() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/dashboard").finally(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
