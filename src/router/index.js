import { createRouter, createWebHistory } from "vue-router";
import user from "./user";
import admin from "./admin";
import publicSite from "./publicSite";


const routes = [
  ...publicSite, ...user, ...admin
];

// const routes = baseRoutes.concat(freepublic, user, admin);

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

function nextFactory(context, middleware, index) {
  const subsequentMiddleware = middleware[index];
  // If no subsequent Middleware exists,
  // the default `next()` callback is returned.
  if (!subsequentMiddleware) return context.next;

  return (...parameters) => {
    // Run the default Vue Router `next()` callback first.
    context.next(...parameters);
    // Then run the subsequent Middleware with a new
    // `nextMiddleware()` callback.
    const nextMiddleware = nextFactory(context, middleware, index + 1);
    subsequentMiddleware({ ...context, next: nextMiddleware });
  };
}

// router.beforeEach((to, from, next) => {
//   if (to.meta.middleware) {
//     const middleware = Array.isArray(to.meta.middleware) ? to.meta.middleware : [to.meta.middleware];

//     const context = {
//       from,
//       next,
//       router,
//       to,
//     };
//     const nextMiddleware = nextFactory(context, middleware, 1);

//     return middleware[0]({ ...context, next: nextMiddleware });
//   }

//   return next();
// });

export default router;
