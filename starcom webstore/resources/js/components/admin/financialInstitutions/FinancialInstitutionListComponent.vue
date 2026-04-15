<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">الجهات التمويلية</h3>
            </div>

            <form class="p-4 border-b border-gray-100" @submit.prevent="save">
                <div class="row">
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title required">اسم الجهة</label>
                        <input v-model="form.company_name" class="db-field-control" type="text" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title required">اسم المسؤول</label>
                        <input v-model="form.name" class="db-field-control" type="text" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title required">البريد الإلكتروني</label>
                        <input v-model="form.email" class="db-field-control" type="email" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title">الهاتف</label>
                        <input v-model="form.phone" class="db-field-control" type="text" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title">هاتف التواصل</label>
                        <input v-model="form.contact_phone" class="db-field-control" type="text" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title required">كود الدولة</label>
                        <input v-model="form.country_code" class="db-field-control" type="text" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title">{{ formModeTitle }}</label>
                        <input v-model="form.password" class="db-field-control" type="password" />
                    </div>
                    <div class="col-12 md:col-6 xl:col-3">
                        <label class="db-field-title">{{ formModeTitle }} التأكيد</label>
                        <input v-model="form.password_confirmation" class="db-field-control" type="password" />
                    </div>
                    <div class="col-12">
                        <label class="db-field-title">ملاحظات</label>
                        <textarea v-model="form.notes" class="db-field-control h-24"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="flex flex-wrap gap-3 mt-4">
                            <button class="db-btn py-2 text-white bg-primary">
                                <i class="lab lab-fill-save"></i>
                                <span>{{ isEditing ? "تحديث الجهة" : "إضافة الجهة" }}</span>
                            </button>
                            <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="reset">
                                <i class="lab lab-line-cross"></i>
                                <span>مسح</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">الجهة</th>
                            <th class="db-table-head-th">المسؤول</th>
                            <th class="db-table-head-th">البريد</th>
                            <th class="db-table-head-th">الهاتف</th>
                            <th class="db-table-head-th">الطلبات المعتمدة</th>
                            <th class="db-table-head-th">الرصيد النشط</th>
                            <th class="db-table-head-th">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="lists.length">
                        <tr class="db-table-body-tr" v-for="item in lists" :key="item.id">
                            <td class="db-table-body-td">{{ item.company_name }}</td>
                            <td class="db-table-body-td">{{ item.name }}</td>
                            <td class="db-table-body-td">{{ item.email }}</td>
                            <td class="db-table-body-td">{{ item.country_code }} {{ item.phone }}</td>
                            <td class="db-table-body-td">{{ item.approved_facilities }}</td>
                            <td class="db-table-body-td">{{ item.active_wallet_funding }}</td>
                            <td class="db-table-body-td">
                                <button class="db-btn py-1.5 px-3 text-white bg-primary" @click="edit(item)">تعديل</button>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="7">لا توجد جهات تمويلية مضافة بعد.</td>
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
import statusEnum from "../../../enums/modules/statusEnum";

export default {
    name: "FinancialInstitutionListComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: this.defaultForm(),
            statusEnum: statusEnum,
        };
    },
    computed: {
        lists: function () {
            return this.$store.getters["financialInstitution/lists"];
        },
        isEditing: function () {
            return this.$store.getters["financialInstitution/temp"].isEditing;
        },
        formModeTitle: function () {
            return this.isEditing ? "كلمة مرور جديدة (اختياري)" : "كلمة المرور";
        },
    },
    mounted() {
        this.list();
    },
    methods: {
        defaultForm: function () {
            return {
                company_name: "",
                name: "",
                email: "",
                phone: "",
                contact_phone: "",
                country_code: "+20",
                password: "",
                password_confirmation: "",
                notes: "",
                status: statusEnum.ACTIVE,
            };
        },
        list: function () {
            this.loading.isActive = true;
            this.$store.dispatch("financialInstitution/lists", { paginate: 0 }).finally(() => {
                this.loading.isActive = false;
            });
        },
        save: function () {
            this.loading.isActive = true;
            this.$store.dispatch("financialInstitution/save", {
                form: this.form,
                search: { paginate: 0 },
            }).then((res) => {
                alertService.success(res.data.message || "تم حفظ الجهة التمويلية بنجاح.");
                this.reset();
                this.list();
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err.response?.data?.message || "تعذر حفظ الجهة التمويلية.");
            });
        },
        edit: function (item) {
            this.$store.dispatch("financialInstitution/edit", item.id);
            this.form = {
                company_name: item.company_name,
                name: item.name,
                email: item.email,
                phone: item.phone,
                contact_phone: item.contact_phone,
                country_code: item.country_code || "+20",
                password: "",
                password_confirmation: "",
                notes: item.notes,
                status: item.status,
            };
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
        reset: function () {
            this.form = this.defaultForm();
            this.loading.isActive = false;
            this.$store.dispatch("financialInstitution/reset");
        },
    },
};
</script>
