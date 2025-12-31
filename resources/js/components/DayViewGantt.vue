<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps<{
    events: App.Models.Event[]
    currentDate: Date
}>()

const emit = defineEmits<{
    eventClick: [event: App.Models.Event]
    eventHover: [event: App.Models.Event | null, position: { x: number, y: number }]
}>()

const page = usePage()
const teamMembers = computed(() => (page.props as any).teamMembers || [])

// 時間軸の設定（7:00〜19:00）
const startHour = 7
const endHour = 19
const hours = Array.from({ length: endHour - startHour + 1 }, (_, i) => startHour + i)

// 業務時間（8:00〜17:00）
const workStartHour = 8
const workEndHour = 17

// 当日の予定をフィルタリング
const todayEvents = computed(() => {
    const dateStr = props.currentDate.toISOString().split('T')[0]
    return props.events.filter(event => {
        const eventStart = event.start_date.split('T')[0]
        const eventEnd = event.end_date.split('T')[0]
        return eventStart <= dateStr && dateStr <= eventEnd
    })
})

// メンバーごとの予定を整理
const memberEvents = computed(() => {
    return teamMembers.value.map(member => {
        const events = todayEvents.value.filter(event => 
            event.participants?.some(p => p.id === member.id)
        )
        
        // 予定を時間順にソート
        const sortedEvents = events.sort((a, b) => {
            const aTime = a.start_time || '00:00:00'
            const bTime = b.start_time || '00:00:00'
            return aTime.localeCompare(bTime)
        })
        
        // 重なりを検出してレーン分け
        const lanes: App.Models.Event[][] = []
        sortedEvents.forEach(event => {
            const eventStart = parseTime(event.start_time || '00:00:00')
            const eventEnd = parseTime(event.end_time || '23:59:59')
            
            // 既存のレーンで配置可能か確認
            let placed = false
            for (let i = 0; i < lanes.length; i++) {
                const lane = lanes[i]
                const canPlace = lane.every(existing => {
                    const existingStart = parseTime(existing.start_time || '00:00:00')
                    const existingEnd = parseTime(existing.end_time || '23:59:59')
                    return eventEnd <= existingStart || eventStart >= existingEnd
                })
                
                if (canPlace) {
                    lane.push(event)
                    placed = true
                    break
                }
            }
            
            if (!placed) {
                lanes.push([event])
            }
        })
        
        return {
            member,
            lanes
        }
    })
})

// 時刻を分に変換
const parseTime = (timeStr: string): number => {
    const [hours, minutes] = timeStr.split(':').map(Number)
    return hours * 60 + minutes
}

// 予定バーの位置とサイズを計算
const getEventStyle = (event: App.Models.Event) => {
    const startTime = event.start_time || '00:00:00'
    const endTime = event.end_time || '23:59:59'
    
    const startMinutes = parseTime(startTime)
    const endMinutes = parseTime(endTime)
    
    const dayStartMinutes = startHour * 60
    const dayEndMinutes = (endHour + 1) * 60
    const totalMinutes = dayEndMinutes - dayStartMinutes
    
    const left = ((startMinutes - dayStartMinutes) / totalMinutes) * 100
    const width = ((endMinutes - startMinutes) / totalMinutes) * 100
    
    return {
        left: `${Math.max(0, left)}%`,
        width: `${Math.min(100 - Math.max(0, left), width)}%`
    }
}

// イベントの色を取得
const getEventColor = (category: string) => {
    const categoryColorMap: { [key: string]: string } = {
        '会議': '#42A5F5',
        '業務': '#66BB6A',
        '来客': '#FFA726',
        '出張': '#9575CD',
        '休暇': '#F06292',
    }
    return categoryColorMap[category] || '#6b7280'
}

// 現在時刻のライン位置
const currentTimePosition = computed(() => {
    const now = new Date()
    const currentMinutes = now.getHours() * 60 + now.getMinutes()
    const dayStartMinutes = startHour * 60
    const dayEndMinutes = (endHour + 1) * 60
    const totalMinutes = dayEndMinutes - dayStartMinutes
    
    if (currentMinutes < dayStartMinutes || currentMinutes > dayEndMinutes) {
        return null
    }
    
    return ((currentMinutes - dayStartMinutes) / totalMinutes) * 100
})

// 現在時刻の文字列
const currentTimeText = computed(() => {
    const now = new Date()
    return now.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' })
})

// 今日かどうか
const isToday = computed(() => {
    const today = new Date()
    return props.currentDate.toDateString() === today.toDateString()
})

const handleEventClick = (event: App.Models.Event) => {
    emit('eventClick', event)
}

const handleEventHover = (event: App.Models.Event | null, mouseEvent: MouseEvent) => {
    if (event) {
        emit('eventHover', event, { x: mouseEvent.clientX, y: mouseEvent.clientY })
    } else {
        emit('eventHover', null, { x: 0, y: 0 })
    }
}
</script>

