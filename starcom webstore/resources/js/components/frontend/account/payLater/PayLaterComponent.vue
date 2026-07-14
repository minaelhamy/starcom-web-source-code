<template>
    <LoadingComponent :props="loading" />
    <div>
        <h2 class="capitalize text-2xl font-bold mb-2 text-primary">اشتري بالآجل / المحفظة</h2>
        <p class="mb-7 font-medium">من هنا تقدر تتابع رصيد المحفظة وترفع أوراقك وتكمل طلب اشتري بالآجل.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-card">
                <p class="text-sm text-text mb-2">رصيد المحفظة</p>
                <h3 class="text-xl font-bold text-primary">{{ summary.wallet_balance_currency || "0" }}</h3>
            </div>
            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-card">
                <p class="text-sm text-text mb-2">إجمالي الرصيد المعتمد</p>
                <h3 class="text-xl font-bold">{{ currency(summary.total_credit_limit) }}</h3>
            </div>
            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-card">
                <p class="text-sm text-text mb-2">المتاح للشراء</p>
                <h3 class="text-xl font-bold">{{ currency(summary.total_available_credit) }}</h3>
            </div>
            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-card">
                <p class="text-sm text-text mb-2">المستخدم</p>
                <h3 class="text-xl font-bold">{{ currency(summary.total_utilized_credit) }}</h3>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-card">
                <h3 class="text-lg font-bold mb-4">طلب اشتري بالآجل</h3>
                <form @submit.prevent="save">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="db-field-title required">الاسم رباعي</label>
                            <input v-model="form.full_name" class="db-field-control" type="text" />
                            <small class="db-field-alert" v-if="errors.full_name">{{ errors.full_name[0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title required">الرقم القومي</label>
                            <input v-model="form.national_id_number" class="db-field-control" type="text" />
                            <small class="db-field-alert" v-if="errors.national_id_number">{{ errors.national_id_number[0] }}</small>
                        </div>
                        <div>
                            <label class="db-field-title required">البطاقة الشخصية - الوجه الأمامي</label>
                            <input class="db-field-control" type="file" accept=".jpg,.jpeg,.png,.pdf" @change="setFile($event, 'national_id_front_document')" />
                            <small class="db-field-alert" v-if="errors.national_id_front_document">{{ errors.national_id_front_document[0] }}</small>
                        </div>
                        <div>
                            <label class="db-field-title">البطاقة الشخصية - الوجه الخلفي</label>
                            <input class="db-field-control" type="file" accept=".jpg,.jpeg,.png,.pdf" @change="setFile($event, 'national_id_back_document')" />
                            <small class="db-field-alert" v-if="errors.national_id_back_document">{{ errors.national_id_back_document[0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title">السجل التجاري - حتى 4 صفحات</label>
                            <input class="db-field-control" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" @change="setFiles($event, 'commercial_register_documents')" />
                            <small class="db-field-alert" v-if="errors.commercial_register_documents">{{ errors.commercial_register_documents[0] }}</small>
                            <small class="db-field-alert" v-if="errors['commercial_register_documents.0']">{{ errors['commercial_register_documents.0'][0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <h4 class="font-semibold text-heading mb-2">مستندات اضافية</h4>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title">رفع البطاقة الضريبية - حتى 4 ملفات</label>
                            <input class="db-field-control" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" @change="setFiles($event, 'tax_card_documents')" />
                            <small class="db-field-alert" v-if="errors.tax_card_documents">{{ errors.tax_card_documents[0] }}</small>
                            <small class="db-field-alert" v-if="errors['tax_card_documents.0']">{{ errors['tax_card_documents.0'][0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title">رفع عقد ايجار - حتى 4 ملفات</label>
                            <input class="db-field-control" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" @change="setFiles($event, 'rent_contract_documents')" />
                            <small class="db-field-alert" v-if="errors.rent_contract_documents">{{ errors.rent_contract_documents[0] }}</small>
                            <small class="db-field-alert" v-if="errors['rent_contract_documents.0']">{{ errors['rent_contract_documents.0'][0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title">رفع ايصال مرافق - حتى 4 ملفات</label>
                            <input class="db-field-control" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" @change="setFiles($event, 'utility_bill_documents')" />
                            <small class="db-field-alert" v-if="errors.utility_bill_documents">{{ errors.utility_bill_documents[0] }}</small>
                            <small class="db-field-alert" v-if="errors['utility_bill_documents.0']">{{ errors['utility_bill_documents.0'][0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title">رفع مستندات اضافية - حتى 4 ملفات</label>
                            <input class="db-field-control" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" @change="setFiles($event, 'additional_documents')" />
                            <small class="db-field-alert" v-if="errors.additional_documents">{{ errors.additional_documents[0] }}</small>
                            <small class="db-field-alert" v-if="errors['additional_documents.0']">{{ errors['additional_documents.0'][0] }}</small>
                        </div>
                        <div class="md:col-span-2">
                            <label class="db-field-title">ملاحظات</label>
                            <textarea v-model="form.notes" class="db-field-control h-28"></textarea>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3 mt-4">
                        <button type="submit" class="db-btn py-2 text-white bg-primary">
                            <i class="lab lab-fill-save"></i>
                            <span>{{ editableApplication ? "حفظ التعديلات" : "إرسال الطلب" }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-card">
                <h3 class="text-lg font-bold mb-4">طلباتك</h3>
                <div v-if="applications.length" class="space-y-4">
                    <div v-for="application in applications" :key="application.id" class="rounded-lg border border-gray-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div>
                                <p class="font-semibold">طلب رقم #{{ application.id }}</p>
                                <p class="text-sm text-text">{{ application.created_date }}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold"
                                  :class="application.status === 'approved' ? 'bg-green-100 text-green-700' : application.status === 'declined' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'">
                                {{ statusText(application.status) }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3 text-sm">
                            <div><span class="font-semibold">الاسم رباعي:</span> {{ application.full_name || "--" }}</div>
                            <div><span class="font-semibold">الرقم القومي:</span> {{ application.national_id_number || "--" }}</div>
                        </div>
                        <div v-if="application.notes_history?.length" class="rounded-lg bg-[#F7F7FC] p-3 mb-3">
                            <p class="font-semibold mb-2">ملاحظات جهة التمويل</p>
                            <div class="space-y-2 text-sm">
                                <div v-for="note in application.notes_history" :key="note.id" class="border-b border-gray-200 pb-2 last:border-b-0 last:pb-0">
                                    <div class="font-medium">{{ note.institution?.name || note.author?.name || "جهة التمويل" }}</div>
                                    <div class="text-text whitespace-pre-line">{{ note.note }}</div>
                                    <div class="text-xs text-text mt-1">{{ note.created_at }}</div>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm mb-3" v-if="application.notes">{{ application.notes }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <a v-if="application.national_id_front_document" :href="application.national_id_front_document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">بطاقة أمامي</a>
                            <a v-if="application.national_id_back_document" :href="application.national_id_back_document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">بطاقة خلفي</a>
                        </div>
                        <div v-if="application.commercial_register_documents?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <a v-for="(document, index) in application.commercial_register_documents" :key="document" :href="document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">
                                صفحة السجل {{ index + 1 }}
                            </a>
                        </div>
                        <div v-if="application.tax_card_documents?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <a v-for="(document, index) in application.tax_card_documents" :key="`tax-${document}`" :href="document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">
                                البطاقة الضريبية {{ index + 1 }}
                            </a>
                        </div>
                        <div v-if="application.rent_contract_documents?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <a v-for="(document, index) in application.rent_contract_documents" :key="`rent-${document}`" :href="document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">
                                عقد الايجار {{ index + 1 }}
                            </a>
                        </div>
                        <div v-if="application.utility_bill_documents?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <a v-for="(document, index) in application.utility_bill_documents" :key="`utility-${document}`" :href="document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">
                                ايصال المرافق {{ index + 1 }}
                            </a>
                        </div>
                        <div v-if="application.additional_documents?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                            <a v-for="(document, index) in application.additional_documents" :key="`additional-${document}`" :href="document" target="_blank" class="db-btn py-2 bg-[#F7F7FC]">
                                مستند إضافي {{ index + 1 }}
                            </a>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button class="db-btn py-2 text-white bg-red-500" @click="destroyApplication(application.id)">
                                حذف الطلب
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-text">لا يوجد طلبات حتى الآن.</div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-card mt-6">
            <h3 class="text-lg font-bold mb-4">حركة المحفظة</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                    <tr class="text-right border-b">
                        <th class="py-3 px-2">التاريخ</th>
                        <th class="py-3 px-2">النوع</th>
                        <th class="py-3 px-2">المبلغ</th>
                        <th class="py-3 px-2">الوصف</th>
                        <th class="py-3 px-2">رقم الطلب</th>
                    </tr>
                    </thead>
                    <tbody v-if="walletTransactions.length">
                    <tr v-for="transaction in walletTransactions" :key="transaction.id" class="border-b">
                        <td class="py-3 px-2">{{ transaction.created_at }}</td>
                        <td class="py-3 px-2">{{ transaction.direction === 'credit' ? 'إضافة' : 'خصم' }}</td>
                        <td class="py-3 px-2">{{ transaction.amount_currency }}</td>
                        <td class="py-3 px-2">{{ transaction.description }}</td>
                        <td class="py-3 px-2">{{ transaction.order_serial_no || '--' }}</td>
                    </tr>
                    </tbody>
                    <tbody v-else>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-text">لا توجد حركة على المحفظة حتى الآن.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent.vue";
import alertService from "../../../../services/alertService";
import appService from "../../../../services/appService";

export default {
    name: "PayLaterComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: {
                full_name: "",
                national_id_number: "",
                national_id_front_document: null,
                national_id_back_document: null,
                commercial_register_documents: [],
                tax_card_documents: [],
                rent_contract_documents: [],
                utility_bill_documents: [],
                additional_documents: [],
                notes: "",
            },
            errors: {},
        };
    },
    computed: {
        summary: function () {
            return this.$store.getters["frontendPayLater/summary"];
        },
        applications: function () {
            return this.$store.getters["frontendPayLater/applications"];
        },
        editableApplication: function () {
            return this.applications.find((application) => application.status === "pending_approval") || null;
        },
        walletTransactions: function () {
            return this.$store.getters["frontendPayLater/walletTransactions"];
        },
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        fetchData: function () {
            this.loading.isActive = true;
            Promise.all([
                this.$store.dispatch("frontendPayLater/summary"),
                this.$store.dispatch("frontendPayLater/applications", { paginate: 0 }),
                this.$store.dispatch("frontendPayLater/walletTransactions", { paginate: 0 }),
            ]).finally(() => {
                this.syncEditableApplication();
                this.loading.isActive = false;
            });
        },
        syncEditableApplication: function () {
            if (!this.editableApplication) {
                return;
            }

            this.form.full_name = this.editableApplication.full_name || "";
            this.form.national_id_number = this.editableApplication.national_id_number || "";
            this.form.notes = this.editableApplication.notes || "";
        },
        setFile: function (event, key) {
            this.form[key] = event.target.files[0] || null;
        },
        setFiles: function (event, key) {
            this.form[key] = Array.from(event.target.files || []);
        },
        save: function () {
            this.loading.isActive = true;
            const formData = new FormData();
            if (this.form.national_id_front_document) {
                formData.append("national_id_front_document", this.form.national_id_front_document);
            }
            if (this.form.national_id_back_document) {
                formData.append("national_id_back_document", this.form.national_id_back_document);
            }
            this.form.commercial_register_documents.forEach((document) => {
                formData.append("commercial_register_documents[]", document);
            });
            this.form.tax_card_documents.forEach((document) => {
                formData.append("tax_card_documents[]", document);
            });
            this.form.rent_contract_documents.forEach((document) => {
                formData.append("rent_contract_documents[]", document);
            });
            this.form.utility_bill_documents.forEach((document) => {
                formData.append("utility_bill_documents[]", document);
            });
            this.form.additional_documents.forEach((document) => {
                formData.append("additional_documents[]", document);
            });
            formData.append("full_name", this.form.full_name || "");
            formData.append("national_id_number", this.form.national_id_number || "");
            formData.append("notes", this.form.notes || "");

            const action = this.editableApplication ? "frontendPayLater/update" : "frontendPayLater/save";
            const payload = this.editableApplication
                ? { id: this.editableApplication.id, form: formData }
                : formData;

            this.$store.dispatch(action, payload).then((res) => {
                alertService.success(res.data.message || "تم إرسال الطلب بنجاح.");
                this.errors = {};
                this.form = {
                    full_name: "",
                    national_id_number: "",
                    national_id_front_document: null,
                    national_id_back_document: null,
                    commercial_register_documents: [],
                    tax_card_documents: [],
                    rent_contract_documents: [],
                    utility_bill_documents: [],
                    additional_documents: [],
                    notes: "",
                };
                this.fetchData();
            }).catch((err) => {
                this.loading.isActive = false;
                this.errors = err.response?.data?.errors || {};
                if (err.response?.data?.message) {
                    alertService.error(err.response.data.message);
                }
            });
        },
        destroyApplication: function (id) {
            appService.destroyConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("frontendPayLater/destroy", id).then((res) => {
                    alertService.success(res.data.message || "تم حذف الطلب بنجاح.");
                    this.fetchData();
                }).catch((err) => {
                    this.loading.isActive = false;
                    alertService.error(err.response?.data?.message || "تعذر حذف الطلب.");
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        currency: function (amount) {
            const symbol = this.$store.getters["frontendSetting/lists"]?.site_default_currency_symbol || "";
            return `${Number(amount || 0).toFixed(2)}${symbol}`;
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
