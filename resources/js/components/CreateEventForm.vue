<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" @click="handleCancel">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <div>
              <h1 class="text-blue-600">新規予定作成</h1>
              <p class="text-xs text-gray-500">部署内共有カレンダー</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Button variant="outline" @click="handleCancel">キャンセル</Button>
            <Button @click="handleSave" class="gap-2 bg-blue-600 hover:bg-blue-700 text-white">
              <Save class="h-4 w-4" />
              保存
            </Button>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6">
      <ScrollArea class="h-[calc(100vh-120px)]">
        <div class="space-y-6 pb-6">
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <FileText class="h-5 w-5" />
                基本情報
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="title" class="required">タイトル / 件名 *</Label>
                <Input id="title" placeholder="例：部署ミーティング" v-model="form.title" class="text-base" />
              </div>
              <div class="space-y-2">
                <Label for="calendar">カレンダー</Label>
                <Select v-model="form.calendar_name">
                  <SelectTrigger id="calendar">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="総務部共有カレンダー">総務部共有カレンダー</SelectItem>
                    <SelectItem value="個人カレンダー">個人カレンダー</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="category">ジャンル</Label>
                <Select v-model="form.category">
                  <SelectTrigger id="category">
                    <div class="flex items-center gap-2">
                      <div :class="['w-3 h-3 rounded-full', getCategoryColor(form.category)]"></div>
                      <SelectValue />
                    </div>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="会議">
                      <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                        会議
                      </div>
                    </SelectItem>
                    <SelectItem value="MTG">
                      <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        MTG
                      </div>
                    </SelectItem>
                    <SelectItem value="期限">
                      <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                        期限
                      </div>
                    </SelectItem>
                    <SelectItem value="重要">
                      <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        重要
                      </div>
                    </SelectItem>
                    <SelectItem value="有給">
                      <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-teal-500"></div>
                        有給
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="importance">重要度</Label>
                <Select v-model="form.importance">
                  <SelectTrigger id="importance">
                    <div class="flex items-center gap-2">
                      <AlertCircle :class="['h-4 w-4', getImportanceColor(form.importance)]" />
                      <SelectValue />
                    </div>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="高">
                      <div class="flex items-center gap-2">
                        <AlertCircle class="h-4 w-4 text-red-600" />
                        重要
                      </div>
                    </SelectItem>
                    <SelectItem value="中">
                      <div class="flex items-center gap-2">
                        <AlertCircle class="h-4 w-4 text-yellow-600" />
                        中
                      </div>
                    </SelectItem>
                    <SelectItem value="低">
                      <div class="flex items-center gap-2">
                        <AlertCircle class="h-4 w-4 text-gray-600" />
                        低
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <CalendarIcon class="h-5 w-5" />
                日時設定
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex items-center space-x-2">
                <Checkbox id="allDay" :checked="form.is_all_day" @update:checked="form.is_all_day = $event" />
                <Label for="allDay" class="text-sm cursor-pointer">終日</Label>
              </div>
              <div class="space-y-2">
                <Label>期間 *</Label>
                <DatePicker v-model.range="form.date_range" :masks="{ modelValue: 'YYYY-MM-DD' }" is-range>
                  <template #default="{ inputValue, inputEvents }">
                    <div class="flex flex-col sm:flex-row justify-start items-center">
                      <div class="relative flex-grow w-full">
                        <Input
                          :value="inputValue.start"
                          v-on="inputEvents.start"
                          class="pr-10"
                        />
                        <CalendarIcon class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                      </div>
                      <span class="m-2">-</span>
                      <div class="relative flex-grow w-full">
                        <Input
                          :value="inputValue.end"
                          v-on="inputEvents.end"
                          class="pr-10"
                        />
                        <CalendarIcon class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                      </div>
                    </div>
                  </template>
                </DatePicker>
              </div>
              <div v-if="!form.is_all_day" class="flex flex-col sm:flex-row gap-4">
                <div class="space-y-2 flex-1">
                  <Label>開始時刻</Label>
                  <vue-material-time-picker v-model="form.start_time" />
                </div>
                <div class="space-y-2 flex-1">
                  <Label>終了時刻</Label>
                  <vue-material-time-picker v-model="form.end_time" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Repeat class="h-5 w-5" />
                繰り返し設定
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex items-center space-x-2">
                <Checkbox id="recurring" :checked="form.is_recurring" @update:checked="(checked) => { form.is_recurring = checked; if (!checked) form.recurrence_type = 'none'; }" />
                <Label for="recurring" class="text-sm cursor-pointer">この予定を繰り返す</Label>
              </div>
              <div v-if="form.is_recurring" class="space-y-4 pl-6 border-l-2 border-gray-200">
                <div class="space-y-2">
                  <Label for="recurrence">繰り返しパターン</Label>
                  <Select v-model="form.recurrence_type">
                    <SelectTrigger id="recurrence">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="daily">毎日</SelectItem>
                      <SelectItem value="weekly">毎週</SelectItem>
                      <SelectItem value="biweekly">隔週</SelectItem>
                      <SelectItem value="monthly">毎月</SelectItem>
                      <SelectItem value="yearly">毎年</SelectItem>
                      <SelectItem value="custom">カスタマイズ</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div v-if="form.recurrence_type === 'custom'" class="space-y-2">
                  <Label for="interval">繰り返し間隔</Label>
                  <div class="flex items-center gap-2">
                    <Input id="interval" type="number" min="1" v-model="form.recurrence_interval" class="w-20" />
                    <Select default-value="days">
                      <SelectTrigger class="w-32">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="days">日ごと</SelectItem>
                        <SelectItem value="weeks">週ごと</SelectItem>
                        <SelectItem value="months">月ごと</SelectItem>
                        <SelectItem value="years">年ごと</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <div class="space-y-2">
                  <Label for="recurrenceEnd">繰り返し終了日</Label>
                  <DatePicker v-model="form.recurrence_end_date" :masks="{ modelValue: 'YYYY-MM-DD' }">
                    <template #default="{ inputValue, inputEvents }">
                      <div class="relative w-full">
                        <Input
                          :value="inputValue"
                          v-on="inputEvents"
                          class="pr-10"
                        />
                        <CalendarIcon class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                      </div>
                    </template>
                  </DatePicker>
                  <p class="text-xs text-gray-500">空白の場合は無期限で繰り返されます</p>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-md">
                  <p class="text-sm text-blue-900">
                    <strong>プレビュー:</strong>
                    <span v-if="form.recurrence_type === 'daily'">毎日繰り返されます</span>
                    <span v-if="form.recurrence_type === 'weekly'">毎週繰り返されます</span>
                    <span v-if="form.recurrence_type === 'biweekly'">隔週で繰り返されます</span>
                    <span v-if="form.recurrence_type === 'monthly'">毎月繰り返されます</span>
                    <span v-if="form.recurrence_type === 'yearly'">毎年繰り返されます</span>
                    <span v-if="form.recurrence_type === 'custom'">{{ form.recurrence_interval }}日/週/月/年ごとに繰り返されます</span>
                    <span v-if="form.recurrence_end_date"> ({{ formatDate(form.recurrence_end_date) }}まで)</span>
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Users class="h-5 w-5" />
                参加者
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-2">
                <Label for="addParticipant">参加者を追加</Label>
                <Select @update:model-value="handleAddParticipant">
                  <SelectTrigger id="addParticipant">
                    <SelectValue placeholder="メンバーを選択..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="member in teamMembers.filter(m => !form.participants.find(p => p.id === m.id))" :key="member.id" :value="member.id">
                      {{ member.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div v-if="form.participants.length > 0" class="space-y-2">
                <Label>選択済み参加者</Label>
                <div class="flex flex-wrap gap-2">
                  <Badge v-for="participant in form.participants" :key="participant.id" variant="secondary" class="text-sm px-3 py-1 gap-2">
                    {{ participant.name }}
                    <button @click="handleRemoveParticipant(participant.id)" class="hover:text-red-600">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <MapPin class="h-5 w-5" />
                場所・会議室
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Input placeholder="例：会議室A、オンライン（Zoom）" v-model="form.location" class="text-base" />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <FileText class="h-5 w-5" />
                詳細・メモ
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Textarea placeholder="予定の詳細、準備事項、議題など..." v-model="form.description" class="min-h-[120px] text-base" />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <LinkIcon class="h-5 w-5" />
                URL
              </CardTitle>
            </Header>
            <CardContent>
              <Input type="url" placeholder="例：https://zoom.us/j/123456789" v-model="form.url" class="text-base" />
              <p class="text-xs text-gray-500 mt-2">オンライン会議のURLや関連資料のリンクなど</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Paperclip class="h-5 w-5" />
                添付ファイル
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div>
                <Label for="fileUpload" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors" @click="triggerFileInput">
                  <Plus class="h-4 w-4" />
                  ファイルを選択
                </Label>
                <Input id="fileUpload" type="file" multiple @change="handleFileChange" class="hidden" ref="fileInput" />
              </div>
              <div v-if="form.attachments.length > 0" class="space-y-2">
                <Label>添付済みファイル</Label>
                <div class="space-y-2">
                  <div v-for="(file, index) in form.attachments" :key="index" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                      <Paperclip class="h-4 w-4 text-gray-400 flex-shrink-0" />
                      <span class="text-sm truncate">{{ file.name }}</span>
                      <span class="text-xs text-gray-500 flex-shrink-0">({{ (file.size / 1024).toFixed(1) }} KB)</span>
                    </div>
                    <Button variant="ghost" size="sm" @click="handleRemoveAttachment(index)" class="flex-shrink-0">
                      <X class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <div class="flex flex-col sm:hidden gap-2 pt-4">
            <Button @click="handleSave" class="w-full gap-2 bg-blue-600 hover:bg-blue-700 text-white">
              <Save class="h-4 w-4" />
              保存
            </Button>
            <Button variant="outline" @click="handleCancel" class="w-full">キャンセル</Button>
          </div>
        </div>
      </ScrollArea>
    </main>
  </div>
</template>