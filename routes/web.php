<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\AnnouncementUnsubscribeController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountPositionController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\BusinessCategoryController;
use App\Http\Controllers\Api\ChamberPositionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\FeeTypeController;
use App\Http\Controllers\Api\IntegrationSettingsController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MemberExportController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentSettingsController;
use App\Http\Controllers\Api\PositionAppointmentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegistrationSwitchController;
use App\Http\Controllers\Api\TwoFactorSettingsController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MemberBillingController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrationVerificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/register');

/*
|--------------------------------------------------------------------------
| Public membership registration (invitation only)
|--------------------------------------------------------------------------
| The registration form is available only through an invitation link. The
| fixed path /register/success is declared before the {token} route on purpose.
*/
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/register', [RegistrationController::class, 'closed'])->name('registration.create');
Route::get('/register/success', [RegistrationController::class, 'success'])->name('registration.success');
Route::get('/register/verify-email/{member}', [RegistrationVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('registration.verify-email');

Route::get('/announcements/unsubscribe/{member}', AnnouncementUnsubscribeController::class)
    ->middleware('signed')
    ->name('announcements.unsubscribe');
Route::get('/register/{token}', [RegistrationController::class, 'create'])->name('registration.invited');
Route::post('/register/{token}', [RegistrationController::class, 'store'])
    ->middleware('throttle:registration')
    ->name('registration.store');

/*
|--------------------------------------------------------------------------
| Account authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware(['throttle:login', 'throttle:login-ip']);

    Route::get('/login/two-factor', [TwoFactorController::class, 'show'])->name('login.two-factor');
    Route::post('/login/two-factor/method', [TwoFactorController::class, 'choose'])
        ->middleware('throttle:two-factor')
        ->name('login.two-factor.method');
    Route::post('/login/two-factor', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:two-factor')
        ->name('login.two-factor.verify');
    Route::post('/login/two-factor/cancel', [TwoFactorController::class, 'cancel'])->name('login.two-factor.cancel');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/set-password/{token}', [SetPasswordController::class, 'show'])->name('password.setup');
    Route::post('/set-password', [SetPasswordController::class, 'store'])
        ->middleware('throttle:two-factor')
        ->name('password.setup.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Management area (authenticated). Every route is protected by a gate.
|--------------------------------------------------------------------------
*/
/*
| Self-service routes for profile, two-factor sign-in and votes. Members use them under /member/api and
| committee accounts under /admin/api, so the same controllers serve both areas.
*/
$selfServiceApi = function (): void {
    Route::prefix('profile/two-factor')->name('profile.two-factor.')->group(function (): void {
        Route::get('/', [TwoFactorSettingsController::class, 'show'])->name('show');
        Route::post('/authenticator/setup', [TwoFactorSettingsController::class, 'startAuthenticator'])->name('authenticator.setup');
        Route::post('/authenticator/confirm', [TwoFactorSettingsController::class, 'confirmAuthenticator'])->name('authenticator.confirm');
        Route::post('/email/send', [TwoFactorSettingsController::class, 'sendEmailCode'])->name('email.send');
        Route::post('/email/confirm', [TwoFactorSettingsController::class, 'confirmEmail'])->name('email.confirm');
        Route::post('/email/disable-code', [TwoFactorSettingsController::class, 'sendDisableEmailCode'])->name('email.disable-code');
        Route::delete('/{method}', [TwoFactorSettingsController::class, 'disable'])->name('disable');
    });

    Route::prefix('profile')->name('profile.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password');
        Route::post('/avatar', [ProfileController::class, 'storeAvatar'])->name('avatar.store');
        Route::delete('/avatar', [ProfileController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::put('/business', [ProfileController::class, 'updateBusiness'])->name('business');
        Route::post('/business/document', [ProfileController::class, 'storeBusinessDocument'])->name('business.document.store');
        Route::get('/business/document', [ProfileController::class, 'showBusinessDocument'])->name('business.document.show');
        Route::post('/business/submit', [ProfileController::class, 'submitBusiness'])->name('business.submit');
    });

    Route::prefix('votes')->name('votes.')->group(function (): void {
        Route::get('/', [VoteController::class, 'index'])->name('index');
        Route::post('/', [VoteController::class, 'store'])->name('store');
        Route::get('/voters', [VoteController::class, 'voters'])->name('voters');
        Route::get('/positions', [VoteController::class, 'positions'])->name('positions');
        Route::get('/creators', [VoteController::class, 'creators'])->middleware('can:manage-accounts')->name('creators');
        Route::put('/creators/{user}', [VoteController::class, 'setCreator'])->middleware('can:manage-accounts')->name('creators.update');
        Route::put('/{vote}', [VoteController::class, 'update'])->name('update');
        Route::post('/{vote}/close', [VoteController::class, 'close'])->name('close');
        Route::post('/{vote}/cancel', [VoteController::class, 'cancel'])->name('cancel');
        Route::post('/{vote}/ballot', [VoteController::class, 'cast'])->name('cast');
    });

};

/*
|--------------------------------------------------------------------------
| Member area: /member. Only member accounts, who see their profile, votes and own activity.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'members-only'])->prefix('member')->name('member.')->group(function () use ($selfServiceApi): void {
    Route::get('/', [PageController::class, 'memberDashboard'])->name('home');
    Route::get('/votes', [PageController::class, 'votes'])->name('votes.index');
    Route::get('/profile', [PageController::class, 'profile'])->name('profile.index');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/invoices', [MemberBillingController::class, 'index'])->name('invoices.index');

    Route::prefix('api')->name('api.')->group(function () use ($selfServiceApi): void {
        // The profile page needs the business category list.
        Route::get('/categories', [BusinessCategoryController::class, 'index'])->name('categories.index');

        $selfServiceApi();
    });
});

Route::middleware(['auth', 'not-member'])->prefix('admin')->name('admin.')->group(function () use ($selfServiceApi): void {
    Route::get('/', [PageController::class, 'dashboard'])->middleware('committee')->name('dashboard');

    Route::get('/members', [PageController::class, 'members'])->middleware('can:view-members')->name('members.index');
    Route::get('/members/{member}', [PageController::class, 'memberShow'])->middleware('can:view-members')->name('members.show');
    Route::get('/positions', [PageController::class, 'positions'])->middleware('committee')->name('positions.index');
    Route::get('/categories', [PageController::class, 'categories'])->middleware('committee')->name('categories.index');
    Route::get('/accounts', [PageController::class, 'accounts'])->middleware('can:manage-accounts')->name('accounts.index');
    Route::get('/settings', [PageController::class, 'settings'])->middleware('can:manage-settings')->name('settings.index');
    Route::get('/invitations', [PageController::class, 'invitations'])->middleware('can:manage-invitations')->name('invitations.index');
    Route::get('/profile', [PageController::class, 'profile'])->name('profile.index');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/votes', [PageController::class, 'votes'])->name('votes.index');
    Route::get('/email-templates', [PageController::class, 'emailTemplates'])->middleware('can:manage-settings')->name('email-templates.index');
    Route::get('/fees', [PageController::class, 'fees'])->middleware('can:manage-settings')->name('fees.index');
    Route::get('/invoices', [PageController::class, 'invoices'])->middleware('can:manage-members')->name('invoices.index');
    Route::get('/payment-settings', [PageController::class, 'paymentSettings'])->middleware('can:manage-settings')->name('payment-settings.index');
    Route::get('/announcements', [PageController::class, 'announcements'])->middleware('can:manage-settings')->name('announcements.index');

    Route::prefix('api')->name('api.')->group(function () use ($selfServiceApi): void {
        Route::get('/dashboard', [DashboardController::class, 'show'])->middleware('committee')->name('dashboard.show');

        Route::get('/members', [MemberController::class, 'index'])->middleware('can:view-members')->name('members.index');
        Route::get('/members/options', [MemberController::class, 'options'])->middleware('can:view-members')->name('members.options');
        Route::get('/members/export', MemberExportController::class)
            ->middleware('can:export-members')
            ->name('members.export');
        Route::get('/members/{member}', [MemberController::class, 'show'])->middleware('can:view-members')->name('members.show');
        Route::post('/members/{member}/account/setup-link', [MemberController::class, 'setupLink'])
            ->middleware('can:manage-members')
            ->name('members.setup-link');
        Route::put('/members/{member}', [MemberController::class, 'update'])
            ->middleware('can:manage-members')
            ->name('members.update');
        Route::post('/members/{member}/status', [MemberController::class, 'review'])
            ->middleware('can:manage-members')
            ->name('members.review');
        Route::get('/fee-types', [FeeTypeController::class, 'index'])->middleware('can:manage-members')->name('fee-types.index');
        Route::post('/fee-types', [FeeTypeController::class, 'store'])->middleware('can:manage-settings')->name('fee-types.store');
        Route::put('/fee-types/{feeType}', [FeeTypeController::class, 'update'])->middleware('can:manage-settings')->name('fee-types.update');

        Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('can:manage-members')->name('invoices.index');
        Route::get('/invoices/summary', [InvoiceController::class, 'summary'])->middleware('can:manage-members')->name('invoices.summary');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->middleware('can:manage-members')->name('invoices.generate');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->middleware('can:manage-members')->name('invoices.payments.store');
        Route::post('/invoices/{invoice}/waive', [InvoiceController::class, 'waive'])->middleware('can:manage-accounts')->name('invoices.waive');
        Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])->middleware('can:manage-accounts')->name('payments.void');

        Route::get('/payment-settings', [PaymentSettingsController::class, 'show'])->middleware('can:manage-settings')->name('payment-settings.show');
        Route::put('/payment-settings', [PaymentSettingsController::class, 'update'])->middleware('can:manage-settings')->name('payment-settings.update');

        Route::post('/members/{member}/upgrade', [MemberController::class, 'upgrade'])
            ->middleware('can:manage-accounts')
            ->name('members.upgrade');
        Route::post('/members/{member}/verification/resend', [MemberController::class, 'resendVerification'])
            ->middleware('can:manage-members')
            ->name('members.verification.resend');

        Route::get('/positions', [ChamberPositionController::class, 'index'])->middleware('committee')->name('positions.index');
        Route::post('/positions', [ChamberPositionController::class, 'store'])
            ->middleware('can:manage-positions')
            ->name('positions.store');
        Route::put('/positions/{chamberPosition}', [ChamberPositionController::class, 'update'])
            ->middleware('can:manage-positions')
            ->name('positions.update');

        Route::get('/appointments', [PositionAppointmentController::class, 'index'])->middleware('can:view-members')->name('appointments.index');
        Route::post('/appointments', [PositionAppointmentController::class, 'store'])
            ->middleware('can:manage-appointments')
            ->name('appointments.store');
        Route::put('/appointments/{positionAppointment}', [PositionAppointmentController::class, 'update'])
            ->middleware('can:manage-appointments')
            ->name('appointments.update');

        Route::get('/categories', [BusinessCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [BusinessCategoryController::class, 'store'])
            ->middleware('can:manage-categories')
            ->name('categories.store');
        Route::put('/categories/{businessCategory}', [BusinessCategoryController::class, 'update'])
            ->middleware('can:manage-categories')
            ->name('categories.update');

        Route::get('/accounts', [AccountController::class, 'index'])->middleware('can:manage-accounts')->name('accounts.index');
        Route::post('/accounts', [AccountController::class, 'store'])
            ->middleware('can:manage-accounts')
            ->name('accounts.store');
        Route::get('/accounts/{user}/business-document', [AccountController::class, 'businessDocument'])
            ->middleware('can:manage-accounts')
            ->name('accounts.business-document');
        Route::post('/accounts/{user}/verify-business', [AccountController::class, 'verifyBusiness'])
            ->middleware('can:manage-accounts')
            ->name('accounts.verify-business');
        Route::put('/accounts/{user}', [AccountController::class, 'update'])
            ->middleware('can:manage-accounts')
            ->name('accounts.update');

        Route::get('/account-positions', [AccountPositionController::class, 'index'])
            ->middleware('can:manage-accounts')
            ->name('account-positions.index');
        Route::post('/account-positions', [AccountPositionController::class, 'store'])
            ->middleware('can:manage-account-positions')
            ->name('account-positions.store');
        Route::put('/account-positions/{accountPosition}', [AccountPositionController::class, 'update'])
            ->middleware('can:manage-account-positions')
            ->name('account-positions.update');

        Route::get('/settings/integrations', [IntegrationSettingsController::class, 'show'])->middleware('can:manage-settings')->name('settings.show');
        Route::put('/settings/integrations/{card}', [IntegrationSettingsController::class, 'update'])->middleware('can:manage-settings')->name('settings.update');
        Route::post('/settings/integrations/test-resend', [IntegrationSettingsController::class, 'testResend'])->middleware('can:manage-settings')->name('settings.test-resend');

        Route::get('/email-templates', [EmailTemplateController::class, 'index'])->middleware('can:manage-settings')->name('email-templates.index');
        Route::get('/email-templates/{type}/{locale}', [EmailTemplateController::class, 'show'])->middleware('can:manage-settings')->name('email-templates.show');
        Route::put('/email-templates/{type}/{locale}', [EmailTemplateController::class, 'update'])->middleware('can:manage-settings')->name('email-templates.update');
        Route::delete('/email-templates/{type}/{locale}', [EmailTemplateController::class, 'destroy'])->middleware('can:manage-settings')->name('email-templates.destroy');
        Route::post('/email-templates/{type}/{locale}/preview', [EmailTemplateController::class, 'preview'])->middleware('can:manage-settings')->name('email-templates.preview');

        Route::get('/announcements/recipients', [AnnouncementController::class, 'recipients'])->middleware('can:manage-settings')->name('announcements.recipients');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('can:manage-settings')->name('announcements.store');

        Route::get('/registration', [RegistrationSwitchController::class, 'show'])->middleware('can:manage-registration')->name('registration.show');
        Route::put('/registration', [RegistrationSwitchController::class, 'update'])->middleware('can:manage-registration')->name('registration.update');

        Route::get('/branding', [BrandingController::class, 'show'])->middleware('can:manage-branding')->name('branding.show');
        Route::post('/branding/logo', [BrandingController::class, 'store'])->middleware('can:manage-branding')->name('branding.store');
        Route::delete('/branding/logo', [BrandingController::class, 'destroy'])->middleware('can:manage-branding')->name('branding.destroy');

        $selfServiceApi();

        Route::get('/invitations', [InvitationController::class, 'index'])
            ->middleware('can:manage-invitations')
            ->name('invitations.index');
        Route::post('/invitations', [InvitationController::class, 'store'])
            ->middleware('can:manage-invitations')
            ->name('invitations.store');
        Route::post('/invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])
            ->middleware('can:manage-invitations')
            ->name('invitations.revoke');
    });
});
