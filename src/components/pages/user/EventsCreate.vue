<script lang="ts">
import { defineComponent, reactive, ref } from "vue";
import { type EventForm } from "@/types/event";

import CardAside from "../CardAside.vue";
import FooterComponent from "../Footer.vue";
import UserEventCard from "@/components/event/UserEventCard.vue";
import PaginationComponent from "../PaginationComponent.vue";

export default defineComponent({
  components: { CardAside, FooterComponent, UserEventCard, PaginationComponent },
  setup() {
    const event = reactive<EventForm>({
      title: '',
      body: '',
      street: '',
      ticket_available: '',
      canal_name: '',
      start_at: '',
      end_at: '',
      village_name: '',
      registration: '',
      entryFee: '',
      clientwww: '',
      published: '',
    });
    const image = ref(null);


    return { event, image };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <form class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <!-- <Header></Header> -->

        <div class="space-y-5">
          <h1 class="font-semibold text-2xl">Vytvoriť akciu</h1>

          <div class="">
            <label for="title">Názov
              <input type="text" v-model="event.title" id="title" class="form-input w-full" placeholder="Názov" required>
            </label>
          </div>

          <div class="">
            <label for="body">Popis akcie
              <textarea rows="12" v-model="event.body" class="w-full" id="body"></textarea>
            </label>
          </div>


          <h4 style="margin-top: 2rem">Príloha alebo obrázok</h4>
          <div class="flex">
            <div class="form-group">
              <label><strong>Obrázok, príloha</strong></label>
              <input type="file" multiple v-on:change="image" ref="fileInput" placeholder="Obrázok"
                accept="image/*,application/pdf,application/doc,application/docx" class="form-control">
            </div>
          </div>
        </div>

      </div>

      <!-- B site -->

      <div class="col-span-3 flex-col space-y-5">

        <div>
          <label for="dateStart">Začiatok akcie
            <input type="datetime-local" v-model="event.start_at" id="dateStart" class="form-date w-full" required>
          </label>
        </div>

        <div>
          <label for="dateend">Dátum ukončenia akcie
            <input type="datetime-local" v-model="event.end_at" id="dateend" class="form-date w-full" required>
          </label>
        </div>


        <div>
          <label>Organizátor
            <select class="form-control w-full" name="organization_id" required>
              <option disabled value="" selected hidden>---Vybrať---</option>
              <option value="1">Organizácia
              </option>
            </select>
          </label>
        </div>

        <div>
          <label for="">Ulica
            <input type="text" v-model="event.street" placeholder="Adresa konania" class="form-input w-full">
          </label>
        </div>

        <div>
          <label for="">Miesto podujatia
            <select name="village_id" class="form-select w-full" required>
              <option disabled value="" selected hidden>---Vybrať---</option>
              <option value="1">mestá</option>
            </select>
          </label>
        </div>

        <div>
          <label for="">web
            <input type="text" v-model="event.clientwww" placeholder="Odkaz na váš webovú stánku"
              class="form-input w-full">
          </label>
        </div>

        <div>
          <label for="">Registrácia je:
            <select v-model="event.registration" class="form-select w-full" required>
              <option disabled value="" selected hidden>---Vybrať---</option>
              <option value="no">S rezerváciou</option>
              <option value="o">Bez rezervácie</option>
              <option value="nod">Vstupenka</option>
            </select>
          </label>
        </div>

        <div>
          <label for="">Vstupné
            <select v-model="event.entryFee" class="form-select input-sm w-full" required>
              <option disabled value="" selected hidden>---Vybrať---</option>
              <option>Bez vstupného</option>
              <option>Dobrovoľné</option>
              <option>Na registráciu</option>
            </select>
          </label>
        </div>

        <div>
          <label for="online_ink">Online link
            <input type="text" id="online_link" name="online_link" placeholder="Link na odkaz"
              class="form-input w-full input-sm">
          </label>
        </div>

        <div>
          <div class="flex justify-between my-3">
            <div>
              <label for="publishet1">Publikovať teraz</label>
              <input type="radio" id="publishet1" v-model="event.published" class="form-radio">
            </div>
            <div>

              <label for="publishet2">Publikovať neskôr</label>
              <input type="radio" id="publishet2" v-model="event.published" class="form-radio">
            </div>
          </div>
        </div>

        <div class="my-5">
          <button type="submit" class="btn btn-primary w-full">Uložiť</button>
        </div>

      </div>
    </form>
  </div>
  <footer-component />
</template>
