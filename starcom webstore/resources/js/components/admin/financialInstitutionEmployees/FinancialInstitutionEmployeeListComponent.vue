<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">موظفو الجهات التمويلية</h3>
                <div class="db-card-filter">
                    <TableLimitComponent :method="list" :search="props.search" :page="paginationPage" />
                    <FilterComponent @click.prevent="handleSlide('financial-institution-employee-filter')" />
                    <FinancialEmployeeCreateComponent :props="props" />
                </div>
            </div>

            <div class="table-filter-div" id="financial-institution-employee-filter">
                <form class="p-4 sm:p-5 mb-5 w-full d-block" @submit.prevent="search">
                    <div class="row">
                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label class="db-field-title after:hidden">الاسم</label>
                            <input v-model="props.search.name" type="text" class="db-field-control" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label class="db-field-title after:hidden">البريد الإلكتروني</label>
                            <input v-model="props.search.email" type="text" class="db-field-control" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label class="db-field-title after:hidden">الهاتف</label>
                            <input v-model="props.search.phone" type="text" class="db-field-control" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label class="db-field-title after:hidden">جهة التمويل</label>
                            <vue-select
                                class="db-field-control f-b-custom-select"
                                v-model="props.search.financial_institution_owner_user_id"
                                :options="institutions"
                                label-by="company_name"
                                value-by="id"
                                :closeOnSelect="true"
                                :searchable="true"
                                :clearOnClose="true"
                                placeholder="--"
                                search-placeholder="--"
                            />
                        </div>
                        <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                            <label class="db-field-title after:hidden">الدور داخل جهة التمويل</label>
                            <select v-model="props.search.financial_institution_role" class="db-field-control">
                                <option value="">--</option>
                                <option value="manager">مدير</option>
                                <option value="employee">موظف</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="flex flex-wrap gap-3 mt-4">
                                <button class="db-btn py-2 text-white bg-primary">
                                    <i class="lab lab-line-search lab-font-size-16"></i>
                                    <span>{{ $t("button.search") }}</span>
                                </button>
                                <button type="button" class="db-btn py-2 text-white bg-gray-600" @click="clear">
                                    <i class="lab lab-line-cross lab-font-size-22"></i>
                                    <span>{{ $t("button.clear") }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">الموظف</th>
                            <th class="db-table-head-th">جهة التمويل</th>
                            <th class="db-table-head-th">الدور</th>
                            <th class="db-table-head-th">البريد</th>
                            <th class="db-table-head-th">الهاتف</th>
                            <th class="db-table-head-th">الحالة</th>
                            <th class="db-table-head-th">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="employees.length">
                        <tr class="db-table-body-tr" v-for="employee in employees" :key="employee.id">
                            <td class="db-table-body-td">{{ employee.name }}</td>
                            <td class="db-table-body-td">{{ employee.financial_institution_owner?.company_name || "--" }}</td>
                            <td class="db-table-body-td">{{ employee.financial_institution_role_name || "--" }}</td>
                            <td class="db-table-body-td">{{ employee.email }}</td>
                            <td class="db-table-body-td">{{ employee.country_code }} {{ employee.phone }}</td>
                            <td class="db-table-body-td">
                                <span :class="statusClass(employee.status)">
                                    {{ statusLabel(employee.status) }}
                                </span>
                            </td>
                            <td class="db-table-body-td">
                                <div class="flex justify-start items-center gap-1.5">
                                    <SmIconViewComponent :link="'admin.employees.show'" :id="employee.id" />
                                    <SmIconSidebarModalEditComponent @click="edit(employee)" />
                                    <SmIconDeleteComponent @click="destroy(employee.id)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="7">لا يوجد موظفون مرتبطون بجهات التمويل حتى الآن.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-6" v-if="employees.length > 0">
                <PaginationSMBox :pagination="pagination" :method="list" />
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <PaginationTextComponent :props="{ page: paginationPage }" />
                    <PaginationBox :pagination="pagination" :method="list" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import TableLimitComponent from "../components/TableLimitComponent";
import FilterComponent from "../components/buttons/collapse/FilterComponent";
import PaginationSMBox from "../components/pagination/PaginationSMBox";
import PaginationTextComponent from "../components/pagination/PaginationTextComponent";
import PaginationBox from "../components/pagination/PaginationBox";
import SmIconViewComponent from "../components/buttons/SmIconViewComponent";
import SmIconSidebarModalEditComponent from "../components/buttons/SmIconSidebarModalEditComponent";
import SmIconDeleteComponent from "../components/buttons/SmIconDeleteComponent";
import alertService from "../../../services/alertService";
import appService from "../../../services/appService";
import statusEnum from "../../../enums/modules/statusEnum";
import roleEnum from "../../../enums/modules/roleEnum";
import EmployeeCreateComponent from "../employees/EmployeeCreateComponent.vue";

export default {
    name: "FinancialInstitutionEmployeeListComponent",
    components: {
        LoadingComponent,
        TableLimitComponent,
        FilterComponent,
        PaginationSMBox,
        PaginationTextComponent,
        PaginationBox,
        SmIconViewComponent,
        SmIconSidebarModalEditComponent,
        SmIconDeleteComponent,
        FinancialEmployeeCreateComponent: EmployeeCreateComponent,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            props: {
                financialInstitutionEmployeeOnly: true,
                defaultFinancialInstitutionOwnerId: null,
                form: {
                    name: "",
                    email: "",
                    phone: "",
                    password: "",
                    password_confirmation: "",
                    country_code: "",
                    role_id: roleEnum.FINANCIAL_INSTITUTION,
                    financial_institution_owner_user_id: null,
                    financial_institution_role: "employee",
                    status: statusEnum.ACTIVE,
                },
                search: {
                    paginate: 1,
                    page: 1,
                    per_page: 10,
                    order_column: "id",
                    order_type: "desc",
                    name: "",
                    email: "",
                    phone: "",
                    role_id: roleEnum.FINANCIAL_INSTITUTION,
                    financial_institution_employee_only: 1,
                    financial_institution_owner_user_id: null,
                    financial_institution_role: "",
                },
                flag: "",
            },
            statusEnum: statusEnum,
        };
    },
    computed: {
        employees: function () {
            return this.$store.getters["employee/lists"];
        },
        pagination: function () {
            return this.$store.getters["employee/pagination"];
        },
        paginationPage: function () {
            return this.$store.getters["employee/page"];
        },
        institutions: function () {
            return this.$store.getters["financialInstitution/lists"] || [];
        },
    },
    mounted() {
        this.$store.dispatch("financialInstitution/lists", { paginate: 0 });
        this.list();
    },
    methods: {
        list: function (page = 1) {
            if (typeof page === "number") {
                this.props.search.page = page;
            }
            this.loading.isActive = true;
            this.$store.dispatch("employee/lists", this.props.search).finally(() => {
                this.loading.isActive = false;
            });
        },
        search: function () {
            this.props.search.page = 1;
            this.list();
        },
        clear: function () {
            this.props.search = {
                paginate: 1,
                page: 1,
                per_page: 10,
                order_column: "id",
                order_type: "desc",
                name: "",
                email: "",
                phone: "",
                role_id: roleEnum.FINANCIAL_INSTITUTION,
                financial_institution_employee_only: 1,
                financial_institution_owner_user_id: null,
                financial_institution_role: "",
            };
            this.list();
        },
        edit: function (employee) {
            this.$store.dispatch("employee/edit", employee.id);
            this.props.form = {
                name: employee.name,
                email: employee.email,
                phone: employee.phone,
                password: "",
                password_confirmation: "",
                country_code: employee.country_code || "+20",
                role_id: roleEnum.FINANCIAL_INSTITUTION,
                financial_institution_owner_user_id: employee.financial_institution_owner_user_id,
                financial_institution_role: employee.financial_institution_role || "employee",
                status: employee.status,
            };
            this.props.defaultFinancialInstitutionOwnerId = employee.financial_institution_owner_user_id;
            window.scrollTo({ top: 0, behavior: "smooth" });
        },
        destroy: function (id) {
            appService.destroyConfirmation().then(() => {
                this.loading.isActive = true;
                this.$store.dispatch("employee/destroy", { id, search: this.props.search }).then((res) => {
                    alertService.success(res.data.message || "تم حذف الموظف بنجاح.");
                }).catch((err) => {
                    alertService.error(err.response?.data?.message || "تعذر حذف الموظف.");
                }).finally(() => {
                    this.loading.isActive = false;
                });
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        handleSlide: function (id) {
            return appService.slide(id);
        },
        statusLabel: function (status) {
            return status === statusEnum.ACTIVE ? this.$t("label.active") : this.$t("label.inactive");
        },
        statusClass: function (status) {
            return status === statusEnum.ACTIVE ? "db-badge py-0.5 text-green-600 bg-green-100" : "db-badge py-0.5 text-red-600 bg-red-100";
        },
    },
};
</script>
