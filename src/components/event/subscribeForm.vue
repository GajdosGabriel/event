<template>
  <form  class="border-2 rounded-md mb-10">


    <div class="text-lg font-semibold bg-gray-600 text-gray-100 mb-6 p-2">Prihlásiť sa na akciu</div>

    <div class="p-2">
      <input
        type="text"
        name="first_name"
        class="border-2 border-gray-500 rounded-md p-2 mb-3"
        value=""
        required
        placeholder="Meno"
      />

      <input
        type="text"
        name="last_name"
        class="border-2 border-gray-500 rounded-md p-2"
        value=""
        required
        placeholder="Priezvisko"
      />

      <input
        type="email"
        name="email"
        class="border-2 border-gray-500 rounded-md p-2 mb-3"
        value=""
        required
        placeholder="email na zaslanie potvrdenia"
      />

      <div>
        <label>
          <input
            type="number"
            class="border-2 border-gray-500 rounded-md p-2 mb-3"
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
