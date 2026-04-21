<template>
    <aside class="db-sidebar">
        <div class="db-sidebar-header">
            <router-link class="w-24 h-12 flex items-center" :to="backendHomeRoute">
                <img :src="resolvedThemeLogo" alt="logo" class="w-full h-full object-cover object-center" @error="applyLogoFallback">
            </router-link>
            <button @click="closeSidebar" class="fa-solid fa-xmark xmark-btn close-db-menu"></button>
        </div>
        <nav class="db-sidebar-nav">
            <ul class="db-sidebar-nav-list" v-if="menus.length > 0" v-for="menu in menus" :key="menu">
                <li class="db-sidebar-nav-item" v-if="menu.url === '#'" @click.prevent="sidebarActive($event)">
                    <a href="javascript:void(0);" class="db-sidebar-nav-title">
                        {{ $t('menu.' + menu.language) }}
                    </a>
                </li>

                <li class="db-sidebar-nav-item" v-else @click.prevent="sidebarActive($event)">
                    <router-link :to="'/admin/' + menu.url" class="db-sidebar-nav-menu">
                        <i class="text-sm" :class="menu.icon"></i>
                        <span class="text-base flex-auto">{{ $t('menu.' + menu.language) }}</span>
                    </router-link>
                </li>

                <li class="db-sidebar-nav-item" v-if="menu.children" v-for="children in menu.children" @click.prevent="sidebarActive($event)">
                    <router-link :to="'/admin/' + children.url" class="db-sidebar-nav-menu">
                        <i class="text-sm" :class="children.icon"></i>
                        <span class="text-base flex-auto">{{ $t('menu.' + children.language) }}</span>
                    </router-link>
                </li>
            </ul>
        </nav>
    </aside>
</template>

<script>
import appService from "../../../services/appService";
import roleEnum from "../../../enums/modules/roleEnum";
export default {
    name: "BackendMenuComponent",
    data: function () {
        return {
            activeParentId: 1,
            activeChildId: 0,
            defaultThemeLogo: "/images/required/theme-logo.png",
        }
    },
    computed: {
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        setting: function () {
            return this.$store.getters['frontendSetting/lists'];
        },
        resolvedThemeLogo: function () {
            return this.setting.theme_logo || this.defaultThemeLogo;
        },
        backendHomeRoute: function () {
            const defaultMenu = this.$store.getters.authDefaultMenu || {};

            if (this.authInfo.role_id && this.authInfo.role_id !== roleEnum.CUSTOMER) {
                return `/admin/${defaultMenu.url || "dashboard"}`;
            }

            return { name: "frontend.home" };
        },
        menus: function () {
            const menus = Array.isArray(this.$store.getters.authMenu) ? [...this.$store.getters.authMenu] : [];

            if (this.authInfo.role_id === roleEnum.FINANCIAL_INSTITUTION) {
                const hasDashboardMenu = menus.some((menu) => menu?.url === "dashboard");

                if (!hasDashboardMenu) {
                    menus.unshift({
                        name: "Dashboard",
                        language: "dashboard",
                        url: "dashboard",
                        icon: "lab lab-line-dashboard",
                    });
                }
            }

            return menus;
        }
    },

    mounted() {
        this.defaultSidebarActive();

    },
    methods: {
        sidebarActive: function (e) {
            const activeMenu = document.querySelector('.db-sidebar-nav-item.active');
            if (activeMenu) {
                activeMenu.classList.remove('active');
            }
            e?.currentTarget?.classList?.add('active');
        },
        applyLogoFallback: function (event) {
            event.target.src = this.defaultThemeLogo;
        },
        defaultSidebarActive: function () {
            if (document?.querySelector(".db-sidebar-nav-menu")?.classList?.contains("active")) {
                document?.querySelector('.db-sidebar-nav-menu')?.parentElement?.classList?.add('active');
            } else {
                document?.querySelector('.router-link-exact-active')?.parentElement?.classList?.add('active');
            }
        },
        closeSidebar : function(){
            return appService.closeSidebar()
        }
    }
}
</script>
