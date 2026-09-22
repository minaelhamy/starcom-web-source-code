<template>
    <LoadingComponent :props="loading" />
    <div class="row">
        <div class="col-12"><BreadcrumbComponent /></div>
        <div class="col-12">
            <div class="db-card">
                <div class="db-card-header border-none">
                    <h3 class="db-card-title">حالة العملاء الائتمانية</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-4 pt-4">
                    <div class="rounded border border-[#E8E8F3] p-4"><p class="text-sm text-secondary">إجمالي مقدمي طلبات التمويل</p><h5 class="text-xl font-semibold text-heading">{{ summary.total || 0 }}</h5></div>
                    <div class="rounded border border-[#E8E8F3] p-4"><p class="text-sm text-secondary">القائمة السوداء</p><h5 class="text-xl font-semibold text-heading">{{ summary.blacklisted || 0 }}</h5></div>
                    <div class="rounded border border-[#E8E8F3] p-4"><p class="text-sm text-secondary">عملاء مميزون</p><h5 class="text-xl font-semibold text-heading">{{ summary.top_customers || 0 }}</h5></div>
                </div>

                <div class="p-4 border-b border-gray-100">
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button v-for="tab in tabs" :key="tab.value" type="button" class="db-btn py-2" :class="filter === tab.value ? 'text-white bg-primary' : 'text-primary bg-primary/10'" @click="selectTab(tab.value)">{{ tab.label }}</button>
                    </div>
                    <form class="flex flex-col md:flex-row gap-3 items-start md:items-end" @submit.prevent="list(1)">
                        <div class="w-full md:flex-1"><label class="db-field-title after:hidden">البحث بالعميل أو الاسم رباعي أو الرقم القومي أو الهاتف</label><input v-model="params.term" class="db-field-control" type="text" placeholder="اكتب الاسم أو الرقم القومي أو الهاتف" /></div>
                        <div class="flex gap-2"><button class="db-btn py-2 text-white bg-primary" type="submit"><i class="lab lab-line-search lab-font-size-16"></i><span>بحث</span></button><button class="db-btn py-2 text-white bg-gray-600" type="button" @click="clear"><i class="lab lab-line-cross lab-font-size-22"></i><span>مسح</span></button></div>
                    </form>
                </div>

                <div class="db-table-responsive"><table class="db-table"><thead class="db-table-head"><tr class="db-table-head-tr"><th class="db-table-head-th">العميل</th><th class="db-table-head-th">الاسم رباعي</th><th class="db-table-head-th">الرقم القومي</th><th class="db-table-head-th">آخر طلب</th><th class="db-table-head-th">الحالة</th><th class="db-table-head-th">الإجراءات</th></tr></thead>
                    <tbody v-if="customers.length" class="db-table-body"><tr v-for="customer in customers" :key="customer.id" class="db-table-body-tr"><td class="db-table-body-td"><div class="font-semibold">{{ customer.name }}</div><div class="text-xs text-text">{{ customer.phone || '--' }}</div></td><td class="db-table-body-td">{{ customer.full_name || '--' }}</td><td class="db-table-body-td">{{ customer.national_id_number || '--' }}</td><td class="db-table-body-td"><router-link v-if="customer.latest_credit_application_id" class="text-primary underline" :to="{ name: 'admin.creditRequests.show', params: { id: customer.latest_credit_application_id } }">فتح الطلب</router-link><span v-else>--</span></td><td class="db-table-body-td"><span v-if="customer.is_credit_blacklisted" class="inline-block px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded">قائمة سوداء</span><span v-else-if="customer.is_top_credit_customer" class="inline-block px-2 py-1 text-xs font-semibold text-amber-800 bg-amber-100 rounded">عميل مميز</span><span v-else>عادي</span><p v-if="customer.is_credit_blacklisted && customer.credit_blacklist_reason" class="text-xs text-text mt-2 max-w-xs">{{ customer.credit_blacklist_reason }}</p></td><td class="db-table-body-td"><div class="flex flex-wrap gap-2"><button v-if="customer.is_credit_blacklisted" class="db-btn py-2 text-white bg-gray-600" type="button" @click="setBlacklist(customer, false)">إلغاء الحظر</button><button v-else class="db-btn py-2 text-white bg-red-600" type="button" @click="setBlacklist(customer, true)">قائمة سوداء</button><button v-if="!customer.is_credit_blacklisted && !customer.is_top_credit_customer" class="db-btn py-2 text-white bg-amber-500" type="button" @click="setTopCustomer(customer, true)">تمييز كعميل مميز</button><button v-if="customer.is_top_credit_customer" class="db-btn py-2 text-white bg-gray-600" type="button" @click="setTopCustomer(customer, false)">إزالة التمييز</button></div></td></tr></tbody>
                    <tbody v-else class="db-table-body"><tr class="db-table-body-tr"><td class="db-table-body-td text-center" colspan="6">لا توجد بيانات.</td></tr></tbody>
                </table></div>
                <div v-if="customers.length" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-6"><PaginationSMBox :pagination="pagination" :method="list" /><div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between"><PaginationTextComponent :props="{ page: page }" /><PaginationBox :pagination="pagination" :method="list" /></div></div>
            </div>
        </div>
    </div>
