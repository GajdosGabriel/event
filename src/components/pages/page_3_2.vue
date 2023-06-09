<template>
  <div class="md:w-10/12 mx-auto p-6 h-screen">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <slot name="content">Stránka je prázdna</slot>
      </div>

      <div class="col-span-3">
        <slot name="aside">Aside je prázdny</slot>
      </div>
    </div>
  </div>
</template>
