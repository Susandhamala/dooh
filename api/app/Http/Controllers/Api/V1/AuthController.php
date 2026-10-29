<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MfaCodeRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SsoExchangeRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use App\Services\MfaService;
use App\Services\Sso\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth, private MfaService $mfa, private SsoService $sso) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->session($this->auth->register($request->validated()), 'Account created.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->session($this->auth->login($request->input('email'), $request->input('password')), 'Signed in.');
    }

    public function sso(SsoExchangeRequest $request): JsonResponse
    {
        return $this->session($this->sso->login($request->input('code')), 'Signed in with rentallnepal.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()->load('roles')), meta: [
            'mfa_verified' => auth('api')->payload()->get('mfa') === true,
        ]);
    }

    public function refresh(): JsonResponse
    {
        return ApiResponse::success($this->auth->refresh(), 'Token refreshed.');
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return ApiResponse::success(null, 'Signed out.');
    }

    public function mfaSetup(Request $request): JsonResponse
    {
        return ApiResponse::success($this->mfa->setup($request->user()), 'Scan the code with your authenticator app, then confirm.');
    }

    public function mfaEnable(MfaCodeRequest $request): JsonResponse
    {
        return $this->session($this->mfa->enable($request->user(), $request->input('code')), 'Two-factor authentication enabled.');
    }

    public function mfaVerify(MfaCodeRequest $request): JsonResponse
    {
        return $this->session($this->mfa->verify($request->user(), $request->input('code')), 'Two-factor verified.');
    }

    private function session(array $result, string $message, int $status = 200): JsonResponse
    {
        $result['user'] = new UserResource($result['user']);

        return ApiResponse::success($result, $message, $status);
    }
}