</template>

<script>
import BreadcrumbComponent from '../components/BreadcrumbComponent';
import LoadingComponent from '../components/LoadingComponent';
import PaginationTextComponent from '../components/pagination/PaginationTextComponent';
import PaginationBox from '../components/pagination/PaginationBox';
import PaginationSMBox from '../components/pagination/PaginationSMBox';
import alertService from '../../../services/alertService';

export default {
    name: 'CreditCustomerStatusComponent',
    components: { BreadcrumbComponent, LoadingComponent, PaginationTextComponent, PaginationBox, PaginationSMBox },
    data() {
        return { loading: { isActive: false }, customers: [], pagination: [], page: {}, summary: {}, filter: 'all', params: { paginate: 1, page: 1, per_page: 25, filter: 'all', term: '' } };
    },
    computed: { tabs() { return [{ value: 'all', label: 'كل مقدمي الطلبات' }, { value: 'blacklisted', label: 'القائمة السوداء' }, { value: 'top_customers', label: 'العملاء المميزون' }]; } },
    mounted() { this.list(); this.loadSummary(); },
    methods: {
        selectTab(filter) { this.filter = filter; this.params.filter = filter; this.list(1); },
        clear() { this.params.term = ''; this.list(1); },
        async list(page = 1) {
            this.loading.isActive = true; this.params.page = page;
            try { const response = await axios.get('admin/credit-customer-status', { params: this.params }); this.customers = response.data.data || []; this.pagination = response.data; this.page = response.data.meta || {}; }
            catch (error) { alertService.error(error.response?.data?.message || 'تعذر تحميل العملاء.'); }
            finally { this.loading.isActive = false; }
        },
        async loadSummary() { try { const response = await axios.get('admin/credit-customer-status/summary'); this.summary = response.data.data || {}; } catch (error) { alertService.error(error.response?.data?.message || 'تعذر تحميل الملخص.'); } },
        async setBlacklist(customer, blacklisted) {
            const reason = blacklisted ? window.prompt('سبب الإدراج في القائمة السوداء:') : null;
            if (blacklisted && !reason?.trim()) return;
            await this.update(customer, 'blacklist', { blacklisted, blacklist_reason: reason });
        },
        async setTopCustomer(customer, isTopCreditCustomer) { await this.update(customer, 'top-customer', { is_top_credit_customer: isTopCreditCustomer }); },
        async update(customer, action, payload) {
            this.loading.isActive = true;
            try { await axios.put(`admin/credit-customer-status/${customer.id}/${action}`, payload); await Promise.all([this.list(this.params.page), this.loadSummary()]); alertService.success('تم حفظ حالة العميل الائتمانية.'); }
            catch (error) { alertService.error(error.response?.data?.message || 'تعذر حفظ حالة العميل.'); }
            finally { this.loading.isActive = false; }
        },
    },
};
</script>
