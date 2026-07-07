const PaymentCollectionReportComponent = () => import("../../components/admin/paymentCollectionReport/PaymentCollectionReportComponent");

export default [
    {
        path: "/admin/payment-collection-report",
        component: PaymentCollectionReportComponent,
        name: "admin.payment-collection-report",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "payment-collection-report",
            breadcrumb: "payment_collection_report",
        },
    },
];
