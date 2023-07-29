<script lang="ts">
import { defineComponent, reactive, ref } from "vue";
import { type EventForm } from "@/types/event";

import CardAside from "../CardAside.vue";
import FooterComponent from "../Footer.vue";
import UserEventCard from "@/components/event/UserEventCard.vue";
import InputField from "@/components/input/InputField.vue";
import SelectField from "@/components/input/SelectField.vue";
import PaginationComponent from "../PaginationComponent.vue";

export default defineComponent({
  components: { CardAside, FooterComponent, UserEventCard, PaginationComponent, InputField, SelectField },
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

          <InputField v-model="event.title" :current-value="event.title" model="event.title" placeholder="Názov akcie"
            label="Názov" />


          <div class="">
            <label for="body">Popis akcie
              <textarea rows="12" v-model="event.body" class="form-textarea w-full" id="body"></textarea>
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

        <InputField v-model="event.start_at" :current-value="event.start_at" model="event.start_at"
          input-type="datetime-local" placeholder="Začiatok akcie" label="Začiatok akcie" />

        <InputField v-model="event.end_at" :current-value="event.end_at" model="event.end_at" input-type="datetime-local"
          placeholder="Koniec akcie" label="Dátum ukončenia akcie" />




        <div>
          <label>Organizátor
            <select class="form-control w-full" name="organization_id" required>
              <option disabled value="" selected hidden>---Vybrať---</option>
              <option value="1">Organizácia
              </option>
            </select>
          </label>
        </div>

        <InputField v-model="event.street" :current-value="event.street" model="event.street" placeholder="Ulica a číslo"
          label="Ulica konania akcie" />


        <div>
          <label for="">Miesto podujatia
            <select name="village_id" class="form-select w-full" required>
              <option disabled value="" selected hidden>---Vybrať---</option>
              <option value="1">mestá</option>
            </select>
          </label>
        </div>

        <InputField v-model="event.clientwww" :current-value="event.clientwww" model="event.clientwww"
          placeholder="Odkaz na váš webovú stánku" label="Odkaz na váš webovú stánku" />


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

        <SelectField :items="[{name: 'dddddddd', value: 'yes' }]" v-model="event.entryFee" :current-value="event.entryFee" model="event.entryFee" placeholder="Vstupné"
          label="Vstupné" />

        <div>
          <div class="flex justify-between my-3">
            <div>
              <label for="publishet1">Publikovať teraz
                <input type="radio" id="publishet1" v-model="event.published" class="form-radio">
              </label>
            </div>

            <div>
              <label for="publishet2">Publikovať neskôr
                <input type="radio" id="publishet2" v-model="event.published" class="form-radio">
              </label>
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
