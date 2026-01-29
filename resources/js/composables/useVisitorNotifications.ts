import { ref, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useVisitorNotifications() {
  const page = usePage()
  const currentUserId = (page.props as any).auth?.user?.id
  const notificationEvent = ref<App.Models.Event | null>(null)
  const showNotification = ref(false)
  const checkInterval = ref<number | null>(null)
  const notifiedEvents = ref<Set<number>>(new Set())

  const checkVisitorEvents = async () => {
    if (!currentUserId) {
      console.log('No current user ID')
      return
    }

    console.log('Checking visitor events...')
    try {
      const response = await fetch('/api/visitor-events/check')
      const data = await response.json()
      console.log('Visitor events response:', data)
      
      if (data.event && !notifiedEvents.value.has(data.event.event_id)) {
        console.log('Showing notification for event:', data.event)
        notificationEvent.value = data.event
        showNotification.value = true
        notifiedEvents.value.add(data.event.event_id)
        
        // 通知を表示したらチェックを停止
        if (checkInterval.value) {
          clearInterval(checkInterval.value)
          checkInterval.value = null
          console.log('Stopped checking after showing notification')
        }
      }
    } catch (error) {
      console.error('Failed to check visitor events:', error)
    }
  }

  const startChecking = () => {
    console.log('Starting visitor event checking')
    checkVisitorEvents()
    checkInterval.value = window.setInterval(checkVisitorEvents, 30000)
  }

  const stopChecking = () => {
    if (checkInterval.value) {
      clearInterval(checkInterval.value)
      checkInterval.value = null
    }
  }

  onMounted(() => {
    startChecking()
  })

  onUnmounted(() => {
    stopChecking()
  })

  return {
    notificationEvent,
    showNotification,
    startChecking,
    stopChecking,
    closeNotification: () => { showNotification.value = false }
  }
}