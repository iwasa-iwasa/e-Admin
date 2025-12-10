import { onMounted, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useHighlight(idKey: string = 'highlight') {
    onMounted(() => {
        const page = usePage()
        const highlightId = (page.props as any)[idKey]
        
        if (highlightId) {
            nextTick(() => {
                setTimeout(() => {
                    const element = document.getElementById(`item-${highlightId}`)
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth', block: 'center' })
                        setTimeout(() => {
                            element.classList.add('highlight-flash')
                            setTimeout(() => {
                                element.classList.remove('highlight-flash')
                            }, 3000)
                        }, 300)
                    }
                }, 300)
            })
        }
    })
}
