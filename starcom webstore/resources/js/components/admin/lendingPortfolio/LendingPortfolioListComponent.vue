<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">المحفظة التمويلية</h3>
            </div>
            <div class="p-4 border-b border-gray-100">
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
                        <label class="db-field-title after:hidden">البحث باسم العميل أو رقم الهاتف</label>
                        <input
                            v-model="searchForm.term"
                            type="text"
                            class="db-field-control"
                            placeholder="اكتب اسم العميل أو رقم الهاتف"
                        />
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
                            <th class="db-table-head-th">الحالة</th>
                            <th class="db-table-head-th">المعتمد</th>
                            <th class="db-table-head-th">المتاح</th>
                            <th class="db-table-head-th">المستخدم</th>
                            <th class="db-table-head-th">جهة التمويل</th>
                            <th class="db-table-head-th">الموظف المسؤول</th>
                            <th class="db-table-head-th">بداية المدة</th>
                            <th class="db-table-head-th">تاريخ الاستحقاق</th>
                            <th class="db-table-head-th">الملف</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="filteredPortfolio.length">
                        <tr class="db-table-body-tr" v-for="item in filteredPortfolio" :key="item.id">
                            <td class="db-table-body-td">
                                <div class="font-semibold">{{ item.user?.name || "--" }}</div>
                                <div class="text-xs text-text">{{ item.user?.phone || "" }}</div>
                            </td>
                            <td class="db-table-body-td">{{ statusText(item.status) }}</td>
                            <td class="db-table-body-td">{{ item.approved_currency }}</td>
                            <td class="db-table-body-td">{{ item.available_currency }}</td>
                            <td class="db-table-body-td">{{ item.utilized_currency }}</td>
                            <td class="db-table-body-td">{{ item.institution?.company_name || item.institution?.name || "--" }}</td>
                            <td class="db-table-body-td">{{ item.employee?.name || "--" }}</td>
                            <td class="db-table-body-td">{{ item.starts_at || "--" }}</td>
                            <td class="db-table-body-td">{{ item.due_at || "--" }}</td>
                            <td class="db-table-body-td">
                                <router-link :to="{ name: 'admin.lendingPortfolio.show', params: { id: item.id } }" class="text-primary font-semibold">فتح الملف</router-link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="10">لا توجد عمليات تمويل معتمدة حتى الآن.</td>
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
            searchForm: {
                term: "",
            },
            appliedTerm: "",
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
                const userPhone = this.normalizeSearchValue(item.user?.phone || "");
                const userEmail = this.normalizeSearchValue(item.user?.email || "");
                const userCountryCode = this.normalizeSearchValue(item.user?.country_code || "");
                const localPhone = userPhone.startsWith("20") ? `0${userPhone.slice(2)}` : userPhone;
                const internationalPhone = `${userCountryCode}${userPhone}`;

                return userName.includes(term)
                    || userPhone.includes(term)
                    || localPhone.includes(term)
                    || internationalPhone.includes(term)
                    || userEmail.includes(term);
            });
        },
        totalCustomers: function () {
            return this.filteredPortfolio.length;
        },
        totalApprovedAmount: function () {
            return this.filteredPortfolio.reduce((sum, item) => sum + Number(item.approved_amount || 0), 0);
        },
        totalUtilizedAmount: function () {
            return this.filteredPortfolio.reduce((sum, item) => sum + Number(item.utilized_amount || 0), 0);
        },
        setting: function () {
            return this.$store.getters["frontendSetting/lists"] || {};
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
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        submitSearch: function () {
            this.appliedTerm = this.searchForm.term.trim();
            this.list();
        },
        clearSearch: function () {
            this.searchForm.term = "";
            this.appliedTerm = "";
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
