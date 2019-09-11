<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller;

use think\Env;
use think\Controller;
use app\api\library\PayonnerApi;


class Test extends Controller
{
    public function _initialize()
    {
        if (!Env::get('app.debug')) \abort(404, '');
    }

    /*
     {
    "scope": "https://api.paypal.com/v1/payments/.* https://uri.paypal.com/services/payments/refund https://uri.paypal.com/services/applications/webhooks https://uri.paypal.com/services/payments/payment/authcapture https://uri.paypal.com/payments/payouts https://api.paypal.com/v1/vault/credit-card/.* https://uri.paypal.com/services/reporting/search/read https://uri.paypal.com/services/disputes/read-seller https://uri.paypal.com/services/subscriptions https://uri.paypal.com/services/disputes/read-buyer https://api.paypal.com/v1/vault/credit-card openid https://uri.paypal.com/services/disputes/update-seller https://uri.paypal.com/services/payments/realtimepayment",
    "nonce": "2018-12-07T03:10:39Z0Nd0G5kyOn7bkGbe6ax4se3THHER54n9zSNhCZAloVY",
    "access_token": "A21AAFYBk3UtuTMha577a_nORl7i_ncG5ftJHiWqbgmyjJCb42kFxF8HVRsEYFIz7Z8EmHAF8InBfSAUDqwB6lfYLPAGho4Hg",
    "token_type": "Bearer",
    "app_id": "APP-80W284485P519543T",
    "expires_in": 32400
}
     */

    public function testPaypal()
    {
        $clientid = Env::get('paypal.clientid');
        $secret = Env::get('paypal.secret');
        $sandboxaccount = Env::get('paypal.sandboxaccount');

        $access_token = 'A21AAFYBk3UtuTMha577a_nORl7i_ncG5ftJHiWqbgmyjJCb42kFxF8HVRsEYFIz7Z8EmHAF8InBfSAUDqwB6lfYLPAGho4Hg';

        echo '<pre>';var_dump($clientid, $secret, $sandboxaccount);echo '</pre>';
        exit;
    }


    public function testPayoneer()
    {
        // $redirect_url = 'http://www.oobest.com';
        // $sb_api = 'https://api.sandbox.payoneer.com';
        // $program_id = "100070840";
        // $api_username = 'Zhuoshi0840';
        // $api_pwd = 'fc8oxBV1007';

        // $api_url = "https://api.sandbox.payoneer.com/v2/programs/{$program_id}/echo";

        // $base = "{$api_username}:{$api_pwd}";
        // $auth = base64_encode($base);
        // $headers = [
        //     'authorization: Basic ' . $auth
        // ];

        // $ret_data = curl_get($api_url, $headers);
        // echo '<pre>';var_dump($api_url, $headers, $base, $auth, $ret_data);echo '</pre>';
        // exit;

        // echo '<pre>';var_dump(PayonnerApi::getInstance()->echo());echo '</pre>';
        // exit;

        // echo '<pre>';var_dump(PayonnerApi::getInstance()->apiVersion());echo '</pre>';
        // exit;

        // echo '<pre>';var_dump(PayonnerApi::getInstance()->loginLink());echo '</pre>';
        // echo '<pre>';var_dump(PayonnerApi::getInstance()->status());echo '</pre>';
        // echo '<pre>';var_dump(PayonnerApi::getInstance()->receivingAccounts());echo '</pre>';
        echo '<pre>';var_dump(PayonnerApi::getInstance()->payeeReport());echo '</pre>';
        exit;
    }

}
/*
// get payment list
//curl -v -X GET https://api.sandbox.paypal.com/v1/payments/payment?count=10&start_index=0&sort_by=create_time&sort_order=desc -H "Content-Type: application/json" -H "Authorization: Bearer A21AAFYBk3UtuTMha577a_nORl7i_ncG5ftJHiWqbgmyjJCb42kFxF8HVRsEYFIz7Z8EmHAF8InBfSAUDqwB6lfYLPAGho4Hg"

// get invoice list
curl -v -X GET https://api.sandbox.paypal.com/v1/invoicing/invoices?page=3&page_size=4&total_count_required=true -H "Content-Type: application/json" -H "Authorization: Bearer A21AAFYBk3UtuTMha577a_nORl7i_ncG5ftJHiWqbgmyjJCb42kFxF8HVRsEYFIz7Z8EmHAF8InBfSAUDqwB6lfYLPAGho4Hg"

// get billing plans
curl -v -X GET https://api.sandbox.paypal.com/v1/payments/billing-plans?page_size=3&status=ALL&page_size=2&page=1&total_required=yes -H "Content-Type: application/json" -H "Authorization: Bearer A21AAFYBk3UtuTMha577a_nORl7i_ncG5ftJHiWqbgmyjJCb42kFxF8HVRsEYFIz7Z8EmHAF8InBfSAUDqwB6lfYLPAGho4Hg"


curl -X POST https://api.sandbox.payoneer.com/v2/programs/100070840/payees/login-link -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" -d "payee_id=qPM5TXBgSlgOrfR" 

curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/status -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" 

curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/receiving-accounts -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" 

curl -X GET 'https://api.sandbox.payoneer.com/v2/programs/100070840/reports/payee_details?payee_id=unique Zhuoshi Payee ID' -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" 

curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/reports/payees_status?start_date=2018-10-01&end_date=2018-11-01 -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc="

curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/details -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc="

curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/balances -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc="

*/
