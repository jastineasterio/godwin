import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Lightweight named-route URL resolver.
 *
 * This application does not use Ziggy, so `route()` was referenced in several
 * AJAX-driven SMS pages without ever being defined. Clicking "Create Template",
 * "Preview Recipients", or "Confirm & Send" threw `ReferenceError: route is
 * not defined` and the request was never made.
 *
 * This small global helper resolves the named routes used by those pages. It
 * intentionally contains only URL paths — no credentials are stored here.
 */
const SMS_ROUTES = {
    'sms.templates.store': '/sms/templates',
    'sms.templates.update': (id) => `/sms/templates/${encodeURIComponent(id)}`,
    'sms.templates.toggle': (id) => `/sms/templates/${encodeURIComponent(id)}/toggle`,
    'sms.templates.destroy': (id) => `/sms/templates/${encodeURIComponent(id)}`,
    'sms.bulk.preview': '/sms/bulk/preview-recipients',
    'sms.bulk.send': '/sms/bulk/send',
    'sms.results.preview': '/sms/results/preview',
    'sms.results.send': '/sms/results/send',
    'sms.results.students': '/sms/results/students',
    'sms.admin.credits.purchase': '/sms/admin/credits/purchase',
    'sms.admin.credits.retry': (id) => `/sms/admin/credits/${encodeURIComponent(id)}/retry`,
    'sms.admin.admission-notifications.reconcile': (id) => `/sms/admin/admission-notifications/${encodeURIComponent(id)}/reconcile`,
    'sms.admin.admission-notifications.resend': (id) => `/sms/admin/admission-notifications/${encodeURIComponent(id)}/resend`,
};

window.route = (name, param = null) => {
    const entry = SMS_ROUTES[name];
    if (entry === undefined) {
        console.error(`route(): unknown route name "${name}"`);
        return '#';
    }
    return typeof entry === 'function' ? entry(param) : entry;
};
