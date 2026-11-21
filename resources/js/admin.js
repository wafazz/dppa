import { createApp } from 'vue';
import MembersIndex from './pages/MembersIndex.vue';
import MemberShow from './pages/MemberShow.vue';
import PositionsIndex from './pages/PositionsIndex.vue';
import AccountsIndex from './pages/AccountsIndex.vue';
import DashboardIndex from './pages/DashboardIndex.vue';
import SettingsIndex from './pages/SettingsIndex.vue';
import InvitationsIndex from './pages/InvitationsIndex.vue';
import CategoriesIndex from './pages/CategoriesIndex.vue';
import ProfileIndex from './pages/ProfileIndex.vue';
import VotesIndex from './pages/VotesIndex.vue';
import EmailTemplatesIndex from './pages/EmailTemplatesIndex.vue';
import AnnouncementsIndex from './pages/AnnouncementsIndex.vue';

/*
 * Each committee page renders a placeholder element with data-vue and JSON data-props.
 * Blade passes the server-side permission flags through those props.
 *
 * Turbo swaps the page body on sidebar navigation without a reload, so Vue apps are
 * unmounted before each swap and mounted again on the new page.
 */
const components = {
    'dashboard-index': DashboardIndex,
    'settings-index': SettingsIndex,
    'members-index': MembersIndex,
    'member-show': MemberShow,
    'positions-index': PositionsIndex,
    'categories-index': CategoriesIndex,
    'accounts-index': AccountsIndex,
    'invitations-index': InvitationsIndex,
    'profile-index': ProfileIndex,
    'votes-index': VotesIndex,
    'email-templates-index': EmailTemplatesIndex,
    'announcements-index': AnnouncementsIndex,
};

let mountedApps = [];

function mountPageApps() {
    // Clear anything still mounted so a page is never mounted twice.
    unmountPageApps();

    document.querySelectorAll('[data-vue]').forEach((element) => {
        const component = components[element.dataset.vue];

        if (!component) {
            return;
        }

        const props = JSON.parse(element.dataset.props || '{}');
        const app = createApp(component, props);

        app.mount(element);
        mountedApps.push(app);
    });
}

function unmountPageApps() {
    mountedApps.forEach((app) => app.unmount());
    mountedApps = [];
}

document.addEventListener('turbo:before-render', unmountPageApps);
document.addEventListener('turbo:load', mountPageApps);

// This module can run after Turbo has already fired turbo:load for the first page.
mountPageApps();
