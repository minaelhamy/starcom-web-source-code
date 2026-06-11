const OnlineOrderComponent = () => import("../../components/admin/onlineOrders/OnlineOrderComponent");
const OnlineOrderListComponent = () => import("../../components/admin/onlineOrders/OnlineOrderListComponent");
const OnlineOrderShowComponent = () => import("../../components/admin/onlineOrders/OnlineOrderShowComponent");
const PosComponent = () => import("../../components/admin/pos/PosComponent");

export default [
    {
        path: '/admin/online-orders',
        component: OnlineOrderComponent,
        name: 'admin.order',
        redirect: {name: 'admin.order.list'},
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: 'online-orders',
            breadcrumb: 'online_orders'
        },
        children: [
            {
                path: '',
                component: OnlineOrderListComponent,
                name: 'admin.order.list',
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: 'online-orders',
                    breadcrumb: ''
                },
            },
            {
                path: "show/:id",
                component: OnlineOrderShowComponent,
                name: "admin.order.show",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "online-orders",
                    breadcrumb: "view",
                },
            },
            {
                path: "edit/:id",
                component: PosComponent,
                name: "admin.order.edit",
                meta: {
                    isFrontend: false,
                    auth: true,
                    permissionUrl: "online-orders",
                    breadcrumb: "edit",
                },
            }
        ]
    }
]
