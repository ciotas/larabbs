<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VerificationCodeRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mews\Captcha\Facades\Captcha;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;

class VerificationCodesController extends Controller
{
    public function store(VerificationCodeRequest $request, EasySms $easySms)
    {
        $captchaData = Captcha::get($request->captcha_key);

        if (!$captchaData) {
            abort(403, '图片验证码已失效');
        }
        if (!hash_equals($captchaData['code'], $request->captcha_code)) {
            // 验证错误就清除缓存
            Cache::forget($request->captcha_key);
            throw new AuthenticationException('验证码错误');
        }

        $phone = $captchaData['phone'];

        if (! app()->environment('production')) {
            $code = '1234';
        } else {
            // 生成4位随机数，左侧补0
            $code = str_pad(random_int(1, 9999), 4, 0, STR_PAD_LEFT);
            $content = '您的验证码是'. $code .'，有效期为5分钟，请尽快验证。';

            try {
                $request = $easySms->send($phone, ['content'  => $content]);
            } catch (NoGatewayAvailableException $exception) {
                $message = $exception->getException('yunpian')->getMessage();
                abort(500, $message ?: '短信发送异常');
            }
        }

        $key = 'verificationCode_'.Str::random(15);
        $expiredAt = now()->addMinutes(5);
        // 缓存验证码 5 分钟过期。
        Cache::put($key, ['phone' => $phone, 'code' => $code], $expiredAt);
        // 清除图片验证码缓存
        Cache::forget($request->captcha_key);

        return response()->json([
            'key' => $key,
            'expired_at' => $expiredAt->toDateTimeString()
        ])->setStatusCode(201);
    }
}
