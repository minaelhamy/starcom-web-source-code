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
                            <th class="db-table-head-th">العميل</th>
                            <th class="db-table-head-th">الاسم رباعي</th>
                            <th class="db-table-head-th">الرقم القومي</th>
                            <th class="db-table-head-th">الحالة</th>
                            <th class="db-table-head-th">المعتمد</th>
                            <th class="db-table-head-th">المتاح</th>
                            <th class="db-table-head-th">المستخدم</th>
                            <th class="db-table-head-th">جهة التمويل</th>
                            <th class="db-table-head-th">الموظف المسؤول</th>
                            <th class="db-table-head-th">بداية المدة</th>
                            <th class="db-table-head-th">تاريخ الاستحقاق</th>
                            <th class="db-table-head-th">العقود</th>
                            <th class="db-table-head-th">العقد الموقع</th>
                            <th class="db-table-head-th">الملف</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="tabbedPortfolio.length">
                        <tr class="db-table-body-tr" v-for="item in tabbedPortfolio" :key="item.id">
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
                            <td class="db-table-body-td text-center" colspan="14">{{ emptyStateText }}</td>
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
            },
            appliedTerm: "",
            appliedHasContracts: "",
            appliedHasSignedContracts: "",
            tabs: [
                { value: "approved", label: "المعتمدة" },
                { value: "declined", label: "المرفوضة" },
                { value: "expired", label: "المنتهية" },
                { value: "all", label: "الكل" },
            ],
        };
    },
    computed: {
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
        totalCustomers: function () {
            return this.tabbedPortfolio.length;
        },
        totalApprovedAmount: function () {
            return this.tabbedPortfolio.reduce((sum, item) => sum + Number(item.approved_amount || 0), 0);
        },
        totalUtilizedAmount: function () {
            return this.tabbedPortfolio.reduce((sum, item) => sum + Number(item.utilized_amount || 0), 0);
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
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        submitSearch: function () {
            this.appliedTerm = this.searchForm.term.trim();
            this.appliedHasContracts = this.searchForm.has_contracts;
            this.appliedHasSignedContracts = this.searchForm.has_signed_contracts;
            this.list();
        },
        clearSearch: function () {
            this.searchForm.term = "";
            this.searchForm.has_contracts = "";
            this.searchForm.has_signed_contracts = "";
            this.appliedTerm = "";
            this.appliedHasContracts = "";
            this.appliedHasSignedContracts = "";
            this.list();
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
