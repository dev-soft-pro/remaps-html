<?php

namespace App\Http\Controllers;

use App\Events\ChatEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Helpers\Helper;
use App\Models\TuningType;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\Styling;
use App\Models\User;
use App\Models\FileService;
use App\Models\Shop\ShopCategory;
use App\Models\Order;
use App\Models\EmailTemplate;
use App\Models\Api\ApiUser;
use App\Models\NotificationRead;
use App\Models\AdminupdateRead;
use App\Models\TuningTypeGroup;
use App\Models\EmailFlag;
use PragmaRX\Google2FAQRCode\Google2FA;

class ApiController extends Controller
{
    //
    public function tuning_type_options(Request $request, $id) {
        $user = User::find($request->user);
        $group = $user->tuningTypeGroup;
        $optionIDs = $group->tuningTypeOptions()->pluck('id')->toArray();
        $res = TuningType::find($id)
            ->tuningTypeOptions()
            ->whereIn('id', $optionIDs)
            ->orderBy('order_as')
            ->get();
        foreach ($res as $option) {
            $groupOption = $group->getOneOption($option->id);
            $option->credits = $groupOption->pivot->for_credit;
        }
        return $res;
    }

    public function car_query(Request $request)
    {
        if ($request->make == '') {
            $makes = Car::groupBy('brand')->pluck('brand');
            return $makes;
        }
        if ($request->model == '') {
            $models = Car::where('brand', $request->make)
                ->groupBy('model')
                ->pluck('model');
            return $models;
        }
        if ($request->generation == '') {
            $generations = Car::where('brand', $request->make)
                ->where('model', $request->model)
                ->groupBy('year')
                ->pluck('year');
            return $generations;
        }
        if ($request->engine == '') {
            $engines = Car::where('brand', $request->make)
                ->where('model', $request->model)
                ->where('year', $request->generation)
                ->get();
            return $engines;
        }
    }

    public function car_id(Request $request)
    {
        $id = Car::where('brand', $request->make)
            ->where('model', $request->model)
            ->where('year', $request->generation)
            ->where('engine_type', $request->engine)
            ->first()->id;
        return $id;
    }

    public function change_style(Request $request)
    {
        $company = Company::find($request->input('company'));
        $styling = Styling::where('company_id', $company->id)->first();
        $styleObj = (array)json_decode($styling->data);
        if ($request->input('type') == 'theme') {
            switch($request->input('value')) {
                case '':
                    $styleObj['theme'] = 'light';
                    break;
                case 'dark-layout':
                    $styleObj['theme'] = 'dark';
                    break;
                case 'semi-dark-layout':
                    $styleObj['theme'] = 'semi-dark';
                    break;
                case 'bordered-layout':
                    $styleObj['theme'] = 'bordered';
                    break;
            }
        } else if ($request->input('type') == 'layoutWidth') {
            $styleObj['layoutWidth'] = $request->input('value');
        } else if ($request->input('type') == 'navbarColor') {
            $styleObj['navbarColor'] = $request->input('value');
        } else if ($request->input('type') == 'navbarType') {
            if ($styleObj['mainLayoutType'] == 'vertical') {
                $styleObj['verticalMenuNavbarType'] = $request->input('value');
            } else if ($styleObj['mainLayoutType'] == 'horizontal') {
                $styleObj['horizontalMenuType'] = $request->input('value');
            }
        } else if ($request->input('type') == 'footerType') {
            $styleObj['footerType'] = $request->input('value');
        }
        $styling->data = json_encode($styleObj);
        $styling->save();
        return $request->input('value');
    }

    public function readShopGuide(Request $request) {
        $company = Company::find($request->id);
        $company->is_first_shop = 1;
        $company->save();
    }

    public function openShop(Request $request) {
        $company = Company::find($request->id);
        $company->is_open_shop = 1;
        $company->save();
    }

    public function sendIM(Request $request) {
        $message = ChatMessage::create($request->all());
        event(new ChatEvent($message));
    }

