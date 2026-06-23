import { onMounted, ref } from 'vue'

export function useScrollReveal() {
    const elements = ref<Element[]>([])

    onMounted(() => {
        if (!('IntersectionObserver' in window)) return

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('reveal-visible')
                        observer.unobserve(entry.target)
                    }
                })
            },
            { threshold: 0.1, rootMargin: '0px 0px -48px 0px' },
        )

        document.querySelectorAll('.reveal').forEach((el) => observer.observe(el))

        return () => observer.disconnect()
    })

    return { elements }
}
