<template>
    <LoadingComponent :props="loading" />
    <div class="space-y-4">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">تقارير خدمة العملاء</h3>
                <button type="button" class="db-btn text-white bg-primary py-2" @click="redistribute">إعادة توزيع العملاء</button>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <input v-model="filters.date_from" type="date" class="db-field-control" />
                <input v-model="filters.date_to" type="date" class="db-field-control" />
                <button type="button" class="db-btn text-white bg-primary py-2" @click="fetchReports">تحديث التقرير</button>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">أداء الفريق</h3>
            </div>
            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">الموظف</th>
                            <th class="db-table-head-th">العملاء الحاليون</th>
                            <th class="db-table-head-th">لم يتم التواصل</th>
                            <th class="db-table-head-th">إعادة اتصال</th>
                            <th class="db-table-head-th">في انتظار الأوراق</th>
                            <th class="db-table-head-th">مرفوض / مغلق</th>
                            <th class="db-table-head-th">إجمالي التقديمات</th>
                            <th class="db-table-head-th">تحديثات اليوم</th>
                            <th class="db-table-head-th">تحديثات الأسبوع</th>
                            <th class="db-table-head-th">تحديثات الفترة</th>
                            <th class="db-table-head-th">طلبات تم تجهيزها</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="reports.agents?.length">
                        <tr class="db-table-body-tr" v-for="agent in reports.agents" :key="agent.agent_id">
                            <td class="db-table-body-td">{{ agent.agent_name }}</td>
                            <td class="db-table-body-td">{{ agent.assigned_leads_count }}</td>
                            <td class="db-table-body-td">{{ agent.not_approached_count }}</td>
                            <td class="db-table-body-td">{{ agent.callbacks_count }}</td>
                            <td class="db-table-body-td">{{ agent.waiting_documents_count }}</td>
                            <td class="db-table-body-td">{{ agent.refused_count }}</td>
                            <td class="db-table-body-td">{{ agent.total_applications_submitted_count }}</td>
                            <td class="db-table-body-td">{{ agent.today_updates_count }}</td>
                            <td class="db-table-body-td">{{ agent.week_updates_count }}</td>
                            <td class="db-table-body-td">{{ agent.period_updates_count }}</td>
                            <td class="db-table-body-td">{{ agent.period_applications_submitted_count }}</td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="11">لا توجد بيانات لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";
import alertService from "../../../services/alertService";

export default {
    name: "CustomerServiceReportsComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            filters: {
                date_from: "",
                date_to: "",
            },
        };
    },
    computed: {
        reports() {
            return this.$store.getters["customerServiceCrm/reports"] || { agents: [] };
        },
    },
    mounted() {
        this.fetchReports();
    },
    methods: {
        fetchReports() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/reports", this.filters).finally(() => {
                this.loading.isActive = false;
            });
        },
        redistribute() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/redistribute", { per_agent: 300 }).then((res) => {
                alertService.success(`تمت إعادة توزيع ${res.data.data.assigned_count} عميل بنجاح`);
                this.fetchReports();
            }).catch((error) => {
                alertService.error(error.response?.data?.message || "تعذر إعادة التوزيع");
                this.loading.isActive = false;
            });
        },
    },
};
</script>
