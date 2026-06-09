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
                        <div><span class="font-semibold">الاسم رباعي:</span> {{ application.full_name || "--" }}</div>
                        <div><span class="font-semibold">الرقم القومي:</span> {{ application.national_id_number || "--" }}</div>
                        <div><span class="font-semibold">العنوان:</span> {{ application.user?.address || "--" }}</div>
                        <div><span class="font-semibold">الهاتف:</span> {{ application.user?.phone || "--" }}</div>
                        <div v-if="canViewCustomerServiceAttribution"><span class="font-semibold">تم التقديم بواسطة:</span> {{ application.submitted_by_customer_service?.name || "--" }}</div>
                        <div v-if="canViewCustomerServiceAttribution"><span class="font-semibold">تاريخ التقديم عبر خدمة العملاء:</span> {{ application.submitted_by_customer_service_at || "--" }}</div>
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

        <div v-if="canManageIdentity" class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">بيانات الهوية</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 md:col-6">
                    <label class="db-field-title required">الاسم رباعي</label>
                    <input v-model="identityForm.full_name" type="text" class="db-field-control" />
                    <small class="db-field-alert" v-if="identityErrors.full_name">{{ identityErrors.full_name[0] }}</small>
                </div>
                <div class="col-12 md:col-6">
                    <label class="db-field-title required">الرقم القومي</label>
                    <input v-model="identityForm.national_id_number" type="text" class="db-field-control" />
                    <small class="db-field-alert" v-if="identityErrors.national_id_number">{{ identityErrors.national_id_number[0] }}</small>
                </div>
                <div class="col-12 mt-3">
                    <button class="db-btn py-2 text-white bg-primary" @click="updateIdentity">حفظ بيانات الهوية</button>
                </div>
            </div>
        </div>

        <div class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">سجل ملاحظات الجهة التمويلية</h3>
            </div>
            <div class="p-4 space-y-3">
                <div v-if="application.notes_history?.length" class="space-y-3">
                    <div v-for="note in application.notes_history" :key="note.id" class="db-field-control !h-auto py-3">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 mb-2">
                            <div class="font-semibold text-sm">
                                {{ note.author?.name || "مستخدم النظام" }}
                                <span v-if="note.institution?.name" class="text-text font-normal">- {{ note.institution.name }}</span>
                            </div>
                            <div class="text-xs text-text">{{ note.created_at || "--" }}</div>
                        </div>
                        <div class="text-sm whitespace-pre-line">{{ note.note }}</div>
                    </div>
                </div>
                <div v-else class="db-field-control min-h-[80px] !h-auto py-3">لا توجد ملاحظات بعد.</div>
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
                <p class="text-sm text-text mb-4">{{ application.starcom_intelligence?.note || "—" }}</p>
                <div class="db-card p-4">
                    <div class="text-sm text-text mb-2">متوسط الشراء الشهري من ستاركوم في آخر ١٢ شهر</div>
                    <div class="text-lg font-semibold">
                        {{ application.starcom_intelligence?.average_monthly_purchase_last_12_months_currency || "--" }}
                    </div>
                </div>
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">قرار الجهة التمويلية</h3>
            </div>
            <div class="p-4">
                <div v-if="canReview" class="row">
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
                    <div v-if="isAdmin" class="col-12 md:col-6">
                        <label class="db-field-title required">جهة التمويل</label>
                        <select v-model="form.financial_institution_user_id" class="db-field-control" @change="handleInstitutionChange">
                            <option value="">اختر جهة التمويل</option>
                            <option v-for="institution in institutions" :key="institution.id" :value="String(institution.id)">
                                {{ institution.company_name || institution.name }}
                            </option>
                        </select>
                    </div>
                    <div v-if="isAdmin" class="col-12 md:col-6">
                        <label class="db-field-title">الموظف المسؤول</label>
                        <select v-model="form.financial_institution_employee_user_id" class="db-field-control">
                            <option value="">نفس جهة التمويل</option>
                            <option v-for="employee in filteredEmployees" :key="employee.id" :value="String(employee.id)">
                                {{ employee.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="flex gap-2 flex-wrap">
                            <button class="db-btn py-2 text-white bg-primary" @click="approve">اعتماد الطلب</button>
                            <button class="db-btn py-2 text-white bg-yellow-600" @click="markPendingApproval">قيد التعديل</button>
                            <button class="db-btn py-2 text-white bg-red-500" @click="decline">رفض الطلب</button>
                            <button v-if="isAdmin" class="db-btn py-2 text-white bg-red-700" @click="destroyApplication">حذف الطلب</button>
                            <router-link :to="{ name: 'admin.creditRequests.list' }" class="db-btn py-2 text-white bg-gray-600">العودة للطلبات</router-link>
                        </div>
                    </div>
                </div>
                <div v-else class="space-y-3">
                    <div class="text-sm text-text">هذا الطلب لم يعد متاحاً للمراجعة.</div>
                    <div class="flex gap-2 flex-wrap">
                        <button v-if="isAdmin" class="db-btn py-2 text-white bg-red-700" @click="destroyApplication">حذف الطلب</button>
                        <router-link :to="{ name: 'admin.creditRequests.list' }" class="db-btn py-2 text-white bg-gray-600">العودة للطلبات</router-link>
                    </div>
                </div>
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
                financial_institution_user_id: "",
                financial_institution_employee_user_id: "",
            },
            identityForm: {
                full_name: "",
                national_id_number: "",
            },
            identityErrors: {},
        };
    },
    computed: {
        application: function () {
            return this.$store.getters["creditApplicationReview/show"];
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        assignmentOptions: function () {
            return this.$store.getters["creditApplicationReview/assignmentOptions"] || { institutions: [], employees: [] };
        },
        institutions: function () {
            return this.assignmentOptions.institutions || [];
        },
        filteredEmployees: function () {
            const institutionId = Number(this.form.financial_institution_user_id || 0);
            return (this.assignmentOptions.employees || []).filter((employee) => {
                return !employee.institution_owner_user_id || Number(employee.institution_owner_user_id) === institutionId || Number(employee.id) === institutionId;
            });
        },
        isAdmin: function () {
            return this.authInfo.role_id === roleEnum.ADMIN;
        },
        canManageIdentity: function () {
            return this.authInfo.role_id === roleEnum.ADMIN || this.authInfo.role_id === roleEnum.MANAGER;
        },
        canViewCustomerServiceAttribution: function () {
            return this.authInfo.role_id === roleEnum.ADMIN || this.authInfo.role_id === roleEnum.MANAGER;
        },
        canReview: function () {
            return this.application.status === "pending" || this.application.status === "declined";
        },
    },
    mounted() {
        this.fetch();
    },
    methods: {
        fetch: function () {
            this.loading.isActive = true;
            Promise.all([
                this.$store.dispatch("creditApplicationReview/show", this.$route.params.id),
                this.isAdmin ? this.$store.dispatch("creditApplicationReview/assignmentOptions") : Promise.resolve(),
            ]).finally(() => {
                this.syncIdentityForm();
                this.loading.isActive = false;
            });
        },
        syncIdentityForm: function () {
            this.identityForm.full_name = this.application.full_name || "";
            this.identityForm.national_id_number = this.application.national_id_number || "";
            this.identityErrors = {};
        },
        handleInstitutionChange: function () {
            const selectedEmployeeId = Number(this.form.financial_institution_employee_user_id || 0);
            if (selectedEmployeeId > 0) {
                const exists = this.filteredEmployees.some((employee) => Number(employee.id) === selectedEmployeeId);
                if (!exists) {
                    this.form.financial_institution_employee_user_id = "";
                }
            }
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
        markPendingApproval: function () {
            this.loading.isActive = true;
            const payload = {
                ...this.form,
                decline_reason: this.form.notes || "يرجى استكمال المستندات أو البيانات المطلوبة.",
            };
            this.$store.dispatch("creditApplicationReview/pendingApproval", {
                id: this.application.id,
                form: payload,
            }).then((res) => {
                alertService.success(res.data.message || "تم نقل الطلب إلى قيد التعديل.");
                this.$router.push({ name: "admin.creditRequests.list" });
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر تحديث حالة الطلب.");
            });
        },
        destroyApplication: function () {
            appService.destroyConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("creditApplicationReview/destroy", this.application.id).then((res) => {
                    alertService.success(res.data.message || "تم حذف الطلب بنجاح.");
                    this.$router.push({ name: "admin.creditRequests.list" });
                }).catch((err) => {
                    this.loading.isActive = false;
                    alertService.error(err.response?.data?.message || "تعذر حذف الطلب.");
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        updateIdentity: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/updateIdentity", {
                id: this.application.id,
                form: this.identityForm,
            }).then((res) => {
                alertService.success(res.data.message || "تم تحديث بيانات الهوية بنجاح.");
                this.identityErrors = {};
                this.$store.commit("creditApplicationReview/show", res.data.data);
                this.syncIdentityForm();
            }).catch((err) => {
                this.identityErrors = err.response?.data?.errors || {};
                alertService.error(err.response?.data?.message || "تعذر تحديث بيانات الهوية.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        statusText: function (status) {
            if (status === "approved") {
                return "تمت الموافقة";
            }
            if (status === "pending_approval") {
                return "قيد التعديل";
            }
            if (status === "declined") {
                return "مرفوض";
            }
            return "قيد المراجعة";
        },
    },
};
</script>
