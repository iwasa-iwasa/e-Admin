<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useToast } from "@/components/ui/toast/use-toast";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Save, X, Info, Clock, CheckCircle } from "lucide-vue-next";
import SurveyForm from "../features/survey/components/SurveyEditor/SurveyForm.vue";
import { convertFromBackend } from "../features/survey/domain/factory";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";

// State for confirmation dialog
const confirmReset = ref<{
    isOpen: boolean;
    message: string;
    onConfirm: () => void;
}>({
    isOpen: false,
    message: '',
    onConfirm: () => {},
});

const props = defineProps<{
    open: boolean;
    survey?: any;
    teamMembers?: Array<{id: number, name: string}> | null;
}>();

const emit = defineEmits(["update:open", "open-dialog"]);
const { toast } = useToast();

const formRef = ref();
const formKey = ref(0);
const initialFormData = ref<any>(null);
const showDraftBanner = ref(false);
const draftSavedAt = ref<Date | null>(null);
const saveMessage = ref('');
const messageType = ref<'success' | 'delete' | 'error'>('success');
const messageTimer = ref<number | null>(null);

// Constants
const DRAFT_KEY = "survey_draft";
const FAILURE_DRAFT_KEY = "survey_failure_draft";

const isEditMode = computed(() => !!props.survey);

// Message Helper
const showMessage = (message: string, type: 'success' | 'delete' | 'error' = 'success') => {
    if (messageTimer.value) clearTimeout(messageTimer.value);
    saveMessage.value = message;
    messageType.value = type;
    messageTimer.value = setTimeout(() => {
        saveMessage.value = '';
    }, 4000);
};

const handleClose = () => {
    emit("update:open", false);
};

// Draft Logic
const loadDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_KEY);
        if (!saved) return;
        const draftData = JSON.parse(saved);
        initialFormData.value = draftData;
        draftSavedAt.value = new Date(draftData.saved_at);
        showDraftBanner.value = true;
        formKey.value++; // Force remount
    } catch (e) {
        console.error("Failed to load draft");
    }
};

