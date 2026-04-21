<template>

    <LoadingComponent :props="loading" />
    <p class="h-screen"></p>

</template>

<script>
import router from "../../../router";
import LoadingComponent from "../components/LoadingComponent";
import alertService from "../../../services/alertService";
import appService from "../../../services/appService";
import roleEnum from "../../../enums/modules/roleEnum";

export default {

    name: "SocialLoginComponent",
    components: {
        LoadingComponent
    },
    data() {
        return {
            loading: {
                isActive: true,
            }
        };
    },
    computed: {
        carts: function () {
            return this.$store.getters['frontendCart/lists'];
        },

    },
    created() {
        this.loading.isActive = true;
        if (this.$route.query.code) {
            this.loading.isActive = true;
            this.$store.dispatch("verifySocialLogin", { code: { code: this.$route.query.code }, provider: 'google' }).then(res => {
                this.loading.isActive = false;
                alertService.success(res.data.message);
                this.$store.dispatch("frontendWishlist/lists").then().catch();
                this.redirectAfterLogin();
                setTimeout(() => {
                    appService.recursiveRouter(router.options.routes, this.$store.getters.authPermission);
                }, 1000);
            }).catch((error) => {
                this.loading.isActive = false;
                alertService.error(error.response.data.message);
            });
        }
    },
    methods: {
        redirectAfterLogin: function () {
            const authInfo = this.$store.getters.authInfo || {};
            const defaultMenu = this.$store.getters.authDefaultMenu || {};

            if (authInfo.role_id && authInfo.role_id !== roleEnum.CUSTOMER) {
                const targetPath = authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION
                    ? "/admin/dashboard"
                    : `/admin/${defaultMenu.url || "dashboard"}`;
                router.push({ path: targetPath });
                return;
            }

            if (this.carts.length > 0) {
                router.push({ name: "frontend.checkout" });
                return;
            }

            router.push({ name: "frontend.home" });
        },
    }
}
</script>
