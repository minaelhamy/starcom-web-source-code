const CustomerServiceLeadComponent = () => import("../../components/admin/customerService/CustomerServiceLeadComponent");
const CustomerServiceLeadListComponent = () => import("../../components/admin/customerService/CustomerServiceLeadListComponent");
const CustomerServiceLeadShowComponent = () => import("../../components/admin/customerService/CustomerServiceLeadShowComponent");
const CustomerServiceReportsComponent = () => import("../../components/admin/customerService/CustomerServiceReportsComponent");

export default [
    {
        path: "/admin/customer-service-leads",
        component: CustomerServiceLeadComponent,
        name: "admin.customerServiceLeads",
        redirect: { name: "admin.customerServiceLeads.list" },
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "customer-service-leads",
            breadcrumb: "customer_service_leads",
        },
        children: [
            {
                path: "",
                component: CustomerServiceLeadListComponent,
                name: "admin.customerServiceLeads.list",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "customer-service-leads",
                    breadcrumb: "",
                },
            },
            {
                path: ":id",
                component: CustomerServiceLeadShowComponent,
                name: "admin.customerServiceLeads.show",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "customer-service-leads",
                    breadcrumb: "customer_service_leads",
                },
            },
        ],
    },
    {
        path: "/admin/customer-service-reports",
        component: CustomerServiceReportsComponent,
        name: "admin.customerServiceReports",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "customer-service-reports",
            breadcrumb: "customer_service_reports",
        },
    },
];
