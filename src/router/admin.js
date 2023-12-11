

const admin = [
  {
    path: "/admin/home",
    name: "admin.index",
    components: {
      default: () => import('../components/pages/admin/Home.vue'),
      navigation: () => import('../components/navigation/NavigationAdmin.vue')
    },
    meta: {
      title: "Správa účtu",
    },
  },
];

export default admin;
