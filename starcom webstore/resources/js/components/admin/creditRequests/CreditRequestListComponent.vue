<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">طلبات اشتري بالآجل</h3>
            </div>
            <div class="p-4 border-b border-gray-100">
                <div class="flex flex-wrap gap-2 mb-4">
                    <button
                        type="button"
                        class="db-btn py-2"
                        :class="activeTab === 'pending' ? 'text-white bg-primary' : 'text-primary bg-primary/10'"
                        @click="activeTab = 'pending'"
                    >
                        الطلبات الجديدة
                    </button>
                    <button
                        type="button"
                        class="db-btn py-2"
                        :class="activeTab === 'pending_approval' ? 'text-white bg-primary' : 'text-primary bg-primary/10'"
                        @click="activeTab = 'pending_approval'"
                    >
                        قيد التعديل
                    </button>
                    <button
                        type="button"
                        class="db-btn py-2"
                        :class="activeTab === 'declined' ? 'text-white bg-primary' : 'text-primary bg-primary/10'"
                        @click="activeTab = 'declined'"
                    >
                        المرفوضة
                    </button>
                </div>
                <form class="flex flex-col md:flex-row gap-3 items-start md:items-end" @submit.prevent="submitSearch">
                    <div class="w-full md:flex-1">
                        <label class="db-field-title after:hidden">البحث بالاسم رباعي أو الرقم القومي أو اسم العميل أو رقم الهاتف</label>
                        <input
                            v-model="searchForm.term"
                            type="text"
                            class="db-field-control"
                            placeholder="اكتب الاسم أو الرقم القومي أو رقم الهاتف"
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
                <div v-if="isAdminLike" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">طلبات ببطاقة شخصية فقط</p>
                        <h5 class="text-xl font-semibold text-heading">{{ nationalIdOnlyCount }}</h5>
                    </div>
                    <div class="rounded-lg border border-[#E8E8F3] p-4">
                        <p class="text-sm text-secondary mb-1">طلبات ببطاقة شخصية ومستندات إضافية</p>
                        <h5 class="text-xl font-semibold text-heading">{{ nationalIdWithAdditionalDocumentsCount }}</h5>
                    </div>
                </div>
            </div>
            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('customer_name')">العميل <span>{{ sortIcon('customer_name') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('full_name')">الاسم رباعي <span>{{ sortIcon('full_name') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('national_id_number')">الرقم القومي <span>{{ sortIcon('national_id_number') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('phone')">الهاتف <span>{{ sortIcon('phone') }}</span></button>
                            </th>
                            <th v-if="canViewCustomerServiceAttribution" class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('submitted_by_customer_service')">تم التقديم بواسطة <span>{{ sortIcon('submitted_by_customer_service') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('status')">الحالة <span>{{ sortIcon('status') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('updated_at')">آخر تحديث <span>{{ sortIcon('updated_at') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('last_update_label')">ما هو آخر تحديث <span>{{ sortIcon('last_update_label') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('approved_amount')">إجمالي المعتمد <span>{{ sortIcon('approved_amount') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('documents_count')">المستندات <span>{{ sortIcon('documents_count') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('identity_documents_status')">حالة مستندات الهوية <span>{{ sortIcon('identity_documents_status') }}</span></button>
                            </th>
                            <th class="db-table-head-th">
                                <button type="button" class="flex items-center gap-1 text-left" @click="toggleSort('decision_state')">القرار <span>{{ sortIcon('decision_state') }}</span></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="sortedTabbedLists.length">
                        <tr class="db-table-body-tr" v-for="item in sortedTabbedLists" :key="item.id">
                            <td class="db-table-body-td">
                                <div class="font-semibold">{{ item.user?.name }}</div>
                                <div class="text-xs text-text">{{ item.user?.email }}</div>
                            </td>
                            <td class="db-table-body-td">{{ item.full_name || "--" }}</td>
                            <td class="db-table-body-td">{{ item.national_id_number || "--" }}</td>
                            <td class="db-table-body-td">{{ item.user?.phone }}</td>
                            <td v-if="canViewCustomerServiceAttribution" class="db-table-body-td">
                                <div v-if="item.submitted_by_customer_service">
                                    <div class="font-semibold">{{ item.submitted_by_customer_service.name }}</div>
                                    <div class="text-xs text-text">{{ item.submitted_by_customer_service_at || "--" }}</div>
                                </div>
                                <span v-else>--</span>
                            </td>
                            <td class="db-table-body-td">{{ statusText(effectiveStatus(item)) }}</td>
                            <td class="db-table-body-td">{{ item.updated_date || "--" }}</td>
                            <td class="db-table-body-td">{{ item.last_update_label || "--" }}</td>
                            <td class="db-table-body-td">{{ item.approved_amount_currency }}</td>
                            <td class="db-table-body-td">
                                <div class="flex flex-col gap-2">
                                    <a v-if="item.national_id_front_document" :href="item.national_id_front_document" target="_blank" download class="text-primary">تحميل البطاقة أمامي</a>
                                    <a v-if="item.national_id_back_document" :href="item.national_id_back_document" target="_blank" download class="text-primary">تحميل البطاقة خلفي</a>
                                    <a v-for="(document, index) in item.commercial_register_documents || []" :key="document" :href="document" target="_blank" download class="text-primary">تحميل السجل التجاري {{ index + 1 }}</a>
                                    <a v-for="(document, index) in item.tax_card_documents || []" :key="`tax-${document}`" :href="document" target="_blank" download class="text-primary">تحميل البطاقة الضريبية {{ index + 1 }}</a>
                                    <a v-for="(document, index) in item.rent_contract_documents || []" :key="`rent-${document}`" :href="document" target="_blank" download class="text-primary">تحميل عقد الايجار {{ index + 1 }}</a>
                                    <a v-for="(document, index) in item.utility_bill_documents || []" :key="`utility-${document}`" :href="document" target="_blank" download class="text-primary">تحميل ايصال المرافق {{ index + 1 }}</a>
                                    <a v-for="(document, index) in item.additional_documents || []" :key="`additional-${document}`" :href="document" target="_blank" download class="text-primary">تحميل مستند إضافي {{ index + 1 }}</a>
                                    <router-link :to="{ name: 'admin.creditRequests.show', params: { id: item.id } }" class="text-primary font-semibold">فتح الملف</router-link>
                                </div>
                            </td>
                            <td class="db-table-body-td">{{ identityDocumentsStatusLabel(item) }}</td>
                            <td class="db-table-body-td">
                                <div v-if="canReview(item)" class="space-y-2 min-w-[240px]">
                                    <input v-model="reviewForms[item.id].approved_amount" type="number" min="1" step="0.01" class="db-field-control" placeholder="المبلغ المعتمد" />
                                    <input v-model="reviewForms[item.id].duration_days" type="number" min="30" class="db-field-control" placeholder="المدة بالأيام" />
                                    <textarea v-model="reviewForms[item.id].notes" class="db-field-control h-20" placeholder="ملاحظات"></textarea>
                                    <div class="flex gap-2">
                                        <button class="db-btn py-2 text-white bg-primary" @click="approve(item.id)">اعتماد</button>
                                        <button class="db-btn py-2 text-white bg-yellow-600" @click="markPendingApproval(item.id)">قيد التعديل</button>
                                        <button class="db-btn py-2 text-white bg-red-500" @click="decline(item.id)">رفض</button>
                                        <button v-if="isAdminLike" class="db-btn py-2 text-white bg-red-700" @click="destroyApplication(item.id)">حذف</button>
                                    </div>
                                </div>
                                <div v-else-if="canReReview(item)" class="space-y-2 min-w-[240px]">
                                    <div class="text-text text-sm whitespace-pre-line">
                                        {{ effectiveStatus(item) === 'pending_approval'
                                            ? (item.latest_review_note || 'هذا الطلب قيد التعديل من حسابك. يمكنك فتح الملف وقراءة الملاحظات ومراجعته بعد استكمال البيانات.')
                                            : 'تم رفض الطلب سابقاً من حسابك. يمكنك مراجعة الملف والاطلاع على الملاحظات السابقة ثم اتخاذ قرار جديد.' }}
                                    </div>
                                    <div class="flex gap-2 flex-wrap">
                                        <router-link :to="{ name: 'admin.creditRequests.show', params: { id: item.id } }" class="db-btn py-2 text-white bg-primary">
                                            مراجعة
                                        </router-link>
                                        <button v-if="isAdminLike" class="db-btn py-2 text-white bg-red-700" @click="destroyApplication(item.id)">حذف</button>
                                    </div>
                                </div>
                                <div v-else class="space-y-2 min-w-[240px]">
                                    <div class="text-text text-sm">
                                        {{ item.reviewed_by_me ? "تم اتخاذ القرار من حسابك." : "هذا الطلب لم يعد متاحاً للمراجعة." }}
                                    </div>
                                    <div v-if="isAdminLike" class="flex gap-2">
                                        <button class="db-btn py-2 text-white bg-red-700" @click="destroyApplication(item.id)">حذف</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" :colspan="canViewCustomerServiceAttribution ? 12 : 11">لا توجد طلبات جديدة حالياً.</td>
                        </tr>
                    </tbody>
                </table>
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
    name: "CreditRequestListComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            activeTab: "pending",
            reviewForms: {},
            searchForm: {
                term: "",
            },
            appliedTerm: "",
            sortField: "updated_at",
            sortDirection: "desc",
        };
    },
    computed: {
        lists: function () {
            return this.$store.getters["creditApplicationReview/lists"];
        },
        filteredLists: function () {
            const term = this.normalizeSearchValue(this.appliedTerm);
            if (!term) {
                return this.lists;
            }

            return this.lists.filter((item) => {
                const userName = this.normalizeSearchValue(item.user?.name || "");
                const fullName = this.normalizeSearchValue(item.full_name || "");
                const nationalIdNumber = this.normalizeSearchValue(item.national_id_number || "");
                const userEmail = this.normalizeSearchValue(item.user?.email || "");
                const userPhone = this.normalizeSearchValue(item.user?.phone || "");
                const userCountryCode = this.normalizeSearchValue(item.user?.country_code || "");
                const localPhone = userPhone.startsWith("20") ? `0${userPhone.slice(2)}` : userPhone;
                const internationalPhone = `${userCountryCode}${userPhone}`;

                return userName.includes(term)
                    || fullName.includes(term)
                    || nationalIdNumber.includes(term)
                    || userEmail.includes(term)
                    || userPhone.includes(term)
                    || localPhone.includes(term)
                    || internationalPhone.includes(term);
            });
        },
        tabbedLists: function () {
            return this.filteredLists.filter((item) => {
                const status = this.effectiveStatus(item);
                if (this.activeTab === "pending_approval") {
                    return status === "pending_approval";
                }

                if (this.activeTab === "declined") {
                    return status === "declined";
                }

                return status === "pending";
            });
        },
        sortedTabbedLists: function () {
            const items = [...this.tabbedLists];
            const direction = this.sortDirection === "asc" ? 1 : -1;

            return items.sort((firstItem, secondItem) => {
                const firstValue = this.getSortValue(firstItem, this.sortField);
                const secondValue = this.getSortValue(secondItem, this.sortField);
                const comparison = this.compareSortValues(firstValue, secondValue);

                if (comparison !== 0) {
                    return comparison * direction;
                }

                return (Number(secondItem.id) - Number(firstItem.id)) * direction;
            });
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        isAdminLike: function () {
            return this.authInfo.role_id === roleEnum.ADMIN || this.authInfo.role_id === roleEnum.MANAGER;
        },
        canViewCustomerServiceAttribution: function () {
            return this.isAdminLike;
        },
        nationalIdOnlyCount: function () {
            return this.tabbedLists.filter((item) => this.identityDocumentsStatusKey(item) === "national_id_only").length;
        },
        nationalIdWithAdditionalDocumentsCount: function () {
            return this.tabbedLists.filter((item) => this.identityDocumentsStatusKey(item) === "national_id_with_additional_documents").length;
        },
    },
    mounted() {
        this.list();
    },
    methods: {
        list: function () {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/lists", {
                paginate: 0,
                term: this.appliedTerm,
            }).then(() => {
                this.lists.forEach((item) => {
                    if (!this.reviewForms[item.id]) {
                        this.reviewForms[item.id] = {
                            approved_amount: "",
                            duration_days: 30,
                            notes: "",
                            decline_reason: "",
                        };
                    }
                });
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
        toggleSort: function (field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
                return;
            }

            this.sortField = field;
            this.sortDirection = field === "updated_at" ? "desc" : "asc";
        },
        sortIcon: function (field) {
            if (this.sortField !== field) {
                return "↕";
            }

            return this.sortDirection === "asc" ? "↑" : "↓";
        },
        getSortValue: function (item, field) {
            const documentCount = this.totalDocumentCount(item);

            const decisionState = this.canReview(item) ? "1-review" : this.canReReview(item) ? "2-rereview" : "3-closed";

            const sortMap = {
                customer_name: item.user?.name || "",
                full_name: item.full_name || "",
                national_id_number: item.national_id_number || "",
                phone: item.user?.phone || "",
                submitted_by_customer_service: item.submitted_by_customer_service?.name || "",
                status: this.statusText(this.effectiveStatus(item)),
                updated_at: item.updated_at || "",
                last_update_label: item.last_update_label || "",
                approved_amount: Number(item.approved_amount || 0),
                documents_count: documentCount,
                identity_documents_status: this.identityDocumentsStatusSortValue(item),
                decision_state: decisionState,
            };

            return sortMap[field] ?? "";
        },
        compareSortValues: function (firstValue, secondValue) {
            if (typeof firstValue === "number" || typeof secondValue === "number") {
                return Number(firstValue || 0) - Number(secondValue || 0);
            }

            const firstDate = Date.parse(firstValue);
            const secondDate = Date.parse(secondValue);
            if (!Number.isNaN(firstDate) && !Number.isNaN(secondDate)) {
                return firstDate - secondDate;
            }

            return String(firstValue || "").localeCompare(String(secondValue || ""), "ar", {
                numeric: true,
                sensitivity: "base",
            });
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
        hasNationalIdDocuments: function (item) {
            return Boolean(item.national_id_front_document) || Boolean(item.national_id_back_document);
        },
        additionalDocumentsCount: function (item) {
            return Number((item.commercial_register_documents || []).length)
                + Number((item.tax_card_documents || []).length)
                + Number((item.rent_contract_documents || []).length)
                + Number((item.utility_bill_documents || []).length)
                + Number((item.additional_documents || []).length);
        },
        totalDocumentCount: function (item) {
            return Number(Boolean(item.national_id_front_document))
                + Number(Boolean(item.national_id_back_document))
                + this.additionalDocumentsCount(item);
        },
        identityDocumentsStatusKey: function (item) {
            const hasNationalId = this.hasNationalIdDocuments(item);
            const additionalCount = this.additionalDocumentsCount(item);

            if (hasNationalId && additionalCount > 0) {
                return "national_id_with_additional_documents";
            }

            if (hasNationalId) {
                return "national_id_only";
            }

            return "no_national_id";
        },
        identityDocumentsStatusLabel: function (item) {
            const status = this.identityDocumentsStatusKey(item);

            if (status === "national_id_with_additional_documents") {
                return "بطاقة شخصية + مستندات إضافية";
            }

            if (status === "national_id_only") {
                return "بطاقة شخصية فقط";
            }

            return "لا توجد بطاقة شخصية";
        },
        identityDocumentsStatusSortValue: function (item) {
            const status = this.identityDocumentsStatusKey(item);
            const sortOrder = {
                no_national_id: 0,
                national_id_only: 1,
                national_id_with_additional_documents: 2,
            };

            return (sortOrder[status] || 0) * 1000 + this.additionalDocumentsCount(item);
        },
        approve: function (id) {
            this.loading.isActive = true;
            this.$store.dispatch("creditApplicationReview/approve", {
                id,
                form: this.reviewForms[id],
            }).then((res) => {
                alertService.success(res.data.message || "تم اعتماد الرصيد وإضافته إلى المحفظة.");
                this.list();
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر اعتماد الطلب.");
            });
        },
        decline: function (id) {
            this.loading.isActive = true;
            const payload = {
                ...this.reviewForms[id],
                decline_reason: this.reviewForms[id].notes || "تم رفض الطلب بعد المراجعة.",
            };
            this.$store.dispatch("creditApplicationReview/decline", {
                id,
                form: payload,
            }).then((res) => {
                alertService.success(res.data.message || "تم رفض الطلب.");
                this.list();
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر رفض الطلب.");
            });
        },
        markPendingApproval: function (id) {
            this.loading.isActive = true;
            const payload = {
                ...this.reviewForms[id],
                decline_reason: this.reviewForms[id].notes || "يرجى استكمال المستندات أو البيانات المطلوبة.",
            };
            this.$store.dispatch("creditApplicationReview/pendingApproval", {
                id,
                form: payload,
            }).then((res) => {
                alertService.success(res.data.message || "تم نقل الطلب إلى قيد التعديل.");
                this.activeTab = "pending_approval";
                this.list();
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر تحديث حالة الطلب.");
            });
        },
        destroyApplication: function (id) {
            appService.destroyConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("creditApplicationReview/destroy", id).then((res) => {
                    alertService.success(res.data.message || "تم حذف الطلب بنجاح.");
                    this.list();
                }).catch((err) => {
                    this.loading.isActive = false;
                    alertService.error(err.response?.data?.message || "تعذر حذف الطلب.");
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        canReview: function (item) {
            return this.isReopenable(item) && !item.reviewed_by_me;
        },
        canReReview: function (item) {
            return this.isReopenable(item) && item.reviewed_by_me;
        },
        isReopenable: function (item) {
            const status = this.effectiveStatus(item);
            return status === "pending" || status === "pending_approval" || status === "declined";
        },
        effectiveStatus: function (item) {
            return item.queue_status || item.my_review_status || item.status;
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
