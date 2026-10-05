export function announcementBanner(version, storage = () => window.localStorage) {
    return {
        dismissed: false,
        init() {
            try { this.dismissed = storage().getItem('site-announcement-dismissed') === version; } catch { /* Storage may be unavailable. */ }
        },
        dismiss() {
            this.dismissed = true;
            try { storage().setItem('site-announcement-dismissed', version); } catch { /* Dismissal still works for this page. */ }
        },
    };
}
if (typeof window !== 'undefined') window.announcementBanner = announcementBanner;
