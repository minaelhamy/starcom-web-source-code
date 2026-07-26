import axios from "axios";
import appService from "../../services/appService";

export const customerServiceCrm = {
    namespaced: true,
    state: {
        leads: [],
        page: {},
        pagination: [],
        show: {},
        dashboard: {},
        reports: {
            agents: [],
        },
    },
    getters: {
        leads: (state) => state.leads,
        page: (state) => state.page,
        pagination: (state) => state.pagination,
        show: (state) => state.show,
        dashboard: (state) => state.dashboard,
        reports: (state) => state.reports,
    },
    actions: {
        leads(context, payload) {
            return new Promise((resolve, reject) => {
                let url = "admin/customer-service-leads";
                if (payload) {
                    url += appService.requestHandler(payload);
                }
                axios.get(url).then((res) => {
                    if (typeof payload?.vuex === "undefined" || payload.vuex === true) {
                        context.commit("leads", res.data.data);
                        context.commit("page", res.data.meta);
                        context.commit("pagination", res.data);
                    }
                    resolve(res);
                }).catch(reject);
            });
        },
        show(context, payload) {
            return new Promise((resolve, reject) => {
                axios.get(`admin/customer-service-leads/show/${payload}`).then((res) => {
                    context.commit("show", res.data.data);
                    resolve(res);
                }).catch(reject);
            });
        },
        updateStatus(context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`admin/customer-service-leads/status/${payload.id}`, payload.form).then((res) => {
                    context.commit("show", res.data.data);
                    resolve(res);
                }).catch(reject);
            });
        },
        updateProfile(context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`admin/customer-service-leads/profile/${payload.id}`, payload.form).then((res) => {
                    context.commit("show", res.data.data);
                    resolve(res);
                }).catch(reject);
            });
        },
        submitApplication(context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`admin/customer-service-leads/application/${payload.id}`, payload.form, {
                    headers: { "Content-Type": "multipart/form-data" },
                }).then((res) => {
                    context.commit("show", res.data.data);
                    resolve(res);
                }).catch(reject);
            });
        },
        dashboard(context) {
            return new Promise((resolve, reject) => {
                axios.get("admin/customer-service-leads/dashboard-summary").then((res) => {
                    context.commit("dashboard", res.data.data);
                    resolve(res);
                }).catch(reject);
            });
        },
        reports(context, payload) {
            return new Promise((resolve, reject) => {
                let url = "admin/customer-service-leads/report-summary";
                if (payload) {
                    url += appService.requestHandler(payload);
                }
                axios.get(url).then((res) => {
                    context.commit("reports", res.data.data);
                    resolve(res);
                }).catch(reject);
            });
        },
        redistribute(context, payload = {}) {
            return new Promise((resolve, reject) => {
                axios.post("admin/customer-service-leads/redistribute", payload).then(resolve).catch(reject);
            });
        },
    },
    mutations: {
        leads(state, payload) {
            state.leads = payload;
        },
        page(state, payload) {
            if (payload) {
                state.page = {
                    from: payload.from,
                    to: payload.to,
                    total: payload.total,
                };
            }
        },
        pagination(state, payload) {
            state.pagination = payload;
        },
        show(state, payload) {
            state.show = payload;
        },
        dashboard(state, payload) {
            state.dashboard = payload || {};
        },
        reports(state, payload) {
            state.reports = payload || { agents: [] };
        },
    },
};
