<template>
    <LoadingComponent :props="loading" />

    <div class="mb-9">
        <h4 class="font-semibold text-xl mb-3 text-heading">متابعة التمويل والإعتمادات</h4>
        <div class="row">
            <div class="col-12 sm:col-6 xl:col-3" v-for="card in cards" :key="card.title">
                <div :class="card.className" class="p-4 rounded-lg flex items-center gap-4 h-full">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white shrink-0">
                        <i :class="card.icon"></i>
                    </div>
                    <div>
                        <h3 class="font-medium tracking-wide text-white">{{ card.title }}</h3>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-white">{{ card.displayValue }}</h4>
                        <p class="text-white/80 text-xs">{{ card.note }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-6">
        <div class="col-12">
            <div class="db-card p-5 mb-6">
                <div class="flex flex-col md:flex-row gap-3 items-start md:items-end">
                    <div class="w-full md:flex-1">
                        <label class="db-field-title after:hidden">البحث باسم العميل أو رقم الهاتف</label>
                        <input
                            v-model="searchForm.term"
                            type="text"
                            class="db-field-control"
                            placeholder="ابحث داخل أحدث العملاء المعتمدين وأحدث فرص التمويل"
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
                </div>
            </div>
        </div>
        <div class="col-12 xl:col-7">
            <div class="db-card p-5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-lg text-heading">أداء المحفظة التمويلية</h4>
                        <p class="text-sm text-secondary">نظرة مجمعة على إجمالي الاعتمادات والمتاح والمستخدم عبر كل الجهات التمويلية.</p>
                    </div>
                    <router-link :to="{ name: 'admin.lendingPortfolio.list' }" class="text-primary text-sm font-medium">
                        فتح المحافظ
                    </router-link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">إجمالي الاعتمادات</p>
                        <h5 class="text-xl font-semibold text-heading">{{ summary.wallet_value_currency || displayCurrency(summary.wallet_value) }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">الرصيد المتاح</p>
                        <h5 class="text-xl font-semibold text-heading">{{ summary.available_wallet_value_currency || displayCurrency(summary.available_wallet_value) }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">الرصيد المستخدم</p>
                        <h5 class="text-xl font-semibold text-heading">{{ summary.utilized_wallet_value_currency || displayCurrency(summary.utilized_wallet_value) }}</h5>
                    </div>
                </div>

                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-secondary">معدل الاستخدام الكلي</p>
                        <span class="text-sm font-semibold text-heading">{{ utilizationLabel }}</span>
                    </div>
                    <div class="w-full h-3 rounded-full bg-[#F3F4FA] overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-300" :style="{ width: `${safeUtilizationRate}%` }"></div>
                    </div>
                </div>
            </div>

            <div class="db-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-lg text-heading">أفضل الجهات التمويلية أداءً</h4>
                        <p class="text-sm text-secondary">مرتبة حسب الاستخدام الفعلي وحجم المحفظة وعدد العملاء الممولين.</p>
                    </div>
                    <router-link :to="{ name: 'admin.financialInstitutions.list' }" class="text-primary text-sm font-medium">
                        إدارة الجهات
                    </router-link>
                </div>

                <div v-if="topInstitutions.length" class="space-y-3">
                    <div v-for="institution in topInstitutions" :key="institution.institution_id" class="rounded-lg border border-[#E8E8F3] p-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <h5 class="font-semibold text-heading">{{ institution.institution_company_name }}</h5>
                                <p class="text-sm text-secondary">{{ institution.active_customers_count }} عميل ممول</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-secondary">إجمالي الاعتماد</p>
                                <h6 class="font-semibold text-heading">{{ institution.approved_amount_currency }}</h6>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">محافظ معتمدة</p>
                                <p class="font-medium text-heading">{{ institution.approved_facilities_count }}</p>
                            </div>
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">المستخدم</p>
                                <p class="font-medium text-heading">{{ institution.utilized_amount_currency }}</p>
                            </div>
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">المتاح</p>
                                <p class="font-medium text-heading">{{ institution.available_amount_currency }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-[#DADCEC] p-6 text-center text-secondary">
                    لا توجد جهات تمويل لديها محافظ معتمدة حتى الآن.
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-5">
            <div class="db-card p-5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-lg text-heading">أحدث العملاء المعتمدين</h4>
                        <p class="text-sm text-secondary">آخر الاعتمادات التي تمت الموافقة عليها مع الجهة والموظف المسؤول.</p>
                    </div>
                    <router-link :to="{ name: 'admin.lendingPortfolio.list' }" class="text-primary text-sm font-medium">
                        فتح المحفظة
                    </router-link>
                </div>

                <div v-if="filteredLatestApprovedClients.length" class="space-y-3">
                    <div v-for="client in filteredLatestApprovedClients" :key="client.facility_id" class="rounded-lg border border-[#E8E8F3] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h5 class="font-semibold text-heading">{{ client.customer_name }}</h5>
                                <p class="text-sm text-secondary">{{ client.customer_phone || 'لا يوجد رقم هاتف' }}</p>
                                <p class="text-sm text-secondary">{{ client.institution_name || 'جهة غير محددة' }}</p>
                                <p class="text-sm text-secondary">الموظف: {{ client.employee_name || 'غير محدد' }}</p>
                            </div>
                            <router-link
                                :to="{ name: 'admin.lendingPortfolio.show', params: { id: client.facility_id } }"
                                class="text-primary text-sm font-medium shrink-0"
                            >
                                فتح الملف
                            </router-link>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">المبلغ المعتمد</p>
                                <p class="font-medium text-heading">{{ client.approved_amount_currency }}</p>
                            </div>
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">المستخدم</p>
                                <p class="font-medium text-heading">{{ client.utilized_amount_currency }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-[#DADCEC] p-6 text-center text-secondary">
                    لا توجد اعتمادات معتمدة حديثة لعرضها.
                </div>
            </div>

            <div class="db-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-lg text-heading">أحدث فرص التمويل المفتوحة</h4>
                        <p class="text-sm text-secondary">طلبات قيد المراجعة بدون اعتماد نهائي حتى الآن.</p>
                    </div>
                    <router-link :to="{ name: 'admin.creditRequests.list' }" class="text-primary text-sm font-medium">
                        عرض الطلبات
                    </router-link>
                </div>

                <div v-if="filteredRecentOpportunities.length" class="space-y-3">
                    <div v-for="opportunity in filteredRecentOpportunities" :key="opportunity.application_id" class="rounded-lg border border-[#E8E8F3] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h5 class="font-semibold text-heading">{{ opportunity.customer_name }}</h5>
                                <p class="text-sm text-secondary">{{ opportunity.customer_phone || 'لا يوجد رقم هاتف' }}</p>
                                <p class="text-sm text-secondary">{{ opportunity.customer_address || 'لا يوجد عنوان مسجل' }}</p>
                            </div>
                            <router-link
                                :to="{ name: 'admin.creditRequests.show', params: { id: opportunity.application_id } }"
                                class="text-primary text-sm font-medium shrink-0"
                            >
                                فتح الملف
                            </router-link>
                        </div>

                        <div class="flex items-center justify-between mt-4 text-sm">
                            <span class="text-secondary">{{ opportunity.created_date || 'طلب جديد' }}</span>
                            <span class="font-semibold text-heading">{{ opportunity.average_monthly_purchase_last_12_months_currency }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-[#DADCEC] p-6 text-center text-secondary">
                    لا توجد طلبات تمويل مفتوحة حالياً.
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import appService from "../../../services/appService";

export default {
    name: "AdminCreditFacilitiesDashboardComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            summary: {},
            searchForm: {
                term: "",
            },
            appliedTerm: "",
        };
    },
    computed: {
        setting: function () {
            return this.$store.getters["frontendSetting/lists"] || {};
        },
        cards: function () {
            return [
                {
                    title: "فرص التمويل المفتوحة",
                    displayValue: this.summary.opportunities_count ?? 0,
                    note: "طلبات بانتظار اعتماد جهة تمويل",
                    className: "bg-admin-orange",
                    icon: "lab-fill-document text-admin-orange text-2xl lab-font-size-24",
                },
                {
                    title: "العملاء الممولون",
                    displayValue: this.summary.active_customers_count ?? 0,
                    note: "عملاء لديهم اعتمادات فعالة",
                    className: "bg-admin-purple",
                    icon: "lab-fill-users text-admin-purple text-2xl lab-font-size-24",
                },
                {
                    title: "المحافظ المعتمدة",
                    displayValue: this.summary.approved_facilities_count ?? 0,
                    note: `منها ${this.summary.expired_facilities_count ?? 0} منتهية`,
                    className: "bg-admin-pink",
                    icon: "lab-fill-wallet text-admin-pink text-2xl lab-font-size-24",
                },
                {
                    title: "الجهات والموظفون",
                    displayValue: `${this.summary.institutions_count ?? 0} / ${this.summary.employees_count ?? 0}`,
                    note: "عدد الجهات التمويلية مقابل عدد الموظفين المرتبطين بها",
                    className: "bg-admin-blue",
                    icon: "lab-fill-box text-admin-blue text-2xl lab-font-size-24",
                },
            ];
        },
        topInstitutions: function () {
            return this.summary.top_institutions || [];
        },
        latestApprovedClients: function () {
            return this.summary.latest_approved_clients || [];
        },
        recentOpportunities: function () {
            return this.summary.recent_opportunities || [];
        },
        filteredLatestApprovedClients: function () {
            const term = this.normalizeSearchValue(this.appliedTerm);
            if (!term) {
                return this.latestApprovedClients;
            }

            return this.latestApprovedClients.filter((client) => {
                const name = this.normalizeSearchValue(client.customer_name || "");
                const phone = this.normalizeSearchValue(client.customer_phone || "");
                const localPhone = phone.startsWith("20") ? `0${phone.slice(2)}` : phone;

                return name.includes(term) || phone.includes(term) || localPhone.includes(term);
            });
        },
        filteredRecentOpportunities: function () {
            const term = this.normalizeSearchValue(this.appliedTerm);
            if (!term) {
                return this.recentOpportunities;
            }

            return this.recentOpportunities.filter((opportunity) => {
                const name = this.normalizeSearchValue(opportunity.customer_name || "");
                const phone = this.normalizeSearchValue(opportunity.customer_phone || "");
                const localPhone = phone.startsWith("20") ? `0${phone.slice(2)}` : phone;

                return name.includes(term) || phone.includes(term) || localPhone.includes(term);
            });
        },
        safeUtilizationRate: function () {
            const value = Number(this.summary.utilization_rate || 0);
            return Math.max(0, Math.min(100, value));
        },
        utilizationLabel: function () {
            return `${this.safeUtilizationRate.toFixed(2)}%`;
        },
    },
    mounted() {
        this.fetchSummary();
    },
    methods: {
        fetchSummary: function () {
            this.loading.isActive = true;
            this.$store.dispatch("dashboard/adminCreditFacilitiesSummary").then((res) => {
                this.summary = res.data.data || {};
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        displayCurrency: function (rawAmount = 0) {
            const decimal = Number.isFinite(Number(this.setting.site_digit_after_decimal_point))
                ? Number(this.setting.site_digit_after_decimal_point)
                : 2;
            const symbol = this.setting.site_default_currency_symbol || "";
            const position = this.setting.site_currency_position;

            if (symbol && position !== undefined && position !== null && position !== "") {
                return appService.currencyFormat(rawAmount || 0, decimal, symbol, position);
            }

            return Number(rawAmount || 0).toFixed(decimal);
        },
        submitSearch: function () {
            this.appliedTerm = this.searchForm.term.trim();
        },
        clearSearch: function () {
            this.searchForm.term = "";
            this.appliedTerm = "";
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
    },
};
</script>
