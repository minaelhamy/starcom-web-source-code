const CreditRequestComponent = () => import("../../components/admin/creditRequests/CreditRequestComponent");
const CreditRequestListComponent = () => import("../../components/admin/creditRequests/CreditRequestListComponent");
const CreditRequestShowComponent = () => import("../../components/admin/creditRequests/CreditRequestShowComponent");

export default [
    {
        path: "/admin/credit-requests",
        component: CreditRequestComponent,
        name: "admin.creditRequests",
        redirect: { name: "admin.creditRequests.list" },
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "credit-requests",
            breadcrumb: "credit_requests",
        },
        children: [
            {
                path: "",
                component: CreditRequestListComponent,
                name: "admin.creditRequests.list",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "credit-requests",
                    breadcrumb: "",
                },
            },
            {
                path: ":id",
                component: CreditRequestShowComponent,
                name: "admin.creditRequests.show",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "credit-requests/show",
                    breadcrumb: "credit_requests",
                },
            },
        ],
    },
];
