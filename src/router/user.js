import NavigationPublic from "../components/navigation/NavigationPublic.vue";
import UserHome from "../components/pages/user/Home.vue";

const user = [
  {
    path: "/user/home",
    name: "user.index",
    components: {
      default: UserHome,
      navigation: NavigationPublic,
    },
    meta: {
      title: "Správa účtu",
    },
  },
];

export default user;