    public function getAdminUsers(Request $request) {
        $admin = User::find(Company::find($request->company_id)->owner->id);
        $lastMessage = ChatMessage::where('target', $request->user_id)
                ->orderBy('created_at', 'DESC')
                ->first();
        $unreadCt = ChatMessage::where('target', $request->user_id)
            ->orderBy('created_at', 'DESC')
            ->where('is_read', 0)
            ->where('to', 0)
            ->count();
        return [[
            'id' => $admin->id,
            'name' => $admin->first_name.' '.$admin->last_name,
            'msg' => $lastMessage ? $lastMessage->message : '',
            'date' => $lastMessage ? \Carbon\Carbon::parse($lastMessage->created_at)->diffForHumans() : '',
            'count' => $unreadCt,
            'avatar' => [
                'color' => Helper::generateAvatarColor($admin->id),
                'name' => Helper::getInitialName($admin->id)
            ]
        ]];
    }

    public function getChatUsers(Request $request) {
        $messageUserIDs = ChatMessage::where('company_id', $request->company_id)
            ->select('target')
            ->groupBy('target')
            ->pluck('target')
            ->toArray();
        $mUsers = User::whereIn('id', $messageUserIDs)->get();
        $cUsers = User::whereNotIn('id', $messageUserIDs)
            ->where('company_id', $request->company_id)
            ->where('is_admin', '!=', 1)
            ->whereNull('is_staff')
            ->get();

        $mUserRes = array();
        foreach($mUsers as $muser) {
            $lastMessage = ChatMessage::where('target', $muser->id)
                ->orderBy('created_at', 'DESC')
                ->first();
            $unreadCt = ChatMessage::where('target', $muser->id)
                ->orderBy('created_at', 'DESC')
                ->where('is_read', 0)
                ->where('to', 1)
                ->count();
            array_push($mUserRes, [
                'id' => $muser->id,
                'name' => $muser->first_name.' '.$muser->last_name,
                'msg' => $lastMessage->message,
                'date' => \Carbon\Carbon::parse($lastMessage->created_at)->diffForHumans(),
                'count' => $unreadCt,
                'logo' => $muser->logo,
                'avatar' => [
                    'color' => Helper::generateAvatarColor($muser->id),
                    'name' => Helper::getInitialName($muser->id)
                ]
            ]);
        }

        $cUserRes = array();
        foreach($cUsers as $cuser) {
            array_push($cUserRes, [
                'id' => $cuser->id,
                'name' => $cuser->first_name.' '.$cuser->last_name,
                'logo' => $cuser->logo,
                'avatar' => [
                    'color' => Helper::generateAvatarColor($cuser->id),
                    'name' => Helper::getInitialName($cuser->id)
                ]
            ]);
        }

        return [
            'm' => $mUserRes,
            'c' => $cUserRes
        ];
    }

    public function getChatMessages(Request $request) {
        $day_key = 'Date(created_at)';
        $messageDates = ChatMessage::where('company_id', $request->company_id)
            ->where('target', $request->target)
            ->orderBy('created_at', 'ASC')
            ->groupBy(DB::raw($day_key))
            ->select(DB::raw($day_key))
            ->get();
        $messageGroups = array();
        foreach ($messageDates as $md) {
            $msgs = ChatMessage::where('company_id', $request->company_id)
                ->where('target', $request->target)
                ->where('created_at', 'like', $md->$day_key.'%')
                ->orderBy('created_at', 'ASC')
                ->get();
            $messageGroups[$md->$day_key] = array();
            $groupDir = '';
            $group = array();
            foreach ($msgs as $msg) {
                if ($msg->to != $groupDir && count($group) > 0) {
                    array_push($messageGroups[$md->$day_key], $group);
                    $group = array($msg);
                } else {
                    array_push($group, $msg);
                }
                $groupDir = $msg->to;
            }
            array_push($messageGroups[$md->$day_key], $group);
        }

        $company = Company::find($request->company_id);
        $unreadCt = ChatMessage::where('company_id', $request->company_id)
            ->where('target', $request->target)
            ->where('to', 0)
            ->where('is_read', 0)
            ->count();

        return [
            'message' => $messageGroups,
            'avatarU' => [
                'color' => Helper::generateAvatarColor($request->target),
                'name' => Helper::getInitialName($request->target),
                'logo' => User::find($request->target)->logo
            ],
            'avatarC' => [
                'color' => Helper::generateAvatarColor($company->owner->id),
                'name' => Helper::getInitialName($company->owner->id),
                'logo' => User::find($company->owner->id)->logo
            ],
            'unreadCt' => $unreadCt
        ];
    }

