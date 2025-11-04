<script setup lang="ts">
import { ref } from 'vue'
import { User, Mail, Building, Camera } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { useToast } from '@/components/ui/toast/use-toast'

defineProps<{ 
    open: boolean 
}>()
const emit = defineEmits(['update:open'])

const name = ref('田中太郎')
const email = ref('tanaka@company.co.jp')
const department = ref('総務部')
const profileImage = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const { toast } = useToast()

const handleImageUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    const reader = new FileReader()
    reader.onloadend = () => {
      profileImage.value = reader.result as string
    }
    reader.readAsDataURL(file)
  }
}

const handleSave = () => {
  if (!name.value.trim()) {
    toast({
        title: 'Error',
        description: '氏名を入力してください',
        variant: 'destructive',
    })
    return
  }

  toast({
    title: 'Success',
    description: '設定を保存しました',
  })
  emit('update:open', false)
}

const handleCancel = () => {
  name.value = '田中太郎'
  profileImage.value = null
  emit('update:open', false)
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

</script>

<template>
  <Dialog :open="open" @update:open="handleCancel">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <User class="h-5 w-5 text-blue-600" />
          プロフィール設定
        </DialogTitle>
        <DialogDescription>
          アカウント情報を編集できます
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-6 py-4">
        <!-- プロフィール画像 -->
        <div class="flex flex-col items-center gap-4">
          <Avatar class="h-24 w-24">
            <img v-if="profileImage" :src="profileImage" alt="プロフィール" class="h-full w-full object-cover" />
            <AvatarFallback v-else class="text-2xl">
              {{ name.charAt(0) }}
            </AvatarFallback>
          </Avatar>

          <div class="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="gap-2"
              @click="triggerFileInput"
            >
              <Camera class="h-4 w-4" />
              画像を変更
            </Button>
            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              class="hidden"
              @change="handleImageUpload"
            />
            <Button
              v-if="profileImage"
              type="button"
              variant="ghost"
              size="sm"
              @click="profileImage = null"
            >
              削除
            </Button>
          </div>
        </div>

        <!-- 氏名 -->
        <div class="space-y-2">
          <Label for="name" class="flex items-center gap-2">
            <User class="h-4 w-4 text-gray-500" />
            氏名
          </Label>
          <Input
            id="name"
            v-model="name"
            placeholder="例：田中太郎"
            autofocus
          />
        </div>

        <!-- メールアドレス（編集不可） -->
        <div class="space-y-2">
          <Label for="email" class="flex items-center gap-2">
            <Mail class="h-4 w-4 text-gray-500" />
            メールアドレス
          </Label>
          <Input
            id="email"
            v-model="email"
            disabled
            class="bg-gray-50 cursor-not-allowed"
          />
          <p class="text-xs text-gray-500">
            メールアドレスは変更できません
          </p>
        </div>

        <!-- 部署名（編集不可） -->
        <div class="space-y-2">
          <Label for="department" class="flex items-center gap-2">
            <Building class="h-4 w-4 text-gray-500" />
            部署名
          </Label>
          <Input
            id="department"
            v-model="department"
            disabled
            class="bg-gray-50 cursor-not-allowed"
          />
          <p class="text-xs text-gray-500">
            部署名は管理者のみ変更できます
          </p>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="handleCancel">
          キャンセル
        </Button>
        <Button @click="handleSave">保存</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
