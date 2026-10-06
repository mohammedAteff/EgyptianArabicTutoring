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
<body class="h-full font-sans antialiased bg-slate-100" 
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
                        <div class="font-bold text-white text-sm leading-tight tracking-tight">{{ auth('web')->user()?->roleLabel() }} Operations Console</div>
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

            <!-- Navigation Links -->
            <nav id="admin-sidebar-nav" class="flex-1 overflow-y-auto px-4 py-6 space-y-6 text-sm font-medium">
                
                <!-- Overview -->
                <div>
                    <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Overview</div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.dashboard') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            Dashboard
                        </a>
                        <a href="{{ route('admin.notifications.index') }}" 
                           class="flex min-h-11 items-center justify-between px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.notifications.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span>Notifications</span>
                            </div>
                            @php
                                $sidebarUnreadQuery = \App\Domains\Administration\Models\AdminNotification::query()->unread();
                                if (auth()->user()?->role !== 'super_admin') {
                                    $sidebarUnreadQuery->whereNotIn('type', ['backup_failure', 'system_warning']);
                                }
                                $sidebarUnreadCount = $sidebarUnreadQuery->count();
                            @endphp
                            @if($sidebarUnreadCount > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-slate-950">{{ $sidebarUnreadCount }}</span>
                            @endif
                        </a>
                @if(in_array(auth('web')->user()?->role, ['super_admin','admin','assistant'],true))
                <a href="{{ route('admin.operations.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('admin.operations.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Today & Operations</a>
                <a href="{{ route('admin.tasks.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('admin.tasks.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Staff Tasks</a>
                <a href="{{ route('admin.staff-bins.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('admin.staff-bins.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.6" d="M5 3h14v18H5zM8 7h8M8 11h8M8 15h5"/></svg>Staff Notes</a>
                @endif
                    </div>
                </div>
                <!-- Business -->
                <div>
                    <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Tutoring Business</div>
                    <div class="space-y-1">
                        @if(in_array(auth()->user()->role, ['super_admin', 'admin', 'assistant'], true))
                            <a href="{{ route('admin.students.index') }}"
                               class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.students.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Student Records
                            </a>
                            @if(auth('web')->user()?->isAdmin())
                            <a href="{{ route('admin.billing.cashier') }}"
                               class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.billing.cashier') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                Cashier Hub
                            </a>
                            <a href="{{ route('admin.billing.reconcile') }}"
                               class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.billing.reconcile*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Billing Reconcile
                            </a>
                            @foreach(['admin.receivables.index' => 'Receivables & Statements', 'admin.recurring.index' => 'Recurring Lessons', 'admin.waitlist.index' => 'Lesson Waitlist', 'admin.booking-policy.index' => 'Booking Policy', 'admin.reports.lifecycle' => 'Business Lifecycle', 'admin.data-quality.index' => 'Data Quality Center'] as $lifecycleRoute => $lifecycleLabel)
                            <a href="{{ route($lifecycleRoute) }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm {{ request()->routeIs($lifecycleRoute) ? 'bg-amber-600 font-semibold text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">{{ $lifecycleLabel }}</a>
                            @endforeach
                            @endif
                        @endif
                        <a href="{{ route('admin.bookings.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.bookings.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Bookings & Calendar
                        </a>
                        <a href="{{ route('admin.availability.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.availability.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Tutor Availability
                        </a>
                        <a href="{{ route('admin.contacts.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.contacts.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Students & Contacts
                        </a>
                        <a href="{{ route('admin.leads') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.leads') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            Leads (Resource Inquiries)
                        </a>
                    </div>
                </div>

                <!-- Content & Learning -->
                <div>
                    <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Content & Learning</div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.resources.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.resources.index', 'admin.resources.create', 'admin.resources.edit') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            Resources
                        </a>
                        <a href="{{ route('admin.games.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.games.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Learning Games
                        </a>
                        <a href="{{ route('admin.content.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.content.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Content & FAQs
                        </a>
                        <a href="{{ route('admin.pages.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.pages.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Pages CMS
                        </a>
                        <a href="{{ route('admin.blog.index') }}" class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.blog.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h9l7 7v7a2 2 0 01-2 2zM14 4v7h7m-12 4h6m-6 3h6"/></svg>
                            Blog
                        </a>
                        <a href="{{ route('admin.promotions.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.promotions.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            Promotions Portal
                        </a>
                        <a href="{{ route('admin.forms.index') }}" class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.forms.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                            Student Forms
                        </a>
                        <a href="{{ route('admin.media.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.media.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Media Library
                        </a>
                    </div>
                </div>

                <!-- Insights & Reports -->
                <div>
                    <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Insights & Telemetry</div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.analytics') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.analytics') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 17l5 5m-5-5l5-5"/></svg>
                            Analytics & Funnels
                        </a>
                        <a href="{{ route('admin.analytics.countries') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.analytics.countries*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Country Analytics
                        </a>
                        <a href="{{ route('admin.analytics.sections') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.analytics.sections*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Section Attention
                        </a>
                        @if(auth('web')->user()?->isSuperAdmin())
                        <a href="{{ route('admin.analytics.maintenance') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('admin.analytics.maintenance*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"><svg aria-hidden="true" class="h-5 w-5 shrink-0 opacity-80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m14 6 4-4a6 6 0 0 1-7 8l-7 7a2 2 0 0 0 3 3l7-7a6 6 0 0 1 8-7l-4 4-4-4Z"/></svg><span>Maintenance Mode</span></a>
                        @endif
                        <a href="{{ route('admin.reports.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.reports.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Operational Reports & Exports
                        </a>
                    </div>
                </div>

                <!-- Settings & System -->
                <div>
                    <div class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Configuration</div>
                    <div class="space-y-1">
                    @if(in_array(auth()->user()->role, ['admin', 'super_admin']))
                    <a href="{{ route('admin.meeting-links.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.meeting-links.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="5" width="14" height="14" rx="3"/><path d="m16 10 6-3v10l-6-3"/></svg>Meeting Links</a>
                <a href="{{ route('admin.payment-methods.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.payment-methods.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M6 15h3"/></svg>Payment Methods</a>
                <a href="{{ route('admin.telegram.index') }}" class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.telegram.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}"><svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M21.4 3.4 2.8 10.6c-1.3.5-1.3 1.2-.2 1.5l4.8 1.5 1.8 5.6c.2.6.1.8.8.8.5 0 .8-.2 1-.4l2.3-2.2 4.8 3.5c.9.5 1.5.2 1.7-.8L23 5c.3-1.3-.5-1.9-1.6-1.6ZM9.3 13.3l9.4-5.9c.4-.2.7-.1.4.2l-7.8 7.1-.3 3.4-1.7-4.8Z"/></svg><span>Telegram Bots</span></a>
                    @endif
                        <a href="{{ route('admin.settings.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.settings.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Settings & Policies
                        </a>
                        @if(auth('web')->user()?->role === 'super_admin')
                        <a href="{{ route('admin.security.show') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('admin.security.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"><svg class="w-5 h-5 shrink-0 opacity-80" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3Z"/><path d="m8 12 3 3 5-5"/></svg><span>Account Security</span></a>
                        <a href="{{ route('admin.health') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.health') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            System Health
                        </a>
                        <a href="{{ route('admin.backups.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.backups.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Backups & Recovery
                        </a>
                        <a href="{{ route('admin.administrators.index') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.administrators.*') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Staff Administrators
                        </a>
                        <a href="{{ route('admin.audit-logs') }}" 
                           class="flex min-h-11 items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.audit-logs') ? 'bg-amber-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Security Audit Log
                        </a>
                        @endif
                    </div>
                </div>
            </nav>

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

        <script>
            (function() {
                var nav = document.getElementById('admin-sidebar-nav');
                if (!nav) return;

                var savedScroll = sessionStorage.getItem('admin_sidebar_scroll');
                if (savedScroll !== null) {
                    nav.scrollTop = parseInt(savedScroll, 10);
                } else {
                    var active = nav.querySelector('.bg-amber-600');
                    if (active) {
                        active.scrollIntoView({ block: 'nearest' });
                    }
                }

                var scrollTimeout;
                nav.addEventListener('scroll', function() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function() {
                        sessionStorage.setItem('admin_sidebar_scroll', nav.scrollTop);
                    }, 50);
                }, { passive: true });

                nav.addEventListener('click', function(e) {
                    var link = e.target.closest('a');
                    if (link) {
                        sessionStorage.setItem('admin_sidebar_scroll', nav.scrollTop);
                    }
                });

                window.addEventListener('beforeunload', function() {
                    if (nav) {
                        sessionStorage.setItem('admin_sidebar_scroll', nav.scrollTop);
                    }
                });
            })();
        </script>

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

                    <form action="{{ route(auth('web')->user()?->isAssistant() ? 'admin.students.index' : 'admin.search') }}" method="GET" class="hidden min-w-0 w-full sm:block">
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
                    <a href="{{ route('admin.notifications.index') }}" class="relative p-2 text-slate-500 hover:text-amber-600 transition-colors" title="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if(($sidebarUnreadCount ?? 0) > 0)
                            <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 rounded-full bg-amber-600 ring-2 ring-white"></span>
                        @endif
                    </a>

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
                    <a href="{{ route('admin.health') }}" class="text-xs font-bold text-amber-900 underline hover:text-amber-950 shrink-0">View System Health &rarr;</a>
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
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>
    @include('admin.partials.media-picker')

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
