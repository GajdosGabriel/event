import { onBeforeMount, onMounted} from 'vue'
// https://www.youtube.com/watch?v=tevotcV6D2E&ab_channel=SolidusCode
export default function useClickAway(el, callback_fn) {

    if (!el) return;

    let listener = (e) => {
        if (e.target == el.value || e.composedPath().includes(el.value)) {
            return
        }

        if (typeof callback_fn == 'function') {
            callback_fn()
        }
    }

    onMounted(() => {
        window.addEventListener('click', listener)
    });

    onBeforeMount(() => {
        window.removeEventListener('click', listener)
    })

    return { listener }
}