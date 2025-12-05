<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import { Save, X } from "lucide-vue-next";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useToast } from "@/components/ui/toast/use-toast";

type Priority = "high" | "medium" | "low";

defineProps<{
    open: boolean;
}>();
const emit = defineEmits(["update:open"]);

const form = useForm<{
    title: string;
    content: string;
    priority: Priority;
    deadline: string;
    tags: string[];
    color: string;
}> ({
    title: "",
    content: "",
    priority: "medium",
    deadline: "",
    tags: [] as string[],
    color: "yellow",
});

const tagInput = ref("");

const { toast } = useToast();

const handleSave = () => {
    if (!form.title.trim()) {
        toast({
            title: "Error",
            description: "タイトルを入力してください",
            variant: "destructive",
        });
        return;
    }
    
    form.post(route("shared-notes.store"), {
        onSuccess: () => {
            toast({
                title: "Success",
                description: "共有メモを正常に作成しました。",
            });
            handleClose();
        },
        onError: (errors) => {
             toast({
                title: "Error",
                description: "メモの作成中にエラーが発生しました。",
                variant: "destructive",
            });
            console.error(errors);
        },
    });
};

const handleClose = () => {
    form.reset();
    form.tags = [];
    tagInput.value = "";
    emit("update:open", false);
};

const handleAddTag = () => {
    if (tagInput.value.trim() && !form.tags.includes(tagInput.value.trim())) {
        form.tags.push(tagInput.value.trim());
        tagInput.value = "";
    }
};

const handleRemoveTag = (tagToRemove: string) => {
    form.tags = form.tags.filter((tag) => tag !== tagToRemove);
};

const getPriorityInfo = (p: Priority) => {
    switch (p) {
        case "high":
            return { className: "bg-red-600 text-white", label: "重要" };
        case "medium":
            return { className: "bg-yellow-500 text-white", label: "中" };
        case "low":
            return { className: "bg-gray-400 text-white", label: "低" };
    }
};

const getColorInfo = (c: string) => {
    const colorMap: Record<string, { bg: string; label: string }> = {
        blue: { bg: "bg-blue-100", label: "ブルー" },
        green: { bg: "bg-green-100", label: "グリーン" },
        yellow: { bg: "bg-yellow-100", label: "オレンジ" },
        purple: { bg: "bg-purple-100", label: "パープル" },
        pink: { bg: "bg-pink-100", label: "ピンク" },
    };
    return colorMap[c] || colorMap.yellow;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>新しい共有メモを作成</DialogTitle>
                <DialogDescription>
                    部署全員で共有できるメモを作成します
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="title">タイトル *</Label>
                    <Input
                        id="title"
                        placeholder="メモのタイトル"
                        v-model="form.title"
                        autofocus
                    />
                </div>

                <div class="space-y-2">
                    <Label for="content">内容</Label>
                    <Textarea
                        id="content"
                        placeholder="メモの内容を入力..."
                        v-model="form.content"
                        rows="6"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="priority">重要度</Label>
                    <Select v-model="form.priority">
                        <SelectTrigger id="priority">
                            <div class="flex items-center gap-2">
                                <Badge
                                    :class="getPriorityInfo(form.priority).className"
                                >
                                    {{ getPriorityInfo(form.priority).label }}
                                </Badge>
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="high">
                                <Badge class="bg-red-600 text-white"
                                    >重要</Badge
                                >
                            </SelectItem>
                            <SelectItem value="medium">
                                <Badge class="bg-yellow-500 text-white"
                                    >中</Badge
                                >
                            </SelectItem>
                            <SelectItem value="low">
                                <Badge class="bg-gray-400 text-white">低</Badge>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="deadline">期限（任意）</Label>
                    <Input id="deadline" type="date" v-model="form.deadline" />
                </div>

                <div class="space-y-2">
                    <Label for="color">メモの色</Label>
                    <Select v-model="form.color">
                        <SelectTrigger id="color">
                            <div class="flex items-center gap-2">
                                <div
                                    :class="[
                                        'w-4 h-4 rounded',
                                        getColorInfo(form.color).bg,
                                    ]"
                                ></div>
                                <span>{{ getColorInfo(form.color).label }}</span>
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="c in [
                                    'yellow',
                                    'blue',
                                    'green',
                                    'pink',
                                    'purple',
                                ]"
                                :key="c"
                                :value="c"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        :class="[
                                            'w-4 h-4 rounded',
                                            getColorInfo(c).bg,
                                        ]"
                                    ></div>
                                    <span>{{ getColorInfo(c).label }}</span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="tags">タグ</Label>
                    <div class="flex gap-2">
                        <Input
                            id="tags"
                            placeholder="タグを追加"
                            v-model="tagInput"
                            @keypress.enter.prevent="handleAddTag"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="handleAddTag"
                        >
                            追加
                        </Button>
                    </div>
                    <div
                        v-if="form.tags.length > 0"
                        class="flex flex-wrap gap-2 mt-2"
                    >
                        <Badge
                            v-for="tag in form.tags"
                            :key="tag"
                            variant="secondary"
                            class="gap-2"
                        >
                            {{ tag }}
                            <button @click="handleRemoveTag(tag)">
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleClose">
                    キャンセル
                </Button>
                <Button 
                    @click="handleSave" 
                    :disabled="form.processing" 
                    class="gap-2"
                >
                    <Save class="h-4 w-4" />
                    作成
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>