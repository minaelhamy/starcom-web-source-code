const LendingPortfolioComponent = () => import("../../components/admin/lendingPortfolio/LendingPortfolioComponent");
const LendingPortfolioListComponent = () => import("../../components/admin/lendingPortfolio/LendingPortfolioListComponent");
const LendingPortfolioShowComponent = () => import("../../components/admin/lendingPortfolio/LendingPortfolioShowComponent");

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
            {
                path: ":id",
                component: LendingPortfolioShowComponent,
                name: "admin.lendingPortfolio.show",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "lending-portfolio/show",
                    breadcrumb: "lending_portfolio",
                },
            },
        ],
    },
];
