<header class=" bg-[#08131c] border-b border-[#183042] flex items-center justify-between gap-4 px-6  py-3.5 flex-shrink-0 z-30 relative">
  <div class="flex items-center gap-3 text-[13px] min-w-0">
    <button type="button" onclick="toggleMobileMenu()" class="lg:hidden p-2 rounded-lg bg-[#102230] border border-[#1e3a50] text-slate-300 hover:text-orange-400 hover:border-orange-500/50 transition-colors inline-flex items-center justify-center cursor-pointer flex-shrink-0" aria-label="Toggle Navigation Menu">
      <i data-lucide="menu" class="w-5 h-5"></i>
    </button>
    <?php if (!empty($breadcrumb) && is_array($breadcrumb)): ?>
      <nav aria-label="Breadcrumb" class="flex items-center gap-2 truncate">
        <?php foreach ($breadcrumb as $index => $crumb): ?>
          <?php if ($index > 0): ?>
            <span class="crumb-sep text-slate-600 rtl:rotate-180 flex-shrink-0 select-none">/</span>
          <?php endif; ?>
          <span class="crumb <?= $index === count($breadcrumb) - 1 ? 'crumb-current font-semibold text-white truncate max-w-[130px] xs:max-w-[180px] sm:max-w-none' : 'text-slate-400 hidden sm:inline hover:text-slate-200 transition-colors' ?>">
            <?= htmlspecialchars($crumb) ?>
          </span>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <nav aria-label="Breadcrumb" class="flex items-center gap-2 truncate">
        <a href="<?= app_url('index.php') ?>" class="crumb hidden md:inline text-slate-400 hover:text-slate-200 transition-colors">I-GAS</a>
        <span class="crumb-sep hidden md:inline text-slate-600 rtl:rotate-180 flex-shrink-0 select-none">/</span>
        <span class="crumb hidden sm:inline text-slate-400" data-en="Operations" data-ar="العمليات">Operations</span>
        <span class="crumb-sep hidden sm:inline text-slate-600 rtl:rotate-180 flex-shrink-0 select-none">/</span>
        <span class="crumb-current font-semibold text-white truncate max-w-[130px] xs:max-w-[180px] sm:max-w-none" data-en="Command Center" data-ar="مركز القيادة">Command Center</span>
      </nav>
    <?php endif; ?>
  </div>

  <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
    <div class="flex items-center gap-2 sm:gap-2.5">
      <!-- Interactive Language Switcher -->
      <button type="button" onclick="toggleAppLanguage()" class="inline-flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-[#102230] border border-[#1e3a50] text-slate-300 hover:text-orange-400 hover:border-orange-500/50 text-xs font-semibold transition-colors cursor-pointer" title="Switch Language / تبديل اللغة">
        <i data-lucide="globe" class="w-4 h-4 text-cyan-400 shrink-0"></i>
        <span id="lang-switch-text" class="tracking-wide">العربية</span>
      </button>

      <!-- Interactive Control Room Theme Switcher (Dark / Light) -->
      <button type="button" onclick="toggleAppTheme()" class="inline-flex items-center justify-center p-2 rounded-lg bg-[#102230] border border-[#1e3a50] text-slate-300 hover:text-orange-400 hover:border-orange-500/50 text-xs font-semibold transition-colors cursor-pointer" title="Toggle Control Room Theme">
        <i data-lucide="moon" id="header-theme-icon" class="w-4 h-4 text-cyan-400"></i>
      </button>

      <!-- Notification Bell (Directly linked to Reminders & Alerts) -->
      <a href="<?= app_url('modules/crm_sales/followup_reminders.php') ?>" class="relative transition-colors p-2 rounded-lg bg-[#102230] border border-[#1e3a50] text-slate-300 hover:text-orange-400 hover:border-orange-500/50 inline-flex items-center justify-center" title="Follow-up Reminders & Alerts">
        <i data-lucide="bell" class="w-4 h-4"></i>
        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full border border-[#08131c] bg-[#f97316]"></span>
      </a>

      <!-- System Settings Quick Access -->
      <?php if (hasAccess(['Admin']) || (isset($pdo) && has_permission($pdo, 'settings.manage'))): ?>
      <a href="<?= app_url('modules/insights/system_settings.php') ?>" class="transition-colors hidden sm:inline-flex items-center justify-center p-2 rounded-lg bg-[#102230] border border-[#1e3a50] text-slate-300 hover:text-orange-400 hover:border-orange-500/50" title="System Settings">
        <i data-lucide="settings" class="w-4 h-4"></i>
      </a>
      <?php endif; ?>
    </div>

    <span class="w-px h-5 hidden sm:block bg-[#183042]"></span>

    <!-- User Profile Card in Header -->
    <a href="<?= app_url('modules/main/profile.php') ?>" class="inline-flex items-center gap-3 p-1.5 rounded-lg hover:bg-[#102230] transition-colors group" title="My Profile">
      <div class="w-8 h-8 rounded-full bg-[#102230] border border-cyan-500/40 text-cyan-400 flex items-center justify-center text-xs font-bold font-mono flex-shrink-0">
        <?= htmlspecialchars(strtoupper(substr($_SESSION['user_name'] ?? 'User', 0, 2))) ?>
      </div>
      <div class="hidden md:flex flex-col text-left rtl:text-right">
        <span class="text-[13px] font-semibold text-white group-hover:text-cyan-400 leading-tight transition-colors"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
        <span class="text-[10px] text-slate-400 capitalize mono font-medium"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></span>
      </div>
    </a>
  </div>
</header>