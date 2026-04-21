<template>
    <LoadingComponent :props="loading" />

    <div class="mb-9">
        <h4 class="font-semibold text-xl mb-3 text-heading">ملخص جهة التمويل</h4>
        <div class="row">
            <div class="col-12 sm:col-6 xl:col-3" v-for="card in cards" :key="card.title">
                <div :class="card.className" class="p-4 rounded-lg flex items-center gap-4 h-full">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white shrink-0">
                        <i :class="card.icon"></i>
                    </div>
                    <div>
                        <h3 class="font-medium tracking-wide text-white">{{ card.title }}</h3>
                        <h4 class="font-semibold text-[22px] leading-[34px] text-white">{{ card.value }}</h4>
                        <p class="text-white/80 text-xs">{{ card.note }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 xl:col-7">
            <div class="db-card p-5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-lg text-heading">أداء المحفظة</h4>
                        <p class="text-sm text-secondary">ملخص تمويل المحافظ ومعدل الاستخدام الحالي.</p>
                    </div>
                    <router-link :to="{ name: 'admin.lendingPortfolio' }" class="text-primary text-sm font-medium">
                        عرض المحافظ
                    </router-link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">إجمالي قيمة المحافظ</p>
                        <h5 class="text-xl font-semibold text-heading">{{ summary.wallet_value_currency || currency(0) }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">الرصيد المتاح</p>
                        <h5 class="text-xl font-semibold text-heading">{{ summary.available_wallet_value_currency || currency(0) }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">الرصيد المستخدم</p>
                        <h5 class="text-xl font-semibold text-heading">{{ summary.utilized_wallet_value_currency || currency(0) }}</h5>
                    </div>
                </div>

                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-secondary">معدل الاستخدام</p>
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
                        <h4 class="font-semibold text-lg text-heading">أفضل العملاء أداءً</h4>
                        <p class="text-sm text-secondary">العملاء الأعلى قيمة بحسب المشتريات الشهرية المقدرة من Starcom Intelligence.</p>
                    </div>
                    <router-link :to="{ name: 'admin.lendingPortfolio' }" class="text-primary text-sm font-medium">
                        فتح المحفظة
                    </router-link>
                </div>

                <div v-if="bestCustomers.length" class="space-y-3">
                    <div v-for="customer in bestCustomers" :key="customer.facility_id" class="rounded-lg border border-[#E8E8F3] p-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <h5 class="font-semibold text-heading">{{ customer.customer_name }}</h5>
                                <p class="text-sm text-secondary">{{ customer.customer_phone || 'لا يوجد رقم هاتف' }}</p>
                                <p class="text-sm text-secondary">{{ customer.customer_address || 'لا يوجد عنوان مسجل' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-secondary">المشتريات الشهرية المقدرة</p>
                                <h6 class="font-semibold text-heading">{{ customer.total_monthly_purchase_currency }}</h6>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">المبلغ المعتمد</p>
                                <p class="font-medium text-heading">{{ customer.approved_amount_currency }}</p>
                            </div>
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">المستخدم حالياً</p>
                                <p class="font-medium text-heading">{{ customer.utilized_amount_currency }}</p>
                            </div>
                            <div class="rounded-lg bg-[#F8F8FC] px-3 py-2">
                                <p class="text-xs text-secondary">الائتمان المقترح</p>
                                <p class="font-medium text-heading">{{ customer.credit_proposed_amount_currency }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-[#DADCEC] p-6 text-center text-secondary">
                    لا توجد محافظ ممولة بعد لعرض أفضل العملاء.
                </div>
            </div>
        </div>

        <div class="col-12 xl:col-5">
            <div class="db-card p-5 h-full">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-semibold text-lg text-heading">أحدث فرص التمويل</h4>
                        <p class="text-sm text-secondary">آخر الطلبات المتاحة للمراجعة واتخاذ القرار.</p>
                    </div>
                    <router-link :to="{ name: 'admin.creditRequests' }" class="text-primary text-sm font-medium">
                        عرض الطلبات
                    </router-link>
                </div>

                <div v-if="recentOpportunities.length" class="space-y-3">
                    <div v-for="opportunity in recentOpportunities" :key="opportunity.application_id" class="rounded-lg border border-[#E8E8F3] p-4">
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
                            <span class="font-semibold text-heading">{{ opportunity.credit_proposed_amount_currency }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-[#DADCEC] p-6 text-center text-secondary">
                    لا توجد طلبات تمويل متاحة حالياً.
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import appService from "../../../services/appService";

export default {
    name: "LenderDashboardComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            summary: {},
        };
    },
    computed: {
        cards: function () {
            return [
                {
                    title: "فرص التمويل",
                    value: this.summary.opportunities_count ?? 0,
                    note: "عدد الطلبات المتاحة الآن للمراجعة",
                    className: "bg-admin-orange",
                    icon: "lab-fill-document text-admin-orange text-2xl lab-font-size-24",
                },
                {
                    title: "العملاء الممولون",
                    value: this.summary.active_customers_count ?? 0,
                    note: "عملاء لديهم محافظ معتمدة من جهتك",
                    className: "bg-admin-purple",
                    icon: "lab-fill-users text-admin-purple text-2xl lab-font-size-24",
                },
                {
                    title: "قيمة المحافظ",
                    value: this.summary.wallet_value_currency || this.currency(0),
                    note: "إجمالي حدود التمويل المعتمدة",
                    className: "bg-admin-pink",
                    icon: "lab-fill-wallet text-admin-pink text-2xl lab-font-size-24",
                },
                {
                    title: "طلبات تمت مراجعتها",
                    value: this.summary.reviewed_requests_count ?? 0,
                    note: `تم رفض ${this.summary.declined_requests_count ?? 0} طلب حتى الآن`,
                    className: "bg-admin-blue",
                    icon: "lab-fill-box text-admin-blue text-2xl lab-font-size-24",
                },
            ];
        },
        bestCustomers: function () {
            return this.summary.best_performing_customers || [];
        },
        recentOpportunities: function () {
            return this.summary.recent_opportunities || [];
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
            this.$store.dispatch("dashboard/lenderSummary").then((res) => {
                this.summary = res.data.data || {};
                this.loading.isActive = false;
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        currency: function (amount) {
            return appService.currencyFormat(amount);
        },
    },
};
</script>
