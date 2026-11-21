<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AccountSetupLinkService;
use App\Services\BusinessDocument;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    /**
     * Every account except superadmin accounts, plus the signed-in account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = User::query()
            ->with('accountPosition')
            ->where(function ($query) use ($request): void {
                $query->whereIn('account_type', [AccountType::Admin->value, AccountType::Staff->value])
                    ->orWhere('id', $request->user()->id);
            })
            ->orderBy('account_type')
            ->orderBy('name')
            ->get();

        return AccountResource::collection($accounts);
    }

    /**
     * Create an admin or staff account.
     */
    public function store(StoreAccountRequest $request, AccountService $accounts, AccountSetupLinkService $setupLinks): JsonResponse|JsonResource
    {
        $account = $accounts->create($request->user(), $request->validated());

        // Sent by default; the password is then chosen by the account holder. Without e-mail the link is returned once.
        $setup = $request->boolean('send_setup_link') ? $setupLinks->send($account, $request->user()) : null;

        return (new AccountResource($account->load('accountPosition')))
            ->additional(['setup' => $setup])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Change an account's details, access role, invitation permission, position, password or active state.
     */
    public function update(UpdateAccountRequest $request, User $user, AccountService $accounts): JsonResource
    {
        $account = $accounts->update($request->user(), $user, $request->validated());

        return new AccountResource($account->load('accountPosition'));
    }

    /**
     * An administrator verifies an account's business details, which locks them for the account holder.
     */
    public function verifyBusiness(Request $request, User $user, ProfileService $profile): JsonResource
    {
        $account = $profile->verifyBusiness($request->user(), $user);

        return new AccountResource($account->load('accountPosition'));
    }

    /**
     * The SSM document of an account, for the administrator who verifies it.
     */
    public function businessDocument(User $user, BusinessDocument $documents): Response
    {
        return $documents->response($user);
    }
}
