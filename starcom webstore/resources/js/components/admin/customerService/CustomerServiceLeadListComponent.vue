<template>
    <LoadingComponent :props="loading" />
    <div class="db-card">
        <div class="db-card-header border-none">
            <h3 class="db-card-title">متابعة خدمة العملاء</h3>
        </div>

        <div class="p-4 border-b border-gray-100">
            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="px-3 py-2 rounded-lg text-sm border"
                    :class="activeTab === tab.value ? 'bg-primary text-white border-primary' : 'bg-white text-secondary border-[#E8E8F3]'"
                    @click="changeTab(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <form class="flex flex-col sm:flex-row gap-3" @submit.prevent="search">
                <input
                    v-model="filters.term"
                    type="text"
                    class="db-field-control flex-1"
                    placeholder="ابحث بالاسم أو العنوان أو الهاتف أو الاسم رباعي أو الرقم القومي"
                />
                <button type="submit" class="db-btn text-white bg-primary py-2">بحث</button>
                <button type="button" class="db-btn text-white bg-gray-600 py-2" @click="clearSearch">مسح</button>
            </form>
        </div>

        <div class="p-4 space-y-3">
            <router-link
                v-for="lead in leads"
                :key="lead.id"
                :to="{ name: 'admin.customerServiceLeads.show', params: { id: lead.id } }"
                class="block rounded-lg border border-[#E8E8F3] p-4 bg-white"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-heading">{{ lead.user?.name || '--' }}</h4>
                        <p class="text-sm text-secondary mt-1">{{ lead.user?.phone || '--' }}</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded bg-orange-50 text-primary">{{ lead.status_label }}</span>
                </div>
                <p class="text-sm text-secondary mt-3">{{ lead.user?.address || 'لا يوجد عنوان مسجل' }}</p>
                <div class="grid grid-cols-2 gap-3 mt-3 text-xs text-secondary">
                    <div>
                        <span class="block">المدينة</span>
                        <strong class="text-heading">{{ lead.user?.city || '--' }}</strong>
                    </div>
                    <div>
                        <span class="block">آخر متابعة</span>
                        <strong class="text-heading">{{ lead.last_contacted_at || '--' }}</strong>
                    </div>
                    <div>
                        <span class="block">المتابعة القادمة</span>
                        <strong class="text-heading">{{ lead.next_follow_up_at || '--' }}</strong>
                    </div>
                    <div>
                        <span class="block">الملاحظات</span>
                        <strong class="text-heading">{{ lead.latest_note || '--' }}</strong>
                    </div>
                </div>
            </router-link>

            <div v-if="!leads.length" class="rounded-lg border border-dashed border-[#E8E8F3] p-6 text-center text-secondary">
                لا توجد عملاء في هذا التصنيف حالياً.
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent.vue";

export default {
    name: "CustomerServiceLeadListComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            activeTab: "all",
            filters: {
                term: "",
            },
            tabs: [
                { value: "all", label: "الكل" },
                { value: "callback", label: "إعادة الاتصال" },
                { value: "waiting", label: "في انتظار الأوراق" },
                { value: "refused", label: "غير مهتم / مغلق" },
            ],
        };
    },
    computed: {
        leads() {
            return this.$store.getters["customerServiceCrm/leads"] || [];
        },
    },
    mounted() {
        this.fetchLeads();
    },
    methods: {
        fetchLeads() {
            this.loading.isActive = true;
            this.$store.dispatch("customerServiceCrm/leads", {
                paginate: 0,
                tab: this.activeTab,
                term: this.filters.term.trim(),
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
        changeTab(tab) {
            this.activeTab = tab;
            this.fetchLeads();
        },
        search() {
            this.fetchLeads();
        },
        clearSearch() {
            this.filters.term = "";
            this.fetchLeads();
        },
    },
};
</script>
