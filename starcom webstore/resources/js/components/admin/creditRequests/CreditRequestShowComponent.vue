<template>
    <LoadingComponent :props="loading" />
    <div class="col-12" v-if="application.id">
        <div class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">تفاصيل طلب اشتري بالآجل</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 lg:col-6">
                    <div class="space-y-2 text-sm">
                        <div><span class="font-semibold">العميل:</span> {{ application.user?.name || "--" }}</div>
                        <div><span class="font-semibold">البريد:</span> {{ application.user?.email || "--" }}</div>
                        <div><span class="font-semibold">الهاتف:</span> {{ application.user?.phone || "--" }}</div>
                        <div><span class="font-semibold">المحفظة الحالية:</span> {{ application.user?.wallet_balance || "--" }}</div>
                        <div><span class="font-semibold">حالة الطلب:</span> {{ statusText(application.status) }}</div>
                        <div><span class="font-semibold">تاريخ الطلب:</span> {{ application.created_date || "--" }}</div>
                    </div>
                </div>
                <div class="col-12 lg:col-6">
                    <label class="db-field-title">ملاحظات العميل</label>
                    <div class="db-field-control min-h-[120px] !h-auto py-3">{{ application.notes || "لا توجد ملاحظات." }}</div>
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
                            <a v-if="application.national_id_front_document" :href="application.national_id_front_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل الوجه الأمامي</a>
                            <a v-if="application.national_id_back_document" :href="application.national_id_back_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل الوجه الخلفي</a>
                            <span v-if="!application.national_id_front_document && !application.national_id_back_document" class="text-sm text-text">لا توجد ملفات مرفوعة.</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-6 xl:col-5">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">السجل التجاري</h4>
                        <div class="flex flex-col gap-2">
                            <a v-for="(document, index) in application.commercial_register_documents || []" :key="document" :href="document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل صفحة {{ index + 1 }}</a>
                            <span v-if="!(application.commercial_register_documents || []).length" class="text-sm text-text">لا توجد ملفات مرفوعة.</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-6 xl:col-4">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">البطاقة الضريبية</h4>
                        <div class="flex flex-col gap-2">
                            <a v-if="application.tax_card_document" :href="application.tax_card_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل البطاقة الضريبية</a>
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
                <p class="text-sm text-text mb-4">{{ application.starcom_intelligence?.note }}</p>
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

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">قرار الجهة التمويلية</h3>
            </div>
            <div class="p-4">
                <div v-if="application.reviewed_by_me" class="text-sm text-text">
                    تم اتخاذ القرار من حسابك بالفعل على هذا الطلب.
                </div>
                <div v-else class="row">
                    <div class="col-12 md:col-4">
                        <label class="db-field-title required">المبلغ المعتمد</label>
                        <input v-model="form.approved_amount" type="number" min="1" step="0.01" class="db-field-control" />
                    </div>
                    <div class="col-12 md:col-4">
                        <label class="db-field-title required">المدة بالأيام</label>
                        <input v-model="form.duration_days" type="number" min="30" class="db-field-control" />
                    </div>
                    <div class="col-12 md:col-4">
                        <label class="db-field-title">ملاحظات</label>
                        <input v-model="form.notes" type="text" class="db-field-control" />
                    </div>
                    <div class="col-12 mt-2">
                        <div class="flex gap-2 flex-wrap">
                            <button class="db-btn py-2 text-white bg-primary" @click="approve">اعتماد الطلب</button>
                            <button class="db-btn py-2 text-white bg-red-500" @click="decline">رفض الطلب</button>
                            <router-link :to="{ name: 'admin.creditRequests.list' }" class="db-btn py-2 text-white bg-gray-600">العودة للطلبات</router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";
import alertService from "../../../services/alertService";

export default {
    name: "CreditRequestShowComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: {
                approved_amount: "",
                duration_days: 30,
                notes: "",
                decline_reason: "",
            },
        };
    },
    computed: {
        application: function () {
            return this.$store.getters["creditApplicationReview/show"];
        },
        intelligenceCards: function () {
            const intelligence = this.application.starcom_intelligence || {};

            return [
                { label: "متوسط الشراء الأسبوعي من ستاركوم", value: intelligence.average_weekly_purchase_currency || "--" },
                { label: "متوسط المبيعات اليومية", value: intelligence.average_daily_sales_currency || "--" },
                { label: "متوسط المبيعات الشهرية", value: intelligence.average_monthly_sales_currency || "--" },
                { label: "إجمالي المشتريات الشهرية", value: intelligence.total_monthly_purchase_currency || "--" },
            ];
        },
    },
    mounted() {
        this.fetch();
    },
    methods: {
        fetch: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/show", this.$route.params.id).finally(() => {
                this.loading.isActive = false;
            });
        },
        approve: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/approve", {
                id: this.application.id,
                form: this.form,
            }).then((res) => {
                alertService.success(res.data.message || "تم اعتماد الرصيد وإضافته إلى المحفظة.");
                this.$router.push({ name: "admin.lendingPortfolio.list" });
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر اعتماد الطلب.");
            });
        },
        decline: function () {
            this.loading.isActive = true;
            const payload = {
                ...this.form,
                decline_reason: this.form.notes || "تم رفض الطلب بعد المراجعة.",
            };
            this.$store.dispatch("creditApplicationReview/decline", {
                id: this.application.id,
                form: payload,
            }).then((res) => {
                alertService.success(res.data.message || "تم رفض الطلب.");
                this.$router.push({ name: "admin.creditRequests.list" });
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر رفض الطلب.");
            });
        },
        statusText: function (status) {
            if (status === "approved") {
                return "تمت الموافقة";
            }
            if (status === "declined") {
                return "مرفوض";
            }
            return "قيد المراجعة";
        },
    },
};
</script>
