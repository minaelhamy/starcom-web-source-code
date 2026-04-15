const FinancialInstitutionComponent = () => import("../../components/admin/financialInstitutions/FinancialInstitutionComponent");
const FinancialInstitutionListComponent = () => import("../../components/admin/financialInstitutions/FinancialInstitutionListComponent");

export default [
    {
        path: "/admin/financial-institutions",
        component: FinancialInstitutionComponent,
        name: "admin.financialInstitutions",
        redirect: { name: "admin.financialInstitutions.list" },
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "financial-institutions",
            breadcrumb: "financial_institutions",
        },
        children: [
            {
                path: "",
                component: FinancialInstitutionListComponent,
                name: "admin.financialInstitutions.list",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "financial-institutions",
                    breadcrumb: "",
                },
            },
        ],
    },
];
