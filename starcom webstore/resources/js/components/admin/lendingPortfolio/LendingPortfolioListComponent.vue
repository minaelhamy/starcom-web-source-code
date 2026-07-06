<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">المحفظة التمويلية</h3>
            </div>
            <div class="p-4 border-b border-gray-100">
                <div class="flex flex-wrap gap-2 mb-4">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        class="db-btn py-2"
                        :class="activeTab === tab.value ? 'text-white bg-primary' : 'text-primary bg-primary/10'"
                        @click="activeTab = tab.value"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">إجمالي عدد العملاء</p>
                        <h5 class="text-xl font-semibold text-heading">{{ totalCustomers }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">إجمالي المبلغ المعتمد</p>
                        <h5 class="text-xl font-semibold text-heading">{{ displayCurrency(totalApprovedAmount) }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">إجمالي المبلغ المستخدم</p>
                        <h5 class="text-xl font-semibold text-heading">{{ displayCurrency(totalUtilizedAmount) }}</h5>
                    </div>
                </div>

                <form class="flex flex-col md:flex-row gap-3 items-start md:items-end" @submit.prevent="submitSearch">
                    <div class="w-full md:flex-1">
                        <label class="db-field-title after:hidden">البحث بالاسم رباعي أو الرقم القومي أو اسم العميل أو رقم الهاتف</label>
                        <input
                            v-model="searchForm.term"
                            type="text"
                            class="db-field-control"
                            placeholder="اكتب الاسم أو الرقم القومي أو رقم الهاتف"
                        />
                    </div>
                    <div class="w-full md:w-56">
                        <label class="db-field-title after:hidden">حالة العقود</label>
                        <select v-model="searchForm.has_contracts" class="db-field-control">
                            <option value="">الكل</option>
                            <option value="1">لديه عقد مرفوع</option>
                            <option value="0">لا يوجد عقد مرفوع</option>
                        </select>
                    </div>
                    <div class="w-full md:w-56">
                        <label class="db-field-title after:hidden">حالة العقد الموقع</label>
                        <select v-model="searchForm.has_signed_contracts" class="db-field-control">
                            <option value="">الكل</option>
                            <option value="1">لديه عقد موقع مرفوع</option>
                            <option value="0">لا يوجد عقد موقع</option>
                        </select>
                    </div>
                    <div v-if="isAdminLike" class="w-full md:w-56">
                        <label class="db-field-title after:hidden">جهة التمويل</label>
                        <select v-model="searchForm.financial_institution_user_id" class="db-field-control" @change="handleInstitutionChange">
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
                    <div v-if="isAdminLike" class="w-full md:w-56">
                        <label class="db-field-title after:hidden">الموظف المسؤول</label>
                        <select v-model="searchForm.financial_institution_employee_user_id" class="db-field-control">
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
                    <div class="flex gap-2">
                        <button type="button" class="db-btn py-2 text-white bg-primary" @click="submitSearch">
                            <i class="lab lab-line-search lab-font-size-16"></i>
                            <span>بحث</span>
                        </button>
                        <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="clearSearch">
                            <i class="lab lab-line-cross lab-font-size-22"></i>
                            <span>مسح</span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('customer_name')">العميل <span>{{ sortIcon('customer_name') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('full_name')">الاسم رباعي <span>{{ sortIcon('full_name') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('national_id_number')">الرقم القومي <span>{{ sortIcon('national_id_number') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('status')">الحالة <span>{{ sortIcon('status') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('approved_amount')">المعتمد <span>{{ sortIcon('approved_amount') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('available_amount')">المتاح <span>{{ sortIcon('available_amount') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('utilized_amount')">المستخدم <span>{{ sortIcon('utilized_amount') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('institution_name')">جهة التمويل <span>{{ sortIcon('institution_name') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('employee_name')">الموظف المسؤول <span>{{ sortIcon('employee_name') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('starts_at')">بداية المدة <span>{{ sortIcon('starts_at') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('due_at')">تاريخ الاستحقاق <span>{{ sortIcon('due_at') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('updated_at')">آخر تحديث <span>{{ sortIcon('updated_at') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('last_update_label')">ما هو آخر تحديث <span>{{ sortIcon('last_update_label') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('has_contract_documents')">العقود <span>{{ sortIcon('has_contract_documents') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('has_signed_contract_documents')">العقد الموقع <span>{{ sortIcon('has_signed_contract_documents') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('file_reference')">الملف <span>{{ sortIcon('file_reference') }}</span></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="sortedTabbedPortfolio.length">
                        <tr class="db-table-body-tr" v-for="item in sortedTabbedPortfolio" :key="item.id">
                            <td class="db-table-body-td">
                                <div class="font-semibold">{{ item.user?.name || "--" }}</div>
                                <div class="text-xs text-text">{{ item.user?.phone || "" }}</div>
                            </td>
                            <td class="db-table-body-td">{{ item.full_name || "--" }}</td>
                            <td class="db-table-body-td">{{ item.national_id_number || "--" }}</td>
                            <td class="db-table-body-td">{{ statusText(item.status) }}</td>
                            <td class="db-table-body-td">{{ item.approved_currency }}</td>
                            <td class="db-table-body-td">{{ item.available_currency }}</td>
                            <td class="db-table-body-td">{{ item.utilized_currency }}</td>
                            <td class="db-table-body-td">{{ item.institution?.company_name || item.institution?.name || "--" }}</td>
                            <td class="db-table-body-td">{{ item.employee?.name || "--" }}</td>
                            <td class="db-table-body-td">{{ item.starts_at || "--" }}</td>
                            <td class="db-table-body-td">{{ item.due_at || "--" }}</td>
                            <td class="db-table-body-td">{{ item.updated_date || "--" }}</td>
                            <td class="db-table-body-td">{{ item.last_update_label || "--" }}</td>
                            <td class="db-table-body-td">
                                <span
                                    class="db-table-badge"
                                    :class="item.has_contract_documents ? 'text-green-600 bg-green-100' : 'text-red-600 bg-red-100'"
                                >
                                    {{ item.has_contract_documents ? `مرفوع (${item.contract_documents_count || 0})` : "غير مرفوع" }}
                                </span>
                            </td>
                            <td class="db-table-body-td">
                                <span
                                    class="db-table-badge"
                                    :class="item.has_signed_contract_documents ? 'text-green-600 bg-green-100' : 'text-red-600 bg-red-100'"
                                >
                                    {{ item.has_signed_contract_documents ? `مرفوع (${item.signed_contract_documents_count || 0})` : "غير مرفوع" }}
                                </span>
                            </td>
                            <td class="db-table-body-td">
                                <router-link :to="{ name: 'admin.lendingPortfolio.show', params: { id: item.id } }" class="text-primary font-semibold">فتح الملف</router-link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="16">{{ emptyStateText }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";
import appService from "../../../services/appService";
import roleEnum from "../../../enums/modules/roleEnum";

export default {
    name: "LendingPortfolioListComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            activeTab: "approved",
            searchForm: {
                term: "",
                has_contracts: "",
                has_signed_contracts: "",
                financial_institution_user_id: "",
                financial_institution_employee_user_id: "",
            },
            appliedTerm: "",
            appliedHasContracts: "",
            appliedHasSignedContracts: "",
            appliedFinancialInstitutionUserId: "",
            appliedFinancialInstitutionEmployeeUserId: "",
            sortField: "updated_at",
            sortDirection: "desc",
            tabs: [
                { value: "approved", label: "المعتمدة" },
                { value: "declined", label: "المرفوضة" },
                { value: "expired", label: "المنتهية" },
                { value: "all", label: "الكل" },
            ],
        };
    },
    computed: {
        authInfo: function () {
            return this.$store.getters.authInfo;
        },
        isAdminLike: function () {
            return this.authInfo?.role_id === roleEnum.ADMIN || this.authInfo?.role_id === roleEnum.MANAGER;
        },
        assignmentOptions: function () {
            return this.$store.getters["creditApplicationReview/assignmentOptions"] || { institutions: [], employees: [] };
        },
        institutions: function () {
            return Array.isArray(this.assignmentOptions?.institutions) ? this.assignmentOptions.institutions : [];
        },
        filteredEmployees: function () {
            const employees = Array.isArray(this.assignmentOptions?.employees) ? this.assignmentOptions.employees : [];
            const institutionId = this.searchForm.financial_institution_user_id;

            if (!institutionId) {
                return employees;
            }

            return employees.filter((employee) => String(employee.institution_owner_user_id) === String(institutionId));
        },
        portfolio: function () {
            return this.$store.getters["creditApplicationReview/portfolio"];
        },
        filteredPortfolio: function () {
            const term = this.normalizeSearchValue(this.appliedTerm);
            if (!term) {
                return this.portfolio;
            }

            return this.portfolio.filter((item) => {
                const userName = this.normalizeSearchValue(item.user?.name || "");
                const fullName = this.normalizeSearchValue(item.full_name || "");
                const nationalIdNumber = this.normalizeSearchValue(item.national_id_number || "");
                const userPhone = this.normalizeSearchValue(item.user?.phone || "");
                const userEmail = this.normalizeSearchValue(item.user?.email || "");
                const userCountryCode = this.normalizeSearchValue(item.user?.country_code || "");
                const localPhone = userPhone.startsWith("20") ? `0${userPhone.slice(2)}` : userPhone;
                const internationalPhone = `${userCountryCode}${userPhone}`;

                return userName.includes(term)
                    || fullName.includes(term)
                    || nationalIdNumber.includes(term)
                    || userPhone.includes(term)
                    || localPhone.includes(term)
                    || internationalPhone.includes(term)
                    || userEmail.includes(term);
            });
        },
        tabbedPortfolio: function () {
            if (this.activeTab === "all") {
                return this.filteredPortfolio;
            }

            return this.filteredPortfolio.filter((item) => item.status === this.activeTab);
        },
        sortedTabbedPortfolio: function () {
            const items = [...this.tabbedPortfolio];
            const direction = this.sortDirection === "asc" ? 1 : -1;

            return items.sort((firstItem, secondItem) => {
                const firstValue = this.getSortValue(firstItem, this.sortField);
                const secondValue = this.getSortValue(secondItem, this.sortField);
                const comparison = this.compareSortValues(firstValue, secondValue);

                if (comparison !== 0) {
                    return comparison * direction;
                }

                return (Number(secondItem.id) - Number(firstItem.id)) * direction;
            });
        },
        totalCustomers: function () {
            return this.sortedTabbedPortfolio.length;
        },
        totalApprovedAmount: function () {
            return this.sortedTabbedPortfolio.reduce((sum, item) => sum + Number(item.approved_amount || 0), 0);
        },
        totalUtilizedAmount: function () {
            return this.sortedTabbedPortfolio.reduce((sum, item) => sum + Number(item.utilized_amount || 0), 0);
        },
        setting: function () {
            return this.$store.getters["frontendSetting/lists"] || {};
        },
        emptyStateText: function () {
            if (this.activeTab === "declined") {
                return "لا توجد عمليات تمويل مرفوضة حالياً.";
            }

            if (this.activeTab === "expired") {
                return "لا توجد عمليات تمويل منتهية حالياً.";
            }

            if (this.activeTab === "all") {
                return "لا توجد عمليات تمويل لعرضها حالياً.";
            }

            return "لا توجد عمليات تمويل معتمدة حتى الآن.";
        },
    },
    mounted() {
        if (this.isAdminLike) {
            this.$store.dispatch("creditApplicationReview/assignmentOptions");
        }
        this.list();
    },
    methods: {
        list: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/portfolio", {
                paginate: 0,
                term: this.appliedTerm,
                has_contracts: this.appliedHasContracts,
                has_signed_contracts: this.appliedHasSignedContracts,
                financial_institution_user_id: this.appliedFinancialInstitutionUserId,
                financial_institution_employee_user_id: this.appliedFinancialInstitutionEmployeeUserId,
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        submitSearch: function () {
            this.appliedTerm = this.searchForm.term.trim();
            this.appliedHasContracts = this.searchForm.has_contracts;
            this.appliedHasSignedContracts = this.searchForm.has_signed_contracts;
            this.appliedFinancialInstitutionUserId = this.searchForm.financial_institution_user_id;
            this.appliedFinancialInstitutionEmployeeUserId = this.searchForm.financial_institution_employee_user_id;
            this.list();
        },
        clearSearch: function () {
            this.searchForm.term = "";
            this.searchForm.has_contracts = "";
            this.searchForm.has_signed_contracts = "";
            this.searchForm.financial_institution_user_id = "";
            this.searchForm.financial_institution_employee_user_id = "";
            this.appliedTerm = "";
            this.appliedHasContracts = "";
            this.appliedHasSignedContracts = "";
            this.appliedFinancialInstitutionUserId = "";
            this.appliedFinancialInstitutionEmployeeUserId = "";
            this.list();
        },
        handleInstitutionChange: function () {
            if (!this.searchForm.financial_institution_user_id) {
                this.searchForm.financial_institution_employee_user_id = "";
                return;
            }

            const employeeStillMatches = this.filteredEmployees.some((employee) => String(employee.id) === String(this.searchForm.financial_institution_employee_user_id));
            if (!employeeStillMatches) {
                this.searchForm.financial_institution_employee_user_id = "";
            }
        },
        toggleSort: function (field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
                return;
            }

            this.sortField = field;
            this.sortDirection = field === "updated_at" ? "desc" : "asc";
        },
        sortIcon: function (field) {
            if (this.sortField !== field) {
                return "↕";
            }

            return this.sortDirection === "asc" ? "↑" : "↓";
        },
        getSortValue: function (item, field) {
            const sortMap = {
                customer_name: item.user?.name || "",
                full_name: item.full_name || "",
                national_id_number: item.national_id_number || "",
                status: this.statusText(item.status),
                approved_amount: Number(item.approved_amount || 0),
                available_amount: Number(item.available_amount || 0),
                utilized_amount: Number(item.utilized_amount || 0),
                institution_name: item.institution?.company_name || item.institution?.name || "",
                employee_name: item.employee?.name || "",
                starts_at: item.starts_at || "",
                due_at: item.due_at || "",
                updated_at: item.updated_at || "",
                last_update_label: item.last_update_label || "",
                has_contract_documents: Number(Boolean(item.has_contract_documents)) * 1000 + Number(item.contract_documents_count || 0),
                has_signed_contract_documents: Number(Boolean(item.has_signed_contract_documents)) * 1000 + Number(item.signed_contract_documents_count || 0),
                file_reference: Number(item.id || 0),
            };

            return sortMap[field] ?? "";
        },
        compareSortValues: function (firstValue, secondValue) {
            if (typeof firstValue === "number" || typeof secondValue === "number") {
                return Number(firstValue || 0) - Number(secondValue || 0);
            }

            const firstDate = Date.parse(firstValue);
            const secondDate = Date.parse(secondValue);
            if (!Number.isNaN(firstDate) && !Number.isNaN(secondDate)) {
                return firstDate - secondDate;
            }

            return String(firstValue || "").localeCompare(String(secondValue || ""), "ar", {
                numeric: true,
                sensitivity: "base",
            });
        },
        normalizeSearchValue: function (value) {
            if (value === null || typeof value === "undefined") {
                return "";
            }

            const arabicDigits = "٠١٢٣٤٥٦٧٨٩";
            const englishDigits = "0123456789";

            return String(value)
                .trim()
                .toLowerCase()
                .replace(/[٠-٩]/g, (digit) => englishDigits[arabicDigits.indexOf(digit)] || digit)
                .replace(/[^\p{L}\p{N}]+/gu, "");
        },
        displayCurrency: function (rawAmount = 0) {
            const decimal = Number.isFinite(Number(this.setting.site_digit_after_decimal_point))
                ? Number(this.setting.site_digit_after_decimal_point)
                : 2;
            return `EGP ${Number(rawAmount || 0).toFixed(decimal)}`;
        },
        statusText: function (status) {
            if (status === "approved") {
                return "معتمد";
            }
            if (status === "declined") {
                return "مرفوض";
            }
            if (status === "expired") {
                return "منتهي";
            }
            return status;
        },
    },
};
</script>
