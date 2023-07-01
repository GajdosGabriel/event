import NavigationPublic from "../components/navigation/NavigationPublic.vue";
import NavigationUser from "../components/navigation/NavigationUser.vue";
import UserHome from "../components/pages/user/Home.vue";

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
];

export default user;
