import {ref} from 'vue'
// https://www.syncfusion.com/blogs/post/can-the-composition-api-replace-vue-mixins.aspx
export default function useClickAway(){

    const open = ref (false)
    function toggleHandle() {
        open.value = ! open.value;
        // console.log(reuseData.value);
        // console.log('Hello from Reusable method!')
    }
    return {
        open,
        toggleHandle
    }
}