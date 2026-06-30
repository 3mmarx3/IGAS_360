<?php
$current_file = basename($_SERVER['PHP_SELF']);
$p = $active_page ?? '';
?>
<aside class="w-64 flex flex-col flex-shrink-0" style="background: var(--sidebar); color: var(--sidebar-text);">
    <div class="flex-1 overflow-y-auto">
        <div class="h-16 flex items-center px-6 border-b flex-shrink-0" style="border-color: var(--sidebar-line);">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 flex items-center justify-center text-white font-semibold text-[13px] mono border" style="border-color: #3A3A3A; background: #232323;">IG</div>
                <div class="leading-none">
                    <span class="text-white font-semibold text-[15px] tracking-tight block">I-GAS</span>
                    <span class="text-[10px] font-medium uppercase tracking-[0.14em] block mt-0.5 mono" style="color: var(--mute-soft);">Enterprise Systems</span>
                </div>
            </div>
        </div>

        <button class="w-full flex items-center justify-between px-6 py-3 border-b transition-colors" style="border-color: var(--sidebar-line);" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-5 h-5 flex items-center justify-center text-[9px] font-semibold text-white flex-shrink-0 rounded-sm" style="background:#2E2E2E;">JI</span>
                <span class="text-[13px] font-medium text-white truncate">Jeddah Industrial — HQ</span>
            </div>
            <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5 flex-shrink-0" style="color: var(--mute-soft);"></i>
        </button>

        <div class="pt-6">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] mb-2 px-6" style="color: #555350;">CRM &amp; Sales</p>
            <nav class="px-3">
                <a href="/IGAS_360/modules/crm_sales/clients_directory.php" class="nav-row <?= ($p === 'clients_directory' || $current_file === 'clients_directory.php') ? 'active' : '' ?> flex items-center justify-between px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <span class="flex items-center gap-3"><i data-lucide="users" class="w-[16px] h-[16px]"></i>Clients Directory</span>
                </a>
                <a href="/IGAS_360/modules/crm_sales/quotations.php" class="nav-row <?= ($p === 'quotations' || $current_file === 'quotations.php') ? 'active' : '' ?> flex items-center justify-between px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <span class="flex items-center gap-3"><i data-lucide="file-text" class="w-[16px] h-[16px]"></i>Quotations</span>
                </a>
                <a href="/IGAS_360/modules/crm_sales/purchase_orders.php" class="nav-row <?= ($p === 'purchase_orders' || $p === 'orders' || $current_file === 'purchase_orders.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="shopping-cart" class="w-[16px] h-[16px]"></i>Purchase Orders (PO)
                </a>
                <a href="/IGAS_360/modules/crm_sales/collections_invoices.php" class="nav-row <?= ($p === 'collections_invoices' || $current_file === 'collections_invoices.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="wallet" class="w-[16px] h-[16px]"></i>Collections &amp; Invoices
                </a>
                <a href="/IGAS_360/modules/crm_sales/client_contracts.php" class="nav-row <?= ($p === 'client_contracts' || $current_file === 'client_contracts.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
                    <i data-lucide="file-signature" class="w-[16px] h-[16px]"></i>Client Contracts
                </a>
            </nav>

            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] mb-2 px-6 mt-7" style="color: #555350;">Production</p>
            <nav class="px-3">
                <a href="/IGAS_360/modules/production/suppliers_directory.php" class="nav-row <?= ($p === 'suppliers_directory' || $current_file === 'suppliers_directory.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="users-round" class="w-[16px] h-[16px]"></i>Suppliers Directory
                </a>
                <a href="/IGAS_360/modules/production/raw_materials.php" class="nav-row <?= ($p === 'raw_materials' || $current_file === 'raw_materials.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="flask-conical" class="w-[16px] h-[16px]"></i>Raw Materials
                </a>
                <a href="/IGAS_360/modules/production/cylinders_inventory.php" class="nav-row <?= ($p === 'cylinders_inventory' || $current_file === 'cylinders_inventory.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="cylinder" class="w-[16px] h-[16px]"></i>Cylinders Inventory
                </a>
                <a href="/IGAS_360/modules/production/daily_log_shifts.php" class="nav-row <?= ($p === 'daily_log_shifts' || $current_file === 'daily_log_shifts.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="factory" class="w-[16px] h-[16px]"></i>Daily Log &amp; Shifts
                </a>
                <a href="/IGAS_360/modules/production/gases_mixtures.php" class="nav-row <?= ($p === 'gases_mixtures' || $current_file === 'gases_mixtures.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="test-tubes" class="w-[16px] h-[16px]"></i>Gases &amp; Mixtures
                </a>
                <a href="/IGAS_360/modules/production/yield_loss_reports.php" class="nav-row <?= ($p === 'yield_loss_reports' || $current_file === 'yield_loss_reports.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
                    <i data-lucide="scale" class="w-[16px] h-[16px]"></i>Yield &amp; Loss Reports
                </a>
            </nav>

            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] mb-2 px-6 mt-7" style="color: #555350;">Logistics &amp; Fleet</p>
            <nav class="px-3">
                <a href="/IGAS_360/modules/logistics_fleet/vehicles_fleet.php" class="nav-row <?= ($p === 'vehicles_fleet' || $current_file === 'vehicles_fleet.php') ? 'active' : '' ?> flex items-center justify-between px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <span class="flex items-center gap-3"><i data-lucide="truck" class="w-[16px] h-[16px]"></i>Vehicles Fleet</span>
                </a>
                <a href="/IGAS_360/modules/logistics_fleet/drivers_directory.php" class="nav-row <?= (in_array($p, ['drivers_directory', 'driver_profile']) || $current_file === 'drivers_directory.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="users-2" class="w-[16px] h-[16px]"></i>Drivers Directory
                </a>
                <a href="/IGAS_360/modules/logistics_fleet/dispatch_delivery.php" class="nav-row <?= ($p === 'dispatch_delivery' || $current_file === 'dispatch_delivery.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="map-pin" class="w-[16px] h-[16px]"></i>Dispatch &amp; Delivery
                </a>
                <a href="/IGAS_360/modules/logistics_fleet/maintenance_fuel.php" class="nav-row <?= ($p === 'maintenance_fuel' || $current_file === 'maintenance_fuel.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="wrench" class="w-[16px] h-[16px]"></i>Maintenance &amp; Fuel
                </a>
                <a href="/IGAS_360/modules/logistics_fleet/licenses_alerts.php" class="nav-row <?= ($p === 'licenses_alerts' || $current_file === 'licenses_alerts.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
                    <i data-lucide="file-warning" class="w-[16px] h-[16px]"></i>Licenses Alerts
                </a>
            </nav>

            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] mb-2 px-6 mt-7" style="color: #555350;">Insights &amp; Admin</p>
            <nav class="px-3">
                <a href="/IGAS_360/modules/insights/analytics.php" class="nav-row <?= ($p === 'analytics' || $current_file === 'analytics.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="bar-chart-3" class="w-[16px] h-[16px]"></i>Analytics
                </a>
                <a href="/IGAS_360/modules/insights/compliance.php" class="nav-row <?= ($p === 'compliance' || $current_file === 'compliance.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="shield-check" class="w-[16px] h-[16px]"></i>Compliance
                </a>
                <a href="/IGAS_360/modules/insights/partner_approvals.php" class="nav-row <?= ($p === 'partner_approvals' || $current_file === 'partner_approvals.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px] mb-0.5">
                    <i data-lucide="user-check" class="w-[16px] h-[16px]"></i>Partner Approvals
                </a>
                <a href="/IGAS_360/modules/insights/system_settings.php" class="nav-row <?= ($p === 'system_settings' || $current_file === 'system_settings.php') ? 'active' : '' ?> flex items-center gap-3 px-3 py-2.5 rounded-sm font-medium text-[13.5px]">
                    <i data-lucide="settings" class="w-[16px] h-[16px]"></i>System Settings
                </a>
            </nav>
        </div>
    </div>

    <div class="p-4 border-t flex-shrink-0" style="border-color: var(--sidebar-line);">
        <a href="/IGAS_360/modules/main/profile.php" class="flex items-center gap-3 px-2 py-2 rounded-sm transition-colors hover:bg-[rgba(255,255,255,0.03)]">
            <img src="https://ui-avatars.com/api/?name=Ibrahim+Al+Manea&background=2E2E2E&color=fff&rounded=true&bold=true" alt="User" class="w-9 h-9 rounded-full">
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-medium text-white truncate">Ibrahim Al Manea</p>
                <p class="text-[11px] truncate mono uppercase tracking-wide" style="color: var(--mute-soft);">Chairman</p>
            </div>
            <i data-lucide="chevron-right" class="w-[17px] h-[17px]" style="color: var(--mute-soft);"></i>
        </a>
    </div>
</aside>