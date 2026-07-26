<template>
    <LoadingComponent :props="loading" />
    <div class="space-y-4">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">ملف العميل</h3>
            </div>
            <div class="p-4 space-y-4" v-if="lead.id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><strong class="text-heading">العميل:</strong> {{ lead.user?.name || '--' }}</div>
                    <div><strong class="text-heading">الهاتف:</strong> {{ lead.user?.phone || '--' }}</div>
                    <div><strong class="text-heading">العنوان:</strong> {{ lead.user?.address || '--' }}</div>
                    <div><strong class="text-heading">المدينة:</strong> {{ lead.user?.city || '--' }}</div>
                    <div><strong class="text-heading">المنطقة:</strong> {{ lead.user?.area || '--' }}</div>
                    <div><strong class="text-heading">خط التوزيع:</strong> {{ lead.user?.distribution_route || '--' }}</div>
                    <div><strong class="text-heading">خط العرض:</strong> {{ lead.user?.latitude || '--' }}</div>
                    <div><strong class="text-heading">خط الطول:</strong> {{ lead.user?.longitude || '--' }}</div>
                    <div><strong class="text-heading">الحالة الحالية:</strong> {{ lead.status_label || '--' }}</div>
                    <div><strong class="text-heading">مرحلة المتابعة:</strong> {{ lead.last_pipeline_stage_label || '--' }}</div>
                    <div><strong class="text-heading">الاسم رباعي:</strong> {{ lead.prospect_full_name || '--' }}</div>
                    <div><strong class="text-heading">الرقم القومي:</strong> {{ lead.prospect_national_id_number || '--' }}</div>
                    <div><strong class="text-heading">متوسط الشراء الشهري آخر 12 شهر:</strong> {{ formatMoney(lead.user?.estimated_average_monthly_purchase) }}</div>
                    <div><strong class="text-heading">الموظف المسؤول:</strong> {{ lead.assigned_agent?.name || '--' }}</div>
                </div>

                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <h4 class="font-semibold text-heading mb-3">تحديث بيانات العميل</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input v-model="profileForm.name" type="text" class="db-field-control" placeholder="اسم العميل" />
                        <input v-model="profileForm.distribution_route" type="text" class="db-field-control" placeholder="خط التوزيع" />
                        <input v-model="profileForm.address" type="text" class="db-field-control md:col-span-2" placeholder="العنوان" />
                        <input v-model="profileForm.city" type="text" class="db-field-control" placeholder="المدينة" />
                        <input v-model="profileForm.area" type="text" class="db-field-control" placeholder="المنطقة" />
                        <input v-model="profileForm.latitude" type="text" inputmode="decimal" dir="ltr" class="db-field-control" placeholder="خط العرض" />
                        <input v-model="profileForm.longitude" type="text" inputmode="decimal" dir="ltr" class="db-field-control" placeholder="خط الطول" />
                        <input v-model="profileForm.estimated_average_monthly_purchase" type="number" min="0" step="0.01" class="db-field-control md:col-span-2" placeholder="متوسط الشراء الشهري من ستاركوم خلال آخر 12 شهر" />
                    </div>
                    <button type="button" class="db-btn text-white bg-primary py-2 mt-3" @click="saveProfile">حفظ بيانات العميل</button>
                </div>

                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <h4 class="font-semibold text-heading mb-3">تحديث الحالة</h4>
                    <div class="space-y-3">
                        <select v-model="statusForm.status" class="db-field-control">
                            <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                        <input v-model="statusForm.next_follow_up_at" type="datetime-local" class="db-field-control" />
                        <textarea v-model="statusForm.note" class="db-field-control h-28" placeholder="اكتب ملاحظات المكالمة أو المطلوب من العميل"></textarea>
                        <button type="button" class="db-btn text-white bg-primary py-2" @click="saveStatus">حفظ الحالة</button>
                    </div>
                </div>

                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <h4 class="font-semibold text-heading mb-3">تجهيز طلب اشتري بالآجل</h4>
                    <div class="rounded-lg bg-gray-50 p-3 text-sm text-secondary mb-3" v-if="lead.current_credit_application">
                        <p>آخر طلب داخل النظام: #{{ lead.current_credit_application.id }}</p>
                        <p>حالة الطلب: {{ lead.last_pipeline_stage_label || lead.current_credit_application.status }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 text-sm text-secondary mb-3" v-if="lead.current_credit_facility">
                        <p>آخر اعتماد: {{ formatMoney(lead.current_credit_facility.approved_amount) }}</p>
                        <p>جهة التمويل: {{ lead.current_credit_facility.institution_name || '--' }}</p>
                        <p>الموظف: {{ lead.current_credit_facility.employee_name || '--' }}</p>
                        <p>السداد المسجل: {{ formatMoney(lead.current_credit_facility.repaid_amount) }}</p>
                        <p>العقود المرفوعة: {{ lead.current_credit_facility.contracts_count || 0 }}</p>
                        <p>العقود الموقعة: {{ lead.current_credit_facility.signed_contracts_count || 0 }}</p>
                    </div>
                    <div class="space-y-3">
                        <input v-model="applicationForm.full_name" type="text" class="db-field-control" placeholder="الاسم رباعي" />
                        <input v-model="applicationForm.national_id_number" type="text" class="db-field-control" placeholder="الرقم القومي" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="db-field-title after:hidden">البطاقة الشخصية - الوجه الأمامي</label>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFile($event, 'national_id_front_document')" />
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">البطاقة الشخصية - الوجه الخلفي</label>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFile($event, 'national_id_back_document')" />
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">رفع البطاقة الضريبية - حتى 4 ملفات</label>
                                <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFiles($event, 'tax_card_documents')" />
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">رفع عقد ايجار - حتى 4 ملفات</label>
                                <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFiles($event, 'rent_contract_documents')" />
                            </div>
                        </div>
                        <div>
                            <label class="db-field-title after:hidden">السجل التجاري / Commercial Register</label>
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFiles($event, 'commercial_register_documents')" />
                        </div>
                        <div>
                            <label class="db-field-title after:hidden">رفع ايصال مرافق - حتى 4 ملفات</label>
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFiles($event, 'utility_bill_documents')" />
                        </div>
                        <div>
                            <label class="db-field-title after:hidden">رفع مستندات اضافية - حتى 4 ملفات</label>
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFiles($event, 'additional_documents')" />
                        </div>
                        <textarea v-model="applicationForm.note" class="db-field-control h-24" placeholder="ملاحظات إضافية"></textarea>
                        <button type="button" class="db-btn text-white bg-primary py-2" @click="submitApplication">رفع البيانات وإنشاء الطلب</button>
                    </div>
                </div>

                <div class="rounded-lg border border-[#E8E8F3] p-4">
                    <h4 class="font-semibold text-heading mb-3">سجل المتابعة</h4>
                    <div class="space-y-3" v-if="(lead.activities || []).length">
                        <div v-for="activity in lead.activities" :key="activity.id" class="rounded-lg bg-gray-50 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-heading">{{ activity.actor?.name || 'النظام' }}</p>
                                    <p class="text-xs text-secondary">{{ activity.created_at }}</p>
                                </div>
                                <span class="text-xs text-primary">{{ labelForStatus(activity.status) }}</span>
                            </div>
                            <p class="text-sm text-secondary mt-2">{{ activity.note || '--' }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-secondary" v-else>
                        لا توجد متابعات مسجلة بعد.
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
    name: "CustomerServiceLeadShowComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            statusForm: {
                status: "waiting_documents",
                next_follow_up_at: "",
                note: "",
            },
            profileForm: {
                name: "",
                address: "",
                city: "",
                area: "",
                distribution_route: "",
                latitude: "",
                longitude: "",
                estimated_average_monthly_purchase: "",
            },
            applicationForm: {
                full_name: "",
                national_id_number: "",
                national_id_front_document: null,
                national_id_back_document: null,
                commercial_register_documents: [],
                tax_card_documents: [],
                rent_contract_documents: [],
                utility_bill_documents: [],
                additional_documents: [],
                note: "",
            },
            statusOptions: [
                { value: "waiting_documents", label: "في انتظار الاوراق" },
                { value: "documents_received", label: "تم استلام الاوراق" },
                { value: "not_interested", label: "غير مهتم" },
                { value: "visit_required", label: "مطلوب زيارة" },
                { value: "no_answer", label: "لم يتم الرد" },
                { value: "contacted_waiting_reply", label: "تم التواصل فى انتظار الرد" },
                { value: "call_later", label: "بيكنسل هكلمه فى وقت تانى" },
                { value: "rejected_commercial_register", label: "رفض فكره السجل" },
                { value: "review_with_owner", label: "هيراجع صاحبب العمل" },
                { value: "no_credit_sales", label: "مش بيشتغل اجل" },
                { value: "no_register_no_id_consent", label: "معندوش سجل ومش موافق على البطاقه" },
                { value: "closed", label: "مقفول" },
            ],
        };
    },
    computed: {
        lead() {
            return this.$store.getters["customerServiceCrm/show"] || {};
        },
    },
    mounted() {
        this.fetchLead();
    },
    methods: {
        fetchLead() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/show", this.$route.params.id).then(() => {
                this.statusForm.status = this.lead.status || "waiting_documents";
                this.applicationForm.full_name = this.lead.prospect_full_name || "";
                this.applicationForm.national_id_number = this.lead.prospect_national_id_number || "";
                this.profileForm = {
                    name: this.lead.user?.name || "",
                    address: this.lead.user?.address || "",
                    city: this.lead.user?.city || "",
                    area: this.lead.user?.area || "",
                    distribution_route: this.lead.user?.distribution_route || "",
                    latitude: this.lead.user?.latitude || "",
                    longitude: this.lead.user?.longitude || "",
                    estimated_average_monthly_purchase: this.lead.user?.estimated_average_monthly_purchase || "",
                };
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        setFile(event, field) {
            this.applicationForm[field] = event.target.files?.[0] || null;
        },
        setFiles(event, field) {
            this.applicationForm[field] = Array.from(event.target.files || []);
        },
        labelForStatus(status) {
            const match = this.statusOptions.find((item) => item.value === status);
            return match ? match.label : status || "--";
        },
        formatMoney(value) {
            const amount = Number(value || 0);
            return new Intl.NumberFormat("en-US", {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(amount) + " EGP";
        },
        saveProfile() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/updateProfile", {
                id: this.$route.params.id,
                form: this.profileForm,
            }).then(() => {
                alertService.success("تم تحديث بيانات العميل بنجاح");
            }).catch((error) => {
                alertService.error(error.response?.data?.message || "تعذر تحديث بيانات العميل");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        saveStatus() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/updateStatus", {
                id: this.$route.params.id,
                form: this.statusForm,
            }).then(() => {
                alertService.success("تم تحديث حالة العميل بنجاح");
            }).catch((error) => {
                alertService.error(error.response?.data?.message || "تعذر تحديث الحالة");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        submitApplication() {
            const form = new FormData();
            form.append("full_name", this.applicationForm.full_name || "");
            form.append("national_id_number", this.applicationForm.national_id_number || "");
            form.append("note", this.applicationForm.note || "");
            if (this.applicationForm.national_id_front_document) {
                form.append("national_id_front_document", this.applicationForm.national_id_front_document);
            }
            if (this.applicationForm.national_id_back_document) {
                form.append("national_id_back_document", this.applicationForm.national_id_back_document);
            }
            this.applicationForm.commercial_register_documents.forEach((document) => {
                form.append("commercial_register_documents[]", document);
            });
            this.applicationForm.tax_card_documents.forEach((document) => {
                form.append("tax_card_documents[]", document);
            });
            this.applicationForm.rent_contract_documents.forEach((document) => {
                form.append("rent_contract_documents[]", document);
            });
            this.applicationForm.utility_bill_documents.forEach((document) => {
                form.append("utility_bill_documents[]", document);
            });
            this.applicationForm.additional_documents.forEach((document) => {
                form.append("additional_documents[]", document);
            });

            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/submitApplication", {
                id: this.$route.params.id,
                form,
            }).then(() => {
                alertService.success("تم إنشاء طلب اشتري بالآجل بنجاح");
                this.fetchLead();
            }).catch((error) => {
                alertService.error(error.response?.data?.message || "تعذر رفع البيانات");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
