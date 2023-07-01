<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
// import { Event } from "../../../types/event";
import Header from "../Header.vue";
import IndexCard from "../../event/IndexCard.vue";
import CardAside from "../CardAside.vue";
import PaginationComponent from "../PaginationComponent.vue";
import FooterComponent from "../Footer.vue";
import Spinner from "../Spinner.vue";

import UseEvent from "../../../store/event.js";
import EventVillageCard from "./EventVillageCard.vue";

export default defineComponent({
  components: { Header, IndexCard, CardAside, PaginationComponent, Spinner, FooterComponent, EventVillageCard },
  setup() {
    const { loading, meta, links, fetchEvents, events, paginationUrl } = UseEvent();

    onMounted(() => {
      fetchEvents();
    });

    const paginatorUrl = (url: string) => {
      paginationUrl(url);
      fetchEvents();
    };

    return { events, loading, meta, links, paginatorUrl };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <!-- <Header></Header> -->
        <Spinner v-if="loading"></Spinner>

       
          <div class="space-y-5">
            <h1 class="font-semibold text-2xl ">Ochrana osobných údajov</h1>

            <p>
              Podmienky spracúvania osobných údajov na eshope (www.zastavy-vlajky.sk). Ak ste mojim zákazníkom,
              odberateľom noviniek alebo návštevníkom webu, zverujete mi svoje osobné údaje. Zodpovedám za ich ochranu a
              zabezpečenie. Zoznámte sa preto, prosím, s ochranou osobných údajov, zásadami a právami, ktoré máte v
              súvislosti s GDPR (Nariadenie o ochrane osobných údajov platné od 25.5.2018).
            </p>

            <p>
              <strong>Kto je správca?</strong>

              Správcom osobných údajov pre zákazníkom, odberateľom noviniek je firma Gajdoš Gabriel, programátor
              projektu. Východná 3, 911 08 Trenčín, (ďalej len „prevádzkovateľ“) spracúva vami poskytnuté osobné údaje,
              v súlade s ustanoveniami Zákona č.122/2013 Z. z. o ochrane osobných údajov a o zmene a doplnení niektorých
              zákonov v znení zákona č. 84/2014 Z. z. v aktuálnom a platnom znení (ďalej len „Zákon“). Vaše osobné údaje
              spracovávam ako správca, t.j. určujem, ako budú spracované, za akým účelom a na ako dlho. Prípadne vyberám
              ďalších spracovateľov, ktorí mi s ich spracovaním budú pomáhať.
            </p>
            <p>
              <strong>Kontaktné údaje</strong>

              Kontaktovať správcu osobných údajov môžete z tejto webovej stránky v záložke Kontakt.
            </p>
            <p>
              <strong>Prehlásenie</strong>

              Prehlasujem, že ako správca vašich osobných údajov spĺňam všetky zákonné povinnosti vyžadované platnou
              legislatívou, najmä zákonom o ochrane osobných údajov a GDPR, to znamená, že: Budem spracovávať vaše
              osobné údaje len na základe platného právneho dôvodu, a to predovšetkým oprávneného záujmu, plnenia
              zmluvy, zákonnej povinnosti alebo udeleného súhlasu. Splním si podľa článku 13 GDPR informačnú povinnosť
              ešte pred zahájením spracovania osobných údajov. Umožním vám a budem vás podporovať v uplatňovaní a plnení
              si vašich práv podľa zákona o ochrane osobných údajov a GDPR. Rozsah osobných údajov a účely spracovania
              Spracovávam osobné údaje, ktoré mi sami zveríte, a to z nasledujúcich dôvodov (pre naplnenie týchto
              účelov): Poskytovanie služieb, plnenie zmluvy. Vaše osobné údaje v rozsahu: e-mail, nevyhnutne potrebujeme
              k plneniu zmluvy (napr. zaslanie prístupov do aplikácie, alebo informácií o produktoch).
            </p>
            <p>
              <strong>Vedenie účtovníctva.</strong>
              Ak ste mojimi zákazníkmi, tak vaše osobné údaje (fakturačné údaje) nevyhnutne potrebujem, aby sa vyhovelo
              zákonnej povinnosti pre vystavovanie a evidenciu daňových dokladov. Marketing – zasielanie informačných
              mailov. Vaše osobné údaje (e-mail a meno), môžem využívať za účelom priameho marketingu – zasielanie
              obchodných oznámení. Ak ste mojim zákazníkom, robím tak z oprávneného záujmu, pretože predpokladám, že vás
              moje novinky zaujímajú a to po dobu 5 rokov od poslednej objednávky. Ak mojim zákazníkom nie ste,
              informačné maily zasielam len na základe vášho súhlasu, počas doby 5 rokov od jeho udelenia. V oboch
              prípadoch môžete tento súhlas odvolať použitím odhlasovacieho odkazu nachádzajúceho sa v každom e-maile.
              Vaše osobné údaje si ponechávam počas doby premlčacích období, pokiaľ zákon nestanoví ďalšiu dobu k ich
              uchovaniu alebo som v konkrétnych prípadoch neuviedli inak.
            </p>
            <p>
              <strong>Cookies</strong>

              Pri prechádzaní našich webových stránok zaznamenávam vašu IP adresu, ako dlho sa na stránke zdržíte a z
              ktorej stránke prichádzate. Používanie cookies pre meranie návštevnosti webu a prispôsobenie zobrazenia
              webových stránok vnímam ako svoj oprávenený záujem správcu, pretože verím, že vďaka tomu vám môžem
              ponúknuť ešte lepšie služby. Cookies pre cielenie reklamy budú spracované len na základe vášho súhlasu.
              Moje webové stránky je možné prechádzať aj v režime, ktorý neumožňuje zbieranie osobných údajov.
              Používanie cookies môžete na svojom počítači zakázať.
            </p>
            <p>
              Zabezpečenie a ochrana osobných údajov V maximálnej možnej miere chránim osobné údaje pomocou moderných
              technológií, ktoré zodpovedajú stupňu technického rozvoja. Chránim ich, ako keby boli moje vlastné. Prijal
              som a udržiavam všetky možné (aktuálne známe) technické a organizačné opatrenia, ktoré zamedzujú
              zneužitiu, poškodeniu alebo zničeniu vašich osobných údajov.

              <strong>Poskytovanie osobných údajov tretím osobám</strong>

              K vašim osobným údajom môžu mať prístup moji zamestnanci alebo spolupracovníci. Pre zabezpečenie
              konkrétnych spracovateľských operácií, ktoré nedokážem poskytovať vo vlastnej réžii, využívam služba a
              aplikácie spracovateľov: Websuport s.r.o. Je možné, že sa pre uľahčenie a skvalitnenie spracovania v
              budúcnosti rozhodnem využiť ďalšie aplikácie alebo spracovateľov. Sľubujem vám však, že v takomto prípade
              pri výbere budem na spracovateľov klásť minimálne rovnaké nároky na zabezpečenie a kvalitu spracovania ako
              na seba. Poskytovanie údajov mimo Európskej únii Akékoľvek spracovanie osobných údajov bude vykonávané na
              území EU.
            </p>
            <p>
              <strong>Vaše práva v súvislosti s ochranou osobných údajov</strong>

              Máte právo na informácie, ktoré sa napĺňa už touto informačnou stránkou so zásadami spracovania osobných
              údajov. Vďaka právu na prístup ma môžete kedykoľvek vyzvať a ja vám doložím do doby 10 dní, aké vaše
              osobné údaje spracovávam a prečo. Ak sa u vás niečo zmení alebo budete považovať svoje osobné údaje za
              neaktuálne alebo neúplné, máte právo na doplnenie a zmenu osobných údajov. Právo na obmedzenie spracovania
              môžete využiť ak sa domnievate, že spracovávam vaše nepresné údaje, prípadne ak sa domnievate, že
              vykonávam spracovávanie údajov nezákonne, ale nechcete ich všetky zmazať alebo ak by ste vzniesli námietku
              proti ich spracovaniu. Obmedziť môžete rozsah osobných údajov alebo účely spracovania (napr. odhlásením sa
              z distribučného zoznamu obmedzujete účel spracovania pre zasielanie obchodných oznámení). Právo na
              prenositeľnosť. Pokiaľ by ste chceli svoje osobné údaje vziať a preniesť k niekomu inému, budem postupovať
              rovnako ako pri využití práva na prístup – len s tým rozdielom, že vám informácie dodám v strojovo
              čitateľnej podobe. V tomto prípade potrebujem na to aspoň 20 dní.
            </p>
            <p>
              <strong>Právo na výmaz.</strong> Vaším ďalším právom je právo na výmaz (byť zabudnutý). Nechcem na vás
              zabudnúť, ale pokiaľ si to budete želať, máte na to právo. V takomto prípade vymažem všetky vaše osobné
              údaje zo svojho systému a zo systémov všetkých ďalších spracovateľov a záloh. Na zabezpečenie práva na
              výmaz potrebujem 10 dní. V niektorých prípadoch som viazaný zákonnou povinnosťou, a napr. musím evidovať
              vystavené daňové doklady počas doby stanovenej zákonom. V tomto prípade teda zmažem všetky také osobné
              údaje, ktoré nie sú viazané iným zákonom. O dokončení výmazu vás budem informovať mailom. Sťažnosť na Úrad
              na ochranu osobných údajov. Ak máte pocit, že s vašimi údajmi nezachádzam v súlade so zákonom, máte právo
              sa so svojou žiadosťou kedykoľvek obrátiť na Úrad pre ochranu osobných údajov. Budem rád, ak o takomto
              prípadnom podozrení budete informovať najprv mňa, aby sme s tým mohli niečo urobiť a prípadne pochybenie
              okamžite napraviť. Odhlásenie zo zasielania newsletterov a obchodných oznámení. Inšpiratívne emaily,
              články, produkty alebo služby vám posielam, ak ste môj zákazník na základe môjho oprávneného záujmu. Ak
              zákazníkom ešte nie ste, posielam vám ich len na základe vášho súhlasu. V oboch prípadoch môžete ukončiť
              odber mojich emailov kliknutím na odhlasovací odkaz v každom zaslanom maile.
            </p>
            <p>
              <strong>Mlčanlivosť</strong>

              Dovoľujeme si Vás uistiť, že moji zamestnanci i spolupracovníci, ktorí budú spracovávať vaše osobné údaje
              sú povinní zachovávať mlčanlivosť o osobných údajoch a bezpečnostných opatreniach, ktorých zverejnenie by
              mohlo ohroziť zabezpečenie vaších osobných údajov. Táto mlčanlivosť pritom trvá aj po skončení zmluvných
              záväzkov so mnou. Bez vášho súhlasu nebudú vaše osobné údaje vydané žiadnej inej tretej strane. Tieto
              zásady spracovania osobných údajov platia od 25.5.2018 a nahrádzajú predchádzajúcu Ochranu osobných
              údajov, ktorej pôvodné znenie je tu:
            </p>
            <p>
              Podmienky spracúvania osobných údajov prostredníctvom webovej stránky internetového obchodu
              www.zastavy-vlajky.sk. firma Gajdoš Gabriel, Východná 3, 911 08 Trenčín, (ďalej len „prevádzkovateľ“)
              spracúva Vami poskytnuté osobné údaje, v súlade s ustanoveniami Zákona č.122/2013 Z. z. o ochrane osobných
              údajov a o zmene a doplnení niektorých zákonov v znení zákona č. 84/2014 Z. z. v aktuálnom a platnom znení
              (ďalej len „Zákon“). Prevádzkovateľ spracúva Vami poskytnuté osobné údaje na webovej stránke
              prevádzkovateľa za účelom predaja tovaru kupujúcim, vyhotovenia faktúry a odoslania tovaru. Osobné údaje
              sa spracúvajú v informačnom systéme IS elektronický obchod (e-shop). Rozsah spracúvaných osobných údajov
              je : meno, priezvisko, adresa na doručenie, telefónne číslo, e-mailová adresa, bankové spojenie. Uvedené
              osobné údaje sa spracúvajú na žiadosť dotknutej osoby v rámci zmluvného/predzmluvného vzťahu, súlade s §
              10 ods. 3 písm. b) Zákona a ďalších právnych predpisov, najmä Občianskeho zákonníka, Obchodného zákonníka,
              Zákona č. 22/2004 Z. z. o elektronickom obchode a o zmene a doplnení zákona č. 128/2002 Z. z. o štátnej
              kontrole vnútorného trhu vo veciach ochrany spotrebiteľa a o zmene a doplnení niektorých zákonov v znení
              zákona č. 284/2002 Z. z., zákona č. 108/2000 Z. z. o ochrane spotrebiteľa pri podomovom predaji a
              zásielkovom predaji, zákona č. 215/2002 Z. z. o elektronickom podpise a o zmene a doplnení niektorých
              zákonov, vyhláška Národného bezpečnostného úradu č. 542/2002 Z. z. o spôsobe a postupe používania
              elektronického podpisu v obchodnom a administratívnom styku. Uvedené osobné údaje poskytujete dobrovoľne,
              v prípade neposkytnutia všetkých povinných vyššie uvedených osobných údajov, Vám prevádzkovateľ nezaručuje
              poskytnutie služby. Poskytnutím vyššie uvedených osobných údajov sa zaväzujete, že osobné údaje sa týkajú
              Vašej osoby, alebo spoločnosti v mene ktorej ju máte právo registrovať a údaje sú uvedené správne.
              Prevádzkovateľ nezodpovedá za škody spôsobené uvedením nesprávnych údajov. Poskytnutie nepravdivých
              osobných údajov je sankciované zo Zákona. Tretie strany/Príjemcovia, ktorým môžu byť Vami poskytnuté
              osobné údaje sprístupnené na nahliadnutie, prípadne poskytnuté na spracúvanie sú oprávnené subjekty,
              ktorým to umožňuje osobitný zákon v zmysle § 10 ods. 2 zákona. Poskytnuté osobné údaje sa nebudú
              zverejňovať, ani prenášať do tretích krajín mimo EU a EHP.
            </p>
            <p>
              Vaše osobné údaje bude prevádzkovateľ spracúvať od zaregistrovania na web stránke prevádzkovateľa až po
              dobu vášho odhlásenia z databázy. Prostredníctvom odkazu nachádzajúcom sa v každom maile budú Vaše osobné
              údaje bezpečne vymazané zo systému. Zároveň môžete požiadať prevádzkovateľa o preukázanie totožnosti
              oprávnenej osoby u prevádzkovateľa, ktorá získava Vaše osobné údaje, a využiť svoje práva pri spracúvaní
              osobných údajov, podľa § 28 zákona.
            </p>

            <h4>Podmienky spracovania osobných údajov prostredníctvom tretích strán</h4>
            <a target="_blank" href="https://www.youtube.com/t/terms">YouTube podmienky.</a> <br />
            <a target="_blank" href="https://policies.google.com/privacy">Google podmienky.</a>
          </div>
    
      </div>

      <div class="col-span-3">
        <CardAside>
          <template v-slot:title>Prevádzkovateľ</template>
          <template v-slot:body>Portál prevádzkuje ...</template>
        </CardAside>
      </div>

      <div class="col-span-8">
        <pagination-component :meta="meta" :links="links" @fetchUrl="paginatorUrl"></pagination-component>
      </div>
    </div>
  </div>
  <footer-component />
</template>
