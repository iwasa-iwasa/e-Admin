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
      return
    }


    try {
      const response = await fetch('/api/visitor-events/check')
      const data = await response.json()


      if (data.event && !notifiedEvents.value.has(data.event.event_id)) {
        notificationEvent.value = data.event
        showNotification.value = true
        notifiedEvents.value.add(data.event.event_id)

        // 通知を表示したらチェックを停止
        if (checkInterval.value) {
          clearInterval(checkInterval.value)
          checkInterval.value = null

        }
      }
    } catch (error) {
      console.error('Failed to check visitor events:', error)
    }
  }

  const startChecking = () => {

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