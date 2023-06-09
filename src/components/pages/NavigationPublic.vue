<template>
    <div class="bg-rose-700 text-gray-100 ">

    <div class="container mx-auto p-5 text-xl ">Navigation</div>
</div>
    
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
    setup() {

        const show: boolean = false;
        
        let title: string = 'Ahoj názov';

        const age: number = 22;

        const arr: number[] = [1,6,88,99];

        let arrcokolvek: any[] = [1, 'napdpis', true, ];

        let person: [number, string, boolean] = [1, 'Odhlásil sa' ,true]
        
    },
})
</script>
