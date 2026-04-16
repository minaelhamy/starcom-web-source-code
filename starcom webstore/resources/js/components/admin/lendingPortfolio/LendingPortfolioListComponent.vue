<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <div class="db-card">
            <div class="db-card-header border-none">
                <h3 class="db-card-title">المحفظة التمويلية</h3>
            </div>
            <div class="db-table-responsive">
                <table class="db-table">
                    <thead class="db-table-head">
                        <tr class="db-table-head-tr">
                            <th class="db-table-head-th">العميل</th>
                            <th class="db-table-head-th">الحالة</th>
                            <th class="db-table-head-th">المعتمد</th>
                            <th class="db-table-head-th">المتاح</th>
                            <th class="db-table-head-th">المستخدم</th>
                            <th class="db-table-head-th">بداية المدة</th>
                            <th class="db-table-head-th">تاريخ الاستحقاق</th>
                            <th class="db-table-head-th">الملف</th>
                        </tr>
                    </thead>
                    <tbody class="db-table-body" v-if="portfolio.length">
                        <tr class="db-table-body-tr" v-for="item in portfolio" :key="item.id">
                            <td class="db-table-body-td">
                                <div class="font-semibold">{{ item.user?.name || "--" }}</div>
                                <div class="text-xs text-text">{{ item.user?.phone || "" }}</div>
                            </td>
                            <td class="db-table-body-td">{{ statusText(item.status) }}</td>
                            <td class="db-table-body-td">{{ item.approved_currency }}</td>
                            <td class="db-table-body-td">{{ item.available_currency }}</td>
                            <td class="db-table-body-td">{{ item.utilized_currency }}</td>
                            <td class="db-table-body-td">{{ item.starts_at || "--" }}</td>
                            <td class="db-table-body-td">{{ item.due_at || "--" }}</td>
                            <td class="db-table-body-td">
                                <router-link :to="{ name: 'admin.lendingPortfolio.show', params: { id: item.id } }" class="text-primary font-semibold">فتح الملف</router-link>
                            </td>
                        </tr>
                    </tbody>
                    <tbody class="db-table-body" v-else>
                        <tr class="db-table-body-tr">
                            <td class="db-table-body-td text-center" colspan="8">لا توجد عمليات تمويل معتمدة حتى الآن.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";

export default {
    name: "LendingPortfolioListComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
        };
    },
    computed: {
        portfolio: function () {
            return this.$store.getters["creditApplicationReview/portfolio"];
        },
    },
    mounted() {
        this.loading.isActive = true;
        this.$store.dispatch("creditApplicationReview/portfolio", { paginate: 0 }).finally(() => {
            this.loading.isActive = false;
        });
    },
    methods: {
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
            return status;
        },
    },
};
</script>