    public function readAll(Request $request) {
        ChatMessage::where('company_id', $request->company_id)
            ->where('target', $request->target)
            ->where('to', $request->to)
            ->update([
                'is_read' => 1
            ]);
    }

    public function unreadCount(Request $request) {
        $user = User::find($request->user_id);
        return $user->unread_chats;
    }

    public function uploadDigital(Request $request) {
        if($request->hasFile('file')){
            if($request->file('file')->isValid()){
                $file = $request->file('file');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $org_filename = $file->getClientOriginalName();
                if($file->move(storage_path('app/public/uploads/products/digital/'), $filename)){
                    return response()->json([
                        'status'=> TRUE,
                        'file' => $filename,
                        'remain' => $org_filename
                    ], 200);
                }else{
                    return response()->json(['status'=> FALSE], 404);
                }
            }else{
                return response()->json(['status'=> FALSE], 404);
            }
        }else{
            return response()->json(['status'=> FALSE], 404);
        }
    }

    public function currentTree(Request $request) {
        $categories = ShopCategory::where('company_id', $request->company_id)->get()->toArray();
        $roots = ShopCategory::where('parent_category', 0)->get()->toArray();
        $categories = array_merge($categories, $roots);
        $tree = function ($elements, $parentId = 0) use (&$tree) {
            $branch = array();
            foreach ($elements as $element) {
                if ($element['parent_category'] == $parentId) {
                    $children = $tree($elements, $element['id']);
                    if ($children) {
                        $element['children'] = $children;
                    } else {
                        $element['children'] = [];
                    }
                    $element['text'] = $element['name'];
                    $element['state'] = [
                        'opened' => true
                    ];
                    $branch[] = $element;
                }
            }
            return $branch;
        };
        $tree = $tree($categories);
        return $tree;
    }

    public function createShopCategory(Request $request) {
        ShopCategory::create($request->all());
        return $this->currentTree($request);
    }

    public function deleteShopCategory(Request $request) {
        ShopCategory::find($request->id)->delete();
        return $this->currentTree($request);
    }

    public function updateParentShopCategory(Request $request) {
        ShopCategory::whereIn('id', $request->ids)->update([
            'parent_category' => $request->parent_category
        ]);
    }

    public function uploadInvoicePdf(Request $request) {
        if($request->hasFile('file')){
            if($request->file('file')->isValid()){
                $file = $request->file('file');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $org_filename = $file->getClientOriginalName();
                if($file->move(storage_path('app/public/uploads/invoice/'), $filename)){
                    $order = Order::find($request->get('order'));
                    $order->update([
                        'document' => $filename
                    ]);
                    return response()->json([
                        'status'=> TRUE,
                        'file' => $filename,
                        'id' => $order->id,
                    ], 200);
                }else{
                    return response()->json(['status'=> FALSE], 404);
                }
            }else{
                return response()->json(['status'=> FALSE], 404);
            }
        }else{
            return response()->json(['status'=> FALSE], 404);
        }
    }

    public function snippet(Request $request) {
        $userid = $request->id;

        $apiuser = ApiUser::find($userid);

        // if (!$apiuser->hasActiveSubscription()) {
        //     return redirect()->route('api.snippet.error');
        // }

        $theme = 'dark';
        if ($request->has('theme')) {
            $theme = $request->get('theme');
        }
        $color = $theme == 'light' ? '212529' : 'ffffff';
        if ($request->has('color')) {
            $color = $request->get('color');
        }
        $btextcolor = $theme == 'dark' ? '212529' : 'ffffff';
        if ($request->has('btextcolor')) {
            $btextcolor = $request->get('btextcolor');
        }
        $background = '';
        if ($request->has('background')) {
            $background = $request->get('background');
        }
        $px = 0;
        if ($request->has('px')) {
            $px = $request->get('px');
        }
        $py = 0;
        if ($request->has('py')) {
            $py = $request->get('py');
        }

        $snippetRoute = route('api.snippet.show', [
            'id' => $userid,
            'theme' => $theme,
            'color' => $color,
            'btextcolor' => $btextcolor,
            'background' => $background,
            'px' => $px,
            'py' => $py
        ]);

        return view('snippet.js')->with([
            'uid' => $userid,
            'snippetRoute' => $snippetRoute
        ]);
    }