<template>
    <div class="day-view-gantt">
        <!-- ヘッダー：時間軸 -->
        <div class="gantt-header">
            <div class="member-column-header">メンバー</div>
            <div class="time-grid-header">
                <div
                    v-for="hour in hours"
                    :key="hour"
                    class="time-cell"
                    :class="{
                        'work-hours': hour >= workStartHour && hour < workEndHour
                    }"
                >
                    {{ hour }}:00
                </div>
            </div>
        </div>
        
        <!-- ボディ：メンバー × 予定 -->
        <div class="gantt-body">
            <div
                v-for="{ member, lanes } in memberEvents"
                :key="member.id"
                class="member-row"
                :style="{ height: `${Math.max(70, lanes.length * 64)}px` }"
            >
                <!-- メンバー名 -->
                <div class="member-column">
                    <div class="member-info">
                        <div
                            class="member-avatar"
                            :style="{ backgroundColor: getEventColor('会議') }"
                        >
                            {{ member.name.charAt(0) }}
                        </div>
                        <span class="member-name">{{ member.name }}</span>
                    </div>
                </div>
                
                <!-- 時間グリッド -->
                <div class="time-grid">
                    <!-- 背景グリッド -->
                    <div class="time-grid-bg">
                        <div
                            v-for="hour in hours"
                            :key="hour"
                            class="time-cell-bg"
                            :class="{
                                'work-hours': hour >= workStartHour && hour < workEndHour
                            }"
                        />
                    </div>
                    
                    <!-- 予定バー -->
                    <div class="events-container">
                        <div
                            v-for="(lane, laneIndex) in lanes"
                            :key="laneIndex"
                            class="event-lane"
                        >
                            <div
                                v-for="event in lane"
                                :key="event.event_id"
                                class="event-bar"
                                :class="{
                                    'important': event.importance === '重要'
                                }"
                                :style="{
                                    ...getEventStyle(event),
                                    backgroundColor: getEventColor(event.category),
                                    borderColor: event.importance === '重要' ? '#dc2626' : getEventColor(event.category)
                                }"
                                @click="handleEventClick(event)"
                                @mouseenter="handleEventHover(event, $event)"
                                @mouseleave="handleEventHover(null, $event)"
                            >
                                <span class="event-title">{{ event.title }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 現在時刻ライン -->
                    <div
                        v-if="isToday && currentTimePosition !== null"
                        class="current-time-line"
                        :style="{ left: `${currentTimePosition}%` }"
                    >
                        <div class="current-time-marker" />
                    </div>
                </div>
            </div>
        </div>
        
        <!-- フッター：現在時刻表示 -->
        <div v-if="isToday && currentTimePosition !== null" class="gantt-footer">
            <div class="member-column-footer"></div>
            <div class="time-grid-footer">
                <div
                    class="current-time-indicator"
                    :style="{ left: `${currentTimePosition}%` }"
                >
                    <div class="current-time-text">現在時刻: {{ currentTimeText }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.day-view-gantt {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: auto;
}

.gantt-header {
    display: flex;
    position: sticky;
    top: 0;
    z-index: 10;
    background: white;
    border-bottom: 2px solid #e5e7eb;
}

.member-column-header {
    width: 150px;
    min-width: 150px;
    padding: 12px;
    font-weight: 600;
    border-right: 1px solid #e5e7eb;
    background: #f9fafb;
}

.time-grid-header {
    display: flex;
    flex: 1;
}

.time-cell {
    flex: 1;
    min-width: 80px;
    padding: 12px 8px;
    text-align: center;
    font-size: 12px;
    font-weight: 500;
    border-right: 1px solid #e5e7eb;
    background: #f9fafb;
}

.time-cell.work-hours {
    background: #eff6ff;
}

.gantt-body {
    flex: 1;
}

.member-row {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    min-height: 70px;
}

.member-column {
    width: 150px;
    min-width: 150px;
    padding: 12px;
    border-right: 1px solid #e5e7eb;
    background: white;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.member-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.member-name {
    font-size: 14px;
    font-weight: 500;
}

.time-grid {
    flex: 1;
    position: relative;
}

.time-grid-bg {
    display: flex;
    position: absolute;
    inset: 0;
}

.time-cell-bg {
    flex: 1;
    min-width: 80px;
    border-right: 1px solid #e5e7eb;
}

.time-cell-bg.work-hours {
    background: #f8fafc;
}

.events-container {
    position: relative;
    height: 100%;
    padding: 4px 0;
}

.event-lane {
    position: relative;
    height: 60px;
    margin: 4px 0;
}

.event-bar {
    position: absolute;
    height: 52px;
    border-radius: 4px;
    border: 2px solid;
    padding: 0 12px;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    overflow: hidden;
}

.event-bar:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 5;
}

.event-bar.important {
    border-width: 3px;
    font-weight: 600;
}

.event-title {
    font-size: 14px;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.current-time-line {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #ef4444;
    z-index: 5;
    pointer-events: none;
}

.current-time-marker {
    position: absolute;
    top: -4px;
    left: -4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ef4444;
}

.gantt-footer {
    display: flex;
    border-top: 2px solid #e5e7eb;
    background: #f9fafb;
    min-height: 40px;
}

.member-column-footer {
    width: 150px;
    min-width: 150px;
    border-right: 1px solid #e5e7eb;
}

.time-grid-footer {
    flex: 1;
    position: relative;
}

.current-time-indicator {
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
}

.current-time-text {
    background: #ef4444;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
}
</style>
