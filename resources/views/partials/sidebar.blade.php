<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand">
          <!--begin::Brand Link-->
          <a href="#" class="brand-link">
            <!--begin::Brand Image-->
            <img
              src="{{ asset('adminlte/dist/assets/img/AdminLTELogo.png') }}"
              alt="AdminLTE Logo"
              class="brand-image opacity-75 shadow"
            />
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light">Vote MSC</span>
            <!--end::Brand Text-->
          </a>
          <!--end::Brand Link-->
        </div>
        <!--end::Sidebar Brand-->
        <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <!--begin::Sidebar Menu-->
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              role="navigation"
              aria-label="Main navigation"
              data-accordion="false"
              id="navigation"
            >
              @if(Auth::user()->role === 'admin')
                  <li class="nav-item">
                      <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                          href="{{ route('admin.dashboard') }}">
                          <i class="bi bi-speedometer2 me-1"></i>Dashboard
                      </a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link {{ request()->routeIs('admin.usermanage') ? 'active' : '' }}" 
                          href="{{ route('admin.usermanage') }}">
                          <i class="bi bi-people me-1"></i>Kelola User
                      </a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link {{ request()->routeIs('admin.ekstramanage') ? 'active' : '' }}" 
                          href="{{ route('admin.ekstramanage') }}">
                          <i class="bi bi-trophy me-1"></i>Kelola Ekstra
                      </a>
                  </li>
              @endif
            </ul>
            <!--end::Sidebar Menu-->
          </nav>
        </div>
        <!--end::Sidebar Wrapper-->
      </aside>