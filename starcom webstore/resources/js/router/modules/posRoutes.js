const PosComponent = () => import ("../../components/admin/pos/PosComponent");

export default [
    {
        path: "/admin/pos",
        component: PosComponent,
        name: "admin.pos",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "pos",
        },
    },
    {
        path: "/admin/pos/edit/:id",
        component: PosComponent,
        name: "admin.pos.edit",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "pos-orders",
        },
    },
];
