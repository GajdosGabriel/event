
import NavigationUser from "../components/navigation/NavigationUser.vue";
import UserHome from "../components/pages/user/Home.vue";
import UserEvents from "../components/pages/user/Events.vue";

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
    },
  },
  {
    path: "/user/events",
    name: "canal.index",
    components: {
      default: UserEvents,
      navigation: NavigationUser,
    },
    meta: {
      title: "Správa účtu",
    },
  },
];

export default user;
