<template>
    <LoadingComponent :props="loading" />
    <div class="row">
        <div class="col-12"><BreadcrumbComponent /></div>
        <div class="col-12">
            <div class="db-card db-tab-div active">
                <div class="db-card-header border">
                    <h3 class="db-card-title">{{ $t('menu.profit_loss_report') }}</h3>
                    <div class="db-card-filter">
                        <TableLimitComponent :method="list" :search="searchParams" :page="paginationPage" />
                        <FilterComponent @click.prevent="handleSlide('profit-loss-filter')" />
                        <button class="db-btn py-2 text-white bg-primary" type="button" @click="download('xlsx')">Excel</button>
                        <button class="db-btn py-2 text-white bg-primary" type="button" @click="download('pdf')">PDF</button>
                    </div>
                </div>

                <div class="table-filter-div" id="profit-loss-filter">
                    <form class="p-4 sm:p-5 mb-5 w-full d-block" @submit.prevent="search">
                        <div class="row">
                            <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                                <label class="db-field-title after:hidden">{{ $t('label.name') }}</label>
                                <input v-model="searchParams.name" type="text" class="db-field-control" />
                            </div>
                            <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                                <label class="db-field-title">{{ $t('label.category') }}</label>
                                <vue-select class="db-field-control f-b-custom-select" v-model="searchParams.product_category_id" :options="productCategories" label-by="option" value-by="id" :closeOnSelect="true" :searchable="true" :clearOnClose="true" placeholder="--" search-placeholder="--" />
                            </div>
                            <div class="col-12 sm:col-6 md:col-4 xl:col-3">
                                <label class="db-field-title after:hidden">{{ $t('label.date') }}</label>
                                <Datepicker v-model="dateRange" hideInputIcon autoApply :enableTimePicker="false" :range="true" @update:modelValue="handleDate" />
                            </div>
                            <div class="col-12"><div class="flex flex-wrap gap-3 mt-4">
                                <button class="db-btn py-2 text-white bg-primary"><i class="lab lab-line-search lab-font-size-16"></i><span>{{ $t('button.search') }}</span></button>
                                <button class="db-btn py-2 text-white bg-gray-600" type="button" @click="clear"><i class="lab lab-line-cross lab-font-size-22"></i><span>{{ $t('button.clear') }}</span></button>
                            </div></div>
                        </div>
                    </form>
                </div>

                <div class="row px-5 mt-5 mb-5">
                    <div v-for="card in summaryCards" :key="card.label" class="col-12 sm:col-6 md:col-4 xl:col-4 mb-4">
                        <div class="border p-4 rounded-lg"><h3 class="font-medium text-sm mb-1">{{ card.label }}</h3><h4 class="font-bold text-lg text-[#6E7191]">{{ card.value }}</h4></div>
                    </div>
                </div>

                <div class="db-table-responsive"><table class="db-table stripe">
                    <thead class="db-table-head"><tr class="db-table-head-tr">
                        <th class="db-table-head-th">{{ $t('label.name') }}</th><th class="db-table-head-th">{{ $t('label.category') }}</th><th class="db-table-head-th">{{ $t('label.quantity') }}</th><th class="db-table-head-th">{{ $t('label.unit_cost') }}</th><th class="db-table-head-th">{{ $t('label.total_cost') }}</th><th class="db-table-head-th">{{ $t('label.average_selling_price') }}</th><th class="db-table-head-th">{{ $t('label.sales_total') }}</th><th class="db-table-head-th">{{ $t('label.gross_profit') }}</th>
                    </tr></thead>
                    <tbody class="db-table-body" v-if="reports.length"><tr class="db-table-body-tr" v-for="report in reports" :key="report.id">
                        <td class="db-table-body-td">{{ report.name }}</td><td class="db-table-body-td">{{ report.category_name || '--' }}</td><td class="db-table-body-td">{{ report.sold_quantity }}</td><td class="db-table-body-td">{{ report.unit_cost_currency }}</td><td class="db-table-body-td">{{ report.cost_total_currency }}</td><td class="db-table-body-td">{{ report.selling_unit_price_currency }}</td><td class="db-table-body-td">{{ report.sales_total_currency }}</td><td class="db-table-body-td">{{ report.gross_profit_currency }}</td>
                    </tr></tbody>
                    <tbody class="db-table-body" v-else><tr class="db-table-body-tr"><td class="db-table-body-td text-center" colspan="8">{{ $t('message.no_data_found') }}</td></tr></tbody>
                </table></div>
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-6" v-if="reports.length">
                    <PaginationSMBox :pagination="pagination" :method="list" />
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between"><PaginationTextComponent :props="{ page: paginationPage }" /><PaginationBox :pagination="pagination" :method="list" /></div>
                </div>
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
import TableLimitComponent from '../components/TableLimitComponent';
import FilterComponent from '../components/buttons/collapse/FilterComponent';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import appService from '../../../services/appService';
import alertService from '../../../services/alertService';

