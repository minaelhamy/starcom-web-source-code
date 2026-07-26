<template>
    <LoadingComponent :props="loading" />
    <div class="space-y-4">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">متابعة أداء خدمة العملاء</h3>
                <button type="button" class="db-btn text-white bg-primary py-2" @click="redistribute">توزيع العملاء غير الموزعين</button>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="db-field-title after:hidden">من تاريخ</label>
                    <input :value="filters.date_from" @input="onDateInput('date_from', $event)" @keydown.enter.prevent="fetchReports" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" dir="ltr" class="db-field-control" />
                </div>
                <div>
                    <label class="db-field-title after:hidden">إلى تاريخ</label>
                    <input :value="filters.date_to" @input="onDateInput('date_to', $event)" @keydown.enter.prevent="fetchReports" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" dir="ltr" class="db-field-control" />
                </div>
                <button type="button" class="db-btn text-white bg-primary py-2 self-end" @click="fetchReports">تحديث التقرير</button>
                <button type="button" class="db-btn text-white bg-gray-600 py-2 self-end" @click="clearFilters">مسح الفترة</button>
            </div>
            <div class="px-4 pb-4 text-sm text-secondary">
                الفترة الحالية: {{ formatPeriodDate(reports.date_from || defaultDateFrom) }} إلى {{ formatPeriodDate(reports.date_to || defaultDateTo) }}
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">التقدم لكل موظف</h3>
            </div>
            <div class="p-4 grid grid-cols-2 xl:grid-cols-4 gap-3 border-b border-[#E8E8F3]">
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">إجمالي العملاء خلال الفترة</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.period_leads_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">إجمالي التقديمات</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.period_applications_submitted_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">إجمالي الموافقات</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.approved_facilities_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">إجمالي دفعات التحصيل</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.repayments_recorded_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">بانتظار الأوراق</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.waiting_documents_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">إعادة اتصال</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.callbacks_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">مرفوض / مغلق</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.refused_count }}</h5>
                </div>
                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <p class="text-xs text-secondary mb-1">فواتير تم إصدارها</p>
                    <h5 class="text-2xl font-semibold text-heading">{{ totals.invoices_issued_count }}</h5>
                </div>
            </div>
            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">التفاصيل</th>
                            <th class="db-table-head-th">الموظف</th>
                            <th class="db-table-head-th">العملاء خلال الفترة</th>
                            <th class="db-table-head-th">لم يتم التواصل خلال الفترة</th>
                            <th class="db-table-head-th">إعادة اتصال خلال الفترة</th>
                            <th class="db-table-head-th">في انتظار الأوراق خلال الفترة</th>
                            <th class="db-table-head-th">مرفوض / مغلق خلال الفترة</th>
                            <th class="db-table-head-th">التقديمات خلال الفترة</th>
                            <th class="db-table-head-th">الموافقات خلال الفترة</th>
                            <th class="db-table-head-th">رفض جهة التمويل</th>
                            <th class="db-table-head-th">الفواتير خلال الفترة</th>
                            <th class="db-table-head-th">العقود الموقعة</th>
                            <th class="db-table-head-th">دفعات التحصيل</th>
                            <th class="db-table-head-th">أيام النشاط خلال الفترة</th>
                            <th class="db-table-head-th">تحديثات الفترة</th>
                            <th class="db-table-head-th">آخر تحديث في الفترة</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="reports.agents?.length">
                        <template v-for="agent in reports.agents" :key="agent.agent_id">
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td">
                                    <button type="button" class="db-btn py-1 px-3 text-xs text-white bg-primary" @click="toggleAgent(agent.agent_id)">
                                        {{ isExpanded(agent.agent_id) ? 'إخفاء' : 'عرض' }}
                                    </button>
                                </td>
                                <td class="db-table-body-td">{{ agent.agent_name }}</td>
                                <td class="db-table-body-td">{{ agent.period_leads_count }}</td>
                                <td class="db-table-body-td">{{ agent.not_approached_count }}</td>
                                <td class="db-table-body-td">{{ agent.callbacks_count }}</td>
                                <td class="db-table-body-td">{{ agent.waiting_documents_count }}</td>
                                <td class="db-table-body-td">{{ agent.refused_count }}</td>
                                <td class="db-table-body-td">{{ agent.period_applications_submitted_count }}</td>
                                <td class="db-table-body-td">{{ agent.approved_facilities_count }}</td>
                                <td class="db-table-body-td">{{ agent.declined_applications_count }}</td>
                                <td class="db-table-body-td">{{ agent.invoices_issued_count }}</td>
                                <td class="db-table-body-td">{{ agent.signed_contracts_count }}</td>
                                <td class="db-table-body-td">{{ agent.repayments_recorded_count }}</td>
                                <td class="db-table-body-td">{{ agent.active_days_count }}</td>
                                <td class="db-table-body-td">{{ agent.period_updates_count }}</td>
                                <td class="db-table-body-td">{{ formatDateTime(agent.last_activity_at) }}</td>
                            </tr>
                            <tr v-if="isExpanded(agent.agent_id)" class="db-table-body-tr bg-gray-50">
                                <td class="db-table-body-td" colspan="16">
                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 py-3">
                                        <div class="rounded-lg border border-[#E8E8F3] p-4 bg-white">
                                            <h4 class="font-semibold text-heading mb-3">العملاء الحاليون لدى الموظف</h4>
                                            <div class="space-y-3 max-h-72 overflow-y-auto" v-if="agent.details?.assigned_customers?.length">
                                                <div v-for="customer in agent.details.assigned_customers" :key="`assigned-${customer.lead_id}`" class="rounded-lg border border-[#E8E8F3] p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <router-link class="font-semibold text-heading" :to="{ name: 'admin.customerServiceLeads.show', params: { id: customer.lead_id } }">{{ customer.customer_name || '--' }}</router-link>
                                                            <p class="text-xs text-secondary mt-1">{{ customer.phone || '--' }}</p>
                                                            <p class="text-xs text-secondary mt-1">{{ customer.address || '--' }}</p>
                                                        </div>
                                                        <div class="text-left">
                                                            <p class="text-xs text-secondary">{{ customer.status_label }}</p>
                                                            <p class="text-xs text-primary mt-1">{{ customer.pipeline_label }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-sm text-secondary" v-else>لا يوجد عملاء حاليون لهذا الموظف.</div>
                                        </div>

                                        <div class="rounded-lg border border-[#E8E8F3] p-4 bg-white">
                                            <h4 class="font-semibold text-heading mb-3">التقديمات خلال الفترة</h4>
                                            <div class="space-y-3 max-h-72 overflow-y-auto" v-if="agent.details?.submitted_customers?.length">
                                                <div v-for="application in agent.details.submitted_customers" :key="`submitted-${application.application_id}`" class="rounded-lg border border-[#E8E8F3] p-3">
                                                    <p class="font-semibold text-heading">{{ application.customer_name || application.full_name || '--' }}</p>
                                                    <p class="text-xs text-secondary mt-1">الاسم رباعي: {{ application.full_name || '--' }}</p>
                                                    <p class="text-xs text-secondary mt-1">الرقم القومي: {{ application.national_id_number || '--' }}</p>
                                                    <p class="text-xs text-secondary mt-1">تاريخ التقديم: {{ formatDateTime(application.submitted_at) }}</p>
                                                </div>
                                            </div>
                                            <div class="text-sm text-secondary" v-else>لا توجد تقديمات خلال الفترة.</div>
                                        </div>

                                        <div class="rounded-lg border border-[#E8E8F3] p-4 bg-white">
                                            <h4 class="font-semibold text-heading mb-3">العملاء المعتمدون</h4>
                                            <div class="space-y-3 max-h-72 overflow-y-auto" v-if="agent.details?.approved_customers?.length">
                                                <div v-for="approved in agent.details.approved_customers" :key="`approved-${approved.lead_id}`" class="rounded-lg border border-[#E8E8F3] p-3">
                                                    <router-link class="font-semibold text-heading" :to="{ name: 'admin.customerServiceLeads.show', params: { id: approved.lead_id } }">{{ approved.customer_name || '--' }}</router-link>
                                                    <p class="text-xs text-secondary mt-1">جهة التمويل: {{ approved.institution_name || '--' }}</p>
                                                    <p class="text-xs text-secondary mt-1">المبلغ المعتمد: {{ formatMoney(approved.approved_amount) }}</p>
                                                    <p class="text-xs text-primary mt-1">{{ approved.pipeline_label }}</p>
                                                </div>
                                            </div>
                                            <div class="text-sm text-secondary" v-else>لا يوجد عملاء معتمدون لهذا الموظف.</div>
                                        </div>

                                        <div class="rounded-lg border border-[#E8E8F3] p-4 bg-white">
                                            <h4 class="font-semibold text-heading mb-3">متابعة التحصيل</h4>
                                            <div class="space-y-3 max-h-72 overflow-y-auto" v-if="agent.details?.collection_customers?.length">
                                                <div v-for="collection in agent.details.collection_customers" :key="`collection-${collection.lead_id}`" class="rounded-lg border border-[#E8E8F3] p-3">
                                                    <router-link class="font-semibold text-heading" :to="{ name: 'admin.customerServiceLeads.show', params: { id: collection.lead_id } }">{{ collection.customer_name || '--' }}</router-link>
                                                    <p class="text-xs text-secondary mt-1">المعتمد: {{ formatMoney(collection.approved_amount) }}</p>
                                                    <p class="text-xs text-secondary mt-1">المسدد: {{ formatMoney(collection.repaid_amount) }}</p>
                                                    <p class="text-xs text-secondary mt-1">المتبقي: {{ formatMoney(collection.remaining_due) }}</p>
                                                    <p class="text-xs text-primary mt-1">{{ collection.pipeline_label }}</p>
                                                </div>
                                            </div>
                                            <div class="text-sm text-secondary" v-else>لا توجد حالات تحصيل مسجلة.</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot v-if="reports.agents?.length" class="bg-gray-50">
                        <tr class="db-table-body-tr font-semibold">
                            <td class="db-table-body-td">--</td>
                            <td class="db-table-body-td">الإجمالي</td>
                            <td class="db-table-body-td">{{ totals.period_leads_count }}</td>
                            <td class="db-table-body-td">{{ totals.not_approached_count }}</td>
                            <td class="db-table-body-td">{{ totals.callbacks_count }}</td>
                            <td class="db-table-body-td">{{ totals.waiting_documents_count }}</td>
                            <td class="db-table-body-td">{{ totals.refused_count }}</td>
                            <td class="db-table-body-td">{{ totals.period_applications_submitted_count }}</td>
                            <td class="db-table-body-td">{{ totals.approved_facilities_count }}</td>
                            <td class="db-table-body-td">{{ totals.declined_applications_count }}</td>
                            <td class="db-table-body-td">{{ totals.invoices_issued_count }}</td>
                            <td class="db-table-body-td">{{ totals.signed_contracts_count }}</td>
                            <td class="db-table-body-td">{{ totals.repayments_recorded_count }}</td>
                            <td class="db-table-body-td">{{ totals.active_days_count }}</td>
                            <td class="db-table-body-td">{{ totals.period_updates_count }}</td>
                            <td class="db-table-body-td">--</td>
                        </tr>
                    </tfoot>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="16">لا توجد بيانات لعرضها.</td>
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
            expandedAgentIds: [],
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
        totals() {
            const agents = this.reports.agents || [];
            const sum = (key) => agents.reduce((carry, item) => carry + Number(item?.[key] || 0), 0);

            return {
                period_leads_count: sum("period_leads_count"),
                not_approached_count: sum("not_approached_count"),
                callbacks_count: sum("callbacks_count"),
                waiting_documents_count: sum("waiting_documents_count"),
                refused_count: sum("refused_count"),
                period_applications_submitted_count: sum("period_applications_submitted_count"),
                approved_facilities_count: sum("approved_facilities_count"),
                declined_applications_count: sum("declined_applications_count"),
                invoices_issued_count: sum("invoices_issued_count"),
                signed_contracts_count: sum("signed_contracts_count"),
                repayments_recorded_count: sum("repayments_recorded_count"),
                active_days_count: sum("active_days_count"),
                period_updates_count: sum("period_updates_count"),
            };
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
        normalizeDateInput(value) {
            if (!value) {
                return "";
            }

            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                return value;
            }

            const slashMatch = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (slashMatch) {
                const [, month, day, year] = slashMatch;
                return `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
            }

            const dashMatch = value.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
            if (dashMatch) {
                const [, day, month, year] = dashMatch;
                return `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
            }

            const parsedDate = new Date(value);
            if (!Number.isNaN(parsedDate.getTime())) {
                const year = parsedDate.getFullYear();
                const month = String(parsedDate.getMonth() + 1).padStart(2, "0");
                const day = String(parsedDate.getDate()).padStart(2, "0");
                return `${year}-${month}-${day}`;
            }

            return "";
        },
        onDateInput(field, event) {
            this.filters[field] = event?.target?.value || "";
        },
        syncFiltersWithReport(report) {
            this.filters.date_from = this.normalizeDateInput(report?.date_from || "");
            this.filters.date_to = this.normalizeDateInput(report?.date_to || "");
        },
        fetchReports() {
            const normalizedFrom = this.normalizeDateInput(this.filters.date_from);
            const normalizedTo = this.normalizeDateInput(this.filters.date_to);
            const payload = {
                date_from: normalizedFrom || null,
                date_to: normalizedTo || null,
            };

            if (payload.date_from && !payload.date_to) {
                payload.date_to = payload.date_from;
                this.filters.date_to = payload.date_to;
            }

            if (payload.date_to && !payload.date_from) {
                payload.date_from = payload.date_to;
                this.filters.date_from = payload.date_from;
            }

            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/reports", payload)
                .then((res) => {
                    this.syncFiltersWithReport(res?.data?.data);
                })
                .finally(() => {
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
        formatMoney(value) {
            const amount = Number(value || 0);
            return `EGP ${new Intl.NumberFormat("en-US", {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(amount)}`;
        },
        formatPeriodDate(value) {
            const normalized = this.normalizeDateInput(value);
            if (!normalized) {
                return "--";
            }

            const [year, month, day] = normalized.split("-");
            return `${day}-${month}-${year}`;
        },
        redistribute() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/redistribute", {}).then((res) => {
                alertService.success(`تم توزيع ${res.data.data.assigned_count} عميل غير موزع بنجاح`);
                this.fetchReports();
            }).catch((error) => {
                alertService.error(error.response?.data?.message || "تعذر توزيع العملاء غير الموزعين");
                this.loading.isActive = false;
            });
        },
        toggleAgent(agentId) {
            if (this.isExpanded(agentId)) {
                this.expandedAgentIds = this.expandedAgentIds.filter((id) => id !== agentId);
                return;
            }

            this.expandedAgentIds = [...this.expandedAgentIds, agentId];
        },
        isExpanded(agentId) {
            return this.expandedAgentIds.includes(agentId);
        },
    },
};
</script>
