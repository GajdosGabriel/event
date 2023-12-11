
const publicSite = [
  {
    path: "/",
    name: "public.index",
    components: {
      default: () => import('../components/pages/public/About.vue'),
      navigation: () => import('../components/navigation/NavigationPublic.vue')
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
      default: () => import('../components/pages/public/Show.vue'),
      navigation: () => import('../components/navigation/NavigationPublic.vue')
    },
  },
  {
    path: "/login",
    name: "login.index",
    components: {
      default: () => import('../components/auth/Card.vue'),
      navigation: () => import('../components/navigation/NavigationPublic.vue')
    },
    meta: {
      title: "Prihlásenie",
    },
  },
  {
    path: "/password/reset",
    name: "password.reset",
    components: {
      default: () => import('../components/auth/ResetPassword.vue'),
      navigation: () => import('../components/navigation/NavigationPublic.vue')
    },
    meta: {
      title: "Prihlásenie",
    },
  },
  {
    path: "/ochrana-osobnych-udajov",
    name: "ochranaOsobnychUdajov",
    components: {
      default: () => import('../components/pages/public/OchranaOsobnychUdajov.vue'),
      navigation: () => import('../components/navigation/NavigationPublic.vue')
    },
    meta: {
      title: "Ochrana osobných údajov",
    },
  },
  {
    path: "/:pathMatch(.*)*",
    name: "Stranka-sa-nenasla",
    components: {
      default: () => import('../components/pages/public/NotFoundPage.vue'),
      navigation: () => import('../components/navigation/NavigationPublic.vue')
    },
    meta: {
      title: "Stránka sa nenašla",
    },
  },
];

export default publicSite;
