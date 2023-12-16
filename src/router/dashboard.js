
import auth from "../middleware/auth";

const dashboard = [
  {
    path: "/dashboard",
    name: "dashboard.index",
    components: {
      default: () => import('../components/pages/dashboard/Home.vue'),
      navigation: () => import('../components/navigation/NavigationDashboard.vue')
    },
    meta: {
      title: "Správa účtu",
      // middleware: [auth],
    },
  },
  {
    path: "/dashboard/events",
    name: "dashboard.events.index",
    components: {
      default: () => import('../components/pages/dashboard/Events.vue'),
      navigation: () => import('../components/navigation/NavigationDashboard.vue')
    },
    meta: {
      title: "Vaše akcie",
      // middleware: [auth],
    },
  },
  {
    path: "/dashboard/event/create",
    name: "dashboard.event.create",
    components: {
      default: () => import('../components/pages/dashboard/EventsCreate.vue'),
      navigation: () => import('../components/navigation/NavigationDashboard.vue')
    },
    meta: {
      title: "Vytvoriť akciu",
      // middleware: [auth],
    },
  },
  {
    path: "/dashboard/setup",
    name: "dashboard.setup",
    components: {
      default: () => import('../components/pages/dashboard/Setup.vue'),
      navigation: () => import('../components/navigation/NavigationDashboard.vue')
    },
    meta: {
      title: "Nastavenie účtu",
      // middleware: [auth],
    },
  },
];


export default dashboard;
