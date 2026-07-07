<template>
    <LoadingComponent :props="loading" />
    <div class="row">
        <div class="col-12">
            <BreadcrumbComponent />
        </div>

        <div class="col-12">
            <div class="db-card mb-4">
                <div class="db-card-header border-none">
                    <h3 class="db-card-title">تقرير التحصيل والسداد</h3>
                </div>
                <div class="p-4">
                    <form class="row" @submit.prevent="list">
                        <div class="col-12 md:col-6 xl:col-3">
                            <label class="db-field-title after:hidden">بحث</label>
                            <input
                                v-model="filters.term"
                                type="text"
                                class="db-field-control"
                                placeholder="اسم العميل أو الرقم القومي أو الهاتف أو الجهة"
                            />
                        </div>
                        <div class="col-12 md:col-6 xl:col-2">
                            <label class="db-field-title after:hidden">من تاريخ</label>
                            <input v-model="filters.date_from" type="date" class="db-field-control" />
                        </div>
                        <div class="col-12 md:col-6 xl:col-2">
                            <label class="db-field-title after:hidden">إلى تاريخ</label>
                            <input v-model="filters.date_to" type="date" class="db-field-control" />
                        </div>
                        <div class="col-12 md:col-6 xl:col-2">
                            <label class="db-field-title after:hidden">طريقة السداد</label>
                            <input v-model="filters.payment_method" type="text" class="db-field-control" placeholder="نقدي، تحويل..." />
                        </div>
                        <div v-if="isAdminLike" class="col-12 md:col-6 xl:col-3">
                            <label class="db-field-title after:hidden">جهة التمويل</label>
                            <select v-model="filters.financial_institution_user_id" class="db-field-control" @change="handleInstitutionChange">
                                <option value="">الكل</option>
                                <option
                                    v-for="institution in institutions"
                                    :key="institution.id"
                                    :value="String(institution.id)"
                                >
                                    {{ institution.company_name || institution.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="isAdminLike" class="col-12 md:col-6 xl:col-3">
                            <label class="db-field-title after:hidden">الموظف المسؤول</label>
                            <select v-model="filters.financial_institution_employee_user_id" class="db-field-control">
                                <option value="">الكل</option>
                                <option
                                    v-for="employee in filteredEmployees"
                                    :key="employee.id"
                                    :value="String(employee.id)"
                                >
                                    {{ employee.name }}
                                </option>
                            </select>
                        </div>
                        <div class="col-12 mt-4">
                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="db-btn py-2 text-white bg-primary">
                                    <i class="lab lab-line-search lab-font-size-16"></i>
                                    <span>تحديث التقرير</span>
                                </button>
                                <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="clearFilters">
                                    <i class="lab lab-line-cross lab-font-size-22"></i>
                                    <span>مسح</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
                <div class="db-card p-4">
                    <div class="text-sm text-text mb-1">إجمالي عمليات السداد</div>
                    <div class="text-2xl font-semibold">{{ summary.total_repayments_count || 0 }}</div>
                </div>
                <div class="db-card p-4">
                    <div class="text-sm text-text mb-1">إجمالي المبلغ المحصل</div>
                    <div class="text-2xl font-semibold">{{ currency(summary.total_repaid_amount || 0) }}</div>
                </div>
                <div class="db-card p-4">
                    <div class="text-sm text-text mb-1">عدد العملاء</div>
                    <div class="text-2xl font-semibold">{{ summary.total_customers_count || 0 }}</div>
                </div>
                <div class="db-card p-4">
                    <div class="text-sm text-text mb-1">تمويلات مسددة بالكامل</div>
                    <div class="text-2xl font-semibold">{{ summary.fully_settled_count || 0 }}</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <h3 class="db-card-title">سجل السداد</h3>
                    <div class="db-card-filter">
                        <TableLimitComponent :method="list" :search="filters" :page="paginationPage" />
                    </div>
                </div>
                <div class="db-table-responsive">
                    <table class="db-table">
                        <thead class="db-table-head">
                            <tr class="db-table-head-tr">
                                <th class="db-table-head-th">تاريخ السداد</th>
                                <th class="db-table-head-th">العميل</th>
                                <th class="db-table-head-th">الاسم رباعي</th>
                                <th class="db-table-head-th">الرقم القومي</th>
                                <th class="db-table-head-th">الهاتف</th>
                                <th class="db-table-head-th">جهة التمويل</th>
                                <th class="db-table-head-th">الموظف</th>
                                <th class="db-table-head-th">المبلغ</th>
                                <th class="db-table-head-th">طريقة السداد</th>
                                <th class="db-table-head-th">المرجع</th>
                                <th class="db-table-head-th">حالة التمويل</th>
                                <th class="db-table-head-th">الملف</th>
                            </tr>
                        </thead>
                        <tbody class="db-table-body" v-if="reports.length">
                            <tr class="db-table-body-tr" v-for="report in reports" :key="report.id">
                                <td class="db-table-body-td">{{ report.paid_date || "--" }}</td>
                                <td class="db-table-body-td">
                                    <div class="font-semibold">{{ report.customer?.name || "--" }}</div>
                                    <div class="text-xs text-text">{{ report.customer?.address || "" }}</div>
                                </td>
                                <td class="db-table-body-td">{{ report.customer?.full_name || "--" }}</td>
                                <td class="db-table-body-td">{{ report.customer?.national_id_number || "--" }}</td>
                                <td class="db-table-body-td">{{ report.customer?.phone || "--" }}</td>
                                <td class="db-table-body-td">{{ report.institution?.company_name || "--" }}</td>
                                <td class="db-table-body-td">{{ report.employee?.name || "--" }}</td>
                                <td class="db-table-body-td">{{ report.amount_currency || "--" }}</td>
                                <td class="db-table-body-td">{{ report.payment_method || "--" }}</td>
                                <td class="db-table-body-td">{{ report.reference_number || "--" }}</td>
                                <td class="db-table-body-td">{{ report.facility_status_label || "--" }}</td>
                                <td class="db-table-body-td">
                                    <router-link
                                        :to="{ name: 'admin.lendingPortfolio.show', params: { id: report.facility_id } }"
                                        class="text-primary font-semibold"
                                    >
                                        فتح الملف
                                    </router-link>
                                </td>
                            </tr>
                        </tbody>
                        <tbody class="db-table-body" v-else>
                            <tr class="db-table-body-tr">
                                <td class="db-table-body-td text-center" colspan="12">لا توجد عمليات سداد مطابقة للفلاتر الحالية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-6" v-if="reports.length">
                    <PaginationSMBox :pagination="pagination" :method="list" />
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <PaginationTextComponent :props="{ page: paginationPage }" />
                        <PaginationBox :pagination="pagination" :method="list" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import BreadcrumbComponent from "../components/BreadcrumbComponent";
import LoadingComponent from "../components/LoadingComponent";
import PaginationTextComponent from "../components/pagination/PaginationTextComponent";
import PaginationBox from "../components/pagination/PaginationBox";
import PaginationSMBox from "../components/pagination/PaginationSMBox";
import TableLimitComponent from "../components/TableLimitComponent";
import roleEnum from "../../../enums/modules/roleEnum";

export default {
    name: "PaymentCollectionReportComponent",
    components: {
        BreadcrumbComponent,
        LoadingComponent,
        PaginationTextComponent,
        PaginationBox,
        PaginationSMBox,
        TableLimitComponent,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            filters: {
                paginate: 1,
                page: 1,
                per_page: 10,
                term: "",
                date_from: "",
                date_to: "",
                payment_method: "",
                financial_institution_user_id: "",
                financial_institution_employee_user_id: "",
            },
        };
    },
    computed: {
        reports() {
            return this.$store.getters["paymentCollectionReport/lists"];
        },
        pagination() {
            return this.$store.getters["paymentCollectionReport/pagination"];
        },
        paginationPage() {
            return this.$store.getters["paymentCollectionReport/page"];
        },
        summary() {
            return this.$store.getters["paymentCollectionReport/summary"] || {};
        },
        authInfo() {
            return this.$store.getters.authInfo || {};
        },
        isAdminLike() {
            return this.authInfo.role_id === roleEnum.ADMIN || this.authInfo.role_id === roleEnum.MANAGER;
        },
        assignmentOptions() {
            return this.$store.getters["creditApplicationReview/assignmentOptions"] || { institutions: [], employees: [] };
        },
        institutions() {
            return this.assignmentOptions.institutions || [];
        },
        filteredEmployees() {
            const institutionId = this.filters.financial_institution_user_id;
            const employees = this.assignmentOptions.employees || [];

            if (!institutionId) {
                return employees;
            }

            return employees.filter((employee) => String(employee.institution_owner_user_id) === String(institutionId));
        },
        setting() {
            return this.$store.getters["frontendSetting/lists"] || {};
        },
    },
    mounted() {
        if (this.isAdminLike) {
            this.$store.dispatch("creditApplicationReview/assignmentOptions");
        }
        this.list();
    },
    methods: {
        list(page = 1) {
            this.loading.isActive = true;
            this.filters.page = page;
            this.$store.dispatch("paymentCollectionReport/lists", this.filters).finally(() => {
                this.loading.isActive = false;
            });
        },
        clearFilters() {
            this.filters.page = 1;
            this.filters.term = "";
            this.filters.date_from = "";
            this.filters.date_to = "";
            this.filters.payment_method = "";
            this.filters.financial_institution_user_id = "";
            this.filters.financial_institution_employee_user_id = "";
            this.list();
        },
        handleInstitutionChange() {
            const exists = this.filteredEmployees.some((employee) => String(employee.id) === String(this.filters.financial_institution_employee_user_id));
            if (!exists) {
                this.filters.financial_institution_employee_user_id = "";
            }
        },
        currency(amount = 0) {
            const decimal = Number.isFinite(Number(this.setting.site_digit_after_decimal_point))
                ? Number(this.setting.site_digit_after_decimal_point)
                : 2;
            return `EGP ${Number(amount || 0).toFixed(decimal)}`;
        },
    },
};
</script>
