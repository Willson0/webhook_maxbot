import {createRouter, createWebHistory} from 'vue-router';
import App from "@/App.vue";
import MarkdownView from "@/views/MarkdownView.vue";

const routes = [
    {
        path: "/",
        component: MarkdownView,
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

export default router;