const FinancialInstitutionEmployeeComponent = () => import("../../components/admin/financialInstitutionEmployees/FinancialInstitutionEmployeeComponent");
const FinancialInstitutionEmployeeListComponent = () => import("../../components/admin/financialInstitutionEmployees/FinancialInstitutionEmployeeListComponent");

export default [
    {
        path: "/admin/financial-institution-employees",
        component: FinancialInstitutionEmployeeComponent,
        name: "admin.financialInstitutionEmployees",
        redirect: { name: "admin.financialInstitutionEmployees.list" },
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "employees",
            breadcrumb: "financial_institution_employees",
        },
        children: [
            {
                path: "",
                component: FinancialInstitutionEmployeeListComponent,
                name: "admin.financialInstitutionEmployees.list",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "employees",
                    breadcrumb: "",
                },
            },
        ],
    },
];
