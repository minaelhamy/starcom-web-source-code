<template>
    <LoadingComponent :props="loading" />
    <div class="col-12" v-if="facility.id">
        <div class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">تفاصيل العميل المعتمد</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 lg:col-6">
                    <div class="space-y-2 text-sm">
                        <div><span class="font-semibold">العميل:</span> {{ facility.user?.name || "--" }}</div>
                        <div><span class="font-semibold">البريد:</span> {{ facility.user?.email || "--" }}</div>
                        <div><span class="font-semibold">الهاتف:</span> {{ facility.user?.phone || "--" }}</div>
                        <div><span class="font-semibold">المبلغ المعتمد:</span> {{ facility.approved_currency || "--" }}</div>
                        <div><span class="font-semibold">المتاح:</span> {{ facility.available_currency || "--" }}</div>
                        <div><span class="font-semibold">المستخدم:</span> {{ facility.utilized_currency || "--" }}</div>
                    </div>
                </div>
                <div class="col-12 lg:col-6">
                    <div class="space-y-2 text-sm">
                        <div><span class="font-semibold">الحالة:</span> {{ statusText(facility.status) }}</div>
                        <div><span class="font-semibold">بداية المدة:</span> {{ facility.starts_at || "--" }}</div>
                        <div><span class="font-semibold">تاريخ الاستحقاق:</span> {{ facility.due_at || "--" }}</div>
                        <div><span class="font-semibold">تاريخ المراجعة:</span> {{ facility.reviewed_at || "--" }}</div>
                        <div><span class="font-semibold">مدة التمويل:</span> {{ facility.duration_days || "--" }} يوم</div>
                    </div>
                </div>
                <div class="col-12 mt-4">
                    <label class="db-field-title">ملاحظات الجهة التمويلية</label>
                    <div class="db-field-control min-h-[100px] !h-auto py-3">{{ facility.notes || "لا توجد ملاحظات." }}</div>
                </div>
            </div>
        </div>

        <div class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">المستندات</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 md:col-6 xl:col-3">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">البطاقة الشخصية</h4>
                        <div class="flex flex-col gap-2">
                            <a v-if="facility.application?.national_id_front_document" :href="facility.application.national_id_front_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل الوجه الأمامي</a>
                            <a v-if="facility.application?.national_id_back_document" :href="facility.application.national_id_back_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل الوجه الخلفي</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-6 xl:col-5">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">السجل التجاري</h4>
                        <div class="flex flex-col gap-2">
                            <a v-for="(document, index) in facility.application?.commercial_register_documents || []" :key="document" :href="document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل صفحة {{ index + 1 }}</a>
                            <span v-if="!(facility.application?.commercial_register_documents || []).length" class="text-sm text-text">لا توجد ملفات مرفوعة.</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-6 xl:col-4">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">البطاقة الضريبية</h4>
                        <div class="flex flex-col gap-2">
                            <a v-if="facility.application?.tax_card_document" :href="facility.application.tax_card_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل البطاقة الضريبية</a>
                            <span v-else class="text-sm text-text">غير مرفوعة.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">Starcom Intelligence</h3>
            </div>
            <div class="px-4 pb-4">
                <p class="text-sm text-text mb-4">{{ facility.starcom_intelligence?.note }}</p>
                <div class="row">
                    <div class="col-12 md:col-6 xl:col-3" v-for="metric in intelligenceCards" :key="metric.label">
                        <div class="db-card p-4 h-full">
                            <div class="text-sm text-text mb-2">{{ metric.label }}</div>
                            <div class="text-lg font-semibold">{{ metric.value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="flex gap-2 flex-wrap">
                <button
                    v-if="canResetApproval"
                    class="db-btn py-2 text-white bg-red-500"
                    @click="resetApproval"
                >
                    إلغاء الاعتماد وإعادة الطلب للمراجعة
                </button>
                <router-link :to="{ name: 'admin.lendingPortfolio.list' }" class="db-btn py-2 text-white bg-gray-600">العودة للمحفظة التمويلية</router-link>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";
import alertService from "../../../services/alertService";
import appService from "../../../services/appService";
import roleEnum from "../../../enums/modules/roleEnum";

export default {
    name: "LendingPortfolioShowComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
        };
    },
    computed: {
        facility: function () {
            return this.$store.getters["creditApplicationReview/portfolioShow"];
        },
        intelligenceCards: function () {
            const intelligence = this.facility.starcom_intelligence || {};

            return [
                { label: "متوسط الشراء الأسبوعي من ستاركوم", value: intelligence.average_weekly_purchase_currency || "--" },
                { label: "متوسط المبيعات اليومية", value: intelligence.average_daily_sales_currency || "--" },
                { label: "متوسط المبيعات الشهرية", value: intelligence.average_monthly_sales_currency || "--" },
                { label: "إجمالي المشتريات الشهرية", value: intelligence.total_monthly_purchase_currency || "--" },
            ];
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        canResetApproval: function () {
            return this.authInfo.role_id === roleEnum.ADMIN &&
                this.facility.status === "approved" &&
                Number(this.facility.utilized_amount || 0) === 0;
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("creditApplicationReview/showFacility", this.$route.params.id).finally(() => {
            this.loading.isActive = false;
        });
    },
    methods: {
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
            return status || "--";
        },
        resetApproval: function () {
            appService.submitConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("creditApplicationReview/resetApproval", this.facility.id).then((res) => {
                    alertService.success(res.data.message || "تم إلغاء الاعتماد وإعادة الطلب إلى قائمة المراجعة.");
                    this.$router.push({ name: "admin.creditRequests.list" });
                }).catch((err) => {
                    alertService.error(err.response?.data?.message || "تعذر إلغاء الاعتماد.");
                }).finally(() => {
                    this.loading.isActive = false;
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
