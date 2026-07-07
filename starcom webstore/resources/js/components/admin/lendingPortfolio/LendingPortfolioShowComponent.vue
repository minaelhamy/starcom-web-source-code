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
                        <div><span class="font-semibold">الاسم رباعي:</span> {{ facility.full_name || "--" }}</div>
                        <div><span class="font-semibold">الرقم القومي:</span> {{ facility.national_id_number || "--" }}</div>
                        <div><span class="font-semibold">العنوان:</span> {{ facility.user?.address || "--" }}</div>
                        <div><span class="font-semibold">المدينة:</span> {{ facility.user?.city || "--" }}</div>
                        <div><span class="font-semibold">المنطقة:</span> {{ facility.user?.area || "--" }}</div>
                        <div><span class="font-semibold">خط العرض:</span> {{ facility.user?.latitude || "--" }}</div>
                        <div><span class="font-semibold">خط الطول:</span> {{ facility.user?.longitude || "--" }}</div>
                        <div><span class="font-semibold">الهاتف:</span> {{ facility.user?.phone || "--" }}</div>
                        <div><span class="font-semibold">المبلغ المعتمد:</span> {{ facility.approved_currency || "--" }}</div>
                        <div><span class="font-semibold">إجمالي المسدد:</span> {{ facility.repaid_currency || "--" }}</div>
                        <div><span class="font-semibold">المتبقي للسداد:</span> {{ facility.remaining_due_currency || "--" }}</div>
                    </div>
                </div>
                <div class="col-12 lg:col-6">
                    <div class="space-y-2 text-sm">
                        <div><span class="font-semibold">الحالة:</span> {{ statusText(facility.status) }}</div>
                        <div><span class="font-semibold">جهة التمويل:</span> {{ facility.institution?.company_name || facility.institution?.name || "--" }}</div>
                        <div><span class="font-semibold">الموظف المسؤول:</span> {{ facility.employee?.name || "--" }}</div>
                        <div><span class="font-semibold">بداية المدة:</span> {{ facility.starts_at || "--" }}</div>
                        <div><span class="font-semibold">تاريخ الاستحقاق:</span> {{ facility.due_at || "--" }}</div>
                        <div><span class="font-semibold">تاريخ المراجعة:</span> {{ facility.reviewed_at || "--" }}</div>
                        <div><span class="font-semibold">مدة التمويل:</span> {{ facility.duration_days || "--" }} يوم</div>
                    </div>
                </div>
                <div class="col-12 mt-4">
                    <label class="db-field-title">سجل ملاحظات الجهة التمويلية</label>
                    <div class="space-y-3">
                        <div v-if="facility.notes_history?.length" class="space-y-3">
                            <div v-for="note in facility.notes_history" :key="note.id" class="db-field-control !h-auto py-3">
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
                        <div v-else class="db-field-control min-h-[100px] !h-auto py-3">لا توجد ملاحظات.</div>
                    </div>
                </div>
                <div class="col-12 mt-4" v-if="canAddNote">
                    <label class="db-field-title">إضافة ملاحظة جديدة</label>
                    <textarea v-model="noteForm.note" class="db-field-control h-28" placeholder="اكتب ملاحظة جديدة على العميل"></textarea>
                    <div class="mt-3">
                        <button class="db-btn py-2 text-white bg-primary" @click="addNote">حفظ الملاحظة</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="db-card mb-4" v-if="canRecordRepayment || (facility.repayments || []).length">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">السداد والتحصيل</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 lg:col-6" v-if="canRecordRepayment">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">تسجيل دفعة جديدة</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="db-field-title required">قيمة السداد</label>
                                <input v-model="repaymentForm.amount" type="number" min="0.01" step="0.01" class="db-field-control" />
                                <small class="db-field-alert" v-if="repaymentErrors.amount">{{ repaymentErrors.amount[0] }}</small>
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">تاريخ السداد</label>
                                <input
                                    v-model="repaymentForm.paid_at"
                                    @keydown.enter.prevent="recordRepayment"
                                    type="text"
                                    placeholder="YYYY-MM-DD أو 07/07/2026"
                                    dir="ltr"
                                    class="db-field-control"
                                />
                                <small class="db-field-alert" v-if="repaymentErrors.paid_at">{{ repaymentErrors.paid_at[0] }}</small>
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">طريقة السداد</label>
                                <input v-model="repaymentForm.payment_method" type="text" class="db-field-control" placeholder="مثال: تحويل بنكي أو نقدي" />
                                <small class="db-field-alert" v-if="repaymentErrors.payment_method">{{ repaymentErrors.payment_method[0] }}</small>
                            </div>
                            <div>
                                <label class="db-field-title after:hidden">المرجع</label>
                                <input v-model="repaymentForm.reference_number" type="text" class="db-field-control" placeholder="رقم العملية أو المرجع" />
                                <small class="db-field-alert" v-if="repaymentErrors.reference_number">{{ repaymentErrors.reference_number[0] }}</small>
                            </div>
                            <div class="md:col-span-2">
                                <label class="db-field-title after:hidden">ملاحظات السداد</label>
                                <textarea v-model="repaymentForm.notes" class="db-field-control h-28" placeholder="أي ملاحظات تخص التحصيل أو السداد"></textarea>
                                <small class="db-field-alert" v-if="repaymentErrors.notes">{{ repaymentErrors.notes[0] }}</small>
                            </div>
                        </div>
                        <div class="mt-3 text-sm text-text">
                            عند السداد الكامل سيتم إغلاق هذا التمويل وإعادة العميل إلى فرص التمويل المفتوحة لهذه الجهة فقط.
                        </div>
                        <div class="mt-3">
                            <button type="button" class="db-btn py-2 text-white bg-primary" @click="recordRepayment">تسجيل السداد</button>
                        </div>
                    </div>
                </div>
                <div :class="canRecordRepayment ? 'col-12 lg:col-6' : 'col-12'">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">سجل السداد</h4>
                        <div v-if="facility.repayments?.length" class="space-y-3">
                            <div v-for="repayment in facility.repayments" :key="repayment.id" class="db-field-control !h-auto py-3">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 mb-2">
                                    <div class="font-semibold text-sm">
                                        {{ repayment.amount_currency }}
                                        <span v-if="repayment.creator?.name" class="text-text font-normal">- بواسطة {{ repayment.creator.name }}</span>
                                    </div>
                                    <div class="text-xs text-text">{{ repayment.paid_date || repayment.created_date || "--" }}</div>
                                </div>
                                <div class="text-sm" v-if="repayment.payment_method">طريقة السداد: {{ repayment.payment_method }}</div>
                                <div class="text-sm" v-if="repayment.reference_number">المرجع: {{ repayment.reference_number }}</div>
                                <div class="text-sm whitespace-pre-line" v-if="repayment.notes">{{ repayment.notes }}</div>
                            </div>
                        </div>
                        <div v-else class="db-field-control min-h-[100px] !h-auto py-3">لا توجد عمليات سداد مسجلة حتى الآن.</div>
                    </div>
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
                <div class="col-12 md:col-6 mt-3">
                    <label class="db-field-title after:hidden">رفع السجل التجاري</label>
                    <input type="file" multiple class="db-field-control" accept=".jpg,.jpeg,.png,.pdf" @change="setIdentityFiles($event, 'commercial_register_documents')" />
                    <small class="db-field-alert" v-if="identityErrors.commercial_register_documents">{{ identityErrors.commercial_register_documents[0] }}</small>
                    <small class="db-field-alert" v-if="identityErrors['commercial_register_documents.0']">{{ identityErrors['commercial_register_documents.0'][0] }}</small>
                </div>
                <div class="col-12 md:col-6 mt-3">
                    <label class="db-field-title after:hidden">رفع البطاقة الضريبية</label>
                    <input type="file" class="db-field-control" accept=".jpg,.jpeg,.png,.pdf" @change="setIdentityFile($event, 'tax_card_document')" />
                    <small class="db-field-alert" v-if="identityErrors.tax_card_document">{{ identityErrors.tax_card_document[0] }}</small>
                </div>
                <div class="col-12 md:col-6 mt-3">
                    <label class="db-field-title after:hidden">رفع عقد ايجار</label>
                    <input type="file" class="db-field-control" accept=".jpg,.jpeg,.png,.pdf" @change="setIdentityFile($event, 'rent_contract_document')" />
                    <small class="db-field-alert" v-if="identityErrors.rent_contract_document">{{ identityErrors.rent_contract_document[0] }}</small>
                </div>
                <div class="col-12 md:col-6 mt-3">
                    <label class="db-field-title after:hidden">رفع ايصال مرافق</label>
                    <input type="file" class="db-field-control" accept=".jpg,.jpeg,.png,.pdf" @change="setIdentityFile($event, 'utility_bill_document')" />
                    <small class="db-field-alert" v-if="identityErrors.utility_bill_document">{{ identityErrors.utility_bill_document[0] }}</small>
                </div>
                <div class="col-12 mt-3">
                    <button class="db-btn py-2 text-white bg-primary" @click="updateIdentity">حفظ بيانات الهوية</button>
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
                <div class="col-12 md:col-6 xl:col-4">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">عقد ايجار</h4>
                        <div class="flex flex-col gap-2">
                            <a v-if="facility.application?.rent_contract_document" :href="facility.application.rent_contract_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل عقد الايجار</a>
                            <span v-else class="text-sm text-text">غير مرفوع.</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 md:col-6 xl:col-4">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">ايصال مرافق</h4>
                        <div class="flex flex-col gap-2">
                            <a v-if="facility.application?.utility_bill_document" :href="facility.application.utility_bill_document" target="_blank" download class="db-btn py-2 text-white bg-primary">تحميل ايصال المرافق</a>
                            <span v-else class="text-sm text-text">غير مرفوع.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="canUploadContracts || (facility.contract_documents || []).length" class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">عقود التمويل غير الموقعة</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 lg:col-6">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">العقود غير الموقعة المرفوعة</h4>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="(document, index) in facility.contract_documents || []"
                                :key="document.id || index"
                                class="flex flex-wrap gap-2"
                            >
                                <a
                                    :href="document.url"
                                    target="_blank"
                                    download
                                    class="db-btn py-2 text-white bg-primary"
                                >
                                    تحميل العقد غير الموقع {{ index + 1 }}
                                </a>
                                <button
                                    v-if="canDeleteContracts"
                                    class="db-btn py-2 text-white bg-red-500"
                                    @click="deleteContract(document.id)"
                                >
                                    حذف العقد
                                </button>
                            </div>
                            <span v-if="!(facility.contract_documents || []).length" class="text-sm text-text">لا توجد عقود غير موقعة مرفوعة بعد.</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 lg:col-6" v-if="canUploadContracts">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">رفع عقود غير موقعة جديدة</h4>
                        <input
                            type="file"
                            multiple
                            class="db-field-control"
                            accept=".jpg,.jpeg,.png,.pdf"
                            @change="setContractFiles"
                        />
                        <small class="db-field-alert" v-if="contractErrors.contract_documents">{{ contractErrors.contract_documents[0] }}</small>
                        <small class="db-field-alert" v-if="contractErrors['contract_documents.0']">{{ contractErrors['contract_documents.0'][0] }}</small>
                        <div class="mt-3">
                            <button class="db-btn py-2 text-white bg-primary" @click="uploadContracts">رفع العقود غير الموقعة</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="canManageSignedContracts || (facility.signed_contract_documents || []).length" class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">العقود الموقعة</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 lg:col-6">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">العقود الموقعة المرفوعة</h4>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="(document, index) in facility.signed_contract_documents || []"
                                :key="document.id || index"
                                class="flex flex-wrap gap-2"
                            >
                                <a
                                    :href="document.url"
                                    target="_blank"
                                    download
                                    class="db-btn py-2 text-white bg-primary"
                                >
                                    تحميل العقد الموقع {{ index + 1 }}
                                </a>
                                <button
                                    v-if="canManageSignedContracts"
                                    class="db-btn py-2 text-white bg-red-500"
                                    @click="deleteSignedContract(document.id)"
                                >
                                    حذف العقد الموقع
                                </button>
                            </div>
                            <span v-if="!(facility.signed_contract_documents || []).length" class="text-sm text-text">لا توجد عقود موقعة مرفوعة بعد.</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 lg:col-6" v-if="canManageSignedContracts">
                    <div class="db-card p-4 h-full">
                        <h4 class="font-semibold mb-3">رفع عقود موقعة جديدة</h4>
                        <input
                            type="file"
                            multiple
                            class="db-field-control"
                            accept=".jpg,.jpeg,.png,.pdf"
                            @change="setSignedContractFiles"
                        />
                        <small class="db-field-alert" v-if="signedContractErrors.signed_contract_documents">{{ signedContractErrors.signed_contract_documents[0] }}</small>
                        <small class="db-field-alert" v-if="signedContractErrors['signed_contract_documents.0']">{{ signedContractErrors['signed_contract_documents.0'][0] }}</small>
                        <div class="mt-3">
                            <button class="db-btn py-2 text-white bg-primary" @click="uploadSignedContracts">رفع العقود الموقعة</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="canManageFacilityDates" class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">تعديل بداية المدة</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 md:col-6">
                    <label class="db-field-title required">بداية المدة</label>
                    <input
                        :value="dateForm.starts_at"
                        @input="onFacilityDateInput($event)"
                        @keydown.enter.prevent="updateFacilityDates"
                        type="text"
                        inputmode="numeric"
                        placeholder="YYYY-MM-DD"
                        dir="ltr"
                        class="db-field-control"
                    />
                    <small class="db-field-alert" v-if="dateErrors.starts_at">{{ dateErrors.starts_at[0] }}</small>
                </div>
                <div class="col-12 md:col-6">
                    <label class="db-field-title">تاريخ الاستحقاق المتوقع</label>
                    <input :value="expectedDueDate" type="text" class="db-field-control" disabled />
                </div>
                <div class="col-12 mt-3">
                    <button class="db-btn py-2 text-white bg-primary" @click="updateFacilityDates">حفظ بداية المدة</button>
                </div>
            </div>
        </div>

        <div class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">Starcom Intelligence</h3>
            </div>
            <div class="px-4 pb-4">
                <p class="text-sm text-text mb-4">{{ facility.starcom_intelligence?.note || "—" }}</p>
                <div class="db-card p-4">
                    <div class="text-sm text-text mb-2">متوسط الشراء الشهري من ستاركوم في آخر ١٢ شهر</div>
                    <div class="text-lg font-semibold">
                        {{ facility.starcom_intelligence?.average_monthly_purchase_last_12_months_currency || "--" }}
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isAdminLike" class="db-card mb-4">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">تعيين جهة التمويل والموظف</h3>
            </div>
            <div class="row p-4">
                <div class="col-12 md:col-6">
                    <label class="db-field-title required">جهة التمويل</label>
                    <select v-model="assignmentForm.financial_institution_user_id" class="db-field-control" @change="handleInstitutionChange">
                        <option value="">اختر جهة التمويل</option>
                        <option v-for="institution in institutions" :key="institution.id" :value="String(institution.id)">
                            {{ institution.company_name || institution.name }}
                        </option>
                    </select>
                </div>
                <div class="col-12 md:col-6">
                    <label class="db-field-title">الموظف المسؤول</label>
                    <select v-model="assignmentForm.financial_institution_employee_user_id" class="db-field-control">
                        <option value="">نفس جهة التمويل</option>
                        <option v-for="employee in filteredEmployees" :key="employee.id" :value="String(employee.id)">
                            {{ employee.name }}
                        </option>
                    </select>
                </div>
                <div class="col-12 mt-3">
                    <div class="flex gap-2 flex-wrap">
                        <button class="db-btn py-2 text-white bg-primary" @click="assignFacility">حفظ التعيين</button>
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
            assignmentForm: {
                financial_institution_user_id: "",
                financial_institution_employee_user_id: "",
            },
            noteForm: {
                note: "",
            },
            contractForm: {
                contract_documents: [],
            },
            contractErrors: {},
            signedContractForm: {
                signed_contract_documents: [],
            },
            signedContractErrors: {},
            dateForm: {
                starts_at: "",
            },
            dateErrors: {},
            repaymentForm: {
                amount: "",
                payment_method: "",
                reference_number: "",
                notes: "",
                paid_at: "",
            },
            repaymentErrors: {},
            identityForm: {
                full_name: "",
                national_id_number: "",
                commercial_register_documents: [],
                tax_card_document: null,
                rent_contract_document: null,
                utility_bill_document: null,
            },
            identityErrors: {},
        };
    },
    computed: {
        facility: function () {
            return this.$store.getters["creditApplicationReview/portfolioShow"];
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
            const institutionId = Number(this.assignmentForm.financial_institution_user_id || 0);
            return (this.assignmentOptions.employees || []).filter((employee) => {
                return !employee.institution_owner_user_id || Number(employee.institution_owner_user_id) === institutionId || Number(employee.id) === institutionId;
            });
        },
        isAdminLike: function () {
            return this.authInfo.role_id === roleEnum.ADMIN || this.authInfo.role_id === roleEnum.MANAGER;
        },
        isFinancialInstitutionManager: function () {
            return this.authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION &&
                this.authInfo.financial_institution_role === "manager";
        },
        canManageIdentity: function () {
            return this.isAdminLike;
        },
        canResetApproval: function () {
            return this.isAdminLike &&
                this.facility.status === "approved" &&
                Number(this.facility.utilized_amount || 0) === 0;
        },
        canAddNote: function () {
            return (this.isAdminLike || this.authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION) && this.facility.id;
        },
        canUploadContracts: function () {
            return (this.isAdminLike || this.authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION) &&
                this.facility.id &&
                this.facility.status === "approved";
        },
        canManageSignedContracts: function () {
            return this.isAdminLike &&
                this.facility.id &&
                this.facility.status === "approved";
        },
        canDeleteContracts: function () {
            return (this.isAdminLike || this.isFinancialInstitutionManager) &&
                this.facility.id &&
                this.facility.status === "approved";
        },
        canManageFacilityDates: function () {
            return (this.isAdminLike || this.isFinancialInstitutionManager) &&
                this.facility.id &&
                this.facility.status === "approved";
        },
        canRecordRepayment: function () {
            return (this.isAdminLike || this.authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION) &&
                this.facility.id &&
                this.facility.status === "approved" &&
                Number(this.facility.remaining_due_amount || 0) > 0;
        },
        expectedDueDate: function () {
            if (!this.dateForm.starts_at) {
                return this.facility.due_at || "--";
            }

            const durationDays = Number(this.facility.duration_days || 0);
            if (!durationDays) {
                return "--";
            }

            const startsAt = new Date(`${this.dateForm.starts_at}T00:00:00`);
            if (Number.isNaN(startsAt.getTime())) {
                return "--";
            }

            startsAt.setDate(startsAt.getDate() + durationDays);

            const year = startsAt.getFullYear();
            const month = String(startsAt.getMonth() + 1).padStart(2, "0");
            const day = String(startsAt.getDate()).padStart(2, "0");

            return `${year}-${month}-${day}`;
        },
    },
    mounted() {
        this.loading.isActive = true;
        Promise.all([
            this.$store.dispatch("creditApplicationReview/showFacility", this.$route.params.id),
            this.isAdminLike ? this.$store.dispatch("creditApplicationReview/assignmentOptions") : Promise.resolve(),
        ]).finally(() => {
            this.syncAssignmentForm();
            this.syncIdentityForm();
            this.syncDateForm();
            this.loading.isActive = false;
        });
    },
    methods: {
        syncAssignmentForm: function () {
            this.assignmentForm.financial_institution_user_id = this.facility.institution?.id ? String(this.facility.institution.id) : "";
            this.assignmentForm.financial_institution_employee_user_id = this.facility.employee?.id && this.facility.employee?.id !== this.facility.institution?.id
                ? String(this.facility.employee.id)
                : "";
        },
        syncIdentityForm: function () {
            this.identityForm.full_name = this.facility.full_name || "";
            this.identityForm.national_id_number = this.facility.national_id_number || "";
            this.identityForm.commercial_register_documents = [];
            this.identityForm.tax_card_document = null;
            this.identityForm.rent_contract_document = null;
            this.identityForm.utility_bill_document = null;
            this.identityErrors = {};
        },
        setIdentityFile: function (event, field) {
            this.identityForm[field] = event.target.files?.[0] || null;
        },
        setIdentityFiles: function (event, field) {
            this.identityForm[field] = Array.from(event.target.files || []);
        },
        syncDateForm: function () {
            this.dateForm.starts_at = this.normalizeFacilityDate(this.facility.starts_at || "");
            this.dateErrors = {};
        },
        resetRepaymentForm: function () {
            this.repaymentForm.amount = "";
            this.repaymentForm.payment_method = "";
            this.repaymentForm.reference_number = "";
            this.repaymentForm.notes = "";
            this.repaymentForm.paid_at = "";
            this.repaymentErrors = {};
        },
        normalizeFacilityDate: function (value) {
            if (!value) {
                return "";
            }

            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                return value;
            }

            const slashMatch = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (slashMatch) {
                const [, month, day, year] = slashMatch;
                return `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
            }

            const dashMatch = value.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
            if (dashMatch) {
                const [, day, month, year] = dashMatch;
                return `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
            }

            const parsedDate = new Date(value);
            if (!Number.isNaN(parsedDate.getTime())) {
                const year = parsedDate.getFullYear();
                const month = String(parsedDate.getMonth() + 1).padStart(2, "0");
                const day = String(parsedDate.getDate()).padStart(2, "0");
                return `${year}-${month}-${day}`;
            }

            return "";
        },
        onFacilityDateInput: function (event) {
            this.dateForm.starts_at = event?.target?.value || "";
        },
        setContractFiles: function (event) {
            this.contractForm.contract_documents = Array.from(event.target.files || []);
        },
        setSignedContractFiles: function (event) {
            this.signedContractForm.signed_contract_documents = Array.from(event.target.files || []);
        },
        handleInstitutionChange: function () {
            const selectedEmployeeId = Number(this.assignmentForm.financial_institution_employee_user_id || 0);
            if (selectedEmployeeId > 0) {
                const exists = this.filteredEmployees.some((employee) => Number(employee.id) === selectedEmployeeId);
                if (!exists) {
                    this.assignmentForm.financial_institution_employee_user_id = "";
                }
            }
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
            if (status === "settled") {
                return "مسدد";
            }
            return status || "--";
        },
        recordRepayment: function () {
            if (document?.activeElement && typeof document.activeElement.blur === "function") {
                document.activeElement.blur();
            }

            const form = {
                ...this.repaymentForm,
                paid_at: this.normalizeFacilityDate(this.repaymentForm.paid_at || ""),
            };

            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/recordRepayment", {
                id: this.facility.id,
                form,
            }).then((res) => {
                alertService.success(res.data.message || "تم تسجيل السداد بنجاح.");
                this.resetRepaymentForm();
            }).catch((err) => {
                this.repaymentErrors = err.response?.data?.errors || {};
                alertService.error(err.response?.data?.message || "تعذر تسجيل السداد.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        assignFacility: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/assignFacility", {
                id: this.facility.id,
                form: this.assignmentForm,
            }).then((res) => {
                alertService.success(res.data.message || "تم تحديث التعيين بنجاح.");
                this.syncAssignmentForm();
            }).catch((err) => {
                alertService.error(err.response?.data?.message || "تعذر تحديث التعيين.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        addNote: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/addFacilityNote", {
                id: this.facility.id,
                form: this.noteForm,
            }).then((res) => {
                alertService.success(res.data.message || "تمت إضافة الملاحظة بنجاح.");
                this.noteForm.note = "";
            }).catch((err) => {
                alertService.error(err.response?.data?.message || "تعذر إضافة الملاحظة.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        uploadContracts: function () {
            if (!this.contractForm.contract_documents.length) {
                alertService.error("يرجى اختيار عقد واحد على الأقل.");
                return;
            }

            this.loading.isActive = true;
            const form = new FormData();
            this.contractForm.contract_documents.forEach((file) => {
                form.append("contract_documents[]", file);
            });

            this.$store.dispatch("creditApplicationReview/uploadFacilityContracts", {
                id: this.facility.id,
                form,
            }).then((res) => {
                alertService.success(res.data.message || "تم رفع العقود بنجاح.");
                this.contractErrors = {};
                this.contractForm.contract_documents = [];
            }).catch((err) => {
                this.contractErrors = err.response?.data?.errors || {};
                alertService.error(err.response?.data?.message || "تعذر رفع العقود.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        uploadSignedContracts: function () {
            if (!this.signedContractForm.signed_contract_documents.length) {
                alertService.error("يرجى اختيار عقد موقع واحد على الأقل.");
                return;
            }

            this.loading.isActive = true;
            const form = new FormData();
            this.signedContractForm.signed_contract_documents.forEach((file) => {
                form.append("signed_contract_documents[]", file);
            });

            this.$store.dispatch("creditApplicationReview/uploadSignedFacilityContracts", {
                id: this.facility.id,
                form,
            }).then((res) => {
                alertService.success(res.data.message || "تم رفع العقود الموقعة بنجاح.");
                this.signedContractErrors = {};
                this.signedContractForm.signed_contract_documents = [];
            }).catch((err) => {
                this.signedContractErrors = err.response?.data?.errors || {};
                alertService.error(err.response?.data?.message || "تعذر رفع العقود الموقعة.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        updateFacilityDates: function () {
            this.dateForm.starts_at = this.normalizeFacilityDate(this.dateForm.starts_at);
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/updateFacilityDates", {
                id: this.facility.id,
                form: this.dateForm,
            }).then((res) => {
                alertService.success(res.data.message || "تم تحديث بداية المدة وتاريخ الاستحقاق بنجاح.");
                this.dateErrors = {};
                this.syncDateForm();
            }).catch((err) => {
                this.dateErrors = err.response?.data?.errors || {};
                alertService.error(err.response?.data?.message || "تعذر تحديث بداية المدة.");
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        deleteContract: function (mediaId) {
            appService.destroyConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("creditApplicationReview/deleteFacilityContract", {
                    id: this.facility.id,
                    mediaId: mediaId,
                }).then((res) => {
                    alertService.success(res.data.message || "تم حذف العقد بنجاح.");
                }).catch((err) => {
                    alertService.error(err.response?.data?.message || "تعذر حذف العقد.");
                }).finally(() => {
                    this.loading.isActive = false;
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        deleteSignedContract: function (mediaId) {
            appService.destroyConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("creditApplicationReview/deleteSignedFacilityContract", {
                    id: this.facility.id,
                    mediaId: mediaId,
                }).then((res) => {
                    alertService.success(res.data.message || "تم حذف العقد الموقع بنجاح.");
                }).catch((err) => {
                    alertService.error(err.response?.data?.message || "تعذر حذف العقد الموقع.");
                }).finally(() => {
                    this.loading.isActive = false;
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        updateIdentity: function () {
            if (!this.facility.application?.id) {
                return;
            }

            this.loading.isActive = true;
            const form = new FormData();
            form.append("full_name", this.identityForm.full_name || "");
            form.append("national_id_number", this.identityForm.national_id_number || "");
            this.identityForm.commercial_register_documents.forEach((document) => {
                form.append("commercial_register_documents[]", document);
            });
            if (this.identityForm.tax_card_document) {
                form.append("tax_card_document", this.identityForm.tax_card_document);
            }
            if (this.identityForm.rent_contract_document) {
                form.append("rent_contract_document", this.identityForm.rent_contract_document);
            }
            if (this.identityForm.utility_bill_document) {
                form.append("utility_bill_document", this.identityForm.utility_bill_document);
            }
            this.$store.dispatch("creditApplicationReview/updateIdentity", {
                id: this.facility.application.id,
                form,
            }).then((res) => {
                alertService.success(res.data.message || "تم تحديث بيانات الهوية بنجاح.");
                this.identityErrors = {};
                return this.$store.dispatch("creditApplicationReview/showFacility", this.facility.id).finally(() => {
                    this.syncIdentityForm();
                });
            }).catch((err) => {
                this.identityErrors = err.response?.data?.errors || {};
                alertService.error(err.response?.data?.message || "تعذر تحديث بيانات الهوية.");
            }).finally(() => {
                this.loading.isActive = false;
            });
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
