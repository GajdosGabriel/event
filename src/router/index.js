import { createRouter, createWebHistory } from "vue-router";
import user from "./user";

import NavigationPublic from "../components/navigation/NavigationPublic.vue";
import OchranaOsobnyhUdajov from "../components/pages/public/OchranaOsobnychUdajov.vue";
import About from "../components/pages/public/About.vue";
import Show from "../components/pages/public/Show.vue";
import NotFoundPage from "../components/pages/public/NotFoundPage.vue";
import LoginCard from "../components/auth/Card.vue";

const routes = [
  ... user,
  {
    path: "/",
    name: "About",
    components: {
      default: About,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Ticket portál",
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

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkActiveClass: "",
  scrollBehavior(to, from, savedPosition) {
    // always scroll to top
    return { top: 0, behavior: "smooth" };
  },
});

router.beforeResolve(async (to, from, next) => {
  // Get the page title from the route meta data that we have defined
  // See further down below for how we setup this data
  const title = to.meta.title;
  //Take the title from the parameters
  const titleFromParams = to.query.pageTitle;
  // If the route has a title, set it as the page title of the document/page
  if (title) {
    document.title = title;
  }
  // If we have a title from the params, extend the title with the title
  // from our params
  if (titleFromParams) {
    document.title = `${titleFromParams} - ${document.title}`;
  }
  // Continue resolving the route
  next();
});

export default router;
