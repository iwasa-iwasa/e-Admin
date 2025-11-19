<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    show: boolean;
    title: string;
    message: string;
}>();

const emit = defineEmits(['confirm', 'close']);

const confirm = () => {
    emit('confirm');
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ title }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ message }}
            </p>

            <div class="mt-6 flex justify-end">
                <Button variant="outline" @click="close"> Cancel </Button>

                <Button
                    variant="destructive"
                    class="ms-3"
                    @click="confirm"
                >
                    Confirm
                </Button>
            </div>
        </div>
    </Modal>
</template>
