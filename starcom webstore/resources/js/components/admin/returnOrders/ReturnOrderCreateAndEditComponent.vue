<template>
    <LoadingComponent :props="loading" />
    <div class="col-12">
        <form @submit.prevent="save" class="block w-full">
            <div class="db-card mb-6">
                <div class="db-card-header">
                    <h3 class="db-card-title">{{ $t("menu.return_orders") }}</h3>
                </div>
                <div class="db-card-body">
                    <div class="row">
                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title required">{{ $t("label.date") }}</label>
                            <Datepicker
                                hideInputIcon
                                autoApply
                                v-model="props.form.date"
                                :enableTimePicker="true"
                                :is24="false"
                                :monthChangeOnScroll="false"
                                utc="false"
                                :input-class-name="errors.date ? 'invalid' : ''"
                            >
                                <template #am-pm-button="{ toggle, value }">
                                    <button @click="toggle">{{ value }}</button>
                                </template>
                            </Datepicker>
                            <small class="db-field-alert" v-if="errors.date">{{ errors.date[0] }}</small>
                        </div>

                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title">{{ $t("label.reference_no") }}</label>
                            <input name="reference_no" v-model="props.form.reference_no" type="text" class="db-field-control" />
                            <small class="db-field-alert" v-if="errors.reference_no">{{ errors.reference_no[0] }}</small>
                        </div>

                        <div class="form-col-12">
                            <div class="rounded-lg border border-amber-100">
                                <h4 class="w-full px-4 py-3 font-medium rounded-t-lg bg-amber-100 text-amber-600">
                                    {{ $t("label.order_serial_no") }}
                                </h4>
                                <div class="p-5">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="flex-1 min-w-[260px]">
                                            <label class="db-field-title required">{{ $t("label.order_serial_no") }}</label>
                                            <input
                                                v-model="invoiceNumber"
                                                type="text"
                                                class="db-field-control"
                                                :class="errors.order_id ? 'invalid' : ''"
                                                :placeholder="$t('label.order_serial_no')"
                                            />
                                            <small class="db-field-alert" v-if="errors.order_id">{{ errors.order_id[0] }}</small>
                                        </div>
                                        <button type="button" class="db-btn text-white bg-primary" @click="fetchInvoice">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                            <span class="tracking-wide">{{ $t("button.search") }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-col-12" v-if="invoice">
                            <div class="db-table-responsive border rounded-md">
                                <table class="db-table">
                                    <tbody class="db-table-body">
                                        <tr class="db-table-body-tr">
                                            <td class="db-table-body-td font-medium">{{ $t("label.order_serial_no") }}</td>
                                            <td class="db-table-body-td">#{{ invoice.order_serial_no }}</td>
                                            <td class="db-table-body-td font-medium">{{ $t("label.customer") }}</td>
                                            <td class="db-table-body-td">{{ invoice.user?.name }}</td>
                                        </tr>
                                        <tr class="db-table-body-tr">
                                            <td class="db-table-body-td font-medium">{{ $t("label.payment_status") }}</td>
                                            <td class="db-table-body-td">{{ invoice.payment_status === 5 ? "مدفوع" : "غير مدفوع" }}</td>
                                            <td class="db-table-body-td font-medium">{{ $t("label.total") }}</td>
                                            <td class="db-table-body-td">{{ invoice.total_currency_price }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-col-12 sm:form-col-6">
                            <label class="db-field-title">{{ $t("label.attachments") }}</label>
                            <input
                                @change="changeFile"
                                :class="errors.file ? 'invalid' : ''"
                                type="file"
                                ref="fileProperty"
                                accept="image/png , image/jpeg, image/jpg , application/pdf "
                                class="db-field-control cursor-pointer"
                            />
                            <small class="db-field-alert" v-if="errors.file">{{ errors.file[0] }}</small>
                        </div>

                        <div class="form-col-12">
                            <label class="db-field-title">{{ $t("label.products") }}</label>
                            <div class="db-table-responsive border rounded-md">
                                <table class="db-table">
                                    <thead class="db-table-head border-t-0">
                                        <tr class="db-table-head-tr">
                                            <th class="db-table-head-th">{{ $t("label.product") }}</th>
                                            <th class="db-table-head-th">{{ $t("label.unit_cost") }}</th>
                                            <th class="db-table-head-th">الكمية الحالية بالفاتورة</th>
                                            <th class="db-table-head-th">كمية المرتجع</th>
                                            <th class="db-table-head-th">{{ $t("label.sub_total") }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="db-table-body">
                                        <tr v-for="(item, index) in datatable" :key="index" class="db-table-body-tr">
                                            <td class="db-table-body-td font-medium">
                                                {{ item.name }}
                                                <span v-if="item.variation_names">
                                                    ( {{ $t('label.variation') }} : {{ item.variation_names }})
                                                </span>
                                            </td>
                                            <td class="db-table-body-td">{{ floatFormat(item.price) }}</td>
                                            <td class="db-table-body-td">{{ floatFormat(item.max_quantity) }}</td>
                                            <td class="db-table-body-td">
                                                <input
                                                    v-model="item.quantity"
                                                    @input="updateQuantity(index)"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="db-field-control"
                                                />
                                            </td>
                                            <td class="db-table-body-td">{{ floatFormat(item.total) }}</td>
                                        </tr>
                                        <tr>
                                            <th class="db-table-body-td" colspan="2">{{ $t("label.total") }}</th>
                                            <th class="db-table-body-td">{{ floatFormat(invoiceRemainingQuantity) }}</th>
                                            <th class="db-table-body-td">{{ floatFormat(totalQuantity) }}</th>
                                            <th class="db-table-body-td">{{ floatFormat(totalPrice) }}</th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="db-field-alert" v-if="errors.products">{{ errors.products[0] }}</small>
                        </div>
                    </div>

                    <div class="row pt-5">
                        <div class="form-col-12">
                            <div :class="errors.note ? 'invalid textarea-error-box-style' : ''">
                                <label class="db-field-title">{{ $t("label.reason") }}</label>
                                <quill-editor v-model:value="props.form.reason" class="!h-40 textarea-border-radius" />
                                <small class="db-field-alert" v-if="errors.reason">{{ errors.reason[0] }}</small>
                            </div>
                        </div>
                        <div class="form-col-12">
                            <div class="flex flex-wrap gap-3">
                                <button v-if="permissionChecker('return_order_create') || permissionChecker('return_order_edit')" type="submit" class="db-btn text-white bg-primary">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span class="tracking-wide">{{ $t("button.save") }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<script lang="js">
import axios from "axios";
import LoadingComponent from "../components/LoadingComponent";
import Datepicker from "@vuepic/vue-datepicker";
import { quillEditor } from "vue3-quill";
import alertService from "../../../services/alertService";
import appService from "../../../services/appService";

export default {
    name: "ReturnOrderCreateAndEditComponent",
    components: {
        quillEditor,
        LoadingComponent,
        Datepicker,
    },
    data() {
        return {
            file: "",
            errors: {},
            datatable: [],
            invoice: null,
            invoiceNumber: "",
            loading: {
                isActive: false,
            },
            props: {
                form: {
                    return_order_id: 0,
                    date: "",
                    total: null,
                    user_id: null,
                    order_id: null,
                    order_serial_no: "",
                    reference_no: "",
                    reason: "",
                    products: [],
                },
            },
            existingReturnProducts: [],
        };
    },
    mounted() {
        this.returnOrderInfo();
    },
    computed: {
        setting() {
            return this.$store.getters["frontendSetting/lists"];
        },
        totalPrice() {
            return this.datatable.reduce((sum, item) => sum + Number(item.total || 0), 0);
        },
        totalQuantity() {
            return this.datatable.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        },
        invoiceRemainingQuantity() {
            return this.datatable.reduce((sum, item) => sum + Number(item.max_quantity || 0), 0);
        },
    },
    methods: {
        permissionChecker(e) {
            return appService.permissionChecker(e);
        },
        changeFile(e) {
            this.file = e.target.files[0];
        },
        floatFormat(num) {
            return appService.floatFormat(num, this.setting.site_digit_after_decimal_point);
        },
        normalizeQuantity(value, maxQuantity) {
            let quantity = Number.parseFloat(value);
            if (Number.isNaN(quantity) || quantity < 0) {
                quantity = 0;
            }

            quantity = Math.round(quantity * 100) / 100;
            if (quantity > maxQuantity) {
                quantity = maxQuantity;
            }

            return quantity;
        },
        updateQuantity(index) {
            const item = this.datatable[index];
            item.quantity = this.normalizeQuantity(item.quantity, item.max_quantity);
            item.total_discount = Number((item.discount_per_unit * item.quantity).toFixed(6));
            item.total_tax = Number((item.tax_per_unit * item.quantity).toFixed(6));
            item.subtotal = Number((item.price * item.quantity).toFixed(6));
            item.total = Number((item.subtotal + item.total_tax - item.total_discount).toFixed(6));
        },
        async fetchInvoice() {
            if (!this.invoiceNumber) {
                alertService.error("يرجى إدخال رقم الفاتورة أولاً.");
                return;
            }

            this.loading.isActive = true;
            try {
                const query = this.props.form.return_order_id
                    ? `?return_order_id=${this.props.form.return_order_id}`
                    : "";
                const res = await axios.get(`admin/return-order/invoice/${this.invoiceNumber}${query}`);
                this.bindInvoice(res.data.data);
            } catch (err) {
                this.invoice = null;
                this.datatable = [];
                alertService.error(err.response?.data?.message || "تعذر تحميل الفاتورة.");
            } finally {
                this.loading.isActive = false;
            }
        },
        bindInvoice(order) {
            this.invoice = order;
            this.invoiceNumber = order.order_serial_no;
            this.props.form.user_id = order.user_id;
            this.props.form.order_id = order.id;
            this.props.form.order_serial_no = order.order_serial_no;

            const existingReturnsByItem = {};
            this.existingReturnProducts.forEach((product) => {
                existingReturnsByItem[`${product.item_id}`] = product;
            });

            this.datatable = order.order_products.map((product) => {
                const existingReturn = existingReturnsByItem[`${product.has_variation ? product.variation_id : product.product_id}`]
                    || existingReturnsByItem[`${product.has_variation ? product.variation_id : product.product_id}:${product.id}`];

                const quantity = existingReturn ? Number(existingReturn.quantity) : 0;
                const orderQuantity = Number(product.quantity || 0);
                const safeQuantity = this.normalizeQuantity(quantity, orderQuantity);
                const discountPerUnit = orderQuantity > 0 ? Number(product.discount || 0) / orderQuantity : 0;
                const taxPerUnit = orderQuantity > 0 ? Number(product.tax || 0) / orderQuantity : 0;
                const subtotal = Number((Number(product.price) * safeQuantity).toFixed(6));
                const totalDiscount = Number((discountPerUnit * safeQuantity).toFixed(6));
                const totalTax = Number((taxPerUnit * safeQuantity).toFixed(6));

                return {
                    order_stock_id: product.id,
                    product_id: product.product_id,
                    item_id: product.has_variation ? product.variation_id : product.product_id,
                    variation_id: product.has_variation ? product.variation_id : 0,
                    is_variation: product.has_variation,
                    variation_names: product.variation_names,
                    name: product.product_name,
                    sku: product.sku,
                    price: Number(product.price),
                    max_quantity: orderQuantity,
                    quantity: safeQuantity,
                    subtotal,
                    total_discount: totalDiscount,
                    total_tax: totalTax,
                    total: Number((subtotal + totalTax - totalDiscount).toFixed(6)),
                    discount_per_unit: discountPerUnit,
                    tax_per_unit: taxPerUnit,
                };
            });
        },
        async returnOrderInfo() {
            if (isNaN(this.$route.params.id)) {
                return;
            }

            this.loading.isActive = true;
            try {
                const res = await this.$store.dispatch("returnOrder/edit", this.$route.params.id);
                this.getReturnOrder(res.data.data);
                await this.fetchInvoice();
            } finally {
                this.loading.isActive = false;
            }
        },
        getReturnOrder(order) {
            this.props.form.return_order_id = order.id;
            this.props.form.date = order.date;
            this.props.form.user_id = order.user_id;
            this.props.form.order_id = order.order_id;
            this.props.form.order_serial_no = order.order_serial_no || "";
            this.props.form.reference_no = order.reference_no || "";
            this.props.form.total = order.total;
            this.props.form.reason = order.reason;
            this.invoiceNumber = order.order_serial_no || "";
            this.existingReturnProducts = order.products.map((product) => ({
                item_id: product.item_id,
                quantity: Number(product.quantity || 0),
            }));
        },
        buildProductsPayload() {
            return this.datatable
                .filter((item) => Number(item.quantity) > 0)
                .map((item) => ({
                    order_stock_id: item.order_stock_id,
                    product_id: item.product_id,
                    item_id: item.item_id,
                    variation_id: item.variation_id,
                    is_variation: item.is_variation,
                    variation_names: item.variation_names,
                    sku: item.sku,
                    price: item.price,
                    quantity: Number(item.quantity),
                    subtotal: item.subtotal,
                    total_discount: item.total_discount,
                    total_tax: item.total_tax,
                    total: item.total,
                }));
        },
        save() {
            try {
                const products = this.buildProductsPayload();
                const fd = new FormData();
                fd.append("user_id", this.props.form.user_id ?? "");
                fd.append("order_id", this.props.form.order_id ?? "");
                fd.append("date", this.props.form.date ? this.props.form.date : "");
                fd.append("reference_no", this.props.form.reference_no);
                fd.append("subtotal", products.reduce((sum, item) => sum + Number(item.subtotal || 0), 0));
                fd.append("tax", products.reduce((sum, item) => sum + Number(item.total_tax || 0), 0));
                fd.append("discount", products.reduce((sum, item) => sum + Number(item.total_discount || 0), 0));
                fd.append("total", products.reduce((sum, item) => sum + Number(item.total || 0), 0));
                fd.append("reason", this.props.form.reason);
                fd.append("products", JSON.stringify(products));
                if (this.file) {
                    fd.append("file", this.file);
                }

                this.loading.isActive = true;
                const tempId = this.$store.getters["returnOrder/temp"].temp_id;
                this.$store.dispatch("returnOrder/save", { form: fd })
                    .then(() => {
                        this.loading.isActive = false;
                        alertService.successFlip(tempId === null ? 0 : 1, this.$t("menu.return_orders"));
                        this.reset();
                        this.$router.push({ name: "admin.return-order.list" });
                    })
                    .catch((err) => {
                        this.loading.isActive = false;
                        this.errors = err.response.data.errors || {};
                        if (err.response?.data?.message) {
                            alertService.error(err.response.data.message);
                        } else if (this.errors.global) {
                            alertService.error(this.errors.global[0]);
                        }
                    });
            } catch (err) {
                this.loading.isActive = false;
                alertService.error(err);
            }
        },
        reset() {
            this.props.form = {
                return_order_id: 0,
                date: "",
                total: null,
                user_id: null,
                order_id: null,
                order_serial_no: "",
                reference_no: "",
                reason: "",
                products: [],
            };
            this.datatable = [];
            this.invoice = null;
            this.invoiceNumber = "";
            this.file = "";
            this.existingReturnProducts = [];
            if (this.$refs.fileProperty) {
                this.$refs.fileProperty.value = "";
            }
            this.errors = {};
        },
    },
};
</script>
