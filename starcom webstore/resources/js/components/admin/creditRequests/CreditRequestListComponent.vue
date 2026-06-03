<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">طلبات اشتري بالآجل</h3>
            </div>
            <div class="p-4 border-b border-gray-100">
                <form class="flex flex-col md:flex-row gap-3 items-start md:items-end" @submit.prevent="submitSearch">
                    <div class="w-full md:flex-1">
                        <label class="db-field-title after:hidden">البحث باسم العميل أو رقم الهاتف</label>
                        <input
                            v-model="searchForm.term"
                            type="text"
                            class="db-field-control"
                            placeholder="اكتب اسم العميل أو رقم الهاتف"
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
            </div>
            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">العميل</th>
                            <th class="db-table-head-th">الهاتف</th>
                            <th class="db-table-head-th">الحالة</th>
                            <th class="db-table-head-th">إجمالي المعتمد</th>
                            <th class="db-table-head-th">المستندات</th>
                            <th class="db-table-head-th">القرار</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="filteredLists.length">
                        <tr class="db-table-body-tr" v-for="item in filteredLists" :key="item.id">
                            <td class="db-table-body-td">
                                <div class="font-semibold">{{ item.user?.name }}</div>
                                <div class="text-xs text-text">{{ item.user?.email }}</div>
                            </td>
                            <td class="db-table-body-td">{{ item.user?.phone }}</td>
                            <td class="db-table-body-td">{{ statusText(item.status) }}</td>
                            <td class="db-table-body-td">{{ item.approved_amount_currency }}</td>
                            <td class="db-table-body-td">
                                <div class="flex flex-col gap-2">
                                    <a v-if="item.national_id_front_document" :href="item.national_id_front_document" target="_blank" download class="text-primary">تحميل البطاقة أمامي</a>
                                    <a v-if="item.national_id_back_document" :href="item.national_id_back_document" target="_blank" download class="text-primary">تحميل البطاقة خلفي</a>
                                    <a v-for="(document, index) in item.commercial_register_documents || []" :key="document" :href="document" target="_blank" download class="text-primary">تحميل السجل التجاري {{ index + 1 }}</a>
                                    <a v-if="item.tax_card_document" :href="item.tax_card_document" target="_blank" download class="text-primary">تحميل البطاقة الضريبية</a>
                                    <router-link :to="{ name: 'admin.creditRequests.show', params: { id: item.id } }" class="text-primary font-semibold">فتح الملف</router-link>
                                </div>
                            </td>
                            <td class="db-table-body-td">
                                <div v-if="canReview(item)" class="space-y-2 min-w-[240px]">
                                    <input v-model="reviewForms[item.id].approved_amount" type="number" min="1" step="0.01" class="db-field-control" placeholder="المبلغ المعتمد" />
                                    <input v-model="reviewForms[item.id].duration_days" type="number" min="30" class="db-field-control" placeholder="المدة بالأيام" />
                                    <textarea v-model="reviewForms[item.id].notes" class="db-field-control h-20" placeholder="ملاحظات"></textarea>
                                    <div class="flex gap-2">
                                        <button class="db-btn py-2 text-white bg-primary" @click="approve(item.id)">اعتماد</button>
                                        <button class="db-btn py-2 text-white bg-red-500" @click="decline(item.id)">رفض</button>
                                        <button v-if="isAdmin" class="db-btn py-2 text-white bg-red-700" @click="destroyApplication(item.id)">حذف</button>
                                    </div>
                                </div>
                                <div v-else class="space-y-2 min-w-[240px]">
                                    <div class="text-text text-sm">
                                        {{ item.reviewed_by_me ? "تم اتخاذ القرار من حسابك." : "هذا الطلب لم يعد متاحاً للمراجعة." }}
                                    </div>
                                    <div v-if="isAdmin" class="flex gap-2">
                                        <button class="db-btn py-2 text-white bg-red-700" @click="destroyApplication(item.id)">حذف</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="6">لا توجد طلبات جديدة حالياً.</td>
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
            reviewForms: {},
            searchForm: {
                term: "",
            },
            appliedTerm: "",
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
                const userEmail = this.normalizeSearchValue(item.user?.email || "");
                const userPhone = this.normalizeSearchValue(item.user?.phone || "");
                const userCountryCode = this.normalizeSearchValue(item.user?.country_code || "");
                const localPhone = userPhone.startsWith("20") ? `0${userPhone.slice(2)}` : userPhone;
                const internationalPhone = `${userCountryCode}${userPhone}`;

                return userName.includes(term)
                    || userEmail.includes(term)
                    || userPhone.includes(term)
                    || localPhone.includes(term)
                    || internationalPhone.includes(term);
            });
        },
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        isAdmin: function () {
            return this.authInfo.role_id === roleEnum.ADMIN;
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
            return item.status === "pending" && !item.reviewed_by_me;
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
