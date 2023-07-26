import NavigationUser from "../components/navigation/NavigationUser.vue";
import UserHome from "../components/pages/user/Home.vue";
import UserEvents from "../components/pages/user/Events.vue";
import UserSetup from "../components/pages/user/Setup.vue";
import auth from "../middleware/auth";

const user = [
  {
    path: "/user/home",
    name: "user.index",
    components: {
      default: UserHome,
      navigation: NavigationUser,
    },
    meta: {
      title: "Správa účtu",
      middleware: [auth],
    },
  },
  {
    path: "/user/events",
    name: "canal.index",
    components: {
      navigation: NavigationUser,
      default: UserEvents,
    },
    meta: {
      title: "Vaše akcie",
      middleware: [auth],
    },
  },
  {
    path: "/user/setup",
    name: "user.setup",
    components: {
      default: UserSetup,
      navigation: NavigationUser,
    },
    meta: {
      title: "Nastavenie účtu",
      middleware: [auth],
    },
  },
];


export default user;
