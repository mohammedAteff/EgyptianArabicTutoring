<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin Console' }} | {{ config('business.site_name') }} Admin</title>

    <!-- Google Fonts: Cairo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
        try { document.documentElement.dataset.sidebarCollapsed = localStorage.getItem('admin_sidebar_collapsed') === '1' ? 'true' : 'false'; } catch {}
    </script>
    <style>
        @media (max-width: 767px) {
            #admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            #admin-sidebar.open {
                transform: translateX(0) !important;
            }
        }
        @media (min-width: 768px) {
            #admin-sidebar {
                transform: translateX(0) !important;
            }
            html[data-sidebar-collapsed="true"] #admin-sidebar { display: none; }
            html[data-sidebar-collapsed="true"] #admin-content { margin-left: 0; }
        }
        /* Custom sleek dark scrollbar for admin sidebar */
        #admin-sidebar-nav::-webkit-scrollbar {
            width: 5px;
        }
        #admin-sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        #admin-sidebar-nav::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 9999px;
        }
        #admin-sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
        #admin-sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
        }
    </style>
</head>
<body data-admin-ux class="h-full font-sans antialiased bg-slate-100"
      x-data="adminMobileNav()" 
      @keydown.window="handleKeydown($event)"
      @staff-quick-actions-open.window="if (mobileSidebarOpen) closeSidebar()">

    <!-- Mobile Sidebar Backdrop (Section 32) -->
    <div id="admin-sidebar-backdrop"
         x-show="mobileSidebarOpen"
         x-cloak
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs md:hidden"
         style="display: none;"
         @click="closeSidebar()"></div>

    <div class="h-screen flex overflow-hidden bg-slate-100">

        <!-- Sidebar for Desktop & Mobile Drawer (Section 32) -->
        <aside id="admin-sidebar"
               role="dialog"
               aria-modal="true"
               aria-label="Admin Navigation"
               :class="mobileSidebarOpen ? 'open translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col transition-transform duration-300 ease-in-out md:translate-x-0 md:fixed md:shrink-0 border-r border-slate-800">
            
            <!-- Brand / Logo -->
            <div class="h-16 flex items-center justify-between px-6 bg-slate-950 border-b border-slate-800">
                <a href="{{ route(auth('web')->user()?->isAssistant() ? 'admin.operations.index' : 'admin.dashboard') }}" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-slate-950 font-bold font-serif shadow-md">
                        <span aria-hidden="true">A</span>
                    </div>
                    <div>
                        <div class="font-bold text-white text-sm leading-tight tracking-tight">Operations Console</div>
                        <div class="text-xs text-amber-400 font-medium">{{ config('business.tutor_name') }}</div>
                    </div>
                </a>
                <button type="button"
                        id="admin-sidebar-close"
                        @click="closeSidebar()"
                        aria-label="Close Navigation Menu"
                        class="md:hidden p-2.5 min-w-[44px] min-h-[44px] flex items-center justify-center text-slate-400 hover:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer touch-manipulation">
                    <svg class="w-6 h-6 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <x-admin.navigation :groups="$adminNavigation" :unread-count="$sidebarUnreadCount" :role="auth('web')->user()?->role" />

            <!-- Current User & Logout Footer -->
            <div class="p-3 bg-slate-900 border-t border-slate-800/80 shrink-0">
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/70 border border-slate-700/60 shadow-xs">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 text-slate-950 font-bold flex items-center justify-center text-xs shrink-0 shadow-xs">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="truncate text-xs">
                            <div class="font-semibold text-white truncate leading-tight">{{ auth()->user()->name ?? 'Administrator' }}</div>
                            <div class="text-slate-400 text-[11px] leading-tight mt-0.5">{{ auth('web')->user()?->roleLabel() }}</div>
                        </div>
                    </div>
                    <form action="{{ route('admin.logout') }}" method="POST" class="shrink-0 ml-1">
                        @csrf
                        <button type="submit" title="Sign Out" class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700/60 rounded-lg transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>



        <!-- Main Content Area -->
        <div id="admin-content" class="flex-1 flex flex-col min-w-0 overflow-hidden md:ml-64">
            
            <!-- Topbar Header -->
            <header class="h-16 shrink-0 bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8 flex items-center justify-between shadow-xs z-10">
                
                <!-- Left: Hamburger toggle + Global Search Form -->
                <div class="flex min-w-0 items-center gap-2 sm:gap-4 flex-1 max-w-xl">
                    <button type="button"
                            id="admin-menu-toggle"
                            @click="openSidebar()"
                            aria-controls="admin-sidebar"
                            :aria-expanded="mobileSidebarOpen ? 'true' : 'false'"
                            aria-label="Open Navigation Menu"
                            class="md:hidden p-2.5 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer touch-manipulation">
                        <svg class="w-6 h-6 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <button type="button" id="admin-desktop-sidebar-toggle" @click="toggleDesktopSidebar()"
                        aria-controls="admin-sidebar" :aria-expanded="desktopCollapsed ? 'false' : 'true'"
                        :aria-label="desktopCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" :title="desktopCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        class="hidden min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-600 md:inline-flex">
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path :d="desktopCollapsed ? 'm13 9 3 3-3 3' : 'm16 9-3 3 3 3'"/></svg>
                    </button>

                    <form action="{{ route('admin.search') }}" method="GET" class="hidden min-w-0 w-full sm:block">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search students, bookings, resources..." 
                                   class="w-full pl-9 pr-4 py-1.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all">
                        </div>
                    </form>
                </div>

                <!-- Right: Cairo Live Clock, Notification Bell & Public Website Link -->
                <div class="flex items-center gap-2 sm:gap-4 text-sm font-medium">
                    <x-staff-quick-actions />
                    <div class="hidden xl:flex items-center gap-2 px-3 py-1 bg-amber-50 text-amber-900 border border-amber-200/80 rounded-full text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        @php $cairoClock = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay(app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone(), now('UTC')); @endphp
                        <span>{{ $cairoClock['city'] }} Time: {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime(now(app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone())) }} ({{ $cairoClock['utc_offset'] }})</span>
                    </div>    <form method="POST" action="{{ route('admin.preferences.time') }}" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2 ">
        @csrf
        <label for="staff-time-format" class="sr-only lg:not-sr-only text-xs whitespace-nowrap">My time format</label>
        <select id="staff-time-format" name="time_format" class="rounded border p-1 text-xs">
            <option value="12" @selected(auth('web')->user()?->time_format === '12')>12-hour</option>
            <option value="24" @selected(auth('web')->user()?->time_format !== '12')>24-hour</option>
        </select>
        <button class="rounded bg-slate-800 px-2 py-1 text-xs text-white">Save</button>
    </form>

                    <!-- Notification Bell -->
                    @if(auth('web')->user()?->isAdmin())
                    <a href="{{ route('admin.notifications.index') }}" class="relative p-2 text-slate-500 hover:text-amber-600 transition-colors" title="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if(($sidebarUnreadCount ?? 0) > 0)
                            <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 rounded-full bg-amber-600 ring-2 ring-white"></span>
                        @endif
                    </a>

                    @endif
                    <a href="{{ route('home') }}" target="_blank" aria-label="View Website" class="flex min-h-11 min-w-11 items-center justify-center gap-1.5 text-xs text-slate-600 hover:text-amber-600 transition-colors">
                        <span class="hidden lg:inline">View Website</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>
            </header>

            <!-- Flash & Alert Banners -->
            @php
                $offsiteBackupStatus = \App\Domains\CMS\Models\Setting::get('last_offsite_backup_status');
                $offsiteBackupCategory = \App\Domains\CMS\Models\Setting::get('last_offsite_backup_category', 's3_replication_failed');
            @endphp
            @if($offsiteBackupStatus && str_starts_with($offsiteBackupStatus, 'failed'))
                <div class="shrink-0 bg-amber-50 border-b border-amber-300 px-6 py-3 flex items-center justify-between text-sm text-amber-900 shadow-xs">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <span class="font-bold">Warning: Offsite Backup Replication Failed.</span>
                            <span class="text-xs text-amber-800 ml-1">Category: <code class="font-mono bg-amber-100 px-1.5 py-0.5 rounded text-amber-900 font-semibold">{{ $offsiteBackupCategory }}</code>. Check system health and storage configuration.</span>
                        </div>
                    </div>
                    @if(auth('web')->user()?->isSuperAdmin())
                    <a href="{{ route('admin.health') }}" class="text-xs font-bold text-amber-900 underline hover:text-amber-950 shrink-0">View System Health &rarr;</a>
                    @endif
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                    <p class="font-semibold">Changes were not saved. Please correct these fields:</p>
                    <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if(session('success'))
                <div class="shrink-0 bg-emerald-50 border-b border-emerald-200 px-6 py-3 flex items-center justify-between text-sm text-emerald-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="shrink-0 bg-red-50 border-b border-red-200 px-6 py-3 flex items-center justify-between text-sm text-red-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Main Page Content -->
            <main class="ml-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                @php($activeLearningPreview = session(\App\Domains\Lms\Services\LmsPreviewService::SESSION_KEY))
                @if(is_array($activeLearningPreview) && ($activeLearningPreview['actor_id']??null)===auth('web')->id() && auth('web')->user()?->can('preview', \App\Domains\Lms\Models\Course::class) && !request()->routeIs('admin.lms.preview.*'))
                <div role="status" class="sticky top-0 z-20 mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
                    <div><p class="font-semibold text-amber-950">Previewing as {{ $activeLearningPreview['label']??'a generic learner' }}</p><p class="mt-1 text-xs text-amber-900">Read-only preview context · Student-area actions are paused until you exit.</p></div>
                    <form method="POST" action="{{ route('admin.lms.preview.exit',$activeLearningPreview['id']) }}">@csrf<x-lms.button tone="neutral">Exit preview</x-lms.button></form>
                </div>
                @endif
                {{ $slot ?? '' }}
                @yield('content')
                @if(request()->routeIs('admin.analytics*', 'admin.reports*'))
                    <p class="mt-8 text-xs text-slate-500">IP country data by <a href="https://db-ip.com/" class="underline">DB-IP</a> (<a href="https://creativecommons.org/licenses/by/4.0/" class="underline">CC BY 4.0</a>).</p>
                @endif
            </main>
        </div>
    </div>
    @if(auth('web')->user()?->isAdmin())
    @include('admin.partials.media-picker')
    @endif

    <script>


    function adminMobileNav() {
        return {
            desktopCollapsed: document.documentElement.dataset.sidebarCollapsed === 'true',
            mobileSidebarOpen: false,
            previousFocusedElement: null,
            previousBodyOverflow: '',

            toggleDesktopSidebar() {
                this.desktopCollapsed = !this.desktopCollapsed;
                document.documentElement.dataset.sidebarCollapsed = String(this.desktopCollapsed);
                try { localStorage.setItem('admin_sidebar_collapsed', this.desktopCollapsed ? '1' : '0'); } catch {}
            },

            openSidebar() {
                this.previousFocusedElement = document.activeElement;
                this.previousBodyOverflow = document.body.style.overflow || '';
                document.body.style.overflow = 'hidden';
                this.mobileSidebarOpen = true;


                const sidebar = document.getElementById('admin-sidebar');
                const backdrop = document.getElementById('admin-sidebar-backdrop');
                const toggle = document.getElementById('admin-menu-toggle');
                if (sidebar) {
                    sidebar.classList.add('open');
                }
                if (backdrop) {
                    backdrop.style.display = 'block';
                }
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }

                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => {
                        if (!sidebar) return;
                        const focusable = this.getFocusableElements(sidebar);
                        if (focusable.length > 0) {
                            focusable[0].focus();
                        }
                    });
                }
            },

            closeSidebar() {
                this.mobileSidebarOpen = false;

                document.body.style.overflow = this.previousBodyOverflow || '';

                const sidebar = document.getElementById('admin-sidebar');
                const backdrop = document.getElementById('admin-sidebar-backdrop');
                const toggle = document.getElementById('admin-menu-toggle');
                if (sidebar) {
                    sidebar.classList.remove('open');
                }
                if (backdrop) {
                    backdrop.style.display = 'none';
                }
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }

                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => {
                        if (this.previousFocusedElement && typeof this.previousFocusedElement.focus === 'function') {
                            this.previousFocusedElement.focus();
                        } else if (toggle) {
                            toggle.focus();
                        }
                    });
                }
            },

            handleKeydown(e) {
                if (!this.mobileSidebarOpen) return;

                if (e.key === 'Escape' || e.keyCode === 27) {
                    e.preventDefault();
                    this.closeSidebar();
                    return;
                }

                if (e.key === 'Tab' || e.keyCode === 9) {
                    const sidebar = document.getElementById('admin-sidebar');
                    if (!sidebar) return;
                    const focusable = this.getFocusableElements(sidebar);
                    if (focusable.length === 0) return;

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === first || !sidebar.contains(document.activeElement)) {
                            e.preventDefault();
                            last.focus();
                        }
                    } else {
                        if (document.activeElement === last || !sidebar.contains(document.activeElement)) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                }
            },

            getFocusableElements(container) {
                return Array.from(
                    container.querySelectorAll(
                        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    )
                ).filter(el => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true' && (el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0));
            }
        };
    }

    window.adminMobileNav = adminMobileNav;

    if (window.Alpine) {
        Alpine.data('adminMobileNav', adminMobileNav);
    } else {
        document.addEventListener('alpine:init', () => {
            Alpine.data('adminMobileNav', adminMobileNav);
        });
    }
    </script>

    @livewireScripts
</body>
</html>
