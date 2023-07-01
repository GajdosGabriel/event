<template>
  <footer style="background: #1e2e37; color: #a6a6a6" class="p-5">
    <div class="max-w-7xl mx-auto md:flex justify-around">
      <div class="w-full">
        <h4 class="border-b-2 py-3 border-gray-300 font-semibold">O projekte</h4>
        <ul class="mt-2 space-y-1">
          <li><a href="#">Pridať novú akciu</a></li>
          <li><router-link to="/ochrana-osobnych-udajov">Ochrana osobných údajov</router-link></li>
        </ul>
      </div>

      <div class="w-full">
        <h4 class="border-b-2 py-3 border-gray-300 font-semibold">Kontakt</h4>
        <ul class="mt-2 space-y-1">
          <li><span class="fa fa-globe"></span> Bratislava, Slovensko</li>
          <li><span class="fa fa-phone"></span> 0905 320 616</li>
        </ul>
      </div>

      <div class="w-full">
        <h4 class="border-b-2 py-3 border-gray-300 font-semibold">Napíšte nám</h4>
        <form method="post" action="#" class="my-4">
          <textarea
            class="border-2 border-gray-500 rounded-md w-full p-2"
            name="body"
            rows="3"
            required
            placeholder="Napíšte nám svoje podnety ... Ďakujeme"
            value=""
          ></textarea>

          <div class="form-group md:flex items-center mt-4">
            <label>Som človek 7 plus 3 = </label>
            <input
              class="mx-2 rounded-sm text-gray-800"
              type="number"
              name="iamHuman"
              placeholder="Zadajte číslo"
              required
            />

            <button type="submit" class="px-2 p-1 border-2 text-sm rounded-sm mt-2 hover:bg-gray-700">
              Odoslať
              <span class="glyphicon glyphicon-envelope"></span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="flex w-full justify-center">
      <div class="text-sm my-2">
        Autor šablóny Gajdoš Gabriel 2018
        <a href="" title="Hlas Cirkvi.sk">Hlas Cirkvi.sk</a>
      </div>
    </div>
  </footer>
</template>
