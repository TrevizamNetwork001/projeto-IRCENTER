<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AutonomousSystemController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExternalIntegrationController;
use App\Http\Controllers\BillingContractController;
use App\Http\Controllers\BillingItemController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\FinanceDashboardController;
use App\Http\Controllers\FinanceClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\FiscalDashboardController;
use App\Http\Controllers\FiscalCustomerProfileController;
use App\Http\Controllers\IrrObjectController;
use App\Http\Controllers\IrrWorkflowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrefixController;
use App\Http\Controllers\Profile\PasswordController as ProfilePasswordController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RpkiValidationController;
use App\Http\Controllers\RoutingIncidentController;
use App\Http\Controllers\SystemDiagnosticController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware([
    'auth',
    'password.changed',
])->group(function (): void {
    Route::get(
        '/change-password',
        [PasswordChangeController::class, 'edit']
    )->name('password.change.edit');

    Route::put(
        '/change-password',
        [PasswordChangeController::class, 'update']
    )->name('password.change.update');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get(
        '/system-diagnostic',
        SystemDiagnosticController::class
    )->name('system-diagnostic.index');

    Route::post(
        '/external-integrations/{externalIntegration}/test',
        [ExternalIntegrationController::class, 'test']
    )->name('external-integrations.test');

    Route::resource(
        'external-integrations',
        ExternalIntegrationController::class
    )
        ->parameters([
            'external-integrations' => 'externalIntegration',
        ])
        ->except([
            'destroy',
        ]);


    Route::get(
        '/finance',
        FinanceDashboardController::class
    )->name('finance.dashboard');

    Route::middleware('fiscal.enabled')->group(function (): void {
        Route::get('/fiscal', FiscalDashboardController::class)->name('fiscal.dashboard');
        Route::get('/clients/{client}/fiscal', [FiscalCustomerProfileController::class, 'edit'])->whereNumber('client')->name('clients.fiscal.edit');
        Route::put('/clients/{client}/fiscal', [FiscalCustomerProfileController::class, 'update'])->whereNumber('client')->name('clients.fiscal.update');
    });

    Route::get('/finance/clients', [FinanceClientController::class, 'index'])
        ->name('finance.clients.index');
    Route::get('/finance/clients/{client}', [FinanceClientController::class, 'show'])
        ->whereNumber('client')->name('finance.clients.show');

    Route::get('/finance/items', [BillingItemController::class, 'index'])
        ->name('finance.items.index');
    Route::post('/finance/items', [BillingItemController::class, 'store'])
        ->name('finance.items.store');
    Route::put('/finance/items/{billingItem}', [BillingItemController::class, 'update'])
        ->whereNumber('billingItem')->name('finance.items.update');
    Route::patch('/finance/items/{billingItem}/toggle', [BillingItemController::class, 'toggle'])
        ->whereNumber('billingItem')->name('finance.items.toggle');

    Route::get('/finance/invoices/create', [InvoiceController::class, 'create'])
        ->name('finance.invoices.create');
    Route::post('/finance/invoices', [InvoiceController::class, 'store'])
        ->name('finance.invoices.store');



    Route::post(
        '/finance/invoices/{invoice}/charges',
        [ChargeController::class, 'store']
    )
        ->whereNumber('invoice')
        ->name('finance.invoices.charges.store');

    Route::post(
        '/finance/charges/{charge}/reconcile',
        [ChargeController::class, 'reconcile']
    )
        ->whereNumber('charge')
        ->name('finance.charges.reconcile');

    Route::get(
        '/finance/invoices',
        [InvoiceController::class, 'index']
    )->name('finance.invoices.index');

    Route::get(
        '/finance/invoices/{invoice}',
        [InvoiceController::class, 'show']
    )
        ->whereNumber('invoice')
        ->name('finance.invoices.show');

    Route::post(
        '/finance/contracts/{billingContract}/invoices',
        [InvoiceController::class, 'generate']
    )
        ->whereNumber('billingContract')
        ->name('finance.contracts.invoices.generate');

    Route::get(
        '/finance/contracts',
        [BillingContractController::class, 'index']
    )->name('finance.contracts.index');

    Route::get(
        '/finance/contracts/create',
        [BillingContractController::class, 'create']
    )->name('finance.contracts.create');

    Route::post(
        '/finance/contracts',
        [BillingContractController::class, 'store']
    )->name('finance.contracts.store');

    Route::get('/finance/contracts/{billingContract}/edit', [BillingContractController::class, 'edit'])
        ->whereNumber('billingContract')->name('finance.contracts.edit');
    Route::put('/finance/contracts/{billingContract}', [BillingContractController::class, 'update'])
        ->whereNumber('billingContract')->name('finance.contracts.update');

    Route::get(
        '/finance/contracts/{billingContract}',
        [BillingContractController::class, 'show']
    )
        ->whereNumber('billingContract')
        ->name('finance.contracts.show');

    Route::post(
        '/finance/contracts/{billingContract}/activate',
        [BillingContractController::class, 'activate']
    )
        ->whereNumber('billingContract')
        ->name('finance.contracts.activate');

    Route::post(
        '/finance/contracts/{billingContract}/suspend',
        [BillingContractController::class, 'suspend']
    )
        ->whereNumber('billingContract')
        ->name('finance.contracts.suspend');

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    )->name('reports.index');

    Route::get(
        '/reports/{report}/csv',
        [ReportController::class, 'export']
    )
        ->whereIn('report', [
            'clients',
            'autonomous-systems',
            'prefixes',
            'incidents',
        ])
        ->name('reports.export');

    Route::get(
        '/routing-incidents',
        [RoutingIncidentController::class, 'index']
    )->name('routing-incidents.index');

    Route::get(
        '/routing-incidents/create',
        [RoutingIncidentController::class, 'create']
    )->name('routing-incidents.create');

    Route::post(
        '/routing-incidents',
        [RoutingIncidentController::class, 'store']
    )->name('routing-incidents.store');

    Route::get(
        '/routing-incidents/{routingIncident}',
        [RoutingIncidentController::class, 'show']
    )->name('routing-incidents.show');

    Route::get(
        '/routing-incidents/{routingIncident}/edit',
        [RoutingIncidentController::class, 'edit']
    )->name('routing-incidents.edit');

    Route::put(
        '/routing-incidents/{routingIncident}',
        [RoutingIncidentController::class, 'update']
    )->name('routing-incidents.update');

    Route::post(
        '/routing-incidents/{routingIncident}/updates',
        [RoutingIncidentController::class, 'storeUpdate']
    )->name('routing-incidents.updates.store');

    Route::get(
        '/notifications',
        [NotificationController::class, 'index']
    )->name('notifications.index');

    Route::patch(
        '/notifications/read-all',
        [NotificationController::class, 'markAllRead']
    )->name('notifications.read-all');

    Route::patch(
        '/notifications/{notification}/read',
        [NotificationController::class, 'markRead']
    )->name('notifications.read');

    Route::get(
        '/profile',
        [ProfileController::class, 'edit']
    )->name('profile.edit');

    Route::put(
        '/profile/avatar',
        [ProfileController::class, 'updateAvatar']
    )->name('profile.avatar.update');

    Route::get(
        '/profile/password',
        [ProfilePasswordController::class, 'edit']
    )->name('profile.password.edit');

    Route::put(
        '/profile/password',
        [ProfilePasswordController::class, 'update']
    )->name('profile.password.update');

    Route::patch(
        '/clients/{client}/toggle-active',
        [ClientController::class, 'toggleActive']
    )->name('clients.toggle-active');

    Route::get(
        '/clients/{client}/contacts/create',
        [ClientContactController::class, 'create']
    )->name('clients.contacts.create');

    Route::post(
        '/clients/{client}/contacts',
        [ClientContactController::class, 'store']
    )->name('clients.contacts.store');

    Route::get(
        '/clients/{client}/contacts/{contact}/edit',
        [ClientContactController::class, 'edit']
    )->name('clients.contacts.edit');

    Route::put(
        '/clients/{client}/contacts/{contact}',
        [ClientContactController::class, 'update']
    )->name('clients.contacts.update');

    Route::patch(
        '/clients/{client}/contacts/{contact}/toggle-active',
        [ClientContactController::class, 'toggleActive']
    )->name('clients.contacts.toggle-active');

    Route::patch(
        '/clients/{client}/contacts/{contact}/toggle-primary',
        [ClientContactController::class, 'togglePrimary']
    )->name('clients.contacts.toggle-primary');

    Route::resource('clients', ClientController::class);

    Route::patch(
        '/autonomous-systems/{autonomous_system}/toggle-active',
        [AutonomousSystemController::class, 'toggleActive']
    )->name('autonomous-systems.toggle-active');

    Route::resource(
        'autonomous-systems',
        AutonomousSystemController::class
    );

    Route::get('/prefixes/ipv4', [PrefixController::class, 'ipv4'])
        ->name('prefixes.ipv4');

    Route::get('/prefixes/ipv6', [PrefixController::class, 'ipv6'])
        ->name('prefixes.ipv6');

    Route::patch(
        '/prefixes/{prefix}/toggle-active',
        [PrefixController::class, 'toggleActive']
    )->name('prefixes.toggle-active');

    Route::resource('prefixes', PrefixController::class);

    Route::patch(
        '/irr-objects/{irr_object}/toggle-active',
        [IrrObjectController::class, 'toggleActive']
    )->name('irr-objects.toggle-active');

    Route::resource('irr-objects', IrrObjectController::class);

    Route::get(
        '/irr-assistant',
        [IrrWorkflowController::class, 'index']
    )->name('irr-workflows.index');

    Route::get(
        '/irr-assistant/create',
        [IrrWorkflowController::class, 'create']
    )->name('irr-workflows.create');

    Route::post(
        '/irr-assistant',
        [IrrWorkflowController::class, 'store']
    )->name('irr-workflows.store');

    Route::get(
        '/irr-assistant/{irrWorkflow}',
        [IrrWorkflowController::class, 'show']
    )->name('irr-workflows.show');

    Route::patch(
        '/irr-assistant/{irrWorkflow}/prefixes/{workflowPrefix}',
        [IrrWorkflowController::class, 'updatePrefixPolicy']
    )->name('irr-workflows.prefixes.update');

    Route::post(
        '/irr-assistant/{irrWorkflow}/steps/{step}/sent',
        [IrrWorkflowController::class, 'markSent']
    )->name('irr-workflows.steps.sent');

    Route::post(
        '/irr-assistant/{irrWorkflow}/steps/{step}/confirm',
        [IrrWorkflowController::class, 'confirm']
    )->name('irr-workflows.steps.confirm');

    Route::get(
        '/rpki',
        [RpkiValidationController::class, 'index']
    )->name('rpki.index');

    Route::get(
        '/rpki/prefixes/{prefix}/history',
        [RpkiValidationController::class, 'history']
    )->name('rpki.history');

    Route::post(
        '/prefixes/{prefix}/rpki-validation',
        [RpkiValidationController::class, 'store']
    )->name('prefixes.rpki-validation.store');

    Route::patch(
        '/users/{user}/toggle-active',
        [UserController::class, 'toggleActive']
    )->name('users.toggle-active');

    Route::patch(
        '/users/{user}/reset-password',
        [UserController::class, 'resetPassword']
    )->name('users.reset-password');

    Route::resource('users', UserController::class)
        ->only([
            'index',
            'create',
            'store',
            'edit',
            'update',
        ]);

    Route::get(
        '/audit',
        [AuditLogController::class, 'index']
    )->name('audit.index');

    Route::get(
        '/audit/{auditLog}',
        [AuditLogController::class, 'show']
    )->name('audit.show');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
