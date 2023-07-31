import NavigationDashboard from "../components/navigation/NavigationDashboard.vue";
import DashboardHome from "../components/pages/dashboard/Home.vue";
import DashboardEvents from "../components/pages/dashboard/Events.vue";
import DashboardEventsCreate from "../components/pages/dashboard/EventsCreate.vue";
import DashboardSetup from "../components/pages/dashboard/Setup.vue";
import auth from "../middleware/auth";

const dashboard = [
  {
    path: "/dashboard",
    name: "dashboard.index",
    components: {
      default: DashboardHome,
      navigation: NavigationDashboard,
    },
    meta: {
      title: "Správa účtu",
      middleware: [auth],
    },
  },
  {
    path: "/dashboard/events",
    name: "dashboard.events.index",
    components: {
      navigation: NavigationDashboard,
      default: DashboardEvents,
    },
    meta: {
      title: "Vaše akcie",
      middleware: [auth],
    },
  },
  {
    path: "/dashboard/event/create",
    name: "dashboard.event.create",
    components: {
      navigation: NavigationDashboard,
      default: DashboardEventsCreate,
    },
    meta: {
      title: "Vytvoriť akciu",
      middleware: [auth],
    },
  },
  {
    path: "/dashboard/setup",
    name: "dashboard.setup",
    components: {
      default: DashboardSetup,
      navigation: NavigationDashboard,
    },
    meta: {
      title: "Nastavenie účtu",
      middleware: [auth],
    },
  },
];


export default dashboard;