export default {
    name: 'ProfitLossReportComponent',
    components: { BreadcrumbComponent, LoadingComponent, PaginationTextComponent, PaginationBox, PaginationSMBox, TableLimitComponent, FilterComponent, Datepicker },
    data() {
        return { loading: { isActive: false }, dateRange: null, productCategories: [], searchParams: { paginate: 1, page: 1, per_page: 10, order_column: 'products.name', order_type: 'asc', name: '', product_category_id: null, from_date: '', to_date: '' } };
    },
    computed: {
        reports() { return this.$store.getters['profitLossReport/lists']; },
        pagination() { return this.$store.getters['profitLossReport/pagination']; },
        paginationPage() { return this.$store.getters['profitLossReport/page']; },
        summary() { return this.$store.getters['profitLossReport/summary']; },
        summaryCards() { return [
            { label: this.$t('label.total_products'), value: this.summary.total_products || 0 },
            { label: this.$t('label.quantity'), value: this.summary.total_quantity || 0 },
            { label: this.$t('label.total_cost'), value: this.summary.total_cost_currency || '0.00' },
            { label: this.$t('label.sales_total'), value: this.summary.total_sales_currency || '0.00' },
            { label: this.$t('label.gross_profit'), value: this.summary.gross_profit_currency || '0.00' },
        ]; },
    },
    mounted() {
        this.list();
        this.$store.dispatch('productCategory/depthTrees').then(res => { this.productCategories = res.data.data; });
    },
    methods: {
        handleSlide(id) { return appService.handleSlide(id); },
        handleDate(range) {
            this.searchParams.from_date = range ? this.formatDate(range[0]) : '';
            this.searchParams.to_date = range ? this.formatDate(range[1]) : '';
        },
        formatDate(value) {
            const date = value instanceof Date ? value : new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${date.getFullYear()}-${month}-${day}`;
        },
        search() { this.list(1); },
        clear() { this.dateRange = null; this.searchParams = { paginate: 1, page: 1, per_page: 10, order_column: 'products.name', order_type: 'asc', name: '', product_category_id: null, from_date: '', to_date: '' }; this.list(1); },
        list(page = 1) {
            this.loading.isActive = true; this.searchParams.page = page;
            Promise.all([this.$store.dispatch('profitLossReport/lists', this.searchParams), this.$store.dispatch('profitLossReport/summary', this.searchParams)])
                .catch(error => alertService.error(error.response?.data?.message || 'Unable to load the report.'))
                .finally(() => { this.loading.isActive = false; });
        },
        download(type) {
            this.loading.isActive = true;
            const action = type === 'pdf' ? 'profitLossReport/exportPdf' : 'profitLossReport/export';
            const mime = type === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            this.$store.dispatch(action, this.searchParams).then(res => {
                const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([res.data], { type: mime })); link.download = `profit-loss-report.${type}`; link.click(); URL.revokeObjectURL(link.href);
            }).catch(err => alertService.error(err.response?.data?.message || 'Unable to export the report.')).finally(() => { this.loading.isActive = false; });
        },
    },
};
</script>
