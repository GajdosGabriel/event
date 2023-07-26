<script lang="ts">
import { defineComponent, reactive, ref } from "vue";
// import { Event } from "../../../types/event";

import CardAside from "../CardAside.vue";
import FooterComponent from "../Footer.vue";
import UserEventCard from "@/components/event/UserEventCard.vue";
import PaginationComponent from "../PaginationComponent.vue";

export default defineComponent({
  components: { CardAside, FooterComponent, UserEventCard, PaginationComponent },
  setup() {
    const form = reactive({});
    const filee = ref(null);


    return { form, filee };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <!-- <Header></Header> -->

        <div class="space-y-5">
          <h1 class="font-semibold text-2xl">Vytvoriť akciu</h1>

          <div class="">
            <label for="title">Názov
              <input type="text" v-model="form.title" id="title" class="form-input w-full" placeholder="Názov" required>
            </label>
          </div>

          <div class="">
            <label for="body">Popis akcie
              <textarea rows="12" v-model="form.body" class="w-full" id="body"></textarea>
            </label>
          </div>


          <!-- <h4 style="margin-top: 2rem">Príloha alebo obrázok</h4>
          <div class="flex">
            <div class="form-group">
              <label><strong>Obrázok, príloha</strong></label>
              <input type="file" v-model="filee" multiple placeholder="Obrázok"
                accept="image/*,application/pdf,application/doc,application/docx" class="form-control">
            </div>

          </div> -->
        </div>

      </div>

      <!-- B site -->

      <div class="col-span-3">


        <label for="dateStart">Začiatok akcie
          <input type="datetime-local" v-model="form.start_at" id="dateStart" class="form-date w-full" required>
        </label>


        <label for="dateend">Dátum ukončenia akcie
          <input type="datetime-local" v-model="form.end_at" id="dateend" class="form-date w-full" required>
        </label>



        <label>Organizátor
          <select class="form-control w-full" name="organization_id" required>
            <option disabled value="" selected hidden>---Vybrať---</option>
            <option value="1">Organizácia
            </option>
          </select>
        </label>

        <label for="">Ulica
          <input type="text" v-model="form.street" placeholder="Adresa konania" class="form-input w-full">
        </label>

        <label for="">Miesto podujatia
          <select name="village_id" class="form-select w-full" required>
            <option disabled value="" selected hidden>---Vybrať---</option>
            <option label="vybrať"></option>
            <option value="1">mestá</option>
          </select>
        </label>
        <label for="">web
          <input type="text" v-model="form.clientwww" placeholder="Odkaz na váš webovú stánku" class="form-input w-full">
        </label>

        <label for="">Registrácia je:
          <select v-model="form.registration" class="form-select w-full input-sm" required>
            <option disabled value="" selected>---Vybrať---</option>
            <option value="no">S rezerváciou</option>
            <option value="o">Bez rezervácie</option>
            <option value="no">Vstupenka</option>
          </select>
        </label>

        <label for="">Vstupné
          <select v-model="form.entryFee" class="form-select input-sm w-full" required>
            <option disabled value="" selected hidden>---Vybrať---</option>
            <option>Bez vstupného</option>
            <option>Dobrovoľné</option>
            <option>Na registráciu</option>
          </select>
        </label>

        <label for="online_ink">Online link
          <input type="text" id="online_link" name="online_link" placeholder="Link na odkaz"
            class="form-input w-full input-sm">
        </label>

        <div class="flex justify-between my-3">
          <div>
            <label for="publishet1">Publikovať teraz</label>
            <input type="radio" id="publishet1" v-model="form.published" class="form-radio">
          </div>
          <div>

            <label for="publishet2">Publikovať neskôr</label>
            <input type="radio" id="publishet2" v-model="form.published" class="form-radio">
          </div>
        </div>

        <div class="form-group my-5">
          <button type="submit" class="btn btn-primary w-full">Uložiť</button>
        </div>

      </div>
    </div>
  </div>
  <footer-component />
</template>
