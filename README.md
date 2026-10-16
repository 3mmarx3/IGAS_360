I-GAS Enterprise Resource Planning (ERP) & CRM System Documentation

Overview

I-GAS Enterprise is a comprehensive, bilingual (Arabic/English) enterprise resource planning and customer relationship management system engineered specifically for industrial gas manufacturing, supply, and distribution operations. Built on native PHP 8.0+, MySQL 8.0/MariaDB, and styled with Tailwind CSS, the platform delivers real-time operational visibility, granular RBAC security, automated inventory management, and strict data isolation.

System Modules & Core Architecture

1. Main Module (الإدارة العليا)

command_center.php (Executive Dashboard):

Real-time executive dashboard providing high-level operational metrics.

Live monitoring of total revenue (MTD), daily production output, active fleet vehicles, and overdue receivables.

Instant action triggers (Quick Actions) for creating sales orders, logging production shifts, and dispatching vehicles.

2. CRM & Sales Module (قسم المبيعات والعملاء)

clients_directory.php (Clients Directory):

Comprehensive ledger for corporate and individual client profiles, commercial registrations, and tax identification numbers.

Integrated activity logs tracking historical client interactions and touchpoints.

quotations.php (Quotations):

Dynamic builder for commercial quotations detailing gas specifications, tiered pricing, and quantities.

One-click PDF generation and export for client transmission.

purchase_orders.php (Purchase & Sales Orders - POs):

Seamless conversion mechanism from approved quotations to active purchase and sales orders.

Lifecycle tracking across operational states: Draft, Processing, In Transit, and Delivered.

collections_invoices.php (Collections & Invoices):

Automated financial ledger issuing tax invoices immediately upon order fulfillment.

Client Statement of Account generation and tracking of remittance payments.

Automated aging reports and alerts for overdue receivables.

client_contracts.php (Client Contracts & SLAs):

Management of long-term take-or-pay supply agreements and fixed monthly commitments.

Proactive expiration warning triggers prior to contract milestone dates.

3. Production & Inventory Module (قسم الإنتاج والمخزون)

suppliers_directory.php (Suppliers Directory):

Centralized directory for raw material suppliers (e.g., SABIC, Air Products).

raw_materials.php (Raw Materials):

Tracking receipts of liquid and solid raw materials (e.g., Calcium Carbide, cryogenic bulk gases).

Built-in multi-unit conversion engine supporting metric tons, liters, gallons, and kilograms.

cylinders_inventory.php (Cylinders Inventory):

High-precision asset tracking for high-pressure cylinders across varying volumetric tiers (10L, 40L, 50L).

Segregation and real-time ledger balance for empty (returned) versus full (ready-for-sale) cylinder inventories.

daily_log_shifts.php (Daily Shift Logs):

Shift-based production logging (Shift A, Shift B, Shift C).

Pressure parameter ingestion (e.g., 2000 PSI or 3000 PSI) calculating exact gas mass evacuated from bulk storage tanks.

Automated inventory reduction (Inventory Deduction) from primary storage tanks upon shift submission.

gases_mixtures.php (Gases & Mixtures):

Management repository supporting over 64 distinct industrial and medical gas profiles.

Advanced blending calculation engine computing individual raw gas consumption ratios for custom mixtures (e.g., methane-air calibration mixes).

yield_loss_reports.php (Yield & Loss Reports):

Comparative analytics measuring theoretical production (tank depletion baseline) against actual packed yield.

Automated loss/variance calculation accounting for residual heel gases in returned cylinders and financial impact on net profit margins.

4. Logistics & Fleet Module (قسم اللوجستيات والأسطول)

vehicles_fleet.php (Vehicles Fleet):

Fleet registry recording maximum payload capacities, volumetric limits, and real-time operational status.

drivers_directory.php (Drivers Directory):

Driver personnel files, safety performance evaluations, and asset-custody vehicle assignments.

dispatch_delivery.php (Dispatch & Delivery):

Trip ticket generation and official Delivery Note issuance.

Real-time dispatch monitoring for outbound delivery runs.

maintenance_fuel.php (Maintenance & Fuel):

Unplanned breakdown logging, maintenance work orders, and repair cost accounting.

Fuel consumption telemetry linked directly against distance traveled metrics.

odometers_tires.php (Odometers & Tires):

Periodic odometer mileage logging.

Lifecycle monitoring and wear-and-tear projections for vehicle tires based on cumulative kilometers.

licenses_alerts.php (Licenses & Compliance Alerts):

Early-warning automated alert console for regulatory expiration dates (vehicle registrations, driver licenses, periodic safety inspections, and commercial insurance).

5. Insights & Analytics Module (قسم الرؤى والتحليلات)

analytics.php (Analytics Cockpit):

Advanced monthly and annual business intelligence reporting featuring graphical visualizers for profitability trends, output volume, and fleet overhead.

compliance.php (Compliance & Safety):

Cylinder recertification tracking, hydrostatic pressure test schedules, and quality assurance audit logs ensuring strict industrial compliance.

system_settings.php (System Settings & RBAC):

Granular Role-Based Access Control (RBAC) management defining permissions across departments.

Global configuration parameters for vehicle categories, gas dictionaries, and mathematical conversion formulas.

Technical Specifications & Deployment Environment

Backend: Native PHP 8.0 / 8.5 (Strict typing and robust error handling).

Database: MySQL 8.0 / MariaDB 10.5+ (InnoDB storage engine with utf8mb4 collation supporting Arabic Cairo typography).

Frontend: Tailwind CSS, responsive layouts, dark industrial UI theme (#071018 / #0c1924 / #f97316).

Security Standards: Deny-by-default access control, parameterized PDO queries, strict anti-CSRF token validation, and multi-tier role verification.
