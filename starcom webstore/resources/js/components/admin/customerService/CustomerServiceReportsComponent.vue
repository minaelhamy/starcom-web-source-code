<template>
    <LoadingComponent :props="loading" />
    <div class="space-y-4">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">تقارير خدمة العملاء</h3>
                <button type="button" class="db-btn text-white bg-primary py-2" @click="redistribute">إعادة توزيع العملاء</button>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="db-field-title after:hidden">من تاريخ</label>
                    <input v-model="filters.date_from" type="date" class="db-field-control" />
                </div>
                <div>
                    <label class="db-field-title after:hidden">إلى تاريخ</label>
                    <input v-model="filters.date_to" type="date" class="db-field-control" />
                </div>
                <button type="button" class="db-btn text-white bg-primary py-2 self-end" @click="fetchReports">تحديث التقرير</button>
                <button type="button" class="db-btn text-white bg-gray-600 py-2 self-end" @click="clearFilters">مسح الفترة</button>
            </div>
            <div class="px-4 pb-4 text-sm text-secondary">
                الفترة الحالية: {{ reports.date_from || defaultDateFrom }} إلى {{ reports.date_to || defaultDateTo }}
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
                            <th class="db-table-head-th">العملاء خلال الفترة</th>
                            <th class="db-table-head-th">لم يتم التواصل خلال الفترة</th>
                            <th class="db-table-head-th">إعادة اتصال خلال الفترة</th>
                            <th class="db-table-head-th">في انتظار الأوراق خلال الفترة</th>
                            <th class="db-table-head-th">مرفوض / مغلق خلال الفترة</th>
                            <th class="db-table-head-th">التقديمات خلال الفترة</th>
                            <th class="db-table-head-th">أيام النشاط خلال الفترة</th>
                            <th class="db-table-head-th">تحديثات الفترة</th>
                            <th class="db-table-head-th">آخر تحديث في الفترة</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="reports.agents?.length">
                        <tr class="db-table-body-tr" v-for="agent in reports.agents" :key="agent.agent_id">
                            <td class="db-table-body-td">{{ agent.agent_name }}</td>
                            <td class="db-table-body-td">{{ agent.period_leads_count }}</td>
                            <td class="db-table-body-td">{{ agent.not_approached_count }}</td>
                            <td class="db-table-body-td">{{ agent.callbacks_count }}</td>
                            <td class="db-table-body-td">{{ agent.waiting_documents_count }}</td>
                            <td class="db-table-body-td">{{ agent.refused_count }}</td>
                            <td class="db-table-body-td">{{ agent.period_applications_submitted_count }}</td>
                            <td class="db-table-body-td">{{ agent.active_days_count }}</td>
                            <td class="db-table-body-td">{{ agent.period_updates_count }}</td>
                            <td class="db-table-body-td">{{ formatDateTime(agent.last_activity_at) }}</td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="10">لا توجد بيانات لعرضها.</td>
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
        defaultDateFrom() {
            const date = new Date();
            date.setDate(date.getDate() - 6);
            return date.toISOString().slice(0, 10);
        },
        defaultDateTo() {
            return new Date().toISOString().slice(0, 10);
        },
    },
    mounted() {
        this.fetchReports();
    },
    methods: {
        fetchReports() {
            const payload = {
                date_from: this.filters.date_from || null,
                date_to: this.filters.date_to || null,
            };

            if (payload.date_from && !payload.date_to) {
                payload.date_to = payload.date_from;
            }

            if (payload.date_to && !payload.date_from) {
                payload.date_from = payload.date_to;
            }

            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/reports", payload).finally(() => {
                this.loading.isActive = false;
            });
        },
        clearFilters() {
            this.filters.date_from = "";
            this.filters.date_to = "";
            this.fetchReports();
        },
        formatDateTime(value) {
            if (!value) {
                return "--";
            }

            return value.replace("T", " ");
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
