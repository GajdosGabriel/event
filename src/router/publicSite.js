import NavigationPublic from "../components/navigation/NavigationPublic.vue";
import OchranaOsobnyhUdajov from "../components/pages/public/OchranaOsobnychUdajov.vue";
import About from "../components/pages/public/About.vue";
import Show from "../components/pages/public/Show.vue";
import NotFoundPage from "../components/pages/public/NotFoundPage.vue";
import LoginCard from "../components/auth/Card.vue";
import ResetPassword from "../components/auth/ResetPassword.vue";

const publicSite = [
  {
    path: "/",
    name: "public.index",
    components: {
      default: About,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Ticket portál",
      // middleware: [auth],
    },
  },
  {
    path: "/event/:eventId/:eventSlug",
    name: "event.show",
    components: {
      default: Show,
      navigation: NavigationPublic,
    },
  },
  {
    path: "/login",
    name: "login.index",
    components: {
      default: LoginCard,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Prihlásenie",
    },
  },
  {
    path: "/password/reset",
    name: "password.reset",
    components: {
      default: ResetPassword,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Prihlásenie",
    },
  },
  {
    path: "/ochrana-osobnych-udajov",
    name: "ochranaOsobnychUdajov",
    components: {
      default: OchranaOsobnyhUdajov,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Ochrana osobných údajov",
    },
  },
  {
    path: "/:pathMatch(.*)*",
    name: "Stranka-sa-nenasla",
    components: {
      default: NotFoundPage,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Stránka sa nenašla",
    },
  },
];

export default publicSite;
