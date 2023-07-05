<template>
  <form class="border-2 rounded-md mb-10">
    <div class="text-lg font-semibold bg-gray-600 text-gray-100 flex justify-between items-center px-2">
      <div class="p-2">Prihlásenie na akciu</div>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="w-5 h-5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 5.25l-7.5 7.5-7.5-7.5m15 6l-7.5 7.5-7.5-7.5" />
      </svg>
    </div>

    <div class="p-3 px-4">
      <input
        type="text"
        name="first_name"
        class="border-2 border-gray-500 rounded-md p-2 mb-3 w-full"
        value=""
        required
        placeholder="Meno"
      />

      <input
        type="text"
        name="last_name"
        class="border-2 border-gray-500 rounded-md p-2 mb-3 w-full"
        value=""
        required
        placeholder="Priezvisko"
      />

      <input
        type="email"
        name="email"
        class="border-2 border-gray-500 rounded-md p-2 mb-3 w-full"
        value=""
        required
        placeholder="email na zaslanie potvrdenia"
      />

      <div>
        <label>
          <input
            type="number"
            class="border-2 border-gray-500 rounded-md p-2 mb-3 w-full"
            name="iamHuman"
            placeholder="Zadajte číslo"
            required
          />
          Zadajte číslo
        </label>
      </div>

      <button type="submit" class="btn btn-primary w-full">Odoslať registráciu</button>
    </div>
  </form>
</template>
