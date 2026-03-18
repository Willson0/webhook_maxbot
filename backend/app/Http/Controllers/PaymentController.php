<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Stats;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use YooKassa\Client;

class PaymentController extends Controller
{
    public function buy (Request $request) {
        $tg = config('price.tg');

        $user = User::where("id", $request["user_id"])->firstOrFail();
        if (!$user->email) abort (409, "Email not found");
        if (!$user->payment_method_id) abort (409, "Payment method not found");

        $client = new Client();
        $client->setAuth(env("SHOP_ID"), env("YOOKASSA_API_KEY"));

        $botname = env("BOT_NAME");
        $response = $client->createPayment(
            [
                'amount' => [
                    'value' =>  number_format(max(1, $request->rub_summ), 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'capture' => true,
                'payment_method_id' => $user->payment_method_id,
                'description' => "Подписка на $request->days дней по тарифу \"$request->sub\" в " . ($tg ? 'Телеграм' : 'Максе') . " - сервисе @$botname",
                'receipt' => [
                    'customer' => [
                        'email' => $user->email,
                    ],
                    'items' => [
                        [
                            'description' =>  "Подписка на $request->days дней по тарифу \"$request->sub\" в " . ($tg ? 'Телеграм' : 'Максе') . " - сервисе @$botname",
                            'quantity' => '1.00',
                            'amount' => [
                                'value' => number_format(max(1, $request->rub_summ), 2, '.', ''),
                                'currency' => 'RUB',
                            ],
                            'vat_code' => 2,
                            'payment_mode' => 'full_payment',
                            'payment_subject' => 'commodity',
                        ],
                    ],
                ],
            ],
            $user->id . "_" . $request->sub . "_" . time()
        );
        $paymentID = $response->id;

        $payment = Payment::create([
            "user_id" => $user->id,
            "is_autopayment" => 0,
            "payment_id" => $paymentID,
            "is_bought" => false,
            "rub_summ" => $request->rub_summ,
            "summ" => $request->rub_summ,
            "days" => $request->days,
            "sub" => $request->sub,
        ]);

        return response()->json(["ok" => true]);
    }

    public function webhook (Request $request)
    {
        $tg = config('price.tg');

        Log::info($request);
        $payment = Payment::where("payment_id", $request->object["id"] ?? $request["id"])->first();

        if ($payment->is_bought) return response('ok', 200);
        if ($request->event === "payment.succeeded" || $request->status === "succeeded") {
            $user = User::find($payment->user_id);
            if (!$user) {
                Log::error("User not found $payment->id: $payment->user_id");
                return response('ok', 200);
            }
            if ($request->object["payment_method"]["saved"] === true ?? null) $user->payment_method_id = $request->object["payment_method"]["id"];

            $user->paid_money += $payment->rub_summ;
            if ($payment->sub === "tokens") $user->bought_tokens += $payment->days;
            else if ($payment->sub === 'img_generations') $user->image_generations += $payment->days;
            else {
                if ($user->tariff !== $payment->sub) {
                    $user->tariff = $payment->sub;
                    $user->tariff_time = Carbon::now()->addDays($payment->days)->timestamp;
                    $user->orig_tariff = $payment->sub . "_0";
                    $user->start_sub_time = Carbon::now()->timestamp;
                } else {
                    try {
                        $dayTries = Stats::firstOrCreate(
                            ['name' => 'dayTries'],
                            ['date' => null, 'params' => 0]
                        );

                        $dayTries->update([
                            "params" => $dayTries->params + (intval(explode('_', $user->orig_tariff)[1]) + 1)
                        ]);

                        $subAutocontSuccess = Stats::firstOrCreate(
                            ["name" => "subAutocontSuccess"],
                            ["date" => null, "params" => 0]
                        );
                        $subAutocontSuccess->update([
                            "params" => $subAutocontSuccess->params + 1,
                        ]);

                        $dayAutocontinueCount = Stats::firstOrCreate(
                            ["name" => "dayAutocontinueCount"],
                            ["date" => null, "params" => 0]
                        );
                        if ($user->is_trial_sub === 1)
                            $dayAutocontinueCount->update([
                                "params" => $dayAutocontinueCount->params + 1,
                            ]);

                        $lastPayment = Payment::where("user_id", $user->id)->where("is_bought", 1)->orderBy("id", "desc")->first();
                        if ($lastPayment->tariff == "pro" AND ($lastPayment->rub_summ == 1 || $lastPayment->rub_summ == '1')) {
                            $autocontSuccess = Stats::firstOrCreate(
                                ["name" => "autocontSuccess"],
                                ["date" => null, "params" => 0]
                            );
                            $autocontSuccess->update([
                                "params" => $autocontSuccess->params + 1,
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::error($e);
                    };

                    $user->tariff_time = Carbon::now()->addDays($payment->days)->timestamp;
                    $user->is_trial_sub = 0;
                }

                $dailyTokens = config('price.dailyTokens');
                $IMAGE_GENERATIONS = config('price.imageGenerations');
                if ($payment->rub_summ == 1 || $payment->rub_summ == '1') {
                    $user->tariff_tokens = $dailyTokens['trial'];
                    $user->image_generations = $IMAGE_GENERATIONS["trial"];
                    $user->is_trial_sub = 1;
                    $user->tried_free_smart = 1;

                    try {
                        $trialBuys = Stats::firstOrCreate(
                            ["name" => "trialBuys"],
                            ["date" => null, "params" => 0]
                        );
                        $trialBuys->update([
                            "params" => $trialBuys->params + 1,
                        ]);
                    } catch (Exception $e) {
                        Log::error($e);
                    }
                }
                else {
                    if ($payment->days === 7) $user->image_generations = 5;
                    else $user->image_generations = $IMAGE_GENERATIONS[$user->tariff];
                    $user->tariff_tokens = $dailyTokens[$user->tariff];
                    $user->is_trial_sub = 0;
                }
            }

            $user->save();
            $payment->is_bought = true;

            $botToken = env('TELEGRAM_BOT_TOKEN');
            if ($payment->sub === "tokens") $text = "✅ Оплата успешно прошла! На ваш аккаунт добавлено {$payment->days} токенов";
            else $text = "<b>🎉 Классно, оплата прошла успешно!</b>\n\n❤️ Спасибо, что выбрали нас!\nЕсли будут вопросы — всегда здесь, чтобы помочь!";

            $data = [
                'chat_id' => $user->id,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            if ($tg)
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $data);
            else {
                $resp = Http::post("https://platform-api.max.ru/messages?user_id=$user->id&chat_id=$user->chat_id", [
                    'text'    => $text,
                    "format"  => "html"
                ])->withHeader("Authorization", $botToken);
                try {
                    Log::debug('JSON:', ['data' => json_encode($resp)]);
                } catch (Exception $e) {}
            }
        } else if (($request->event === "payment.canceled" || $request->status === "canceled") && $payment->is_autopayment) {
            $user = User::find($payment->user_id);
            $user->tariff = "free";
            $user->is_trial_sub = 0;
            $user->tariff_tokens = 10000;

            $try = intval(explode('_', $user->orig_tariff)[1]);

            if (intval($payment->days) === 30) {
                $PRICES = config('price.prices');

                $this->buy(new Request([
                    "user_id" => $user->id,
                    "sub" => "pro",
                    "days" => 7,
                    "rub_summ" => $PRICES[7],
                    "summ" => $PRICES[7],
                ]));
            }

            if ($try === 4) {
                $user->orig_tariff = "free";
                $user->tariff_time = 0;
                $user->save();
                return response("ok", 200);
            }

            $user->orig_tariff = $payment->sub . "_" . ($try + 1);

            $SPISANIE_TIMES_OSN = config('price.withdrawTime');
            $tryIndex = $try + 1;
            $seconds = isset($SPISANIE_TIMES_OSN[$tryIndex]) ? (int)$SPISANIE_TIMES_OSN[$tryIndex] : 0;
            $user->tariff_time = Carbon::now()->addSeconds($seconds)->timestamp;

            $user->start_sub_time = 0;

            $user->save();
        }

        $payment->save();
        return response()->json();
    }
}
