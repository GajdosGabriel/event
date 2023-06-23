import { createRouter, createWebHistory } from 'vue-router';

import NavigationPublic from '../components/navigation/NavigationPublic.vue';
import About from '../components/pages/public/About.vue';
import Show from '../components/pages/public/Show.vue';


const routes = [
    {
        path: '/',
        name: 'About',
        components: {
            default: About,
            navigation: NavigationPublic,
        },
        meta: {
            title: 'Ticket portál'
        }
    },
    {
        path: '/event/:eventId/:eventSlug:',
        name: 'event.show',
        components: {
            default: Show,
            navigation: NavigationPublic,
        },
        meta: {
            title: 'Show event'
        }
    },
    // {
    //     path: '/:pathMatch(.*)*', name: 'Stranka-sa-nenasla', component: NotFoundPage, meta: {
    //         title: 'Stránka sa nenašla'
    //     }
    // },
]


const router = createRouter({
    history: createWebHistory(),
    routes,
    linkActiveClass: '',
    scrollBehavior(to, from, savedPosition) {
        // always scroll to top
        return { top: 0, behavior: 'smooth' }
    },
})


router.beforeResolve(async (to, from, next) => {
    // Get the page title from the route meta data that we have defined
    // See further down below for how we setup this data
    const title = to.meta.title

    //Take the title from the parameters
    const titleFromParams = to.params.pageTitle;
    // If the route has a title, set it as the page title of the document/page
    if (title) {
        document.title = title
    }
    // If we have a title from the params, extend the title with the title
    // from our params
    if (titleFromParams) {
        document.title = `${titleFromParams} - ${document.title}`;
    }
    // Continue resolving the route
    next()
})


export default router;