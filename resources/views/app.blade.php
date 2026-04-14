<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PANGI — Dashboard</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    [x-cloak] { display: none; }
    .nav-active {
      background: rgba(20,184,166,.12);
      color: #2dd4bf;
      border-left-color: #14b8a6;
    }
    .nav-item { border-left: 2px solid transparent; }
    .nav-item:not(.nav-active):hover {
      background: rgba(255,255,255,.05);
      color: #fff;
    }

    /* Sidebar always fixed, main content has left padding on lg+ */
    #sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 256px; /* w-64 */
      z-index: 50;
      background: #0f172a;
      display: flex;
      flex-direction: column;
      transform: translateX(-100%);
      transition: transform 300ms ease;
    }
    #sidebar.open {
      transform: translateX(0);
    }
    @media (min-width: 1024px) {
      #sidebar {
        transform: translateX(0) !important;
      }
      #main-content {
        padding-left: 256px;
      }
    }
    #main-content {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      width: 100%;
    }
  </style>
</head>
<body class="bg-slate-100 font-sans" x-data="{ sidebarOpen: false }" @keydown.escape="sidebarOpen = false">

@include('sidebar')

<div id="main-content">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 bg-white border-b border-slate-200 px-4 md:px-8 py-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 hover:bg-slate-100 rounded-lg transition">
          <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>
        <div>
          <h1 class="text-base font-bold text-slate-800">Dashboard</h1>
          <p class="text-xs text-slate-400">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        
        <div class="hidden sm:flex items-center gap-2.5">
          <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->nama) }}&background=14b8a6&color=fff"
             class="w-8 h-8 rounded-full" alt="avatar"/>
          <div class="leading-tight">
            <p class="text-sm font-semibold text-slate-700">{{ auth()->user()->nama }}</p>
            <p class="text-xs text-slate-400">{{ auth()->user()->role_label }}</p>
          </div>
        </div>
      </div>
    </header>

    @yield('content')
  </div>

@stack('scripts')
</body>
</html>