    public function showsnippet(Request $request) {
        $res = array();
        $brands = Car::groupBy('brand')->pluck('brand');
        foreach($brands as $i => $b) {
            array_push($res, [
                'brand' => $b,
                'logo' => asset('images/carlogo/'.str_replace(" ", "-", strtolower($b)).'.jpg')
            ]);
        }

        $theme = 'dark';
        if ($request->has('theme')) {
            $theme = $request->get('theme');
        }
        $color = $theme == 'light' ? '212529' : 'ffffff';
        if ($request->has('color')) {
            $color = $request->get('color');
        }
        $btextcolor = $theme == 'dark' ? '212529' : 'ffffff';
        if ($request->has('btextcolor')) {
            $btextcolor = $request->get('btextcolor');
        }
        $background = '';
        if ($request->has('background')) {
            $background = $request->get('background');
        }
        $px = 10;
        if ($request->has('px')) {
            $px = $request->get('px');
        }
        $py = 10;
        if ($request->has('py')) {
            $py = $request->get('py');
        }

        $userid = $request->id;
        $apiuser = ApiUser::find($userid);
        if (!$apiuser->hasActiveSubscription()) {
            Log::info('out of active subscription '.$userid);
            return redirect()->route('api.snippet.error');
        }

        $dm = '';
        if ($request->has('dm')) {
            $dm = $request->get('dm');
        }

        try {
            $orgDomain = parse_url($apiuser->domain)['host'];
            $wwwDomain = 'www.'.$orgDomain;
            if (!$dm) {
                $curDomain = parse_url($_SERVER['HTTP_REFERER'])['host'];
                if ($orgDomain != $curDomain && $wwwDomain != $curDomain) {
                    Log::info('$orgDomain != $curDomain '.$orgDomain.' : '.$curDomain);
                    return redirect()->route('api.snippet.error');
                }
                $dm = $curDomain;
            } else {
                if ($dm != $orgDomain && $dm != $wwwDomain) {
                    Log::info('$dm != $curDomain '.$dm.' : '.$curDomain);
                    return redirect()->route('api.snippet.error');
                }
            }
        } catch (\Exception $ex){
            Log::info($ex->getMessage());
            return redirect()->route('api.snippet.error');
        }

        return view('snippet.content')->with([
            'id' => $userid,
            'brands' => $res,
            'theme' => $theme,
            'color' => $color,
            'btextcolor' => $btextcolor,
            'background' => $background,
            'px' => $px,
            'py' => $py,
            'dm' => $dm
        ]);
    }

    public function snippet_search(Request $request) {
        $make = $request->brand;
        $models = Car::where('brand', $make)->groupBy('model')->pluck('id', 'model');

        $theme = 'dark';
        if ($request->has('theme')) {
            $theme = $request->get('theme');
        }
        $color = $theme == 'light' ? '212529' : 'ffffff';
        if ($request->has('color')) {
            $color = $request->get('color');
        }
        $btextcolor = $theme == 'dark' ? '212529' : 'ffffff';
        if ($request->has('btextcolor')) {
            $btextcolor = $request->get('btextcolor');
        }
        $background = '';
        if ($request->has('background')) {
            $background = $request->get('background');
        }
        $px = 10;
        if ($request->has('px')) {
            $px = $request->get('px');
        }
        $py = 10;
        if ($request->has('py')) {
            $py = $request->get('py');
        }

        $id = $request->id;
        $apiuser = ApiUser::find($id);
        if (!$apiuser->hasActiveSubscription()) {
            return redirect()->route('api.snippet.error');
        }

        $dm = '';

        try {
            $orgDomain = parse_url($apiuser->domain)['host'];
            $wwwDomain = 'www.'.$orgDomain;
            if (!$request->has('dm') || $request->get('dm') != $orgDomain && $request->get('dm') != $wwwDomain) {
                return redirect()->route('api.snippet.error');
            }
            $dm = $request->get('dm');
        } catch (\Exception $ex){
            return redirect()->route('api.snippet.error');
        }

        return view('snippet.search', compact('id', 'models', 'make', 'theme', 'color', 'btextcolor', 'background', 'px', 'py', 'dm'));
    }

