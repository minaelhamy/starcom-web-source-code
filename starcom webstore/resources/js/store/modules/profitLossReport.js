import axios from 'axios';
import appService from '../../services/appService';

export const profitLossReport = {
    namespaced: true,
    state: { lists: [], page: {}, pagination: [], summary: {} },
    getters: {
        lists: state => state.lists,
        page: state => state.page,
        pagination: state => state.pagination,
        summary: state => state.summary,
    },
    actions: {
        lists(context, payload) {
            let url = 'admin/profit-loss-report' + appService.requestHandler(payload);
            return axios.get(url).then(res => {
                context.commit('lists', res.data.data);
                context.commit('page', res.data.meta);
                context.commit('pagination', res.data);
                return res;
            });
        },
        summary(context, payload) {
            return axios.get('admin/profit-loss-report/summary' + appService.requestHandler(payload)).then(res => {
                context.commit('summary', res.data.data);
                return res;
            });
        },
        export(context, payload) {
            return axios.get('admin/profit-loss-report/export' + appService.requestHandler(payload), { responseType: 'blob' });
        },
        exportPdf(context, payload) {
            return axios.get('admin/profit-loss-report/export-pdf' + appService.requestHandler(payload), { responseType: 'blob' });
        },
    },
    mutations: {
        lists(state, payload) { state.lists = payload; },
        page(state, payload) { state.page = payload || {}; },
        pagination(state, payload) { state.pagination = payload; },
        summary(state, payload) { state.summary = payload; },
    },
};
