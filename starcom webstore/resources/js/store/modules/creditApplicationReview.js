import axios from "axios";
import appService from "../../services/appService";

export const creditApplicationReview = {
    namespaced: true,
    state: {
        lists: [],
        page: {},
        pagination: [],
        show: {},
        portfolio: [],
        portfolioPage: {},
        portfolioPagination: [],
        portfolioShow: {},
    },
    getters: {
        lists: function (state) {
            return state.lists;
        },
        page: function (state) {
            return state.page;
        },
        pagination: function (state) {
            return state.pagination;
        },
        show: function (state) {
            return state.show;
        },
        portfolio: function (state) {
            return state.portfolio;
        },
        portfolioPage: function (state) {
            return state.portfolioPage;
        },
        portfolioPagination: function (state) {
            return state.portfolioPagination;
        },
        portfolioShow: function (state) {
            return state.portfolioShow;
        },
    },
    actions: {
        lists: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = "admin/credit-application";
                if (payload) {
                    url += appService.requestHandler(payload);
                }
                axios.get(url).then((res) => {
                    if (typeof payload?.vuex === "undefined" || payload.vuex === true) {
                        context.commit("lists", res.data.data);
                        context.commit("page", res.data.meta);
                        context.commit("pagination", res.data);
                    }
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        show: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.get(`admin/credit-application/show/${payload}`).then((res) => {
                    context.commit("show", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        approve: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`admin/credit-application/approve/${payload.id}`, payload.form).then((res) => {
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        decline: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`admin/credit-application/decline/${payload.id}`, payload.form).then((res) => {
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        portfolio: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = "admin/credit-application/portfolio";
                if (payload) {
                    url += appService.requestHandler(payload);
                }
                axios.get(url).then((res) => {
                    if (typeof payload?.vuex === "undefined" || payload.vuex === true) {
                        context.commit("portfolio", res.data.data);
                        context.commit("portfolioPage", res.data.meta);
                        context.commit("portfolioPagination", res.data);
                    }
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        showFacility: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.get(`admin/credit-application/portfolio/show/${payload}`).then((res) => {
                    context.commit("portfolioShow", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        lists: function (state, payload) {
            state.lists = payload;
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
        pagination: function (state, payload) {
            state.pagination = payload;
        },
        show: function (state, payload) {
            state.show = payload;
        },
        portfolio: function (state, payload) {
            state.portfolio = payload;
        },
        portfolioPage: function (state, payload) {
            if (payload) {
                state.portfolioPage = {
                    from: payload.from,
                    to: payload.to,
                    total: payload.total,
                };
            }
        },
        portfolioPagination: function (state, payload) {
            state.portfolioPagination = payload;
        },
        portfolioShow: function (state, payload) {
            state.portfolioShow = payload;
        },
    },
};