    public function snippet_search_post(Request $request) {
        $engine = $request->engine;
        $car = $engines = Car::find($engine);
        $logofile = str_replace(" ", "-", strtolower($car->brand));
        $logofile = asset('images/carlogo/'.$logofile.'.jpg');

        $company = Company::where('is_default', 1)->first();

        $id = $request->id;
        $apiuser = ApiUser::find($id);

        if (!$apiuser->hasActiveSubscription()) {
            return redirect()->route('api.snippet.error');
        }

        $body = $apiuser->body;
        if (!$body) {
            $template = EmailTemplate::where('company_id', $company->id)->where('label', 'car-data-text')->first(['subject', 'body']);
            $body = $template->body;
        }
        $body = str_replace('##COMPANY_NAME', $apiuser->company, $body);
        $body = str_replace('##CAR_MODEL', $car->title, $body);

        $theme = 'dark';
        if ($request->has('theme')) {
            $theme = $request->get('theme');
        }
        $color = $theme == 'light' ? '212529' : 'ffffff';
        if ($request->has('color')) {
            $color = $request->get('color');
        }
        $btextcolor = $theme == 'dark' ? '212529' : 'ffffff';
        if ($request->has('btextcolor')) {
            $btextcolor = $request->get('btextcolor');
        }
        $background = '';
        if ($request->has('background')) {
            $background = $request->get('background');
        }
        $px = 10;
        if ($request->has('px')) {
            $px = $request->get('px');
        }
        $py = 10;
        if ($request->has('py')) {
            $py = $request->get('py');
        }

        $dm = '';
        try {
            $orgDomain = parse_url($apiuser->domain)['host'];
            $wwwDomain = 'www.'.$orgDomain;
            if (!$request->has('dm') || $request->get('dm') != $orgDomain && $request->get('dm') != $wwwDomain) {
                return redirect()->route('api.snippet.error');
            }
            $dm = $request->get('dm');
        } catch (\Exception $ex){
            return redirect()->route('api.snippet.error');
        }

        return view('snippet.searchresult', compact('id', 'car', 'logofile', 'body', 'theme', 'color', 'btextcolor', 'background', 'px', 'py' ,'dm'));
    }

    public function bug() {
        return view('snippet.bug');
    }

    public function getCarTextTemplate() {
        $company = Company::where('is_default', 1)->first();
        $template = \App\Models\EmailTemplate::where('company_id', $company->id)->where('label', 'car-data-text')->first(['subject', 'body']);
        return $template->body;
    }

    public function getNotifies() {
        $user = User::find(request()->userid);
        if (is_null($user)) {
            return false;
        }
        $notifies = array_filter($user->notifies()->distinct()->get()->toArray(), function($obj) use($user){
            $readObj = NotificationRead::where('notification_id', $obj['id'])->where('user_id', $user->id)->first();
            if (is_null($readObj)) {
                return false;
            }
            if ($readObj->is_read == 1) {
                return false;
            }
            return true;
        });
        return $notifies;
    }

    public function getEmailNotifies() {
        $user = User::find(request()->userid);
        if (is_null($user)) {
            return false;
        }
        $emailFlags = $user->company->emailFlags;
        return $emailFlags;
    }

    public function readEmailNotify(Request $request) {
        EmailFlag::find($request->id)->update([
            'is_email_failed' => 0
        ]);
    }

    public function getSideBarCounts() {
        $user = User::find(request()->userid);
        if (is_null($user)) {
            return false;
        }
        $tickets = count($user->unread_tickets);
        $chats = $user->unread_chats;
        return [
            'tickets' => $tickets,
            'chats' => $chats
        ];
    }

    public function adminupdate_read_one(Request $request) {
        AdminupdateRead::where('adminupdate_id', $request->id)
            ->where('company_id', $request->company)
            ->first()
            ->update([
                'is_read' => 1
            ]);
    }

    public function twofa_check(Request $request) {
        $company = Company::find($request->input('company_id'));
        $secretKey = $company->secret_2fa_key;
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secretKey, $request->input('code'));

        return [
            'verified' => $valid
        ];
    }

    public function uk_car_query(Request $request) {
        $url = "https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
           "x-api-key: du0EYdugUh10pkYuIbCyHa0VomA6vpgTas11S8Vz",
           "Content-Type: application/json",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $data = '{"registrationNumber": "' . $request->registrationNumber . '"}';

        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);

        return $resp;
    }
}
