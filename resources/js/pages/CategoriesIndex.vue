<script setup>
import { onMounted, ref } from 'vue';
import NamedItemModal from '../components/forms/NamedItemModal.vue';
import { api } from '../lib/http';
import { t } from '../lib/i18n';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
});

const categories = ref([]);
const loading = ref(false);
const loadError = ref('');
const showForm = ref(false);
const categoryToEdit = ref(null);

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api('/admin/api/categories');
        categories.value = response.data;
    } catch (error) {
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

function openForm(category = null) {
    categoryToEdit.value = category;
    showForm.value = true;
}

onMounted(load);
</script>

<template>
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold">{{ t('app.categories.header') }}</span>
            <button v-if="can.manageCategories" type="button" class="btn btn-sm btn-primary" @click="openForm()">{{ t('app.categories.add') }}</button>
        </div>

        <div v-if="loadError" class="alert alert-danger m-3 d-flex flex-wrap justify-content-between align-items-center" role="alert">
            <span>{{ t('app.categories.load_error', { message: loadError }) }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="load">{{ t('app.common.retry') }}</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ t('app.positions.name_bm') }}</th>
                        <th>{{ t('app.positions.name_en') }}</th>
                        <th>{{ t('app.positions.order') }}</th>
                        <th>{{ t('app.positions.status') }}</th>
                        <th v-if="can.manageCategories"><span class="visually-hidden">{{ t('app.common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="5" class="text-center py-4">{{ t('app.categories.loading') }}</td>
                    </tr>
                    <tr v-else-if="!categories.length">
                        <td colspan="5" class="text-center py-4 text-body-secondary">{{ t('app.categories.empty') }}</td>
                    </tr>
                    <template v-else>
                        <tr v-for="category in categories" :key="category.id">
                            <td class="fw-semibold">{{ category.name_bm }}</td>
                            <td>{{ category.name_en ?? '—' }}</td>
                            <td>{{ category.sort_order }}</td>
                            <td>
                                <span class="badge" :class="category.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ category.is_active ? t('app.common.active') : t('app.common.inactive') }}
                                </span>
                            </td>
                            <td v-if="can.manageCategories" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="openForm(category)">{{ t('app.common.edit') }}</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <NamedItemModal
            :open="showForm"
            :title="categoryToEdit ? t('app.categories.edit_title') : t('app.categories.new_title')"
            endpoint="/admin/api/categories"
            :item="categoryToEdit"
            @close="showForm = false"
            @saved="load"
        />
    </div>
</template>
