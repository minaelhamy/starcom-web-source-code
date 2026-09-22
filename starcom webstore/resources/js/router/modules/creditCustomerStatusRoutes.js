const CreditCustomerStatusComponent = () => import('../../components/admin/creditCustomerStatus/CreditCustomerStatusComponent');

export default [{
    path: '/admin/credit-customer-status',
    component: CreditCustomerStatusComponent,
    name: 'admin.credit-customer-status',
    meta: {
        isFrontend: false,
        auth: true,
        permissionUrl: 'credit-customer-status',
        breadcrumb: 'credit_customer_status',
    },
}];
