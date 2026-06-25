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
                    <div><strong class="text-heading">الحالة الحالية:</strong> {{ lead.status_label || '--' }}</div>
                    <div><strong class="text-heading">الاسم رباعي:</strong> {{ lead.prospect_full_name || '--' }}</div>
                    <div><strong class="text-heading">الرقم القومي:</strong> {{ lead.prospect_national_id_number || '--' }}</div>
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
                    <div v-if="lead.current_credit_application" class="text-sm text-secondary">
                        هذا العميل لديه طلب داخل النظام بالفعل برقم {{ lead.current_credit_application.id }}.
                    </div>
                    <div v-else class="space-y-3">
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
                                <label class="db-field-title after:hidden">البطاقة الضريبية</label>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFile($event, 'tax_card_document')" />
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">عقد ايجار</label>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFile($event, 'rent_contract_document')" />
                            </div>
                        </div>
                        <div>
                            <label class="db-field-title after:hidden">السجل التجاري / Commercial Register</label>
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="db-field-control" @change="setFiles($event, 'commercial_register_documents')" />
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
            applicationForm: {
                full_name: "",
                national_id_number: "",
                national_id_front_document: null,
                national_id_back_document: null,
                commercial_register_documents: [],
                tax_card_document: null,
                rent_contract_document: null,
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
            if (this.applicationForm.tax_card_document) {
                form.append("tax_card_document", this.applicationForm.tax_card_document);
            }
            if (this.applicationForm.rent_contract_document) {
                form.append("rent_contract_document", this.applicationForm.rent_contract_document);
            }

            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/submitApplication", {
                id: this.$route.params.id,
                form,
            }).then(() => {
                alertService.success("تم إنشاء طلب اشتري بالآجل بنجاح");
                this.$router.push({ name: "admin.customerServiceLeads.list" });
            }).catch((error) => {
                alertService.error(error.response?.data?.message || "تعذر رفع البيانات");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
