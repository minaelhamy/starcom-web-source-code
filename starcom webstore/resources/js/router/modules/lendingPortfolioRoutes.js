const LendingPortfolioComponent = () => import("../../components/admin/lendingPortfolio/LendingPortfolioComponent");
const LendingPortfolioListComponent = () => import("../../components/admin/lendingPortfolio/LendingPortfolioListComponent");

export default [
    {
        path: "/admin/lending-portfolio",
        component: LendingPortfolioComponent,
        name: "admin.lendingPortfolio",
        redirect: { name: "admin.lendingPortfolio.list" },
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "lending-portfolio",
            breadcrumb: "lending_portfolio",
        },
        children: [
            {
                path: "",
                component: LendingPortfolioListComponent,
                name: "admin.lendingPortfolio.list",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "lending-portfolio",
                    breadcrumb: "",
                },
            },
        ],
    },
];
