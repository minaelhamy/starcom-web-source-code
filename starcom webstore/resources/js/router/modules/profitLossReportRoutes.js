const ProfitLossReportComponent = () => import('../../components/admin/profitLossReport/ProfitLossReportComponent');

export default [{
    path: '/admin/profit-loss-report',
    component: ProfitLossReportComponent,
    name: 'admin.profit-loss-report',
    meta: {
        isFrontend: false,
        auth: true,
        permissionUrl: 'profit-loss-report',
        breadcrumb: 'profit_loss_report',
    },
}];
