import { createRouter, createWebHistory } from "vue-router";
import DashboardComponent from "../components/admin/dashboard/DashboardComponent";
import ExceptionComponent from "../components/exception/ExceptionComponent.vue";
import NotFoundComponent from "../components/exception/NotFoundComponent.vue";
import ENV from "../config/env";
import roleEnum from "../enums/modules/roleEnum";
import appService from "../services/appService";
import store from "../store";
import administratorRoutes from "./modules/administratorRoutes";
import authRoutes from "./modules/authRoutes";
import couponRoutes from "./modules/couponRoutes";
import creditRequestRoutes from "./modules/creditRequestRoutes";
import creditBalanceReportRoutes from "./modules/creditBalanceReportRoutes";
import customerRoutes from "./modules/customerRoutes";
import damageRoutes from "./modules/damageRoutes";
import employeeRoutes from "./modules/employeeRoutes";
import financialInstitutionRoutes from "./modules/financialInstitutionRoutes";
import frontendRoutes from "./modules/frontendRoutes";
import lendingPortfolioRoutes from "./modules/lendingPortfolioRoutes";
import onlineOrderRoutes from "./modules/onlineOrderRoutes";
import posOrderRoutes from "./modules/posOrderRoutes";
import posRoutes from "./modules/posRoutes";
import ProductSectionRoutes from "./modules/ProductSectionRoutes";
import productsReportRoutes from "./modules/productsReportRoutes";
import productsRoutes from "./modules/productsRoutes";
import profileRoutes from "./modules/profileRoutes";
import PromotionRoutes from "./modules/PromotionRoutes";
import purchaseRoutes from "./modules/purchaseRoutes";
import pushNotificationRoutes from "./modules/pushNotificationRoutes";
import returnAndRefundRoutes from "./modules/returnAndRefundRoutes";
import returnOrderRoutes from "./modules/returnOrderRoutes";
import reviewRoutes from "./modules/reviewRoutes";
import salesReportRoutes from "./modules/salesReportRoutes";
import settingRoutes from "./modules/settingRoutes";
import stockRoutes from "./modules/stockRoutes";
import subscriberRoutes from "./modules/subscriberRoutes";
import transactionRoutes from "./modules/transactionRoutes";

const baseRoutes = [
    {
        path: "/",
        redirect: { name: "frontend.home" },
        name: "root",
    },
    {
        path: "/:pathMatch(.*)*",
        name: "route.notFound",
        component: NotFoundComponent,
        meta: {
            isFrontend: true,
        },
    },
    {
        path: "/exception",
        name: "route.exception",
        component: ExceptionComponent,
    },
    {
        path: "/admin/dashboard",
        component: DashboardComponent,
        name: "admin.dashboard",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "dashboard",
            breadcrumb: "dashboard",
        },
    },
];

const routes = baseRoutes.concat(
    frontendRoutes,
    authRoutes,
    settingRoutes,
    profileRoutes,
    productsRoutes,
    administratorRoutes,
    customerRoutes,
    employeeRoutes,
    financialInstitutionRoutes,
    transactionRoutes,
    salesReportRoutes,
    creditBalanceReportRoutes,
    creditRequestRoutes,
    lendingPortfolioRoutes,
    pushNotificationRoutes,
    productsRoutes,
    couponRoutes,
    PromotionRoutes,
    ProductSectionRoutes,
    purchaseRoutes,
    stockRoutes,
    returnOrderRoutes,
    damageRoutes,
    onlineOrderRoutes,
    productsReportRoutes,
    posOrderRoutes,
    posRoutes,
    returnAndRefundRoutes,
    subscriberRoutes,
    reviewRoutes
);

const permission = store.getters.authPermission;
appService.recursiveRouter(routes, permission);

const API_URL = ENV.API_URL;
const router = createRouter({
    linkActiveClass: "active",
    mode: "history",
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { left: 0, top: 0 };
    },
});

const lenderLandingPath = function () {
    return "/admin/dashboard";
};

router.beforeEach((to, from, next) => {
    const isAuthenticated = store.getters.authStatus;
    const authInfo = store.getters.authInfo || {};
    const isLender = authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION;

    if (to.meta.auth === true) {
        if (!isAuthenticated) {
            next({ name: "auth.login" });
        } else {
            if (isLender && to.meta.isFrontend === true) {
                next({ path: lenderLandingPath() });
                return;
            }
            if (to.meta.isFrontend === false) {
                if (to.meta.access === false) {
                    next({
                        name: "route.exception",
                    });
                } else {
                    next();
                }
            } else {
                next();
            }
        }
    } else if (
        (to.name === "auth.login" ||
            to.name === "auth.signup" ||
            to.name === "auth.forgotPassword") &&
        isAuthenticated
    ) {
        next(isLender ? { path: lenderLandingPath() } : { name: "frontend.home" });
    } else if (isAuthenticated && isLender && to.meta.isFrontend === true) {
        next({ path: lenderLandingPath() });
    } else {
        next();
    }
});
export default router;
