<template>
    <LoadingComponent :props="loading" />
    <div class="db-card mb-8">
        <div class="db-card-header border-none">
            <h3 class="db-card-title">ملخص خدمة العملاء</h3>
        </div>
        <div class="p-4 grid grid-cols-2 xl:grid-cols-4 gap-3">
            <div class="rounded-lg border border-[#E8E8F3] p-4">
                <p class="text-xs text-secondary mb-1">العملاء المفتوحون</p>
                <h5 class="text-2xl font-semibold text-heading">{{ summary.total_open_leads || 0 }}</h5>
            </div>
            <div class="rounded-lg border border-[#E8E8F3] p-4">
                <p class="text-xs text-secondary mb-1">موظفو الخدمة النشطون</p>
                <h5 class="text-2xl font-semibold text-heading">{{ summary.active_agents_count || 0 }}</h5>
            </div>
            <div class="rounded-lg border border-[#E8E8F3] p-4">
                <p class="text-xs text-secondary mb-1">غير موزعين</p>
                <h5 class="text-2xl font-semibold text-heading">{{ summary.unassigned_count || 0 }}</h5>
            </div>
            <div class="rounded-lg border border-[#E8E8F3] p-4">
                <p class="text-xs text-secondary mb-1">طلبات تم تجهيزها</p>
                <h5 class="text-2xl font-semibold text-heading">{{ summary.applications_submitted_count || 0 }}</h5>
            </div>
        </div>

        <div class="p-4 pt-0 grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-lg border border-[#E8E8F3] p-4">
                <h4 class="font-semibold text-heading mb-3">توزيع الحالات</h4>
                <div class="space-y-3">
                    <div v-for="item in summary.status_breakdown || []" :key="item.status" class="flex items-center justify-between text-sm">
                        <span class="text-secondary">{{ item.label }}</span>
                        <span class="font-semibold text-heading">{{ item.count }}</span>
                    </div>
                </div>
            </div>
            <div class="rounded-lg border border-[#E8E8F3] p-4">
                <h4 class="font-semibold text-heading mb-3">أفضل أداء حالياً</h4>
                <div class="space-y-3" v-if="(summary.top_agents || []).length">
                    <div v-for="agent in summary.top_agents" :key="agent.agent_id" class="flex items-center justify-between text-sm">
                        <div>
                            <p class="font-semibold text-heading">{{ agent.agent_name }}</p>
                            <p class="text-secondary">تحديثات الفترة: {{ agent.period_updates_count }}</p>
                        </div>
                        <span class="text-primary font-semibold">{{ agent.assigned_leads_count }} عميل</span>
                    </div>
                </div>
                <div class="text-sm text-secondary" v-else>
                    لا توجد بيانات أداء حتى الآن.
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";

export default {
    name: "CustomerServiceAdminDashboardComponent",
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
