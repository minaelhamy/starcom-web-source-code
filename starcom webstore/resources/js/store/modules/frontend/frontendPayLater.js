import axios from "axios";
import appService from "../../../services/appService";

export const frontendPayLater = {
    namespaced: true,
    state: {
        applications: [],
        walletTransactions: [],
        page: {},
        pagination: [],
        walletPage: {},
        walletPagination: [],
        summary: {},
    },
    getters: {
        applications: function (state) {
            return state.applications;
        },
        walletTransactions: function (state) {
            return state.walletTransactions;
        },
        page: function (state) {
            return state.page;
        },
        pagination: function (state) {
            return state.pagination;
        },
        walletPage: function (state) {
            return state.walletPage;
        },
        walletPagination: function (state) {
            return state.walletPagination;
        },
        summary: function (state) {
            return state.summary;
        },
    },
    actions: {
        summary: function (context) {
            return new Promise((resolve, reject) => {
                axios.get("frontend/pay-later/summary").then((res) => {
                    context.commit("summary", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        applications: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = "frontend/pay-later/applications";
                if (payload) {
                    url += appService.requestHandler(payload);
                }
                axios.get(url).then((res) => {
                    if (typeof payload?.vuex === "undefined" || payload.vuex === true) {
                        context.commit("applications", res.data.data);
                        if (res.data.meta) {
                            context.commit("pagination", res.data);
                            context.commit("page", res.data.meta);
                        }
                    }
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        walletTransactions: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = "frontend/pay-later/wallet-transactions";
                if (payload) {
                    url += appService.requestHandler(payload);
                }
                axios.get(url).then((res) => {
                    if (typeof payload?.vuex === "undefined" || payload.vuex === true) {
                        context.commit("walletTransactions", res.data.data);
                        if (res.data.meta) {
                            context.commit("walletPagination", res.data);
                            context.commit("walletPage", res.data.meta);
                        }
                    }
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        save: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post("frontend/pay-later/applications", payload, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                }).then((res) => {
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        summary: function (state, payload) {
            state.summary = payload;
        },
        applications: function (state, payload) {
            state.applications = payload;
        },
        pagination: function (state, payload) {
            state.pagination = payload;
        },
        page: function (state, payload) {
            if (payload) {
                state.page = {
                    from: payload.from,
                    to: payload.to,
                    total: payload.total,
                };
            }
        },
        walletTransactions: function (state, payload) {
            state.walletTransactions = payload;
        },
        walletPagination: function (state, payload) {
            state.walletPagination = payload;
        },
        walletPage: function (state, payload) {
            if (payload) {
                state.walletPage = {
                    from: payload.from,
                    to: payload.to,
                    total: payload.total,
                };
            }
        },
    },
};