const saveDraft = () => {
    try {
        const data = formRef.value.getData();
        const draftData = {
            ...data,
            saved_at: new Date().toISOString()
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
        draftSavedAt.value = new Date();
        showDraftBanner.value = true;
        showMessage('アンケートが一時保存されました', 'success');
    } catch (e) {
        showMessage('一時保存に失敗しました', 'error');
    }
};

const clearDraft = () => {
    localStorage.removeItem(DRAFT_KEY);
    showDraftBanner.value = false;
    draftSavedAt.value = null;
    showMessage('下書きを削除しました', 'delete');
};

// Save (Publish/Draft) Logic
const handleSave = (isDraft: boolean, force = false) => {
    if (!formRef.value) return;

    const errors = formRef.value.validate();
    if (errors.length > 0) {
        showMessage(errors.join('\n'), 'error');
        return;
    }

    const data = formRef.value.getData();
    // Transform data for backend if needed
    // Assuming backend accepts what useSurveyEditor produces (which matches UpdateSurveyRequest requirements mostly)
    
    // Convert Question Structure slightly if backend expects specific structure?
    // Backend expects options as: string[] in questions.*.options
    // My useSurveyEditor uses Question model which has options as string[] or object[].
    // If objects, I map to string? Wait, existing backend handles Objects Update?
    // UpdateSurveyRequest says `questions.*.options.* => string`.
    // So I must ensure options are strings.
    
    const preparedQuestions = data.questions.map((q: any) => ({
        ...q,
        options: q.options.map((o: any) => typeof o === 'string' ? o : (o.text || o.option_text))
    }));

    const formData = {
        ...data,
        questions: preparedQuestions,
        is_draft: isDraft, // Note: backend might expect is_draft
        isDraft: isDraft, // Just in case
        confirm_reset: force // 強制更新フラグ
    };

    const form = useForm(formData);

    const handleError = (err: any) => {
        // 確認が必要なエラーかチェック
        if (err.requires_confirmation) {
            confirmReset.value = {
                isOpen: true,
                message: err.message || 'この操作には確認が必要です。',
                onConfirm: () => handleSave(isDraft, true) // force=trueで再実行
            };
            return;
        }
        showMessage(Object.values(err).flat().join('\n'), 'error');
    };

    if (isEditMode.value) {
        form.put(`/surveys/${props.survey.survey_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showMessage('更新しました', 'success');
                handleClose();
            },
            onError: handleError
        });
    } else {
        form.post('/surveys', {
            preserveScroll: true,
            onSuccess: () => {
                if (!isDraft) clearDraft();
                showMessage('作成しました', 'success');
                handleClose();
            },
            onError: handleError
        });
    }
};

// Watch Open
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        if (props.survey) {
            initialFormData.value = convertFromBackend(props.survey);
        } else {
            initialFormData.value = null;
            // check draft
            const saved = localStorage.getItem(DRAFT_KEY);
            if (saved) {
                // Ask user? Or just show banner?
                // Legacy showed confirm.
                if (window.confirm("前回の下書きが見つかりました。復元しますか？")) {
                    loadDraft();
                } else {
                    clearDraft();
                    formKey.value++;
                }
            } else {
                formKey.value++;
            }
        }
    } else {
        initialFormData.value = null;
        saveMessage.value = '';
    }
});

const formatSavedTime = (date: Date | null) => {
    if(!date) return '';
    return date.toLocaleString('ja-JP', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="max-w-4xl md:max-w-5xl h-[90vh] flex flex-col p-0 gap-0 overflow-hidden sm:rounded-2xl">
             <DialogHeader class="p-6 pb-2 shrink-0">
                <DialogTitle>{{ isEditMode ? "アンケートを編集" : "新しいアンケートを作成" }}</DialogTitle>
                <DialogDescription>{{ isEditMode ? "アンケートの内容を編集します" : "総務部 共同管理のアンケートを作成します" }}</DialogDescription>
            </DialogHeader>

            <!-- Draft Banner -->
            <div v-if="showDraftBanner && draftSavedAt && !isEditMode" class="mx-6 mb-2 p-3 bg-blue-50 border border-blue-200 rounded-md flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2 text-sm text-blue-700">
                    <Clock class="h-4 w-4" />
                    <span>最終保存: {{ formatSavedTime(draftSavedAt) }}</span>
                </div>
                <Button variant="ghost" size="sm" @click="clearDraft" class="h-6 px-2 text-blue-700 hover:text-blue-900">
                    <X class="h-3 w-3" />
                </Button>
            </div>

            <div class="flex-1 overflow-y-auto px-6">
                <SurveyForm
                    ref="formRef"
                    :key="formKey"
                    :initialData="initialFormData"
                    :teamMembers="teamMembers || undefined"
                />
                <div class="h-6"></div> <!-- Spacer -->
            </div>

            <DialogFooter class="p-6 border-t mt-auto shrink-0 bg-white">
                <Button variant="outline" @click="handleClose">キャンセル</Button>
                <div class="flex gap-2">
                    <Button v-if="!isEditMode" variant="outline" @click="saveDraft">一時保存</Button>
                    <Button @click="handleSave(false)" class="gap-2">
                        <Save class="h-4 w-4" />
                        {{ isEditMode ? "更新" : "作成" }}
                    </Button>
                </div>
            </DialogFooter>

            <!-- Message Toast (Inline) -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="transform opacity-0 translate-y-full"
                enter-to-class="transform opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform opacity-100 translate-y-0"
                leave-to-class="transform opacity-0 translate-y-full"
            >
                <div v-if="saveMessage" 
                     class="absolute bottom-20 left-1/2 transform -translate-x-1/2 z-50 p-3 text-white rounded-lg shadow-lg flex items-center gap-2"
                     :class="messageType === 'success' ? 'bg-green-500' : 'bg-red-500'"
                >
                    <CheckCircle class="h-5 w-5" />
                    <span>{{ saveMessage }}</span>
                </div>
            </Transition>
        </DialogContent>

        <!-- Confirmation Dialog -->
        <AlertDialog :open="confirmReset.isOpen">
            <AlertDialogContent class="bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle>確認が必要です</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ confirmReset.message }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="confirmReset.isOpen = false">キャンセル</AlertDialogCancel>
                    <AlertDialogAction 
                        @click="() => { 
                            confirmReset.isOpen = false; 
                            confirmReset.onConfirm(); 
                        }" 
                        class="bg-red-600 hover:bg-red-700"
                    >
                        続行する
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </Dialog>
</template>
