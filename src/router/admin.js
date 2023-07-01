import NavigationAdmin from "../components/navigation/NavigationAdmin.vue";
import AdminHome from "../components/pages/admin/Home.vue";

const admin = [
  {
    path: "/admin/home",
    name: "admin.index",
    components: {
      default: AdminHome,
      navigation: NavigationAdmin,
    },
    meta: {
      title: "Správa účtu",
    },
  },
];

export default admin;
